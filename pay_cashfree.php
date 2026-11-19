<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Context;
use Nutronyx\Services\CartService;
use Nutronyx\Services\CashfreePaymentService;

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$db = Database::getConnection();
$websiteId = Context::getWebsiteId();

$orderNumber = trim((string)($_GET['order_id'] ?? $_POST['order_id'] ?? ''));
$sessionId = trim((string)($_GET['session_id'] ?? $_POST['session_id'] ?? ''));

if (empty($orderNumber)) {
    header('Location: cart.php');
    exit;
}

// Fetch order
$stmt = $db->prepare("SELECT * FROM orders WHERE order_number = :onum AND website_id = :wid LIMIT 1");
$stmt->execute([':onum' => $orderNumber, ':wid' => $websiteId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: cart.php?error=order_not_found');
    exit;
}

// If already paid, redirect to confirmation immediately
if ($order['payment_status'] === 'PAID') {
    $_SESSION['nutronyx_last_order'] = $order;
    header('Location: checkout.php?confirmed=' . urlencode($order['order_number']));
    exit;
}

$cfConfig = CashfreePaymentService::getConfig($websiteId);
$env = strtoupper($cfConfig['environment'] ?? 'PRODUCTION');
$isProduction = ($env === 'PRODUCTION');
$cfMode = $isProduction ? 'production' : 'sandbox';

$paymentReturnStatus = null;
$returnErrorMessage = null;

// 1. Handle Return from Cashfree Hosted Gateway
if (isset($_GET['cf_action']) && $_GET['cf_action'] === 'return') {
    $cfStatus = CashfreePaymentService::getOrderStatus($orderNumber, $websiteId);
    $orderStatus = strtoupper((string)($cfStatus['order_status'] ?? ''));

    if ($orderStatus === 'PAID') {
        $cfPaymentId = $cfStatus['cf_order_id'] ?? ('cf_pay_' . rand(10000000, 99999999));

        $updOrder = $db->prepare("
            UPDATE orders 
            SET payment_status = 'PAID',
                order_status = 'PROCESSING',
                payment_method = 'CASHFREE',
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $updOrder->execute([':id' => $order['id']]);

        $updCf = $db->prepare("
            UPDATE cashfree_transactions 
            SET status = 'SUCCESS',
                cf_payment_id = :cfpid 
            WHERE order_id = :onum AND website_id = :wid
        ");
        $updCf->execute([
            ':cfpid' => $cfPaymentId,
            ':onum' => $orderNumber,
            ':wid' => $websiteId
        ]);

        try {
            $txStmt = $db->prepare("
                INSERT INTO payment_transactions (
                    id, website_id, order_id, gateway, gateway_transaction_id, transaction_type,
                    amount, status, idempotency_key, created_at
                ) VALUES (
                    :id, :wid, :oid, 'cashfree', :gtxid, 'CHARGE',
                    :amt, 'SUCCESS', :idem, CURRENT_TIMESTAMP
                )
            ");
            $txStmt->execute([
                ':id' => 'tx_' . bin2hex(random_bytes(8)),
                ':wid' => $websiteId,
                ':oid' => $order['id'],
                ':gtxid' => $cfPaymentId,
                ':amt' => (int)$order['grand_total_amount'],
                ':idem' => 'idem_' . md5($order['id'] . microtime())
            ]);
        } catch (\Throwable $t) {}

        try {
            $tlStmt = $db->prepare("
                INSERT INTO order_timeline (id, website_id, order_id, event_type, message, actor, created_at)
                VALUES (:id, :wid, :oid, 'PAYMENT_RECEIVED', :msg, 'Cashfree PG', CURRENT_TIMESTAMP)
            ");
            $tlStmt->execute([
                ':id' => 'evt_' . bin2hex(random_bytes(8)),
                ':wid' => $websiteId,
                ':oid' => $order['id'],
                ':msg' => 'Cashfree verified payment of ₹' . number_format((float)$order['grand_total_amount'] / 100, 2) . ' received (Ref: ' . $cfPaymentId . ').'
            ]);
        } catch (\Throwable $t) {}

        CartService::clear();

        $stmt->execute([':onum' => $orderNumber, ':wid' => $websiteId]);
        $updatedOrder = $stmt->fetch(PDO::FETCH_ASSOC);
        $iStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = :oid");
        $iStmt->execute([':oid' => $order['id']]);
        $updatedOrder['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        $_SESSION['nutronyx_last_order'] = $updatedOrder;

        header('Location: checkout.php?confirmed=' . urlencode($orderNumber));
        exit;
    } else {
        $paymentReturnStatus = $orderStatus ?: 'INCOMPLETE';
        $returnErrorMessage = 'Cashfree payment was not completed (Status: ' . htmlspecialchars($paymentReturnStatus) . '). You may retry securely below.';
    }
}

// 2. Fetch existing session or generate a fresh session via Cashfree API
if (empty($sessionId)) {
    $txStmt = $db->prepare("
        SELECT payment_session_id 
        FROM cashfree_transactions 
        WHERE order_id = :onum AND website_id = :wid 
        ORDER BY CASE WHEN payment_session_id NOT LIKE 'session_cf_%' THEN 1 ELSE 2 END, created_at DESC 
        LIMIT 1
    ");
    $txStmt->execute([':onum' => $orderNumber, ':wid' => $websiteId]);
    $existingSession = (string)($txStmt->fetchColumn() ?: '');
    if (!empty($existingSession)) {
        $sessionId = $existingSession;
    }
}

if (empty($sessionId) || isset($_GET['retry'])) {
    $amountRupees = (float)($order['grand_total_amount'] / 100);
    $cfRes = CashfreePaymentService::createOrder([
        'order_id' => $order['order_number'],
        'order_amount' => $amountRupees,
        'order_currency' => 'INR',
        'customer_details' => [
            'customer_id' => $order['customer_id'] ?? ('cust_' . rand(1000, 9999)),
            'customer_phone' => $order['shipping_phone'] ?? '9876543210',
            'customer_email' => $order['customer_email'] ?? 'customer@nutronyx.com',
            'customer_name' => $order['shipping_name'] ?? 'Nutronyx Patron'
        ]
    ], $websiteId);

    if (!empty($cfRes['payment_session_id'])) {
        $sessionId = $cfRes['payment_session_id'];
    } elseif (!empty($cfRes['api_error'])) {
        $returnErrorMessage = 'Cashfree Gateway Response: ' . $cfRes['api_error'];
    }
}

// Cashfree Hosted URL fallback
$hostedCheckoutBase = $isProduction ? 'https://payments.cashfree.com/order/#' : 'https://payments-test.cashfree.com/order/#';
$hostedCheckoutUrl = !empty($sessionId) ? ($hostedCheckoutBase . urlencode($sessionId)) : '#';

$amountRupees = (float)($order['grand_total_amount'] / 100);
$isRealCashfreeSession = !empty($sessionId) && !str_starts_with($sessionId, 'session_cf_sandbox_') && !str_starts_with($sessionId, 'session_cf_production_');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NUTRONYX</title>
    
    <!-- Official Cashfree JS SDK v3 -->
    <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --botanical-deep: #0D2818;
            --botanical-forest: #143D24;
            --botanical-sage: #2D6A4F;
            --botanical-gold: #C5A059;
            --cashfree-purple: #5A278B;
            --cashfree-hover: #43196A;
            --bg-ivory: #FAF7F2;
            --text-dark: #1A1A1A;
            --text-muted: #556459;
            --border-soft: #E5DFD5;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: var(--bg-ivory);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Top Header */
        .cf-checkout-header {
            background: #FFFFFF;
            border-bottom: 1px solid var(--border-soft);
            padding: 16px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-cluster {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .nutronyx-wordmark {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 2px;
            color: var(--botanical-forest);
            text-transform: uppercase;
        }

        .header-sep {
            width: 1px;
            height: 22px;
            background: #D8D2C5;
        }

        .cf-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #F3E8FF;
            color: var(--cashfree-purple);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
        }

        .security-indicator {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #0F5132;
            background: #E8F5E9;
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid #C8E6C9;
        }

        /* Main Container */
        .checkout-main-wrap {
            max-width: 680px;
            margin: 40px auto;
            width: 92%;
            background: #FFFFFF;
            border-radius: 20px;
            box-shadow: 0 12px 36px rgba(13, 40, 24, 0.06);
            border: 1px solid var(--border-soft);
            overflow: hidden;
            text-align: center;
            padding: 40px 32px;
        }

        /* Status Icon & Spinners */
        .spinner-ring {
            width: 56px;
            height: 56px;
            border: 4px solid #E9D5FF;
            border-top-color: var(--cashfree-purple);
            border-radius: 50%;
            animation: cfSpin 0.9s cubic-bezier(0.55, 0.15, 0.45, 0.85) infinite;
            margin: 0 auto 24px;
        }

        @keyframes cfSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .checkout-title {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--botanical-forest);
            margin-bottom: 8px;
        }

        .checkout-subtitle {
            font-size: 14.5px;
            color: var(--text-muted);
            line-height: 1.5;
            max-width: 480px;
            margin: 0 auto 24px;
        }

        /* Order Summary Card */
        .order-summary-box {
            background: #FAF8F5;
            border: 1px solid #EAE4D8;
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 28px;
            text-align: left;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            font-size: 13.5px;
            color: #4A5568;
            border-bottom: 1px dashed #E2DACB;
        }

        .summary-row:last-child {
            border-bottom: none;
            padding-top: 12px;
            margin-top: 4px;
        }

        .summary-label {
            color: #718096;
            font-weight: 500;
        }

        .summary-val {
            font-weight: 600;
            color: #1A202C;
        }

        .summary-total {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--botanical-forest);
        }

        /* Buttons */
        .btn-cf-proceed {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 16px 24px;
            background: var(--cashfree-purple);
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(90, 39, 139, 0.25);
        }

        .btn-cf-proceed:hover {
            background: var(--cashfree-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(90, 39, 139, 0.35);
        }

        .btn-cf-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            background: transparent;
            color: #4A5568;
            border: 1px solid #CBD5E0;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            margin-top: 14px;
            transition: all 0.2s;
        }

        .btn-cf-secondary:hover {
            background: #F7FAFC;
            color: #1A202C;
            border-color: #A0AEC0;
        }

        /* Payment Icons Bar */
        .pg-payment-methods {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #EDF2F7;
            font-size: 12px;
            color: #718096;
            font-weight: 600;
        }

        .method-pill {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 4px 10px;
            color: #475569;
        }

        /* Error/Notice Alert */
        .notice-alert {
            background: #FFFBEB;
            border: 1px solid #FCD34D;
            border-radius: 12px;
            padding: 16px;
            color: #92400E;
            font-size: 13.5px;
            line-height: 1.5;
            margin-bottom: 22px;
            text-align: left;
        }

        /* Footer */
        .cf-footer {
            text-align: center;
            padding: 24px;
            font-size: 12px;
            color: #8C9990;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="cf-checkout-header">
        <div class="brand-cluster">
            <span class="nutronyx-wordmark">Nutronyx</span>
            <span class="header-sep"></span>
            <span class="cf-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9v-2h2v2zm0-4H9V7h2v5z"/></svg>
                Cashfree PG
            </span>
        </div>

        <div class="security-indicator">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            256-Bit SSL Encrypted
        </div>
    </header>

    <!-- Main Payment Screen -->
    <main>
        <div class="checkout-main-wrap">
            
            <?php if ($returnErrorMessage): ?>
                <div class="notice-alert">
                    <strong>Payment Update:</strong> <?= htmlspecialchars($returnErrorMessage) ?>
                </div>
            <?php endif; ?>

            <!-- Loading Spinner -->
            <div class="spinner-ring" id="cfSpinner"></div>

            <h1 class="checkout-title" id="cfTitle">Connecting to Cashfree Secure Checkout</h1>
            <p class="checkout-subtitle" id="cfSub">
                Transferring your order safely to the official Cashfree Payment Gateway. Please do not refresh or close this tab.
            </p>

            <!-- Order Summary -->
            <div class="order-summary-box">
                <div class="summary-row">
                    <span class="summary-label">Order Number</span>
                    <span class="summary-val">#<?= htmlspecialchars($order['order_number']) ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Recipient</span>
                    <span class="summary-val"><?= htmlspecialchars($order['shipping_name'] ?? 'Patron') ?> &bull; <?= htmlspecialchars($order['shipping_phone'] ?? '') ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Gateway Mode</span>
                    <span class="summary-val" style="color:<?= $isProduction ? '#175C31' : '#D97706' ?>; font-weight:700;">
                        Cashfree <?= htmlspecialchars($env) ?>
                    </span>
                </div>
                <div class="summary-row">
                    <span class="summary-label" style="font-size:15px; font-weight:700; color:#1A202C;">Total Payable</span>
                    <span class="summary-total">₹<?= number_format($amountRupees, 2) ?></span>
                </div>
            </div>

            <!-- Direct Action Button -->
            <a href="<?= htmlspecialchars($hostedCheckoutUrl) ?>" id="cfActionBtn" class="btn-cf-proceed">
                <span>Proceed to Cashfree Checkout</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </a>

            <div>
                <a href="cart.php" class="btn-cf-secondary">
                    <span>&larr;</span> Cancel and Return to Cart
                </a>
            </div>

            <!-- Accepted Payment Channels -->
            <div class="pg-payment-methods">
                <span>Accepted via Cashfree:</span>
                <span class="method-pill">UPI / QR</span>
                <span class="method-pill">GPay &bull; PhonePe &bull; Paytm</span>
                <span class="method-pill">Debit / Credit Cards</span>
                <span class="method-pill">Net Banking</span>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="cf-footer">
        Nutronyx Botanical Wellness &bull; Secured by Cashfree Payments India Pvt Ltd &bull; PCI-DSS Level 1 Compliant
    </footer>

    <script>
    const cfMode = "<?= $cfMode ?>";
    const paymentSessionId = "<?= htmlspecialchars($sessionId) ?>";
    const isRealSession = <?= $isRealCashfreeSession ? 'true' : 'false' ?>;

    function launchCashfreeCheckout() {
        if (!paymentSessionId) {
            console.warn('Waiting for payment session ID...');
            return;
        }

        try {
            if (typeof Cashfree !== 'function') {
                console.warn('Cashfree JS SDK still loading...');
                setTimeout(launchCashfreeCheckout, 300);
                return;
            }

            const cashfree = Cashfree({ mode: cfMode });

            // Launch official Cashfree checkout with full-window redirect
            cashfree.checkout({
                paymentSessionId: paymentSessionId,
                redirectTarget: "_self"
            });
        } catch (err) {
            console.error('Cashfree SDK invocation error:', err);
            // Fallback direct link
            const actionBtn = document.getElementById('cfActionBtn');
            if (actionBtn && paymentSessionId) {
                const targetBase = (cfMode === 'production') 
                    ? 'https://payments.cashfree.com/order/#' 
                    : 'https://payments-test.cashfree.com/order/#';
                actionBtn.href = targetBase + encodeURIComponent(paymentSessionId);
            }
        }
    }

    // Auto-launch checkout upon page load
    document.addEventListener('DOMContentLoaded', () => {
        if (paymentSessionId) {
            // Initiate redirect
            setTimeout(launchCashfreeCheckout, 150);
        }
    });
    </script>
</body>
</html>
