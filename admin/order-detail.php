<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\OrderService;
use Nutronyx\Services\PaymentService;
use Nutronyx\SDK\ExtensionRegistry;

AuthService::requireAuth();

$activePage = 'orders';
$activeWebsite = Context::getWebsite();
$orderId = $_GET['id'] ?? '';
$order = OrderService::getOrder($orderId);

if (!$order) {
    header('Location: orders.php');
    exit;
}

$notice = '';
$error = '';

$db = \Nutronyx\Database::getConnection();
$refStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE order_id = :oid AND transaction_type = 'REFUND' AND status = 'SUCCESS'");
$refStmt->execute([':oid' => $orderId]);
$totalRefundedPaise = (int)$refStmt->fetchColumn();
$refundablePaise = max(0, (int)$order['grand_total_amount'] - $totalRefundedPaise);
$canRefund = ($order['payment_status'] !== 'REFUNDED' && $refundablePaise > 0 && $order['order_status'] !== 'CANCELLED');

// Handle Address Update (Controlled Editing with Audit Log)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_address') {
    try {
        OrderService::updateShippingAddress($orderId, [
            'shipping_name' => trim($_POST['shipping_name']),
            'shipping_phone' => trim($_POST['shipping_phone']),
            'shipping_address_line1' => trim($_POST['shipping_address_line1']),
            'shipping_address_line2' => trim($_POST['shipping_address_line2']),
            'shipping_city' => trim($_POST['shipping_city']),
            'shipping_state' => trim($_POST['shipping_state']),
            'shipping_postal_code' => trim($_POST['shipping_postal_code'])
        ]);
        $notice = 'Shipping address updated successfully and recorded in the immutable audit timeline.';
        $order = OrderService::getOrder($orderId);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

// Handle Add Internal Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    $noteText = trim($_POST['note_text'] ?? '');
    if ($noteText) {
        OrderService::addInternalNote($orderId, $noteText);
        $notice = 'Internal staff note recorded.';
        $order = OrderService::getOrder($orderId);
    }
}

// Handle Direct Ekart Dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ship_ekart_auto') {
    try {
        $res = OrderService::fulfillWithEkart($orderId, null);
        $notice = "Order booked directly via Ekart Logistics API! Official AWB: " . htmlspecialchars($res['tracking_number']);
        $order = OrderService::getOrder($orderId);
    } catch (\Throwable $e) {
        $error = "Ekart Logistics Error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ship_ekart') {
    try {
        $trackingNumber = trim($_POST['tracking_number'] ?? '');
        if (empty($trackingNumber)) {
            throw new \InvalidArgumentException("Tracking AWB is required. Please enter the authentic AWB from your Ekart Merchant Portal or use Auto-Book.");
        }
        $res = OrderService::fulfillWithEkart($orderId, $trackingNumber);
        $notice = "Order dispatched with Ekart Logistics! Real AWB: " . htmlspecialchars($res['tracking_number']);
        $order = OrderService::getOrder($orderId);
    } catch (\Throwable $e) {
        $error = "Ekart Logistics Error: " . $e->getMessage();
    }
}

// Handle Fulfillment Dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fulfill_order') {
    $carrier = trim($_POST['carrier'] ?: 'Ekart Logistics');
    $tracking = trim($_POST['tracking_number'] ?? '');
    try {
        if (empty($tracking)) {
            throw new \InvalidArgumentException("Tracking AWB number is required to confirm dispatch.");
        }
        if (str_contains(strtolower($carrier), 'ekart')) {
            $res = OrderService::fulfillWithEkart($orderId, $tracking);
            $notice = "Order dispatched with Ekart Logistics! Tracking AWB: " . htmlspecialchars($res['tracking_number']);
        } else {
            $res = OrderService::markFulfilled($orderId, $carrier, $tracking);
            $notice = "Order fulfilled via {$carrier} with tracking #{$res['tracking_number']}.";
        }
        $order = OrderService::getOrder($orderId);
    } catch (\Throwable $e) {
        $error = "Dispatch Error: " . $e->getMessage();
    }
}

// Handle Order Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    OrderService::transitionStatus($orderId, 'CANCELLED');
    $notice = 'Order status transitioned to CANCELLED and reserved inventory was released.';
    $order = OrderService::getOrder($orderId);
}

// Handle Refund Process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_refund') {
    $amountPaise = (int)(round((float)($_POST['refund_amount_rupees'] ?? 0) * 100));
    $reason = trim($_POST['refund_reason'] ?: 'Customer requested return');
    
    if ($amountPaise <= 0) {
        $error = 'Refund amount must be greater than zero.';
    } elseif ($amountPaise > $refundablePaise) {
        $error = 'Refund amount cannot exceed remaining balance of ' . Context::formatMoney($refundablePaise) . '.';
    } else {
        PaymentService::processRefund($orderId, $amountPaise, $reason);
        $notice = 'Refund of ' . Context::formatMoney($amountPaise) . ' recorded in the transaction ledger successfully.';
        $order = OrderService::getOrder($orderId);
        $refStmt->execute([':oid' => $orderId]);
        $totalRefundedPaise = (int)$refStmt->fetchColumn();
        $refundablePaise = max(0, (int)$order['grand_total_amount'] - $totalRefundedPaise);
        $canRefund = ($order['payment_status'] !== 'REFUNDED' && $refundablePaise > 0 && $order['order_status'] !== 'CANCELLED');
    }
}

// Render SDK extension slots
$sdkSidebarWidget = ExtensionRegistry::getInstance()->renderSlot('order.detail.sidebar.card', ['order' => $order]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order <?= htmlspecialchars($order['order_number']) ?> &mdash; Nutronyx Admin Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=20261119_white">
</head>
<body class="admin-body">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="admin-layout-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main-stage">
            
            <!-- Breadcrumbs & Header -->
            <div class="page-header">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <a href="orders.php" style="color: var(--admin-forest-light); text-decoration: none; font-weight: 600; font-size: 13px;">
                            &larr; Back to Orders
                        </a>
                        <span style="color: #A0B0A7;">/</span>
                        <span style="font-size: 13px; color: var(--admin-text-muted);">Order Management</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <h1 class="page-title">Order <?= htmlspecialchars($order['order_number']) ?></h1>
                        <span class="badge-status badge-<?= strtolower($order['order_status']) ?>"><?= htmlspecialchars($order['order_status']) ?></span>
                        <span class="badge-status badge-<?= strtolower($order['payment_status']) ?>"><?= htmlspecialchars($order['payment_status']) ?></span>
                    </div>
                </div>

                <div class="page-actions">
                    <a href="../track-order.php?order=<?= urlencode($order['order_number']) ?>&phone=<?= urlencode($order['shipping_phone'] ?? '') ?>" target="_blank" class="btn-action btn-action-outline btn-action-sm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                        <span>Customer View</span>
                    </a>

                    <?php if ($order['order_status'] !== 'CANCELLED'): ?>
                        <?php if ($order['fulfillment_status'] !== 'FULFILLED' && $order['order_status'] !== 'DELIVERED'): ?>
                            <?php
                                $detailShippingAddr = trim(($order['shipping_address_line1'] ?? '') . ' ' . ($order['shipping_address_line2'] ?? '') . ', ' . ($order['shipping_city'] ?? '') . ', ' . ($order['shipping_state'] ?? '') . ' - ' . ($order['shipping_postal_code'] ?? ''));
                                $detailShippingName = $order['shipping_name'] ?: ($order['customer_name'] ?: 'Customer');
                                $detailShippingPhone = $order['shipping_phone'] ?: ($order['customer_phone'] ?: '');
                                $detailTotalRupees = (float)(($order['grand_total_amount'] ?? 0) / 100);
                                $detailPayMode = strtoupper($order['payment_method'] ?? '') === 'COD' ? 'COD' : 'Prepaid';
                            ?>
                            <button type="button" class="btn-action btn-action-sm" style="background: #144D29; color: #FFFFFF; border-color: #144D29; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;"
                                onclick="window.NutronyxOrder.openEkartShipModal('<?= htmlspecialchars($order['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($order['order_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($detailShippingName, ENT_QUOTES) ?>', '<?= htmlspecialchars($detailShippingPhone, ENT_QUOTES) ?>', '<?= htmlspecialchars($detailShippingAddr, ENT_QUOTES) ?>', '<?= $detailTotalRupees ?>', '<?= $detailPayMode ?>')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                <span>Ship on Ekart</span>
                            </button>
                            <button type="button" class="btn-action btn-action-sm btn-action-outline" onclick="window.NutronyxModal.open('fulfillModal')">
                                <span>Other Carrier &hellip;</span>
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($canRefund): ?>
                            <button type="button" class="btn-action btn-action-sm btn-action-outline" onclick="window.NutronyxModal.open('refundModal')">
                                <span>Process Refund</span>
                            </button>
                        <?php endif; ?>
                        
                        <button type="button" class="btn-action btn-action-sm btn-action-danger" onclick="triggerCancelConfirm()">
                            <span>Cancel Order</span>
                        </button>
                        <form id="cancelOrderForm" action="order-detail.php?id=<?= urlencode($order['id']) ?>" method="POST" style="display: none;">
                            <input type="hidden" name="action" value="cancel_order">
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($notice)): ?>
                <div class="dash-panel" style="background: var(--admin-success-bg); border-color: rgba(43, 110, 78, 0.25); color: var(--admin-success); padding: 14px 18px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span><?= htmlspecialchars($notice) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="dash-panel" style="background: var(--admin-danger-bg); border-color: rgba(184, 56, 56, 0.25); color: var(--admin-danger); padding: 14px 18px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Main Order Grid: 2 Column on Desktop, 1 Column on Mobile -->
            <div style="display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 24px;" class="form-grid-2">
                
                <!-- Left Column -->
                <div>
                    <!-- Order Items Panel -->
                    <div class="dash-panel">
                        <div class="dash-panel-head">
                            <div>
                                <h2 class="dash-panel-title">Order Line Items (<?= count($order['items']) ?>)</h2>
                                <p class="dash-panel-subtitle">Items purchased in this transaction</p>
                            </div>
                            <span class="badge-status badge-approved">Price Locked</span>
                        </div>

                        <div class="table-responsive">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Product Item</th>
                                        <th>Price</th>
                                        <th>Qty</th>
                                        <th style="text-align: right;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order['items'] as $item): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: #14221A;"><?= htmlspecialchars($item['title']) ?></div>
                                                <div style="font-size: 11.5px; color: #73867B;">SKU: <code><?= htmlspecialchars($item['sku']) ?></code></div>
                                            </td>
                                            <td><?= Context::formatMoney((int)$item['unit_price_amount']) ?></td>
                                            <td><strong><?= $item['quantity'] ?></strong></td>
                                            <td style="text-align: right; font-weight: 600; color: #14221A;">
                                                <?= Context::formatMoney((int)$item['total_amount']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Financial Ledger Summary -->
                        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--admin-border-subtle); display: flex; flex-direction: column; gap: 8px; max-width: 340px; margin-left: auto;">
                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--admin-text-muted);">
                                <span>Subtotal</span>
                                <span><?= Context::formatMoney((int)$order['subtotal_amount']) ?></span>
                            </div>
                            <?php if ($order['discount_amount'] > 0): ?>
                                <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--admin-success); font-weight: 600;">
                                    <span>Discount Applied</span>
                                    <span>-<?= Context::formatMoney((int)$order['discount_amount']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--admin-text-muted);">
                                <span>Estimated Taxes</span>
                                <span><?= Context::formatMoney((int)$order['tax_amount']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--admin-text-muted);">
                                <span>Shipping Charges</span>
                                <span><?= $order['shipping_amount'] > 0 ? Context::formatMoney((int)$order['shipping_amount']) : 'FREE' ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 700; color: #14221A; padding-top: 8px; border-top: 1px dashed var(--admin-border-subtle);">
                                <span>Grand Total</span>
                                <span><?= Context::formatMoney((int)$order['grand_total_amount']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Audit Timeline & Internal Notes -->
                    <div class="dash-panel">
                        <div class="dash-panel-head">
                            <h2 class="dash-panel-title">Audit Log & Timeline</h2>
                            <span style="font-size: 12px; color: var(--admin-text-muted);">Immutable Record</span>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            <?php foreach ($order['timeline'] as $tEvent): ?>
                                <div style="display: flex; gap: 12px; position: relative; padding-left: 20px;">
                                    <span style="position: absolute; left: 0; top: 6px; width: 8px; height: 8px; border-radius: 50%; background: var(--admin-forest-light);"></span>
                                    <div>
                                        <div style="font-size: 13.5px; color: #27382D; font-weight: 500; line-height: 1.4;">
                                            <?= htmlspecialchars($tEvent['message']) ?>
                                        </div>
                                        <div style="font-size: 11.5px; color: #8A9A90; margin-top: 2px;">
                                            <?= date('d M Y, h:i A', strtotime($tEvent['created_at'])) ?> &bull; Actor: <strong><?= htmlspecialchars($tEvent['actor']) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Add Note Form -->
                        <form action="order-detail.php?id=<?= urlencode($order['id']) ?>" method="POST" style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--admin-border-subtle);">
                            <input type="hidden" name="action" value="add_note">
                            <label class="form-label">Add Internal Staff Note</label>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <input type="text" name="note_text" required placeholder="Type operational note..." class="form-control" style="flex: 1; min-width: 200px;">
                                <button type="submit" class="btn-action">Save Note</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column (Customer, Carrier & Developer SDK Extension Point) -->
                <div>
                    <!-- Customer & Shipping Address Card -->
                    <div class="dash-panel">
                        <div class="dash-panel-head">
                            <h2 class="dash-panel-title" style="font-size: 18px;">Customer & Shipping</h2>
                            <?php if ($order['fulfillment_status'] !== 'FULFILLED' && $order['order_status'] !== 'CANCELLED'): ?>
                                <button type="button" class="btn-action btn-action-sm btn-action-outline" onclick="window.NutronyxModal.open('editAddressModal')">
                                    Edit Address
                                </button>
                            <?php else: ?>
                                <span style="font-size: 11px; color: var(--admin-text-muted); font-weight: 600;">🔒 Address Locked</span>
                            <?php endif; ?>
                        </div>

                        <div style="font-size: 13.5px; line-height: 1.6; color: #27382D;">
                            <strong style="color: #14221A; font-size: 14.5px;"><?= htmlspecialchars($order['shipping_name']) ?></strong>
                            <div><?= htmlspecialchars($order['shipping_address_line1']) ?></div>
                            <?php if (!empty($order['shipping_address_line2'])): ?>
                                <div><?= htmlspecialchars($order['shipping_address_line2']) ?></div>
                            <?php endif; ?>
                            <div><?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?> &mdash; <?= htmlspecialchars($order['shipping_postal_code']) ?></div>
                            <div style="margin-top: 8px; color: var(--admin-forest-light); font-weight: 600;">
                                Phone: <?= htmlspecialchars($order['shipping_phone']) ?>
                            </div>
                        </div>

                        <?php if (!empty($order['customer_notes'])): ?>
                            <div style="margin-top: 14px; padding: 10px 12px; background: #FAF9F6; border-radius: 8px; border: 1px solid var(--admin-border-subtle); font-size: 12.5px;">
                                <span style="font-weight: 600; color: #14221A; display: block; margin-bottom: 2px;">Delivery Note:</span>
                                <span style="color: #586B5F;"><?= htmlspecialchars($order['customer_notes']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Carrier & Tracking Info -->
                    <div class="dash-panel">
                        <div class="dash-panel-head">
                            <h2 class="dash-panel-title" style="font-size: 18px;">Shipment Tracking</h2>
                        </div>
                        <?php if (empty($order['shipments'])): ?>
                            <p style="font-size: 13px; color: var(--admin-text-muted);">No carrier dispatch record yet. Click "Dispatch Order" when ready.</p>
                        <?php else: ?>
                            <?php foreach ($order['shipments'] as $shipment): ?>
                                <div style="font-size: 13.5px; color: #27382D;">
                                    <div>Carrier: <strong><?= htmlspecialchars($shipment['carrier']) ?></strong></div>
                                    <div style="margin: 6px 0; display: flex; align-items: center; gap: 8px;">
                                        <span>Tracking #:</span>
                                        <code style="color: var(--admin-forest); font-weight: 700; background: #FAF9F6; padding: 3px 6px; border-radius: 6px; border: 1px solid var(--admin-input-border);"><?= htmlspecialchars($shipment['tracking_number']) ?></code>
                                        <button type="button" onclick="window.NutronyxCopy('<?= htmlspecialchars($shipment['tracking_number']) ?>', this)" style="background: none; border: none; color: var(--admin-forest); cursor: pointer; font-size: 12px; text-decoration: underline;">Copy</button>
                                    </div>
                                    <div style="font-size: 12px; color: #697C70; margin-bottom: 8px;">Dispatched on: <?= date('d M Y, h:i A', strtotime($shipment['shipped_at'])) ?></div>
<?php 
                                        $shipCarrier = $shipment['carrier_name'] ?? $shipment['carrier'] ?? 'Ekart Logistics';
                                        $shipCarrierLower = strtolower($shipCarrier);
                                        $shipAwb = $shipment['tracking_number'] ?? '';
                                        $shipUrl = $shipment['tracking_url'] ?? '';
                                        if (empty($shipUrl) && !empty($shipAwb)) {
                                            if (str_contains($shipCarrierLower, 'ekart')) {
                                                $shipUrl = 'https://app.elite.ekartlogistics.in/track/' . urlencode($shipAwb);
                                            } elseif (str_contains($shipCarrierLower, 'delhivery')) {
                                                $shipUrl = 'https://www.delhivery.com/track/package/' . urlencode($shipAwb);
                                            } else {
                                                $shipUrl = 'https://app.elite.ekartlogistics.in/track/' . urlencode($shipAwb);
                                            }
                                        }
                                    ?>
                                    <a href="<?= htmlspecialchars($shipUrl ?: 'https://app.elite.ekartlogistics.in/') ?>" target="_blank" class="dash-tool-link" style="display: inline-flex;">
                                        <span>Track on <?= htmlspecialchars($shipCarrier) ?> &rarr;</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- DEVELOPER SDK UI EXTENSION POINT -->
                    <!-- Injected dynamically if an extension registers order.detail.sidebar.card -->
                    <?= $sdkSidebarWidget ?>

                </div>

            </div>

        </main>
    </div>

    <!-- Controlled Address Editing Modal -->
    <div id="editAddressModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title">Controlled Address Modification</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('editAddressModal')">&times;</button>
            </div>
            <form action="order-detail.php?id=<?= urlencode($order['id']) ?>" method="POST">
                <input type="hidden" name="action" value="update_address">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Recipient Full Name *</label>
                        <input type="text" name="shipping_name" value="<?= htmlspecialchars($order['shipping_name']) ?>" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Phone Number *</label>
                        <input type="text" name="shipping_phone" value="<?= htmlspecialchars($order['shipping_phone']) ?>" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Street Address Line 1 *</label>
                        <input type="text" name="shipping_address_line1" value="<?= htmlspecialchars($order['shipping_address_line1']) ?>" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 2 (Optional)</label>
                        <input type="text" name="shipping_address_line2" value="<?= htmlspecialchars((string)($order['shipping_address_line2'] ?? '')) ?>" class="form-control">
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">City *</label>
                            <input type="text" name="shipping_city" value="<?= htmlspecialchars($order['shipping_city']) ?>" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">State *</label>
                            <input type="text" name="shipping_state" value="<?= htmlspecialchars($order['shipping_state']) ?>" required class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">PIN / Postal Code *</label>
                        <input type="text" name="shipping_postal_code" value="<?= htmlspecialchars($order['shipping_postal_code']) ?>" required class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxModal.close('editAddressModal')">Cancel</button>
                    <button type="submit" class="btn-action">Save Address</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Fulfillment Dispatch Modal -->
    <!-- Ekart Ship Modal -->
    <?php include __DIR__ . '/includes/ekart-ship-modal.php'; ?>

    <div id="fulfillModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title">Fulfill & Dispatch Order</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('fulfillModal')">&times;</button>
            </div>
            <form action="order-detail.php?id=<?= urlencode($order['id']) ?>" method="POST">
                <input type="hidden" name="action" value="fulfill_order">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Logistics Carrier *</label>
                        <select name="carrier" class="form-control">
                            <option value="Ekart Logistics" selected>Ekart Logistics Enterprise</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">AWB / Courier Tracking Number *</label>
                        <input type="text" name="tracking_number" placeholder="Enter carrier AWB or tracking number" required class="form-control">
                        <small style="display: block; margin-top: 4px; font-size: 11.5px; color: var(--admin-text-muted);">Enter the authentic AWB from your shipping carrier dashboard.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxModal.close('fulfillModal')">Cancel</button>
                    <button type="submit" class="btn-action">Confirm Dispatch</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Refund Modal -->
    <div id="refundModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title">Process Financial Refund</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('refundModal')">&times;</button>
            </div>
            <form action="order-detail.php?id=<?= urlencode($order['id']) ?>" method="POST">
                <input type="hidden" name="action" value="process_refund">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Refund Amount (INR ₹) * &mdash; <small style="color: #586B5F;">Max refundable: <?= Context::formatMoney($refundablePaise) ?></small></label>
                        <input type="number" step="0.01" min="1" max="<?= $refundablePaise / 100 ?>" name="refund_amount_rupees" value="<?= $refundablePaise / 100 ?>" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Refund Reason Code *</label>
                        <select name="refund_reason" class="form-control">
                            <option value="Customer Return (Defective/Damaged)">Customer Return (Defective/Damaged)</option>
                            <option value="Customer Cancellation">Customer Cancellation</option>
                            <option value="Packaging Compromised in Transit">Packaging Compromised in Transit</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxModal.close('refundModal')">Cancel</button>
                    <button type="submit" class="btn-action btn-action-danger">Confirm Refund</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
    function triggerCancelConfirm() {
        window.NutronyxConfirm({
            title: 'Cancel Order <?= htmlspecialchars($order['order_number']) ?>?',
            message: 'Cancelling this order will release all reserved inventory items back to active stock. This action cannot be reversed.',
            confirmText: 'Yes, Cancel Order',
            cancelText: 'Keep Order Active',
            isDanger: true,
            onConfirm: function() {
                document.getElementById('cancelOrderForm').submit();
            }
        });
    }
    </script>
</body>
</html>
