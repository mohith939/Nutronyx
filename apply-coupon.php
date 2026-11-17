<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

use Nutronyx\Services\PromotionService;
use Nutronyx\Context;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$code = $_GET['code'] ?? '';
$redirect = $_GET['redirect'] ?? 'shop.php';

// Format validation
if (empty($code)) {
    header('Location: shop.php');
    exit;
}

$validation = PromotionService::validateCoupon($code);

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($validation);
    exit;
}

if ($validation['valid']) {
    $_SESSION['applied_coupon'] = $validation['promo'];
    $_SESSION['coupon_banner'] = [
        'type' => 'success',
        'code' => $validation['promo']['code'],
        'message' => "Coupon '{$validation['promo']['code']}' has been auto-applied! You'll receive your discount at checkout."
    ];
} else {
    $_SESSION['coupon_banner'] = [
        'type' => 'error',
        'code' => htmlspecialchars($code),
        'message' => $validation['error'] ?? 'Coupon could not be applied.'
    ];
}

// Redirect to target or show confirmation page
$target = in_array($redirect, ['shop.php', 'index.php', 'track-order.php'], true) ? $redirect : 'shop.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applying Coupon &mdash; Nutronyx</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #FAF7F2;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .coupon-card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(20, 61, 36, 0.08);
            border: 1px solid #E6ECE8;
            max-width: 440px;
            width: 100%;
            padding: 32px 28px;
            text-align: center;
        }
        .badge-code {
            display: inline-block;
            background: #E8F5E9;
            color: #1B5E20;
            border: 1px dashed #2E6B42;
            padding: 8px 18px;
            font-size: 18px;
            font-weight: 700;
            border-radius: 8px;
            margin: 14px 0;
            letter-spacing: 1px;
        }
        .btn-shop {
            display: inline-block;
            background: #143D24;
            color: #FFFFFF;
            padding: 12px 28px;
            border-radius: 9999px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            margin-top: 20px;
            transition: transform 0.15s ease;
        }
        .btn-shop:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="coupon-card">
        <?php if ($validation['valid']): ?>
            <div style="font-size: 38px; margin-bottom: 10px;">🎉</div>
            <h2 style="margin: 0; color: #143D24; font-size: 22px;">Coupon Auto-Applied!</h2>
            <div class="badge-code"><?= htmlspecialchars(strtoupper($code)) ?></div>
            <p style="color: #586B5F; font-size: 14px; line-height: 1.5; margin: 0 0 10px 0;">
                Your promotional discount is locked in and will be automatically deducted from your order at checkout.
            </p>
            <a href="<?= htmlspecialchars($target) ?>" class="btn-shop">Start Shopping &rarr;</a>
        <?php else: ?>
            <div style="font-size: 38px; margin-bottom: 10px;">⚠️</div>
            <h2 style="margin: 0; color: #C53030; font-size: 22px;">Coupon Ineligible</h2>
            <div class="badge-code" style="background: #FFEBEE; color: #C62828; border-color: #E57373;"><?= htmlspecialchars(strtoupper($code)) ?></div>
            <p style="color: #586B5F; font-size: 14px; line-height: 1.5; margin: 0 0 10px 0;">
                <?= htmlspecialchars($validation['error']) ?>
            </p>
            <a href="<?= htmlspecialchars($target) ?>" class="btn-shop" style="background: #405549;">Continue to Store</a>
        <?php endif; ?>
    </div>
</body>
</html>
