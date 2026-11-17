<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['nutronyx_admin_logged'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
