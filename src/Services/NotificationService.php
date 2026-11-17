<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;
use PDO;

class NotificationService
{
    /**
     * Get live store notifications
     */
    public static function getNotifications(int $limit = 15): array
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $notifications = [];

        // 1. Recent Orders
        try {
            $stmt = $db->prepare("
                SELECT id, order_number, shipping_name, grand_total_amount, order_status, created_at 
                FROM orders 
                WHERE website_id = :wid 
                ORDER BY created_at DESC 
                LIMIT 6
            ");
            $stmt->execute([':wid' => $wid]);
            $recentOrders = $stmt->fetchAll();
            foreach ($recentOrders as $o) {
                $totalStr = Context::formatMoney((int)$o['grand_total_amount']);
                $notifications[] = [
                    'id' => 'ord_' . $o['id'],
                    'type' => 'order',
                    'icon' => 'shopping-bag',
                    'title' => "Order #{$o['order_number']} placed",
                    'message' => "Customer {$o['shipping_name']} placed order for {$totalStr} ({$o['order_status']}).",
                    'link' => "order-detail.php?id=" . urlencode($o['id']),
                    'created_at' => $o['created_at'],
                    'time_ago' => self::formatTimeAgo($o['created_at']),
                    'priority' => 'high'
                ];
            }
        } catch (\Throwable $e) {}

        // 2. Low Stock Alerts
        try {
            $stmt = $db->prepare("
                SELECT p.id, p.title as name, s.stock_available, s.low_stock_threshold
                FROM inventory_stocks s
                JOIN products p ON s.product_id = p.id
                WHERE s.website_id = :wid AND s.stock_available <= COALESCE(s.low_stock_threshold, 5)
                ORDER BY s.stock_available ASC
                LIMIT 4
            ");
            $stmt->execute([':wid' => $wid]);
            $lowStock = $stmt->fetchAll();
            foreach ($lowStock as $ls) {
                $notifications[] = [
                    'id' => 'stk_' . $ls['id'],
                    'type' => 'stock',
                    'icon' => 'alert-triangle',
                    'title' => "Low stock: {$ls['name']}",
                    'message' => "Only {$ls['stock_available']} units left in inventory stock.",
                    'link' => "inventory.php",
                    'created_at' => date('Y-m-d H:i:s', time() - 3600),
                    'time_ago' => 'Action needed',
                    'priority' => 'urgent'
                ];
            }
        } catch (\Throwable $e) {}

        // 3. Recent Customer Registrations
        try {
            $stmt = $db->prepare("
                SELECT id, name, email, total_orders, created_at
                FROM customers
                WHERE website_id = :wid
                ORDER BY created_at DESC
                LIMIT 4
            ");
            $stmt->execute([':wid' => $wid]);
            $newCustomers = $stmt->fetchAll();
            foreach ($newCustomers as $nc) {
                $cohort = ((int)$nc['total_orders'] > 1) ? 'Repeat Buyer' : 'New Shopper';
                $notifications[] = [
                    'id' => 'cust_' . $nc['id'],
                    'type' => 'customer',
                    'icon' => 'user-check',
                    'title' => "Customer registration: {$nc['name']}",
                    'message' => "{$nc['email']} enrolled as {$cohort}.",
                    'link' => "customers.php",
                    'created_at' => $nc['created_at'],
                    'time_ago' => self::formatTimeAgo($nc['created_at']),
                    'priority' => 'normal'
                ];
            }
        } catch (\Throwable $e) {}

        // 4. Recent Reviews
        try {
            $stmt = $db->prepare("
                SELECT r.id, r.product_id, r.customer_name, r.rating, r.title, r.created_at, p.title as product_name
                FROM reviews r
                LEFT JOIN products p ON r.product_id = p.id
                WHERE r.website_id = :wid
                ORDER BY r.created_at DESC
                LIMIT 3
            ");
            $stmt->execute([':wid' => $wid]);
            $reviews = $stmt->fetchAll();
            foreach ($reviews as $rev) {
                $notifications[] = [
                    'id' => 'rev_' . $rev['id'],
                    'type' => 'review',
                    'icon' => 'star',
                    'title' => "{$rev['rating']}★ Review by {$rev['customer_name']}",
                    'message' => '“' . ($rev['title'] ?: 'Verified Customer Feedback') . '” for ' . ($rev['product_name'] ?? 'Product'),
                    'link' => "reviews.php",
                    'created_at' => $rev['created_at'],
                    'time_ago' => self::formatTimeAgo($rev['created_at']),
                    'priority' => 'normal'
                ];
            }
        } catch (\Throwable $e) {}

        // 5. Recent Contact & Partnership Inquiries
        try {
            InquiryService::ensureTables();
            $stmt = $db->prepare("
                SELECT id, type, name, category, status, created_at 
                FROM customer_inquiries 
                WHERE website_id = :wid 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute([':wid' => $wid]);
            $inquiries = $stmt->fetchAll();
            foreach ($inquiries as $inq) {
                $isPartner = ($inq['type'] === 'PARTNERSHIP');
                $notifications[] = [
                    'id' => 'inq_' . $inq['id'],
                    'type' => 'inquiry',
                    'icon' => $isPartner ? 'briefcase' : 'mail',
                    'title' => ($isPartner ? 'Partnership Proposal: ' : 'Contact Inquiry: ') . $inq['name'],
                    'message' => ($inq['category'] ?: ($isPartner ? 'Partnership Application' : 'Contact Message')) . ' (' . $inq['status'] . ')',
                    'link' => "inquiries.php?id=" . urlencode($inq['id']),
                    'created_at' => $inq['created_at'],
                    'time_ago' => self::formatTimeAgo($inq['created_at']),
                    'priority' => ($inq['status'] === 'NEW') ? 'high' : 'normal'
                ];
            }
        } catch (\Throwable $e) {}

        // Sort descending by created_at
        usort($notifications, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        // Filter and limit
        $readIds = $_SESSION['read_notifications'] ?? [];
        foreach ($notifications as &$n) {
            $n['is_read'] = in_array($n['id'], $readIds, true);
        }

        return array_slice($notifications, 0, $limit);
    }

    public static function getUnreadCount(): int
    {
        $all = self::getNotifications(20);
        $unread = 0;
        foreach ($all as $n) {
            if (!$n['is_read']) {
                $unread++;
            }
        }
        return $unread;
    }

    public static function markAllRead(): void
    {
        $all = self::getNotifications(50);
        $readIds = array_column($all, 'id');
        $_SESSION['read_notifications'] = $readIds;
    }

    private static function formatTimeAgo(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if (!$timestamp) return 'recently';
        
        $diff = time() - $timestamp;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return (int)($diff / 60) . 'm ago';
        if ($diff < 86400) return (int)($diff / 3600) . 'h ago';
        if ($diff < 604800) return (int)($diff / 86400) . 'd ago';
        return date('d M', $timestamp);
    }
}
