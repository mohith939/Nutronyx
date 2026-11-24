<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

use Nutronyx\Services\CartService;
use Nutronyx\Database;
use Nutronyx\Context;

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$notice = '';
$error = '';

// Handle Fallback Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $vid = trim((string)($_POST['variant_id'] ?? ''));
        $qty = (int)($_POST['quantity'] ?? 1);
        CartService::updateQuantity($vid, $qty);
        header('Location: cart.php');
        exit;
    } elseif ($action === 'remove') {
        $vid = trim((string)($_POST['variant_id'] ?? ''));
        CartService::removeItem($vid);
        header('Location: cart.php');
        exit;
    } elseif ($action === 'apply_coupon') {
        $code = trim((string)($_POST['coupon_code'] ?? ''));
        $res = CartService::applyCoupon($code);
        if ($res['success']) {
            $notice = 'Coupon applied successfully!';
        } else {
            $error = $res['error'] ?? 'Invalid coupon.';
        }
    } elseif ($action === 'remove_coupon') {
        CartService::removeCoupon();
        $notice = 'Coupon removed.';
    } elseif ($action === 'clear') {
        CartService::clear();
        header('Location: cart.php');
        exit;
    } elseif ($action === 'add') {
        $vid = trim((string)($_POST['variant_id'] ?? ''));
        $qty = (int)($_POST['quantity'] ?? 1);
        CartService::addItem($vid, $qty);
        header('Location: cart.php');
        exit;
    }
}

$cart = CartService::getCart();
$items = $cart['items'];
$cartItemVids = array_column($items, 'variant_id');

// Fetch active promo codes dynamically from database
$activeCoupons = [];
try {
    $allPromos = \Nutronyx\Services\PromotionService::getPromotions();
    $now = time();
    foreach ($allPromos as $p) {
        if (($p['status'] ?? '') === 'ACTIVE' && (int)($p['is_active'] ?? 1) === 1) {
            if (!empty($p['expires_at']) && strtotime($p['expires_at']) < $now) {
                continue;
            }
            $activeCoupons[] = $p;
        }
    }
} catch (\Throwable $e) {
    $activeCoupons = [];
}

// Fetch Complementary Botanical Add-Ons from Database
$db = Database::getConnection();
$crossSellQuery = "
    SELECT p.id as product_id, p.title as product_title, p.image_url,
           pv.id as variant_id, pv.title as variant_title, pv.price_amount
    FROM products p
    JOIN product_variants pv ON p.id = pv.product_id
    WHERE UPPER(p.status) = 'ACTIVE'
      AND p.id IN ('prod_amla_vital', 'prod_beet_vital', 'prod_moringa', 'prod_carrot', 'prod_ginger')
    GROUP BY p.id
    LIMIT 4
";
$crossSellProducts = $db->query($crossSellQuery)->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'cart';
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

    <meta name="description" content="Review your pure botanical formulations and proceed to secure checkout.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Global Base & Component Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261124_v1">
    <link rel="stylesheet" href="assets/css/shop.css?v=20261119_v3">
    <link rel="stylesheet" href="assets/css/cart.css?v=20261119_v2">
</head>
<body class="nutronyx-body">

    <!-- Site Header -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main id="main-content">
        <div class="cart-page-wrap">

            <!-- Header Row -->
            <div class="cart-header-row">
                <div class="cart-title-row">
                    <h1 class="cart-title">Your Wellness Cart</h1>
                    <div class="cart-item-count-badge">
                        <span id="cartCountHeader"><?= count($items) ?></span> <?= count($items) === 1 ? 'item' : 'items' ?>
                    </div>
                </div>
            </div>



            <!-- Notifications -->
            <?php if (!empty($notice)): ?>
                <div style="background:#E8F3EB; border:1px solid #C4E2CC; color:#175C31; padding:12px 18px; border-radius:8px; margin-bottom:20px; font-size:13.5px; font-weight:500;">
                    <?= htmlspecialchars($notice) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div style="background:#FDEBEA; border:1px solid #F5C6CB; color:#C0392B; padding:12px 18px; border-radius:8px; margin-bottom:20px; font-size:13.5px; font-weight:500;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($items)): ?>
                <!-- Empty Cart State -->
                <div class="cart-empty-state" id="emptyCartState">
                    <div class="cart-empty-icon">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                    </div>
                    <h2 class="cart-empty-title">Your Wellness Cart is Empty</h2>
                    <p class="cart-empty-text">It seems you haven't added any items to your ritual yet. Explore our cold-milled single-origin powders and revitalize your daily routine.</p>
                    <a href="shop.php" class="btn-cart-proceed" style="display:inline-flex; width:auto; padding:12px 28px;">
                        Explore Products
                    </a>
                </div>
            <?php else: ?>
                <!-- Main 2-Column Cart Layout (Exact Image 1 Match) -->
                <div class="cart-grid-main" id="mainCartLayout">
                    <!-- Left Column: Products Table -->
                    <div class="cart-items-panel">
                        <div class="cart-table-head">
                            <div>Product</div>
                            <div>Price</div>
                            <div style="text-align:center;">Quantity</div>
                            <div style="text-align:right;">Total</div>
                        </div>

                        <div id="cartItemsList">
                            <?php foreach ($items as $it): ?>
                                <div class="cart-item-row" data-variant-id="<?= htmlspecialchars($it['variant_id']) ?>">
                                    <div class="cart-prod-flex">
                                        <img src="<?= htmlspecialchars($it['image_url'] ?: 'assets/images/product-abc.jpg') ?>" 
                                             alt="<?= htmlspecialchars($it['product_title']) ?>" 
                                             class="cart-prod-img">
                                        <div class="cart-prod-details">
                                            <a href="shop.php" class="cart-prod-name"><?= htmlspecialchars($it['product_title']) ?></a>
                                            <span class="cart-variant-badge"><?= htmlspecialchars($it['variant_title']) ?></span>
                                            <span class="cart-sku-code">SKU: <?= htmlspecialchars($it['sku']) ?></span>
                                        </div>
                                    </div>
                                    <div class="cart-price-col">
                                        ₹<?= number_format($it['price_rupees'], 2) ?>
                                    </div>
                                    <div style="display:flex; justify-content:center;">
                                        <div class="cart-stepper-box">
                                            <button type="button" class="cart-stepper-btn" onclick="stepCartQty('<?= $it['variant_id'] ?>', -1)" aria-label="Decrease quantity">&minus;</button>
                                            <input type="text" class="cart-stepper-input" id="qty-<?= htmlspecialchars($it['variant_id']) ?>" value="<?= (int)$it['quantity'] ?>" readonly>
                                            <button type="button" class="cart-stepper-btn" onclick="stepCartQty('<?= $it['variant_id'] ?>', 1)" aria-label="Increase quantity">&plus;</button>
                                        </div>
                                    </div>
                                    <div class="cart-total-col">
                                        <span class="cart-line-total" id="line-total-<?= htmlspecialchars($it['variant_id']) ?>">
                                            ₹<?= number_format($it['line_total_rupees'], 2) ?>
                                        </span>
                                        <button type="button" class="cart-trash-btn" onclick="removeCartItem('<?= $it['variant_id'] ?>')" title="Remove item" aria-label="Remove item">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Footer Toolbar -->
                        <div class="cart-panel-footer">
                            <a href="shop.php" class="cart-continue-link">
                                &larr; Continue Shopping
                            </a>
                            <button type="button" class="cart-clear-btn" onclick="clearCart()">
                                Clear Cart
                            </button>
                        </div>
                    </div>

                    <!-- Right Column: Order Summary Sidebar (Exact Image 1 Match) -->
                    <aside class="cart-summary-panel">
                        <h2 class="summary-heading">Order Summary</h2>

                        <!-- Promo Coupon Box -->
                        <form id="couponForm" onsubmit="applyCouponAjax(event)">
                            <div class="coupon-input-group">
                                <div class="coupon-field-wrap">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                    <input type="text" id="couponCodeInput" class="coupon-input" placeholder="COUPON CODE" 
                                           value="<?= htmlspecialchars($cart['applied_coupon']['code'] ?? '') ?>">
                                </div>
                                <button type="submit" class="coupon-apply-btn" id="couponSubmitBtn">APPLY</button>
                            </div>
                        </form>

                        <div id="appliedCouponContainer" style="<?= empty($cart['applied_coupon']) ? 'display:none;' : '' ?>">
                            <div class="cart-applied-coupon-chip">
                                <div class="coupon-chip-left">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                    <span id="appliedCouponName"><?= htmlspecialchars($cart['applied_coupon']['code'] ?? '') ?></span>
                                </div>
                                <span class="coupon-remove-btn" onclick="removeCouponAjax()">Remove</span>
                            </div>
                        </div>

                        <?php if (!empty($activeCoupons)): ?>
                            <p class="coupon-hints">
                                Use 
                                <?php 
                                $hints = [];
                                foreach (array_slice($activeCoupons, 0, 2) as $ac) {
                                    $cCode = htmlspecialchars($ac['code']);
                                    $cType = $ac['discount_type'] ?? 'PERCENTAGE';
                                    $cVal = (int)($ac['discount_value'] ?? 0);
                                    $cDesc = ($cType === 'PERCENTAGE') ? "{$cVal}% OFF" : (($cType === 'FIXED_AMOUNT') ? "₹{$cVal} OFF" : "Free Delivery");
                                    $hints[] = "<strong onclick=\"quickApplyCoupon('{$cCode}')\" style=\"cursor:pointer;\">{$cCode}</strong> ({$cDesc})";
                                }
                                echo implode(' or ', $hints) . '.';
                                ?>
                            </p>
                        <?php endif; ?>

                        <!-- Price Breakdown -->
                        <div class="summary-rows-wrap">
                            <div class="summary-data-row">
                                <span>Subtotal</span>
                                <span id="cartSubtotalText">₹<?= number_format($cart['subtotal_rupees'], 2) ?></span>
                            </div>
                            
                            <div class="summary-data-row discount-text" id="discountRow" style="<?= ($cart['discount_rupees'] > 0) ? 'display:flex;' : 'display:none;' ?>">
                                <span>Promotion Discount</span>
                                <span id="cartDiscountText">&minus;₹<?= number_format($cart['discount_rupees'], 2) ?></span>
                            </div>

                            <div class="summary-data-row">
                                <span>Shipping</span>
                                <span id="cartShippingText">
                                    <?php if ($cart['is_free_shipping']): ?>
                                        <strong style="color:#10B981;">FREE</strong>
                                    <?php elseif (!empty($cart['shipping_calculated']) && $cart['shipping_rupees'] > 0): ?>
                                        ₹<?= number_format($cart['shipping_rupees'], 2) ?>
                                    <?php else: ?>
                                        <span style="color:#6B7280; font-size:12.5px;">Calculated at checkout</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Green Highlighted Total Box (Image 1) -->
                        <div class="summary-green-box">
                            <span class="summary-green-label">Estimated Total</span>
                            <span class="summary-green-val" id="cartTotalText">₹<?= number_format($cart['total_rupees'], 2) ?></span>
                        </div>

                        <!-- Proceed Button -->
                        <a href="checkout.php" class="btn-cart-proceed" id="proceedCheckoutBtn">
                            <span>PROCEED TO CHECKOUT</span>
                            <span>&rarr;</span>
                        </a>

                        <!-- Trust Hallmarks (Matching Image 1) -->
                        <div class="cart-hallmarks-list">
                            <div class="hallmark-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                <span>100% Organic &amp; FSSAI Certified Formulations</span>
                            </div>
                        </div>
                    </aside>
                </div>

                <!-- Bottom 3-Pillar Features Banner (Matching Image 1) -->
                <div class="bottom-features-pill">
                    <div class="feature-pill-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <span>Thoughtfully made</span>
                    </div>
                    <div class="feature-pill-divider"></div>
                    <div class="feature-pill-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                        <span>Carefully packed</span>
                    </div>
                    <div class="feature-pill-divider"></div>
                    <div class="feature-pill-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                        <span>Delivered with care</span>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Global Cart Toast -->
    <div class="cart-toast" id="cartToast" aria-live="polite">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <span id="cartToastMsg">Item updated in your cart</span>
    </div>

    <!-- Editorial Botanical Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Interactive Cart Engine -->
    <script>
    function showToast(msg) {
        const toast = document.getElementById('cartToast');
        const msgEl = document.getElementById('cartToastMsg');
        if (toast && msgEl) {
            msgEl.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        }
    }

    function updateCartUI(cart, itemCount) {
        // Update header badge
        const badge = document.getElementById('headerCartBadge');
        if (badge) {
            badge.textContent = itemCount;
            badge.style.transform = 'scale(1.35)';
            setTimeout(() => { badge.style.transform = 'scale(1)'; }, 200);
        }

        const countHeader = document.getElementById('cartCountHeader');
        if (countHeader) countHeader.textContent = cart.items.length;

        // If cart is empty, reload or render empty state
        if (!cart.items || cart.items.length === 0) {
            window.location.reload();
            return;
        }



        // Update each item line total
        cart.items.forEach(it => {
            const qtyInput = document.getElementById('qty-' + it.variant_id);
            if (qtyInput) qtyInput.value = it.quantity;
            const lineTot = document.getElementById('line-total-' + it.variant_id);
            if (lineTot) lineTot.textContent = '₹' + it.line_total_rupees.toFixed(2);
        });

        // Summary totals
        const subtotalEl = document.getElementById('cartSubtotalText');
        if (subtotalEl) subtotalEl.textContent = '₹' + cart.subtotal_rupees.toFixed(2);

        const discountRow = document.getElementById('discountRow');
        const discountEl = document.getElementById('cartDiscountText');
        if (cart.discount_rupees > 0) {
            if (discountRow) discountRow.style.display = 'flex';
            if (discountEl) discountEl.innerHTML = '&minus;₹' + cart.discount_rupees.toFixed(2);
        } else {
            if (discountRow) discountRow.style.display = 'none';
        }

        const shippingEl = document.getElementById('cartShippingText');
        if (shippingEl) {
            if (cart.is_free_shipping) {
                shippingEl.innerHTML = '<strong style="color:#10B981;">FREE</strong>';
            } else if (cart.shipping_calculated && cart.shipping_rupees > 0) {
                shippingEl.innerHTML = '₹' + parseFloat(cart.shipping_rupees).toFixed(2);
            } else {
                shippingEl.innerHTML = '<span style="color:#6B7280; font-size:12.5px;">Calculated at checkout</span>';
            }
        }

        const totalEl = document.getElementById('cartTotalText');
        if (totalEl) totalEl.textContent = '₹' + cart.total_rupees.toFixed(2);

        // Coupon container
        const appliedCont = document.getElementById('appliedCouponContainer');
        const appliedName = document.getElementById('appliedCouponName');
        if (cart.applied_coupon) {
            if (appliedCont) appliedCont.style.display = 'block';
            if (appliedName) appliedName.textContent = cart.applied_coupon.code;
        } else {
            if (appliedCont) appliedCont.style.display = 'none';
        }
    }

    function stepCartQty(variantId, delta) {
        const qtyEl = document.getElementById('qty-' + variantId);
        const current = parseInt(qtyEl.value, 10) || 1;
        const newQty = Math.max(0, current + delta);

        fetch('api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update', variant_id: variantId, quantity: newQty })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (newQty === 0) {
                    const row = document.querySelector('[data-variant-id="' + variantId + '"]');
                    if (row) row.remove();
                }
                updateCartUI(data.cart, data.item_count);
                showToast('Cart updated');
            } else {
                alert(data.error || 'Failed to update item quantity.');
            }
        })
        .catch(e => console.error(e));
    }

    function removeCartItem(variantId) {
        fetch('api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove', variant_id: variantId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const row = document.querySelector('[data-variant-id="' + variantId + '"]');
                if (row) row.remove();
                updateCartUI(data.cart, data.item_count);
                showToast('Item removed from cart');
            }
        })
        .catch(e => console.error(e));
    }

    function clearCart() {
        if (!confirm('Clear all items from your wellness cart?')) return;
        fetch('api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clear' })
        })
        .then(r => r.json())
        .then(data => {
            window.location.reload();
        })
        .catch(e => console.error(e));
    }

    function applyCouponAjax(e) {
        if (e) e.preventDefault();
        const code = document.getElementById('couponCodeInput').value.trim();
        if (!code) {
            alert('Please enter a coupon code.');
            return;
        }

        const btn = document.getElementById('couponSubmitBtn');
        if (btn) btn.textContent = '...';

        fetch('api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'apply_coupon', code: code })
        })
        .then(r => r.json())
        .then(data => {
            if (btn) btn.textContent = 'APPLY';
            if (data.success) {
                updateCartUI(data.cart, data.item_count);
                showToast('Coupon ' + code + ' applied!');
            } else {
                alert(data.error || 'Unable to apply coupon.');
            }
        })
        .catch(e => {
            if (btn) btn.textContent = 'APPLY';
            console.error(e);
        });
    }

    function quickApplyCoupon(code) {
        const inp = document.getElementById('couponCodeInput');
        if (inp) inp.value = code;
        applyCouponAjax(null);
    }

    function removeCouponAjax() {
        fetch('api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove_coupon' })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateCartUI(data.cart, data.item_count);
                showToast('Coupon removed');
            }
        })
        .catch(e => console.error(e));
    }
    </script>
</body>
</html>
