<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\CashfreePaymentService;

AuthService::requireAuth();

// Check logged in user authority
$currentUser = AuthService::user();
$currentUserRole = strtoupper($currentUser['role'] ?? 'STORE_MANAGER');
$isSuperAdmin = ($currentUserRole === 'SUPER_ADMIN');

// Strictly block Store Manager or non-super-admin from mutating payments / API credentials or calling AJAX
if (!$isSuperAdmin && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || isset($_GET['ajax']))) {
    http_response_code(403);
    die('Access Denied: Store Manager is not permitted to access or modify Payment Gateway configurations. Please contact the Super Admin.');
}

// -------------------------------------------------------------------------
// AJAX Handlers for Cashfree Payment Gateway & Cashfree HERE
// -------------------------------------------------------------------------
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ajaxAction = $_GET['ajax'];

    if ($ajaxAction === 'cashfree_test_connection') {
        $appId = $_POST['app_id'] ?? $_GET['app_id'] ?? '';
        $secretKey = $_POST['secret_key'] ?? $_GET['secret_key'] ?? '';
        $env = $_POST['environment'] ?? $_GET['environment'] ?? 'SANDBOX';
        $res = CashfreePaymentService::testConnection($appId, $secretKey, $env);
        echo json_encode($res);
        exit;
    }

    if ($ajaxAction === 'cashfree_create_test_order') {
        $amount = (float)($_POST['amount'] ?? $_GET['amount'] ?? 1499.00);
        $method = $_POST['method'] ?? $_GET['method'] ?? 'UPI';
        $bankCode = $_POST['bank_code'] ?? $_GET['bank_code'] ?? null;
        $isMcp = !empty($_POST['is_mcp']) || !empty($_GET['is_mcp']);

        $res = CashfreePaymentService::createOrder([
            'order_amount' => $amount,
            'payment_method' => $method,
            'bank_code' => $bankCode,
            'is_mcp' => $isMcp,
            'customer_details' => [
                'customer_id' => 'cust_' . rand(1000, 9999),
                'customer_name' => 'Nutronyx Shopper',
                'customer_phone' => '9876543210',
                'customer_email' => 'shopper@nutronyx.com'
            ]
        ]);
        echo json_encode($res);
        exit;
    }
}

$activePage = 'payments';
$activeWebsite = Context::getWebsite();
$notice = '';
$error = '';

// Handle Direct POST to Save Cashfree Credentials
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_cashfree_credentials') {
    $cfgData = [
        'app_id' => trim($_POST['app_id'] ?? ''),
        'secret_key' => trim($_POST['secret_key'] ?? ''),
        'environment' => $_POST['environment'] ?? 'SANDBOX',
        'webhook_secret' => trim($_POST['webhook_secret'] ?? ''),
        'webhook_url' => trim($_POST['webhook_url'] ?? 'https://nutronyx.com/api/v2/webhooks/cashfree'),
        'enable_upi' => isset($_POST['enable_upi']),
        'enable_cards' => isset($_POST['enable_cards']),
        'enable_netbanking' => isset($_POST['enable_netbanking']),
        'enable_mcp_apps' => isset($_POST['enable_mcp_apps']),
        'status' => 'CONNECTED'
    ];

    if (!empty($cfgData['app_id']) && !empty($cfgData['secret_key'])) {
        if (CashfreePaymentService::saveConfig(Context::getWebsiteId(), $cfgData)) {
            $testRes = CashfreePaymentService::testConnection($cfgData['app_id'], $cfgData['secret_key'], $cfgData['environment']);
            if ($testRes['success']) {
                $notice = "Cashfree API credentials saved successfully! Connection verified with Cashfree {$cfgData['environment']}.";
            } else {
                $notice = "Credentials saved. Gateway notice: " . htmlspecialchars($testRes['message']);
            }
        } else {
            $error = "Failed to save Cashfree API credentials.";
        }
    } else {
        $error = "Please fill in both Cashfree App ID and Secret Key.";
    }
}

$cfConfig = CashfreePaymentService::getConfig();
$transactions = CashfreePaymentService::getTransactions(Context::getWebsiteId(), 8);
$isSandbox = ($cfConfig['environment'] ?? 'SANDBOX') === 'SANDBOX';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashfree Payment Gateway Integration &mdash; Nutronyx Admin Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=20261119_white">
    <style>
        :root {
            --admin-forest: #143D24;
            --admin-sage: #2E6B42;
            --cf-purple: #6D28D9;
            --cf-purple-light: #F5F3FF;
            --card-border: #E5E7EB;
        }

        body.admin-body {
            background-color: #F9FAFB !important;
            color: #111827;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
        }

        .hero-banner {
            background: linear-gradient(135deg, #FAF5FF 0%, #F5F3FF 50%, #ECFDF5 100%);
            border: 1px solid #DDD6FE;
            border-radius: 16px;
            padding: 22px 28px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 2px 6px rgba(109, 40, 217, 0.04);
        }

        .hero-left {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .brand-badge {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: #6D28D9;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 22px;
            box-shadow: 0 4px 12px rgba(109, 40, 217, 0.25);
        }

        .status-pill-connected {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
            border-radius: 9999px;
            padding: 5px 14px;
            font-size: 12.5px;
            font-weight: 700;
        }

        .status-pill-connected .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        .white-card {
            background: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px 26px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            margin-bottom: 22px;
        }

        .form-standard-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 7px;
        }

        .form-standard-input {
            width: 100%;
            box-sizing: border-box;
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13.5px;
            color: #111827;
            font-family: inherit;
            outline: none;
            transition: all 0.2s;
        }
        .form-standard-input:focus {
            background: #FFFFFF;
            border-color: #6D28D9;
            box-shadow: 0 0 0 3px rgba(109, 40, 217, 0.1);
        }

        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-with-icon input {
            width: 100%;
            box-sizing: border-box;
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 10px 44px 10px 14px;
            font-size: 13.5px;
            color: #111827;
            font-family: inherit;
            outline: none;
            transition: all 0.2s;
        }
        .input-with-icon input:focus {
            background: #FFFFFF;
            border-color: #6D28D9;
            box-shadow: 0 0 0 3px rgba(109, 40, 217, 0.1);
        }
        .input-icon-btn {
            position: absolute;
            right: 8px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            color: #6B7280;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.15s;
        }
        .input-icon-btn:hover {
            color: #111827;
            background: #F3F4F6;
        }

        .btn-save-changes {
            width: 100%;
            background: #143D24;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            margin-top: 10px;
        }
        .btn-save-changes:hover {
            background: #0E2B1A;
            transform: translateY(-1px);
        }

        .btn-test-action {
            background: #FFFFFF;
            color: #6D28D9;
            border: 1px solid #DDD6FE;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-test-action:hover {
            background: #F5F3FF;
            border-color: #C4B5FD;
        }

        .env-radio-group {
            display: flex;
            gap: 14px;
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            padding: 8px 14px;
            border-radius: 8px;
        }
        .env-radio-option {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            color: #374151;
        }

        .sim-tab-bar {
            display: flex;
            border-bottom: 1px solid #E5E7EB;
            margin-bottom: 16px;
            gap: 4px;
        }
        .sim-tab-btn {
            background: none;
            border: none;
            padding: 8px 14px;
            font-size: 12.5px;
            font-weight: 600;
            color: #6B7280;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: all 0.2s;
        }
        .sim-tab-btn.active {
            color: #6D28D9;
            border-bottom-color: #6D28D9;
        }

        .bank-badge-btn {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            text-align: center;
            transition: all 0.15s;
        }
        .bank-badge-btn:hover {
            border-color: #6D28D9;
            background: #F5F3FF;
            color: #6D28D9;
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
                <a href="shipping.php" style="padding: 9px 18px; border-radius: 8px; font-size: 13.5px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; background: #FFFFFF; color: #4B5563; border: 1px solid #E5E7EB; transition: all 0.15s;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    Shipping &amp; Logistics (Ekart)
                </a>
                <a href="payments.php" style="padding: 9px 18px; border-radius: 8px; font-size: 13.5px; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 8px; background: #143D24; color: #FFFFFF; box-shadow: 0 2px 4px rgba(20, 61, 36, 0.15);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    Payment Gateway (Cashfree HERE)
                </a>
            </div>

            <!-- Page Title Section -->
            <div style="margin-bottom: 22px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h1 style="font-family: 'Playfair Display', Georgia, serif; font-size: 26px; font-weight: 700; color: #111827; margin: 0; letter-spacing: -0.02em;">
                        Cashfree Payments Gateway Integration
                    </h1>
                </div>
                <p style="font-size: 13.5px; color: #6B7280; margin: 5px 0 0 0;">
                    Configure Cashfree payment gateway live API credentials, UPI, cards, net banking, and webhook verification.
                </p>
            </div>

            <!-- Notifications -->
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

            <!-- Hero Banner -->
            <div class="hero-banner">
                <div class="hero-left">
                    <div class="brand-badge">CF</div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <h2 style="font-size: 19px; font-weight: 700; color: #111827; margin: 0;">
                                Cashfree Payments
                            </h2>
                            <span style="background: #EDE9FE; color: #6D28D9; border: 1px solid #DDD6FE; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px;">
                                MCP Apps Certified
                            </span>
                        </div>
                        <p style="font-size: 13px; color: #6B7280; margin: 4px 0 0 0;">
                            Unified UPI Intent, Dynamic QR, Cards &amp; Net Banking for Seamless D2C Checkout.
                        </p>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                    <span class="status-pill-connected">
                        <span class="dot"></span>
                        LIVE PRODUCTION GATEWAY
                    </span>
                </div>
            </div>

            <!-- 2-Column Responsive Layout -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 24px;">
                
                <!-- LEFT COLUMN: API Credentials & Features -->
                <div>
                    <!-- API Credentials Card -->
                    <div class="white-card">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 18px; border-bottom: 1px solid #F3F4F6; padding-bottom: 14px;">
                            <span style="font-size: 24px;">🔑</span>
                            <div>
                                <h3 style="font-size: 17px; font-weight: 700; color: #111827; margin: 0;">
                                    Cashfree API Credentials
                                </h3>
                                <p style="font-size: 12.5px; color: #6B7280; margin: 2px 0 0 0;">
                                    Enter your credentials from the Cashfree Merchant Dashboard.
                                </p>
                            </div>
                        </div>

                        <form method="POST" action="payments.php" id="cashfreeForm">
                            <input type="hidden" name="action" value="save_cashfree_credentials">

                            <!-- Environment -->
                            <div style="margin-bottom: 18px;">
                                <label class="form-standard-label">Payment Gateway Mode *</label>
                                <div style="display: flex; align-items: center; gap: 10px; background: #FAF9F6; border: 1px solid #E5E7EB; border-radius: 8px; padding: 10px 14px;">
                                    <span style="width: 8px; height: 8px; border-radius: 50%; background: #059669; display: inline-block;"></span>
                                    <span style="font-size: 13px; font-weight: 700; color: #111827;">Live Production Mode</span>
                                    <input type="hidden" name="environment" value="PRODUCTION">
                                </div>
                            </div>

                            <!-- App ID / Client ID -->
                            <div style="margin-bottom: 18px;">
                                <label class="form-standard-label">Cashfree App ID (Client ID) *</label>
                                <div class="input-with-icon">
                                    <input type="text" id="cfAppId" name="app_id" value="<?= htmlspecialchars($cfConfig['app_id']) ?>" placeholder="e.g. CF100982348271" required>
                                    <button type="button" class="input-icon-btn" onclick="window.copyToClipboard('cfAppId')" title="Copy App ID">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Secret Key -->
                            <div style="margin-bottom: 18px;">
                                <label class="form-standard-label">Cashfree Secret Key *</label>
                                <div class="input-with-icon">
                                    <input type="password" id="cfSecretKey" name="secret_key" value="<?= htmlspecialchars($cfConfig['secret_key']) ?>" placeholder="cfsk_ma_..." required>
                                    <button type="button" class="input-icon-btn" onclick="window.toggleSecretVisibility()" title="Toggle secret visibility">
                                        <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Webhook Secret -->
                            <div style="margin-bottom: 18px;">
                                <label class="form-standard-label">Webhook Secret (for Signature Verification)</label>
                                <input type="text" name="webhook_secret" class="form-standard-input" value="<?= htmlspecialchars($cfConfig['webhook_secret'] ?? '') ?>" placeholder="whsec_...">
                            </div>

                            <!-- Webhook URL (Copyable) -->
                            <div style="margin-bottom: 22px;">
                                <label class="form-standard-label">Webhook Endpoint URL</label>
                                <div class="input-with-icon">
                                    <input type="text" id="cfWebhookUrl" name="webhook_url" value="<?= htmlspecialchars($cfConfig['webhook_url']) ?>" readonly>
                                    <button type="button" class="input-icon-btn" onclick="window.copyToClipboard('cfWebhookUrl')" title="Copy Webhook URL">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Payment Features Checkboxes -->
                            <div style="background: #FAF9F6; border: 1px solid #E5E7EB; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px;">
                                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #6B7280; display: block; margin-bottom: 10px;">
                                    Enabled Checkout Methods
                                </span>
                                <div style="display: flex; flex-direction: column; gap: 9px;">
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #1F2937; cursor: pointer;">
                                        <input type="checkbox" name="enable_upi" <?= !empty($cfConfig['enable_upi']) ? 'checked' : '' ?>>
                                        <span>UPI Intent &amp; Dynamic QR Code (GPay, PhonePe, Paytm, BHIM)</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #1F2937; cursor: pointer;">
                                        <input type="checkbox" name="enable_cards" <?= !empty($cfConfig['enable_cards']) ? 'checked' : '' ?>>
                                        <span>Credit &amp; Debit Cards (PCI-DSS Instrument Vault)</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #1F2937; cursor: pointer;">
                                        <input type="checkbox" name="enable_netbanking" <?= !empty($cfConfig['enable_netbanking']) ? 'checked' : '' ?>>
                                        <span>Net Banking (80+ Indian Banks)</span>
                                    </label>

                                </div>
                            </div>

                            <button type="submit" class="btn-save-changes">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                                <span>Save Gateway Settings</span>
                            </button>
                        </form>
                    </div>

                </div>

                <!-- RIGHT COLUMN: Recent Transactions -->
                <div>
                    <!-- Recent Transactions Ledger -->
                    <div class="white-card">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; border-bottom: 1px solid #F3F4F6; padding-bottom: 10px;">
                            <h3 style="font-size: 16px; font-weight: 700; color: #111827; margin: 0;">
                                Recent Cashfree Transactions
                            </h3>
                            <span style="font-size: 12px; color: #6B7280;"><?= count($transactions) ?> logged</span>
                        </div>

                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; text-align: left;">
                                <thead>
                                    <tr style="border-bottom: 1px solid #E5E7EB; color: #6B7280;">
                                        <th style="padding: 6px 8px;">Order</th>
                                        <th style="padding: 6px 8px;">Amount</th>
                                        <th style="padding: 6px 8px;">Method</th>
                                        <th style="padding: 6px 8px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr style="border-bottom: 1px solid #F3F4F6;">
                                            <td style="padding: 8px; font-weight: 700; color: #111827;">
                                                <?= htmlspecialchars((string)($tx['order_id'] ?? '')) ?>
                                                <?php if (!empty($tx['mcp_invoked'])): ?>
                                                    <span style="font-size: 9.5px; background: #EDE9FE; color: #6D28D9; padding: 1px 5px; border-radius: 4px; margin-left: 4px;">AI</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 8px; font-weight: 600;">
                                                ₹<?= number_format((float)($tx['amount'] ?? 0), 2) ?>
                                            </td>
                                            <td style="padding: 8px; color: #4B5563;">
                                                <?= htmlspecialchars((string)($tx['payment_method'] ?? 'UPI')) ?>
                                                <?php if (!empty($tx['bank_code'])): ?>
                                                    <span style="font-size: 10px; color: #6B7280;">(<?= htmlspecialchars((string)$tx['bank_code']) ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 8px;">
                                                <span style="background: #D1FAE5; color: #065F46; font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 99px;">
                                                    <?= htmlspecialchars((string)($tx['status'] ?? 'SUCCESS')) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
                    <a href="dashboard.php" class="btn-save-changes" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px 20px; font-size: 14px; font-weight: 600; text-decoration: none; border-radius: 10px; margin-top: 0; background: #143D24; color: #FFFFFF;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                        <span>Return to Dashboard</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Client-Side Interactivity -->
    <script>
        function copyToClipboard(elementId) {
            const el = document.getElementById(elementId);
            if (!el) return;
            navigator.clipboard.writeText(el.value).then(() => {
                alert('Copied to clipboard: ' + el.value);
            }).catch(() => {
                el.select();
                document.execCommand('copy');
                alert('Copied to clipboard!');
            });
        }

        function toggleSecretVisibility() {
            const inp = document.getElementById('cfSecretKey');
            if (inp.type === 'password') {
                inp.type = 'text';
            } else {
                inp.type = 'password';
            }
        }

        function updateEnvNotice(env) {
            console.log('Switched env to ' + env);
        }

    </script>
</body>
</html>
