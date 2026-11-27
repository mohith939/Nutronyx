<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Services\CartService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    echo json_encode([
        'success' => true,
        'cart' => CartService::getCart(),
        'item_count' => CartService::getItemCount()
    ]);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = [];
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    if (empty($data)) {
        $data = $_POST;
    }

    $action = $data['action'] ?? 'add';

    try {
        switch ($action) {
            case 'add':
                $variantId = trim((string)($data['variant_id'] ?? ''));
                $qty = (int)($data['quantity'] ?? 1);
                if (empty($variantId)) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'Variant ID is required.']);
                    exit;
                }
                $res = CartService::addItem($variantId, $qty);
                echo json_encode(array_merge($res, ['item_count' => CartService::getItemCount()]));
                break;

            case 'update':
                $variantId = trim((string)($data['variant_id'] ?? ''));
                $qty = (int)($data['quantity'] ?? 1);
                if (empty($variantId)) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'Variant ID is required.']);
                    exit;
                }
                $res = CartService::updateQuantity($variantId, $qty);
                echo json_encode(array_merge($res, ['item_count' => CartService::getItemCount()]));
                break;

            case 'remove':
                $variantId = trim((string)($data['variant_id'] ?? ''));
                if (empty($variantId)) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'Variant ID is required.']);
                    exit;
                }
                $res = CartService::removeItem($variantId);
                echo json_encode(array_merge($res, ['item_count' => CartService::getItemCount()]));
                break;

            case 'apply_coupon':
                $code = trim((string)($data['code'] ?? ''));
                $res = CartService::applyCoupon($code);
                if (!$res['success']) {
                    http_response_code(422);
                }
                echo json_encode(array_merge($res, ['item_count' => CartService::getItemCount()]));
                break;

            case 'remove_coupon':
                CartService::removeCoupon();
                echo json_encode([
                    'success' => true,
                    'cart' => CartService::getCart(),
                    'item_count' => CartService::getItemCount()
                ]);
                break;

            case 'clear':
                CartService::clear();
                echo json_encode([
                    'success' => true,
                    'cart' => CartService::getCart(),
                    'item_count' => 0
                ]);
                break;

            case 'calculate_shipping':
                $pincode = trim((string)($data['pincode'] ?? ''));
                if (strlen($pincode) !== 6 || !ctype_digit($pincode)) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'Please enter a valid 6-digit PIN code.']);
                    exit;
                }

                $cart = CartService::getCart();
                $subtotalRupees = (float)$cart['subtotal_rupees'];
                $appliedCoupon = $cart['applied_coupon'];
                $isFreeShipping = false;

                if (!empty($appliedCoupon) && ($appliedCoupon['discount_type'] ?? '') === 'FREE_SHIPPING') {
                    $isFreeShipping = true;
                } elseif ($subtotalRupees >= ($cart['free_shipping_threshold'] ?? 999.00)) {
                    $isFreeShipping = true;
                }

                if ($isFreeShipping) {
                    $shippingPaise = 0;
                    $shippingRupees = 0.0;
                    $courierName = 'Ekart Express Logistics (Free)';
                    $zoneName = 'Nationwide Free Delivery';
                } else {
                    // Total weight of cart items in grams
                    $weightGrams = 0;
                    foreach ($cart['items'] as $it) {
                        $vTitle = strtolower($it['variant_title'] ?? '');
                        if (strpos($vTitle, '400') !== false) {
                            $itemWeight = 400;
                        } elseif (strpos($vTitle, '500') !== false) {
                            $itemWeight = 500;
                        } elseif (strpos($vTitle, '1000') !== false || strpos($vTitle, '1kg') !== false) {
                            $itemWeight = 1000;
                        } else {
                            $itemWeight = 200; // default 200gm
                        }
                        $weightGrams += ($itemWeight * (int)$it['quantity']);
                    }
                    $weightGrams = max(200, $weightGrams);

                    // Call Ekart rate estimate
                    $estimate = \Nutronyx\Services\EkartLogisticsService::getRateEstimate([
                        'dropPincode' => $pincode,
                        'weight' => $weightGrams,
                        'invoiceAmount' => $subtotalRupees
                    ]);

                    $shippingRupees = (float)($estimate['total'] ?? 50.00);
                    $shippingPaise = (int)round($shippingRupees * 100);
                    $courierName = 'Ekart Express Logistics';
                    $zoneName = $estimate['zone'] ?? 'Standard Surface';
                }

                $serviceability = \Nutronyx\Services\EkartLogisticsService::checkServiceability($pincode);
                $resolvedCity = $serviceability['details']['city'] ?? '';
                $resolvedState = $serviceability['details']['state'] ?? '';

                $_SESSION['checkout_shipping_pincode'] = $pincode;
                $_SESSION['checkout_shipping_paise'] = $shippingPaise;
                $_SESSION['checkout_shipping_zone'] = $zoneName ?? '';
                $_SESSION['checkout_courier_name'] = $courierName ?? 'Ekart Express Logistics';

                // Re-fetch cart with updated shipping applied
                $updatedCart = CartService::getCart();

                echo json_encode([
                    'success' => true,
                    'shipping_rupees' => $shippingRupees,
                    'shipping_paise' => $shippingPaise,
                    'is_free' => $isFreeShipping,
                    'courier' => $courierName,
                    'zone' => $zoneName ?? '',
                    'city' => $resolvedCity,
                    'state' => $resolvedState,
                    'location' => trim($resolvedCity . ($resolvedState ? ', ' . $resolvedState : '')),
                    'cart' => $updatedCart
                ]);
                break;

            case 'clear_shipping':
                CartService::clearShippingEstimate();
                $updatedCart = CartService::getCart();
                echo json_encode([
                    'success' => true,
                    'shipping_rupees' => 0,
                    'shipping_paise' => 0,
                    'is_free' => $updatedCart['is_free_shipping'],
                    'cart' => $updatedCart
                ]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Unknown action.']);
                break;
        }
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
