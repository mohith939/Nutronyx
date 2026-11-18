<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;

class CartService
{
    private static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        if (!isset($_SESSION['nutronyx_cart']) || !is_array($_SESSION['nutronyx_cart'])) {
            $_SESSION['nutronyx_cart'] = [];
            // Seed 1 default flagship item if totally empty for instant visual demonstration
            self::seedDefaultItem();
        }
    }

    private static function seedDefaultItem(): void
    {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT pv.id as variant_id, pv.sku, pv.title as variant_title, pv.price_amount, 
                   p.title as product_title, p.image_url
            FROM product_variants pv
            JOIN products p ON pv.product_id = p.id
            WHERE UPPER(p.status) = 'ACTIVE'
            ORDER BY CASE WHEN p.id = 'prod_abc_powder' THEN 0 ELSE 1 END, pv.price_amount ASC
            LIMIT 1
        ");
        $item = $stmt->fetch();
        if ($item) {
            $_SESSION['nutronyx_cart'][$item['variant_id']] = [
                'variant_id' => $item['variant_id'],
                'sku' => $item['sku'],
                'product_title' => $item['product_title'],
                'variant_title' => $item['variant_title'],
                'price_amount' => (int)$item['price_amount'], // In paise (e.g. 59900 = ₹599.00)
                'quantity' => 1,
                'image_url' => $item['image_url'] ?: 'assets/images/nutronyx-box.png'
            ];
        }
    }

    public static function addItem(string $variantId, int $quantity = 1): array
    {
        self::initSession();
        $quantity = max(1, $quantity);

        if (isset($_SESSION['nutronyx_cart'][$variantId])) {
            $_SESSION['nutronyx_cart'][$variantId]['quantity'] += $quantity;
            return ['success' => true, 'cart' => self::getCart()];
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT pv.id as variant_id, pv.sku, pv.title as variant_title, pv.price_amount, 
                   p.title as product_title, p.image_url, p.is_visible_storefront, pv.is_visible, p.status
            FROM product_variants pv
            JOIN products p ON pv.product_id = p.id
            WHERE pv.id = :vid LIMIT 1
        ");
        $stmt->execute([':vid' => $variantId]);
        $prod = $stmt->fetch();

        if (!$prod || (int)$prod['is_visible_storefront'] !== 1 || (int)$prod['is_visible'] !== 1 || strtoupper($prod['status']) !== 'ACTIVE') {
            return ['success' => false, 'error' => 'This product is currently unavailable.'];
        }

        $_SESSION['nutronyx_cart'][$variantId] = [
            'variant_id' => $prod['variant_id'],
            'sku' => $prod['sku'],
            'product_title' => $prod['product_title'],
            'variant_title' => $prod['variant_title'],
            'price_amount' => (int)$prod['price_amount'],
            'quantity' => $quantity,
            'image_url' => $prod['image_url'] ?: 'assets/images/nutronyx-box.png'
        ];

        return ['success' => true, 'cart' => self::getCart()];
    }

    public static function updateQuantity(string $variantId, int $quantity): array
    {
        self::initSession();
        if ($quantity <= 0) {
            return self::removeItem($variantId);
        }

        if (isset($_SESSION['nutronyx_cart'][$variantId])) {
            $_SESSION['nutronyx_cart'][$variantId]['quantity'] = $quantity;
        }

        return ['success' => true, 'cart' => self::getCart()];
    }

    public static function removeItem(string $variantId): array
    {
        self::initSession();
        unset($_SESSION['nutronyx_cart'][$variantId]);
        return ['success' => true, 'cart' => self::getCart()];
    }

    public static function clear(): void
    {
        self::initSession();
        $_SESSION['nutronyx_cart'] = [];
        unset($_SESSION['nutronyx_applied_coupon']);
    }

    public static function applyCoupon(string $code): array
    {
        self::initSession();
        $cart = self::getCart();
        $code = strtoupper(trim($code));

        if (empty($code)) {
            unset($_SESSION['nutronyx_applied_coupon']);
            return ['success' => false, 'error' => 'Please enter a coupon code.'];
        }

        $validation = PromotionService::validateCoupon($code, (int)$cart['subtotal_paise']);
        if (!$validation['valid']) {
            unset($_SESSION['nutronyx_applied_coupon']);
            return ['success' => false, 'error' => $validation['error'] ?? 'Coupon is invalid or expired.'];
        }

        $_SESSION['nutronyx_applied_coupon'] = $validation['promo'];
        return ['success' => true, 'promo' => $validation['promo'], 'cart' => self::getCart()];
    }

    public static function removeCoupon(): void
    {
        self::initSession();
        unset($_SESSION['nutronyx_applied_coupon']);
    }

    public static function getItemCount(): int
    {
        self::initSession();
        $count = 0;
        foreach ($_SESSION['nutronyx_cart'] as $item) {
            $count += (int)($item['quantity'] ?? 1);
        }
        return $count;
    }

    public static function getCart(): array
    {
        self::initSession();

        $items = [];
        $subtotalPaise = 0;

        foreach ($_SESSION['nutronyx_cart'] as $vid => $item) {
            $qty = (int)$item['quantity'];
            $pricePaise = (int)$item['price_amount'];
            $linePaise = $pricePaise * $qty;
            $subtotalPaise += $linePaise;

            $items[] = array_merge($item, [
                'line_total_paise' => $linePaise,
                'price_rupees' => $pricePaise / 100,
                'line_total_rupees' => $linePaise / 100
            ]);
        }

        $subtotalRupees = $subtotalPaise / 100;

        // Discount computation from applied coupon
        $discountPaise = 0;
        $appliedCoupon = $_SESSION['nutronyx_applied_coupon'] ?? null;
        if ($appliedCoupon && $subtotalPaise > 0) {
            $check = PromotionService::validateCoupon($appliedCoupon['code'], $subtotalPaise);
            if ($check['valid']) {
                $discountPaise = min($check['discount_amount'], $subtotalPaise);
            } else {
                // If subtotal dropped below minimum, keep promo metadata but discount is 0 or notify
                $discountPaise = 0;
            }
        }

        $discountRupees = $discountPaise / 100;

        // Shipping calculation: Free above ₹999, else ₹99
        $freeShippingThreshold = 999.00;
        $shippingPaise = 0;
        if ($subtotalRupees > 0) {
            $shippingPaise = ($subtotalRupees >= $freeShippingThreshold) ? 0 : 9900;
        }
        $shippingRupees = $shippingPaise / 100;

        // GST Tax: included or standard 5% on health powders (already included or 0)
        $taxPaise = 0;
        $taxRupees = 0;

        $totalPaise = max(0, $subtotalPaise - $discountPaise + $shippingPaise + $taxPaise);
        $totalRupees = $totalPaise / 100;

        $neededForFreeShipping = max(0, $freeShippingThreshold - $subtotalRupees);
        $freeShippingPercent = min(100, round(($subtotalRupees / $freeShippingThreshold) * 100));

        return [
            'items' => $items,
            'item_count' => count($items),
            'total_quantity' => self::getItemCount(),
            'subtotal_paise' => $subtotalPaise,
            'subtotal_rupees' => $subtotalRupees,
            'discount_paise' => $discountPaise,
            'discount_rupees' => $discountRupees,
            'shipping_paise' => $shippingPaise,
            'shipping_rupees' => $shippingRupees,
            'tax_paise' => $taxPaise,
            'tax_rupees' => $taxRupees,
            'total_paise' => $totalPaise,
            'total_rupees' => $totalRupees,
            'applied_coupon' => $appliedCoupon,
            'free_shipping_threshold' => $freeShippingThreshold,
            'needed_for_free_shipping' => $neededForFreeShipping,
            'free_shipping_percent' => $freeShippingPercent,
            'is_free_shipping' => ($shippingPaise === 0 && $subtotalRupees > 0)
        ];
    }
}
