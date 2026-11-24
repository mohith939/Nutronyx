<?php
/**
 * NUTRONYX — Partnership Application Handler
 * Validates partnership applications, enforces anti-spam and CSRF,
 * logs to SQLite database via InquiryService, and dispatches notification
 * email to nutronyx.offical@gmail.com.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Services\InquiryService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

function sendPartnerResponse(bool $success, string $message, array $errors = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'errors'    => $errors,
        'timestamp' => time()
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendPartnerResponse(false, 'Method Not Allowed. Please submit via POST.', [], 405);
}

// Parse input
$rawInput = file_get_contents('php://input');
$inputData = [];
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $inputData = $decoded;
    }
}
if (empty($inputData) && !empty($_POST)) {
    $inputData = $_POST;
}

// Rate Limiting Check (Max 5 submissions per 10 minutes)
$currentTime = time();
$_SESSION['partner_rate_limit'] = $_SESSION['partner_rate_limit'] ?? [];
$_SESSION['partner_rate_limit'] = array_filter(
    $_SESSION['partner_rate_limit'],
    fn($t) => ($currentTime - $t) < 600
);
if (count($_SESSION['partner_rate_limit']) >= 5) {
    sendPartnerResponse(false, 'Too many applications submitted. Please wait a few minutes before trying again.', ['rate_limit' => 'Rate limit exceeded'], 429);
}

// Anti-Spam Check: Honeypot
if (!empty($inputData['bot_catch'])) {
    usleep(500000);
    sendPartnerResponse(false, 'Spam detection triggered.', ['spam' => 'Honeypot filled'], 400);
}

// Anti-Spam Check: Timing Guard
$formRenderTime = isset($inputData['form_timestamp']) ? (int)$inputData['form_timestamp'] : 0;
if ($formRenderTime > 0) {
    $elapsedSeconds = $currentTime - $formRenderTime;
    if ($elapsedSeconds < 2) {
        sendPartnerResponse(false, 'Form submitted too rapidly. Please take your time.', ['spam' => 'Submission too fast'], 400);
    }
}

// CSRF Token Check
$clientToken = $inputData['csrf_token'] ?? '';
$serverToken = $_SESSION['nutronyx_csrf_token'] ?? '';
if (!empty($serverToken) && !empty($clientToken)) {
    if (!hash_equals($serverToken, (string)$clientToken)) {
        sendPartnerResponse(false, 'Security verification token invalid or expired. Please refresh the page.', ['csrf' => 'Invalid CSRF token'], 403);
    }
}

// Sanitize & Validate Form Fields
$errors = [];

// Full Name
$fullName = trim(strip_tags((string)($inputData['full_name'] ?? '')));
if (empty($fullName)) {
    $errors['full_name'] = 'Please enter your full name.';
} elseif (mb_strlen($fullName) < 2) {
    $errors['full_name'] = 'Full name must be at least 2 characters.';
}

// Company / Brand / Clinic Name (Optional)
$company = trim(strip_tags((string)($inputData['company'] ?? '')));
if (empty($company)) {
    $company = 'Not specified';
}

// Email Address
$email = trim((string)($inputData['email'] ?? ''));
if (empty($email)) {
    $errors['email'] = 'Please enter your work or business email address.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

// Phone Number
$phone = trim(strip_tags((string)($inputData['phone'] ?? '')));
if (empty($phone)) {
    $errors['phone'] = 'Please provide your phone number so our partnership team can reach you.';
} elseif (!preg_match('/^[0-9+\-\s().]{7,25}$/', $phone)) {
    $errors['phone'] = 'Please enter a valid phone number.';
}

// Partnership Category
$category = trim(strip_tags((string)($inputData['category'] ?? '')));
if (empty($category)) {
    $category = 'General Partnership';
}

// Message / Proposal
$message = trim(strip_tags((string)($inputData['message'] ?? '')));
if (empty($message)) {
    $errors['message'] = 'Please provide a brief summary of how you would like to collaborate with Nutronyx.';
} elseif (mb_strlen($message) < 10) {
    $errors['message'] = 'Message must be at least 10 characters so we can understand your proposal.';
}

if (!empty($errors)) {
    sendPartnerResponse(false, 'Please correct the highlighted fields.', $errors, 422);
}

$_SESSION['partner_rate_limit'][] = $currentTime;

try {
    InquiryService::createInquiry([
        'type'       => 'PARTNERSHIP',
        'name'       => $fullName,
        'email'      => $email,
        'phone'      => $phone,
        'company'    => $company,
        'category'   => $category,
        'message'    => $message,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
} catch (\Throwable $e) {
    error_log("NUTRONYX Partnership Application DB Error: " . $e->getMessage());
    sendPartnerResponse(false, 'An internal error occurred while saving your application. Please email us directly at nutronyx.offical@gmail.com.', ['server' => $e->getMessage()], 500);
}

// Regenerate CSRF token
$_SESSION['nutronyx_csrf_token'] = bin2hex(random_bytes(16));

sendPartnerResponse(
    true,
    "Thank you, {$fullName}. Your partnership application has been submitted to the Nutronyx Strategic Alliances team. We will review your proposal and get in touch within 24 to 48 hours.",
    []
);
