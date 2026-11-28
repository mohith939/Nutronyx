<?php
/**
 * NUTRONYX — Real-Time Ekart Logistics Order Tracking Portal
 * Live synchronized with Ekart Logistics Enterprise Gateway
 * Supports verification via Order Number + Mobile, or directly via Ekart AWB
 */

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

use Nutronyx\Database;
use Nutronyx\Context;
use Nutronyx\Services\OrderService;
use Nutronyx\Services\EkartLogisticsService;

$db = Database::getConnection();

// Helper to load order, items, shipment and perform live sync with Ekart
function loadOrderTrackingDetails(PDO $db, string $orderId, bool $doLiveSync = true): ?array {
    $stmt = $db->prepare("
        SELECT o.*, c.name as customer_name
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.id = :oid
    ");
    $stmt->execute([':oid' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) return null;

    $iStmt = $db->prepare("SELECT sku, title, quantity, total_amount FROM order_items WHERE order_id = :oid");
    $iStmt->execute([':oid' => $order['id']]);
    $order['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);

    $sStmt = $db->prepare("SELECT * FROM shipments WHERE order_id = :oid ORDER BY shipped_at DESC LIMIT 1");
    $sStmt->execute([':oid' => $order['id']]);
    $order['shipment'] = $sStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $order['sync_result'] = null;
    if ($doLiveSync && $order['shipment'] && !empty($order['shipment']['tracking_number'])) {
        try {
            $syncRes = OrderService::syncEkartStatus($order['id']);
            $order['sync_result'] = $syncRes;
            // Reload updated order & shipment
            $stmt->execute([':oid' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $order['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);
            $sStmt->execute([':oid' => $order['id']]);
            $order['shipment'] = $sStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $ignore) {}
    }

    // Fetch live checkpoints from order_timeline
    $tlStmt = $db->prepare("
        SELECT * FROM order_timeline 
        WHERE order_id = :oid AND event_type IN ('EKART_UPDATE', 'FULFILLMENT') 
        ORDER BY created_at DESC, id DESC
    ");
    $tlStmt->execute([':oid' => $order['id']]);
    $order['ekart_checkpoints'] = $tlStmt->fetchAll(PDO::FETCH_ASSOC);

    return $order;
}

// -------------------------------------------------------------------------
// Live AJAX Polling Endpoint: ?ajax=live_track
// -------------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'live_track') {
    header('Content-Type: application/json; charset=utf-8');

    $targetOrderId = null;
    $reqOrderId = trim((string)($_GET['order_id'] ?? ''));
    $reqAwb = trim((string)($_GET['awb'] ?? ''));
    $reqOrderNum = trim((string)($_GET['order_number'] ?? $_GET['order'] ?? ''));
    $reqMobile = trim((string)($_GET['mobile'] ?? $_GET['phone'] ?? ''));

    if (!empty($reqOrderId)) {
        $targetOrderId = $reqOrderId;
    } elseif (!empty($reqAwb)) {
        $stmt = $db->prepare("SELECT order_id FROM shipments WHERE UPPER(tracking_number) = UPPER(:awb) ORDER BY shipped_at DESC LIMIT 1");
        $stmt->execute([':awb' => $reqAwb]);
        $targetOrderId = $stmt->fetchColumn() ?: null;
    } elseif (!empty($reqOrderNum) && !empty($reqMobile)) {
        $stmt = $db->prepare("SELECT id, shipping_phone FROM orders WHERE UPPER(order_number) = UPPER(:onum) LIMIT 1");
        $stmt->execute([':onum' => $reqOrderNum]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $c1 = preg_replace('/\D/', '', (string)$row['shipping_phone']);
            $c2 = preg_replace('/\D/', '', $reqMobile);
            if (substr($c1, -10) === substr($c2, -10)) {
                $targetOrderId = $row['id'];
            }
        }
    }

    if (!$targetOrderId) {
        echo json_encode(['success' => false, 'error' => 'Order authorization failed or not found.']);
        exit;
    }

    $freshOrder = loadOrderTrackingDetails($db, $targetOrderId, true);
    if (!$freshOrder) {
        echo json_encode(['success' => false, 'error' => 'Unable to load order data.']);
        exit;
    }

    $shipment = $freshOrder['shipment'];
    $shipmentStatus = strtoupper($shipment['status'] ?? '');
    $orderStatus = strtoupper($freshOrder['order_status'] ?? '');
    $isCancelled = ($orderStatus === 'CANCELLED' || $shipmentStatus === 'CANCELLED');
    $isPacked = !empty($shipment);
    $isDispatched = in_array($shipmentStatus, ['IN_TRANSIT', 'DISPATCHED', 'OUT_FOR_DELIVERY', 'DELIVERED'], true);
    $isDelivered = ($shipmentStatus === 'DELIVERED' || $orderStatus === 'DELIVERED' || $orderStatus === 'COMPLETED');

    $stepIndex = 1;
    if ($isDelivered) $stepIndex = 4;
    elseif ($isDispatched) $stepIndex = 3;
    elseif ($isPacked) $stepIndex = 2;

    $statusLabel = $isCancelled ? 'CANCELLED' : ($isDelivered ? 'DELIVERED' : ($isDispatched ? 'IN TRANSIT' : ($isPacked ? 'MANIFESTED' : $orderStatus)));

    $checkpoints = [];
    foreach (($freshOrder['ekart_checkpoints'] ?? []) as $cp) {
        $timeStr = !empty($cp['created_at']) ? date('d M Y, h:i A', strtotime($cp['created_at'])) . ' IST' : '';
        $checkpoints[] = [
            'id' => $cp['id'],
            'message' => $cp['message'],
            'created_at' => $timeStr,
            'actor' => $cp['actor'] ?? 'Ekart Logistics'
        ];
    }

    $edd = $shipment['estimated_delivery_date'] ?? null;
    $eddFormatted = !empty($edd) ? date('l, d M Y', strtotime($edd)) : 'Within 2–3 Business Days';

    echo json_encode([
        'success' => true,
        'order_number' => $freshOrder['order_number'],
        'order_status' => $orderStatus,
        'shipment_status' => $shipmentStatus,
        'status_label' => $statusLabel,
        'step_index' => $stepIndex,
        'is_cancelled' => $isCancelled,
        'is_delivered' => $isDelivered,
        'estimated_delivery' => $eddFormatted,
        'awb' => $shipment['tracking_number'] ?? '',
        'carrier' => $shipment['carrier'] ?? 'Ekart Logistics',
        'tracking_url' => $shipment['tracking_url'] ?? (!empty($shipment['tracking_number']) ? 'https://app.elite.ekartlogistics.in/track/' . urlencode($shipment['tracking_number']) : ''),
        'checkpoints' => $checkpoints,
        'last_updated' => date('h:i:s A') . ' IST',
        'is_live_track' => !empty($freshOrder['sync_result']['is_live_track'])
    ]);
    exit;
}

// -------------------------------------------------------------------------
// Standard Page GET / POST Query Resolution
// -------------------------------------------------------------------------
$orderNumber = trim((string)($_GET['order_number'] ?? $_GET['order'] ?? $_POST['order_number'] ?? ''));
$mobileNumber = trim((string)($_GET['mobile'] ?? $_GET['phone'] ?? $_POST['mobile'] ?? ''));
$awbNumber = trim((string)($_GET['awb'] ?? $_GET['tracking_number'] ?? $_GET['tracking_id'] ?? $_POST['awb'] ?? ''));

// Detect if user typed an Ekart AWB into the order number input field
if (empty($awbNumber) && !empty($orderNumber) && (str_starts_with(strtoupper($orderNumber), 'LUAP') || str_starts_with(strtoupper($orderNumber), 'EKT') || strlen($orderNumber) >= 14)) {
    $awbNumber = $orderNumber;
}

$matchedOrder = null;
$error = '';

if (!empty($awbNumber)) {
    // 1. Direct search by Ekart AWB / Tracking number
    $stmt = $db->prepare("SELECT order_id FROM shipments WHERE UPPER(tracking_number) = UPPER(:awb) ORDER BY shipped_at DESC LIMIT 1");
    $stmt->execute([':awb' => $awbNumber]);
    $oid = $stmt->fetchColumn();
    if ($oid) {
        $matchedOrder = loadOrderTrackingDetails($db, (string)$oid, true);
        if ($matchedOrder) {
            $orderNumber = $matchedOrder['order_number'];
        }
    } else {
        $error = 'No shipment found matching Ekart AWB ' . htmlspecialchars($awbNumber) . '. Please verify your AWB or enter your Nutronyx Order Number and Phone.';
    }
} elseif (!empty($orderNumber) && !empty($mobileNumber)) {
    // 2. Search by Order Number + Phone
    $cleanMobile = preg_replace('/\D/', '', $mobileNumber);
    $stmt = $db->prepare("SELECT id, shipping_phone FROM orders WHERE UPPER(order_number) = UPPER(:onum) LIMIT 1");
    $stmt->execute([':onum' => $orderNumber]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $orderCleanPhone = preg_replace('/\D/', '', (string)$row['shipping_phone']);
        if (substr($orderCleanPhone, -10) === substr($cleanMobile, -10)) {
            $matchedOrder = loadOrderTrackingDetails($db, (string)$row['id'], true);
        } else {
            $error = 'The mobile number does not match records for order ' . htmlspecialchars($orderNumber) . '. Please verify your registered phone number.';
        }
    } else {
        $error = 'No order found with order number ' . htmlspecialchars($orderNumber) . '. Please check your confirmation SMS or email.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-Time Order Tracking &mdash; NUTRONYX</title>
    <!-- Favicon & Brand Icons -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#144D29">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=20261128_v1">
    <style>
    :root {
        --ekart-forest: #144D29;
        --ekart-forest-hover: #0F3A1F;
        --ekart-emerald: #10B981;
        --ekart-emerald-dark: #047857;
        --ekart-bg: #FAF7F2;
        --ekart-card-border: rgba(215, 207, 192, 0.7);
        --ekart-text-dark: #14221A;
        --ekart-text-muted: #586B5F;
    }

    body.track-body { 
        background-color: var(--ekart-bg); 
        min-height: 100vh; 
        display: flex; 
        flex-direction: column;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .track-hero { 
        padding: 160px 4vw 36px; 
        text-align: center; 
        max-width: 760px; 
        margin: 0 auto; 
    }

    @media (max-width: 768px) {
        .track-hero { padding-top: 135px; }
        .track-card { padding: 22px 18px !important; margin-bottom: 30px !important; width: 95% !important; }
        .form-grid-search { grid-template-columns: 1fr !important; }
        .carrier-row { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; }
    }

    .track-card { 
        background: #FFFFFF; 
        border-radius: 24px; 
        padding: clamp(28px, 4vw, 44px); 
        border: 1px solid var(--ekart-card-border); 
        box-shadow: 0 16px 40px rgba(20, 52, 34, 0.05); 
        max-width: 720px; 
        margin: 0 auto 60px; 
        width: 92%; 
    }

    /* Stepper */
    .stepper { 
        display: flex; 
        justify-content: space-between; 
        position: relative; 
        margin: 32px 0 36px; 
    }
    .stepper::before { 
        content: ''; 
        position: absolute; 
        top: 18px; 
        left: 12%; 
        right: 12%; 
        height: 3px; 
        background: #E8E2D8; 
        z-index: 1; 
    }
    .stepper-progress-fill {
        position: absolute;
        top: 18px;
        left: 12%;
        height: 3px;
        background: var(--ekart-forest);
        z-index: 1;
        transition: width 0.4s ease;
    }
    .step-node { 
        position: relative; 
        z-index: 2; 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        gap: 8px; 
    }
    .step-dot { 
        width: 38px; 
        height: 38px; 
        border-radius: 50%; 
        background: #FAF9F6; 
        border: 2px solid #D2C9B8; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 13.5px; 
        font-weight: 700; 
        color: #73867B;
        transition: all 0.3s ease;
    }
    .step-dot.active { 
        background: var(--ekart-forest); 
        border-color: var(--ekart-forest); 
        color: #FFFFFF; 
        box-shadow: 0 0 0 4px rgba(20, 77, 41, 0.2); 
    }
    .step-dot.completed { 
        background: #EAF6ED; 
        border-color: #1E6B39; 
        color: #1E6B39; 
    }
    .step-title { 
        font-size: 12px; 
        font-weight: 600; 
        color: #586B5F; 
        text-align: center;
        white-space: nowrap;
    }
    .step-title.active { 
        color: var(--ekart-text-dark); 
        font-weight: 700; 
    }

    /* Live pulse indicator */
    .radar-pulse {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #10B981;
        display: inline-block;
        position: relative;
    }
    .radar-pulse::after {
        content: '';
        position: absolute;
        top: -3px;
        left: -3px;
        right: -3px;
        bottom: -3px;
        border-radius: 50%;
        border: 2px solid #10B981;
        animation: radarPing 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
    }
    @keyframes radarPing {
        0% { transform: scale(0.8); opacity: 1; }
        80%, 100% { transform: scale(2.4); opacity: 0; }
    }

    .btn-track { 
        padding: 13px 32px; 
        border-radius: 9999px; 
        background: var(--ekart-forest); 
        color: #FAF6ED; 
        font-size: 14.5px; 
        font-weight: 700; 
        border: none; 
        cursor: pointer; 
        transition: all 0.25s ease; 
        box-shadow: 0 4px 14px rgba(20, 77, 41, 0.2); 
        letter-spacing: 0.01em; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; 
        gap: 8px; 
    }
    .btn-track:hover { 
        background: var(--ekart-forest-hover); 
        transform: translateY(-2px); 
        box-shadow: 0 8px 20px rgba(15, 58, 31, 0.3); 
        color: #FFFFFF; 
    }

    .btn-sync-live {
        background: #FFFFFF;
        border: 1.5px solid var(--ekart-forest);
        color: var(--ekart-forest);
        font-size: 12px;
        font-weight: 700;
        padding: 6px 14px;
        border-radius: 9999px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    .btn-sync-live:hover {
        background: #F0FDF4;
        transform: translateY(-1px);
    }

    /* Checkpoint timeline */
    .timeline-wrap {
        position: relative;
        padding-left: 28px;
        margin-top: 14px;
    }
    .timeline-wrap::before {
        content: '';
        position: absolute;
        top: 10px;
        bottom: 10px;
        left: 9px;
        width: 2px;
        background: #E5E7EB;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }
    .timeline-item:last-child {
        margin-bottom: 0;
    }
    .timeline-item-node {
        position: absolute;
        left: -28px;
        top: 4px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #FFFFFF;
        border: 2px solid #CBD5E1;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
    }
    .timeline-item.active .timeline-item-node {
        border-color: #10B981;
        background: #10B981;
        color: #FFFFFF;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
    }
    .timeline-item.completed .timeline-item-node {
        border-color: #144D29;
        background: #144D29;
        color: #FFFFFF;
    }

    .form-tab-btn {
        padding: 8px 18px;
        font-size: 12.5px;
        font-weight: 700;
        border-radius: 9999px;
        border: 1px solid #D2C9B8;
        background: transparent;
        color: #586B5F;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .form-tab-btn.active {
        background: var(--ekart-forest);
        color: #FFFFFF;
        border-color: var(--ekart-forest);
    }
    </style>
</head>
<body class="track-body">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <main>
        <div class="track-hero">
            <div style="display: inline-flex; align-items: center; gap: 8px; background: #EAF6ED; border: 1px solid #BBF7D0; padding: 4px 14px; border-radius: 9999px; margin-bottom: 12px;">
                <span class="radar-pulse"></span>
                <span style="font-size: 11.5px; font-weight: 700; color: #144D29; text-transform: uppercase; letter-spacing: 0.08em;">Live Ekart Logistics Integration</span>
            </div>
            <h1 style="font-family: var(--font-serif, 'Playfair Display'); font-size: clamp(32px, 4.5vw, 44px); font-weight: 600; color: #14221A; margin: 0;">Track Your Order</h1>
            <p style="font-size: 14.5px; color: #586B5F; margin-top: 10px; line-height: 1.5;">Real-time shipment telemetry synchronized with Ekart Logistics network.</p>
        </div>

        <div class="track-card">
            
            <!-- Toggle Search Method Tabs -->
            <div style="display: flex; justify-content: center; gap: 10px; margin-bottom: 22px;">
                <button type="button" class="form-tab-btn <?= empty($awbNumber) ? 'active' : '' ?>" onclick="window.switchTrackTab('order')">
                    Order Number + Phone
                </button>
                <button type="button" class="form-tab-btn <?= !empty($awbNumber) ? 'active' : '' ?>" onclick="window.switchTrackTab('awb')">
                    Ekart Waybill / AWB
                </button>
            </div>

            <!-- Tab 1: Order Number + Mobile Form -->
            <form id="trackOrderForm" action="track-order" method="GET" style="display: <?= empty($awbNumber) ? 'flex' : 'none' ?>; flex-direction: column; gap: 16px; margin-bottom: 20px;">
                <div class="form-grid-search" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: #14221A; display: block; margin-bottom: 6px;">Nutronyx Order Number</label>
                        <input type="text" name="order_number" value="<?= htmlspecialchars($orderNumber) ?>" placeholder="e.g. NX-1049" style="width: 100%; padding: 11px 14px; border: 1.5px solid #E2DBD0; border-radius: 10px; font-size: 13.5px; text-transform: uppercase; outline: none; background: #FAF9F6; font-weight: 600;">
                    </div>
                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: #14221A; display: block; margin-bottom: 6px;">Contact Mobile Number</label>
                        <input type="tel" name="mobile" value="<?= htmlspecialchars($mobileNumber) ?>" placeholder="e.g. 9876543210" style="width: 100%; padding: 11px 14px; border: 1.5px solid #E2DBD0; border-radius: 10px; font-size: 13.5px; outline: none; background: #FAF9F6; font-weight: 600;">
                    </div>
                </div>
                <button type="submit" class="btn-track" style="align-self: center; width: 100%; max-width: 260px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <span>Track Order</span>
                </button>
            </form>

            <!-- Tab 2: Ekart AWB Direct Form -->
            <form id="trackAwbForm" action="track-order" method="GET" style="display: <?= !empty($awbNumber) ? 'flex' : 'none' ?>; flex-direction: column; gap: 16px; margin-bottom: 20px;">
                <div>
                    <label style="font-size: 12px; font-weight: 700; color: #14221A; display: block; margin-bottom: 6px;">Ekart Logistics AWB / Tracking ID</label>
                    <input type="text" name="awb" value="<?= htmlspecialchars($awbNumber) ?>" placeholder="e.g. LUAP500039A2026 or 500999A3408005" style="width: 100%; padding: 11px 14px; border: 1.5px solid #E2DBD0; border-radius: 10px; font-size: 14px; text-transform: uppercase; outline: none; background: #FAF9F6; font-weight: 700; letter-spacing: 0.05em;">
                </div>
                <button type="submit" class="btn-track" style="align-self: center; width: 100%; max-width: 260px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                    <span>Track via Ekart AWB</span>
                </button>
            </form>

            <!-- Error Banner -->
            <?php if (!empty($error)): ?>
                <div style="background: #FEF2F2; border: 1.5px solid #FECACA; color: #991B1B; padding: 14px 18px; border-radius: 12px; font-size: 13.5px; text-align: center; margin-top: 14px;">
                    &#9888; <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Matched Order Verified Milestone View -->
            <?php if ($matchedOrder): 
                $shipment = $matchedOrder['shipment'];
                $shipmentStatus = strtoupper($shipment['status'] ?? '');
                $orderStatus = strtoupper($matchedOrder['order_status'] ?? '');
                $isCancelled = ($orderStatus === 'CANCELLED' || $shipmentStatus === 'CANCELLED');
                $isPacked = !empty($shipment);
                $isDispatched = in_array($shipmentStatus, ['IN_TRANSIT', 'DISPATCHED', 'OUT_FOR_DELIVERY', 'DELIVERED'], true);
                $isDelivered = ($shipmentStatus === 'DELIVERED' || $orderStatus === 'DELIVERED' || $orderStatus === 'COMPLETED');

                $stepIndex = 1;
                if ($isDelivered) $stepIndex = 4;
                elseif ($isDispatched) $stepIndex = 3;
                elseif ($isPacked) $stepIndex = 2;

                $progressWidth = '0%';
                if ($stepIndex === 2) $progressWidth = '33%';
                elseif ($stepIndex === 3) $progressWidth = '66%';
                elseif ($stepIndex === 4) $progressWidth = '100%';

                $shipCarrier = $shipment['carrier_name'] ?? $shipment['carrier'] ?? 'Ekart Logistics';
                $shipAwb = $shipment['tracking_number'] ?? '';
                $shipUrl = $shipment['tracking_url'] ?? '';
                if (empty($shipUrl) && !empty($shipAwb)) {
                    $shipUrl = 'https://app.elite.ekartlogistics.in/track/' . urlencode($shipAwb);
                }

                $eddRaw = $shipment['estimated_delivery_date'] ?? null;
                $eddDisplay = !empty($eddRaw) ? date('l, d M Y', strtotime($eddRaw)) : 'Within 2–3 Business Days';
            ?>
                <div id="verifiedTrackingArea" style="border-top: 1px solid #ECE7DE; padding-top: 24px; margin-top: 20px;">
                    
                    <!-- Live Header Row -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
                        <div>
                            <span style="font-size: 11.5px; font-weight: 700; color: #697C70; text-transform: uppercase; letter-spacing: 0.05em;">Verified Order</span>
                            <div style="font-family: var(--font-serif); font-size: 26px; font-weight: 700; color: #14221A; margin-top: 2px;">
                                <?= htmlspecialchars($matchedOrder['order_number']) ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span id="orderStatusBadge" style="font-size: 11.5px; font-weight: 800; background: <?= $isCancelled ? '#FEF2F2' : ($isDelivered ? '#DCFCE7' : '#E0F2FE') ?>; color: <?= $isCancelled ? '#991B1B' : ($isDelivered ? '#15803D' : '#0369A1') ?>; padding: 4px 12px; border-radius: 9999px; letter-spacing: 0.04em; display: inline-block;">
                                <?= htmlspecialchars($isCancelled ? 'CANCELLED' : ($isDelivered ? 'DELIVERED' : ($isDispatched ? 'IN TRANSIT' : ($isPacked ? 'MANIFESTED' : $matchedOrder['order_status'])))) ?>
                            </span>
                            <div style="font-size: 12px; color: #697C70; margin-top: 5px;">
                                Placed on <?= date('d M Y, h:i A', strtotime($matchedOrder['created_at'])) ?> IST
                            </div>
                        </div>
                    </div>

                    <!-- Milestone Stepper -->
                    <?php if (!$isCancelled): ?>
                        <div class="stepper" id="milestoneStepper">
                            <div class="stepper-progress-fill" id="stepperProgressFill" style="width: <?= $progressWidth ?>;"></div>
                            
                            <div class="step-node" id="stepNode1">
                                <div class="step-dot completed">&check;</div>
                                <span class="step-title active">Confirmed</span>
                            </div>
                            
                            <div class="step-node" id="stepNode2">
                                <div class="step-dot <?= $isPacked ? 'completed' : 'active' ?>" id="stepDot2">
                                    <?= $isPacked ? '&check;' : '2' ?>
                                </div>
                                <span class="step-title <?= $isPacked ? 'active' : '' ?>" id="stepTitle2">Packed / Ready</span>
                            </div>
                            
                            <div class="step-node" id="stepNode3">
                                <div class="step-dot <?= $isDelivered ? 'completed' : ($isDispatched ? 'active' : '') ?>" id="stepDot3">
                                    <?= $isDelivered ? '&check;' : '3' ?>
                                </div>
                                <span class="step-title <?= ($isDispatched || $isDelivered) ? 'active' : '' ?>" id="stepTitle3">In Transit</span>
                            </div>
                            
                            <div class="step-node" id="stepNode4">
                                <div class="step-dot <?= $isDelivered ? 'completed' : '' ?>" id="stepDot4">
                                    <?= $isDelivered ? '&check;' : '4' ?>
                                </div>
                                <span class="step-title <?= $isDelivered ? 'active' : '' ?>" id="stepTitle4">Delivered</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="background: #FEF2F2; padding: 16px; border-radius: 12px; color: #991B1B; font-size: 13.5px; margin: 20px 0; text-align: center; border: 1.5px solid #FECACA;">
                            This shipment was cancelled on Ekart Logistics. If payment was captured, your refund is being processed to your original payment method.
                        </div>
                    <?php endif; ?>

                    <!-- Carrier & Delivery Card -->
                    <?php if (!empty($shipment)): ?>
                        <div style="background: #FAF9F6; border: 1.5px solid #E8E2D8; border-radius: 16px; padding: 18px 20px; margin-bottom: 20px;">
                            <div class="carrier-row" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                                <div>
                                    <div style="font-size: 11px; color: #697C70; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">
                                        Logistics Carrier Partner
                                    </div>
                                    <div style="font-size: 15px; font-weight: 800; color: #14221A; margin-top: 2px;">
                                        <?= htmlspecialchars($shipCarrier) ?> (Flipkart Supply Chain)
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                        <span style="font-size: 13px; font-family: monospace; font-weight: 700; color: #144D29;" id="shipmentAwbText">
                                            AWB: <?= htmlspecialchars($shipAwb) ?>
                                        </span>
                                        <button type="button" onclick="window.copyToClipboard('<?= htmlspecialchars($shipAwb) ?>')" style="background: none; border: none; cursor: pointer; color: #047857; font-size: 11.5px; font-weight: 700; padding: 2px 6px; text-decoration: underline;">
                                            Copy
                                        </button>
                                    </div>
                                </div>

                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 6px;">
                                    <a href="<?= htmlspecialchars($shipUrl) ?>" target="_blank" style="padding: 8px 16px; border-radius: 9999px; background: #144D29; color: #FFFFFF; font-size: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(20,77,41,0.2);">
                                        <span>View on Ekart Portal</span>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                    </a>
                                </div>
                            </div>

                            <!-- Estimated Delivery Highlight -->
                            <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed #D2C9B8; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                <div style="font-size: 12.5px; color: #374151;">
                                    <span style="color: #6B7280;">Estimated Delivery:</span>
                                    <strong style="color: #144D29; margin-left: 4px;" id="estimatedDeliveryText"><?= htmlspecialchars($eddDisplay) ?></strong>
                                </div>
                                <div style="font-size: 12px; color: #6B7280;">
                                    Destination: <strong><?= htmlspecialchars(($matchedOrder['shipping_city'] ?? '') . ', ' . ($matchedOrder['shipping_state'] ?? '')) ?></strong> (PIN <?= htmlspecialchars(substr((string)($matchedOrder['shipping_postal_code'] ?? '000000'), 0, 3) . '***') ?>)
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Real-Time Telemetry Bar -->
                    <div style="background: #F0FDF4; border: 1.5px solid #BBF7D0; border-radius: 12px; padding: 12px 16px; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px; font-size: 12.5px; color: #166534; font-weight: 600;">
                            <span class="radar-pulse"></span>
                            <span id="liveSyncStatusText">Live Ekart Telemetry Active &bull; Last synchronized: Just now</span>
                        </div>
                        <button type="button" class="btn-sync-live" id="btnLiveSync" onclick="window.fetchLiveTrackUpdates(this)">
                            <svg id="syncSpinnerIcon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                            <span>Live Refresh</span>
                        </button>
                    </div>

                    <!-- Live Checkpoints Timeline -->
                    <div style="background: #FFFFFF; border: 1.5px solid #E8E2D8; border-radius: 16px; padding: 20px; margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <div style="font-size: 13px; font-weight: 800; color: #14221A; text-transform: uppercase; letter-spacing: 0.05em;">
                                Live Ekart Checkpoint History
                            </div>
                            <span style="font-size: 11px; font-weight: 700; color: #047857; background: #DCFCE7; padding: 2px 8px; border-radius: 9999px;">
                                Auto-updating
                            </span>
                        </div>

                        <div class="timeline-wrap" id="checkpointsTimelineContainer">
                            <?php if (!empty($matchedOrder['ekart_checkpoints'])): ?>
                                <?php foreach ($matchedOrder['ekart_checkpoints'] as $idx => $cp): ?>
                                    <div class="timeline-item <?= $idx === 0 ? 'active' : 'completed' ?>">
                                        <div class="timeline-item-node">
                                            <?= $idx === 0 ? '●' : '✓' ?>
                                        </div>
                                        <div>
                                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                                <span style="font-size: 13.5px; font-weight: 700; color: <?= $idx === 0 ? '#144D29' : '#1F2937' ?>;">
                                                    <?= htmlspecialchars($cp['message']) ?>
                                                </span>
                                                <?php if ($idx === 0): ?>
                                                    <span style="font-size: 10px; font-weight: 800; background: #10B981; color: #FFFFFF; padding: 2px 6px; border-radius: 9999px; text-transform: uppercase;">
                                                        Latest Scan
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 11.5px; color: #6B7280; margin-top: 3px;">
                                                <?= date('d M Y, h:i A', strtotime($cp['created_at'])) ?> IST &bull; Ingested by <?= htmlspecialchars($cp['actor'] ?? 'Ekart Logistics') ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="timeline-item active">
                                    <div class="timeline-item-node">✓</div>
                                    <div>
                                        <div style="font-size: 13.5px; font-weight: 700; color: #144D29;">
                                            Order Confirmed & Manifesting with Ekart Logistics
                                        </div>
                                        <div style="font-size: 11.5px; color: #6B7280; margin-top: 3px;">
                                            Origin: Uppal, Hyderabad, Telangana (500039)
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Order Items Summary -->
                    <div style="background: #FAF9F6; border: 1.5px solid #E8E2D8; border-radius: 16px; padding: 18px 20px;">
                        <span style="font-size: 12px; font-weight: 700; color: #14221A; display: block; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.05em;">
                            Package Contents (<?= count($matchedOrder['items']) ?> <?= count($matchedOrder['items']) === 1 ? 'item' : 'items' ?>)
                        </span>
                        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach ($matchedOrder['items'] as $it): ?>
                                <li style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #374151; border-bottom: 1px dashed #E5E7EB; padding-bottom: 8px;">
                                    <span><strong><?= (int)$it['quantity'] ?>x</strong> <?= htmlspecialchars($it['title']) ?></span>
                                    <strong style="color: #144D29;"><?= Context::formatMoney((int)$it['total_amount']) ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Client-Side Real-Time Polling Script -->
    <script>
    window.switchTrackTab = function(type) {
        const orderForm = document.getElementById('trackOrderForm');
        const awbForm = document.getElementById('trackAwbForm');
        const btns = document.querySelectorAll('.form-tab-btn');

        if (type === 'awb') {
            if (orderForm) orderForm.style.display = 'none';
            if (awbForm) awbForm.style.display = 'flex';
            if (btns[0]) btns[0].classList.remove('active');
            if (btns[1]) btns[1].classList.add('active');
        } else {
            if (orderForm) orderForm.style.display = 'flex';
            if (awbForm) awbForm.style.display = 'none';
            if (btns[0]) btns[0].classList.add('active');
            if (btns[1]) btns[1].classList.remove('active');
        }
    };

    window.copyToClipboard = function(text) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            alert('Ekart AWB copied to clipboard: ' + text);
        }).catch(() => {
            prompt('Copy Ekart AWB:', text);
        });
    };

    // Live Tracking Poller
    <?php if ($matchedOrder): ?>
    (function() {
        const orderId = <?= json_encode($matchedOrder['id']) ?>;
        const isFinal = <?= json_encode($isDelivered || $isCancelled) ?>;
        let pollTimer = null;

        window.fetchLiveTrackUpdates = function(btn) {
            const spinner = document.getElementById('syncSpinnerIcon');
            if (spinner) spinner.style.animation = 'spin 1s linear infinite';
            if (btn) btn.disabled = true;

            const syncText = document.getElementById('liveSyncStatusText');
            if (syncText) syncText.textContent = 'Syncing with Ekart Logistics network...';

            fetch(`track-order.php?ajax=live_track&order_id=${encodeURIComponent(orderId)}`)
                .then(r => r.json())
                .then(data => {
                    if (spinner) spinner.style.animation = 'none';
                    if (btn) btn.disabled = false;

                    if (data.success) {
                        // Update status badge
                        const badge = document.getElementById('orderStatusBadge');
                        if (badge) {
                            badge.textContent = data.status_label;
                            if (data.is_cancelled) {
                                badge.style.background = '#FEF2F2';
                                badge.style.color = '#991B1B';
                            } else if (data.is_delivered) {
                                badge.style.background = '#DCFCE7';
                                badge.style.color = '#15803D';
                            } else {
                                badge.style.background = '#E0F2FE';
                                badge.style.color = '#0369A1';
                            }
                        }

                        // Update Stepper
                        const fill = document.getElementById('stepperProgressFill');
                        const sDot2 = document.getElementById('stepDot2');
                        const sTitle2 = document.getElementById('stepTitle2');
                        const sDot3 = document.getElementById('stepDot3');
                        const sTitle3 = document.getElementById('stepTitle3');
                        const sDot4 = document.getElementById('stepDot4');
                        const sTitle4 = document.getElementById('stepTitle4');

                        if (fill) {
                            if (data.step_index === 1) fill.style.width = '0%';
                            else if (data.step_index === 2) fill.style.width = '33%';
                            else if (data.step_index === 3) fill.style.width = '66%';
                            else if (data.step_index === 4) fill.style.width = '100%';
                        }

                        if (data.step_index >= 2) {
                            if (sDot2) { sDot2.className = 'step-dot completed'; sDot2.innerHTML = '&check;'; }
                            if (sTitle2) sTitle2.classList.add('active');
                        }
                        if (data.step_index >= 3) {
                            if (sDot3) { sDot3.className = (data.step_index === 3) ? 'step-dot active' : 'step-dot completed'; if (data.step_index > 3) sDot3.innerHTML = '&check;'; }
                            if (sTitle3) sTitle3.classList.add('active');
                        }
                        if (data.step_index === 4) {
                            if (sDot4) { sDot4.className = 'step-dot completed'; sDot4.innerHTML = '&check;'; }
                            if (sTitle4) sTitle4.classList.add('active');
                        }

                        // Update Estimated Delivery
                        const eddEl = document.getElementById('estimatedDeliveryText');
                        if (eddEl && data.estimated_delivery) {
                            eddEl.textContent = data.estimated_delivery;
                        }

                        // Update Checkpoints Timeline
                        const container = document.getElementById('checkpointsTimelineContainer');
                        if (container && data.checkpoints && data.checkpoints.length > 0) {
                            let html = '';
                            data.checkpoints.forEach((cp, idx) => {
                                const isActive = (idx === 0);
                                html += `
                                    <div class="timeline-item ${isActive ? 'active' : 'completed'}">
                                        <div class="timeline-item-node">${isActive ? '●' : '✓'}</div>
                                        <div>
                                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                                <span style="font-size: 13.5px; font-weight: 700; color: ${isActive ? '#144D29' : '#1F2937'};">
                                                    ${cp.message}
                                                </span>
                                                ${isActive ? '<span style="font-size: 10px; font-weight: 800; background: #10B981; color: #FFFFFF; padding: 2px 6px; border-radius: 9999px; text-transform: uppercase;">Latest Scan</span>' : ''}
                                            </div>
                                            <div style="font-size: 11.5px; color: #6B7280; margin-top: 3px;">
                                                ${cp.created_at} &bull; Ingested by ${cp.actor}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            container.innerHTML = html;
                        }

                        if (syncText) syncText.textContent = `Live Ekart Telemetry Active • Updated ${data.last_updated}`;
                    } else {
                        if (syncText) syncText.textContent = 'Live update check complete.';
                    }
                })
                .catch(err => {
                    if (spinner) spinner.style.animation = 'none';
                    if (btn) btn.disabled = false;
                    if (syncText) syncText.textContent = 'Temporary network delay syncing with Ekart.';
                    console.error('Ekart sync error:', err);
                });
        };

        // If order is active in transit, poll automatically every 25 seconds
        if (!isFinal) {
            pollTimer = setInterval(() => {
                if (!document.hidden) {
                    window.fetchLiveTrackUpdates(null);
                }
            }, 25000);
        }
    })();
    <?php endif; ?>
    </script>
    <style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    </style>
</body>
</html>
