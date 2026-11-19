<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\RoleService;

AuthService::requireAuth();

RoleService::ensureTables();

$activePage = 'roles';
$activeWebsite = Context::getWebsite();
$notice = '';
$error = '';
$db = Database::getConnection();

// Check logged in user authority
$currentUser = AuthService::user();
$currentUserRole = strtoupper($currentUser['role'] ?? 'STORE_MANAGER');
$isSuperAdmin = ($currentUserRole === 'SUPER_ADMIN');

// Strictly block Store Manager or non-super-admin from mutating administrative data
if (!$isSuperAdmin && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
    http_response_code(403);
    die('Access Denied: Store Manager is not permitted to perform administrative actions. Please contact the Super Admin.');
}

// POST Action Handlers
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'create_admin_user') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = trim($_POST['role'] ?? 'OPERATIONS');
            $phone = trim($_POST['phone'] ?? '');
            $storeAccessType = $_POST['store_access_type'] ?? 'ALL';
            $allowedStores = $_POST['allowed_stores'] ?? [];
            if (!is_array($allowedStores)) {
                $allowedStores = [$allowedStores];
            }

            if (empty($name) || empty($email) || empty($password)) {
                throw new \InvalidArgumentException("Name, Email, and Password are required.");
            }

            RoleService::createUser([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'phone' => $phone,
                'store_access_type' => $storeAccessType,
                'allowed_stores' => $allowedStores,
                'status' => 'ACTIVE'
            ]);
            $notice = "Admin user {$email} ({$name}) has been successfully created.";

        } elseif ($action === 'invite_staff_user') {
            $email = trim($_POST['email'] ?? '');
            $role = trim($_POST['role'] ?? 'OPERATIONS');
            $storeAccessType = $_POST['store_access_type'] ?? 'ALL';
            $allowedStores = $_POST['allowed_stores'] ?? [];
            if (!is_array($allowedStores)) {
                $allowedStores = [$allowedStores];
            }
            $message = trim($_POST['message'] ?? '');

            if (empty($email)) {
                throw new \InvalidArgumentException("Email address is required to invite staff.");
            }

            $invitation = RoleService::inviteUser($email, $role, $storeAccessType, $allowedStores, $message);
            $notice = "Staff invitation link generated for {$email}: {$invitation['invite_url']}";

        } elseif ($action === 'update_admin_user') {
            $userId = trim($_POST['user_id'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = trim($_POST['role'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $storeAccessType = $_POST['store_access_type'] ?? 'ALL';
            $allowedStores = $_POST['allowed_stores'] ?? [];
            if (!is_array($allowedStores)) {
                $allowedStores = [$allowedStores];
            }
            $status = trim($_POST['status'] ?? 'ACTIVE');

            if (empty($userId) || empty($name) || empty($email)) {
                throw new \InvalidArgumentException("User ID, Name, and Email are required.");
            }

            RoleService::updateUser($userId, [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'phone' => $phone,
                'store_access_type' => $storeAccessType,
                'allowed_stores' => $allowedStores,
                'status' => $status
            ]);
            $notice = "User account {$email} has been updated.";

        } elseif ($action === 'toggle_user_status') {
            $userId = trim($_POST['user_id'] ?? '');
            $newStatus = trim($_POST['new_status'] ?? '');
            RoleService::setUserStatus($userId, $newStatus);
            $notice = "User status successfully changed to {$newStatus}.";

        } elseif ($action === 'reset_user_password') {
            $userId = trim($_POST['user_id'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');
            if (empty($newPassword)) {
                $newPassword = 'Nutronyx#' . substr(md5(uniqid('', true)), 0, 8);
            }
            RoleService::resetPassword($userId, $newPassword);
            $notice = "Password for user has been reset to: {$newPassword}";

        } elseif ($action === 'delete_user') {
            $userId = trim($_POST['user_id'] ?? '');
            RoleService::deleteUser($userId);
            $notice = "User account has been permanently removed.";

        } elseif ($action === 'bulk_action') {
            $bulkType = $_POST['bulk_type'] ?? '';
            $selectedUsers = $_POST['selected_users'] ?? [];
            if (!is_array($selectedUsers) || empty($selectedUsers)) {
                throw new \InvalidArgumentException("No users were selected for bulk action.");
            }

            $count = 0;
            if ($bulkType === 'suspend') {
                foreach ($selectedUsers as $uid) {
                    try {
                        RoleService::setUserStatus($uid, 'SUSPENDED');
                        $count++;
                    } catch (\Throwable $e) {}
                }
                $notice = "Successfully suspended {$count} selected user accounts.";
            } elseif ($bulkType === 'activate') {
                foreach ($selectedUsers as $uid) {
                    try {
                        RoleService::setUserStatus($uid, 'ACTIVE');
                        $count++;
                    } catch (\Throwable $e) {}
                }
                $notice = "Successfully activated {$count} selected user accounts.";
            } elseif ($bulkType === 'change_role') {
                $targetRole = trim($_POST['bulk_role'] ?? '');
                if ($targetRole) {
                    foreach ($selectedUsers as $uid) {
                        try {
                            RoleService::updateUser($uid, ['role' => $targetRole]);
                            $count++;
                        } catch (\Throwable $e) {}
                    }
                    $notice = "Updated role to {$targetRole} for {$count} users.";
                }
            }
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

// Active View (Only Admin Users exists now)
$activeTab = 'users';

// Fetch KPIs
$kpis = RoleService::getGovernanceKpis();

// Users Filters & Pagination
$userSearch = trim($_GET['search'] ?? '');
$userRoleFilter = trim($_GET['role'] ?? '');
$userStatusFilter = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 10)));

$userFilters = [
    'search' => $userSearch,
    'role' => $userRoleFilter,
    'status' => $userStatusFilter,
    'limit' => $perPage,
    'offset' => ($page - 1) * $perPage
];
$usersData = RoleService::getUsers($userFilters);
$usersList = $usersData['items'];
$totalUsersCount = $usersData['total'];
$totalPages = max(1, (int)ceil($totalUsersCount / $perPage));

// Roles for select dropdowns
$allRoles = RoleService::getRoles();
$rolesByKey = [];
foreach ($allRoles as $r) {
    $rolesByKey[$r['id']] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles & Security Governance &mdash; Nutronyx Admin Core</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../assets/css/admin.css?v=20261119_white">
    <style>
        :root {
            --gov-primary: #144D29;
            --gov-primary-hover: #0E371D;
            --gov-primary-light: #EBF7EE;
            --gov-text-dark: #1F2937;
            --gov-text-muted: #6B7280;
            --gov-border: #E5E7EB;
            --gov-card-bg: #FFFFFF;
            --gov-badge-green-bg: #DCFCE7;
            --gov-badge-green-text: #166534;
            --gov-badge-mint-bg: #DCFCE7;
            --gov-badge-mint-text: #15803D;
            --gov-badge-lavender-bg: #F3E8FF;
            --gov-badge-lavender-text: #7E22CE;
            --gov-badge-sky-bg: #E0F2FE;
            --gov-badge-sky-text: #0284C7;
            --gov-badge-amber-bg: #FEF3C7;
            --gov-badge-amber-text: #D97706;
            --gov-badge-rose-bg: #FEE2E2;
            --gov-badge-rose-text: #DC2626;
        }

        .gov-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .gov-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 32px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 4px 0;
            line-height: 1.2;
        }

        .gov-subtitle {
            font-size: 14px;
            color: var(--gov-text-muted);
            margin: 0;
        }

        .gov-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-gov-primary {
            background-color: var(--gov-primary);
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            text-decoration: none;
        }
        .btn-gov-primary:hover {
            background-color: var(--gov-primary-hover);
            transform: translateY(-1px);
        }

        .btn-gov-secondary {
            background: #FFFFFF;
            color: #374151;
            border: 1px solid var(--gov-border);
            border-radius: 8px;
            padding: 9px 16px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-gov-secondary:hover {
            background: #F9FAFB;
            border-color: #D1D5DB;
        }

        /* KPI Cards Grid Matching media_1794579148003.jpg */
        .gov-kpis-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        @media (max-width: 1024px) {
            .gov-kpis-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 640px) {
            .gov-kpis-grid {
                grid-template-columns: 1fr;
            }
        }

        .gov-kpi-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .gov-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .gov-kpi-icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .gov-kpi-icon-wrap.green {
            background-color: #ECFDF5;
            color: #10B981;
        }
        .gov-kpi-icon-wrap.mint {
            background-color: #F0FDF4;
            color: #16A34A;
        }
        .gov-kpi-icon-wrap.amber {
            background-color: #FEF3C7;
            color: #D97706;
        }
        .gov-kpi-icon-wrap.rose {
            background-color: #FEE2E2;
            color: #DC2626;
        }

        .gov-kpi-label {
            font-size: 13px;
            font-weight: 500;
            color: #6B7280;
            margin-bottom: 4px;
        }
        .gov-kpi-val {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            line-height: 1.1;
        }

        /* 5-Tab Segmented Navigation Bar */
        .gov-tabs-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #FFFFFF;
            border: 1px solid var(--gov-border);
            border-radius: 10px;
            padding: 6px;
            margin-bottom: 24px;
            overflow-x: auto;
        }

        .gov-tab-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            font-size: 14px;
            font-weight: 600;
            color: #4B5563;
            border-radius: 7px;
            text-decoration: none;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .gov-tab-item:hover {
            color: #111827;
            background: #F3F4F6;
        }
        .gov-tab-item.active {
            background-color: var(--gov-primary);
            color: #FFFFFF;
        }
        .gov-tab-item.active svg {
            color: #FFFFFF;
            stroke: #FFFFFF;
        }

        /* Table Card Container */
        .gov-card {
            background: #FFFFFF;
            border: 1px solid var(--gov-border);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .gov-card-header {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            border-bottom: 1px solid var(--gov-border);
        }

        .gov-card-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 2px 0;
        }

        .gov-card-subtitle {
            font-size: 13px;
            color: var(--gov-text-muted);
            margin: 0;
        }

        .gov-filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .gov-search-wrap {
            position: relative;
        }
        .gov-search-wrap svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
        }
        .gov-search-input {
            padding: 8px 14px 8px 36px;
            border: 1px solid var(--gov-border);
            border-radius: 8px;
            font-size: 13.5px;
            background: #F9FAFB;
            width: 240px;
            outline: none;
            transition: all 0.2s ease;
        }
        .gov-search-input:focus {
            background: #FFFFFF;
            border-color: var(--gov-primary);
            box-shadow: 0 0 0 3px rgba(20, 77, 41, 0.1);
        }

        .gov-select-filter {
            padding: 8px 12px;
            border: 1px solid var(--gov-border);
            border-radius: 8px;
            font-size: 13.5px;
            color: #374151;
            background: #FFFFFF;
            outline: none;
            cursor: pointer;
        }
        .gov-select-filter:focus {
            border-color: var(--gov-primary);
        }

        /* Bulk Actions Toolbar */
        .gov-bulk-toolbar {
            background: #F0FDF4;
            border-bottom: 1px solid #DCFCE7;
            padding: 10px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 13.5px;
        }

        /* Table Styling */
        .gov-table-wrap {
            width: 100%;
            overflow-x: auto;
        }
        .gov-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }
        .gov-table th {
            background: #F9FAFB;
            color: #4B5563;
            font-weight: 600;
            padding: 12px 18px;
            border-bottom: 1px solid var(--gov-border);
            white-space: nowrap;
        }
        .gov-table td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--gov-border);
            vertical-align: middle;
            color: #1F2937;
        }
        .gov-table tr:hover td {
            background-color: #FBFDFB;
        }

        /* Avatar Pill */
        .gov-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
        }

        /* Role Badge Styling */
        .role-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        .role-pill.super-admin {
            background-color: var(--gov-badge-green-bg);
            color: var(--gov-badge-green-text);
        }
        .role-pill.store-manager {
            background-color: var(--gov-badge-mint-bg);
            color: var(--gov-badge-mint-text);
        }
        .role-pill.operations {
            background-color: var(--gov-badge-mint-bg);
            color: var(--gov-badge-mint-text);
        }
        .role-pill.marketing {
            background-color: var(--gov-badge-lavender-bg);
            color: var(--gov-badge-lavender-text);
        }
        .role-pill.inventory {
            background-color: var(--gov-badge-sky-bg);
            color: var(--gov-badge-sky-text);
        }
        .role-pill.support {
            background-color: var(--gov-badge-amber-bg);
            color: var(--gov-badge-amber-text);
        }
        .role-pill.custom {
            background-color: #F3F4F6;
            color: #374151;
            border: 1px solid #E5E7EB;
        }

        /* Store Access Badge */
        .store-access-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 500;
            color: #374151;
        }

        /* Account Status Pill */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pill .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }
        .status-pill.active {
            background-color: #F0FDF4;
            color: #166534;
        }
        .status-pill.active .dot {
            background-color: #16A34A;
        }
        .status-pill.suspended {
            background-color: #FEF2F2;
            color: #991B1B;
        }
        .status-pill.suspended .dot {
            background-color: #DC2626;
        }
        .status-pill.invited {
            background-color: #FFFBEB;
            color: #B45309;
        }
        .status-pill.invited .dot {
            background-color: #F59E0B;
        }

        /* Action Menu Dropdown */
        .action-menu-btn {
            background: transparent;
            border: 1px solid transparent;
            border-radius: 6px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #6B7280;
            transition: all 0.2s;
        }
        .action-menu-btn:hover {
            background: #F3F4F6;
            border-color: #E5E7EB;
            color: #111827;
        }

        .dropdown-menu {
            position: absolute;
            right: 0;
            top: 100%;
            background: #FFFFFF;
            border: 1px solid var(--gov-border);
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            min-width: 190px;
            z-index: 50;
            padding: 6px 0;
            display: none;
        }
        .dropdown-menu.show {
            display: block;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 8px 16px;
            font-size: 13px;
            color: #374151;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
        }
        .dropdown-item:hover {
            background-color: #F9FAFB;
            color: #111827;
        }
        .dropdown-item.danger {
            color: #DC2626;
        }
        .dropdown-item.danger:hover {
            background-color: #FEF2F2;
        }
        .dropdown-divider {
            height: 1px;
            background-color: #E5E7EB;
            margin: 4px 0;
        }

        /* Pagination Footer */
        .gov-pagination-bar {
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid var(--gov-border);
            flex-wrap: wrap;
            gap: 16px;
            font-size: 13.5px;
            color: #4B5563;
        }

        .gov-pagination-controls {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .gov-page-btns {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .page-btn {
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: 1px solid #E5E7EB;
            background: #FFFFFF;
            color: #374151;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .page-btn:hover {
            background: #F9FAFB;
            border-color: #D1D5DB;
        }
        .page-btn.active {
            background-color: var(--gov-primary);
            border-color: var(--gov-primary);
            color: #FFFFFF;
            font-weight: 600;
        }
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Slide-out Drawer */
        .gov-drawer-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .gov-drawer-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

        .gov-drawer {
            position: fixed;
            top: 0;
            right: -480px;
            width: 460px;
            max-width: 90vw;
            height: 100vh;
            background: #FFFFFF;
            box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15);
            z-index: 101;
            transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .gov-drawer.active {
            right: 0;
        }

        .gov-drawer-header {
            padding: 20px 24px;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .gov-drawer-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        .gov-drawer-footer {
            padding: 16px 24px;
            border-top: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            background: #F9FAFB;
        }

        /* Modal Overlays */
        .gov-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 110;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .gov-modal-backdrop.active {
            display: flex;
        }
        .gov-modal-content {
            background: #FFFFFF;
            border-radius: 14px;
            width: 580px;
            max-width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .gov-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .gov-modal-body {
            padding: 24px;
        }
        .gov-modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            background: #F9FAFB;
            border-bottom-left-radius: 14px;
            border-bottom-right-radius: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 13.5px;
            color: #111827;
            background: #FFFFFF;
            outline: none;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--gov-primary);
            box-shadow: 0 0 0 3px rgba(20, 77, 41, 0.1);
        }
        .form-hint {
            font-size: 12px;
            color: #6B7280;
            margin-top: 4px;
        }

        /* Matrix Checkbox Styling */
        .perm-grid-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .perm-grid-table th {
            background: #F9FAFB;
            padding: 10px 14px;
            border: 1px solid #E5E7EB;
            text-align: center;
            font-weight: 600;
        }
        .perm-grid-table th:first-child {
            text-align: left;
        }
        .perm-grid-table td {
            padding: 10px 14px;
            border: 1px solid #E5E7EB;
            text-align: center;
        }
        .perm-grid-table td:first-child {
            text-align: left;
            font-weight: 500;
        }
        .perm-checkbox {
            width: 16px;
            height: 16px;
            accent-color: var(--gov-primary);
            cursor: pointer;
        }

        /* Store Manager Translucent & Locked Screen Styles */
        .gov-content-locked {
            filter: blur(5px);
            opacity: 0.35;
            pointer-events: none;
            user-select: none;
            -webkit-user-select: none;
        }
        .gov-lock-screen-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(243, 244, 246, 0.45);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            padding: 24px;
        }
        .gov-lock-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 20px;
            padding: 40px 36px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.18);
            position: relative;
            z-index: 100000;
            animation: govLockPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes govLockPop {
            0% { opacity: 0; transform: scale(0.95) translateY(10px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }
        .gov-lock-icon {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: #FEF3C7;
            color: #D97706;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            box-shadow: 0 6px 16px rgba(217, 119, 6, 0.18);
        }
        .gov-lock-tag {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #B45309;
            background: #FEF3C7;
            padding: 4px 12px;
            border-radius: 9999px;
            margin-bottom: 12px;
        }
        .gov-lock-title {
            font-size: 22px;
            font-weight: 800;
            color: #111827;
            margin: 0 0 10px;
            font-family: var(--admin-font-serif, inherit);
        }
        .gov-lock-desc {
            font-size: 14.5px;
            color: #4B5563;
            line-height: 1.6;
            margin: 0 0 26px;
        }
    </style>
</head>
<body class="admin-body">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="admin-layout-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main-stage" style="position: relative;">
            <div class="gov-content-wrap <?= !$isSuperAdmin ? 'gov-content-locked' : '' ?>">
                
                <!-- Page Header Matching media_1794579148003.jpg -->
                <div class="gov-header">
                    <div>
                        <h1 class="gov-title">Roles & Security Governance</h1>
                        <p class="gov-subtitle">Manage admin users, roles, permissions, store access, and monitor account activity.</p>
                    </div>
                    <div class="gov-header-actions">
                        <button type="button" class="btn-gov-secondary" onclick="openInviteModal()">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            <span>Invite Staff</span>
                        </button>
                        <button type="button" class="btn-gov-primary" onclick="openCreateUserModal()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            <span>Create New Admin User</span>
                        </button>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (!empty($notice)): ?>
                    <div class="dash-panel" style="background: #F0FDF4; border: 1px solid #DCFCE7; color: #166534; padding: 14px 18px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 10px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span><?= htmlspecialchars($notice) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="dash-panel" style="background: #FEF2F2; border: 1px solid #FEE2E2; color: #991B1B; padding: 14px 18px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 10px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- 4 KPI Summary Cards Matching media_1794579148003.jpg -->
                <div class="gov-kpis-grid">
                    <!-- Total Admin Users -->
                    <div class="gov-kpi-card">
                        <div class="gov-kpi-icon-wrap green">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        </div>
                        <div>
                            <div class="gov-kpi-label">Total Admin Users</div>
                            <div class="gov-kpi-val"><?= (int)$kpis['total_users'] ?></div>
                        </div>
                    </div>

                    <!-- Active Users -->
                    <div class="gov-kpi-card">
                        <div class="gov-kpi-icon-wrap mint">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        </div>
                        <div>
                            <div class="gov-kpi-label">Active Users</div>
                            <div class="gov-kpi-val"><?= (int)$kpis['active_users'] ?></div>
                        </div>
                    </div>

                    <!-- Roles -->
                    <div class="gov-kpi-card">
                        <div class="gov-kpi-icon-wrap amber">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                        </div>
                        <div>
                            <div class="gov-kpi-label">Roles</div>
                            <div class="gov-kpi-val"><?= (int)$kpis['roles_count'] ?></div>
                        </div>
                    </div>

                    <!-- Suspended Users -->
                    <div class="gov-kpi-card">
                        <div class="gov-kpi-icon-wrap rose">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                        </div>
                        <div>
                            <div class="gov-kpi-label">Suspended Users</div>
                            <div class="gov-kpi-val"><?= (int)$kpis['suspended_users'] ?></div>
                        </div>
                    </div>
                </div>

                <!-- ADMIN USERS VIEW -->
                <div class="gov-card">
                    <div class="gov-card-header">
                        <div>
                            <h2 class="gov-card-title">Admin Users (<?= $totalUsersCount ?>)</h2>
                            <p class="gov-card-subtitle">Manage admin and staff accounts, their assigned roles, and login access.</p>
                        </div>
                        <form method="GET" action="roles.php" class="gov-filter-group">
                            <input type="hidden" name="tab" value="users">
                            <div class="gov-search-wrap">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                <input type="text" name="search" value="<?= htmlspecialchars($userSearch) ?>" placeholder="Search by name or email..." class="gov-search-input">
                            </div>
                            <select name="role" class="gov-select-filter" onchange="this.form.submit()">
                                <option value="">All Roles</option>
                                <?php foreach ($allRoles as $role): ?>
                                    <option value="<?= htmlspecialchars($role['id']) ?>" <?= $userRoleFilter === $role['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status" class="gov-select-filter" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="ACTIVE" <?= $userStatusFilter === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                                <option value="SUSPENDED" <?= $userStatusFilter === 'SUSPENDED' ? 'selected' : '' ?>>Suspended</option>
                                <option value="INVITED" <?= $userStatusFilter === 'INVITED' ? 'selected' : '' ?>>Invited</option>
                            </select>
                            <?php if ($userSearch || $userRoleFilter || $userStatusFilter): ?>
                                <a href="?tab=users" class="btn-gov-secondary" style="padding: 7px 12px; font-size: 13px;">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Bulk Actions Toolbar -->
                    <form id="bulkForm" method="POST" action="roles.php?tab=users">
                        <input type="hidden" name="action" value="bulk_action">
                        <input type="hidden" name="bulk_type" id="bulkActionType" value="">
                        <input type="hidden" name="bulk_role" id="bulkActionRole" value="">

                        <div id="bulkToolbar" class="gov-bulk-toolbar" style="display: none;">
                            <div style="font-weight: 600; color: #166534;">
                                <span id="selectedCount">0</span> users selected
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="btn-gov-secondary" style="font-size: 12.5px; padding: 6px 12px;" onclick="triggerBulk('activate')">Activate Selected</button>
                                <button type="button" class="btn-gov-secondary" style="font-size: 12.5px; padding: 6px 12px; color: #DC2626;" onclick="triggerBulk('suspend')">Suspend Selected</button>
                                <button type="button" class="btn-gov-secondary" style="font-size: 12.5px; padding: 6px 12px;" onclick="triggerBulkRoleModal()">Change Role</button>
                                <button type="button" class="btn-gov-secondary" style="font-size: 12.5px; padding: 6px 12px;" onclick="deselectAllUsers()">Deselect All</button>
                            </div>
                        </div>

                        <!-- Table Matching Image -->
                        <div class="gov-table-wrap">
                            <table class="gov-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;">
                                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" style="cursor: pointer; accent-color: var(--gov-primary);">
                                        </th>
                                        <th>Staff Member</th>
                                        <th>Email Address</th>
                                        <th>Assigned Role</th>
                                        <th>Account Status</th>
                                        <th>Last Login</th>
                                        <th>Date Created</th>
                                        <th style="text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($usersList)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 40px 20px; color: #6B7280;">
                                                No admin users found matching the selected filters.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($usersList as $usr): 
                                            $firstLetter = strtoupper(substr($usr['name'] ?? $usr['email'], 0, 1));
                                            $avatarBg = $usr['avatar_color'] ?? '#E0F2FE';
                                            
                                            // Format role badge class & label
                                            $roleKey = strtoupper($usr['role'] ?? 'STORE_MANAGER');
                                            $roleClass = ($roleKey === 'SUPER_ADMIN') ? 'super-admin' : 'store-manager';
                                            $roleLabel = ($roleKey === 'SUPER_ADMIN') ? 'Super Admin' : 'Store Manager';

                                            // Status formatting
                                            $statusKey = strtoupper($usr['status'] ?? 'ACTIVE');
                                            $statusClass = 'active';
                                            $statusText = 'Active';
                                            if ($statusKey === 'SUSPENDED') {
                                                $statusClass = 'suspended';
                                                $statusText = 'Suspended';
                                            } elseif ($statusKey === 'INVITED') {
                                                $statusClass = 'invited';
                                                $statusText = 'Invited';
                                            }

                                            // Formatted Dates
                                            $createdDateFormatted = !empty($usr['created_at']) ? date('d M, Y', strtotime($usr['created_at'])) : '—';
                                            $lastLoginFormatted = 'Never';
                                            if (!empty($usr['last_login_at'])) {
                                                $ts = strtotime($usr['last_login_at']);
                                                $today = strtotime('today');
                                                $yesterday = strtotime('yesterday');
                                                if ($ts >= $today) {
                                                    $lastLoginFormatted = 'Today, ' . date('h:i A', $ts);
                                                } elseif ($ts >= $yesterday) {
                                                    $lastLoginFormatted = 'Yesterday, ' . date('h:i A', $ts);
                                                } else {
                                                    $lastLoginFormatted = date('d M, Y', $ts);
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td style="text-align: center;">
                                                    <input type="checkbox" name="selected_users[]" value="<?= htmlspecialchars($usr['id']) ?>" class="user-row-checkbox" onchange="updateSelectedCount()" style="cursor: pointer; accent-color: var(--gov-primary);">
                                                </td>
                                                <td>
                                                    <div style="display: flex; align-items: center; gap: 12px;">
                                                        <div class="gov-avatar" style="background-color: <?= htmlspecialchars($avatarBg) ?>; color: #166534;">
                                                            <?= $firstLetter ?>
                                                        </div>
                                                        <span style="font-weight: 600; color: #111827;">
                                                            <?= htmlspecialchars($usr['name']) ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td style="color: #4B5563;">
                                                    <?= htmlspecialchars($usr['email']) ?>
                                                </td>
                                                <td>
                                                    <span class="role-pill <?= $roleClass ?>">
                                                        <?= htmlspecialchars($roleLabel) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-pill <?= $statusClass ?>">
                                                        <span class="dot"></span>
                                                        <span><?= $statusText ?></span>
                                                    </span>
                                                </td>
                                                <td style="color: #4B5563; font-size: 13px;">
                                                    <?= htmlspecialchars($lastLoginFormatted) ?>
                                                </td>
                                                <td style="color: #4B5563; font-size: 13px;">
                                                    <?= htmlspecialchars($createdDateFormatted) ?>
                                                </td>
                                                <td style="text-align: center; position: relative;">
                                                    <button type="button" class="action-menu-btn" onclick="toggleActionMenu(event, 'menu-<?= $usr['id'] ?>')">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
                                                    </button>
                                                    
                                                    <!-- Dropdown Menu -->
                                                    <div id="menu-<?= $usr['id'] ?>" class="dropdown-menu">
                                                        <button type="button" class="dropdown-item" onclick="openUserDrawer(<?= htmlspecialchars(json_encode($usr), ENT_QUOTES, 'UTF-8') ?>)">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                                            <span>View Details</span>
                                                        </button>
                                                        <button type="button" class="dropdown-item" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($usr), ENT_QUOTES, 'UTF-8') ?>)">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                            <span>Edit User</span>
                                                        </button>
                                                        <button type="button" class="dropdown-item" onclick="openResetPasswordModal('<?= $usr['id'] ?>', '<?= htmlspecialchars($usr['name'], ENT_QUOTES) ?>')">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                                            <span>Reset Password</span>
                                                        </button>
                                                        <div class="dropdown-divider"></div>
                                                        <?php if ($statusKey === 'ACTIVE'): ?>
                                                            <button type="button" class="dropdown-item" style="color: #D97706;" onclick="submitSingleAction('toggle_user_status', {user_id: '<?= $usr['id'] ?>', new_status: 'SUSPENDED'}, 'Are you sure you want to suspend <?= htmlspecialchars($usr['name']) ?>?')">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                                                                <span>Suspend Account</span>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="dropdown-item" style="color: #16A34A;" onclick="submitSingleAction('toggle_user_status', {user_id: '<?= $usr['id'] ?>', new_status: 'ACTIVE'})">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                                <span>Activate Account</span>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="dropdown-item danger" onclick="submitSingleAction('delete_user', {user_id: '<?= $usr['id'] ?>'}, 'Are you sure you want to delete this admin account permanently?')">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                            <span>Delete User</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <!-- Table Footer with Pagination matching Image -->
                    <div class="gov-pagination-bar">
                        <div>
                            <?php 
                                $startRow = $totalUsersCount > 0 ? ($page - 1) * $perPage + 1 : 0;
                                $endRow = min($totalUsersCount, $page * $perPage);
                            ?>
                            Showing <?= $startRow ?>–<?= $endRow ?> of <?= $totalUsersCount ?> users
                        </div>
                        <div class="gov-pagination-controls">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span>Rows per page</span>
                                <select class="gov-select-filter" style="padding: 4px 8px;" onchange="location.href='?tab=users&page=1&per_page=' + this.value + '&search=<?= urlencode($userSearch) ?>&role=<?= urlencode($userRoleFilter) ?>&status=<?= urlencode($userStatusFilter) ?>'">
                                    <option value="5" <?= $perPage === 5 ? 'selected' : '' ?>>5</option>
                                    <option value="10" <?= $perPage === 10 ? 'selected' : '' ?>>10</option>
                                    <option value="25" <?= $perPage === 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
                                </select>
                            </div>
                            <div class="gov-page-btns">
                                <a href="?tab=users&page=<?= max(1, $page - 1) ?>&per_page=<?= $perPage ?>&search=<?= urlencode($userSearch) ?>&role=<?= urlencode($userRoleFilter) ?>&status=<?= urlencode($userStatusFilter) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                </a>
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <a href="?tab=users&page=<?= $p ?>&per_page=<?= $perPage ?>&search=<?= urlencode($userSearch) ?>&role=<?= urlencode($userRoleFilter) ?>&status=<?= urlencode($userStatusFilter) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>">
                                        <?= $p ?>
                                    </a>
                                <?php endfor; ?>
                                <a href="?tab=users&page=<?= min($totalPages, $page + 1) ?>&per_page=<?= $perPage ?>&search=<?= urlencode($userSearch) ?>&role=<?= urlencode($userRoleFilter) ?>&status=<?= urlencode($userStatusFilter) ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div> <!-- /.gov-content-wrap -->

            <?php if (!$isSuperAdmin): ?>
            <!-- Store Manager Locked Access Overlay -->
            <div class="gov-lock-screen-overlay">
                <div class="gov-lock-card">
                    <div class="gov-lock-icon">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <span class="gov-lock-tag">Restricted Access</span>
                    <h2 class="gov-lock-title">Access Restricted</h2>
                    <p class="gov-lock-desc">Store Manager is not having the access. Please contact the Super Admin.</p>
                    <a href="dashboard.php" class="btn-gov-primary" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px 20px; font-size: 14px; font-weight: 600; text-decoration: none; border-radius: 10px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                        <span>Return to Dashboard</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- MODAL 1: CREATE NEW ADMIN USER -->
    <div id="createUserModal" class="gov-modal-backdrop">
        <div class="gov-modal-content">
            <div class="gov-modal-header">
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #111827;">Create New Admin User</h3>
                <button type="button" class="action-menu-btn" onclick="closeModal('createUserModal')">&times;</button>
            </div>
            <form method="POST" action="roles.php?tab=users">
                <input type="hidden" name="action" value="create_admin_user">
                <div class="gov-modal-body">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Rahul Varma" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" required placeholder="e.g. rahul@nutronyx.com" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number (Optional)</label>
                        <input type="text" name="phone" placeholder="+91 98765 43210" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Administrative Role *</label>
                        <select name="role" id="newAdminUserRole" class="form-select" required onchange="updateRoleExplanation('newAdminUserRole', 'newAdminUserRoleDesc')">
                            <option value="STORE_MANAGER" selected>Store Manager (Daily Store Operations — Catalog, Orders, Inventory & CRM)</option>
                            <option value="SUPER_ADMIN">Super Admin (Full Root Access — Platform Settings, Governance & All Modules)</option>
                        </select>
                        <div id="newAdminUserRoleDesc" style="margin-top: 8px; font-size: 12px; color: #374151; background: #F3F4F6; padding: 10px 12px; border-radius: 8px; border: 1px solid #E5E7EB;">
                            <strong>What this role is for:</strong> Store Manager manages day-to-day operations: products, orders, stock adjustments, customer CRM, coupons, and reviews. Restricted from modifying system governance.
                        </div>
                    </div>
                    <input type="hidden" name="store_access_type" value="SPECIFIC">
                    <input type="hidden" name="allowed_stores[]" value="<?= htmlspecialchars($activeWebsite['id']) ?>">
                    <div class="form-group">
                        <label class="form-label">Initial Password *</label>
                        <input type="text" name="password" required value="Mohith@123" class="form-input">
                        <div class="form-hint">Staff member can reset their password upon initial login.</div>
                    </div>
                </div>
                <div class="gov-modal-footer">
                    <button type="button" class="btn-gov-secondary" onclick="closeModal('createUserModal')">Cancel</button>
                    <button type="submit" class="btn-gov-primary">Create User Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: INVITE STAFF USER -->
    <div id="inviteStaffModal" class="gov-modal-backdrop">
        <div class="gov-modal-content">
            <div class="gov-modal-header">
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #111827;">Invite Staff User</h3>
                <button type="button" class="action-menu-btn" onclick="closeModal('inviteStaffModal')">&times;</button>
            </div>
            <form method="POST" action="roles.php?tab=users">
                <input type="hidden" name="action" value="invite_staff_user">
                <div class="gov-modal-body">
                    <div class="form-group">
                        <label class="form-label">Staff Email Address *</label>
                        <input type="email" name="email" required placeholder="colleague@nutronyx.com" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assigned Role *</label>
                        <select name="role" id="inviteStaffRole" class="form-select" required onchange="updateRoleExplanation('inviteStaffRole', 'inviteStaffRoleDesc')">
                            <option value="STORE_MANAGER" selected>Store Manager (Daily Store Operations — Catalog, Orders, Inventory & CRM)</option>
                            <option value="SUPER_ADMIN">Super Admin (Full Root Access — Platform Settings, Governance & All Modules)</option>
                        </select>
                        <div id="inviteStaffRoleDesc" style="margin-top: 8px; font-size: 12px; color: #374151; background: #F3F4F6; padding: 10px 12px; border-radius: 8px; border: 1px solid #E5E7EB;">
                            <strong>What this role is for:</strong> Store Manager manages day-to-day operations: products, orders, stock adjustments, customer CRM, coupons, and reviews. Restricted from modifying system governance.
                        </div>
                    </div>
                    <input type="hidden" name="store_access_type" value="SPECIFIC">
                    <input type="hidden" name="allowed_stores[]" value="<?= htmlspecialchars($activeWebsite['id']) ?>">
                    <div class="form-group">
                        <label class="form-label">Personal Note / Welcome Message</label>
                        <textarea name="message" rows="3" class="form-textarea" placeholder="Welcome to the Nutronyx operational management team. Please use the link below to accept your invitation."></textarea>
                    </div>
                </div>
                <div class="gov-modal-footer">
                    <button type="button" class="btn-gov-secondary" onclick="closeModal('inviteStaffModal')">Cancel</button>
                    <button type="submit" class="btn-gov-primary">Send Invitation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: EDIT ADMIN USER -->
    <div id="editUserModal" class="gov-modal-backdrop">
        <div class="gov-modal-content">
            <div class="gov-modal-header">
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #111827;">Edit Admin User</h3>
                <button type="button" class="action-menu-btn" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <form method="POST" action="roles.php?tab=users">
                <input type="hidden" name="action" value="update_admin_user">
                <input type="hidden" name="user_id" id="editUserId" value="">
                <div class="gov-modal-body">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" id="editUserName" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" id="editUserEmail" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" id="editUserPhone" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Administrative Role *</label>
                        <select name="role" id="editUserRole" class="form-select" required onchange="updateRoleExplanation('editUserRole', 'editUserRoleDesc')">
                            <option value="STORE_MANAGER">Store Manager (Daily Store Operations — Catalog, Orders, Inventory & CRM)</option>
                            <option value="SUPER_ADMIN">Super Admin (Full Root Access — Platform Settings, Governance & All Modules)</option>
                        </select>
                        <div id="editUserRoleDesc" style="margin-top: 8px; font-size: 12px; color: #374151; background: #F3F4F6; padding: 10px 12px; border-radius: 8px; border: 1px solid #E5E7EB;">
                            <strong>What this role is for:</strong> Store Manager manages day-to-day operations: products, orders, stock adjustments, customer CRM, coupons, and reviews. Restricted from modifying system governance.
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select name="status" id="editUserStatus" class="form-select">
                            <option value="ACTIVE">Active</option>
                            <option value="SUSPENDED">Suspended</option>
                            <option value="INVITED">Invited</option>
                        </select>
                    </div>
                    <input type="hidden" name="store_access_type" value="SPECIFIC">
                    <input type="hidden" name="allowed_stores[]" value="<?= htmlspecialchars($activeWebsite['id']) ?>">
                </div>
                <div class="gov-modal-footer">
                    <button type="button" class="btn-gov-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                    <button type="submit" class="btn-gov-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 4: RESET PASSWORD -->
    <div id="resetPasswordModal" class="gov-modal-backdrop">
        <div class="gov-modal-content" style="max-width: 440px;">
            <div class="gov-modal-header">
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #111827;">Reset Password</h3>
                <button type="button" class="action-menu-btn" onclick="closeModal('resetPasswordModal')">&times;</button>
            </div>
            <form method="POST" action="roles.php?tab=users">
                <input type="hidden" name="action" value="reset_user_password">
                <input type="hidden" name="user_id" id="resetPwdUserId" value="">
                <div class="gov-modal-body">
                    <p style="font-size: 13.5px; color: #4B5563; margin-top: 0;">
                        Reset password for <strong id="resetPwdUserName">Staff Member</strong>.
                    </p>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="text" name="new_password" id="resetPwdInput" value="Mohith@123" class="form-input">
                        <button type="button" class="btn-gov-secondary" style="font-size: 12px; margin-top: 8px; padding: 4px 10px;" onclick="generateStrongPwd()">Generate Random Key</button>
                    </div>
                </div>
                <div class="gov-modal-footer">
                    <button type="button" class="btn-gov-secondary" onclick="closeModal('resetPasswordModal')">Cancel</button>
                    <button type="submit" class="btn-gov-primary">Confirm Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SLIDE-OUT DRAWER: USER PROFILE & ACTIVITY -->
    <div id="userDrawerBackdrop" class="gov-drawer-backdrop" onclick="closeUserDrawer()"></div>
    <div id="userDrawer" class="gov-drawer">
        <div class="gov-drawer-header">
            <div>
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #111827;" id="drawerName">User Profile</h3>
                <div style="font-size: 12.5px; color: #6B7280;" id="drawerEmail">user@nutronyx.com</div>
            </div>
            <button type="button" class="action-menu-btn" onclick="closeUserDrawer()">&times;</button>
        </div>
        <div class="gov-drawer-body">
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
                <div id="drawerAvatar" class="gov-avatar" style="width: 56px; height: 56px; font-size: 20px;">M</div>
                <div>
                    <h4 style="margin: 0 0 4px 0; font-size: 17px; font-weight: 700; color: #111827;" id="drawerTitle">Staff Member</h4>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span id="drawerRoleBadge" class="role-pill super-admin">Super Admin</span>
                        <span id="drawerStatusBadge" class="status-pill active"><span class="dot"></span> Active</span>
                    </div>
                </div>
            </div>

            <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                <div style="font-size: 12px; font-weight: 600; color: #6B7280; text-transform: uppercase; margin-bottom: 10px;">Security Credentials</div>
                <div style="font-size: 13px; color: #374151; line-height: 1.8;">
                    <div><strong>User ID:</strong> <code id="drawerUserId" style="font-family: monospace;">usr_mohith</code></div>
                    <div><strong>Phone:</strong> <span id="drawerPhone">—</span></div>
                    <div><strong>Store Scope:</strong> <span id="drawerStoreScope">All Stores</span></div>
                    <div><strong>Last Login:</strong> <span id="drawerLastLogin">Today, 11:24 AM</span></div>
                    <div><strong>Created:</strong> <span id="drawerCreated">12 Jan, 2025</span></div>
                </div>
            </div>

            <div>
                <h4 style="font-size: 14px; font-weight: 700; color: #111827; margin: 0 0 12px 0;">Recent Security Events</h4>
                <div id="drawerActivityList" style="font-size: 12.5px; color: #4B5563; line-height: 1.6;">
                    <div style="padding: 10px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; margin-bottom: 8px;">
                        <div style="font-weight: 600; color: #111827;">Successful authentication</div>
                        <div style="color: #6B7280; font-size: 11.5px;">IP: 127.0.0.1 &bull; Status: SUCCESS</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="gov-drawer-footer">
            <button type="button" class="btn-gov-secondary" onclick="closeUserDrawer()">Close</button>
        </div>
    </div>

    <!-- Hidden Form for Single Actions -->
    <form id="singleActionForm" method="POST" action="roles.php?tab=<?= $activeTab ?>" style="display: none;">
        <input type="hidden" name="action" id="saAction" value="">
        <div id="saPayload"></div>
    </form>

    <script>
        // Modal Controls
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function openCreateUserModal() {
            openModal('createUserModal');
        }
        function openInviteModal() {
            openModal('inviteStaffModal');
        }

        function toggleStoreCheckboxes(selectId, divId) {
            const val = document.getElementById(selectId).value;
            document.getElementById(divId).style.display = (val === 'SPECIFIC') ? 'block' : 'none';
        }

        // Action Dropdowns
        function toggleActionMenu(event, menuId) {
            event.stopPropagation();
            const menus = document.querySelectorAll('.dropdown-menu');
            menus.forEach(m => {
                if (m.id !== menuId) m.classList.remove('show');
            });
            const target = document.getElementById(menuId);
            if (target) target.classList.toggle('show');
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-menu') && !e.target.closest('.action-menu-btn')) {
                document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
            }
        });

        // Edit User Modal Populate
        function openEditUserModal(user) {
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUserName').value = user.name;
            document.getElementById('editUserEmail').value = user.email;
            document.getElementById('editUserPhone').value = user.phone || '';
            document.getElementById('editUserRole').value = user.role;
            document.getElementById('editUserStatus').value = user.status || 'ACTIVE';
            updateRoleExplanation('editUserRole', 'editUserRoleDesc');
            openModal('editUserModal');
        }

        function updateRoleExplanation(selectId, descId) {
            const sel = document.getElementById(selectId);
            const desc = document.getElementById(descId);
            if (!sel || !desc) return;
            if (sel.value === 'SUPER_ADMIN') {
                desc.innerHTML = '<strong>What this role is for:</strong> Super Admin has unrestricted root access across all modules, team user management, roles, audit logs, and platform settings.';
            } else {
                desc.innerHTML = '<strong>What this role is for:</strong> Store Manager manages day-to-day operations: products, orders, stock adjustments, customer CRM, coupons, and reviews. Restricted from modifying system governance.';
            }
        }

        // Reset Password Modal
        function openResetPasswordModal(userId, name) {
            document.getElementById('resetPwdUserId').value = userId;
            document.getElementById('resetPwdUserName').textContent = name;
            openModal('resetPasswordModal');
        }
        function generateStrongPwd() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
            let pwd = 'Nx#';
            for (let i = 0; i < 9; i++) {
                pwd += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('resetPwdInput').value = pwd;
        }

        // User Drawer
        function openUserDrawer(user) {
            document.getElementById('drawerName').textContent = user.name;
            document.getElementById('drawerEmail').textContent = user.email;
            document.getElementById('drawerTitle').textContent = user.name;
            document.getElementById('drawerUserId').textContent = user.id;
            document.getElementById('drawerPhone').textContent = user.phone || 'Not configured';
            document.getElementById('drawerStoreScope').textContent = (user.store_access_type === 'SPECIFIC') ? 'Specific Stores' : 'All Stores (Global)';
            document.getElementById('drawerLastLogin').textContent = user.last_login_at || 'Never';
            document.getElementById('drawerCreated').textContent = user.created_at || '—';

            const avatar = document.getElementById('drawerAvatar');
            avatar.textContent = (user.name || user.email).charAt(0).toUpperCase();
            avatar.style.backgroundColor = user.avatar_color || '#E0F2FE';

            const roleBadge = document.getElementById('drawerRoleBadge');
            roleBadge.textContent = user.role;
            roleBadge.className = 'role-pill ' + (user.role.toLowerCase());

            const statusBadge = document.getElementById('drawerStatusBadge');
            statusBadge.className = 'status-pill ' + (user.status.toLowerCase());
            statusBadge.innerHTML = '<span class="dot"></span> ' + user.status;

            document.getElementById('userDrawerBackdrop').classList.add('active');
            document.getElementById('userDrawer').classList.add('active');
        }

        function closeUserDrawer() {
            document.getElementById('userDrawerBackdrop').classList.remove('active');
            document.getElementById('userDrawer').classList.remove('active');
        }

        // Bulk Selection Logic
        function toggleSelectAll(master) {
            const checkboxes = document.querySelectorAll('.user-row-checkbox');
            checkboxes.forEach(cb => cb.checked = master.checked);
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checked = document.querySelectorAll('.user-row-checkbox:checked');
            const toolbar = document.getElementById('bulkToolbar');
            const countLabel = document.getElementById('selectedCount');
            if (checked.length > 0) {
                toolbar.style.display = 'flex';
                countLabel.textContent = checked.length;
            } else {
                toolbar.style.display = 'none';
                document.getElementById('selectAllCheckbox').checked = false;
            }
        }

        function deselectAllUsers() {
            document.querySelectorAll('.user-row-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAllCheckbox').checked = false;
            updateSelectedCount();
        }

        function triggerBulk(type) {
            if (confirm('Execute bulk ' + type + ' on selected users?')) {
                document.getElementById('bulkActionType').value = type;
                document.getElementById('bulkForm').submit();
            }
        }

        function triggerBulkRoleModal() {
            const role = prompt('Enter new role identifier for selected users (SUPER_ADMIN, STORE_MANAGER):');
            if (role) {
                document.getElementById('bulkActionType').value = 'change_role';
                document.getElementById('bulkActionRole').value = role.toUpperCase();
                document.getElementById('bulkForm').submit();
            }
        }

        // Single Actions Trigger
        function submitSingleAction(actionName, payload, confirmMsg) {
            if (confirmMsg && !confirm(confirmMsg)) return;
            const form = document.getElementById('singleActionForm');
            document.getElementById('saAction').value = actionName;
            const container = document.getElementById('saPayload');
            container.innerHTML = '';
            for (const key in payload) {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = key;
                inp.value = payload[key];
                container.appendChild(inp);
            }
            form.submit();
        }
    </script>
</body>
</html>
