<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use Nutronyx\Services\CashfreePaymentService;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, x-client-id, x-client-secret');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$raw = file_get_contents('php://input');
$json = json_decode($raw, true) ?: [];

// If standard GET request, return server metadata
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($json)) {
    echo json_encode([
        'name' => 'Nutronyx Cashfree HERE MCP Server',
        'version' => '1.0.0',
        'protocol' => 'modelcontextprotocol',
        'status' => 'ONLINE',
        'tools' => ['UpiTool', 'CardPaymentTool', 'NetbankingTool'],
        'resources' => ['ui://widget/cashfree-here.html']
    ]);
    exit;
}

$response = CashfreePaymentService::handleMcpRequest($json);
echo json_encode($response);
