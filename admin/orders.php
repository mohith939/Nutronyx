<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\OrderService;

AuthService::requireAuth();

$activePage = 'orders';
$activeWebsite = Context::getWebsite();
$db = Database::getConnection();

// -------------------------------------------------------------------------
// 1. AJAX ENDPOINTS (Used by window.NutronyxOrder in admin.js)
// -------------------------------------------------------------------------
if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    try {
        if ($action === 'get_order_details') {
            $orderId = trim((string)($_GET['order_id'] ?? ''));
            $order = OrderService::getOrder($orderId);
            if (!$order) {
                echo json_encode(['success' => false, 'error' => 'Order not found']);
                exit;
            }
            echo json_encode(['success' => true, 'order' => $order]);
            exit;
        }

        if ($action === 'mark_delivered') {
            $orderId = trim((string)($_POST['order_id'] ?? ''));
            OrderService::markDelivered($orderId);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'resend_confirmation') {
            $orderId = trim((string)($_POST['order_id'] ?? ''));
            OrderService::resendConfirmation($orderId);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown AJAX action: ' . $action]);
        exit;
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// 2. FORM ACTIONS & LIFECYCLE WORKFLOWS
// -------------------------------------------------------------------------
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_order') {
            // Manual admin order creation
            $order = OrderService::createOrder($_POST);
            $notice = "Order " . htmlspecialchars($order['order_number']) . " created successfully.";
        } elseif ($action === 'ship_order') {
            $orderId = trim($_POST['order_id'] ?? '');
            $carrier = trim($_POST['carrier'] ?? 'BlueDart Express');
            $trackingNumber = trim($_POST['tracking_number'] ?? '');
            $trackingUrl = trim($_POST['tracking_url'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            OrderService::createShipment($orderId, $carrier, $trackingNumber, $trackingUrl, $notes);
            $notice = "Shipment created via {$carrier} (Tracking: {$trackingNumber}).";
        } elseif ($action === 'refund_order') {
            $orderId = trim($_POST['order_id'] ?? '');
            $refundRupees = (float)($_POST['refund_amount_rupees'] ?? 0);
            $reason = trim($_POST['refund_reason'] ?? 'Customer Return');
            $notes = trim($_POST['refund_notes'] ?? '');
            $amountPaise = (int)round($refundRupees * 100);

            OrderService::processRefund($orderId, $amountPaise, $reason, $notes);
            $notice = "Refund of " . Context::formatMoney($amountPaise) . " processed successfully.";
        } elseif ($action === 'return_order') {
            $orderId = trim($_POST['order_id'] ?? '');
            $reason = trim($_POST['return_reason'] ?? 'Customer Return');
            $restock = !empty($_POST['restock_items']);
            $resolution = trim($_POST['resolution'] ?? 'REFUND');

            OrderService::createReturn($orderId, [], $reason, $restock, $resolution);
            $notice = "Return created successfully (Resolution: {$resolution}).";
        } elseif ($action === 'update_address') {
            $orderId = trim($_POST['order_id'] ?? '');
            OrderService::updateShippingAddress($orderId, [
                'shipping_name' => trim($_POST['shipping_name'] ?? ''),
                'shipping_phone' => trim($_POST['shipping_phone'] ?? ''),
                'shipping_address_line1' => trim($_POST['shipping_address_line1'] ?? ''),
                'shipping_address_line2' => trim($_POST['shipping_address_line2'] ?? ''),
                'shipping_city' => trim($_POST['shipping_city'] ?? ''),
                'shipping_state' => trim($_POST['shipping_state'] ?? ''),
                'shipping_postal_code' => trim($_POST['shipping_postal_code'] ?? ''),
            ]);
            $notice = "Shipping address updated and logged in the immutable audit timeline.";
        } elseif ($action === 'add_note') {
            $orderId = trim($_POST['order_id'] ?? '');
            $note = trim($_POST['note_text'] ?? '');
            if ($note) {
                OrderService::addInternalNote($orderId, $note);
                $notice = "Internal note recorded successfully.";
            }
        } elseif ($action === 'update_status') {
            $orderId = trim($_POST['order_id'] ?? '');
            $newStatus = strtoupper(trim($_POST['status'] ?? 'PROCESSING'));
            OrderService::transitionStatus($orderId, $newStatus);
            $notice = "Order status updated to " . htmlspecialchars($newStatus) . ".";
        } elseif ($action === 'delete_order') {
            $orderId = trim($_POST['order_id'] ?? '');
            $stmt = $db->prepare("DELETE FROM orders WHERE id = :id");
            $stmt->execute([':id' => $orderId]);
            $notice = "Order deleted successfully.";
        } elseif ($action === 'bulk_action') {
            $subaction = trim($_POST['subaction'] ?? '');
            $ids = array_filter(explode(',', (string)($_POST['selected_ids'] ?? '')));

            if (empty($ids)) {
                $error = "Please select at least one order to perform a bulk operation.";
            } else {
                if ($subaction === 'bulk_ship') {
                    $cnt = OrderService::bulkShip($ids);
                    $notice = "Successfully created shipments for {$cnt} order(s).";
                } elseif ($subaction === 'bulk_deliver') {
                    $cnt = OrderService::bulkMarkDelivered($ids);
                    $notice = "Successfully marked {$cnt} order(s) as delivered.";
                } elseif ($subaction === 'bulk_status') {
                    $st = strtoupper(trim($_POST['bulk_status_value'] ?? 'PROCESSING'));
                    $cnt = OrderService::bulkUpdateStatus($ids, $st);
                    $notice = "Successfully transitioned {$cnt} order(s) to {$st}.";
                } elseif ($subaction === 'cancel_orders') {
                    $cnt = OrderService::bulkCancel($ids);
                    $notice = "Successfully cancelled {$cnt} order(s) and restocked inventory.";
                }
            }
        } elseif ($action === 'import_orders') {
            if (!empty($_FILES['csv_file']['tmp_name'])) {
                $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $header = fgetcsv($handle);
                $importedCount = 0;

                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 4) continue;
                    $custName = trim($row[0] ?? 'Guest Shopper');
                    $custPhone = trim($row[1] ?? '9876543210');
                    $amtRupees = (float)($row[2] ?? 449);
                    $payMethod = strtoupper(trim($row[3] ?? 'COD'));

                    $vRow = $db->query("SELECT id FROM product_variants LIMIT 1")->fetch();
                    $vId = $vRow ? $vRow['id'] : 'var_abc_300g';

                    try {
                        OrderService::createOrder([
                            'customer_name' => $custName,
                            'customer_phone' => $custPhone,
                            'payment_method' => $payMethod,
                            'items' => [
                                ['variant_id' => $vId, 'quantity' => 1, 'unit_price' => $amtRupees]
                            ]
                        ]);
                        $importedCount++;
                    } catch (\Throwable $ex) {
                        // skip invalid rows
                    }
                }
                fclose($handle);
                $notice = "Successfully imported {$importedCount} order(s) from CSV.";
            } else {
                $error = "Please select a valid CSV file to upload.";
            }
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

// -------------------------------------------------------------------------
// 3. TAB COUNTS & ADVANCED FILTER PARAMS
// -------------------------------------------------------------------------
$tabCounts = OrderService::getOrderCounts();

$statusTab = strtoupper($_GET['tab'] ?? 'ALL');
$search = trim($_GET['search'] ?? '');
$dateRange = trim($_GET['date_range'] ?? 'all');
$paymentFilter = trim($_GET['payment_status'] ?? '');
$fulfillmentFilter = trim($_GET['fulfillment_status'] ?? '');
$carrierFilter = trim($_GET['carrier'] ?? '');
$minAmount = isset($_GET['min_amount']) && $_GET['min_amount'] !== '' ? (float)$_GET['min_amount'] : null;
$maxAmount = isset($_GET['max_amount']) && $_GET['max_amount'] !== '' ? (float)$_GET['max_amount'] : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 10)));

$filters = [
    'tab' => $statusTab,
    'search' => $search,
    'date_range' => $dateRange,
    'payment_status' => $paymentFilter,
    'fulfillment_status' => $fulfillmentFilter,
    'carrier' => $carrierFilter,
    'min_amount' => $minAmount,
    'max_amount' => $maxAmount,
    'page' => $page,
    'per_page' => $perPage,
];

$orderData = OrderService::getOrdersAdvanced($filters);
$orders = $orderData['orders'];
$totalOrders = $orderData['total'];
$totalPages = $orderData['pages'] ?? $orderData['total_pages'] ?? 1;

// Load products and variants for manual Create Order modal
$catalogStmt = $db->query("
    SELECT p.id as product_id, p.title as product_title, v.id as variant_id, v.title as variant_title, v.sku, v.price_amount
    FROM products p
    JOIN product_variants v ON v.product_id = p.id
    WHERE p.status = 'ACTIVE'
    ORDER BY p.title ASC
");
$catalogVariants = $catalogStmt->fetchAll();

// Pre-load default order only if order_id is explicitly passed in URL
$initialOrderId = $_GET['order_id'] ?? null;
$initialOrder = $initialOrderId ? OrderService::getOrder($initialOrderId) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders &amp; Fulfillments &mdash; Nutronyx Admin Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .order-table-row {
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .order-table-row:hover {
            background: #F9FAFB !important;
        }
        .order-table-row.selected-order-row {
            background: #F0FDF4 !important;
        }
        .action-dots-btn {
            background: none;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #6B7280;
            transition: all 0.15s;
        }
        .action-dots-btn:hover {
            background: #F3F4F6;
            color: #111827;
            border-color: #D1D5DB;
        }
        .action-dropdown-menu {
            display: none;
            position: absolute;
            right: 16px;
            margin-top: 4px;
            width: 200px;
            background: #FFFFFF;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1);
            z-index: 50;
            padding: 4px 0;
            text-align: left;
        }
        .action-dropdown-menu.show {
            display: block;
        }
        .action-dropdown-menu a, .action-dropdown-menu button {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 8px 14px;
            font-size: 13px;
            color: #374151;
            text-decoration: none;
            background: none;
            border: none;
            cursor: pointer;
            transition: background 0.12s;
        }
        .action-dropdown-menu a:hover, .action-dropdown-menu button:hover {
            background: #F3F4F6;
            color: #111827;
        }
        .action-dropdown-menu .menu-divider {
            height: 1px;
            background: #E5E7EB;
            margin: 4px 0;
        }
        .filter-select {
            padding: 7px 12px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 7px;
            border: 1px solid #E5E7EB;
            background: #FFF;
            color: #374151;
            cursor: pointer;
            outline: none;
            transition: border-color 0.15s;
        }
        .filter-select:hover {
            border-color: #D1D5DB;
        }
    </style>
</head>
<body class="admin-body orders-page-layout">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="admin-layout-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main-stage">
            
            <!-- SPLIT SCREEN LAYOUT: Master Table on Left (inside single card), Live Workspace on Right (Matching Mockup) -->
            <div class="orders-split-layout">
                
                <!-- Left Column: Master Orders Container Card -->
                <div class="orders-master-column">
                    <div class="orders-card-left">
                        
                        <!-- Header with Serif Title, Subtitle, and Actions -->
                        <div class="orders-left-header">
                            <div>
                                <h1 class="orders-title">Orders &amp; Fulfillments</h1>
                                <p class="orders-subtitle">Manage orders, payments, shipments, returns and customer fulfillments.</p>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxOrder.openExportModal()" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: 13px; font-weight: 600; border-radius: 8px;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    <span>Export</span>
                                </button>
                                <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxOrder.openImportModal()" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: 13px; font-weight: 600; border-radius: 8px;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                    <span>Import</span>
                                </button>
                                <button type="button" class="btn-create-order-forest" onclick="window.NutronyxOrder.openCreateOrderModal()">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                    <span>Create Order</span>
                                </button>
                            </div>
                        </div>

                        <!-- Feedback Toasts / Notices -->
                        <?php if (!empty($notice)): ?>
                            <div class="dash-panel" style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; padding: 12px 18px; margin-bottom: 20px; font-weight: 500; font-size: 13.5px; display: flex; align-items: center; gap: 8px; border-radius: 8px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span><?= htmlspecialchars($notice) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="dash-panel" style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; padding: 12px 18px; margin-bottom: 20px; font-weight: 500; font-size: 13.5px; display: flex; align-items: center; gap: 8px; border-radius: 8px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                <span><?= htmlspecialchars($error) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Horizontal Status Tabs Bar (Matching Mockup) -->
                        <div class="order-tabs-bar">
                            <a href="orders.php?tab=ALL<?= $search ? '&search=' . urlencode($search) : '' ?>" class="order-tab-btn <?= ($statusTab === 'ALL') ? 'active' : '' ?>">
                                <span>All Orders</span>
                                <span class="order-tab-counter"><?= number_format($tabCounts['ALL'] ?? 0) ?></span>
                            </a>
                            <a href="orders.php?tab=PENDING<?= $search ? '&search=' . urlencode($search) : '' ?>" class="order-tab-btn <?= ($statusTab === 'PENDING') ? 'active' : '' ?>">
                                <span>Pending</span>
                                <span class="order-tab-counter"><?= number_format($tabCounts['PENDING'] ?? 0) ?></span>
                            </a>
                            <a href="orders.php?tab=PROCESSING<?= $search ? '&search=' . urlencode($search) : '' ?>" class="order-tab-btn <?= ($statusTab === 'PROCESSING') ? 'active' : '' ?>">
                                <span>Processing</span>
                                <span class="order-tab-counter"><?= number_format($tabCounts['PROCESSING'] ?? 0) ?></span>
                            </a>
                            <a href="orders.php?tab=SHIPPED<?= $search ? '&search=' . urlencode($search) : '' ?>" class="order-tab-btn <?= ($statusTab === 'SHIPPED') ? 'active' : '' ?>">
                                <span>Shipped</span>
                                <span class="order-tab-counter"><?= number_format($tabCounts['SHIPPED'] ?? 0) ?></span>
                            </a>
                            <a href="orders.php?tab=DELIVERED<?= $search ? '&search=' . urlencode($search) : '' ?>" class="order-tab-btn <?= ($statusTab === 'DELIVERED') ? 'active' : '' ?>">
                                <span>Delivered</span>
                                <span class="order-tab-counter"><?= number_format($tabCounts['DELIVERED'] ?? 0) ?></span>
                            </a>
                            <a href="orders.php?tab=RETURNS<?= $search ? '&search=' . urlencode($search) : '' ?>" class="order-tab-btn <?= ($statusTab === 'RETURNS') ? 'active' : '' ?>">
                                <span>Returns</span>
                                <span class="order-tab-counter"><?= number_format($tabCounts['RETURNS'] ?? 0) ?></span>
                            </a>
                        </div>

                        <!-- Filter Toolbar (Matching Mockup: Search | Date [📅 Last 30 Days ⌄] | Payment [All ⌄] | Fulfillment [All ⌄] | [More Filters]) -->
                        <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 10px; padding: 10px 14px; margin-bottom: 16px; display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                            <form method="GET" action="orders.php" id="orderFilterForm" style="display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; flex: 1;">
                                <input type="hidden" name="tab" value="<?= htmlspecialchars($statusTab) ?>">

                                <!-- Search Input -->
                                <div style="position: relative; min-width: 260px; flex: 1;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                    <input type="text" name="search" class="search-input" placeholder="Search by order ID, customer, phone, email..." value="<?= htmlspecialchars($search) ?>" style="width: 100%; padding: 7px 12px 7px 34px; border-radius: 7px; border: 1px solid #E5E7EB; font-size: 13px; outline: none;">
                                </div>

                                <!-- Date Range Dropdown with Stacked Label -->
                                <div class="filter-field-group">
                                    <label class="filter-field-label">Order Date</label>
                                    <select name="date_range" class="filter-select" onchange="document.getElementById('orderFilterForm').submit()">
                                        <option value="all" <?= ($dateRange === 'all') ? 'selected' : '' ?>>All Time</option>
                                        <option value="today" <?= ($dateRange === 'today') ? 'selected' : '' ?>>Today</option>
                                        <option value="yesterday" <?= ($dateRange === 'yesterday') ? 'selected' : '' ?>>Yesterday</option>
                                        <option value="7d" <?= ($dateRange === '7d') ? 'selected' : '' ?>>Last 7 Days</option>
                                        <option value="30d" <?= ($dateRange === '30d') ? 'selected' : '' ?>>📅 Last 30 Days</option>
                                        <option value="month" <?= ($dateRange === 'month') ? 'selected' : '' ?>>This Month</option>
                                    </select>
                                </div>

                                <!-- Payment Status Dropdown with Stacked Label -->
                                <div class="filter-field-group">
                                    <label class="filter-field-label">Payment</label>
                                    <select name="payment_status" class="filter-select" onchange="document.getElementById('orderFilterForm').submit()">
                                        <option value="" <?= ($paymentFilter === '') ? 'selected' : '' ?>>All</option>
                                        <option value="PAID" <?= ($paymentFilter === 'PAID') ? 'selected' : '' ?>>Paid</option>
                                        <option value="COD" <?= ($paymentFilter === 'COD') ? 'selected' : '' ?>>COD</option>
                                        <option value="REFUNDED" <?= ($paymentFilter === 'REFUNDED') ? 'selected' : '' ?>>Refunded</option>
                                        <option value="PENDING" <?= ($paymentFilter === 'PENDING') ? 'selected' : '' ?>>Pending</option>
                                    </select>
                                </div>

                                <!-- Fulfillment Status Dropdown with Stacked Label -->
                                <div class="filter-field-group">
                                    <label class="filter-field-label">Fulfillment</label>
                                    <select name="fulfillment_status" class="filter-select" onchange="document.getElementById('orderFilterForm').submit()">
                                        <option value="" <?= ($fulfillmentFilter === '') ? 'selected' : '' ?>>All</option>
                                        <option value="DELIVERED" <?= ($fulfillmentFilter === 'DELIVERED') ? 'selected' : '' ?>>Delivered</option>
                                        <option value="DISPATCHING" <?= ($fulfillmentFilter === 'DISPATCHING') ? 'selected' : '' ?>>Dispatching</option>
                                        <option value="PREPARING" <?= ($fulfillmentFilter === 'PREPARING') ? 'selected' : '' ?>>Preparing</option>
                                        <option value="SHIPPED" <?= ($fulfillmentFilter === 'SHIPPED') ? 'selected' : '' ?>>Shipped</option>
                                        <option value="PENDING" <?= ($fulfillmentFilter === 'PENDING') ? 'selected' : '' ?>>Pending</option>
                                        <option value="RETURNED" <?= ($fulfillmentFilter === 'RETURNED') ? 'selected' : '' ?>>Returned</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn-action btn-action-sm" style="display: none;">Filter</button>
                                <?php if ($search || $dateRange !== 'all' || $paymentFilter || $fulfillmentFilter || $carrierFilter): ?>
                                    <a href="orders.php?tab=<?= urlencode($statusTab) ?>" style="font-size: 12.5px; color: #EF4444; text-decoration: none; font-weight: 600; padding-bottom: 6px;">Reset</a>
                                <?php endif; ?>
                            </form>

                            <div style="padding-bottom: 1px;">
                                <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxOrder.toggleMoreFilters()" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; font-size: 13px; font-weight: 600; border-radius: 7px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                                    <span>More Filters</span>
                                </button>
                            </div>
                        </div>

                        <!-- More Filters Expandable Drawer -->
                        <div id="orderMoreFiltersPanel" style="display: <?= ($carrierFilter || $minAmount !== null || $maxAmount !== null) ? 'block' : 'none' ?>; background: #F9FAFB; border: 1px dashed #D1D5DB; border-radius: 8px; padding: 14px 18px; margin-bottom: 16px;">
                            <form method="GET" action="orders.php" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
                                <input type="hidden" name="tab" value="<?= htmlspecialchars($statusTab) ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <input type="hidden" name="date_range" value="<?= htmlspecialchars($dateRange) ?>">
                                <input type="hidden" name="payment_status" value="<?= htmlspecialchars($paymentFilter) ?>">
                                <input type="hidden" name="fulfillment_status" value="<?= htmlspecialchars($fulfillmentFilter) ?>">

                                <div>
                                    <label style="display: block; font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 4px;">Logistics Carrier</label>
                                    <select name="carrier" class="filter-select" style="min-width: 160px;">
                                        <option value="">Any Carrier</option>
                                        <option value="BlueDart Express" <?= ($carrierFilter === 'BlueDart Express') ? 'selected' : '' ?>>BlueDart Express</option>
                                        <option value="Delhivery Logistics" <?= ($carrierFilter === 'Delhivery Logistics') ? 'selected' : '' ?>>Delhivery Logistics</option>
                                        <option value="DTDC Courier" <?= ($carrierFilter === 'DTDC Courier') ? 'selected' : '' ?>>DTDC Courier</option>
                                        <option value="Shiprocket" <?= ($carrierFilter === 'Shiprocket') ? 'selected' : '' ?>>Shiprocket</option>
                                        <option value="Ekart Logistics" <?= ($carrierFilter === 'Ekart Logistics') ? 'selected' : '' ?>>Ekart Logistics</option>
                                    </select>
                                </div>

                                <div>
                                    <label style="display: block; font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 4px;">Min Order Amount (₹)</label>
                                    <input type="number" step="0.01" name="min_amount" placeholder="Min ₹" value="<?= $minAmount !== null ? htmlspecialchars((string)$minAmount) : '' ?>" style="padding: 7px 10px; font-size: 13px; border: 1px solid #E5E7EB; border-radius: 6px; width: 120px;">
                                </div>

                                <div>
                                    <label style="display: block; font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 4px;">Max Order Amount (₹)</label>
                                    <input type="number" step="0.01" name="max_amount" placeholder="Max ₹" value="<?= $maxAmount !== null ? htmlspecialchars((string)$maxAmount) : '' ?>" style="padding: 7px 10px; font-size: 13px; border: 1px solid #E5E7EB; border-radius: 6px; width: 120px;">
                                </div>

                                <div style="display: flex; gap: 8px;">
                                    <button type="submit" class="btn-create-order-forest" style="padding: 7px 14px; font-size: 13px; border-radius: 6px;">Apply Filters</button>
                                    <a href="orders.php?tab=<?= urlencode($statusTab) ?>" class="btn-action btn-action-outline" style="padding: 7px 12px; font-size: 13px; text-decoration: none; border-radius: 6px;">Reset</a>
                                </div>
                            </form>
                        </div>

                        <!-- Bulk Action Bar -->
                        <div id="orderBulkBar" class="order-bulk-bar">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600; color: #374151;">
                                    <input type="checkbox" id="selectAllOrderCheckbox" class="nutronyx-checkbox" onchange="window.NutronyxOrder.toggleSelectAll(this)">
                                </label>
                                <span id="orderSelectedBadge" class="bulk-selected-pill" style="display: none;">
                                    <span id="orderSelectedCount">0</span> selected
                                </span>
                            </div>

                            <div id="orderBulkButtonsGroup" style="display: none; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <button type="button" class="btn-bulk-item" onclick="window.NutronyxOrder.bulkAction('bulk_ship')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                    <span>Ship Orders</span>
                                </button>
                                <button type="button" class="btn-bulk-item" onclick="window.NutronyxOrder.bulkAction('bulk_deliver')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    <span>Mark as Delivered</span>
                                </button>
                                <button type="button" class="btn-bulk-item" onclick="window.NutronyxOrder.bulkAction('print_invoice')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    <span>Print Invoice</span>
                                </button>
                                
                                <!-- Update Status Inline Trigger (Clean Single Arrow) -->
                                <select onchange="if(this.value){ document.getElementById('bulkStatusValueField').value = this.value; window.NutronyxOrder.bulkAction('bulk_status'); this.value = ''; }" class="btn-bulk-select" style="font-size: 12.5px; padding: 6px 10px; border-radius: 6px; border: 1px solid #E5E7EB; background: #FFFFFF; color: #374151; cursor: pointer;">
                                    <option value="">⇄ Update Status</option>
                                    <option value="PROCESSING">Mark Processing</option>
                                    <option value="SHIPPED">Mark Shipped</option>
                                    <option value="DELIVERED">Mark Delivered</option>
                                    <option value="PENDING">Mark Pending</option>
                                </select>

                                <button type="button" class="btn-bulk-item btn-bulk-cancel" onclick="window.NutronyxOrder.bulkAction('cancel')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    <span>Cancel</span>
                                </button>
                            </div>
                        </div>

                        <!-- Hidden Form for Bulk Action Submissions -->
                        <form id="orderBulkForm" method="POST" action="orders.php" style="display: none;">
                            <input type="hidden" name="action" value="bulk_action">
                            <input type="hidden" name="subaction" id="orderBulkSubactionField" value="">
                            <input type="hidden" name="selected_ids" id="orderBulkSelectedIdsField" value="">
                            <input type="hidden" name="bulk_status_value" id="bulkStatusValueField" value="">
                        </form>

                        <!-- Master Orders Table -->
                        <div class="table-responsive" style="margin-bottom: 0;">
                            <table class="dash-table" style="margin: 0; width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #F9FAFB; border-bottom: 1px solid #E5E7EB;">
                                        <th style="width: 42px; text-align: center; padding: 12px 10px;">
                                            <span style="display: none;">Select</span>
                                        </th>
                                        <th style="min-width: 100px; font-size: 12px; font-weight: 600; color: #6B7280;">Order ID</th>
                                        <th style="min-width: 170px; font-size: 12px; font-weight: 600; color: #6B7280;">Customer</th>
                                        <th style="min-width: 90px; font-size: 12px; font-weight: 600; color: #6B7280;">Amount</th>
                                        <th style="min-width: 100px; font-size: 12px; font-weight: 600; color: #6B7280;">Payment</th>
                                        <th style="min-width: 110px; font-size: 12px; font-weight: 600; color: #6B7280;">Fulfillment</th>
                                        <th style="min-width: 130px; font-size: 12px; font-weight: 600; color: #6B7280;">Order Date</th>
                                        <th style="width: 48px; text-align: center; font-size: 12px; font-weight: 600; color: #6B7280;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="8" class="table-empty-state" style="padding: 60px 20px; text-align: center;">
                                                <div class="empty-icon" style="color: #9CA3AF; margin-bottom: 12px;">
                                                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                                </div>
                                                <div class="empty-title" style="font-size: 16px; font-weight: 700; color: #111827;">No orders found</div>
                                                <p class="empty-desc" style="color: #6B7280; font-size: 13px; max-width: 400px; margin: 6px auto 0;">No order records matched your active filters. Try resetting search or changing status tabs.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $rowIndex = 0; foreach ($orders as $order): ?>
                                            <?php 
                                                $isSelected = ($initialOrder && $initialOrder['id'] === $order['id']);
                                                $isRowChecked = false; // Only checked when clicked
                                                $paymentPillClass = strtolower($order['payment_status'] ?? 'pending');
                                                $fulfillmentPillClass = strtolower($order['fulfillment_status'] ?? 'pending');
                                                $dt = new DateTime($order['created_at']);
                                                $datePart = $dt->format('M d, Y');
                                                $timePart = $dt->format('h:i A');
                                                $amountFormatted = '₹' . number_format(($order['grand_total_amount'] ?? 0) / 100);
                                                $rowIndex++;
                                            ?>
                                            <tr id="row-<?= htmlspecialchars($order['id']) ?>" 
                                                class="order-row-item order-table-row <?= $isSelected ? 'selected-order-row' : '' ?>"
                                                onclick="window.NutronyxOrder.loadOrderDetails('<?= htmlspecialchars($order['id']) ?>', event)">
                                                
                                                <!-- Checkbox Column -->
                                                <td style="text-align: center; padding: 12px 10px;" onclick="event.stopPropagation()">
                                                    <input type="checkbox" class="order-select-checkbox nutronyx-checkbox" value="<?= htmlspecialchars($order['id']) ?>" <?= $isRowChecked ? 'checked' : '' ?> onchange="window.NutronyxOrder.handleRowCheckbox()">
                                                </td>

                                                <!-- Order ID Column -->
                                                <td>
                                                    <a href="order-detail.php?id=<?= urlencode($order['id']) ?>" onclick="window.NutronyxOrder.loadOrderDetails('<?= htmlspecialchars($order['id']) ?>', event); return false;" style="font-weight: 700; color: #111827; text-decoration: none; font-size: 13.5px;">
                                                        #<?= htmlspecialchars($order['order_number']) ?>
                                                    </a>
                                                </td>

                                                <!-- Customer Column -->
                                                <td>
                                                    <div style="font-weight: 600; color: #1F2937; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;">
                                                        <?= htmlspecialchars($order['shipping_name'] ?? 'Walk-in Customer') ?>
                                                    </div>
                                                    <div style="font-size: 11.5px; color: #6B7280; margin-top: 1px;">
                                                        <?= htmlspecialchars($order['shipping_phone'] ?? 'N/A') ?>
                                                    </div>
                                                </td>

                                                <!-- Amount Column -->
                                                <td>
                                                    <span style="font-weight: 700; color: #111827; font-size: 13.5px;">
                                                        <?= $amountFormatted ?>
                                                    </span>
                                                </td>

                                                <!-- Payment Status Pill with Dot -->
                                                <td>
                                                    <span class="status-pill-dot <?= $paymentPillClass ?>">
                                                        <span class="dot"></span>
                                                        <span><?= htmlspecialchars(ucfirst(strtolower($order['payment_status'] ?? 'Pending'))) ?></span>
                                                    </span>
                                                </td>

                                                <!-- Fulfillment Status Pill with Dot -->
                                                <td>
                                                    <span class="status-pill-dot <?= $fulfillmentPillClass ?>">
                                                        <span class="dot"></span>
                                                        <span><?= htmlspecialchars(ucfirst(strtolower($order['fulfillment_status'] ?? 'Pending'))) ?></span>
                                                    </span>
                                                </td>

                                                <!-- Date Column (2 lines: date and time) -->
                                                <td>
                                                    <div style="font-size: 12.5px; font-weight: 500; color: #111827; white-space: nowrap;">
                                                        <?= htmlspecialchars($datePart) ?>
                                                    </div>
                                                    <div style="font-size: 11px; color: #6B7280; margin-top: 1px;">
                                                        <?= htmlspecialchars($timePart) ?>
                                                    </div>
                                                </td>

                                                <!-- Action Menu (••• Matching Products Page UI) -->
                                                <td style="text-align: right; padding-right: 18px;" onclick="event.stopPropagation()">
                                                    <div class="action-dropdown-wrap">
                                                        <button type="button" class="action-dots-btn" onclick="window.NutronyxOrder.toggleActionMenu('menu-<?= htmlspecialchars($order['id']) ?>', event)" title="Order Actions">
                                                            &bull;&bull;&bull;
                                                        </button>
                                                        <div id="menu-<?= htmlspecialchars($order['id']) ?>" class="action-dropdown-menu">
                                                            <a href="order-detail.php?id=<?= urlencode($order['id']) ?>" class="action-menu-item">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                                <span>Full Page View</span>
                                                            </a>
                                                            <button type="button" class="action-menu-item" onclick="window.NutronyxOrder.directPrintInvoice('<?= htmlspecialchars($order['id']) ?>')">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                                                <span>Print Invoice</span>
                                                            </button>
                                                            <a href="receipt.php?id=<?= urlencode($order['id']) ?>&download=pdf" target="_blank" class="action-menu-item">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                                                                <span>Download Receipt (PDF)</span>
                                                            </a>
                                                            <div style="height: 1px; background: #F3F4F6; margin: 4px 0;"></div>
                                                            <button type="button" class="action-menu-item" onclick="window.NutronyxOrder.openShipModal('<?= htmlspecialchars($order['id']) ?>')">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                                                <span>Ship / Add Tracking</span>
                                                            </button>
                                                            <button type="button" class="action-menu-item" onclick="window.NutronyxOrder.markDelivered('<?= htmlspecialchars($order['id']) ?>')">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                                                <span>Mark Delivered</span>
                                                            </button>
                                                            <button type="button" class="action-menu-item" onclick="window.NutronyxOrder.openRefundModal('<?= htmlspecialchars($order['id']) ?>', <?= (float)($order['grand_total_amount']/100) ?>)">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                                                <span>Process Refund</span>
                                                            </button>
                                                            <button type="button" class="action-menu-item" onclick="window.NutronyxOrder.openReturnModal('<?= htmlspecialchars($order['id']) ?>')">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                                                                <span>Create Return</span>
                                                            </button>
                                                            <div style="height: 1px; background: #F3F4F6; margin: 4px 0;"></div>
                                                            <form method="POST" action="orders.php" style="margin: 0;" onsubmit="return confirm('Cancel this order?');">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['id']) ?>">
                                                                <input type="hidden" name="status" value="CANCELLED">
                                                                <button type="submit" class="action-menu-item danger">
                                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                                                                    <span>Cancel Order</span>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Footer Pagination -->
                        <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 16px; margin-top: 8px; border-top: 1px solid #E5E7EB; background: #FFFFFF; flex-wrap: wrap; gap: 12px;">
                            <div style="font-size: 13px; color: #6B7280;">
                                <?php 
                                    $from = $totalOrders > 0 ? (($page - 1) * $perPage + 1) : 0;
                                    $to = min($totalOrders, $page * $perPage);
                                ?>
                                <?php if ($totalOrders > 0): ?>
                                    Showing <strong style="color: #111827;"><?= $from ?>–<?= $to ?></strong> of <strong style="color: #111827;"><?= number_format($totalOrders) ?></strong> orders
                                <?php else: ?>
                                    Showing <strong style="color: #111827;">0</strong> orders
                                <?php endif; ?>
                            </div>

                            <div style="display: flex; align-items: center; gap: 16px;">
                                <!-- Rows Per Page Selector -->
                                <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #6B7280;">
                                    <span>Rows per page:</span>
                                    <select onchange="window.location.href = 'orders.php?tab=<?= urlencode($statusTab) ?>&page=1&per_page=' + this.value" class="filter-select" style="padding: 4px 8px; font-size: 12.5px;">
                                        <option value="10" <?= ($perPage == 10) ? 'selected' : '' ?>>10</option>
                                        <option value="25" <?= ($perPage == 25) ? 'selected' : '' ?>>25</option>
                                        <option value="50" <?= ($perPage == 50) ? 'selected' : '' ?>>50</option>
                                    </select>
                                </div>

                                <!-- Page Navigation Buttons -->
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <?php if ($page > 1): ?>
                                        <a href="orders.php?tab=<?= urlencode($statusTab) ?>&page=<?= $page - 1 ?>&per_page=<?= $perPage ?>" class="page-num-btn" title="Previous">&lsaquo;</a>
                                    <?php else: ?>
                                        <span class="page-num-btn" style="opacity: 0.4; pointer-events: none;">&lsaquo;</span>
                                    <?php endif; ?>

                                    <?php if ($totalPages <= 1): ?>
                                        <span class="page-num-btn active">1</span>
                                    <?php else: ?>
                                        <?php for ($p = 1; $p <= min(3, $totalPages); $p++): ?>
                                            <a href="orders.php?tab=<?= urlencode($statusTab) ?>&page=<?= $p ?>&per_page=<?= $perPage ?>" class="page-num-btn <?= ($page === $p) ? 'active' : '' ?>"><?= $p ?></a>
                                        <?php endfor; ?>
                                        <?php if ($totalPages > 4): ?>
                                            <span style="font-size: 12.5px; color: #9CA3AF; padding: 0 2px;">...</span>
                                            <a href="orders.php?tab=<?= urlencode($statusTab) ?>&page=<?= $totalPages ?>&per_page=<?= $perPage ?>" class="page-num-btn <?= ($page === $totalPages) ? 'active' : '' ?>"><?= $totalPages ?></a>
                                        <?php elseif ($totalPages === 4): ?>
                                            <a href="orders.php?tab=<?= urlencode($statusTab) ?>&page=4&per_page=<?= $perPage ?>" class="page-num-btn <?= ($page === 4) ? 'active' : '' ?>">4</a>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <a href="orders.php?tab=<?= urlencode($statusTab) ?>&page=<?= $page + 1 ?>&per_page=<?= $perPage ?>" class="page-num-btn" title="Next">&rsaquo;</a>
                                    <?php else: ?>
                                        <span class="page-num-btn" style="opacity: 0.4; pointer-events: none;">&rsaquo;</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Right Column: Interactive Order Details Workspace (Pre-rendered or dynamically loaded) -->
                <div class="orders-detail-column" id="orderDetailWorkspace" style="<?= $initialOrder ? 'display: flex;' : 'display: none;' ?>">
                    <?php if ($initialOrder): ?>
                        <?php 
                            $o = $initialOrder;
                            $orderDateStr = (new DateTime($o['created_at']))->format('M d, Y \a\t h:i A');
                            $subtotalRupees = number_format(($o['subtotal_amount'] ?? 0) / 100);
                            $shippingRupees = number_format(($o['shipping_amount'] ?? 0) / 100);
                            $discountRupees = number_format(($o['discount_amount'] ?? 0) / 100);
                            $grandTotalRupees = number_format(($o['grand_total_amount'] ?? 0) / 100);
                            $fullAddress = htmlspecialchars(trim(
                                ($o['shipping_address_line1'] ?? '') . ' ' .
                                ($o['shipping_address_line2'] ?? '') . ', ' .
                                ($o['shipping_city'] ?? '') . ', ' .
                                ($o['shipping_state'] ?? '') . ' - ' .
                                ($o['shipping_postal_code'] ?? '')
                            ));
                            $fullAddressJs = addslashes($fullAddress);
                            $shipment = !empty($o['shipments']) ? $o['shipments'][0] : null;
                        ?>
                        <!-- Header with Order Number, Date, Status Pill, Close -->
                        <div class="order-workspace-header">
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <h2 class="order-heading-title">
                                        #<?= htmlspecialchars($o['order_number']) ?>
                                    </h2>
                                    <button type="button" class="star-toggle-btn" title="Copy Order Number" onclick="window.NutronyxOrder.copyText('<?= htmlspecialchars($o['order_number']) ?>', 'Order Number')" style="padding: 2px;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    </button>
                                </div>
                                <div style="font-size: 12px; color: #6B7280; margin-top: 3px;">
                                    Placed on <?= htmlspecialchars($orderDateStr) ?>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="status-pill-dot <?= strtolower($o['fulfillment_status'] ?? 'pending') ?>">
                                    <span class="dot"></span>
                                    <span><?= htmlspecialchars(ucfirst(strtolower($o['fulfillment_status'] ?? 'Pending'))) ?></span>
                                </span>
                                <button type="button" onclick="window.NutronyxOrder.closeDetails()" style="border: none; background: transparent; cursor: pointer; color: #9CA3AF; padding: 4px; font-size: 18px; line-height: 1;">&times;</button>
                            </div>
                        </div>

                        <!-- Full-Width Summary Tiles Banner: Payment, Fulfillment, Order Type -->
                        <div class="order-summary-tiles-strip">
                            <!-- Payment Tile -->
                            <div class="summary-tile-box">
                                <div class="tile-header">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                    <span>Payment</span>
                                </div>
                                <div class="tile-status-pill <?= strtolower($o['payment_status'] ?? 'paid') ?>">
                                    <span class="dot"></span>
                                    <span><?= htmlspecialchars(strtoupper($o['payment_status'] ?? 'PAID')) ?></span>
                                </div>
                                <div class="tile-subtext" title="<?= htmlspecialchars($o['payment_method'] ?? 'Online') ?>">
                                    <?= htmlspecialchars($o['payment_method'] ?? 'Online (Razorpay UPI)') ?>
                                </div>
                            </div>

                            <!-- Fulfillment Tile -->
                            <div class="summary-tile-box">
                                <div class="tile-header">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                    <span>Fulfillment</span>
                                </div>
                                <div class="tile-status-pill <?= strtolower($o['fulfillment_status'] ?? 'delivered') ?>">
                                    <span class="dot"></span>
                                    <span><?= htmlspecialchars(strtoupper($o['fulfillment_status'] ?? 'DELIVERED')) ?></span>
                                </div>
                                <div class="tile-subtext" title="<?= htmlspecialchars($shipment['carrier_name'] ?? $shipment['carrier'] ?? 'Delhivery Logistics') ?>">
                                    <?= htmlspecialchars($shipment['carrier_name'] ?? $shipment['carrier'] ?? 'Delhivery Logistics') ?>
                                </div>
                            </div>

                            <!-- Order Type Tile -->
                            <div class="summary-tile-box">
                                <div class="tile-header">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                                    <span>Order Type</span>
                                </div>
                                <div class="tile-status-pill online">
                                    <span class="dot"></span>
                                    <span>ONLINE</span>
                                </div>
                                <div class="tile-subtext" title="Direct Storefront">
                                    Direct Storefront
                                </div>
                            </div>
                        </div>

                        <!-- 2-Column Body Layout: Left Sub-Column (Customer, Items, Breakdown) & Right Sub-Column (Action Buttons) -->
                        <div style="display: grid; grid-template-columns: 1fr 140px; gap: 16px; margin-bottom: 20px;">
                            
                            <!-- Left Sub-Column: Customer, Address, Items, Totals -->
                            <div style="display: flex; flex-direction: column; gap: 14px;">

                                <!-- Customer Details Card -->
                                <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 10px 12px;">
                                    <div style="font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; display: flex; align-items: center; gap: 4px;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                        <span>Customer</span>
                                    </div>
                                    <div style="font-weight: 700; font-size: 13px; color: #111827;"><?= htmlspecialchars($o['shipping_name'] ?? 'Walk-in Customer') ?></div>
                                    <div style="font-size: 12px; color: #4B5563; margin-top: 2px;">
                                        📞 <?= htmlspecialchars($o['shipping_phone'] ?? 'N/A') ?>
                                    </div>
                                    <?php if (!empty($o['customer_email'])): ?>
                                        <div style="font-size: 11.5px; color: #6B7280; margin-top: 2px;">
                                            ✉ <?= htmlspecialchars($o['customer_email']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Shipping Address Card -->
                                <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 10px 12px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                        <div style="font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase; letter-spacing: 0.04em; display: flex; align-items: center; gap: 4px;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                            <span>Shipping Address</span>
                                        </div>
                                        <button type="button" class="star-toggle-btn" title="Copy Address" onclick="window.NutronyxOrder.copyText('<?= $fullAddressJs ?>', 'Address')" style="padding: 2px;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        </button>
                                    </div>
                                    <div style="font-size: 12px; color: #374151; line-height: 1.45;">
                                        <?= $fullAddress ?>
                                    </div>
                                    <div style="margin-top: 4px;">
                                        <a href="javascript:void(0)" onclick="window.NutronyxOrder.openAddressModal('<?= htmlspecialchars($o['id']) ?>')" style="font-size: 11.5px; color: #144D29; text-decoration: underline; font-weight: 600;">Edit Address</a>
                                    </div>
                                </div>

                                <!-- Items in Order (Thumbnail, Name, SKU, Price x Qty, Total) -->
                                <div>
                                    <div style="font-size: 11.5px; font-weight: 700; color: #4B5563; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px; display: flex; align-items: center; gap: 4px;">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                        <span>Order Items (<?= count($o['items'] ?? []) ?>)</span>
                                    </div>
                                    <div style="border-radius: 8px; border: 1px solid #E5E7EB; background: #FFF; padding: 2px 10px;">
                                        <?php if (empty($o['items'])): ?>
                                            <div style="color: #9CA3AF; padding: 10px 0; font-size: 12px;">No items record found.</div>
                                        <?php else: ?>
                                            <?php foreach ($o['items'] as $it): ?>
                                                <?php 
                                                    $itPrice = number_format(($it['unit_price_amount'] ?? 0) / 100);
                                                    $itTotal = number_format(($it['total_amount'] ?? 0) / 100);
                                                    $imgUrl = !empty($it['product_image_url']) ? (str_starts_with($it['product_image_url'], 'http') || str_starts_with($it['product_image_url'], '/') ? $it['product_image_url'] : '../' . $it['product_image_url']) : '../assets/images/about-commitment-still-life.jpg';
                                                ?>
                                                <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #F3F4F6;">
                                                    <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                                                        <div style="width: 40px; height: 40px; border-radius: 6px; border: 1px solid #E5E7EB; overflow: hidden; background: #FFF; flex-shrink: 0;">
                                                            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($it['title']) ?>" style="width: 100%; height: 100%; object-fit: contain;">
                                                        </div>
                                                        <div style="min-width: 0;">
                                                            <div style="font-weight: 600; font-size: 12.5px; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;">
                                                                <?= htmlspecialchars($it['title']) ?>
                                                            </div>
                                                            <div style="font-size: 11px; color: #6B7280; font-family: monospace;">SKU: <?= htmlspecialchars($it['sku'] ?? 'N/A') ?></div>
                                                        </div>
                                                    </div>
                                                    <div style="text-align: right; flex-shrink: 0; padding-left: 8px;">
                                                        <div style="font-size: 11.5px; color: #4B5563;">₹<?= $itPrice ?> &times; <?= (int)$it['quantity'] ?></div>
                                                        <div style="font-size: 12.5px; font-weight: 700; color: #111827;">₹<?= $itTotal ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Financial Breakdown & Total Paid Green Banner -->
                                <div>
                                    <div style="font-size: 12.5px; color: #4B5563; display: flex; flex-direction: column; gap: 4px;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Subtotal</span>
                                            <span style="font-weight: 600; color: #111827;">₹<?= $subtotalRupees ?></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Shipping</span>
                                            <span style="font-weight: 600; color: #111827;"><?= ((int)$o['shipping_amount'] === 0) ? 'Free' : ('₹' . $shippingRupees) ?></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Discount</span>
                                            <span style="color: #6B7280;">- ₹<?= $discountRupees ?></span>
                                        </div>
                                    </div>

                                    <!-- Total Paid Full Width Banner -->
                                    <div class="total-paid-banner">
                                        <span class="banner-label">Total Paid</span>
                                        <span class="banner-amount">₹<?= $grandTotalRupees ?></span>
                                    </div>
                                </div>

                            </div>

                            <!-- Right Sub-Column: Vertical Stack of 7 Action Buttons -->
                            <div class="order-action-vertical-stack">
                                <button type="button" class="btn-order-action-card" onclick="window.NutronyxOrder.directPrintInvoice('<?= htmlspecialchars($o['id']) ?>')">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    <span>Print Invoice</span>
                                </button>
                                <a href="receipt.php?id=<?= urlencode($o['id']) ?>&download=pdf" download="Nutronyx-Receipt-<?= urlencode($o['order_number'] ?? $o['id']) ?>.pdf" target="_blank" class="btn-order-action-card">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    <span>Download Receipt</span>
                                </a>
                                <button type="button" class="btn-order-action-card" onclick="window.NutronyxOrder.openShipModal('<?= htmlspecialchars($o['id']) ?>', '<?= htmlspecialchars($shipment['carrier_name'] ?? '') ?>', '<?= htmlspecialchars($shipment['tracking_number'] ?? '') ?>')">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                    <span>Track Shipment</span>
                                </button>
                                <button type="button" class="btn-order-action-card" onclick="window.NutronyxOrder.openRefundModal('<?= htmlspecialchars($o['id']) ?>', <?= (float)(($o['refundable_balance'] ?? $o['grand_total_amount'])/100) ?>)">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                    <span>Process Refund</span>
                                </button>
                                <button type="button" class="btn-order-action-card" onclick="window.NutronyxOrder.openReturnModal('<?= htmlspecialchars($o['id']) ?>')">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
                                    <span>Create Return</span>
                                </button>
                                <button type="button" class="btn-order-action-card" onclick="window.NutronyxOrder.resendConfirmation('<?= htmlspecialchars($o['id']) ?>')">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                    <span>Resend Confirm</span>
                                </button>
                                <button type="button" class="btn-order-action-card" onclick="window.NutronyxOrder.openNotesModal('<?= htmlspecialchars($o['id']) ?>', '<?= addslashes(htmlspecialchars($o['internal_notes'] ?? '')) ?>')">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                    <span>Order Notes</span>
                                </button>
                            </div>

                        </div>

                        <!-- Full-Width Bottom Section: Tabs & Interactive Timeline (Spanning across beneath both columns) -->
                        <div style="border-top: 1px solid #E5E7EB; padding-top: 16px;">
                            <div style="display: flex; gap: 16px; border-bottom: 1px solid #E5E7EB; margin-bottom: 14px;">
                                <button type="button" id="tabBtnActivity" class="order-tab-btn active" style="padding-bottom: 8px; font-size: 13px;" onclick="window.NutronyxOrder.switchDetailTab('activity')">Order Activity</button>
                                <button type="button" id="tabBtnTracking" class="order-tab-btn" style="padding-bottom: 8px; font-size: 13px;" onclick="window.NutronyxOrder.switchDetailTab('tracking')">Shipment Tracking</button>
                            </div>

                            <div id="detailTabActivity">
                                <div class="order-timeline-v2">
                                    <?php if (empty($o['timeline'])): ?>
                                        <div style="color: #9CA3AF; font-size: 12px; padding: 8px 0;">No events logged yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($o['timeline'] as $tl): ?>
                                            <?php 
                                                $tlDate = (new DateTime($tl['created_at']))->format('M d, Y, h:i A');
                                            ?>
                                            <div class="timeline-v2-item">
                                                <span class="node-dot"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                                    <div>
                                                        <div style="font-weight: 700; font-size: 13px; color: #111827;"><?= htmlspecialchars(str_replace('_', ' ', $tl['event_type'])) ?></div>
                                                        <div style="font-size: 12px; color: #4B5563; margin-top: 2px;"><?= htmlspecialchars($tl['message']) ?></div>
                                                    </div>
                                                    <span style="font-size: 11px; color: #9CA3AF; white-space: nowrap;"><?= htmlspecialchars($tlDate) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div id="detailTabTracking" style="display: none;">
                                <?php if ($shipment): ?>
                                    <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 14px;">
                                        <div style="font-weight: 700; font-size: 13.5px; color: #111827;"><?= htmlspecialchars($shipment['carrier_name'] ?? $shipment['carrier'] ?? 'Delhivery') ?></div>
                                        <div style="font-size: 12.5px; color: #4B5563; margin-top: 4px; font-family: monospace;">
                                            Tracking #: <strong><?= htmlspecialchars($shipment['tracking_number'] ?? 'DL1234567890') ?></strong>
                                        </div>
                                        <div style="margin-top: 10px;">
                                            <a href="<?= htmlspecialchars($shipment['tracking_url'] ?: ('https://www.delhivery.com/track/package/' . urlencode($shipment['tracking_number'] ?? ''))) ?>" target="_blank" class="btn-action btn-action-sm btn-action-outline" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                                <span>Live Tracking Link &rarr;</span>
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="background: #F9FAFB; border: 1px dashed #D1D5DB; border-radius: 8px; padding: 20px; text-align: center;">
                                        <div style="font-size: 13px; color: #6B7280; margin-bottom: 10px;">No shipment dispatched yet.</div>
                                        <button type="button" class="btn-create-order-forest" style="font-size: 12px; padding: 6px 12px;" onclick="window.NutronyxOrder.openShipModal('<?= htmlspecialchars($o['id']) ?>')">
                                            Create Shipment
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div style="text-align: center; padding: 60px 20px; color: #9CA3AF;">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <div style="margin-top: 10px; font-size: 14px; font-weight: 600; color: #4B5563;">Select an order from the list</div>
                            <p style="font-size: 12.5px; margin-top: 4px;">Click any order row on the left to preview details and timeline.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </main>
    </div>

    <!-- ========================================================================= -->
    <!-- MODALS: CREATE ORDER, SHIP ORDER, REFUND, RETURN, NOTES, ADDRESS, EXPORT, IMPORT -->
    <!-- ========================================================================= -->

    <!-- 1. CREATE ORDER MODAL -->
    <div id="createOrderModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 680px;">
            <div class="modal-header">
                <h3 class="modal-title">Create Manual Customer Order</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('createOrderModal')">&times;</button>
            </div>
            <form method="POST" action="orders.php">
                <input type="hidden" name="action" value="create_order">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div>
                            <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Customer Full Name *</label>
                            <input type="text" name="customer_name" class="form-control" required placeholder="e.g. Rajesh Kumar" style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                        </div>
                        <div>
                            <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Phone Number *</label>
                            <input type="text" name="customer_phone" class="form-control" required placeholder="e.g. +91 98765 43210" style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                        </div>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Email Address</label>
                        <input type="email" name="customer_email" class="form-control" placeholder="customer@example.com" style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Shipping Address Line 1 *</label>
                        <input type="text" name="shipping_address_line1" class="form-control" required placeholder="Flat / House #, Street Name" style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                        <div>
                            <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">City</label>
                            <input type="text" name="shipping_city" value="Hyderabad" class="form-control" style="width: 100%; padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                        </div>
                        <div>
                            <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">State</label>
                            <input type="text" name="shipping_state" value="Telangana" class="form-control" style="width: 100%; padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                        </div>
                        <div>
                            <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">PIN Code</label>
                            <input type="text" name="shipping_postal_code" value="500081" class="form-control" style="width: 100%; padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                        </div>
                    </div>

                    <div style="border-top: 1px solid #E5E7EB; padding-top: 14px; margin-bottom: 14px;">
                        <label class="form-label" style="display: block; font-size: 13px; font-weight: 700; color: #111827; margin-bottom: 8px;">Order Line Item</label>
                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 10px;">
                            <div>
                                <label style="display: block; font-size: 11.5px; color: #6B7280; margin-bottom: 3px;">Product Variant</label>
                                <select name="items[0][variant_id]" class="filter-select" style="width: 100%;" required id="manualOrderVariantSelect" onchange="updateManualOrderPrice(this)">
                                    <?php foreach ($catalogVariants as $cv): ?>
                                        <option value="<?= htmlspecialchars($cv['variant_id']) ?>" data-price="<?= (float)($cv['price_amount'] / 100) ?>">
                                            <?= htmlspecialchars($cv['product_title']) ?> - <?= htmlspecialchars($cv['variant_title']) ?> (₹<?= number_format($cv['price_amount'] / 100) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-size: 11.5px; color: #6B7280; margin-bottom: 3px;">Quantity</label>
                                <input type="number" name="items[0][quantity]" value="1" min="1" class="form-control" style="width: 100%; padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 11.5px; color: #6B7280; margin-bottom: 3px;">Unit Price (₹)</label>
                                <input type="number" step="0.01" name="items[0][unit_price]" id="manualOrderUnitPrice" value="<?= !empty($catalogVariants) ? number_format($catalogVariants[0]['price_amount']/100, 2, '.', '') : '449.00' ?>" class="form-control" style="width: 100%; padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div>
                            <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Payment Method</label>
                            <select name="payment_method" class="filter-select" style="width: 100%;">
                                <option value="COD">Cash on Delivery (COD)</option>
                                <option value="CARD">Credit / Debit Card (Online)</option>
                                <option value="UPI">UPI Direct</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Shipping Fee (₹)</label>
                            <input type="number" step="0.01" name="shipping_amount" value="0.00" class="form-control" style="width: 100%; padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxModal.close('createOrderModal')">Cancel</button>
                    <button type="submit" class="btn-emerald-brand" style="padding: 8px 18px; border: none; background: #10B981; color: #FFF; border-radius: 6px; font-weight: 600; cursor: pointer;">Place Order</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. SHIP ORDER / TRACKING MODAL -->
    <div id="shipOrderModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title">Create Shipment &amp; Add Tracking</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('shipOrderModal')">&times;</button>
            </div>
            <form method="POST" action="orders.php">
                <input type="hidden" name="action" value="ship_order">
                <input type="hidden" name="order_id" id="shipOrderIdField" value="">
                <div class="modal-body">
                    <div style="margin-bottom: 14px;">
                        <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Carrier Name *</label>
                        <select name="carrier" id="shipCarrierInput" class="filter-select" style="width: 100%;" required>
                            <option value="BlueDart Express">BlueDart Express</option>
                            <option value="Delhivery Logistics">Delhivery Logistics</option>
                            <option value="DTDC Courier">DTDC Courier</option>
                            <option value="Shiprocket">Shiprocket</option>
                            <option value="Ekart Logistics">Ekart Logistics</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 14px;">
                        <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Tracking Number / AWB *</label>
                        <input type="text" name="tracking_number" id="shipTrackingInput" class="form-control" required placeholder="e.g. BD-89234821" style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                    </div>
                    <div style="margin-bottom: 14px;">
                        <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Tracking URL (Optional)</label>
                        <input type="url" name="tracking_url" class="form-control" placeholder="https://..." style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Dispatch Notes</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Packed in eco box, fragile tag applied..." style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxModal.close('shipOrderModal')">Cancel</button>
                    <button type="submit" class="btn-emerald-brand" style="padding: 8px 18px; border: none; background: #10B981; color: #FFF; border-radius: 6px; font-weight: 600; cursor: pointer;">Save Shipment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. REFUND ORDER MODAL -->
    <div id="refundOrderModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header">
                <h3 class="modal-title">Process Financial Refund</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('refundOrderModal')">&times;</button>
            </div>
            <form method="POST" action="orders.php">
                <input type="hidden" name="action" value="refund_order">
                <input type="hidden" name="order_id" id="refundOrderIdField" value="">
                <div class="modal-body">
                    <div id="refundMaxBalanceNotice" style="font-size: 12.5px; color: #047857; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 8px 12px; border-radius: 6px; margin-bottom: 12px;"></div>
                    <div style="margin-bottom: 14px;">
                        <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Refund Amount (₹) *</label>
                        <input type="number" step="0.01" name="refund_amount_rupees" id="refundAmountInput" class="form-control" required style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 14px; font-weight: 700;">
                    </div>
                    <div style="margin-bottom: 14px;">
                        <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Reason for Refund *</label>
                        <select name="refund_reason" class="filter-select" style="width: 100%;" required>
                            <option value="Customer Return Request">Customer Return Request</option>
                            <option value="Damaged Item in Transit">Damaged Item in Transit</option>
                            <option value="Out of Stock / Backorder">Out of Stock / Backorder</option>
                            <option value="Order Cancelled Before Shipping">Order Cancelled Before Shipping</option>
                            <option value="Goodwill Customer Concession">Goodwill Customer Concession</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Internal Audit Notes</label>
                        <textarea name="refund_notes" rows="2" class="form-control" placeholder="Approved by manager..." style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxModal.close('refundOrderModal')">Cancel</button>
                    <button type="submit" class="btn-emerald-brand" style="padding: 8px 18px; border: none; background: #DC2626; color: #FFF; border-radius: 6px; font-weight: 600; cursor: pointer;">Execute Refund</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. RETURN ORDER MODAL -->
    <div id="returnOrderModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header">
                <h3 class="modal-title">Initiate Return &amp; Restock</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('returnOrderModal')">&times;</button>
            </div>
            <form method="POST" action="orders.php">
                <input type="hidden" name="action" value="return_order">
                <input type="hidden" name="order_id" id="returnOrderIdField" value="">
                <div class="modal-body">
                    <div style="margin-bottom: 14px;">
                        <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Return Reason *</label>
                        <select name="return_reason" class="filter-select" style="width: 100%;" required>
                            <option value="Damaged Seal / Leaked">Damaged Seal / Leaked</option>
                            <option value="Defective / Quality Issue">Defective / Quality Issue</option>
                            <option value="Wrong Item Delivered">Wrong Item Delivered</option>
                            <option value="Customer Changed Mind">Customer Changed Mind</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 14px;">
                        <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Resolution Type</label>
                        <select name="resolution" class="filter-select" style="width: 100%;">
                            <option value="REFUND">Refund via Original Payment</option>
                            <option value="REPLACEMENT">Send Replacement Item</option>
                            <option value="STORE_CREDIT">Issue Store Credit</option>
                        </select>
                    </div>
                    <div style="background: #F9FAFB; padding: 10px 14px; border-radius: 6px; border: 1px solid #E5E7EB;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #374151; cursor: pointer;">
                            <input type="checkbox" name="restock_items" value="1" checked style="width: 16px; height: 16px; accent-color: #10B981;">
                            <span>Return items into available inventory stock</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxModal.close('returnOrderModal')">Cancel</button>
                    <button type="submit" class="btn-emerald-brand" style="padding: 8px 18px; border: none; background: #10B981; color: #FFF; border-radius: 6px; font-weight: 600; cursor: pointer;">Submit Return</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. ORDER NOTES MODAL -->
    <div id="orderNotesModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header">
                <h3 class="modal-title">Staff Internal Notes</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('orderNotesModal')">&times;</button>
            </div>
            <form method="POST" action="orders.php">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="order_id" id="noteOrderIdField" value="">
                <div class="modal-body">
                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 11.5px; font-weight: 700; color: #6B7280; text-transform: uppercase;">Existing Notes</label>
                        <div id="existingNotesDisplay" style="margin-top: 4px; padding: 10px 12px; background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 6px; font-size: 12.5px; color: #374151; max-height: 120px; overflow-y: auto; white-space: pre-wrap;"></div>
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 4px;">Append New Note *</label>
                        <textarea name="note_text" id="newNoteInput" rows="3" class="form-control" required placeholder="Type operational note here..." style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxModal.close('orderNotesModal')">Cancel</button>
                    <button type="submit" class="btn-emerald-brand" style="padding: 8px 18px; border: none; background: #10B981; color: #FFF; border-radius: 6px; font-weight: 600; cursor: pointer;">Save Note</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 6. ORDER ADDRESS EDIT MODAL -->
    <div id="orderAddressModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 520px;">
            <div class="modal-header">
                <h3 class="modal-title">Edit Shipping Address</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('orderAddressModal')">&times;</button>
            </div>
            <form method="POST" action="orders.php">
                <input type="hidden" name="action" value="update_address">
                <input type="hidden" name="order_id" id="addrOrderIdField" value="">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                        <div>
                            <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">Recipient Name *</label>
                            <input type="text" name="shipping_name" id="addrNameInput" class="form-control" required style="width: 100%; padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                        </div>
                        <div>
                            <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">Phone Number *</label>
                            <input type="text" name="shipping_phone" id="addrPhoneInput" class="form-control" required style="width: 100%; padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                        </div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">Address Line 1 *</label>
                        <input type="text" name="shipping_address_line1" id="addrLine1Input" class="form-control" required style="width: 100%; padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">Address Line 2 (Optional)</label>
                        <input type="text" name="shipping_address_line2" id="addrLine2Input" class="form-control" style="width: 100%; padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                        <div>
                            <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">City</label>
                            <input type="text" name="shipping_city" id="addrCityInput" class="form-control" required style="width: 100%; padding: 7px 8px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                        </div>
                        <div>
                            <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">State</label>
                            <input type="text" name="shipping_state" id="addrStateInput" class="form-control" required style="width: 100%; padding: 7px 8px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                        </div>
                        <div>
                            <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">PIN Code</label>
                            <input type="text" name="shipping_postal_code" id="addrPincodeInput" class="form-control" required style="width: 100%; padding: 7px 8px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxModal.close('orderAddressModal')">Cancel</button>
                    <button type="submit" class="btn-emerald-brand" style="padding: 8px 18px; border: none; background: #10B981; color: #FFF; border-radius: 6px; font-weight: 600; cursor: pointer;">Update Address</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 7. EXPORT MODAL -->
    <div id="exportOrderModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 440px;">
            <div class="modal-header">
                <h3 class="modal-title">Export Orders &amp; Ledger</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('exportOrderModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size: 13px; color: #4B5563; margin-bottom: 14px;">Select the export scope for generating the downloadable CSV report:</p>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="export.php?type=orders" class="btn-action btn-action-outline" style="padding: 10px 14px; text-decoration: none; display: flex; justify-content: space-between; align-items: center; border-radius: 6px;">
                        <span>Export All Filtered Orders (<?= number_format($totalOrders) ?>)</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                    <button type="button" id="btnExportOrderSelected" class="btn-action btn-action-outline" onclick="window.NutronyxOrder.exportSelected()" style="display: none; padding: 10px 14px; justify-content: space-between; align-items: center; border-radius: 6px; background: #ECFDF5; border-color: #A7F3D0; color: #047857;">
                        <span id="btnExportOrderSelectedText">Export Selected Checkboxes Only</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                </div>
            </div>
            <div class="modal-footer" style="padding: 12px 20px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end;">
                <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxModal.close('exportOrderModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- 8. IMPORT MODAL -->
    <div id="importOrderModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header">
                <h3 class="modal-title">Import Orders from CSV</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('importOrderModal')">&times;</button>
            </div>
            <form method="POST" action="orders.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_orders">
                <div class="modal-body">
                    <p style="font-size: 13px; color: #4B5563; margin-bottom: 12px;">Upload a CSV file containing order records (Columns: <code>Customer Name, Phone, Amount, Payment Method</code>).</p>
                    <div style="border: 2px dashed #D1D5DB; border-radius: 8px; padding: 24px; text-align: center; background: #F9FAFB;">
                        <input type="file" name="csv_file" accept=".csv" required style="font-size: 13px;">
                    </div>
                </div>
                <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxModal.close('importOrderModal')">Cancel</button>
                    <button type="submit" class="btn-emerald-brand" style="padding: 8px 18px; border: none; background: #10B981; color: #FFF; border-radius: 6px; font-weight: 600; cursor: pointer;">Upload &amp; Import</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
    function updateManualOrderPrice(selectEl) {
        const opt = selectEl.options[selectEl.selectedIndex];
        const price = opt.getAttribute('data-price');
        const priceInput = document.getElementById('manualOrderUnitPrice');
        if (price && priceInput) {
            priceInput.value = parseFloat(price).toFixed(2);
        }
    }

    // Close any action menu if clicked outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.action-dots-btn') && !e.target.closest('.action-dropdown-menu')) {
            document.querySelectorAll('.action-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        }
    });
    </script>
</body>
</html>
