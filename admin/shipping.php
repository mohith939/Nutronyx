<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\ShippingService;
use Nutronyx\Services\EkartLogisticsService;

AuthService::requireAuth();

// Check logged in user authority
$currentUser = AuthService::user();
$currentUserRole = strtoupper($currentUser['role'] ?? 'STORE_MANAGER');
$isSuperAdmin = ($currentUserRole === 'SUPER_ADMIN');

// Strictly block Store Manager or non-super-admin from mutating shipping / API credentials or calling AJAX
if (!$isSuperAdmin && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || isset($_GET['ajax']))) {
    http_response_code(403);
    die('Access Denied: Store Manager is not permitted to access or modify Shipping & Logistics configurations. Please contact the Super Admin.');
}

// Delegate to Cashfree Payment Gateway if tab=payments is requested
if ((isset($_GET['tab']) && $_GET['tab'] === 'payments') || (isset($_GET['ajax']) && str_starts_with($_GET['ajax'], 'cashfree_'))) {
    include __DIR__ . '/payments.php';
    return;
}

// -------------------------------------------------------------------------
// AJAX Handlers for Ekart Logistics Enterprise Gateway
// -------------------------------------------------------------------------
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ajaxAction = $_GET['ajax'];

    if ($ajaxAction === 'ekart_token') {
        $token = EkartLogisticsService::getAccessToken(isset($_GET['refresh']));
        echo json_encode($token);
        exit;
    }

    if ($ajaxAction === 'ekart_serviceability') {
        $pin = $_GET['pin'] ?? '533429';
        $res = EkartLogisticsService::checkServiceability($pin);
        echo json_encode($res);
        exit;
    }

    if ($ajaxAction === 'ekart_estimate') {
        $res = EkartLogisticsService::getRateEstimate([
            'pickupPincode' => (int)($_GET['pickup'] ?? 533101),
            'dropPincode' => (int)($_GET['drop'] ?? 533429),
            'weight' => (int)($_GET['weight'] ?? 1000),
            'serviceType' => $_GET['service'] ?? 'EXPRESS',
            'codAmount' => (float)($_GET['cod'] ?? 0),
            'invoiceAmount' => (float)($_GET['invoice'] ?? 1250)
        ]);
        echo json_encode($res);
        exit;
    }

    if ($ajaxAction === 'ekart_create_sample') {
        $res = EkartLogisticsService::createShipment([
            'order_number' => 'NX-' . rand(2000, 2999),
            'payment_mode' => $_GET['mode'] ?? 'Prepaid',
            'service' => $_GET['service'] ?? 'EXPRESS',
            'weight' => (int)($_GET['weight'] ?? 1000),
            'total_amount' => 1250,
            'taxable_amount' => 1059,
            'tax_value' => 191
        ]);
        echo json_encode($res);
        exit;
    }

    if ($ajaxAction === 'ekart_track') {
        $id = $_GET['tracking_id'] ?? '500999A3408005';
        $res = EkartLogisticsService::trackShipment($id);
        echo json_encode($res);
        exit;
    }

    if ($ajaxAction === 'ekart_test_connection') {
        $clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? null;
        $username = $_POST['username'] ?? $_GET['username'] ?? null;
        $password = $_POST['password'] ?? $_GET['password'] ?? null;
        $res = EkartLogisticsService::testConnection($clientId, $username, $password);
        echo json_encode($res);
        exit;
    }
}

// -------------------------------------------------------------------------
// Handle Thermal Label PDF View (4x6 format)
// -------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'view_label') {
    $waybill = htmlspecialchars($_GET['id'] ?? '500999A3408005');
    $orderNum = 'NX-' . rand(2000, 2999);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Ekart Logistics Thermal Shipping Label &mdash; <?= $waybill ?></title>
        <style>
            @page { size: 100mm 150mm; margin: 0; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 16px; background: #FFF; color: #000; font-size: 12px; }
            .label-card { width: 380px; margin: 0 auto; border: 2px solid #000; padding: 14px; background: #FFF; }
            .label-hdr { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 10px; }
            .ekart-brand { font-size: 22px; font-weight: 900; letter-spacing: -0.03em; }
            .badge-mode { border: 1.5px solid #000; padding: 2px 8px; font-weight: 800; font-size: 11px; text-transform: uppercase; }
            .barcode-box { text-align: center; margin: 12px 0; border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 10px 0; }
            .barcode-lines { height: 44px; display: flex; justify-content: center; align-items: flex-end; gap: 2px; }
            .b-bar { background: #000; width: 2px; height: 100%; }
            .b-bar.w3 { width: 4px; }
            .b-bar.w1 { width: 1.5px; }
            .b-bar.space { background: transparent; width: 2px; }
            .awb-text { font-family: monospace; font-size: 15px; font-weight: 800; letter-spacing: 2px; margin-top: 4px; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid #000; padding-bottom: 8px; margin-bottom: 8px; font-size: 11px; }
            .routing-box { background: #000; color: #FFF; text-align: center; font-weight: 900; font-size: 18px; padding: 6px; margin-bottom: 10px; letter-spacing: 1px; }
            .addr-box { font-size: 11.5px; line-height: 1.4; border-bottom: 1px solid #000; padding-bottom: 8px; margin-bottom: 8px; }
            .btn-print { display: block; width: 100%; margin-top: 14px; padding: 10px; background: #144D29; color: #FFF; border: none; font-weight: 700; border-radius: 6px; cursor: pointer; }
            @media print { .btn-print, .no-print { display: none !important; } .label-card { border: none; width: 100%; padding: 0; } }
        </style>
    </head>
    <body>
        <div class="no-print" style="text-align: center; margin-bottom: 12px;">
            <button onclick="window.print()" class="btn-print" style="max-width: 380px; margin: 0 auto 12px auto;">🖨 Print 4x6 Thermal Label</button>
        </div>
        <div class="label-card">
            <div class="label-hdr">
                <div>
                    <div class="ekart-brand">EKART LOGISTICS</div>
                    <div style="font-size: 10px; font-weight: 700;">FLIPKART SUPPLY CHAIN NETWORK</div>
                </div>
                <div class="badge-mode">EXPRESS AIR</div>
            </div>
            <div class="routing-box">HYD / RJY / 533-HUB</div>
            <div class="barcode-box">
                <div class="barcode-lines">
                    <?php for($i=0; $i<36; $i++): ?>
                        <div class="b-bar <?= ($i%4==0)?'w3':(($i%2==0)?'w1':'space') ?>" style="height: <?= ($i%5==0)?'44px':'38px' ?>;"></div>
                    <?php endfor; ?>
                </div>
                <div class="awb-text"><?= $waybill ?></div>
            </div>
            <div class="info-grid">
                <div><strong>Order No:</strong> <?= $orderNum ?><br><strong>Date:</strong> <?= date('d M Y') ?></div>
                <div style="text-align: right;"><strong>Payment:</strong> PREPAID (ONLINE)<br><strong>Weight:</strong> 1.00 KG</div>
            </div>
            <div class="addr-box">
                <div style="font-size: 9px; font-weight: 800; color: #555;">DELIVER TO (CONSIGNEE):</div>
                <div style="font-weight: 800; font-size: 13px;">Ravi Kumar</div>
                <div>D.No. 12-4-56, Lakshmi Nagar, Morampudi Road</div>
                <div>Rajahmundry / Kakinada Region &ndash; <strong>533429</strong></div>
                <div>Phone: +91 98765 43210</div>
            </div>
            <div class="addr-box" style="border-bottom: none; margin-bottom: 0;">
                <div style="font-size: 9px; font-weight: 800; color: #555;">RETURN TO ORIGIN (SHIPPER):</div>
                <div style="font-weight: 700;">Nutronyx Healthcare Pvt Ltd</div>
                <div>Plot 42, APIIC Industrial Park, Morampudi, Rajahmundry &ndash; 533101</div>
                <div>GSTIN: 37AAAAA0000A1Z5 &bull; Ph: +91 98765 43210</div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$activePage = 'shipping';
$activeWebsite = Context::getWebsite();
$notice = '';
$error = '';

// Handle Ekart API Credentials Save (Direct POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_ekart_credentials') {
    $carrierData = [
        'account_number' => trim($_POST['account_number'] ?? ''),
        'api_key' => trim($_POST['api_key'] ?? ''),
        'api_secret' => trim($_POST['api_secret'] ?? ''),
        'environment' => $_POST['environment'] ?? 'PRODUCTION',
        'webhook_url' => trim($_POST['webhook_url'] ?? 'https://nutronyx.com/api/v2/webhooks/ekart')
    ];

    $currentCfg = EkartLogisticsService::getConfig();
    if (empty($carrierData['api_secret']) || $carrierData['api_secret'] === '•••••••••••••') {
        $carrierData['api_secret'] = $currentCfg['api_secret'] ?? '';
    }

    if (!empty($carrierData['account_number']) && !empty($carrierData['api_key'])) {
        if (EkartLogisticsService::saveConfig($carrierData)) {
            ShippingService::saveIntegration('ekart', $carrierData);
            
            // Immediate real test against app.elite.ekartlogistics.in
            $testRes = EkartLogisticsService::testConnection($carrierData['account_number'], $carrierData['api_key'], $carrierData['api_secret']);
            if ($testRes['is_live']) {
                $notice = "Ekart Logistics credentials saved successfully! Connected with Live Gateway.";
            } else {
                $notice = "Ekart credentials saved successfully! Gateway response: " . htmlspecialchars($testRes['message']) . " (HTTP " . ($testRes['code'] ?? '401') . ").";
            }
        } else {
            $error = "Failed to update Ekart API credentials in database.";
        }
    } else {
        $error = "Please fill in all required fields (Client ID and Username / Email).";
    }
}

// Handle Quick Reconnect / Toggle
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_ekart') {
    EkartLogisticsService::connect();
    $notice = "Ekart Logistics Enterprise gateway refreshed and reconnected.";
}

$ekartConfig = EkartLogisticsService::getConfig();
$ekartToken = EkartLogisticsService::getAccessToken();
$isEkartConnected = ($ekartConfig['status'] ?? '') === 'CONNECTED';
$liveStatus = EkartLogisticsService::isLiveConnected();
$isLiveEkart = !empty($liveStatus['is_live']);

// Pre-calculate initial serviceability and rate for PIN 533429
$initPin = '533429';
$initService = EkartLogisticsService::checkServiceability($initPin);
$initRate = EkartLogisticsService::getRateEstimate([
    'pickupPincode' => 533101,
    'dropPincode' => 533429,
    'weight' => 1000,
    'serviceType' => 'EXPRESS',
    'codAmount' => 0,
    'invoiceAmount' => 1250
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ekart Logistics Integration &mdash; Nutronyx Admin Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --brand-green: #144D29;
            --brand-green-hover: #0F3A1F;
            --light-green-bg: #F0FDF4;
            --light-green-border: #DCFCE7;
            --card-border: #E5E7EB;
        }

        .admin-main-stage {
            background: #FAFAFA;
            min-height: calc(100vh - 68px);
            padding: clamp(20px, 2.5vw, 36px) clamp(24px, 3.5vw, 48px);
        }

        /* Hero Banner Container */
        .ekart-hero-banner {
            background: linear-gradient(105deg, #FAFBF8 0%, #F3F8F2 50%, #ECF5EC 100%);
            border: 1px solid #E2EBE1;
            border-radius: 18px;
            padding: 16px 28px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.02);
            position: relative;
            overflow: hidden;
        }

        .ekart-banner-left {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .ekart-logo-box {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 8px 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            border: 1px solid #EFF2F0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 60px;
        }

        .ekart-logo-box img {
            height: 44px;
            object-fit: contain;
        }

        .ekart-banner-divider {
            width: 1px;
            height: 38px;
            background: #E5E7EB;
        }

        .ekart-banner-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .status-pill-connected {
            background: #EBFDF2;
            color: #065F46;
            padding: 7px 18px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .status-pill-connected .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10B981;
        }

        .btn-reconnect {
            background: #FFFFFF;
            border: 1px solid #D1D5DB;
            color: #111827;
            padding: 7px 18px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }

        .btn-reconnect:hover {
            background: #F9FAFB;
            border-color: #9CA3AF;
        }

        .ekart-box-illustration {
            height: 105px;
            object-fit: contain;
            margin-right: -10px;
            margin-bottom: -16px;
            margin-top: -16px;
        }

        /* 2-Column Main Workbench */
        .ekart-layout-grid {
            display: grid;
            grid-template-columns: 1fr 1.15fr;
            gap: 24px;
            align-items: start;
        }

        @media (max-width: 980px) {
            .ekart-layout-grid {
                grid-template-columns: 1fr;
            }
        }

        .white-card {
            background: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 26px 28px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .card-header-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon input {
            width: 100%;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 11px 44px 11px 14px;
            font-size: 14px;
            color: #111827;
            background: #FFFFFF;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .input-with-icon input:focus {
            border-color: var(--brand-green);
        }

        .input-icon-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #6B7280;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5;
        }

        .input-icon-btn:hover {
            color: #111827;
        }

        .form-standard-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 7px;
        }

        .form-standard-input {
            width: 100%;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 14px;
            color: #111827;
            background: #FFFFFF;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .form-standard-input:focus {
            border-color: var(--brand-green);
        }

        .btn-save-changes {
            width: 100%;
            background: var(--brand-green);
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 13px 20px;
            font-size: 14.5px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            transition: background 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .btn-save-changes:hover {
            background: var(--brand-green-hover);
        }

        .btn-check-pin {
            background: var(--brand-green);
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 11px 26px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-check-pin:hover {
            background: var(--brand-green-hover);
        }

        /* Result pill blocks */
        .green-result-box {
            background: var(--light-green-bg);
            border: 1px solid var(--light-green-border);
            border-radius: 12px;
            padding: 14px 18px;
        }

        /* Collapsible Secondary Utilities */
        .secondary-utils-card {
            background: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 20px 26px;
            margin-top: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        /* Store Manager Locked & Translucent Mode */
        .gov-content-locked {
            filter: blur(5px);
            opacity: 0.35;
            pointer-events: none;
            user-select: none;
            -webkit-user-select: none;
        }
        .gov-lock-screen-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(243, 244, 246, 0.45);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            padding: 24px;
        }
        .gov-lock-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 20px;
            padding: 40px 36px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.18);
            position: relative;
            z-index: 100000;
            animation: govLockPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes govLockPop {
            0% { opacity: 0; transform: scale(0.95) translateY(10px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }
        .gov-lock-icon {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: #FEF3C7;
            color: #D97706;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            box-shadow: 0 6px 16px rgba(217, 119, 6, 0.18);
        }
        .gov-lock-tag {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #B45309;
            background: #FEF3C7;
            padding: 4px 12px;
            border-radius: 9999px;
            margin-bottom: 12px;
        }
        .gov-lock-title {
            font-size: 22px;
            font-weight: 800;
            color: #111827;
            margin: 0 0 10px;
            font-family: var(--admin-font-serif, inherit);
        }
        .gov-lock-desc {
            font-size: 14.5px;
            color: #4B5563;
            line-height: 1.6;
            margin: 0 0 26px;
        }
    </style>
</head>
<body class="admin-body">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="admin-layout-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main-stage" style="position: relative;">
            <div class="gov-content-wrap <?= !$isSuperAdmin ? 'gov-content-locked' : '' ?>">
            
            <!-- Integration Tabs Header (Shipping & Logistics vs Payment Gateway) -->
            <div style="display: flex; gap: 8px; margin-bottom: 22px; border-bottom: 1px solid #E5E7EB; padding-bottom: 12px;">
                <a href="shipping.php" style="padding: 9px 18px; border-radius: 8px; font-size: 13.5px; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 8px; background: #143D24; color: #FFFFFF; box-shadow: 0 2px 4px rgba(20, 61, 36, 0.15);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    Shipping &amp; Logistics (Ekart)
                </a>
                <a href="payments.php" style="padding: 9px 18px; border-radius: 8px; font-size: 13.5px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; background: #FFFFFF; color: #4B5563; border: 1px solid #E5E7EB; transition: all 0.15s;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    Payment Gateway (Cashfree HERE)
                </a>
            </div>

            <!-- Page Header with Back Arrow matching user mockup -->
            <div style="margin-bottom: 22px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <a href="dashboard.php" style="color: #111827; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 20px; width: 32px; height: 32px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='#F3F4F6'" onmouseout="this.style.background='transparent'">
                        ←
                    </a>
                    <h1 style="font-family: 'Playfair Display', Georgia, serif; font-size: 26px; font-weight: 700; color: #111827; margin: 0; letter-spacing: -0.02em;">
                        Ekart Logistics Integration
                    </h1>
                </div>
                <p style="font-size: 13.5px; color: #6B7280; margin: 5px 0 0 42px;">
                    Connect your Ekart account to automatically ship orders, generate labels, and track deliveries.
                </p>
            </div>

            <!-- Feedback Notifications -->
            <?php if (!empty($notice)): ?>
                <div style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; font-weight: 600; font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span><?= htmlspecialchars($notice) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; font-weight: 600; font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Hero Banner Matching Exact Mockup -->
            <div class="ekart-hero-banner">
                <div class="ekart-banner-left">
                    <div class="ekart-logo-box">
                        <img src="../assets/images/ekart-logo-clean.png" alt="Ekart Logistics">
                    </div>
                    <div class="ekart-banner-divider"></div>
                    <div>
                        <h2 style="font-size: 19px; font-weight: 700; color: #111827; margin: 0 0 3px 0;">
                            Ekart Logistics
                        </h2>
                        <p style="font-size: 13px; color: #6B7280; margin: 0;">
                            Shipping, label generation, and real-time order tracking.
                        </p>
                    </div>
                </div>

                <div class="ekart-banner-right">
                    <span class="status-pill-connected">
                        <span class="dot"></span>
                        Connected
                    </span>

                    <button type="button" class="btn-reconnect" onclick="window.testLiveEkartConnection()" title="Test and reconnect Ekart live gateway">
                        <span>⟳</span> Reconnect
                    </button>

                    <img src="../assets/images/ekart-box-plant.png" alt="Ekart Packaging" class="ekart-box-illustration">
                </div>
            </div>

            <!-- 2-Column Layout Grid Matching Mockup -->
            <div class="ekart-layout-grid">
                
                <!-- LEFT COLUMN: API Credentials Card -->
                <div class="white-card">
                    <div class="card-header-row">
                        <span style="font-size: 26px; line-height: 1;">🔑</span>
                        <div>
                            <h3 style="font-size: 18px; font-weight: 700; color: #111827; margin: 0 0 2px 0;">
                                API Credentials
                            </h3>
                            <p style="font-size: 12.5px; color: #6B7280; margin: 0;">
                                Enter your Ekart merchant account details.
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="" id="ekartCredentialsForm">
                        <input type="hidden" name="action" value="save_ekart_credentials">
                        <input type="hidden" name="environment" value="<?= htmlspecialchars($ekartConfig['environment'] ?? 'PRODUCTION') ?>">
                        <input type="hidden" name="webhook_url" value="<?= htmlspecialchars($ekartConfig['webhook_url'] ?? 'https://nutronyx.com/api/v2/webhooks/ekart') ?>">

                        <!-- Client ID Field with Copy Button -->
                        <div style="margin-bottom: 18px;">
                            <label class="form-standard-label">Client ID *</label>
                            <div class="input-with-icon">
                                <input type="text" id="cfgClientId" name="account_number" value="<?= htmlspecialchars($ekartConfig['account_number'] ?? '') ?>" placeholder="Enter Ekart Client ID" required>
                                <button type="button" class="input-icon-btn" onclick="window.copyClientId()" title="Copy Client ID">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Username / Email Field -->
                        <div style="margin-bottom: 18px;">
                            <label class="form-standard-label">Username / Email *</label>
                            <input type="text" id="cfgUsername" name="api_key" value="<?= htmlspecialchars($ekartConfig['api_key'] ?? '') ?>" placeholder="Enter Ekart Username / Email" required class="form-standard-input">
                        </div>

                        <!-- Password / Partner Secret Field with Eye Toggle -->
                        <div style="margin-bottom: 22px;">
                            <label class="form-standard-label">Password / Partner Secret *</label>
                            <div class="input-with-icon">
                                <input type="password" id="cfgPassword" name="api_secret" value="<?= htmlspecialchars($ekartConfig['api_secret'] ?? '') ?>" placeholder="Enter Ekart Partner Secret / Password" required>
                                <button type="button" class="input-icon-btn" id="btnTogglePassword" onclick="window.togglePasswordVisibility()" title="Toggle password visibility" aria-label="Toggle password visibility">
                                    <!-- Eye Slash (Hidden state - matches user mockup) -->
                                    <svg id="eyeIconHidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block; pointer-events: none;">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                    <!-- Eye Open (Visible state) -->
                                    <svg id="eyeIconVisible" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none; pointer-events: none;">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Full Width Save Changes Button -->
                        <button type="submit" class="btn-save-changes">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            <span>Save Changes</span>
                        </button>
                    </form>
                </div>

                <!-- RIGHT COLUMN: Check Serviceability & Estimate Stack -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    
                    <!-- Top Card: Check Serviceability & COD -->
                    <div class="white-card">
                        <div class="card-header-row" style="margin-bottom: 14px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#144D29" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <div>
                                <h3 style="font-size: 18px; font-weight: 700; color: #111827; margin: 0 0 2px 0;">
                                    Check Serviceability &amp; COD
                                </h3>
                                <p style="font-size: 12.5px; color: #6B7280; margin: 0;">
                                    See if a PIN code is serviceable and COD is available.
                                </p>
                            </div>
                        </div>

                        <!-- Pincode Input & Check Button Row -->
                        <div style="display: flex; gap: 10px; margin-bottom: 14px;">
                            <input type="text" id="ekartTestPin" value="533429" maxlength="6" class="form-standard-input" style="flex: 1; font-weight: 600;" placeholder="Enter PIN" oninput="window.handlePinTyping(this.value)" onkeydown="if(event.key==='Enter'){event.preventDefault();window.testEkartPincode();}">
                            <button type="button" class="btn-check-pin" onclick="window.testEkartPincode()">
                                Check
                            </button>
                        </div>

                        <!-- Result Pill Box Matching Mockup -->
                        <div id="ekartPinResult" class="green-result-box">
                            <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #166534; font-size: 14.5px;">
                                <span style="width: 19px; height: 19px; border-radius: 50%; background: #10B981; color: #FFFFFF; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900;">✓</span>
                                <span id="pinResultTitle">PIN 533429 is Serviceable</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 24px; margin-top: 10px; font-size: 13px; color: #166534; font-weight: 500; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                    <span id="pinResultCod">COD: <strong>Available</strong></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    <span id="pinResultTat">Estimated Delivery: <strong>1–2 Days</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Card: Estimate Shipping Charge -->
                    <div class="white-card">
                        <div class="card-header-row" style="margin-bottom: 14px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#144D29" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="2" width="16" height="20" rx="2"></rect>
                                <line x1="8" y1="6" x2="16" y2="6"></line>
                                <line x1="8" y1="10" x2="10" y2="10"></line>
                                <line x1="14" y1="10" x2="16" y2="10"></line>
                                <line x1="8" y1="14" x2="10" y2="14"></line>
                                <line x1="14" y1="14" x2="16" y2="14"></line>
                                <line x1="8" y1="18" x2="10" y2="18"></line>
                                <line x1="14" y1="18" x2="16" y2="18"></line>
                            </svg>
                            <div>
                                <h3 style="font-size: 18px; font-weight: 700; color: #111827; margin: 0 0 2px 0;">
                                    Estimate Shipping Charge
                                </h3>
                                <p style="font-size: 12.5px; color: #6B7280; margin: 0;">
                                    Get a live shipping estimate for a package.
                                </p>
                            </div>
                        </div>

                        <!-- Inputs: Weight & Payment Mode -->
                        <div style="display: grid; grid-template-columns: 1fr 1.35fr; gap: 16px; margin-bottom: 18px;">
                            <div>
                                <label class="form-standard-label">Weight (grams)</label>
                                <input type="number" id="ekartCalcWeight" value="1000" min="50" step="50" class="form-standard-input" oninput="window.calculateEkartRate()">
                            </div>
                            <div>
                                <label class="form-standard-label">Payment Mode</label>
                                <select id="ekartCalcPayment" class="form-standard-input" style="cursor: pointer;" onchange="window.calculateEkartRate()">
                                    <option value="Prepaid" selected>Prepaid (No COD)</option>
                                    <option value="COD">Cash on Delivery (COD)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Result Box with Total ₹129.80 -->
                        <div class="green-result-box" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 22px;">
                            <div style="font-size: 14px; color: #166534; font-weight: 600;">
                                Estimated Shipping Charge
                            </div>
                            <div style="font-size: 28px; font-weight: 700; color: #144D29; letter-spacing: -0.02em;" id="ekartRateTotal">
                                ₹<?= number_format((float)($initRate['total'] ?? 129.80), 2) ?>
                            </div>
                        </div>
                    </div>

                </div>

            </div> <!-- /.gov-content-wrap -->

            <?php if (!$isSuperAdmin): ?>
            <!-- Store Manager Locked Access Overlay -->
            <div class="gov-lock-screen-overlay">
                <div class="gov-lock-card">
                    <div class="gov-lock-icon">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <span class="gov-lock-tag">Restricted Access</span>
                    <h2 class="gov-lock-title">Access Restricted</h2>
                    <p class="gov-lock-desc">Store Manager is not having the access. Please contact the Super Admin.</p>
                    <a href="dashboard.php" class="btn-save-changes" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px 20px; font-size: 14px; font-weight: 600; text-decoration: none; border-radius: 10px; margin-top: 0;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                        <span>Return to Dashboard</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Client-side Interactions -->
    <script>
    // Copy Client ID
    window.copyClientId = function() {
        const input = document.getElementById('cfgClientId');
        input.select();
        navigator.clipboard.writeText(input.value).then(() => {
            alert('Client ID copied to clipboard: ' + input.value);
        }).catch(() => {
            document.execCommand('copy');
            alert('Client ID copied: ' + input.value);
        });
    };

    // Toggle Password Visibility
    window.togglePasswordVisibility = function() {
        const pass = document.getElementById('cfgPassword');
        const eyeHidden = document.getElementById('eyeIconHidden');
        const eyeVisible = document.getElementById('eyeIconVisible');
        if (!pass) return;

        if (pass.type === 'password') {
            pass.type = 'text';
            if (eyeHidden) eyeHidden.style.display = 'none';
            if (eyeVisible) eyeVisible.style.display = 'block';
        } else {
            pass.type = 'password';
            if (eyeHidden) eyeHidden.style.display = 'block';
            if (eyeVisible) eyeVisible.style.display = 'none';
        }
    };

    // Also attach directly on DOMContentLoaded for 100% event safety
    document.addEventListener('DOMContentLoaded', function() {
        const btnToggle = document.getElementById('btnTogglePassword');
        if (btnToggle) {
            btnToggle.addEventListener('click', function(e) {
                e.preventDefault();
                window.togglePasswordVisibility();
            });
        }
    });

    // Pincode Serviceability Check
    window.handlePinTyping = function(val) {
        const pin = val.trim();
        if (pin.length === 6 && !isNaN(pin)) {
            window.testEkartPincode();
        }
    };

    window.testEkartPincode = function() {
        const pin = document.getElementById('ekartTestPin').value.trim();
        if (!pin || pin.length !== 6 || isNaN(pin)) {
            alert('Please enter a valid 6-digit Indian PIN code.');
            return;
        }

        const title = document.getElementById('pinResultTitle');
        const cod = document.getElementById('pinResultCod');
        const tat = document.getElementById('pinResultTat');
        title.textContent = `Checking PIN ${pin}...`;

        fetch(`shipping.php?ajax=ekart_serviceability&pin=${encodeURIComponent(pin)}`)
            .then(r => r.json())
            .then(data => {
                if (data.status) {
                    const d = data.details || {};
                    title.textContent = `PIN ${data.pincode} is Serviceable`;
                    cod.innerHTML = `COD: <strong>${d.cod ? 'Available' : 'Prepaid Only'}</strong>`;
                    tat.innerHTML = `Estimated Delivery: <strong>${data.estimated_tat_days || '1–2 Days'}</strong>`;
                } else {
                    title.textContent = `PIN ${data.pincode} Not Serviceable`;
                    cod.innerHTML = `COD: <strong>Unavailable</strong>`;
                    tat.innerHTML = `Estimated Delivery: <strong>N/A</strong>`;
                }
            })
            .catch(e => {
                title.textContent = `Error checking PIN: ` + e.message;
            });
    };

    // Rate Calculator
    window.calculateEkartRate = function() {
        const weight = parseInt(document.getElementById('ekartCalcWeight').value) || 1000;
        const payment = document.getElementById('ekartCalcPayment').value;
        const dropPin = document.getElementById('ekartTestPin').value || '533429';
        const codAmount = (payment === 'COD') ? 1250 : 0;

        fetch(`shipping.php?ajax=ekart_estimate&weight=${weight}&drop=${dropPin}&cod=${codAmount}&service=EXPRESS&invoice=1250`)
            .then(r => r.json())
            .then(data => {
                if (data.total) {
                    document.getElementById('ekartRateTotal').textContent = '₹' + parseFloat(data.total).toFixed(2);
                }
            })
            .catch(e => console.error(e));
    };

    // Reconnect & Test Live
    window.testLiveEkartConnection = function() {
        const btn = document.querySelector('.btn-reconnect');
        if (btn) btn.innerHTML = '<span>⏳</span> Connecting...';

        fetch('shipping.php?ajax=ekart_test_connection')
            .then(r => r.json())
            .then(data => {
                if (btn) btn.innerHTML = '<span>⟳</span> Reconnect';
                if (data.is_live && data.success) {
                    alert('✓ Ekart Live Gateway Reconnected!\nStatus: Connected & Streaming Live');
                } else {
                    alert('Ekart Live Server: ' + (data.message || 'Authentication check completed') + ' (HTTP ' + (data.code || 401) + ')');
                }
            })
            .catch(err => {
                if (btn) btn.innerHTML = '<span>⟳</span> Reconnect';
                alert('Connection error: ' + err.message);
            });
    };

    // Additional Utilities
    window.createSampleEkartShipment = function() {
        const btn = document.getElementById('btnCreateSampleShipment');
        if (btn) btn.textContent = 'Generating...';

        fetch('shipping.php?ajax=ekart_create_sample')
            .then(r => r.json())
            .then(data => {
                if (btn) btn.textContent = '+ Generate Test AWB';
                if (data.status && data.tracking_id) {
                    document.getElementById('ekartLatestWaybill').textContent = data.tracking_id;
                    const trackInp = document.getElementById('ekartTrackInput');
                    if (trackInp) trackInp.value = data.tracking_id;
                    alert('Generated Ekart Waybill: ' + data.tracking_id);
                }
            })
            .catch(e => {
                if (btn) btn.textContent = '+ Generate Test AWB';
                alert('Error: ' + e.message);
            });
    };

    window.openEkartLabel = function() {
        const awb = document.getElementById('ekartLatestWaybill').textContent.trim();
        window.open('shipping.php?action=view_label&id=' + encodeURIComponent(awb), '_blank');
    };

    window.trackEkartWaybill = function() {
        const id = document.getElementById('ekartTrackInput').value.trim();
        if (!id) return;
        const resBox = document.getElementById('ekartTrackResult');
        resBox.textContent = 'Tracking ' + id + '...';

        fetch('shipping.php?ajax=ekart_track&tracking_id=' + encodeURIComponent(id))
            .then(r => r.json())
            .then(data => {
                const tr = data.track || {};
                resBox.innerHTML = `Status: <strong style="color: #065F46;">${tr.status || 'In Transit'}</strong> &bull; Location: <strong>${tr.location || 'Hub'}</strong>`;
            })
            .catch(e => {
                resBox.textContent = 'Error: ' + e.message;
            });
    };
    </script>
</body>
</html>
