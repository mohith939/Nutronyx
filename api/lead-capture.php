<?php
/**
 * NUTRONYX — Welcome Popup Lead Capture Handler
 * Captures mobile numbers for welcome offer & discounts
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

function sendJson(bool $success, string $message, array $data = [], int $status = 200): void {
    http_response_code($status);
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'data'      => $data,
        'timestamp' => time()
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(false, 'Method Not Allowed', [], 405);
}

// Parse input
$raw = file_get_contents('php://input');
$input = [];
if (!empty($raw)) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $input = $decoded;
    }
}
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

$mobile = trim((string)($input['mobile'] ?? $input['phone'] ?? ''));
$mobile = preg_replace('/\D/', '', $mobile);

// Extract last 10 digits if country code was entered
if (strlen($mobile) > 10) {
    $mobile = substr($mobile, -10);
}

if (strlen($mobile) !== 10 || !preg_match('/^[6-9]\d{9}$/', $mobile)) {
    sendJson(false, 'Please enter a valid 10-digit mobile number.', ['mobile' => 'Invalid mobile number'], 422);
}

// Persist into SQLite
try {
    $dbPath = __DIR__ . '/../storage/nutronyx_commerce.sqlite';
    if (file_exists($dbPath)) {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("
            INSERT INTO customer_inquiries (
                id, website_id, type, name, email, phone, message, status, ip_address, created_at, updated_at
            ) VALUES (
                :id, :website_id, :type, :name, :email, :phone, :message, :status, :ip, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':id'         => 'inq_' . bin2hex(random_bytes(8)),
            ':website_id' => 'web_nutronyx_d2c',
            ':type'       => 'WELCOME_OFFER',
            ':name'       => 'Welcome Offer Subscriber',
            ':email'      => 'mobile_' . $mobile . '@nutronyx.lead',
            ':phone'      => '+91 ' . $mobile,
            ':message'    => 'Instant 10% First Purchase Discount Claimed',
            ':status'     => 'NEW',
            ':ip'         => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    }
} catch (Throwable $e) {
    error_log('Lead capture error: ' . $e->getMessage());
}

sendJson(true, '10% discount unlocked successfully!', [
    'coupon'        => 'WELCOME10',
    'discount'      => '10%',
    'display_phone' => '+91 ' . $mobile
]);
