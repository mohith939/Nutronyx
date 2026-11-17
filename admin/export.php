<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Context;
use Nutronyx\Services\AuthService;

AuthService::requireAuth();

// Clean any previously started output buffers to avoid HTML pollution
while (ob_get_level() > 0) {
    ob_end_clean();
}

$type = strtolower(trim($_GET['type'] ?? 'orders'));
$wid = Context::getWebsiteId();
$db = Database::getConnection();

$filename = "nutronyx_{$type}_" . date('Y-m-d_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

$output = fopen('php://output', 'w');

function writeCsvLine($handle, array $fields): void {
    fputcsv($handle, $fields, ',', '"', '\\');
}

// Write UTF-8 BOM so Microsoft Excel, Apple Numbers, and Google Sheets open it with proper character encoding
fwrite($output, "\xEF\xBB\xBF");

if ($type === 'customers') {
    writeCsvLine($output, ['Customer ID', 'Full Name', 'Email', 'Phone', 'Status', 'Total Orders', 'Total Spent (INR)', 'AOV (INR)', 'Last Order Date', 'Member Since']);
    $stmt = $db->prepare("SELECT * FROM customers WHERE website_id = :wid ORDER BY created_at DESC");
    $stmt->execute([':wid' => $wid]);
    while ($c = $stmt->fetch()) {
        $spent = ($c['total_spent_amount'] ?? 0) / 100;
        $orders = (int)($c['total_orders'] ?? 0);
        $aov = $orders > 0 ? ($spent / $orders) : 0;
        writeCsvLine($output, [
            $c['id'],
            $c['name'],
            $c['email'],
            $c['phone'] ?? '',
            $c['status'] ?? 'Active',
            (string)$orders,
            number_format($spent, 2, '.', ''),
            number_format($aov, 2, '.', ''),
            !empty($c['last_order_at']) ? date('Y-m-d H:i', strtotime($c['last_order_at'])) : 'Never',
            date('Y-m-d', strtotime($c['created_at']))
        ]);
    }
} elseif ($type === 'products') {
    writeCsvLine($output, [
        'Product ID', 'Product Title', 'Product Slug', 'Category', 'Status',
        'Storefront Visible', 'Variant SKU', 'Variant Title', 'Regular Price (INR)',
        'Sale Price (INR)', 'Weight (g)', 'Inventory On Hand', 'Low Stock Threshold', 'Created At'
    ]);

    $selectedIds = !empty($_GET['ids']) ? array_filter(explode(',', (string)$_GET['ids'])) : [];
    $where = "p.website_id = :wid";
    $params = [':wid' => $wid];

    if (!empty($selectedIds)) {
        $inPlaceholders = [];
        foreach ($selectedIds as $idx => $sid) {
            $key = ":pid_{$idx}";
            $inPlaceholders[] = $key;
            $params[$key] = trim($sid);
        }
        $where .= " AND p.id IN (" . implode(',', $inPlaceholders) . ")";
    }

    $stmt = $db->prepare("
        SELECT p.id as product_id, p.title as product_title, p.slug as product_slug,
               c.name as category_name, p.status as product_status,
               p.is_visible_storefront,
               pv.id as variant_id, pv.sku as variant_sku, pv.title as variant_title,
               pv.price_amount, pv.compare_at_amount, pv.weight_grams,
               COALESCE(inv.quantity_on_hand, 0) as stock_qty,
               COALESCE(inv.low_stock_threshold, 10) as low_thresh,
               p.created_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN product_variants pv ON pv.product_id = p.id
        LEFT JOIN inventory_stocks inv ON inv.variant_id = pv.id
        WHERE {$where}
        ORDER BY p.title ASC, pv.price_amount ASC
    ");
    $stmt->execute($params);
    while ($row = $stmt->fetch()) {
        writeCsvLine($output, [
            $row['product_id'],
            $row['product_title'],
            $row['product_slug'],
            $row['category_name'] ?? 'Uncategorized',
            $row['product_status'] ?? 'ACTIVE',
            ($row['is_visible_storefront'] == 1) ? 'Yes' : 'No',
            $row['variant_sku'] ?? '',
            $row['variant_title'] ?? 'Standard',
            number_format(($row['price_amount'] ?? 0) / 100, 2, '.', ''),
            !empty($row['compare_at_amount']) ? number_format($row['compare_at_amount'] / 100, 2, '.', '') : '',
            (string)($row['weight_grams'] ?? 0),
            (string)(int)$row['stock_qty'],
            (string)(int)$row['low_thresh'],
            date('Y-m-d H:i', strtotime($row['created_at']))
        ]);
    }
} elseif ($type === 'categories') {
    writeCsvLine($output, [
        'Category ID', 'Category Name', 'Slug', 'Description', 'Product Count',
        'Status', 'Featured', 'Subcategories', 'Created At'
    ]);

    $selectedIds = !empty($_GET['ids']) ? array_filter(explode(',', (string)$_GET['ids'])) : [];
    $where = "c.website_id = :wid";
    $params = [':wid' => $wid];

    if (!empty($selectedIds)) {
        $inPlaceholders = [];
        foreach ($selectedIds as $idx => $sid) {
            $key = ":cid_{$idx}";
            $inPlaceholders[] = $key;
            $params[$key] = trim($sid);
        }
        $where .= " AND c.id IN (" . implode(',', $inPlaceholders) . ")";
    }

    $stmt = $db->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.website_id = c.website_id) as product_count
        FROM categories c
        WHERE {$where}
        ORDER BY c.created_at DESC
    ");
    $stmt->execute($params);
    while ($row = $stmt->fetch()) {
        $subcats = !empty($row['subcategories_json']) ? implode(', ', json_decode($row['subcategories_json'], true) ?: []) : '';
        writeCsvLine($output, [
            $row['id'],
            $row['name'],
            $row['slug'],
            $row['description'] ?? '',
            (string)(int)$row['product_count'],
            $row['status'] ?? 'ACTIVE',
            ($row['is_featured'] == 1) ? 'Yes' : 'No',
            $subcats,
            date('Y-m-d H:i', strtotime($row['created_at']))
        ]);
    }
} elseif ($type === 'reports' || $type === 'analytics') {
    writeCsvLine($output, ['Date', 'Orders Placed', 'Gross Sales (INR)', 'Fulfilled Orders', 'Cancelled Orders', 'AOV (INR)']);
    $stmt = $db->prepare("
        SELECT strftime('%Y-%m-%d', created_at) as order_day,
               COUNT(*) as orders_count,
               SUM(CASE WHEN payment_status IN ('PAID', 'PARTIALLY_REFUNDED') THEN grand_total_amount ELSE 0 END) as gross_sales,
               SUM(CASE WHEN order_status = 'FULFILLED' THEN 1 ELSE 0 END) as fulfilled_count,
               SUM(CASE WHEN order_status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled_count
        FROM orders
        WHERE website_id = :wid
        GROUP BY order_day
        ORDER BY order_day DESC
    ");
    $stmt->execute([':wid' => $wid]);
    while ($r = $stmt->fetch()) {
        $salesRupees = ($r['gross_sales'] ?? 0) / 100;
        $cnt = (int)$r['orders_count'];
        $aov = $cnt > 0 ? ($salesRupees / $cnt) : 0;
        writeCsvLine($output, [
            $r['order_day'],
            (string)$cnt,
            number_format($salesRupees, 2, '.', ''),
            (string)(int)$r['fulfilled_count'],
            (string)(int)$r['cancelled_count'],
            number_format($aov, 2, '.', '')
        ]);
    }
} else {
    // Default: Orders export
    $exportStartDate = $_GET['start_date'] ?? null;
    $exportEndDate = $_GET['end_date'] ?? null;
    $selectedIds = !empty($_GET['ids']) ? array_filter(explode(',', (string)$_GET['ids'])) : [];

    $exportSql = "
        SELECT id, order_number, created_at, shipping_name, shipping_phone, order_status, payment_status, fulfillment_status,
               subtotal_amount, discount_amount, shipping_amount, grand_total_amount, currency
        FROM orders
        WHERE website_id = :wid
    ";
    $exportParams = [':wid' => $wid];

    if (!empty($selectedIds)) {
        $inPlaceholders = [];
        foreach ($selectedIds as $idx => $sid) {
            $key = ":oid_{$idx}";
            $inPlaceholders[] = $key;
            $exportParams[$key] = trim($sid);
        }
        $exportSql .= " AND id IN (" . implode(',', $inPlaceholders) . ")";
    }

    if ($exportStartDate && $exportEndDate) {
        $exportSql .= " AND created_at BETWEEN :start AND :end";
        $exportParams[':start'] = $exportStartDate;
        $exportParams[':end'] = $exportEndDate;
    }

    $exportSql .= " ORDER BY created_at DESC";

    writeCsvLine($output, [
        'Order Number', 'Date', 'Customer Name', 'Phone', 'Products Purchased',
        'Order Status', 'Payment Status', 'Fulfillment Status',
        'Subtotal (INR)', 'Discount (INR)', 'Shipping (INR)', 'Grand Total (INR)', 'Currency'
    ]);

    $stmt = $db->prepare($exportSql);
    $stmt->execute($exportParams);

    $itemQuery = $db->prepare("SELECT title, quantity, sku FROM order_items WHERE order_id = :oid OR order_id = :onum");

    while ($o = $stmt->fetch()) {
        $itemQuery->execute([':oid' => $o['id'], ':onum' => $o['order_number']]);
        $itemsList = [];
        while ($it = $itemQuery->fetch()) {
            $itemTitle = trim((string)$it['title']);
            $qty = (int)$it['quantity'];
            $sku = !empty($it['sku']) ? " [SKU: {$it['sku']}]" : "";
            $itemsList[] = "{$qty}x {$itemTitle}{$sku}";
        }
        $productsStr = !empty($itemsList) ? implode('; ', $itemsList) : 'Pure Botanical Formulations';

        writeCsvLine($output, [
            $o['order_number'],
            $o['created_at'],
            $o['shipping_name'] ?? 'Guest Shopper',
            $o['shipping_phone'] ?? '',
            $productsStr,
            $o['order_status'],
            $o['payment_status'],
            $o['fulfillment_status'],
            number_format(($o['subtotal_amount'] ?? 0) / 100, 2, '.', ''),
            number_format(($o['discount_amount'] ?? 0) / 100, 2, '.', ''),
            number_format(($o['shipping_amount'] ?? 0) / 100, 2, '.', ''),
            number_format(($o['grand_total_amount'] ?? 0) / 100, 2, '.', ''),
            $o['currency'] ?? 'INR'
        ]);
    }
}

fclose($output);
exit;
