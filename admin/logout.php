<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../src/bootstrap.php';
use Nutronyx\Services\RoleService;

if (!empty($_SESSION['nutronyx_admin_id']) || !empty($_SESSION['nutronyx_admin_email'])) {
    try {
        RoleService::logAudit('auth.logout', 'admin_users', (string)($_SESSION['nutronyx_admin_id'] ?? 'unknown'), [
            'email' => $_SESSION['nutronyx_admin_email'] ?? '',
            'role' => $_SESSION['nutronyx_admin_role'] ?? ''
        ], 'SUCCESS');
    } catch (\Throwable $e) {}
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

if (isset($_COOKIE['nutronyx_admin_remember'])) {
    setcookie('nutronyx_admin_remember', '', time() - 3600, '/');
}

header('Location: login.php?logged_out=1');
exit;
