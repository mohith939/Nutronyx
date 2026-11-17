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
