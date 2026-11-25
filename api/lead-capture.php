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

        // 1. Ensure FIRST10 coupon exists in promotions table
        try {
            $chkPromo = $pdo->prepare("SELECT id FROM promotions WHERE website_id = 'web_nutronyx_d2c' AND code = 'FIRST10' LIMIT 1");
            $chkPromo->execute();
            if (!$chkPromo->fetchColumn()) {
                $promoId = 'promo_first10_' . bin2hex(random_bytes(4));
                $insPromo = $pdo->prepare("
                    INSERT INTO promotions (
                        id, website_id, code, discount_type, discount_value, minimum_order_amount,
                        usage_limit, usage_count, is_active, name, campaign_name, description, status, created_at
                    ) VALUES (
                        :id, 'web_nutronyx_d2c', 'FIRST10', 'PERCENTAGE', 10, 0,
                        10000, 0, 1, 'Welcome First Order 10% Off', 'Welcome Offers', '10% off your first order', 'ACTIVE', CURRENT_TIMESTAMP
                    )
                ");
                $insPromo->execute([':id' => $promoId]);
            }
        } catch (Throwable $pe) {
            // Log and continue
            error_log('Promo check error: ' . $pe->getMessage());
        }

        // 2. Persist into customer_inquiries table
        $stmt = $pdo->prepare("
            INSERT INTO customer_inquiries (
                id, website_id, type, name, email, phone, category, message, status, ip_address, created_at, updated_at
            ) VALUES (
                :id, :website_id, :type, :name, :email, :phone, :category, :message, :status, :ip, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':id'         => 'inq_' . bin2hex(random_bytes(8)),
            ':website_id' => 'web_nutronyx_d2c',
            ':type'       => 'WELCOME_OFFER',
            ':name'       => 'Welcome Lead (+91 ' . $mobile . ')',
            ':email'      => 'lead_' . $mobile . '@nutronyx.in',
            ':phone'      => '+91 ' . $mobile,
            ':category'   => 'Welcome Offer (FIRST10)',
            ':message'    => 'Subscriber claimed 10% welcome discount coupon FIRST10 on storefront popup. Mobile: +91 ' . $mobile,
            ':status'     => 'NEW',
            ':ip'         => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        // 3. Sync into customers table so admin can view and export from Customers page
        try {
            $checkCust = $pdo->prepare("SELECT id, tags FROM customers WHERE website_id = 'web_nutronyx_d2c' AND (phone = :phone OR phone = :clean_phone) LIMIT 1");
            $checkCust->execute([':phone' => '+91 ' . $mobile, ':clean_phone' => $mobile]);
            $existingCust = $checkCust->fetch(PDO::FETCH_ASSOC);

            if (!$existingCust) {
                $custId = 'cust_' . bin2hex(random_bytes(8));
                $custNum = 'NUX-L' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
                $custStmt = $pdo->prepare("
                    INSERT INTO customers (
                        id, website_id, customer_number, first_name, last_name, name, email, phone,
                        status, customer_type, total_orders, total_spent_amount, avg_order_value,
                        tags, notes, marketing_consent, created_at, updated_at
                    ) VALUES (
                        :id, 'web_nutronyx_d2c', :cust_num, 'Subscriber', :mobile_short, :full_name, :email, :phone,
                        'Active', 'Lead', 0, 0, 0,
                        :tags, :notes, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    )
                ");
                $custStmt->execute([
                    ':id' => $custId,
                    ':cust_num' => $custNum,
                    ':mobile_short' => substr($mobile, -4),
                    ':full_name' => 'Subscriber (+91 ' . $mobile . ')',
                    ':email' => 'lead_' . $mobile . '@nutronyx.in',
                    ':phone' => '+91 ' . $mobile,
                    ':tags' => json_encode(['Welcome Offer', 'FIRST10 Lead', 'Marketing Consent']),
                    ':notes' => 'Joined via Welcome 10% Discount Popup modal on storefront.'
                ]);
            }
        } catch (Throwable $ce) {
            error_log('Customer sync error: ' . $ce->getMessage());
        }
    }
} catch (Throwable $e) {
    error_log('Lead capture error: ' . $e->getMessage());
}

sendJson(true, '10% discount unlocked successfully!', [
    'coupon'        => 'FIRST10',
    'discount'      => '10%',
    'display_phone' => '+91 ' . $mobile
]);
