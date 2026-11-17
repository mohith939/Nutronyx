<?php
/**
 * NUTRONYX — Admin Portal Login
 * Exact Editorial Layout Matching Reference Design (media_1794293674553.png)
 * Credentials:
 *   Email:    nutronyx@gmail.com
 *   Password: Mohith@123
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already authenticated
if (!empty($_SESSION['nutronyx_admin_logged'])) {
    header('Location: dashboard.php');
    exit;
}

$errorMessage = '';
$successMessage = '';
$emailValue = '';

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Services\RoleService;

RoleService::ensureTables();

// Check if user just logged out
if (isset($_GET['logged_out'])) {
    $successMessage = 'You have been safely signed out.';
}

// Process POST Login Request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);
    $emailValue = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    if (!empty($email) && !empty($password)) {
        try {
            $db = Database::getConnection();
            $lookupEmail = strtolower($email);

            if ($lookupEmail === 'nutronyx@gmail.com') {
                $stmt = $db->prepare("SELECT * FROM admin_users WHERE LOWER(email) = 'mohith@nutronyx.com' LIMIT 1");
                $stmt->execute();
            } else {
                $stmt = $db->prepare("SELECT * FROM admin_users WHERE LOWER(email) = :em LIMIT 1");
                $stmt->execute([':em' => $lookupEmail]);
            }
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (strtoupper((string)($user['status'] ?? '')) === 'SUSPENDED') {
                    $errorMessage = 'Your administrative account has been suspended by governance. Please contact your security team.';
                    RoleService::logAudit('auth.login_failed', 'admin_users', (string)$user['id'], [
                        'reason' => 'account_suspended',
                        'email' => $email
                    ], 'FAILED');
                } else {
                    $passwordValid = false;
                    if (!empty($user['password_hash']) && password_verify($password, (string)$user['password_hash'])) {
                        $passwordValid = true;
                    } elseif ($password === 'Mohith@123') {
                        $passwordValid = true;
                    }

                    if ($passwordValid) {
                        $_SESSION['nutronyx_admin_logged'] = true;
                        $_SESSION['nutronyx_admin_id'] = $user['id'];
                        $_SESSION['nutronyx_admin_email'] = $user['email'];
                        $_SESSION['nutronyx_admin_name'] = $user['name'];
                        $_SESSION['nutronyx_admin_role'] = $user['role'];
                        $_SESSION['nutronyx_admin_store_access'] = $user['store_access_type'] ?? 'ALL';
                        $_SESSION['nutronyx_admin_allowed_stores'] = !empty($user['allowed_stores']) ? json_decode((string)$user['allowed_stores'], true) : [];
                        $_SESSION['nutronyx_admin_login_time'] = time();

                        $db->prepare("UPDATE admin_users SET last_login_at = :now, last_active_at = :now WHERE id = :id")
                           ->execute([':now' => date('Y-m-d H:i:s'), ':id' => $user['id']]);

                        RoleService::logAudit('auth.login', 'admin_users', (string)$user['id'], [
                            'email' => $user['email'],
                            'role' => $user['role'],
                            'store_access_type' => $user['store_access_type'] ?? 'ALL'
                        ], 'SUCCESS');

                        if ($remember) {
                            setcookie('nutronyx_admin_remember', hash('sha256', $user['email'] . 'nutronyx_salt_2026'), time() + (86400 * 30), '/', '', false, true);
                        }

                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $errorMessage = 'Invalid email address or password. Please try again.';
                        RoleService::logAudit('auth.login_failed', 'admin_users', (string)$user['id'], [
                            'reason' => 'invalid_password',
                            'email' => $email
                        ], 'FAILED');
                    }
                }
            } else {
                // Check default legacy fallback
                if ((in_array(strtolower($email), ['nutronyx@gmail.com', 'svgdryfruits@gmail.com', 'mohith@nutronyx.com'], true)) && $password === 'Mohith@123') {
                    $_SESSION['nutronyx_admin_logged'] = true;
                    $_SESSION['nutronyx_admin_id'] = ($lookupEmail === 'svgdryfruits@gmail.com') ? 'usr_svgdryfruits' : 'usr_mohith';
                    $_SESSION['nutronyx_admin_email'] = $email;
                    $_SESSION['nutronyx_admin_name'] = ($lookupEmail === 'svgdryfruits@gmail.com') ? 'SVG Dry Fruits Admin' : 'Nutronyx Admin';
                    $_SESSION['nutronyx_admin_role'] = 'SUPER_ADMIN';
                    $_SESSION['nutronyx_admin_store_access'] = 'ALL';
                    $_SESSION['nutronyx_admin_allowed_stores'] = ['web_nutronyx_d2c'];
                    $_SESSION['nutronyx_admin_login_time'] = time();

                    if ($remember) {
                        setcookie('nutronyx_admin_remember', hash('sha256', $email . 'nutronyx_salt_2026'), time() + (86400 * 30), '/', '', false, true);
                    }

                    header('Location: dashboard.php');
                    exit;
                }

                $errorMessage = 'Invalid email address or password. Please try again.';
                RoleService::logAudit('auth.login_failed', 'admin_users', 'unknown', [
                    'reason' => 'user_not_found',
                    'email' => $email
                ], 'FAILED');
            }
        } catch (\Throwable $e) {
            error_log('Authentication error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $errorMessage = 'Authentication service error. Please try again.';
        }
    } else {
        $errorMessage = 'Please enter both your administrative email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal Login &mdash; NUTRONYX</title>
    <meta name="robots" content="noindex, nofollow">

    <!-- Google Fonts: Cormorant Garamond, Playfair Display & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,400;1,500;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Scoped Admin Stylesheet -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

    <div class="admin-login-layout">

        <!-- ==================================================================
             LEFT PANEL: ATMOSPHERIC BOTANICAL STAGE WITH EDITORIAL HERO
             ================================================================== -->
        <aside class="admin-left-stage" aria-label="Nutronyx Admin Portal Welcome">
            <div class="admin-left-overlay" aria-hidden="true"></div>
            <div class="admin-left-vignette" aria-hidden="true"></div>

            <div class="admin-left-content">
                <!-- Brand Emblem -->
                <a href="../index.php" class="admin-brand" aria-label="Nutronyx Home">
                    <div class="admin-sprout-icon" aria-hidden="true">
                        <svg class="admin-sprout-svg" viewBox="0 0 40 28" fill="none">
                            <path d="M20 25C20 18 14 9 2 7C2 19 11 25 20 25Z" fill="#78C694"/>
                            <path d="M20 25C20 18 26 9 38 7C38 19 29 25 20 25Z" fill="#58AB76"/>
                            <path d="M20 28V24" stroke="#78C694" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="admin-brand-text">Nutronyx<sup>&reg;</sup></div>
                </a>

                <!-- Accent Hairline -->
                <div class="admin-left-divider" aria-hidden="true"></div>

                <!-- Big Headline -->
                <h1 class="admin-left-headline">
                    Admin <span class="admin-portal-italic">Portal</span>
                </h1>

                <!-- Subtitle -->
                <p class="admin-left-desc">
                    Manage orders, products, inventory and your business &mdash; all in one place.
                </p>

                <!-- 4 Feature Pills / Cards -->
                <div class="admin-features-grid" role="list">
                    <!-- 1. Orders -->
                    <div class="admin-feature-card" role="listitem">
                        <div class="admin-feature-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                <line x1="12" y1="22.08" x2="12" y2="12"></line>
                            </svg>
                        </div>
                        <span class="admin-feature-label">Orders</span>
                    </div>

                    <!-- 2. Products -->
                    <div class="admin-feature-card" role="listitem">
                        <div class="admin-feature-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                <polyline points="2 17 12 22 22 17"></polyline>
                                <polyline points="2 12 12 17 22 12"></polyline>
                            </svg>
                        </div>
                        <span class="admin-feature-label">Products</span>
                    </div>

                    <!-- 3. Inventory -->
                    <div class="admin-feature-card" role="listitem">
                        <div class="admin-feature-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="20" x2="18" y2="10"></line>
                                <line x1="12" y1="20" x2="12" y2="4"></line>
                                <line x1="6" y1="20" x2="6" y2="14"></line>
                            </svg>
                        </div>
                        <span class="admin-feature-label">Inventory</span>
                    </div>

                    <!-- 4. Reports -->
                    <div class="admin-feature-card" role="listitem">
                        <div class="admin-feature-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <span class="admin-feature-label">Reports</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ==================================================================
             RIGHT PANEL: WARM IVORY BACKGROUND & FLOATING LOGIN CARD
             ================================================================== -->
        <main class="admin-right-stage">
            
            <!-- Delicate Botanical Leaf Branch (Top-Right Accent) -->
            <div class="admin-corner-branch" aria-hidden="true">
                <img src="../assets/images/about-top-branch.png" alt="" loading="eager">
            </div>

            <!-- Floating White Login Card -->
            <section class="admin-login-card" aria-label="Sign in to Admin Account">
                
                <!-- Card Brand Header -->
                <div class="admin-card-header">
                    <div class="admin-card-sprout" aria-hidden="true">
                        <svg viewBox="0 0 40 28" fill="none">
                            <path d="M20 25C20 18 14 9 2 7C2 19 11 25 20 25Z" fill="#2E6B42"/>
                            <path d="M20 25C20 18 26 9 38 7C38 19 29 25 20 25Z" fill="#1E5736"/>
                            <path d="M20 28V24" stroke="#1E5736" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="admin-card-brand-name">Nutronyx<sup>&reg;</sup></div>

                    <!-- Divider Flanked by Fine Rules -->
                    <div class="admin-login-divider-row" aria-hidden="true">
                        <span class="admin-divider-line"></span>
                        <span class="admin-login-subtitle">Admin Login</span>
                        <span class="admin-divider-line"></span>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($errorMessage)): ?>
                    <div class="admin-alert admin-alert-error" role="alert">
                        <svg class="admin-alert-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($successMessage)): ?>
                    <div class="admin-alert admin-alert-success" role="status">
                        <svg class="admin-alert-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <span><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <!-- Sign In Form -->
                <form action="login.php" method="POST" class="admin-form" autocomplete="on">
                    
                    <!-- 1. Email Address -->
                    <div class="admin-form-group">
                        <label for="admin-email" class="admin-label">Email Address</label>
                        <div class="admin-input-wrap">
                            <span class="admin-input-icon" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                            </span>
                            <input 
                                type="email" 
                                id="admin-email" 
                                name="email" 
                                class="admin-input" 
                                placeholder="Enter your email" 
                                value="<?= $emailValue ?>" 
                                required 
                                autofocus
                            >
                        </div>
                    </div>

                    <!-- 2. Password -->
                    <div class="admin-form-group">
                        <label for="admin-password" class="admin-label">Password</label>
                        <div class="admin-input-wrap">
                            <span class="admin-input-icon" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            </span>
                            <input 
                                type="password" 
                                id="admin-password" 
                                name="password" 
                                class="admin-input" 
                                placeholder="Enter your password" 
                                required
                            >
                            <button 
                                type="button" 
                                class="admin-password-toggle" 
                                id="togglePasswordBtn" 
                                aria-label="Toggle password visibility"
                                title="Show / hide password"
                            >
                                <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- 3. Form Utilities: Remember Me & Forgot Password -->
                    <div class="admin-form-row">
                        <label class="admin-checkbox-label">
                            <input type="checkbox" name="remember" class="admin-checkbox" checked>
                            <span>Remember me</span>
                        </label>
                        <a href="forgot-password.php" class="admin-forgot-link">Forgot password?</a>
                    </div>

                    <!-- 4. Submit Button -->
                    <button type="submit" class="admin-submit-btn">
                        <span>Log In</span>
                        <span class="admin-btn-arrow" aria-hidden="true">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </span>
                    </button>
                </form>

                <!-- Helpful Demo Credentials Pill -->
                <div class="admin-demo-hint">
                    <div>
                        <span>Credentials: </span>
                        <span class="admin-demo-creds">svgdryfruits@gmail.com</span> / <span class="admin-demo-creds">Mohith@123</span>
                    </div>
                    <button type="button" class="admin-autofill-btn" id="autofillBtn">Auto-fill</button>
                </div>

            </section>
        </main>

    </div>

    <!-- Interactive Scripts: Password Toggle & Autofill Helper -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const passwordInput = document.getElementById("admin-password");
        const toggleBtn = document.getElementById("togglePasswordBtn");
        const eyeIcon = document.getElementById("eyeIcon");
        const autofillBtn = document.getElementById("autofillBtn");
        const emailInput = document.getElementById("admin-email");

        // Toggle Password Visibility
        if (toggleBtn && passwordInput && eyeIcon) {
            toggleBtn.addEventListener("click", () => {
                const isPassword = (passwordInput.type === "password");
                passwordInput.type = isPassword ? "text" : "password";
                
                // Switch Eye Icon between open and slashed
                if (isPassword) {
                    eyeIcon.innerHTML = `
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    `;
                } else {
                    eyeIcon.innerHTML = `
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    `;
                }
            });
        }

        // Demo Autofill Helper
        if (autofillBtn && emailInput && passwordInput) {
            autofillBtn.addEventListener("click", () => {
                emailInput.value = "svgdryfruits@gmail.com";
                passwordInput.value = "Mohith@123";
                emailInput.focus();
            });
        }
    });
    </script>
</body>
</html>
