<?php
/**
 * NUTRONYX — Standalone Customer Order Tracking Portal
 * Secure verification via Order Number + Customer Mobile Number
 * Strictly decoupled from customer-facing account dashboards
 */

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

use Nutronyx\Database;
use Nutronyx\Context;

$orderNumber = trim($_GET['order_number'] ?? $_POST['order_number'] ?? '');
$mobileNumber = trim($_GET['mobile'] ?? $_POST['mobile'] ?? '');

$matchedOrder = null;
$error = '';

if (!empty($orderNumber) && !empty($mobileNumber)) {
    $db = Database::getConnection();
    // Normalize phone (strip non-digits)
    $cleanMobile = preg_replace('/\D/', '', $mobileNumber);

    $stmt = $db->prepare("
        SELECT o.*, c.name as customer_name
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE UPPER(o.order_number) = UPPER(:onum)
    ");
    $stmt->execute([':onum' => $orderNumber]);
    $order = $stmt->fetch();

    if ($order) {
        $orderCleanPhone = preg_replace('/\D/', '', $order['shipping_phone']);
        // Verify last 10 digits match
        if (substr($orderCleanPhone, -10) === substr($cleanMobile, -10)) {
            // Verified! Fetch line items and shipments
            $iStmt = $db->prepare("SELECT sku, title, quantity, total_amount FROM order_items WHERE order_id = :oid");
            $iStmt->execute([':oid' => $order['id']]);
            $order['items'] = $iStmt->fetchAll();

            $sStmt = $db->prepare("SELECT * FROM shipments WHERE order_id = :oid ORDER BY shipped_at DESC LIMIT 1");
            $sStmt->execute([':oid' => $order['id']]);
            $order['shipment'] = $sStmt->fetch() ?: null;

            $matchedOrder = $order;
        } else {
            $error = 'The mobile number does not match the records for this order number. Please verify your details.';
        }
    } else {
        $error = 'No order found with the provided order number. Please check your confirmation SMS or email.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NUTRONYX</title>
    <!-- Favicon & Brand Icons -->
    <link rel=icon type=image/x-icon href="/favicon.ico">
    <link rel=icon type=image/png sizes="16x16" href="/favicon-16x16.png">
    <link rel=icon type=image/png sizes="32x32" href="/favicon-32x32.png">
    <link rel=icon type=image/png sizes="48x48" href="/favicon-48x48.png">
    <link rel=icon type=image/png sizes="96x96" href="/favicon-96x96.png">
    <link rel=icon type=image/png sizes="192x192" href="/favicon-192x192.png">
    <link rel=apple-touch-icon sizes="180x180" href="/apple-touch-icon.png">
    <link rel=manifest href="/site.webmanifest">
    <meta name=theme-color content="#174529">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=20261124_v1">
    <style>
    .track-body { background-color: #FAF7F2; min-height: 100vh; display: flex; flex-direction: column; }
    .track-hero { padding: 120px 4vw 40px; text-align: center; max-width: 720px; margin: 0 auto; }
    .track-card { background: #FFFFFF; border-radius: 24px; padding: clamp(28px, 4vw, 40px); border: 1px solid rgba(215, 207, 192, 0.6); box-shadow: 0 16px 40px rgba(20, 52, 34, 0.05); max-width: 680px; margin: 0 auto 60px; width: 92%; }
    .stepper { display: flex; justify-content: space-between; position: relative; margin: 30px 0 36px; }
    .stepper::before { content: ''; position: absolute; top: 16px; left: 10%; right: 10%; height: 2px; background: #E2DBD0; z-index: 1; }
    .step-node { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 8px; }
    .step-dot { width: 34px; height: 34px; border-radius: 50%; background: #FAF9F6; border: 2px solid #D2C9B8; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #73867B; }
    .step-dot.active { background: #143D24; border-color: #143D24; color: #FFFFFF; }
    .step-dot.completed { background: #EAF6ED; border-color: #1E6B39; color: #1E6B39; }
    .step-title { font-size: 12px; font-weight: 600; color: #586B5F; }
    .step-title.active { color: #14221A; font-weight: 700; }
    .btn-track { padding: 12px 28px; border-radius: 9999px; background: #143D24; color: #FFFFFF; font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; }
    .btn-track:hover { background: #0D2817; }
    </style>
</head>
<body class="track-body">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <main>
        <div class="track-hero">
            <span style="font-size: 11.5px; font-weight: 700; color: #1E5736; text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 8px;">Order Tracking Concierge</span>
            <h1 style="font-family: var(--font-serif, 'Playfair Display'); font-size: clamp(32px, 4.5vw, 44px); font-weight: 600; color: #14221A;">Track Your Wellness Package</h1>
            <p style="font-size: 14.5px; color: #586B5F; margin-top: 10px; line-height: 1.5;">Enter your order reference number and associated contact mobile number to view real-time fulfillment milestones.</p>
        </div>

        <div class="track-card">
            <!-- Tracking Query Form -->
            <form action="track-order.php" method="GET" style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div>
                        <label style="font-size: 12.5px; font-weight: 600; color: #14221A; display: block; margin-bottom: 6px;">Order Number</label>
                        <input type="text" name="order_number" value="<?= htmlspecialchars($orderNumber) ?>" required placeholder="e.g. NX-1049" style="width: 100%; padding: 11px 14px; border: 1px solid #E2DBD0; border-radius: 10px; font-size: 13.5px; text-transform: uppercase; outline: none; background: #FAF9F6;">
                    </div>
                    <div>
                        <label style="font-size: 12.5px; font-weight: 600; color: #14221A; display: block; margin-bottom: 6px;">Contact Mobile Number</label>
                        <input type="tel" name="mobile" value="<?= htmlspecialchars($mobileNumber) ?>" required placeholder="e.g. 9876543210" style="width: 100%; padding: 11px 14px; border: 1px solid #E2DBD0; border-radius: 10px; font-size: 13.5px; outline: none; background: #FAF9F6;">
                    </div>
                </div>
                <button type="submit" class="btn-track" style="align-self: center; width: 100%; max-width: 240px;">
                    Locate Shipment &rarr;
                </button>
            </form>

            <!-- Error Banner -->
            <?php if (!empty($error)): ?>
                <div style="background: #FDF3F3; border: 1px solid rgba(184, 56, 56, 0.3); color: #B83838; padding: 12px 16px; border-radius: 10px; font-size: 13px; text-align: center; margin-top: 12px;">
                    &#9888; <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Matched Order Verified Milestone View -->
            <?php if ($matchedOrder): 
                $isFulfilled = ($matchedOrder['fulfillment_status'] === 'FULFILLED');
                $isCancelled = ($matchedOrder['order_status'] === 'CANCELLED');
            ?>
                <div style="border-top: 1px solid #ECE7DE; padding-top: 24px; margin-top: 20px;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div>
                            <span style="font-size: 12px; color: #697C70;">Verified Order:</span>
                            <div style="font-family: var(--font-serif); font-size: 24px; font-weight: 600; color: #14221A;">
                                <?= htmlspecialchars($matchedOrder['order_number']) ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 11.5px; font-weight: 700; background: <?= $isCancelled ? '#FDF3F3' : '#EAF6ED' ?>; color: <?= $isCancelled ? '#B83838' : '#1E6B39' ?>; padding: 4px 10px; border-radius: 9999px;">
                                <?= htmlspecialchars($matchedOrder['order_status']) ?>
                            </span>
                            <div style="font-size: 12px; color: #697C70; margin-top: 4px;">Placed on <?= date('d M Y', strtotime($matchedOrder['created_at'])) ?></div>
                        </div>
                    </div>

                    <!-- Milestone Stepper -->
                    <?php if (!$isCancelled): ?>
                        <div class="stepper">
                            <div class="step-node">
                                <div class="step-dot completed">&check;</div>
                                <span class="step-title active">Confirmed</span>
                            </div>
                            <div class="step-node">
                                <div class="step-dot <?= $isFulfilled ? 'completed' : 'active' ?>">
                                    <?= $isFulfilled ? '&check;' : '2' ?>
                                </div>
                                <span class="step-title active">Packed</span>
                            </div>
                            <div class="step-node">
                                <div class="step-dot <?= $isFulfilled ? 'active' : '' ?>">3</div>
                                <span class="step-title <?= $isFulfilled ? 'active' : '' ?>">Dispatched</span>
                            </div>
                            <div class="step-node">
                                <div class="step-dot">4</div>
                                <span class="step-title">Delivered</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="background: #FDF3F3; padding: 14px; border-radius: 10px; color: #B83838; font-size: 13.5px; margin: 20px 0; text-align: center;">
                            This order was cancelled. Any captured payments have been scheduled for full refund.
                        </div>
                    <?php endif; ?>

                    <!-- Carrier Tracking Details -->
                    <?php if (!empty($matchedOrder['shipment'])): ?>
                        <div style="background: #FAF9F6; border: 1px solid #ECE7DE; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <span style="font-size: 11.5px; color: #697C70; text-transform: uppercase; font-weight: 600;">Carrier Partner</span>
                                    <div style="font-size: 14px; font-weight: 700; color: #14221A;"><?= htmlspecialchars($matchedOrder['shipment']['carrier']) ?></div>
                                    <div style="font-size: 12.5px; color: #1E5736; margin-top: 2px;">AWB: <?= htmlspecialchars($matchedOrder['shipment']['tracking_number']) ?></div>
                                </div>
                                <a href="<?= htmlspecialchars($matchedOrder['shipment']['tracking_url']) ?>" target="_blank" style="padding: 7px 14px; border-radius: 9999px; background: #143D24; color: #FFFFFF; font-size: 12px; font-weight: 600; text-decoration: none;">
                                    Carrier Live GPS &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Items Summary (Privacy-Scrubbed) -->
                    <div style="border-top: 1px dashed #ECE7DE; padding-top: 16px;">
                        <span style="font-size: 12.5px; font-weight: 600; color: #14221A; display: block; margin-bottom: 8px;">Order Items</span>
                        <ul style="list-style: none; display: flex; flex-direction: column; gap: 8px;">
                            <?php foreach ($matchedOrder['items'] as $it): ?>
                                <li style="display: flex; justify-content: space-between; font-size: 13px; color: #374A3F;">
                                    <span><?= $it['quantity'] ?>x <?= htmlspecialchars($it['title']) ?></span>
                                    <strong><?= Context::formatMoney((int)$it['total_amount']) ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>
