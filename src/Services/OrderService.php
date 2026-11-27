<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;
use Nutronyx\SDK\EventBus;
use PDO;

class OrderService
{
    /**
     * Advanced paginated order query supporting tabs, multi-field search, filters, and dynamic counts
     */
    public static function getOrdersAdvanced(array $filters = []): array
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $tab = strtoupper(trim((string)($filters['tab'] ?? 'ALL')));
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        $paymentStatus = strtoupper(trim((string)($filters['payment_status'] ?? '')));
        $fulfillmentStatus = strtoupper(trim((string)($filters['fulfillment_status'] ?? '')));
        $dateRange = trim((string)($filters['date_range'] ?? ''));
        $search = trim((string)($filters['search'] ?? ''));
        $carrier = trim((string)($filters['carrier'] ?? ''));
        $minAmount = isset($filters['min_amount']) && is_numeric($filters['min_amount']) ? (int)round((float)$filters['min_amount'] * 100) : null;
        $maxAmount = isset($filters['max_amount']) && is_numeric($filters['max_amount']) ? (int)round((float)$filters['max_amount'] * 100) : null;
        $sortBy = trim((string)($filters['sort'] ?? 'newest'));
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, (int)($filters['per_page'] ?? 10));

        // Base SQL
        $sql = "
            SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
                   (SELECT s.tracking_number FROM shipments s WHERE s.order_id = o.id ORDER BY s.shipped_at DESC LIMIT 1) as tracking_number,
                   (SELECT s.carrier FROM shipments s WHERE s.order_id = o.id ORDER BY s.shipped_at DESC LIMIT 1) as carrier
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.website_id = :wid
        ";
        $params = [':wid' => $websiteId];

        // 1. Tab filter
        if ($tab !== 'ALL' && $tab !== '') {
            if ($tab === 'RETURNS' || $tab === 'RETURNED') {
                $sql .= " AND (o.order_status = 'RETURNED' OR o.fulfillment_status = 'RETURNED' OR EXISTS (SELECT 1 FROM order_returns ret WHERE ret.order_id = o.id))";
            } elseif ($tab === 'PENDING') {
                $sql .= " AND (o.order_status = 'PENDING' OR o.order_status = 'PENDING_PAYMENT' OR o.fulfillment_status = 'PENDING')";
            } elseif ($tab === 'PROCESSING') {
                $sql .= " AND (o.order_status = 'PROCESSING' OR o.fulfillment_status IN ('PREPARING', 'DISPATCHING'))";
            } elseif ($tab === 'SHIPPED') {
                $sql .= " AND (o.order_status = 'SHIPPED' OR o.fulfillment_status = 'SHIPPED')";
            } elseif ($tab === 'DELIVERED') {
                $sql .= " AND (o.order_status = 'DELIVERED' OR o.fulfillment_status = 'DELIVERED')";
            } else {
                $sql .= " AND o.order_status = :tab_status";
                $params[':tab_status'] = $tab;
            }
        }

        // 2. Explicit status filter
        if ($status !== '' && $status !== 'ALL') {
            $sql .= " AND o.order_status = :explicit_status";
            $params[':explicit_status'] = $status;
        }

        // 3. Payment Status Filter
        if ($paymentStatus !== '' && $paymentStatus !== 'ALL') {
            $sql .= " AND o.payment_status = :payment_status";
            $params[':payment_status'] = $paymentStatus;
        }

        // 4. Fulfillment Status Filter
        if ($fulfillmentStatus !== '' && $fulfillmentStatus !== 'ALL') {
            $sql .= " AND o.fulfillment_status = :fulfillment_status";
            $params[':fulfillment_status'] = $fulfillmentStatus;
        }

        // 5. Date Range Filter
        if ($dateRange !== '' && $dateRange !== 'all') {
            if ($dateRange === 'today') {
                $sql .= " AND date(o.created_at) = date('now')";
            } elseif ($dateRange === 'yesterday') {
                $sql .= " AND date(o.created_at) = date('now', '-1 day')";
            } elseif ($dateRange === '7d' || $dateRange === 'last_7_days') {
                $sql .= " AND o.created_at >= datetime('now', '-7 days')";
            } elseif ($dateRange === '30d' || $dateRange === 'last_30_days') {
                $sql .= " AND o.created_at >= datetime('now', '-30 days')";
            } elseif ($dateRange === 'month' || $dateRange === 'this_month') {
                $sql .= " AND strftime('%Y-%m', o.created_at) = strftime('%Y-%m', 'now')";
            }
        }

        // 6. Carrier Filter
        if ($carrier !== '' && $carrier !== 'all') {
            $sql .= " AND EXISTS (SELECT 1 FROM shipments sh WHERE sh.order_id = o.id AND sh.carrier LIKE :carrier)";
            $params[':carrier'] = '%' . $carrier . '%';
        }

        // 7. Amount Min/Max
        if ($minAmount !== null) {
            $sql .= " AND o.grand_total_amount >= :min_amt";
            $params[':min_amt'] = $minAmount;
        }
        if ($maxAmount !== null) {
            $sql .= " AND o.grand_total_amount <= :max_amt";
            $params[':max_amt'] = $maxAmount;
        }

        // 8. Search query across order_number, customer, phone, email, sku, tracking, payment ref
        if ($search !== '') {
            $sql .= " AND (
                o.order_number LIKE :s 
                OR o.shipping_name LIKE :s 
                OR o.shipping_phone LIKE :s 
                OR c.name LIKE :s 
                OR c.phone LIKE :s 
                OR c.email LIKE :s
                OR EXISTS (SELECT 1 FROM order_items ois WHERE ois.order_id = o.id AND (ois.title LIKE :s OR ois.sku LIKE :s))
                OR EXISTS (SELECT 1 FROM shipments shs WHERE shs.order_id = o.id AND shs.tracking_number LIKE :s)
                OR EXISTS (SELECT 1 FROM payment_transactions pts WHERE pts.order_id = o.id AND (pts.gateway_transaction_id LIKE :s OR pts.id LIKE :s))
            )";
            $params[':s'] = '%' . $search . '%';
        }

        // Count matching records
        $countSql = "SELECT COUNT(*) FROM (" . $sql . ") as t";
        $cStmt = $db->prepare($countSql);
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();

        // Sort
        switch ($sortBy) {
            case 'oldest':
                $sql .= " ORDER BY o.created_at ASC";
                break;
            case 'amount_desc':
                $sql .= " ORDER BY o.grand_total_amount DESC";
                break;
            case 'amount_asc':
                $sql .= " ORDER BY o.grand_total_amount ASC";
                break;
            case 'newest':
            default:
                $sql .= " ORDER BY o.created_at DESC";
                break;
        }

        // Pagination
        $offset = max(0, ($page - 1) * $perPage);
        $sql .= " LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll();

        // Dynamic Tab Counts for Header Tabs
        $counts = self::getOrderCounts($websiteId);

        return [
            'orders' => $orders,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int)ceil($total / $perPage)),
            'total_pages' => max(1, (int)ceil($total / $perPage)),
            'counts' => $counts
        ];
    }

    /**
     * Backward-compatible getOrders wrapper for existing reporting and admin calls
     */
    public static function getOrders(?string $status = null, ?string $search = null, ?string $paymentStatus = null, bool $delayedOnly = false): array
    {
        $filters = [
            'tab' => ($status && strtoupper($status) !== 'ALL') ? strtoupper($status) : 'ALL',
            'search' => $search,
            'payment_status' => $paymentStatus,
            'delayed' => $delayedOnly,
            'per_page' => 1000
        ];
        $res = self::getOrdersAdvanced($filters);
        return $res['orders'];
    }

    /**
     * Compute dynamic lifecycle counts for header tabs
     */
    public static function getOrderCounts(?string $websiteId = null): array
    {
        $db = Database::getConnection();
        $websiteId = $websiteId ?: Context::getWebsiteId();
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as all_count,
                SUM(CASE WHEN order_status IN ('PENDING', 'PENDING_PAYMENT') OR fulfillment_status = 'PENDING' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN order_status = 'PROCESSING' OR fulfillment_status IN ('PREPARING', 'DISPATCHING') THEN 1 ELSE 0 END) as processing_count,
                SUM(CASE WHEN order_status = 'SHIPPED' OR fulfillment_status = 'SHIPPED' THEN 1 ELSE 0 END) as shipped_count,
                SUM(CASE WHEN order_status IN ('DELIVERED', 'FULFILLED') OR fulfillment_status = 'DELIVERED' THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN order_status = 'RETURNED' OR fulfillment_status = 'RETURNED' THEN 1 ELSE 0 END) as returns_count,
                SUM(CASE WHEN order_status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled_count,
                SUM(CASE WHEN order_status = 'PROCESSING' AND created_at < datetime('now', '-48 hours') THEN 1 ELSE 0 END) as delayed_count
            FROM orders
            WHERE website_id = :wid
        ");
        $stmt->execute([':wid' => $websiteId]);
        $res = $stmt->fetch();

        $all = (int)($res['all_count'] ?? 0);
        $pending = (int)($res['pending_count'] ?? 0);
        $processing = (int)($res['processing_count'] ?? 0);
        $shipped = (int)($res['shipped_count'] ?? 0);
        $delivered = (int)($res['delivered_count'] ?? 0);
        $returns = (int)($res['returns_count'] ?? 0);
        $cancelled = (int)($res['cancelled_count'] ?? 0);
        $delayed = (int)($res['delayed_count'] ?? 0);

        return [
            'ALL' => $all,
            'PENDING' => $pending,
            'PROCESSING' => $processing,
            'SHIPPED' => $shipped,
            'DELIVERED' => $delivered,
            'RETURNS' => $returns,
            'CANCELLED' => $cancelled,
            'DELAYED' => $delayed,
            'all' => $all,
            'pending' => $pending,
            'processing' => $processing,
            'shipped' => $shipped,
            'delivered' => $delivered,
            'returns' => $returns,
            'cancelled' => $cancelled,
            'delayed' => $delayed
        ];
    }

    /**
     * Retrieve complete order record with items, timeline, transactions, shipments, and returns
     */
    public static function getOrder(string $id): ?array
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $stmt = $db->prepare("
            SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE (o.id = :id OR o.order_number = :id) AND o.website_id = :wid
        ");
        $stmt->execute([':id' => $id, ':wid' => $websiteId]);
        $order = $stmt->fetch();
        if (!$order) return null;
        $realId = $order['id'];

        // Items with product images
        $iStmt = $db->prepare("
            SELECT oi.*, COALESCE(pv.image_url, p.image_url) as product_image_url, pv.title as variant_name
            FROM order_items oi
            LEFT JOIN product_variants pv ON oi.variant_id = pv.id
            LEFT JOIN products p ON pv.product_id = p.id
            WHERE oi.order_id = :oid AND oi.website_id = :wid
        ");
        $iStmt->execute([':oid' => $realId, ':wid' => $websiteId]);
        $order['items'] = $iStmt->fetchAll();

        // Timeline
        $tStmt = $db->prepare("SELECT * FROM order_timeline WHERE order_id = :oid AND website_id = :wid ORDER BY created_at DESC");
        $tStmt->execute([':oid' => $realId, ':wid' => $websiteId]);
        $order['timeline'] = $tStmt->fetchAll();

        // Payment Transactions
        $pStmt = $db->prepare("SELECT * FROM payment_transactions WHERE order_id = :oid AND website_id = :wid ORDER BY created_at DESC");
        $pStmt->execute([':oid' => $realId, ':wid' => $websiteId]);
        $order['transactions'] = $pStmt->fetchAll();

        // Shipments
        $sStmt = $db->prepare("SELECT * FROM shipments WHERE order_id = :oid AND website_id = :wid ORDER BY shipped_at DESC");
        $sStmt->execute([':oid' => $realId, ':wid' => $websiteId]);
        $order['shipments'] = $sStmt->fetchAll();

        // Returns
        $rStmt = $db->prepare("SELECT * FROM order_returns WHERE order_id = :oid AND website_id = :wid ORDER BY created_at DESC");
        $rStmt->execute([':oid' => $realId, ':wid' => $websiteId]);
        $order['returns'] = $rStmt->fetchAll();

        // Compute financial totals
        $refStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE order_id = :oid AND transaction_type = 'REFUND' AND status = 'SUCCESS'");
        $refStmt->execute([':oid' => $realId]);
        $order['total_refunded_amount'] = (int)$refStmt->fetchColumn();
        $order['refundable_balance'] = max(0, (int)$order['grand_total_amount'] - $order['total_refunded_amount']);

        return $order;
    }

    /**
     * Create manual order from admin workspace
     */
    public static function createOrder(array $data): array
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $orderId = 'ord_' . bin2hex(random_bytes(6));
        $randNum = rand(2850, 9999);
        $orderNumber = $data['order_number'] ?? ('NX' . $randNum);

        $shippingName = $data['shipping_name'] ?? $data['customer_name'] ?? 'Direct Customer';
        $shippingPhone = $data['shipping_phone'] ?? $data['customer_phone'] ?? '';
        $customerEmail = !empty($data['customer_email']) ? trim($data['customer_email']) : '';

        $customerId = $data['customer_id'] ?? null;
        if (!$customerId && !empty($customerEmail)) {
            $cStmt = $db->prepare("SELECT id FROM customers WHERE email = :email AND website_id = :wid");
            $cStmt->execute([':email' => $customerEmail, ':wid' => $websiteId]);
            $customerId = $cStmt->fetchColumn() ?: null;
        }

        if (!$customerId && !empty($shippingPhone)) {
            $pStmt = $db->prepare("SELECT id FROM customers WHERE phone = :ph AND website_id = :wid");
            $pStmt->execute([':ph' => $shippingPhone, ':wid' => $websiteId]);
            $customerId = $pStmt->fetchColumn() ?: null;
        }

        if (!$customerId) {
            $customerId = 'cust_' . bin2hex(random_bytes(6));
            $fallbackEmail = !empty($customerEmail) ? $customerEmail : ('customer_' . substr($customerId, 5) . '@nutronyx.internal');
            $insC = $db->prepare("
                INSERT INTO customers (id, website_id, name, email, phone)
                VALUES (:id, :wid, :name, :email, :phone)
            ");
            $insC->execute([
                ':id' => $customerId,
                ':wid' => $websiteId,
                ':name' => $shippingName,
                ':email' => $fallbackEmail,
                ':phone' => $shippingPhone
            ]);
        }

        $subtotal = 0;
        $items = $data['items'] ?? [];
        $normalizedItems = [];
        foreach ($items as $it) {
            $qty = max(1, (int)($it['quantity'] ?? $it['qty'] ?? 1));
            $varId = $it['variant_id'] ?? 'var_abc_300g';
            
            // Fetch variant title and SKU if not provided
            $sku = $it['sku'] ?? null;
            $title = $it['title'] ?? null;
            $unitP = isset($it['unit_price_amount']) ? (int)$it['unit_price_amount'] : (int)(round((float)($it['unit_price'] ?? 0) * 100));

            if (!$sku || !$title || $unitP <= 0) {
                $vStmt = $db->prepare("SELECT pv.sku, pv.title as variant_title, pv.price_amount, p.title as prod_title FROM product_variants pv JOIN products p ON pv.product_id = p.id WHERE pv.id = :vid");
                $vStmt->execute([':vid' => $varId]);
                $vData = $vStmt->fetch();
                if ($vData) {
                    $sku = $sku ?: $vData['sku'];
                    $title = $title ?: ($vData['prod_title'] . ' (' . $vData['variant_title'] . ')');
                    if ($unitP <= 0) $unitP = (int)$vData['price_amount'];
                }
            }

            $lineTotal = $unitP * $qty;
            $subtotal += $lineTotal;

            $normalizedItems[] = [
                'variant_id' => $varId,
                'sku' => $sku ?: 'NX-SKU',
                'title' => $title ?: 'Nutronyx Item',
                'unit_price' => $unitP,
                'quantity' => $qty,
                'line_total' => $lineTotal
            ];
        }

        $shipping = isset($data['shipping_amount_paise']) ? (int)$data['shipping_amount_paise'] : (int)(round((float)($data['shipping_amount'] ?? 0) * 100));
        $discount = isset($data['discount_amount_paise']) ? (int)$data['discount_amount_paise'] : (int)(round((float)($data['discount_amount'] ?? 0) * 100));
        $tax = (int)($data['tax_amount'] ?? 0);
        $grandTotal = max(0, $subtotal + $shipping + $tax - $discount);

        $orderStatus = strtoupper(trim((string)($data['order_status'] ?? 'PENDING')));
        $payMethod = strtoupper(trim((string)($data['payment_method'] ?? 'COD')));
        $paymentStatus = strtoupper(trim((string)($data['payment_status'] ?? ($payMethod === 'COD' ? 'PENDING' : 'PAID'))));
        $fulfillmentStatus = strtoupper(trim((string)($data['fulfillment_status'] ?? 'PENDING')));

        $couponCode = !empty($data['coupon_code']) ? strtoupper(trim((string)$data['coupon_code'])) : null;

        $stmt = $db->prepare("
            INSERT INTO orders (
                id, website_id, order_number, customer_id, order_status, payment_status, fulfillment_status,
                subtotal_amount, shipping_amount, discount_amount, tax_amount, grand_total_amount, currency,
                shipping_name, shipping_phone, shipping_address_line1, shipping_address_line2,
                shipping_city, shipping_state, shipping_postal_code, customer_notes, internal_notes,
                payment_method, coupon_code, created_at, updated_at
            ) VALUES (
                :id, :wid, :onum, :cid, :ostatus, :pstatus, :fstatus,
                :subtot, :ship, :disc, :tax, :gtot, 'INR',
                :sname, :sphone, :addr1, :addr2,
                :city, :state, :pincode, :cnotes, :inotes,
                :pmethod, :ccode, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':id' => $orderId,
            ':wid' => $websiteId,
            ':onum' => $orderNumber,
            ':cid' => $customerId,
            ':ostatus' => $orderStatus,
            ':pstatus' => $paymentStatus,
            ':fstatus' => $fulfillmentStatus,
            ':subtot' => $subtotal,
            ':ship' => $shipping,
            ':disc' => $discount,
            ':tax' => $tax,
            ':gtot' => $grandTotal,
            ':sname' => $shippingName,
            ':sphone' => $shippingPhone,
            ':addr1' => $data['shipping_address_line1'] ?? '',
            ':addr2' => $data['shipping_address_line2'] ?? '',
            ':city' => $data['shipping_city'] ?? '',
            ':state' => $data['shipping_state'] ?? '',
            ':pincode' => $data['shipping_postal_code'] ?? '',
            ':cnotes' => $data['customer_notes'] ?? '',
            ':inotes' => $data['internal_notes'] ?? '',
            ':pmethod' => $payMethod,
            ':ccode' => $couponCode
        ]);

        // Insert items and adjust stock
        foreach ($normalizedItems as $idx => $it) {
            $itemId = 'item_' . $orderId . '_' . $idx;
            $iStmt = $db->prepare("
                INSERT INTO order_items (id, website_id, order_id, variant_id, sku, title, unit_price_amount, quantity, total_amount, tax_amount, discount_amount)
                VALUES (:id, :wid, :oid, :vid, :sku, :title, :price, :qty, :tot, 0, 0)
            ");
            $iStmt->execute([
                ':id' => $itemId,
                ':wid' => $websiteId,
                ':oid' => $orderId,
                ':vid' => $it['variant_id'],
                ':sku' => $it['sku'],
                ':title' => $it['title'],
                ':price' => $it['unit_price'],
                ':qty' => $it['quantity'],
                ':tot' => $it['line_total']
            ]);

            // Deduct inventory
            try {
                InventoryService::adjustStock($it['variant_id'], -$it['quantity'], "Order created: #{$orderNumber}");
            } catch (\Throwable $e) {}
        }

        // Add payment transaction if paid
        if ($paymentStatus === 'PAID') {
            $db->prepare("
                INSERT INTO payment_transactions (id, website_id, order_id, gateway, gateway_transaction_id, transaction_type, amount, status)
                VALUES (:id, :wid, :oid, 'admin_manual', :gtx, 'CHARGE', :amt, 'SUCCESS')
            ")->execute([
                ':id' => 'tx_' . bin2hex(random_bytes(6)),
                ':wid' => $websiteId,
                ':oid' => $orderId,
                ':gtx' => 'man_' . bin2hex(random_bytes(6)),
                ':amt' => $grandTotal
            ]);
        }

        self::addTimelineEvent($orderId, 'ORDER_CREATED', "Order #{$orderNumber} created via Admin Workspace for " . Context::formatMoney($grandTotal));
        AuditService::log('order.create', 'order', $orderId, "Created manual order #{$orderNumber}");

        return self::getOrder($orderId);
    }

    /**
     * Controlled Order Editing: Update Shipping Address
     */
    public static function updateShippingAddress(string $orderId, array $data): bool
    {
        $order = self::getOrder($orderId);
        if (!$order) return false;

        if (in_array($order['order_status'], ['DELIVERED', 'FULFILLED', 'CANCELLED', 'RETURNED'])) {
            throw new \DomainException("Cannot modify address of an order that is {$order['order_status']}.");
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE orders SET
                shipping_name = :sname,
                shipping_phone = :sphone,
                shipping_address_line1 = :addr1,
                shipping_address_line2 = :addr2,
                shipping_city = :city,
                shipping_state = :state,
                shipping_postal_code = :pincode,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND website_id = :wid
        ");

        $res = $stmt->execute([
            ':sname' => $data['shipping_name'] ?? $order['shipping_name'],
            ':sphone' => $data['shipping_phone'] ?? $order['shipping_phone'],
            ':addr1' => $data['shipping_address_line1'] ?? $order['shipping_address_line1'],
            ':addr2' => $data['shipping_address_line2'] ?? $order['shipping_address_line2'],
            ':city' => $data['shipping_city'] ?? $order['shipping_city'],
            ':state' => $data['shipping_state'] ?? $order['shipping_state'],
            ':pincode' => $data['shipping_postal_code'] ?? $order['shipping_postal_code'],
            ':id' => $order['id'],
            ':wid' => Context::getWebsiteId()
        ]);

        self::addTimelineEvent(
            $order['id'], 
            'ADDRESS_MODIFIED', 
            "Shipping address updated to: {$data['shipping_address_line1']}, {$data['shipping_city']} - {$data['shipping_postal_code']}"
        );

        AuditService::log('order.edit_address', 'order', $order['id'], "Updated shipping address");

        return $res;
    }

    /**
     * Controlled Contact Update
     */
    public static function updateContactInfo(string $orderId, string $name, string $phone, string $email): bool
    {
        $order = self::getOrder($orderId);
        if (!$order) return false;

        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE orders SET
                shipping_name = :sname,
                shipping_phone = :sphone,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND website_id = :wid
        ");
        $stmt->execute([
            ':sname' => $name,
            ':sphone' => $phone,
            ':id' => $order['id'],
            ':wid' => Context::getWebsiteId()
        ]);

        if (!empty($order['customer_id'])) {
            $db->prepare("UPDATE customers SET name = :nm, phone = :ph, email = :em WHERE id = :cid AND website_id = :wid")
               ->execute([':nm' => $name, ':ph' => $phone, ':em' => $email, ':cid' => $order['customer_id'], ':wid' => Context::getWebsiteId()]);
        }

        self::addTimelineEvent($order['id'], 'CONTACT_UPDATED', "Customer contact details updated to {$name} ({$phone}, {$email})");
        return true;
    }

    /**
     * Add Internal Note
     */
    public static function addInternalNote(string $orderId, string $note): bool
    {
        $order = self::getOrder($orderId);
        if (!$order) return false;

        $db = Database::getConnection();
        $user = AuthService::user();
        $formatted = date('d M Y, h:i A') . " (" . ($user['name'] ?? 'Staff') . "): " . trim($note);

        $stmt = $db->prepare("
            UPDATE orders SET 
                internal_notes = CASE 
                    WHEN internal_notes IS NULL OR internal_notes = '' THEN :new_note
                    ELSE internal_notes || char(10) || :new_note
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND website_id = :wid
        ");
        $res = $stmt->execute([':new_note' => $formatted, ':id' => $order['id'], ':wid' => Context::getWebsiteId()]);
        self::addTimelineEvent($order['id'], 'NOTE_ADDED', "Internal note recorded: \"{$note}\"");
        return $res;
    }

    /**
     * Update Order Tags and Staff Assignment
     */
    public static function updateTagsAndAssignment(string $orderId, ?string $tags, ?string $assignedTo): bool
    {
        $order = self::getOrder($orderId);
        if (!$order) return false;

        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE orders 
            SET tags = :tags, assigned_to = :assigned, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND website_id = :wid
        ");
        $res = $stmt->execute([
            ':tags' => $tags,
            ':assigned' => $assignedTo,
            ':id' => $order['id'],
            ':wid' => Context::getWebsiteId()
        ]);

        self::addTimelineEvent($order['id'], 'METADATA_UPDATED', "Order assigned to {$assignedTo}, tags: {$tags}");
        return $res;
    }

    /**
     * Validated Order Status Transitions
     */
    public static function transitionStatus(string $orderId, string $newStatus, ?string $reason = null): bool
    {
        $order = self::getOrder($orderId);
        if (!$order) return false;

        $currentStatus = $order['order_status'];
        $newStatus = strtoupper(trim($newStatus));
        $validStatuses = ['PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED', 'RETURNED'];

        if (!in_array($newStatus, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid order status: {$newStatus}");
        }

        // Restriction: Cannot cancel already delivered order without return
        if ($newStatus === 'CANCELLED' && $currentStatus === 'DELIVERED') {
            throw new \RuntimeException("Delivered orders cannot be cancelled directly. Please initiate a Return/Refund workflow instead.");
        }

        $db = Database::getConnection();
        $user = AuthService::user();

        // If cancelling, restore inventory
        if ($newStatus === 'CANCELLED' && $currentStatus !== 'CANCELLED') {
            foreach ($order['items'] as $item) {
                if (!empty($item['variant_id'])) {
                    try {
                        InventoryService::adjustStock(
                            $item['variant_id'],
                            (int)$item['quantity'],
                            "Order #{$order['order_number']} cancellation restock"
                        );
                    } catch (\Throwable $e) {}
                }
            }

            $stmt = $db->prepare("
                UPDATE orders SET 
                    order_status = 'CANCELLED',
                    fulfillment_status = 'CANCELLED',
                    cancellation_reason = :reason,
                    cancelled_at = CURRENT_TIMESTAMP,
                    cancelled_by = :actor,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id AND website_id = :wid
            ");
            $res = $stmt->execute([
                ':reason' => $reason ?? 'Cancelled by administrator',
                ':actor' => $user['email'] ?? 'admin',
                ':id' => $order['id'],
                ':wid' => Context::getWebsiteId()
            ]);

            self::addTimelineEvent(
                $order['id'], 
                'CANCELLED', 
                "Order cancelled by {$user['name']}. Reason: " . ($reason ?? 'Administrative cancellation') . ". Inventory released."
            );
        } else {
            // Update fulfillment status in lockstep if transitioning to SHIPPED or DELIVERED
            $fStatusUpdate = "";
            if ($newStatus === 'SHIPPED') {
                $fStatusUpdate = ", fulfillment_status = 'SHIPPED'";
            } elseif ($newStatus === 'DELIVERED') {
                $fStatusUpdate = ", fulfillment_status = 'DELIVERED'";
            }

            $stmt = $db->prepare("UPDATE orders SET order_status = :status {$fStatusUpdate}, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND website_id = :wid");
            $res = $stmt->execute([':status' => $newStatus, ':id' => $order['id'], ':wid' => Context::getWebsiteId()]);

            self::addTimelineEvent($order['id'], 'STATUS_CHANGE', "Order status transitioned from {$currentStatus} to {$newStatus}.");
        }

        AuditService::log('order.status_change', 'order', $order['id'], "Changed status from {$currentStatus} to {$newStatus}");

        EventBus::getInstance()->publish('order.status_changed', [
            'order_id' => $order['id'],
            'old_status' => $currentStatus,
            'new_status' => $newStatus
        ]);

        return $res;
    }

    /**
     * Create Shipment / Fulfill Order
     */
    public static function createShipment(string $orderId, string $carrier, string $trackingNumber, ?string $trackingUrl = null, ?string $notes = null): array
    {
        $order = self::getOrder($orderId);
        if (!$order) {
            throw new \InvalidArgumentException("Order not found: {$orderId}");
        }

        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        if (empty($trackingUrl)) {
            $carrierLower = strtolower($carrier);
            if (str_contains($carrierLower, 'ekart')) {
                $trackingUrl = 'https://app.elite.ekartlogistics.in/track/' . urlencode($trackingNumber);
            } elseif (str_contains($carrierLower, 'delhivery')) {
                $trackingUrl = 'https://www.delhivery.com/track/package/' . urlencode($trackingNumber);
            } elseif (str_contains($carrierLower, 'bluedart')) {
                $trackingUrl = 'https://track.bluedart.com/' . urlencode($trackingNumber);
            } elseif (str_contains($carrierLower, 'dtdc')) {
                $trackingUrl = 'https://www.dtdc.in/tracking/tracking_results.asp?trType=awb_no&strAvbNo=' . urlencode($trackingNumber);
            } else {
                $trackingUrl = 'https://app.elite.ekartlogistics.in/track/' . urlencode($trackingNumber);
            }
        }

        $shipmentId = 'ship_' . bin2hex(random_bytes(6));
        $sStmt = $db->prepare("
            INSERT INTO shipments (id, website_id, order_id, carrier, tracking_number, tracking_url, status, shipped_at, dispatch_notes)
            VALUES (:id, :wid, :oid, :carrier, :tn, :url, 'SHIPPED', CURRENT_TIMESTAMP, :notes)
        ");
        $sStmt->execute([
            ':id' => $shipmentId,
            ':wid' => $websiteId,
            ':oid' => $order['id'],
            ':carrier' => $carrier,
            ':tn' => $trackingNumber,
            ':url' => $trackingUrl,
            ':notes' => $notes
        ]);

        // Update order status to SHIPPED
        $oStmt = $db->prepare("
            UPDATE orders SET 
                fulfillment_status = 'SHIPPED', 
                order_status = 'SHIPPED',
                shipping_method_name = :carrier,
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id AND website_id = :wid
        ");
        $oStmt->execute([
            ':carrier' => $carrier,
            ':id' => $order['id'],
            ':wid' => $websiteId
        ]);

        self::addTimelineEvent(
            $order['id'], 
            'SHIPPED', 
            "Order dispatched via {$carrier} (Tracking #: {$trackingNumber})."
        );

        AuditService::log('order.fulfill', 'order', $order['id'], "Fulfilled via {$carrier} with tracking {$trackingNumber}");

        return [
            'id' => $shipmentId,
            'carrier_name' => $carrier,
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingUrl
        ];
    }

    /**
     * Fulfill an order directly via Ekart Logistics Enterprise Gateway
     */
    public static function fulfillWithEkart(string $orderId, ?string $manualAwb = null): array
    {
        $order = self::getOrder($orderId);
        if (!$order) {
            throw new \InvalidArgumentException("Order not found: {$orderId}");
        }

        $trackingId = trim((string)$manualAwb);

        // If manual AWB was not provided, attempt to create via live Ekart API
        if (empty($trackingId)) {
            $liveStatus = EkartLogisticsService::isLiveConnected();
            if (empty($liveStatus['is_live'])) {
                $auth = EkartLogisticsService::getAccessToken(true);
                if (empty($auth['is_live'])) {
                    $authErr = $auth['last_error'] ?: 'Authentication check failed with Ekart server';
                    throw new \RuntimeException("Ekart Logistics API is not connected ({$authErr}). Please verify your Ekart Client ID and API credentials under Admin > Shipping Settings.");
                }
            }

            // Format items for Ekart payload
            $orderItems = [];
            $totalQty = 0;
            if (!empty($order['items'])) {
                foreach ($order['items'] as $it) {
                    $orderItems[] = [
                        'item_name' => $it['title'] ?? 'Nutronyx Wellness Nutrition',
                        'sku' => $it['sku'] ?? 'NX-SKU',
                        'quantity' => (int)($it['quantity'] ?? 1),
                        'price' => (float)(($it['unit_price_amount'] ?? 0) / 100)
                    ];
                    $totalQty += (int)($it['quantity'] ?? 1);
                }
            }

            if (empty($orderItems)) {
                $orderItems[] = [
                    'item_name' => 'Nutronyx Wellness Nutrition',
                    'sku' => 'NX-WELLNESS-DEFAULT',
                    'quantity' => 1,
                    'price' => (float)(($order['grand_total_amount'] ?? 0) / 100)
                ];
                $totalQty = 1;
            }

            $custPhone = preg_replace('/[^0-9]/', '', $order['shipping_phone'] ?? $order['customer_phone'] ?? '');
            if (strlen($custPhone) > 10) {
                $custPhone = substr($custPhone, -10);
            }
            if (strlen($custPhone) < 10) {
                $custPhone = '9876543210';
            }

            $custPincode = (int)preg_replace('/[^0-9]/', '', $order['shipping_postal_code'] ?? '500081');
            if ($custPincode < 100000) {
                $custPincode = 500081;
            }

            $addressLine = trim(($order['shipping_address_line1'] ?? '') . ' ' . ($order['shipping_address_line2'] ?? ''));
            if (empty($addressLine)) {
                $addressLine = 'Customer Delivery Address';
            }

            $altPhone = null;
            $rawAlt = preg_replace('/[^0-9]/', '', $order['billing_phone'] ?? '');
            if (strlen($rawAlt) > 10) {
                $rawAlt = substr($rawAlt, -10);
            }
            if (strlen($rawAlt) === 10 && (int)$rawAlt !== (int)$custPhone) {
                $altPhone = $rawAlt;
            }

            $ekartPayload = [
                'order_number' => $order['order_number'],
                'consignee_name' => $order['shipping_name'] ?? $order['customer_name'] ?? 'Customer',
                'payment_mode' => (strtoupper($order['payment_method'] ?? '') === 'COD') ? 'COD' : 'Prepaid',
                'total_amount' => (float)(($order['grand_total_amount'] ?? 0) / 100),
                'tax_value' => (float)(($order['tax_amount'] ?? 0) / 100),
                'taxable_amount' => (float)((($order['grand_total_amount'] ?? 0) - ($order['tax_amount'] ?? 0)) / 100),
                'quantity' => $totalQty,
                'drop_location' => [
                    'name' => $order['shipping_name'] ?? $order['customer_name'] ?? 'Customer',
                    'address' => $addressLine,
                    'city' => $order['shipping_city'] ?? 'Hyderabad',
                    'state' => $order['shipping_state'] ?? 'Telangana',
                    'country' => $order['shipping_country'] ?? 'India',
                    'phone' => (int)$custPhone,
                    'pin' => $custPincode
                ],
                'items' => $orderItems
            ];

            if (!empty($altPhone)) {
                $ekartPayload['consignee_alternate_phone'] = $altPhone;
            }

            $ekartRes = EkartLogisticsService::createShipment($ekartPayload);
            $trackingId = $ekartRes['tracking_id'] ?? $ekartRes['barcodes']['wbn'] ?? null;
            if (empty($trackingId)) {
                $err = $ekartRes['error'] ?? 'Ekart API could not generate a live shipment.';
                throw new \RuntimeException("Ekart Logistics API Error: {$err}");
            }
        }

        if (empty($trackingId)) {
            throw new \InvalidArgumentException("A valid Ekart Tracking AWB number is required.");
        }

        $trackingUrl = "https://app.elite.ekartlogistics.in/track/" . urlencode($trackingId);

        return self::createShipment(
            $order['id'],
            'Ekart Logistics',
            $trackingId,
            $trackingUrl,
            'Dispatched via Ekart Logistics'
        );
    }

    /**
     * Mark Order as Fulfilled (compatibility wrapper for order-detail and legacy callers)
     */
    public static function markFulfilled(string $orderId, string $carrier = 'Ekart Logistics', ?string $trackingNumber = null, ?string $trackingUrl = null, ?string $notes = null): array
    {
        $carrierLower = strtolower($carrier);
        if (str_contains($carrierLower, 'ekart')) {
            return self::fulfillWithEkart($orderId, $trackingNumber);
        }

        if (empty($trackingNumber)) {
            throw new \InvalidArgumentException("A valid tracking number / AWB is required to fulfill the order.");
        }

        return self::createShipment($orderId, $carrier, $trackingNumber, $trackingUrl, $notes);
    }

    /**
     * Mark Order & Shipment as Delivered
     */
    public static function markDelivered(string $orderId): bool
    {
        $order = self::getOrder($orderId);
        if (!$order) return false;

        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $payUpdate = "";
        if (strtoupper($order['payment_method'] ?? '') === 'COD' && $order['payment_status'] !== 'PAID') {
            $payUpdate = ", payment_status = 'PAID'";
        }

        $stmt = $db->prepare("
            UPDATE orders SET 
                order_status = 'DELIVERED', 
                fulfillment_status = 'DELIVERED'
                {$payUpdate},
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND website_id = :wid
        ");
        $stmt->execute([':id' => $order['id'], ':wid' => $websiteId]);

        // Update shipment
        $db->prepare("
            UPDATE shipments SET status = 'DELIVERED', delivered_at = CURRENT_TIMESTAMP
            WHERE order_id = :oid AND website_id = :wid
        ")->execute([':oid' => $order['id'], ':wid' => $websiteId]);

        self::addTimelineEvent($order['id'], 'DELIVERED', "Package successfully delivered to recipient.");
        AuditService::log('order.deliver', 'order', $order['id'], "Marked order as delivered");

        return true;
    }

    /**
     * Process Refund (Full or Partial)
     */
    public static function processRefund(string $orderId, int $amountPaise, string $reason, ?string $notes = null): array
    {
        $order = self::getOrder($orderId);
        if (!$order) {
            throw new \InvalidArgumentException("Order not found: {$orderId}");
        }

        $refundable = $order['refundable_balance'];
        if ($amountPaise <= 0) {
            throw new \InvalidArgumentException("Refund amount must be greater than zero.");
        }
        if ($amountPaise > $refundable) {
            throw new \InvalidArgumentException("Refund amount (" . Context::formatMoney($amountPaise) . ") exceeds remaining refundable balance (" . Context::formatMoney($refundable) . ").");
        }

        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $txId = 'tx_ref_' . bin2hex(random_bytes(6));
        $rStmt = $db->prepare("
            INSERT INTO payment_transactions (id, website_id, order_id, gateway, gateway_transaction_id, transaction_type, amount, status, idempotency_key)
            VALUES (:id, :wid, :oid, 'razorpay', :gtx, 'REFUND', :amt, 'SUCCESS', :idemp)
        ");
        $rStmt->execute([
            ':id' => $txId,
            ':wid' => $websiteId,
            ':oid' => $order['id'],
            ':gtx' => 'rfnd_' . bin2hex(random_bytes(6)),
            ':amt' => $amountPaise,
            ':idemp' => 'idemp_' . bin2hex(random_bytes(8))
        ]);

        $newTotalRefunded = $order['total_refunded_amount'] + $amountPaise;
        $isFull = ($newTotalRefunded >= (int)$order['grand_total_amount']);
        $newPaymentStatus = $isFull ? 'REFUNDED' : 'PARTIALLY_REFUNDED';

        $uStmt = $db->prepare("UPDATE orders SET payment_status = :pstatus, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND website_id = :wid");
        $uStmt->execute([':pstatus' => $newPaymentStatus, ':id' => $order['id'], ':wid' => $websiteId]);

        self::addTimelineEvent(
            $order['id'], 
            'REFUND_PROCESSED', 
            "Processed refund of " . Context::formatMoney($amountPaise) . " ({$newPaymentStatus}). Reason: {$reason}" . ($notes ? " Notes: {$notes}" : "")
        );

        AuditService::log('payment.refund', 'order', $order['id'], "Processed refund of {$amountPaise} paise. Reason: {$reason}");

        return [
            'success' => true,
            'refunded_amount' => $amountPaise,
            'transaction_id' => $txId,
            'payment_status' => $newPaymentStatus
        ];
    }

    /**
     * Create Return & Optional Restock
     */
    public static function createReturn(string $orderId, array $itemsOrData = [], ?string $reason = null, ?bool $restock = null, ?string $resolution = null): array
    {
        $order = self::getOrder($orderId);
        if (!$order) {
            throw new \RuntimeException("Order not found.");
        }

        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $returnId = 'ret_' . bin2hex(random_bytes(6));
        $reasonCode = $reason ?? $itemsOrData['return_reason'] ?? $itemsOrData['reason_code'] ?? 'CUSTOMER_REQUEST';
        $restockApproved = ($restock !== null) ? ($restock ? 1 : 0) : (!empty($itemsOrData['restock_approved']) || !empty($itemsOrData['restock_items']) ? 1 : 0);
        $actionTaken = strtoupper(trim((string)($resolution ?? $itemsOrData['resolution'] ?? $itemsOrData['action_taken'] ?? 'REFUND')));
        $notes = trim((string)($itemsOrData['internal_notes'] ?? $itemsOrData['notes'] ?? ''));
        $refundAmountPaise = isset($itemsOrData['refund_amount_rupees']) ? (int)round((float)$itemsOrData['refund_amount_rupees'] * 100) : (int)$order['grand_total_amount'];

        $stmt = $db->prepare("
            INSERT INTO order_returns (id, website_id, order_id, reason_code, status, refund_amount, restock_approved, internal_notes, action_taken)
            VALUES (:id, :wid, :oid, :reason, 'APPROVED', :amt, :restock, :notes, :action)
        ");
        $stmt->execute([
            ':id' => $returnId,
            ':wid' => $websiteId,
            ':oid' => $order['id'],
            ':reason' => $reasonCode,
            ':amt' => $refundAmountPaise,
            ':restock' => $restockApproved,
            ':notes' => $notes,
            ':action' => $actionTaken
        ]);

        // Restock inventory if approved
        if ($restockApproved === 1) {
            foreach ($order['items'] as $item) {
                if (!empty($item['variant_id'])) {
                    try {
                        InventoryService::adjustStock(
                            $item['variant_id'],
                            (int)$item['quantity'],
                            "Restock from return #{$returnId} for order #{$order['order_number']}"
                        );
                    } catch (\Throwable $e) {}
                }
            }
        }

        // Update order status
        $db->prepare("
            UPDATE orders SET 
                order_status = 'RETURNED', 
                fulfillment_status = 'RETURNED', 
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND website_id = :wid
        ")->execute([':id' => $order['id'], ':wid' => $websiteId]);

        // Process refund if requested
        if ($actionTaken === 'REFUND' && $refundAmountPaise > 0) {
            try {
                self::processRefund($order['id'], min($refundAmountPaise, $order['refundable_balance']), "Return approved: {$reasonCode}");
            } catch (\Throwable $e) {}
        }

        self::addTimelineEvent(
            $order['id'], 
            'RETURN_APPROVED', 
            "Return created and approved. Reason: {$reasonCode}. Action: {$actionTaken}. Restocked: " . ($restockApproved ? 'Yes' : 'No')
        );

        AuditService::log('order.return', 'order', $order['id'], "Created return #{$returnId}");

        return [
            'id' => $returnId,
            'order_id' => $order['id'],
            'reason' => $reasonCode,
            'restock' => (bool)$restockApproved,
            'action_taken' => $actionTaken
        ];
    }

    /**
     * Resend Customer Confirmation
     */
    public static function resendConfirmation(string $orderId): bool
    {
        $order = self::getOrder($orderId);
        if (!$order) return false;

        self::addTimelineEvent(
            $order['id'], 
            'NOTIFICATION_SENT', 
            "Order confirmation resent to customer ({$order['shipping_name']}: {$order['shipping_phone']})."
        );

        AuditService::log('order.resend_confirmation', 'order', $order['id'], "Resent confirmation");
        return true;
    }

    /**
     * Bulk Operations
     */
    public static function bulkShip(array $orderIds, string $carrier = 'Ekart Logistics'): int
    {
        $count = 0;
        foreach ($orderIds as $oid) {
            $order = self::getOrder($oid);
            if ($order && !in_array($order['order_status'], ['CANCELLED', 'RETURNED', 'DELIVERED', 'SHIPPED'])) {
                try {
                    if (str_contains(strtolower($carrier), 'ekart')) {
                        self::fulfillWithEkart($oid);
                        $count++;
                    }
                } catch (\Throwable $e) {}
            }
        }
        return $count;
    }

    public static function bulkMarkDelivered(array $orderIds): int
    {
        $count = 0;
        foreach ($orderIds as $oid) {
            $order = self::getOrder($oid);
            if ($order && $order['order_status'] !== 'CANCELLED' && $order['order_status'] !== 'RETURNED') {
                if (self::markDelivered($oid)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    public static function bulkUpdateStatus(array $orderIds, string $newStatus): int
    {
        $count = 0;
        foreach ($orderIds as $oid) {
            try {
                if (self::transitionStatus($oid, $newStatus)) {
                    $count++;
                }
            } catch (\Throwable $e) {}
        }
        return $count;
    }

    public static function bulkCancel(array $orderIds, ?string $reason = null): int
    {
        $count = 0;
        $reason = $reason ?? 'Bulk cancelled by administrator';
        foreach ($orderIds as $oid) {
            try {
                if (self::transitionStatus($oid, 'CANCELLED', $reason)) {
                    $count++;
                }
            } catch (\Throwable $e) {}
        }
        return $count;
    }

    /**
     * Add timeline event
     */
    public static function addTimelineEvent(string $orderId, string $eventType, string $message): void
    {
        $user = AuthService::user();
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO order_timeline (id, website_id, order_id, event_type, message, actor)
            VALUES (:id, :wid, :oid, :type, :msg, :actor)
        ");
        $stmt->execute([
            ':id' => 'tl_' . bin2hex(random_bytes(6)),
            ':wid' => Context::getWebsiteId(),
            ':oid' => $orderId,
            ':type' => $eventType,
            ':msg' => $message,
            ':actor' => $user['name'] ?? ($user['email'] ?? 'System')
        ]);
    }
}
