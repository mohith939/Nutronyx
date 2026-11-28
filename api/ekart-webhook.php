<?php
/**
 * NUTRONYX — Ekart Logistics Elite Real-Time Webhook Endpoint
 * Ingests live tracking checkpoint and status push events from Ekart Logistics
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Services\OrderService;

header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true) ?: [];

// Also support GET ping / test
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['status' => 'healthy', 'service' => 'Nutronyx Ekart Webhook Receiver']);
    exit;
}

$dataObj = $payload['data'] ?? ($payload['shipment'] ?? $payload);

$awb = trim((string)(
    $payload['tracking_id'] ?? 
    $payload['awb'] ?? 
    $payload['tracking_number'] ?? 
    $payload['wbn'] ?? 
    $dataObj['tracking_id'] ?? 
    $dataObj['awb'] ?? 
    $dataObj['wbn'] ?? 
    $_GET['awb'] ?? 
    ''
));

$orderNumber = trim((string)(
    $payload['order_id'] ?? 
    $payload['order_number'] ?? 
    $payload['merchant_order_id'] ?? 
    $payload['client_order_id'] ?? 
    $payload['orderReference'] ?? 
    $dataObj['order_id'] ?? 
    $dataObj['order_number'] ?? 
    ''
));

$db = Database::getConnection();
$orderId = null;

if (!empty($awb)) {
    $stmt = $db->prepare("SELECT order_id FROM shipments WHERE tracking_number = :awb ORDER BY shipped_at DESC LIMIT 1");
    $stmt->execute([':awb' => $awb]);
    $orderId = $stmt->fetchColumn() ?: null;
}

if (!$orderId && !empty($orderNumber)) {
    $stmt = $db->prepare("SELECT id FROM orders WHERE UPPER(order_number) = UPPER(:onum) LIMIT 1");
    $stmt->execute([':onum' => $orderNumber]);
    $orderId = $stmt->fetchColumn() ?: null;
}

if (!$orderId) {
    http_response_code(404);
    echo json_encode([
        'status' => 'ignored',
        'message' => 'No active Nutronyx order matching provided AWB or Order Number.',
        'awb' => $awb,
        'order_number' => $orderNumber
    ]);
    exit;
}

try {
    $res = OrderService::syncEkartStatus($orderId);
    echo json_encode([
        'status' => 'success',
        'message' => 'Order tracking synchronized with Ekart successfully.',
        'sync' => $res
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Sync failure: ' . $e->getMessage()
    ]);
}
