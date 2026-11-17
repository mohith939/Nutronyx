<?php
/**
 * NUTRONYX — Secure Contact Form Backend Handler
 * Handles validation, CSRF verification, anti-spam honeypot, timing guards,
 * persistent message storage, and email notifications.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Services\InquiryService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure JSON response header
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Helper to respond with JSON and exit
function sendResponse(bool $success, string $message, array $errors = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'errors'    => $errors,
        'timestamp' => time()
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method Not Allowed. Please submit via POST.', [], 405);
}

// 2. Parse input data (Supports JSON payload and form-data)
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

// 3. Rate Limiting Check (Max 5 submissions per 10 minutes per IP/Session)
$currentTime = time();
$ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip');
$_SESSION['contact_rate_limit'] = $_SESSION['contact_rate_limit'] ?? [];

// Purge timestamps older than 600s
$_SESSION['contact_rate_limit'] = array_filter(
    $_SESSION['contact_rate_limit'],
    fn($t) => ($currentTime - $t) < 600
);

if (count($_SESSION['contact_rate_limit']) >= 5) {
    sendResponse(false, 'Too many messages sent. Please wait a few minutes before trying again.', ['rate_limit' => 'Rate limit exceeded'], 429);
}

// 4. Anti-Spam Check A: Honeypot field
// 'bot_catch' must be completely empty
if (!empty($inputData['bot_catch'])) {
    // Silently reject or simulate delay to foil bots
    usleep(500000);
    sendResponse(false, 'Spam detection triggered.', ['spam' => 'Honeypot filled'], 400);
}

// 5. Anti-Spam Check B: Form completion timing guard
// Real humans take at least 2.5 seconds to fill out name, email, subject, message
$formRenderTime = isset($inputData['form_timestamp']) ? (int)$inputData['form_timestamp'] : 0;
if ($formRenderTime > 0) {
    $elapsedSeconds = $currentTime - $formRenderTime;
    if ($elapsedSeconds < 2) {
        sendResponse(false, 'Form submitted too rapidly. Please take your time.', ['spam' => 'Submission too fast'], 400);
    }
}

// 6. CSRF Token Verification
$clientToken = $inputData['csrf_token'] ?? '';
$serverToken = $_SESSION['nutronyx_csrf_token'] ?? '';

// If session token exists, require match
if (!empty($serverToken)) {
    if (!hash_equals($serverToken, (string)$clientToken)) {
        sendResponse(false, 'Security verification token invalid or expired. Please refresh the page.', ['csrf' => 'Invalid CSRF token'], 403);
    }
}

// 7. Sanitize & Validate Form Fields
$errors = [];

// Full Name
$fullName = trim(strip_tags((string)($inputData['full_name'] ?? '')));
if (empty($fullName)) {
    $errors['full_name'] = 'Please enter your full name.';
} elseif (mb_strlen($fullName) < 2) {
    $errors['full_name'] = 'Full name must be at least 2 characters.';
} elseif (mb_strlen($fullName) > 100) {
    $errors['full_name'] = 'Full name must not exceed 100 characters.';
}

// Email Address
$email = trim((string)($inputData['email'] ?? ''));
if (empty($email)) {
    $errors['email'] = 'Please enter your email address.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address (e.g., name@domain.com).';
} elseif (mb_strlen($email) > 120) {
    $errors['email'] = 'Email address is too long.';
}

// Phone Number (Optional)
$phone = trim(strip_tags((string)($inputData['phone'] ?? '')));
if (!empty($phone)) {
    // Allow standard international/national phone characters
    if (!preg_match('/^[0-9+\-\s().]{7,25}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number or leave blank.';
    }
}

// Subject
$subject = trim(strip_tags((string)($inputData['subject'] ?? '')));
if (empty($subject)) {
    $errors['subject'] = 'Please select or enter an inquiry subject.';
} elseif (mb_strlen($subject) < 3) {
    $errors['subject'] = 'Subject must be at least 3 characters.';
} elseif (mb_strlen($subject) > 150) {
    $errors['subject'] = 'Subject must not exceed 150 characters.';
}

// Message
$message = trim(strip_tags((string)($inputData['message'] ?? '')));
if (empty($message)) {
    $errors['message'] = 'Please write your message or question.';
} elseif (mb_strlen($message) < 10) {
    $errors['message'] = 'Message must be at least 10 characters so we can assist you.';
} elseif (mb_strlen($message) > 3000) {
    $errors['message'] = 'Message exceeds the maximum limit of 3,000 characters.';
}

if (!empty($errors)) {
    sendResponse(false, 'Please review and correct the highlighted fields.', $errors, 422);
}

// 8. Record Rate Limit Hit
$_SESSION['contact_rate_limit'][] = $currentTime;

// 9. Primary Database Storage & Email Dispatch (InquiryService)
try {
    $inquiryRecord = InquiryService::createInquiry([
        'type'       => 'CONTACT',
        'name'       => $fullName,
        'email'      => $email,
        'phone'      => $phone ?: null,
        'company'    => null,
        'category'   => $subject,
        'message'    => $message,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
    $inquiryId = $inquiryRecord['id'];
} catch (\Throwable $e) {
    error_log("NUTRONYX Contact Form DB Error: " . $e->getMessage());
    $inquiryId = 'msg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
}

// 10. Secondary File Backup (storage/messages.json)
try {
    $storageDir = __DIR__ . '/../storage';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0755, true);
    }
    $storageFile = $storageDir . '/messages.json';
    $newEntry = [
        'id'         => $inquiryId,
        'created_at' => date('c'),
        'name'       => $fullName,
        'email'      => $email,
        'phone'      => $phone ?: null,
        'subject'    => $subject,
        'message'    => $message,
        'ip_hash'    => substr($ipHash, 0, 16),
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 120),
        'status'     => 'received'
    ];

    $existingData = [];
    if (file_exists($storageFile)) {
        $fileContents = file_get_contents($storageFile);
        if (!empty($fileContents)) {
            $parsed = json_decode($fileContents, true);
            if (is_array($parsed)) {
                $existingData = $parsed;
            }
        }
    }
    array_unshift($existingData, $newEntry);
    if (count($existingData) > 1000) {
        $existingData = array_slice($existingData, 0, 1000);
    }
    @file_put_contents(
        $storageFile,
        json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
} catch (\Throwable $e) {
    // Non-blocking fallback
}

// Regenerate CSRF token for next submission
$_SESSION['nutronyx_csrf_token'] = bin2hex(random_bytes(16));

// 11. Send Final Success Response
sendResponse(
    true,
    "Thank you, {$fullName}. Your message has been received by our Botanical Concierge. We will get back to you within 24 hours.",
    []
);
