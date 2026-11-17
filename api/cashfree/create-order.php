<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use Nutronyx\Services\CashfreePaymentService;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$res = CashfreePaymentService::createOrder($input);
echo json_encode($res);
