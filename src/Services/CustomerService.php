<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;
use PDO;

class CustomerService
{
    /**
     * Get 4 Top KPI Cards matching the enterprise dashboard mockup.
     */
    public static function getCustomerKpis(): array
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_customers,
                SUM(CASE WHEN total_orders > 1 THEN 1 ELSE 0 END) as repeat_buyers,
                COALESCE(SUM(total_spent_amount), 0) as lifetime_revenue,
                COALESCE(SUM(total_orders), 0) as total_orders
            FROM customers
            WHERE website_id = :wid AND (is_anonymized IS NULL OR is_anonymized = 0)
        ");
        $stmt->execute([':wid' => $wid]);
        $row = $stmt->fetch();

        $totalCustomers = (int)($row['total_customers'] ?? 0);
        $repeatCustomers = (int)($row['repeat_buyers'] ?? 0);
        $lifetimeRevenue = (int)($row['lifetime_revenue'] ?? 0);
        $totalOrders = (int)($row['total_orders'] ?? 0);

        // Also query orders table directly if orders exist
        $ordStmt = $db->prepare("
            SELECT 
                COUNT(*) as ord_count,
                COALESCE(SUM(grand_total_amount), 0) as ord_rev
            FROM orders
            WHERE website_id = :wid AND order_status != 'CANCELLED'
        ");
        $ordStmt->execute([':wid' => $wid]);
        $ordRow = $ordStmt->fetch();
        if ($ordRow) {
            $totalOrders = max($totalOrders, (int)($ordRow['ord_count'] ?? 0));
            $lifetimeRevenue = max($lifetimeRevenue, (int)($ordRow['ord_rev'] ?? 0));
        }

        // Check repeat buyers from orders table as well
        $repStmt = $db->prepare("
            SELECT COUNT(*) FROM (
                SELECT customer_id, COUNT(*) as c 
                FROM orders 
                WHERE website_id = :wid 
                  AND customer_id IS NOT NULL 
                  AND customer_id != ''
                  AND order_status != 'CANCELLED' 
                GROUP BY customer_id 
                HAVING c > 1
            )
        ");
        $repStmt->execute([':wid' => $wid]);
        $repOrdersCount = (int)$repStmt->fetchColumn();
        $repeatCustomers = max($repeatCustomers, $repOrdersCount);

        // Retention rate
        $retentionRate = $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0.0;

        // Month-over-month calculation
        $lastMonthStart = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $lastMonthEnd = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        $thisMonthStart = date('Y-m-01 00:00:00');

        $lmCustStmt = $db->prepare("
            SELECT COUNT(*) FROM customers 
            WHERE website_id = :wid AND (is_anonymized IS NULL OR is_anonymized = 0) AND created_at BETWEEN :start AND :end
        ");
        $lmCustStmt->execute([':wid' => $wid, ':start' => $lastMonthStart, ':end' => $lastMonthEnd]);
        $lmCust = (int)$lmCustStmt->fetchColumn();

        $tmCustStmt = $db->prepare("
            SELECT COUNT(*) FROM customers 
            WHERE website_id = :wid AND (is_anonymized IS NULL OR is_anonymized = 0) AND created_at >= :start
        ");
        $tmCustStmt->execute([':wid' => $wid, ':start' => $thisMonthStart]);
        $tmCust = (int)$tmCustStmt->fetchColumn();

        $lmOrdStmt = $db->prepare("
            SELECT COUNT(*), COALESCE(SUM(grand_total_amount), 0) FROM orders 
            WHERE website_id = :wid AND order_status != 'CANCELLED' AND created_at BETWEEN :start AND :end
        ");
        $lmOrdStmt->execute([':wid' => $wid, ':start' => $lastMonthStart, ':end' => $lastMonthEnd]);
        $lmOrdRow = $lmOrdStmt->fetch(PDO::FETCH_NUM);
        $lmOrders = (int)($lmOrdRow[0] ?? 0);
        $lmRev = (int)($lmOrdRow[1] ?? 0);

        $tmOrdStmt = $db->prepare("
            SELECT COUNT(*), COALESCE(SUM(grand_total_amount), 0) FROM orders 
            WHERE website_id = :wid AND order_status != 'CANCELLED' AND created_at >= :start
        ");
        $tmOrdStmt->execute([':wid' => $wid, ':start' => $thisMonthStart]);
        $tmOrdRow = $tmOrdStmt->fetch(PDO::FETCH_NUM);
        $tmOrders = (int)($tmOrdRow[0] ?? 0);
        $tmRev = (int)($tmOrdRow[1] ?? 0);

        $formatChange = function(int $curr, int $prev) {
            if ($prev === 0) {
                return $curr > 0 ? '+100% from last month' : '0% from last month';
            }
            $pct = round((($curr - $prev) / $prev) * 100, 1);
            $sign = $pct >= 0 ? '+' : '';
            return "{$sign}{$pct}% from last month";
        };

        $custChange = $formatChange($tmCust, $lmCust);
        $ordersChange = $formatChange($tmOrders, $lmOrders);
        $revChange = $formatChange($tmRev, $lmRev);

        return [
            'total_customers' => $totalCustomers,
            'total_customers_formatted' => number_format($totalCustomers),
            'total_customers_change' => $custChange,
            'repeat_customers' => $repeatCustomers,
            'repeat_customers_formatted' => number_format($repeatCustomers),
            'repeat_customers_retention' => "{$retentionRate}% retention rate",
            'total_orders' => $totalOrders,
            'total_orders_formatted' => number_format($totalOrders),
            'total_orders_change' => $ordersChange,
            'total_revenue' => $lifetimeRevenue,
            'total_revenue_formatted' => '₹' . number_format($lifetimeRevenue / 100, 0),
            'total_revenue_change' => $revChange
        ];
    }

    /**
     * Advanced directory listing with search, filters, tags, date range, sorting and pagination.
     */
    public static function getCustomersAdvanced(
        ?string $search = null,
        ?string $status = null,
        ?string $tag = null,
        ?string $dateRange = null,
        ?string $cohort = null,
        ?string $sort = null,
        int $page = 1,
        int $perPage = 10
    ): array {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $where = ["website_id = :wid", "(is_anonymized IS NULL OR is_anonymized = 0)"];
        $params = [':wid' => $wid];

        if (!empty($search)) {
            $where[] = "(name LIKE :s OR email LIKE :s OR phone LIKE :s OR customer_number LIKE :s)";
            $params[':s'] = '%' . trim($search) . '%';
        }

        if (!empty($status) && $status !== 'All statuses' && $status !== 'All Status' && $status !== 'all') {
            $where[] = "status = :status";
            $params[':status'] = $status;
        }

        if (!empty($tag) && $tag !== 'All Tags' && $tag !== 'all') {
            $where[] = "tags LIKE :tag";
            $params[':tag'] = '%' . trim($tag) . '%';
        }

        if (!empty($dateRange) && $dateRange !== 'all') {
            if ($dateRange === '7_days' || $dateRange === 'Last 7 Days') {
                $where[] = "created_at >= datetime('now', '-7 days')";
            } elseif ($dateRange === '30_days' || $dateRange === 'Last 30 Days') {
                $where[] = "created_at >= datetime('now', '-30 days')";
            } elseif ($dateRange === '90_days' || $dateRange === 'Last 90 Days') {
                $where[] = "created_at >= datetime('now', '-90 days')";
            } elseif ($dateRange === 'this_year' || $dateRange === 'This Year') {
                $where[] = "created_at >= datetime('now', 'start of year')";
            }
        }

        if (!empty($cohort)) {
            if ($cohort === 'repeat' || $cohort === 'Repeat buyers') {
                $where[] = "total_orders > 1";
            } elseif ($cohort === 'first_time' || $cohort === 'First-time buyers') {
                $where[] = "total_orders = 1";
            } elseif ($cohort === 'inactive' || $cohort === 'No orders') {
                $where[] = "total_orders = 0";
            }
        }

        $whereClause = implode(" AND ", $where);

        // Count total matching
        $countStmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        // Sort order
        $orderBy = "created_at DESC";
        if ($sort === 'highest_spend' || $sort === 'Highest spenders') {
            $orderBy = "total_spent_amount DESC";
        } elseif ($sort === 'most_orders' || $sort === 'Most orders') {
            $orderBy = "total_orders DESC";
        } elseif ($sort === 'name_asc') {
            $orderBy = "name ASC";
        } elseif ($sort === 'recent' || $sort === 'Recent activity') {
            $orderBy = "(last_order_at IS NOT NULL) DESC, last_order_at DESC, created_at DESC";
        }

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT * FROM customers WHERE {$whereClause} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enhance rows with initials and tags array
        foreach ($rows as &$r) {
            $r['initials'] = self::computeInitials($r['name'] ?? 'Customer');
            $decodedTags = json_decode($r['tags'] ?? '[]', true);
            $r['tags_array'] = is_array($decodedTags) ? $decodedTags : [];
        }
        unset($r);

        $totalPages = $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1;

        return [
            'customers' => $rows,
            'total_count' => $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'from' => $totalCount > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $totalCount)
        ];
    }

    /**
     * Backwards-compatible getCustomers wrapper.
     */
    public static function getCustomers(?string $search = null, ?string $status = null, ?string $cohort = null, ?string $sort = null): array
    {
        $res = self::getCustomersAdvanced($search, $status, null, null, $cohort, $sort, 1, 100);
        return $res['customers'];
    }

    /**
     * Compute 2-letter uppercase initials for avatar.
     */
    public static function computeInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
        } elseif (!empty($parts[0])) {
            return strtoupper(mb_substr($parts[0], 0, 2));
        }
        return 'CU';
    }

    /**
     * Get full Customer Details bundle for the right workspace pane.
     */
    public static function getCustomerDetails(string $id): ?array
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("SELECT * FROM customers WHERE id = :id AND website_id = :wid");
        $stmt->execute([':id' => $id, ':wid' => $wid]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            return null;
        }

        $customer['initials'] = self::computeInitials($customer['name'] ?? 'Customer');
        $decodedTags = json_decode($customer['tags'] ?? '[]', true);
        $customer['tags_array'] = is_array($decodedTags) ? $decodedTags : [];

        // Linked Orders
        $phoneClean = preg_replace('/[^0-9]/', '', $customer['phone'] ?? '');
        $oStmt = $db->prepare("
            SELECT o.*, 
                   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
            FROM orders o
            WHERE o.website_id = :wid 
              AND (o.customer_id = :cid OR o.shipping_phone = :phone OR o.shipping_phone = :phoneClean)
            ORDER BY o.created_at DESC
        ");
        $oStmt->execute([
            ':wid' => $wid,
            ':cid' => $customer['id'],
            ':phone' => $customer['phone'] ?? '',
            ':phoneClean' => $phoneClean
        ]);
        $orders = $oStmt->fetchAll(PDO::FETCH_ASSOC);

        // Addresses
        $addrStmt = $db->prepare("
            SELECT * FROM customer_addresses 
            WHERE customer_id = :cid 
            ORDER BY is_default DESC, created_at DESC
        ");
        $addrStmt->execute([':cid' => $customer['id']]);
        $addresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fallback primary address if customer_addresses is empty but order exists
        if (empty($addresses) && !empty($orders)) {
            $latest = $orders[0];
            $addresses = [[
                'id' => 'addr_from_order',
                'customer_id' => $customer['id'],
                'type' => 'shipping',
                'label' => 'Order Shipping Address',
                'name' => $latest['shipping_name'] ?? $customer['name'],
                'phone' => $latest['shipping_phone'] ?? $customer['phone'],
                'address_line1' => $latest['shipping_address_line1'] ?? '',
                'address_line2' => $latest['shipping_address_line2'] ?? '',
                'city' => $latest['shipping_city'] ?? ($customer['shipping_city'] ?? ''),
                'state' => $latest['shipping_state'] ?? ($customer['shipping_state'] ?? ''),
                'postal_code' => $latest['shipping_postal_code'] ?? '',
                'country' => 'India',
                'is_default' => 1
            ]];
        }

        // Notes
        $notesStmt = $db->prepare("
            SELECT * FROM customer_notes 
            WHERE customer_id = :cid 
            ORDER BY created_at DESC
        ");
        $notesStmt->execute([':cid' => $customer['id']]);
        $notes = $notesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fallback note if customer_notes table is empty but customer.notes field has text
        if (empty($notes) && !empty($customer['notes'])) {
            $notes = [[
                'id' => 'note_legacy',
                'customer_id' => $customer['id'],
                'author_name' => 'Staff',
                'note' => $customer['notes'],
                'created_at' => $customer['created_at']
            ]];
        }

        // Activity timeline
        $actStmt = $db->prepare("
            SELECT * FROM customer_activity 
            WHERE customer_id = :cid 
            ORDER BY created_at DESC
        ");
        $actStmt->execute([':cid' => $customer['id']]);
        $activity = $actStmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate synthetic activity if empty
        if (empty($activity)) {
            if (!empty($orders)) {
                foreach ($orders as $ord) {
                    $activity[] = [
                        'id' => 'act_' . $ord['id'],
                        'customer_id' => $customer['id'],
                        'event_type' => 'order',
                        'title' => 'Order Placed',
                        'description' => "Placed order {$ord['order_number']} for ₹" . number_format($ord['grand_total_amount'] / 100, 0),
                        'actor_name' => $customer['name'],
                        'created_at' => $ord['created_at']
                    ];
                }
            }
            $activity[] = [
                'id' => 'act_reg_' . $customer['id'],
                'customer_id' => $customer['id'],
                'event_type' => 'account',
                'title' => 'Customer Registered',
                'description' => "Customer joined Nutronyx store",
                'actor_name' => 'System',
                'created_at' => $customer['created_at']
            ];
        }

        // Metrics
        $totalOrders = max((int)$customer['total_orders'], count($orders));
        $totalSpent = (int)$customer['total_spent_amount'];
        if ($totalSpent === 0 && !empty($orders)) {
            foreach ($orders as $o) {
                if (in_array($o['payment_status'] ?? '', ['PAID', 'PARTIALLY_REFUNDED'], true)) {
                    $totalSpent += (int)$o['grand_total_amount'];
                }
            }
        }
        $aov = $totalOrders > 0 ? (int)round($totalSpent / $totalOrders) : 0;

        $lastOrderAt = $customer['last_order_at'];
        if (empty($lastOrderAt) && !empty($orders)) {
            $lastOrderAt = $orders[0]['created_at'];
        }

        $location = 'India';
        if (!empty($customer['shipping_city'])) {
            $location = $customer['shipping_city'] . (!empty($customer['shipping_state']) ? ', ' . $customer['shipping_state'] : '');
        } elseif (!empty($addresses[0]['city'])) {
            $location = $addresses[0]['city'] . (!empty($addresses[0]['state']) ? ', ' . $addresses[0]['state'] : '');
        }

        return [
            'customer' => $customer,
            'orders' => $orders,
            'addresses' => $addresses,
            'notes' => $notes,
            'activity' => $activity,
            'location' => $location,
            'metrics' => [
                'total_orders' => $totalOrders,
                'total_spent' => $totalSpent,
                'total_spent_formatted' => '₹' . number_format($totalSpent / 100, 0),
                'aov' => $aov,
                'aov_formatted' => '₹' . number_format($aov / 100, 0),
                'last_order_at' => $lastOrderAt ? date('M d, Y', strtotime($lastOrderAt)) : 'Never',
                'joined_date' => date('M d, Y', strtotime($customer['created_at']))
            ]
        ];
    }

    /**
     * Create Customer.
     */
    public static function createCustomer(array $data): string
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $id = 'cust_' . bin2hex(random_bytes(6));
        $custNum = 'CUST-' . date('Y') . '-' . str_pad((string)rand(100, 9999), 4, '0', STR_PAD_LEFT);

        $name = trim($data['name']);
        $parts = explode(' ', $name, 2);
        $fn = $parts[0] ?? $name;
        $ln = $parts[1] ?? '';

        $tags = isset($data['tags']) ? (is_array($data['tags']) ? $data['tags'] : [trim($data['tags'])]) : ['New'];

        $stmt = $db->prepare("
            INSERT INTO customers (
                id, website_id, customer_number, first_name, last_name, name, email, phone,
                status, customer_type, total_orders, total_spent_amount, avg_order_value,
                tags, shipping_city, shipping_state, notes, created_at, updated_at
            ) VALUES (
                :id, :wid, :num, :fn, :ln, :name, :email, :phone,
                :status, :ctype, 0, 0, 0,
                :tags, :city, :state, :notes, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':id' => $id,
            ':wid' => $wid,
            ':num' => $custNum,
            ':fn' => $fn,
            ':ln' => $ln,
            ':name' => $name,
            ':email' => trim($data['email']),
            ':phone' => trim($data['phone'] ?? ''),
            ':status' => $data['status'] ?? 'Active',
            ':ctype' => $data['customer_type'] ?? 'Registered',
            ':tags' => json_encode($tags),
            ':city' => trim($data['city'] ?? ''),
            ':state' => trim($data['state'] ?? ''),
            ':notes' => trim($data['notes'] ?? '')
        ]);

        // If address supplied, save address
        if (!empty($data['address_line1'])) {
            self::addAddress($id, [
                'type' => 'shipping',
                'label' => 'Default Address',
                'name' => $name,
                'phone' => trim($data['phone'] ?? ''),
                'address_line1' => trim($data['address_line1']),
                'address_line2' => trim($data['address_line2'] ?? ''),
                'city' => trim($data['city'] ?? ''),
                'state' => trim($data['state'] ?? ''),
                'postal_code' => trim($data['postal_code'] ?? ''),
                'country' => 'India',
                'is_default' => 1
            ]);
        }

        // Log Activity
        self::logActivity($id, 'account', 'Customer Created', "Customer profile created manually by staff", 'Staff');
        AuditService::log('customer.create', 'customer', $id, "Added customer {$name} ({$data['email']})");

        return $id;
    }

    /**
     * Update Customer profile.
     */
    public static function updateCustomer(string $id, array $data): bool
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $name = trim($data['name']);
        $parts = explode(' ', $name, 2);
        $fn = $parts[0] ?? $name;
        $ln = $parts[1] ?? '';

        $fields = [
            "name = :name",
            "first_name = :fn",
            "last_name = :ln",
            "email = :email",
            "phone = :phone",
            "status = :status",
            "shipping_city = :city",
            "shipping_state = :state",
            "updated_at = CURRENT_TIMESTAMP"
        ];
        $params = [
            ':id' => $id,
            ':wid' => $wid,
            ':name' => $name,
            ':fn' => $fn,
            ':ln' => $ln,
            ':email' => trim($data['email']),
            ':phone' => trim($data['phone'] ?? ''),
            ':status' => $data['status'] ?? 'Active',
            ':city' => trim($data['city'] ?? ''),
            ':state' => trim($data['state'] ?? '')
        ];

        if (isset($data['tags'])) {
            $fields[] = "tags = :tags";
            $params[':tags'] = is_array($data['tags']) ? json_encode($data['tags']) : json_encode(array_map('trim', explode(',', $data['tags'])));
        }

        if (isset($data['notes'])) {
            $fields[] = "notes = :notes";
            $params[':notes'] = trim($data['notes']);
        }

        $sql = "UPDATE customers SET " . implode(", ", $fields) . " WHERE id = :id AND website_id = :wid";
        $stmt = $db->prepare($sql);
        $ok = $stmt->execute($params);

        self::logActivity($id, 'edit', 'Profile Updated', "Customer details updated by staff", 'Staff');
        AuditService::log('customer.update', 'customer', $id, "Updated profile for customer ID {$id}");

        return $ok;
    }

    /**
     * Add Address for Customer.
     */
    public static function addAddress(string $customerId, array $data): string
    {
        $db = Database::getConnection();
        $addrId = 'addr_' . bin2hex(random_bytes(6));
        $isDefault = !empty($data['is_default']) ? 1 : 0;

        if ($isDefault) {
            $db->prepare("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = :cid")->execute([':cid' => $customerId]);
        }

        $stmt = $db->prepare("
            INSERT INTO customer_addresses (
                id, customer_id, type, label, name, phone, address_line1, address_line2,
                city, state, postal_code, country, is_default, created_at, updated_at
            ) VALUES (
                :id, :cid, :type, :label, :name, :phone, :line1, :line2,
                :city, :state, :postal, :country, :def, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':id' => $addrId,
            ':cid' => $customerId,
            ':type' => $data['type'] ?? 'shipping',
            ':label' => $data['label'] ?? 'Home',
            ':name' => $data['name'] ?? '',
            ':phone' => $data['phone'] ?? '',
            ':line1' => $data['address_line1'] ?? '',
            ':line2' => $data['address_line2'] ?? '',
            ':city' => $data['city'] ?? '',
            ':state' => $data['state'] ?? '',
            ':postal' => $data['postal_code'] ?? '',
            ':country' => $data['country'] ?? 'India',
            ':def' => $isDefault
        ]);

        self::logActivity($customerId, 'address', 'Address Added', "Added address ({$data['label']}): {$data['address_line1']}, {$data['city']}", 'Staff');

        return $addrId;
    }

    /**
     * Delete Address.
     */
    public static function deleteAddress(string $addressId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM customer_addresses WHERE id = :id");
        return $stmt->execute([':id' => $addressId]);
    }

    /**
     * Add Note.
     */
    public static function addNote(string $customerId, string $note, string $authorName = 'Staff'): string
    {
        $db = Database::getConnection();
        $noteId = 'note_' . bin2hex(random_bytes(6));

        $stmt = $db->prepare("
            INSERT INTO customer_notes (id, customer_id, author_id, author_name, note, created_at, updated_at)
            VALUES (:id, :cid, 'admin_staff', :author, :note, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':id' => $noteId,
            ':cid' => $customerId,
            ':author' => $authorName,
            ':note' => trim($note)
        ]);

        self::logActivity($customerId, 'note', 'Note Added', "Internal note added by {$authorName}", $authorName);

        return $noteId;
    }

    /**
     * Delete Note.
     */
    public static function deleteNote(string $noteId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM customer_notes WHERE id = :id");
        return $stmt->execute([':id' => $noteId]);
    }

    /**
     * Log Activity.
     */
    public static function logActivity(
        string $customerId,
        string $eventType,
        string $title,
        string $description,
        string $actorName = 'System',
        array $metadata = []
    ): string {
        $db = Database::getConnection();
        $actId = 'act_' . bin2hex(random_bytes(6));

        $stmt = $db->prepare("
            INSERT INTO customer_activity (id, customer_id, event_type, title, description, actor_name, metadata, created_at)
            VALUES (:id, :cid, :type, :title, :desc, :actor, :meta, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':id' => $actId,
            ':cid' => $customerId,
            ':type' => $eventType,
            ':title' => $title,
            ':desc' => $description,
            ':actor' => $actorName,
            ':meta' => json_encode($metadata)
        ]);

        return $actId;
    }

    /**
     * Anonymize customer (GDPR Right-to-be-Forgotten).
     */
    public static function anonymizeCustomer(string $id): bool
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("
            UPDATE customers SET
                name = 'Anonymized Customer',
                first_name = 'Anonymized',
                last_name = '',
                email = 'anonymized_' || id || '@redacted.local',
                phone = '+91 0000000000',
                shipping_city = 'Redacted',
                shipping_state = 'Redacted',
                notes = 'Customer PII anonymized per data privacy policy',
                is_anonymized = 1,
                status = 'Inactive',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND website_id = :wid
        ");
        $stmt->execute([':id' => $id, ':wid' => $wid]);

        // Remove detailed address lines
        $db->prepare("DELETE FROM customer_addresses WHERE customer_id = :id")->execute([':id' => $id]);

        self::logActivity($id, 'privacy', 'Customer Anonymized', "PII scrubbed per privacy compliance", 'Staff');
        AuditService::log('customer.anonymize', 'customer', $id, "Anonymized customer ID {$id}");

        return true;
    }

    /**
     * Delete customer.
     */
    public static function deleteCustomer(string $id): bool
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("DELETE FROM customers WHERE id = :id AND website_id = :wid");
        $ok = $stmt->execute([':id' => $id, ':wid' => $wid]);

        $db->prepare("DELETE FROM customer_addresses WHERE customer_id = :id")->execute([':id' => $id]);
        $db->prepare("DELETE FROM customer_notes WHERE customer_id = :id")->execute([':id' => $id]);
        $db->prepare("DELETE FROM customer_activity WHERE customer_id = :id")->execute([':id' => $id]);

        AuditService::log('customer.delete', 'customer', $id, "Deleted customer ID {$id}");

        return $ok;
    }

    /**
     * Bulk actions: status, tags, delete.
     */
    public static function bulkUpdateStatus(array $ids, string $status): int
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $count = 0;

        $stmt = $db->prepare("UPDATE customers SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND website_id = :wid");
        foreach ($ids as $id) {
            $stmt->execute([':status' => $status, ':id' => $id, ':wid' => $wid]);
            $count += $stmt->rowCount();
        }

        return $count;
    }

    public static function bulkAddTag(array $ids, string $tag): int
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $tag = trim($tag);
        if ($tag === '') return 0;

        $count = 0;
        foreach ($ids as $id) {
            $stmt = $db->prepare("SELECT tags FROM customers WHERE id = :id AND website_id = :wid");
            $stmt->execute([':id' => $id, ':wid' => $wid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $tags = json_decode($row['tags'] ?? '[]', true) ?: [];
                if (!in_array($tag, $tags, true)) {
                    $tags[] = $tag;
                    $up = $db->prepare("UPDATE customers SET tags = :tags WHERE id = :id");
                    $up->execute([':tags' => json_encode(array_values($tags)), ':id' => $id]);
                    $count++;
                }
            }
        }
        return $count;
    }

    public static function bulkDelete(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if (self::deleteCustomer($id)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Duplicate detection: find matching phone or email.
     */
    public static function findDuplicates(): array
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("
            SELECT email, phone, COUNT(*) as cnt, GROUP_CONCAT(id) as ids, GROUP_CONCAT(name) as names
            FROM customers
            WHERE website_id = :wid AND (is_anonymized IS NULL OR is_anonymized = 0)
            GROUP BY email
            HAVING cnt > 1
        ");
        $stmt->execute([':wid' => $wid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Merge secondary customer into primary customer.
     */
    public static function mergeCustomers(string $primaryId, string $secondaryId): bool
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        // 1. Reassign orders
        $db->prepare("UPDATE orders SET customer_id = :pid WHERE customer_id = :sid AND website_id = :wid")
           ->execute([':pid' => $primaryId, ':sid' => $secondaryId, ':wid' => $wid]);

        // 2. Reassign addresses
        $db->prepare("UPDATE customer_addresses SET customer_id = :pid, is_default = 0 WHERE customer_id = :sid")
           ->execute([':pid' => $primaryId, ':sid' => $secondaryId]);

        // 3. Reassign notes
        $db->prepare("UPDATE customer_notes SET customer_id = :pid WHERE customer_id = :sid")
           ->execute([':pid' => $primaryId, ':sid' => $secondaryId]);

        // 4. Reassign activity
        $db->prepare("UPDATE customer_activity SET customer_id = :pid WHERE customer_id = :sid")
           ->execute([':pid' => $primaryId, ':sid' => $secondaryId]);

        // 5. Delete or archive secondary
        self::deleteCustomer($secondaryId);

        // 6. Recalculate metrics on primary
        self::syncFromOrders();

        self::logActivity($primaryId, 'merge', 'Customer Merged', "Merged secondary customer ID {$secondaryId} into this record", 'Staff');
        AuditService::log('customer.merge', 'customer', $primaryId, "Merged customer {$secondaryId} into {$primaryId}");

        return true;
    }

    /**
     * Export customers to CSV.
     */
    public static function exportCustomersCsv(?array $ids = null): void
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        if (!empty($ids)) {
            $inClause = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("SELECT * FROM customers WHERE website_id = ? AND id IN ($inClause) ORDER BY created_at DESC");
            $params = array_merge([$wid], $ids);
            $stmt->execute($params);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare("SELECT * FROM customers WHERE website_id = :wid AND (is_anonymized IS NULL OR is_anonymized = 0) ORDER BY created_at DESC");
            $stmt->execute([':wid' => $wid]);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (php_sapi_name() !== 'cli') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="nutronyx_customers_' . date('Ymd_His') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Customer ID', 'Full Name', 'Email', 'Phone', 'Status', 'Total Orders', 'Total Spent (INR)', 'AOV (INR)', 'Tags', 'City', 'Joined Date'], ',', '"', "\\");

        foreach ($customers as $c) {
            $spent = ($c['total_spent_amount'] ?? 0) / 100;
            $orders = (int)($c['total_orders'] ?? 0);
            $aov = $orders > 0 ? ($spent / $orders) : 0;
            $tags = implode(', ', json_decode($c['tags'] ?? '[]', true) ?: []);

            fputcsv($output, [
                $c['customer_number'] ?? $c['id'],
                $c['name'],
                $c['email'],
                $c['phone'] ?? '',
                $c['status'] ?? 'Active',
                $orders,
                number_format($spent, 2, '.', ''),
                number_format($aov, 2, '.', ''),
                $tags,
                $c['shipping_city'] ?? '',
                date('Y-m-d', strtotime($c['created_at']))
            ], ',', '"', "\\");
        }
        fclose($output);
    }

    /**
     * Import customers from uploaded CSV.
     */
    public static function importCustomersCsv(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return ['success' => false, 'error' => 'CSV file not found or readable'];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['success' => false, 'error' => 'Unable to open CSV file'];
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ['success' => false, 'error' => 'CSV file is empty'];
        }

        // Map column indexes
        $map = [];
        foreach ($header as $idx => $col) {
            $clean = strtolower(trim($col));
            if (strpos($clean, 'name') !== false) $map['name'] = $idx;
            elseif (strpos($clean, 'email') !== false) $map['email'] = $idx;
            elseif (strpos($clean, 'phone') !== false) $map['phone'] = $idx;
            elseif (strpos($clean, 'status') !== false) $map['status'] = $idx;
            elseif (strpos($clean, 'tag') !== false) $map['tags'] = $idx;
            elseif (strpos($clean, 'city') !== false) $map['city'] = $idx;
        }

        $imported = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $name = isset($map['name']) ? trim($row[$map['name']] ?? '') : '';
            $email = isset($map['email']) ? trim($row[$map['email']] ?? '') : '';
            if (empty($name) && empty($email)) continue;

            $phone = isset($map['phone']) ? trim($row[$map['phone']] ?? '') : '';
            $status = isset($map['status']) ? trim($row[$map['status']] ?? 'Active') : 'Active';
            $city = isset($map['city']) ? trim($row[$map['city']] ?? '') : '';
            $tagsRaw = isset($map['tags']) ? trim($row[$map['tags']] ?? '') : '';
            $tags = !empty($tagsRaw) ? array_map('trim', explode(',', $tagsRaw)) : ['Imported'];

            self::createCustomer([
                'name' => $name ?: 'Customer ' . rand(100, 999),
                'email' => $email ?: ('imported_' . rand(1000, 9999) . '@example.com'),
                'phone' => $phone,
                'status' => in_array($status, ['Active', 'Inactive', 'Lead']) ? $status : 'Active',
                'tags' => $tags,
                'city' => $city
            ]);
            $imported++;
        }
        fclose($handle);

        return ['success' => true, 'imported' => $imported];
    }

    /**
     * Sync stats from orders ledger.
     */
    public static function syncFromOrders(): int
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("
            SELECT customer_id, COUNT(*) as order_count, SUM(grand_total_amount) as total_spent, MAX(created_at) as last_order
            FROM orders
            WHERE website_id = :wid AND customer_id IS NOT NULL AND customer_id != ''
            GROUP BY customer_id
        ");
        $stmt->execute([':wid' => $wid]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updated = 0;
        foreach ($orders as $o) {
            $cnt = (int)$o['order_count'];
            $spent = (int)$o['total_spent'];
            $aov = $cnt > 0 ? (int)round($spent / $cnt) : 0;

            $uStmt = $db->prepare("
                UPDATE customers SET
                    total_orders = :cnt,
                    total_spent_amount = :spent,
                    avg_order_value = :aov,
                    last_order_at = :last_order,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :cid AND website_id = :wid
            ");
            $uStmt->execute([
                ':cnt' => $cnt,
                ':spent' => $spent,
                ':aov' => $aov,
                ':last_order' => $o['last_order'],
                ':cid' => $o['customer_id'],
                ':wid' => $wid
            ]);
            $updated++;
        }

        return $updated;
    }
}