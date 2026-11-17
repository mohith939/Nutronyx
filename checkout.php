<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

use Nutronyx\Services\CartService;
use Nutronyx\Services\OrderService;
use Nutronyx\Services\CashfreePaymentService;
use Nutronyx\Database;
use Nutronyx\Context;

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$cart = CartService::getCart();
$placedOrder = null;
$errorMessage = '';

// Check if user reached checkout directly with empty cart
if (empty($cart['items']) && $_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_GET['confirmed'])) {
    header('Location: cart.php');
    exit;
}

// Handle Order Placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = preg_replace('/[^\d]/', '', (string)($_POST['phone'] ?? ''));
    $addr1 = trim((string)($_POST['address_line1'] ?? ''));
    $addr2 = trim((string)($_POST['address_line2'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $state = trim((string)($_POST['state'] ?? ''));
    $pincode = trim((string)($_POST['pincode'] ?? ''));
    $customerNotes = trim((string)($_POST['customer_notes'] ?? ''));
    $paymentMethod = strtoupper(trim((string)($_POST['payment_method'] ?? 'CASHFREE')));

    if (empty($fullName)) {
        $errorMessage = 'Please enter your recipient full name.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address for order notifications.';
    } elseif (strlen($phone) < 10) {
        $errorMessage = 'Please enter a valid 10-digit mobile number for delivery SMS.';
    } elseif (empty($addr1)) {
        $errorMessage = 'Please enter your street address or building number.';
    } elseif (empty($city) || empty($state) || empty($pincode)) {
        $errorMessage = 'Please fill in your complete city, state, and PIN code.';
    } elseif (empty($cart['items'])) {
        $errorMessage = 'Your cart is empty. Please add items before placing an order.';
    } else {
        try {
            $orderItems = [];
            foreach ($cart['items'] as $it) {
                $orderItems[] = [
                    'variant_id' => $it['variant_id'],
                    'sku' => $it['sku'],
                    'title' => $it['product_title'] . ' (' . $it['variant_title'] . ')',
                    'quantity' => (int)$it['quantity'],
                    'unit_price' => $it['price_rupees'],
                    'unit_price_amount' => (int)$it['price_amount']
                ];
            }

            $orderData = [
                'customer_name' => $fullName,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'shipping_name' => $fullName,
                'shipping_phone' => $phone,
                'shipping_address_line1' => $addr1,
                'shipping_address_line2' => $addr2,
                'shipping_city' => $city,
                'shipping_state' => $state,
                'shipping_postal_code' => $pincode,
                'customer_notes' => $customerNotes,
                'items' => $orderItems,
                'shipping_amount_paise' => $cart['shipping_paise'],
                'discount_amount_paise' => $cart['discount_paise'],
                'payment_method' => 'CASHFREE',
                'payment_status' => 'PENDING',
                'order_status' => 'PENDING'
            ];

            $placedOrder = OrderService::createOrder($orderData);

            if ($placedOrder) {
                $cfRes = CashfreePaymentService::createOrder([
                    'order_id' => $placedOrder['order_number'],
                    'order_amount' => (float)$cart['total_rupees'],
                    'order_currency' => 'INR',
                    'customer_details' => [
                        'customer_id' => $placedOrder['customer_id'],
                        'customer_phone' => $phone,
                        'customer_email' => $email,
                        'customer_name' => $fullName
                    ],
                    'payment_method' => 'UPI'
                ]);

                $_SESSION['nutronyx_last_order'] = $placedOrder;
                $_SESSION['nutronyx_last_cart_summary'] = $cart;

                header('Location: pay_cashfree.php?order_id=' . urlencode($placedOrder['order_number']) . '&session_id=' . urlencode($cfRes['payment_session_id'] ?? ''));
                exit;
            }

        } catch (\Throwable $e) {
            $errorMessage = 'Failed to process order: ' . $e->getMessage();
        }
    }
}

// Confirmation View check
if (isset($_GET['confirmed'])) {
    $confNum = trim((string)$_GET['confirmed']);
    if (!empty($_SESSION['nutronyx_last_order']) && ($_SESSION['nutronyx_last_order']['order_number'] ?? '') === $confNum) {
        $placedOrder = $_SESSION['nutronyx_last_order'];
    } else {
        $db = Database::getConnection();
        $q = $db->prepare("SELECT * FROM orders WHERE order_number = :onum LIMIT 1");
        $q->execute([':onum' => $confNum]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $placedOrder = $row;
            $iq = $db->prepare("SELECT * FROM order_items WHERE order_id = :oid");
            $iq->execute([':oid' => $row['id']]);
            $placedOrder['items'] = $iq->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    $confirmedCart = $_SESSION['nutronyx_last_cart_summary'] ?? null;
}

$currentPage = 'checkout';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $placedOrder ? 'Order Confirmed' : 'Complete Your Order' ?> &mdash; NUTRONYX</title>
    <meta name="description" content="Nutronyx botanical wellness order checkout and instant fulfillment.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/shop.css">
    <link rel="stylesheet" href="assets/css/cart.css">
</head>
<body class="nutronyx-body">

    <!-- Site Header -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main id="main-content">
        <div class="cart-page-wrap">

            <?php if ($placedOrder): ?>
                <!-- ==============================================================
                     ORDER CONFIRMATION / SUCCESS STATE
                     ============================================================== -->
                <div class="order-success-card">
                    <div class="success-icon-wrap">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>

                    <h1 class="order-success-title">Your Botanical Ritual is On Its Way!</h1>
                    <p class="order-success-sub">
                        Thank you, <strong><?= htmlspecialchars($placedOrder['shipping_name'] ?? $placedOrder['customer_name'] ?? 'valued patron') ?></strong>. 
                        Your formulation order has been confirmed and scheduled for dispatch.
                    </p>

                    <!-- Order Receipt Box -->
                    <div class="order-receipt-box">
                        <div class="receipt-meta-grid">
                            <div class="receipt-meta-item">
                                <div class="meta-label">Order Number</div>
                                <div class="meta-val" style="color:#0F3822; font-size:15px; font-weight:700;">#<?= htmlspecialchars($placedOrder['order_number']) ?></div>
                            </div>
                            <div class="receipt-meta-item">
                                <div class="meta-label">Payment Method</div>
                                <div class="meta-val">
                                    <?= htmlspecialchars($placedOrder['payment_method'] === 'CASHFREE' ? 'Cashfree Payments & UPI' : $placedOrder['payment_method']) ?>
                                </div>
                            </div>
                            <div class="receipt-meta-item">
                                <div class="meta-label">Payment Status</div>
                                <div class="meta-val" style="color:<?= ($placedOrder['payment_status'] === 'PAID') ? '#175C31' : '#B7791F' ?>;">
                                    <?= htmlspecialchars($placedOrder['payment_status']) ?>
                                </div>
                            </div>
                            <div class="receipt-meta-item">
                                <div class="meta-label">Estimated Delivery</div>
                                <div class="meta-val">
                                    <?= date('l, d M Y', strtotime('+4 days')) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Shipping Destination -->
                        <div style="font-size:13px; color:#556459; margin-bottom:16px; line-height:1.6; border-top:1px solid #E8E2D6; padding-top:14px;">
                            <strong style="color:#14221A;">Delivering to:</strong><br>
                            <?= htmlspecialchars($placedOrder['shipping_name']) ?> &bull; <?= htmlspecialchars($placedOrder['shipping_phone']) ?><br>
                            <?= htmlspecialchars($placedOrder['shipping_address_line1']) ?>
                            <?php if (!empty($placedOrder['shipping_address_line2'])): ?>, <?= htmlspecialchars($placedOrder['shipping_address_line2']) ?><?php endif; ?><br>
                            <?= htmlspecialchars($placedOrder['shipping_city']) ?>, <?= htmlspecialchars($placedOrder['shipping_state']) ?> &ndash; <?= htmlspecialchars($placedOrder['shipping_postal_code']) ?>
                        </div>

                        <!-- Itemized Items -->
                        <div class="receipt-items-list" style="border-top:1px solid #E8E2D6; padding-top:14px;">
                            <?php if (!empty($placedOrder['items'])): ?>
                                <?php foreach ($placedOrder['items'] as $oit): ?>
                                    <div class="receipt-item-row">
                                        <span><?= (int)$oit['quantity'] ?>x <?= htmlspecialchars($oit['title']) ?></span>
                                        <strong>₹<?= number_format((float)($oit['total_amount'] / 100), 2) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif ($confirmedCart && !empty($confirmedCart['items'])): ?>
                                <?php foreach ($confirmedCart['items'] as $cit): ?>
                                    <div class="receipt-item-row">
                                        <span><?= (int)$cit['quantity'] ?>x <?= htmlspecialchars($cit['product_title']) ?> (<?= htmlspecialchars($cit['variant_title']) ?>)</span>
                                        <strong>₹<?= number_format($cit['line_total_rupees'], 2) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Total summary -->
                        <div style="border-top:1px dashed #DDD6C9; padding-top:14px; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:14.5px; font-weight:700; color:#14221A;">Grand Total Paid</span>
                            <span style="font-family:var(--font-sans); font-size:24px; font-weight:800; color:#175C31;">
                                ₹<?= number_format((float)($placedOrder['grand_total_amount'] / 100), 2) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="order-success-actions">
                        <a href="track-order.php?order_number=<?= urlencode($placedOrder['order_number']) ?>&mobile=<?= urlencode($placedOrder['shipping_phone']) ?>" 
                           class="btn-cart-proceed" style="display:inline-flex; width:auto; padding:12px 28px;">
                            Track Live Dispatch
                        </a>
                        <a href="shop.php" class="btn-cart-proceed" style="display:inline-flex; width:auto; padding:12px 28px; background:#FAF7F2; color:#14221A; border:1px solid #DDD6C9;">
                            Return to Shop
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- ==============================================================
                     CHECKOUT INTERFACE (Exact Reference Image 2 with user exclusions)
                     - Delivery unlocked card omitted
                     - Shipping charges line in Order Review omitted
                     ============================================================== -->

                <!-- Top Meta Row: Breadcrumbs & Secure Tag (Image 2) -->
                <div class="checkout-top-meta">
                    <a href="cart.php" class="checkout-crumb-link">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        <span>Cart / Checkout</span>
                    </a>
                    <div class="checkout-secure-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        <span>Secure Checkout</span>
                    </div>
                </div>

                <!-- Header Block: Title & 2-Step Indicator (Image 2) -->
                <div class="checkout-header-block">
                    <div>
                        <h1 class="checkout-main-title">Complete Your Order</h1>
                        <p class="checkout-sub-title">Provide your contact and delivery details to proceed.</p>
                    </div>

                    <!-- 2-Step Progress (Image 2: (1) Delivery details --- (2) Review) -->
                    <div class="checkout-two-steps">
                        <div class="two-step-item active">
                            <span class="two-step-circle">1</span>
                            <span>Delivery details</span>
                        </div>
                        <div class="two-step-line"></div>
                        <div class="two-step-item">
                            <span class="two-step-circle">2</span>
                            <span>Review</span>
                        </div>
                    </div>
                </div>

                <!-- Error Alert if any -->
                <?php if (!empty($errorMessage)): ?>
                    <div style="background:#FDEBEA; border:1px solid #F5C6CB; color:#C0392B; padding:12px 18px; border-radius:8px; margin-bottom:24px; font-size:13.5px; font-weight:500;">
                        <strong>Note:</strong> <?= htmlspecialchars($errorMessage) ?>
                    </div>
                <?php endif; ?>

                <!-- Checkout 2-Column Grid (Image 2) -->
                <form method="POST" action="checkout.php" id="checkoutForm">
                    <input type="hidden" name="action" value="place_order">

                    <div class="cart-grid-main">
                        <!-- Left Column: Form Cards -->
                        <div>
                            <!-- Card 1: Contact Information (Image 2) -->
                            <div class="checkout-card">
                                <div class="checkout-card-header">
                                    <div class="checkout-icon-circle">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    </div>
                                    <div>
                                        <h2 class="checkout-card-title">Contact Information</h2>
                                        <p class="checkout-card-desc">We'll send order updates and dispatch tracking here.</p>
                                    </div>
                                </div>

                                <div class="form-row-2">
                                    <div class="form-field-group">
                                        <label for="email">Email Address <span class="req">*</span></label>
                                        <div class="input-icon-wrap">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                            <input type="email" id="email" name="email" class="form-input-control" 
                                                   placeholder="alexandra@example.com" required 
                                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                        </div>
                                    </div>

                                    <div class="form-field-group">
                                        <label for="phone">Mobile Phone <span class="req">*</span></label>
                                        <div class="input-icon-wrap">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                            <input type="tel" id="phone" name="phone" class="form-input-control" 
                                                   placeholder="9876543210" pattern="[0-9]{10}" maxlength="10" required 
                                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 2: Delivery Address (Image 2) -->
                            <div class="checkout-card">
                                <div class="checkout-card-header">
                                    <div class="checkout-icon-circle">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    </div>
                                    <div>
                                        <h2 class="checkout-card-title">Delivery Address</h2>
                                        <p class="checkout-card-desc">Where should we deliver your botanical formulations?</p>
                                    </div>
                                </div>

                                <div class="form-field-group">
                                    <label for="full_name">Recipient Full Name <span class="req">*</span></label>
                                    <div class="input-icon-wrap">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                        <input type="text" id="full_name" name="full_name" class="form-input-control" 
                                               placeholder="Alexandra Thorne" required 
                                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="form-field-group">
                                    <label for="address_line1">Street Address <span class="req">*</span></label>
                                    <div class="input-icon-wrap">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                        <input type="text" id="address_line1" name="address_line1" class="form-input-control" 
                                               placeholder="Penthouse 4B, Emerald Heights, MG Road" required 
                                               value="<?= htmlspecialchars($_POST['address_line1'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="form-field-group">
                                    <label for="address_line2">Area, Landmark (Optional)</label>
                                    <div class="input-icon-wrap">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
                                        <input type="text" id="address_line2" name="address_line2" class="form-input-control" 
                                               placeholder="Near Botanical Garden" 
                                               value="<?= htmlspecialchars($_POST['address_line2'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="form-row-3">
                                    <div class="form-field-group">
                                        <label for="city">City <span class="req">*</span></label>
                                        <div class="input-icon-wrap">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
                                            <input type="text" id="city" name="city" class="form-input-control" 
                                                   placeholder="Bengaluru" required 
                                                   value="<?= htmlspecialchars($_POST['city'] ?? 'Bengaluru') ?>">
                                        </div>
                                    </div>

                                    <div class="form-field-group">
                                        <label for="state">State <span class="req">*</span></label>
                                        <div class="input-icon-wrap">
                                            <select id="state" name="state" class="form-input-control select-control" required style="padding-left:14px;">
                                                <option value="" disabled <?= empty($_POST['state']) ? 'selected' : '' ?>>Select State / Union Territory</option>
                                                <optgroup label="States">
                                                    <option value="Andhra Pradesh" <?= ($_POST['state'] ?? '') === 'Andhra Pradesh' ? 'selected' : '' ?>>Andhra Pradesh</option>
                                                    <option value="Arunachal Pradesh" <?= ($_POST['state'] ?? '') === 'Arunachal Pradesh' ? 'selected' : '' ?>>Arunachal Pradesh</option>
                                                    <option value="Assam" <?= ($_POST['state'] ?? '') === 'Assam' ? 'selected' : '' ?>>Assam</option>
                                                    <option value="Bihar" <?= ($_POST['state'] ?? '') === 'Bihar' ? 'selected' : '' ?>>Bihar</option>
                                                    <option value="Chhattisgarh" <?= ($_POST['state'] ?? '') === 'Chhattisgarh' ? 'selected' : '' ?>>Chhattisgarh</option>
                                                    <option value="Goa" <?= ($_POST['state'] ?? '') === 'Goa' ? 'selected' : '' ?>>Goa</option>
                                                    <option value="Gujarat" <?= ($_POST['state'] ?? '') === 'Gujarat' ? 'selected' : '' ?>>Gujarat</option>
                                                    <option value="Haryana" <?= ($_POST['state'] ?? '') === 'Haryana' ? 'selected' : '' ?>>Haryana</option>
                                                    <option value="Himachal Pradesh" <?= ($_POST['state'] ?? '') === 'Himachal Pradesh' ? 'selected' : '' ?>>Himachal Pradesh</option>
                                                    <option value="Jharkhand" <?= ($_POST['state'] ?? '') === 'Jharkhand' ? 'selected' : '' ?>>Jharkhand</option>
                                                    <option value="Karnataka" <?= ($_POST['state'] ?? 'Karnataka') === 'Karnataka' ? 'selected' : '' ?>>Karnataka</option>
                                                    <option value="Kerala" <?= ($_POST['state'] ?? '') === 'Kerala' ? 'selected' : '' ?>>Kerala</option>
                                                    <option value="Madhya Pradesh" <?= ($_POST['state'] ?? '') === 'Madhya Pradesh' ? 'selected' : '' ?>>Madhya Pradesh</option>
                                                    <option value="Maharashtra" <?= ($_POST['state'] ?? '') === 'Maharashtra' ? 'selected' : '' ?>>Maharashtra</option>
                                                    <option value="Manipur" <?= ($_POST['state'] ?? '') === 'Manipur' ? 'selected' : '' ?>>Manipur</option>
                                                    <option value="Meghalaya" <?= ($_POST['state'] ?? '') === 'Meghalaya' ? 'selected' : '' ?>>Meghalaya</option>
                                                    <option value="Mizoram" <?= ($_POST['state'] ?? '') === 'Mizoram' ? 'selected' : '' ?>>Mizoram</option>
                                                    <option value="Nagaland" <?= ($_POST['state'] ?? '') === 'Nagaland' ? 'selected' : '' ?>>Nagaland</option>
                                                    <option value="Odisha" <?= ($_POST['state'] ?? '') === 'Odisha' ? 'selected' : '' ?>>Odisha</option>
                                                    <option value="Punjab" <?= ($_POST['state'] ?? '') === 'Punjab' ? 'selected' : '' ?>>Punjab</option>
                                                    <option value="Rajasthan" <?= ($_POST['state'] ?? '') === 'Rajasthan' ? 'selected' : '' ?>>Rajasthan</option>
                                                    <option value="Sikkim" <?= ($_POST['state'] ?? '') === 'Sikkim' ? 'selected' : '' ?>>Sikkim</option>
                                                    <option value="Tamil Nadu" <?= ($_POST['state'] ?? '') === 'Tamil Nadu' ? 'selected' : '' ?>>Tamil Nadu</option>
                                                    <option value="Telangana" <?= ($_POST['state'] ?? '') === 'Telangana' ? 'selected' : '' ?>>Telangana</option>
                                                    <option value="Tripura" <?= ($_POST['state'] ?? '') === 'Tripura' ? 'selected' : '' ?>>Tripura</option>
                                                    <option value="Uttar Pradesh" <?= ($_POST['state'] ?? '') === 'Uttar Pradesh' ? 'selected' : '' ?>>Uttar Pradesh</option>
                                                    <option value="Uttarakhand" <?= ($_POST['state'] ?? '') === 'Uttarakhand' ? 'selected' : '' ?>>Uttarakhand</option>
                                                    <option value="West Bengal" <?= ($_POST['state'] ?? '') === 'West Bengal' ? 'selected' : '' ?>>West Bengal</option>
                                                </optgroup>
                                                <optgroup label="Union Territories">
                                                    <option value="Andaman and Nicobar Islands" <?= ($_POST['state'] ?? '') === 'Andaman and Nicobar Islands' ? 'selected' : '' ?>>Andaman and Nicobar Islands</option>
                                                    <option value="Chandigarh" <?= ($_POST['state'] ?? '') === 'Chandigarh' ? 'selected' : '' ?>>Chandigarh</option>
                                                    <option value="Dadra and Nagar Haveli and Daman and Diu" <?= ($_POST['state'] ?? '') === 'Dadra and Nagar Haveli and Daman and Diu' ? 'selected' : '' ?>>Dadra and Nagar Haveli and Daman and Diu</option>
                                                    <option value="Delhi" <?= ($_POST['state'] ?? '') === 'Delhi' ? 'selected' : '' ?>>Delhi (NCT)</option>
                                                    <option value="Jammu and Kashmir" <?= ($_POST['state'] ?? '') === 'Jammu and Kashmir' ? 'selected' : '' ?>>Jammu and Kashmir</option>
                                                    <option value="Ladakh" <?= ($_POST['state'] ?? '') === 'Ladakh' ? 'selected' : '' ?>>Ladakh</option>
                                                    <option value="Lakshadweep" <?= ($_POST['state'] ?? '') === 'Lakshadweep' ? 'selected' : '' ?>>Lakshadweep</option>
                                                    <option value="Puducherry" <?= ($_POST['state'] ?? '') === 'Puducherry' ? 'selected' : '' ?>>Puducherry</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-field-group">
                                        <label for="pincode">PIN Code <span class="req">*</span></label>
                                        <div class="input-icon-wrap">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path></svg>
                                            <input type="text" id="pincode" name="pincode" class="form-input-control" 
                                                   placeholder="560001" pattern="[0-9]{6}" maxlength="6" required 
                                                   value="<?= htmlspecialchars($_POST['pincode'] ?? '560001') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-field-group" style="margin-bottom:0;">
                                    <label for="customer_notes">Delivery Instructions (Optional)</label>
                                    <div class="textarea-icon-wrap">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                        <textarea id="customer_notes" name="customer_notes" class="textarea-control" 
                                                  maxlength="200" rows="2" 
                                                  placeholder="e.g., Gate code, leave with concierge, call upon arrival"
                                                  oninput="updateNoteCounter(this)"><?= htmlspecialchars($_POST['customer_notes'] ?? '') ?></textarea>
                                    </div>
                                    <div class="textarea-counter" id="noteCounter">0 / 200</div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Order Review (Exact Image 2 Match with user exclusions) -->
                        <aside class="cart-summary-panel">
                            <div class="checkout-card-header" style="margin-bottom:16px;">
                                <div class="checkout-icon-circle">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                                </div>
                                <div>
                                    <h2 class="checkout-card-title">Order Review</h2>
                                    <p class="checkout-card-desc"><?= count($cart['items']) ?> <?= count($cart['items']) === 1 ? 'item' : 'items' ?> in your ritual</p>
                                </div>
                            </div>

                            <!-- Items List -->
                            <div class="review-items-list">
                                <?php foreach ($cart['items'] as $cit): ?>
                                    <div class="review-item-row">
                                        <img src="<?= htmlspecialchars($cit['image_url'] ?: 'assets/images/product-abc.jpg') ?>" 
                                             alt="<?= htmlspecialchars($cit['product_title']) ?>" 
                                             class="review-item-thumb">
                                        <div class="review-item-info">
                                            <div class="review-item-name"><?= htmlspecialchars($cit['product_title']) ?></div>
                                            <div class="review-item-qty">Qty: <?= (int)$cit['quantity'] ?> &bull; <?= htmlspecialchars($cit['variant_title']) ?></div>
                                        </div>
                                        <div class="review-item-price">
                                            ₹<?= number_format($cit['line_total_rupees'], 2) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Promo Coupon Box (Checkout) -->
                            <div class="checkout-coupon-section" style="padding-top:16px; border-top:1px solid #EBE6DC; margin-bottom:16px;">
                                <div class="coupon-input-group" style="display:flex; gap:8px;">
                                    <div class="coupon-field-wrap" style="flex:1; position:relative; display:flex; align-items:center;">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="position:absolute; left:12px; color:#7A766F;"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                        <input type="text" id="checkoutCouponCode" class="coupon-input" placeholder="COUPON CODE" 
                                               value="<?= htmlspecialchars($cart['applied_coupon']['code'] ?? '') ?>"
                                               onkeydown="if(event.key === 'Enter'){ event.preventDefault(); applyCheckoutCoupon(); }"
                                               style="width:100%; padding:10px 12px 10px 36px; border:1px solid #D9D2C5; border-radius:8px; font-size:13px; text-transform:uppercase; font-weight:600; letter-spacing:0.5px; background:#fff;">
                                    </div>
                                    <button type="button" class="coupon-apply-btn" id="checkoutCouponBtn" onclick="applyCheckoutCoupon()" 
                                            style="padding:10px 18px; background:#1C2826; color:#fff; border:none; border-radius:8px; font-weight:700; font-size:12px; cursor:pointer; letter-spacing:0.8px;">
                                        APPLY
                                    </button>
                                </div>

                                <div id="checkoutAppliedCouponContainer" style="<?= empty($cart['applied_coupon']) ? 'display:none;' : '' ?> margin-top:8px;">
                                    <div class="cart-applied-coupon-chip" style="display:flex; align-items:center; justify-content:space-between; background:#EEF6F0; border:1px solid #C8DEC9; padding:6px 12px; border-radius:6px; font-size:12px;">
                                        <div class="coupon-chip-left" style="display:flex; align-items:center; gap:6px; color:#175C31; font-weight:600;">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                            <span id="checkoutAppliedCouponName"><?= htmlspecialchars($cart['applied_coupon']['code'] ?? '') ?></span>
                                        </div>
                                        <span class="coupon-remove-btn" onclick="removeCheckoutCoupon()" style="color:#C33; font-weight:600; cursor:pointer; font-size:11px;">Remove</span>
                                    </div>
                                </div>

                                <p class="coupon-hints" style="font-size:11px; color:#7A766F; margin-top:6px; margin-bottom:0;">
                                    Use <strong onclick="quickApplyCheckout('WELCOME15')" style="color:#175C31; cursor:pointer; text-decoration:underline;">WELCOME15</strong> (15% OFF) or <strong onclick="quickApplyCheckout('NUTRO10')" style="color:#175C31; cursor:pointer; text-decoration:underline;">NUTRO10</strong> (10% OFF).
                                </p>
                            </div>

                            <!-- Price Breakdown (Shipping charges line omitted per user request) -->
                            <div class="summary-rows-wrap" style="border-top:1px solid #EBE6DC; padding-top:16px;">
                                <div class="summary-data-row">
                                    <span>Items Subtotal</span>
                                    <span id="checkoutSubtotalText">₹<?= number_format($cart['subtotal_rupees'], 2) ?></span>
                                </div>

                                <div class="summary-data-row discount-text" id="checkoutDiscountRow" style="<?= ($cart['discount_rupees'] > 0) ? '' : 'display:none;' ?>">
                                    <span id="checkoutDiscountLabel">Discount (<?= htmlspecialchars($cart['applied_coupon']['code'] ?? '') ?>)</span>
                                    <span id="checkoutDiscountVal">&minus;₹<?= number_format($cart['discount_rupees'], 2) ?></span>
                                </div>

                                <div class="summary-data-row">
                                    <span>Taxes (GST)</span>
                                    <span>Included</span>
                                </div>
                            </div>

                            <!-- Green Highlighted Total Box (Image 2) -->
                            <div class="summary-green-box">
                                <span class="summary-green-label">Final Payable</span>
                                <span class="summary-green-val" id="checkoutFinalPayableText">₹<?= number_format($cart['total_rupees'], 2) ?></span>
                            </div>

                            <!-- Cashfree Payment Gateway Selector (No COD) -->
                            <input type="hidden" name="payment_method" value="CASHFREE">

                            <!-- CTA Submit Button -->
                            <button type="submit" class="btn-cart-proceed" id="submitCheckoutBtn">
                                <span>Proceed to Cashfree Payment</span>
                                <span>&rarr;</span>
                            </button>

                            <!-- Trust Hallmarks (Cleaned per user request) -->
                            <div class="cart-hallmarks-list">
                                <div class="hallmark-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                    <span>100% Organic &amp; FSSAI Certified Formulations</span>
                                </div>
                            </div>
                        </aside>
                    </div>
                </form>

            <?php endif; ?>

        </div>
    </main>

    <!-- Editorial Botanical Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
    function updateNoteCounter(textarea) {
        const counter = document.getElementById('noteCounter');
        if (counter) {
            counter.textContent = textarea.value.length + ' / 200';
        }
    }

    // Checkout Coupon Handlers
    function applyCheckoutCoupon() {
        const input = document.getElementById('checkoutCouponCode');
        const code = input ? input.value.trim() : '';
        if (!code) {
            alert('Please enter a coupon code.');
            return;
        }

        const btn = document.getElementById('checkoutCouponBtn');
        if (btn) btn.disabled = true;

        fetch('api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'apply_coupon', code: code })
        })
        .then(res => res.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (data.success && data.cart) {
                updateCheckoutPriceUI(data.cart);
            } else {
                alert(data.error || 'Unable to apply coupon.');
            }
        })
        .catch(err => {
            if (btn) btn.disabled = false;
            console.error(err);
            alert('Failed to apply coupon. Please try again.');
        });
    }

    function removeCheckoutCoupon() {
        fetch('api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove_coupon' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.cart) {
                updateCheckoutPriceUI(data.cart);
            }
        })
        .catch(err => console.error(err));
    }

    function quickApplyCheckout(code) {
        const input = document.getElementById('checkoutCouponCode');
        if (input) input.value = code;
        applyCheckoutCoupon();
    }

    function updateCheckoutPriceUI(cart) {
        // Subtotal
        const subtotalEl = document.getElementById('checkoutSubtotalText');
        if (subtotalEl) subtotalEl.textContent = '₹' + parseFloat(cart.subtotal_rupees).toFixed(2);

        // Final Payable
        const finalEl = document.getElementById('checkoutFinalPayableText');
        if (finalEl) finalEl.textContent = '₹' + parseFloat(cart.total_rupees).toFixed(2);

        // Coupon container & label
        const couponCont = document.getElementById('checkoutAppliedCouponContainer');
        const couponName = document.getElementById('checkoutAppliedCouponName');
        const discountRow = document.getElementById('checkoutDiscountRow');
        const discountLabel = document.getElementById('checkoutDiscountLabel');
        const discountVal = document.getElementById('checkoutDiscountVal');
        const couponInput = document.getElementById('checkoutCouponCode');

        if (cart.applied_coupon) {
            if (couponCont) couponCont.style.display = 'block';
            if (couponName) couponName.textContent = cart.applied_coupon.code;
            if (couponInput) couponInput.value = cart.applied_coupon.code;

            if (discountRow) discountRow.style.display = 'flex';
            if (discountLabel) discountLabel.textContent = 'Discount (' + cart.applied_coupon.code + ')';
            if (discountVal) discountVal.textContent = '-₹' + parseFloat(cart.discount_rupees).toFixed(2);
        } else {
            if (couponCont) couponCont.style.display = 'none';
            if (couponInput) couponInput.value = '';
            if (discountRow) discountRow.style.display = 'none';
        }
    }

    // Auto-initialize character counter on load
    document.addEventListener('DOMContentLoaded', () => {
        const noteEl = document.getElementById('customer_notes');
        if (noteEl) updateNoteCounter(noteEl);
    });
    </script>
</body>
</html>
