<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Services\RoleService;

$msg = '';
$resetToken = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!empty($email)) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id, name, email FROM admin_users WHERE LOWER(email) = :em LIMIT 1");
            $stmt->execute([':em' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $token = bin2hex(random_bytes(24));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                $upd = $db->prepare("UPDATE admin_users SET password_reset_token = :t, password_reset_expires_at = :exp WHERE id = :id");
                $upd->execute([':t' => $token, ':exp' => $expires, ':id' => $user['id']]);

                RoleService::logAudit('auth.password_reset_requested', 'admin_users', (string)$user['id'], [
                    'email' => $user['email']
                ], 'SUCCESS');

                $resetToken = $token;
                $msg = "Password recovery instructions and security token have been generated for {$email}.";
            } else {
                $msg = 'If the provided administrative email is registered, password recovery instructions have been dispatched.';
            }
        } catch (\Throwable $e) {
            $msg = 'If the provided administrative email is registered, password recovery instructions have been dispatched.';
        }
    } else {
        $msg = 'Please provide a valid administrative email address.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Recovery &mdash; NUTRONYX Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=20261119_v1">
    <style>
    .recovery-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 30px 20px;
        background-color: var(--admin-cream);
    }
    .recovery-card {
        max-width: 440px;
        width: 100%;
        background: #FFFFFF;
        border-radius: 28px;
        padding: 40px 36px;
        box-shadow: 0 20px 60px rgba(20, 52, 34, 0.07);
        border: 1px solid rgba(215, 207, 192, 0.5);
        text-align: center;
    }
    .recovery-desc {
        font-size: 14px;
        color: #586B5F;
        line-height: 1.6;
        margin: 16px 0 24px;
    }
    .back-link {
        display: inline-block;
        margin-top: 20px;
        color: var(--admin-forest);
        font-weight: 600;
        font-size: 13.5px;
        text-decoration: none;
    }
    .back-link:hover {
        text-decoration: underline;
    }
    </style>
</head>
<body class="admin-body">
    <div class="recovery-wrapper">
        <div class="recovery-card">
            <div class="admin-card-sprout" aria-hidden="true">
                <svg width="32" height="24" viewBox="0 0 40 28" fill="none">
                    <path d="M20 25C20 18 14 9 2 7C2 19 11 25 20 25Z" fill="#2E6B42"/>
                    <path d="M20 25C20 18 26 9 38 7C38 19 29 25 20 25Z" fill="#1E5736"/>
                    <path d="M20 28V24" stroke="#1E5736" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
            <h1 class="admin-card-brand-name">Nutronyx<sup>&reg;</sup></h1>
            <h2 style="font-size: 18px; margin-top: 10px; color: #14221A; font-weight: 600;">Reset Admin Password</h2>
            
            <p class="recovery-desc">
                Enter your administrative email address below. We'll send you a secure verification link to reset your credentials.
            </p>

            <?php if (!empty($msg)): ?>
                <div class="admin-alert admin-alert-success" role="status">
                    <span><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php else: ?>
                <form action="forgot-password.php" method="POST" class="admin-form">
                    <div class="admin-form-group">
                        <label for="recovery-email" class="admin-label" style="text-align: left;">Email Address</label>
                        <div class="admin-input-wrap">
                            <span class="admin-input-icon" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                            </span>
                            <input type="email" id="recovery-email" name="email" class="admin-input" placeholder="nutronyx@gmail.com" required>
                        </div>
                    </div>
                    <button type="submit" class="admin-submit-btn">
                        <span>Send Recovery Link</span>
                    </button>
                </form>
            <?php endif; ?>

            <a href="login.php" class="back-link">&larr; Back to Admin Login</a>
        </div>
    </div>
</body>
</html>
