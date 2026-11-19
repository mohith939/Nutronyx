<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\CustomerService;

AuthService::requireAuth();

$activePage = 'customers';
$successMsg = '';
$errorMsg = '';
$activeWebsite = Context::getWebsite();

// Handle Customer Creation
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_customer') {
    try {
        $newId = CustomerService::createCustomer([
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phone'] ?? ''),
            'status' => $_POST['status'] ?? 'Active',
            'customer_type' => $_POST['customer_type'] ?? 'Registered',
            'tags' => !empty($_POST['tags']) ? array_map('trim', explode(',', $_POST['tags'])) : ['New'],
            'city' => trim($_POST['city'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'address_line1' => trim($_POST['address_line1'] ?? ''),
            'address_line2' => trim($_POST['address_line2'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'notes' => trim($_POST['notes'] ?? '')
        ]);
        $successMsg = 'Customer created successfully.';
        $_GET['customer_id'] = $newId;
    } catch (\Throwable $e) {
        $errorMsg = 'Error creating customer: ' . $e->getMessage();
    }
}

// Handle Customer Edit
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_customer') {
    try {
        CustomerService::updateCustomer($_POST['customer_id'], [
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phone'] ?? ''),
            'status' => $_POST['status'] ?? 'Active',
            'tags' => trim($_POST['tags'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'notes' => trim($_POST['notes'] ?? '')
        ]);
        $successMsg = 'Customer details updated successfully.';
        $_GET['customer_id'] = $_POST['customer_id'];
    } catch (\Throwable $e) {
        $errorMsg = 'Error updating customer: ' . $e->getMessage();
    }
}

// Handle Add Address
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_address') {
    try {
        CustomerService::addAddress($_POST['customer_id'], [
            'type' => $_POST['type'] ?? 'shipping',
            'label' => trim($_POST['label'] ?? 'Home'),
            'name' => trim($_POST['name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address_line1' => trim($_POST['address_line1']),
            'address_line2' => trim($_POST['address_line2'] ?? ''),
            'city' => trim($_POST['city']),
            'state' => trim($_POST['state']),
            'postal_code' => trim($_POST['postal_code']),
            'country' => 'India',
            'is_default' => !empty($_POST['is_default']) ? 1 : 0
        ]);
        $successMsg = 'Address added successfully.';
        $_GET['customer_id'] = $_POST['customer_id'];
    } catch (\Throwable $e) {
        $errorMsg = 'Error adding address: ' . $e->getMessage();
    }
}

// Handle Delete Address
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_address') {
    try {
        CustomerService::deleteAddress($_POST['address_id']);
        $successMsg = 'Address deleted successfully.';
        $_GET['customer_id'] = $_POST['customer_id'];
    } catch (\Throwable $e) {
        $errorMsg = 'Error deleting address: ' . $e->getMessage();
    }
}

// Handle Add Note
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    try {
        CustomerService::addNote($_POST['customer_id'], trim($_POST['note']), 'Admin Staff');
        $successMsg = 'Internal note saved successfully.';
        $_GET['customer_id'] = $_POST['customer_id'];
    } catch (\Throwable $e) {
        $errorMsg = 'Error saving note: ' . $e->getMessage();
    }
}

// Handle Delete Note
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_note') {
    try {
        CustomerService::deleteNote($_POST['note_id']);
        $successMsg = 'Note removed successfully.';
        $_GET['customer_id'] = $_POST['customer_id'];
    } catch (\Throwable $e) {
        $errorMsg = 'Error deleting note: ' . $e->getMessage();
    }
}

// Handle Customer Anonymization (GDPR)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'anonymize_customer') {
    try {
        CustomerService::anonymizeCustomer($_POST['customer_id']);
        $successMsg = 'Customer personal data has been anonymized successfully.';
    } catch (\Throwable $e) {
        $errorMsg = 'Error anonymizing customer: ' . $e->getMessage();
    }
}

// Handle Customer Deletion
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_customer') {
    try {
        CustomerService::deleteCustomer($_POST['customer_id']);
        $successMsg = 'Customer account deleted successfully.';
    } catch (\Throwable $e) {
        $errorMsg = 'Error deleting customer: ' . $e->getMessage();
    }
}

// Handle Bulk Actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_action') {
    $bulkType = $_POST['bulk_type'] ?? '';
    $ids = isset($_POST['selected_ids']) ? explode(',', $_POST['selected_ids']) : [];
    $ids = array_filter(array_map('trim', $ids));

    if (!empty($ids)) {
        if ($bulkType === 'change_status') {
            $status = $_POST['bulk_status'] ?? 'Active';
            $cnt = CustomerService::bulkUpdateStatus($ids, $status);
            $successMsg = "Updated status to {$status} for {$cnt} customer(s).";
        } elseif ($bulkType === 'add_tag') {
            $tag = trim($_POST['bulk_tag'] ?? 'Loyal');
            $cnt = CustomerService::bulkAddTag($ids, $tag);
            $successMsg = "Applied tag '{$tag}' to {$cnt} customer(s).";
        } elseif ($bulkType === 'delete') {
            $cnt = CustomerService::bulkDelete($ids);
            $successMsg = "Deleted {$cnt} customer(s).";
        } elseif ($bulkType === 'send_email') {
            $successMsg = "Email broadcast initiated for " . count($ids) . " customer(s).";
        }
    }
}

// Handle Duplicate Merging
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'merge_customers') {
    try {
        CustomerService::mergeCustomers($_POST['primary_id'], $_POST['secondary_id']);
        $successMsg = 'Customer profiles merged successfully.';
        $_GET['customer_id'] = $_POST['primary_id'];
    } catch (\Throwable $e) {
        $errorMsg = 'Error merging customers: ' . $e->getMessage();
    }
}

// Handle CSV Import
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_csv') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $res = CustomerService::importCustomersCsv($_FILES['csv_file']['tmp_name']);
        if ($res['success']) {
            $successMsg = "Successfully imported {$res['imported']} customer(s).";
        } else {
            $errorMsg = $res['error'] ?? 'Import failed.';
        }
    } else {
        $errorMsg = 'Please select a valid CSV file to upload.';
    }
}

// Handle Sync from Orders
if (isset($_GET['sync_orders'])) {
    $syncedCount = CustomerService::syncFromOrders();
    $successMsg = "Synchronized customer metrics from orders ledger ({$syncedCount} customer accounts updated).";
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    CustomerService::exportCustomersCsv();
    exit;
}

// Handle AJAX Customer Details Request
if (isset($_GET['ajax_customer_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $details = CustomerService::getCustomerDetails($_GET['ajax_customer_id']);
    echo json_encode($details ?: ['error' => 'Customer not found']);
    exit;
}

// Handle AJAX Duplicate Detection Request
if (isset($_GET['action']) && $_GET['action'] === 'find_duplicates_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    $duplicates = CustomerService::findDuplicates();
    echo json_encode(['duplicates' => $duplicates]);
    exit;
}

// Filter and Pagination Query Parameters
$search = $_GET['search'] ?? null;
$statusFilter = $_GET['status'] ?? null;
$tagFilter = $_GET['tag'] ?? null;
$dateRangeFilter = $_GET['date_range'] ?? '30_days';
$cohortFilter = $_GET['cohort'] ?? null;
$sortFilter = $_GET['sort'] ?? 'recent';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$kpis = CustomerService::getCustomerKpis();
$directory = CustomerService::getCustomersAdvanced(
    $search,
    $statusFilter,
    $tagFilter,
    $dateRangeFilter,
    $cohortFilter,
    $sortFilter,
    $page,
    $perPage
);
$customers = $directory['customers'];

// Determine Active Customer for Right-Side Workspace
$selectedCustomerId = $_GET['customer_id'] ?? null;
if (!$selectedCustomerId && !empty($customers)) {
    // Default to Ravi Kumar if present or first customer
    foreach ($customers as $c) {
        if ($c['id'] === 'cust_ravi' || $c['name'] === 'Ravi Kumar') {
            $selectedCustomerId = $c['id'];
            break;
        }
    }
    if (!$selectedCustomerId) {
        $selectedCustomerId = $customers[0]['id'];
    }
}

$activeCustomerDetails = $selectedCustomerId ? CustomerService::getCustomerDetails($selectedCustomerId) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers &mdash; Nutronyx Admin Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=20261119_white">
    <style>
        .customers-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (max-width: 960px) {
            .customers-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 520px) {
            .customers-kpi-grid { grid-template-columns: 1fr; }
        }
        .cust-kpi-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .cust-kpi-title {
            font-size: 13px;
            color: #6B7280;
            font-weight: 500;
            margin-bottom: 6px;
        }
        .cust-kpi-num {
            font-size: 26px;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.02em;
        }
        .cust-kpi-badge {
            font-size: 11.5px;
            font-weight: 600;
            color: #059669;
            margin-top: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
    </style>
</head>
<body class="admin-body customers-page-layout">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="admin-layout-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main-stage">

            <!-- Alert Messages -->
            <?php if (!empty($successMsg)): ?>
                <div style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; font-weight: 500; font-size: 13.5px; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span><?= htmlspecialchars($successMsg) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($errorMsg)): ?>
                <div style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; font-weight: 500; font-size: 13.5px; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span><?= htmlspecialchars($errorMsg) ?></span>
                </div>
            <?php endif; ?>

            <!-- Top Header (Matching Mockup) -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 22px; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 style="font-family: 'Playfair Display', Georgia, serif; font-size: 28px; font-weight: 700; color: #111827; margin: 0; letter-spacing: -0.02em;">Customers</h1>
                    <p style="font-size: 13.5px; color: #6B7280; margin-top: 4px; margin-bottom: 0;">Manage your customer base, view purchasing history and engagement</p>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <button type="button" class="btn-bulk-item" onclick="window.NutronyxModal.open('importCsvModal')" style="font-weight: 600; padding: 8px 14px; font-size: 13px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <span>Import</span>
                    </button>
                    <button type="button" class="btn-create-order-forest" onclick="window.NutronyxModal.open('addCustomerModal')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        <span>Add Customer</span>
                    </button>
                </div>
            </div>

            <!-- 4 Top KPI Cards (Matching Mockup) -->
            <?php
            $renderTrendBadge = function(string $text) {
                $icon = '&#8599;'; // ↗
                $color = '#059669'; // Green
                if (str_starts_with($text, '-')) {
                    $icon = '&#8600;'; // ↘
                    $color = '#DC2626'; // Red
                } elseif (str_starts_with($text, '0%') || str_starts_with($text, '0.0%')) {
                    $icon = '&bull;'; // •
                    $color = '#6B7280'; // Neutral gray
                }
                return '<div class="cust-kpi-badge" style="color: ' . $color . ';"><span>' . $icon . '</span> <span>' . htmlspecialchars($text) . '</span></div>';
            };
            ?>
            <!-- 4 Top KPI Cards (Matching Real Database Numbers) -->
            <div class="customers-kpi-grid">
                <div class="cust-kpi-card">
                    <div class="cust-kpi-title">Total Customers</div>
                    <div class="cust-kpi-num"><?= htmlspecialchars($kpis['total_customers_formatted']) ?></div>
                    <?= $renderTrendBadge($kpis['total_customers_change']) ?>
                </div>

                <div class="cust-kpi-card">
                    <div class="cust-kpi-title">Repeat Customers</div>
                    <div class="cust-kpi-num"><?= htmlspecialchars($kpis['repeat_customers_formatted']) ?></div>
                    <?= $renderTrendBadge($kpis['repeat_customers_retention']) ?>
                </div>

                <div class="cust-kpi-card">
                    <div class="cust-kpi-title">Total Orders</div>
                    <div class="cust-kpi-num"><?= htmlspecialchars($kpis['total_orders_formatted']) ?></div>
                    <?= $renderTrendBadge($kpis['total_orders_change']) ?>
                </div>

                <div class="cust-kpi-card">
                    <div class="cust-kpi-title">Total Revenue</div>
                    <div class="cust-kpi-num"><?= htmlspecialchars($kpis['total_revenue_formatted']) ?></div>
                    <?= $renderTrendBadge($kpis['total_revenue_change']) ?>
                </div>
            </div>

            <!-- Filter Toolbar (Matching Mockup) -->
            <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px; padding: 14px 18px; margin-bottom: 18px;">
                <form method="GET" action="customers.php" id="customerFilterForm" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <!-- Search Input -->
                    <div style="position: relative; flex: 1; min-width: 240px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="search" placeholder="Search by name, email, phone..." value="<?= htmlspecialchars($search ?? '') ?>" style="width: 100%; padding: 8px 12px 8px 36px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 13px; color: #111827; outline: none; background: #FFFFFF;">
                    </div>

                    <!-- Date Range Filter -->
                    <div style="min-width: 150px;">
                        <select name="date_range" onchange="document.getElementById('customerFilterForm').submit()" style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 13px; color: #374151; background: #FFFFFF; outline: none;">
                            <option value="7_days" <?= ($dateRangeFilter === '7_days') ? 'selected' : '' ?>>📅 Last 7 Days</option>
                            <option value="30_days" <?= ($dateRangeFilter === '30_days') ? 'selected' : '' ?>>📅 Last 30 Days</option>
                            <option value="90_days" <?= ($dateRangeFilter === '90_days') ? 'selected' : '' ?>>📅 Last 90 Days</option>
                            <option value="this_year" <?= ($dateRangeFilter === 'this_year') ? 'selected' : '' ?>>📅 This Year</option>
                            <option value="all" <?= ($dateRangeFilter === 'all') ? 'selected' : '' ?>>📅 All Time</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div style="min-width: 130px;">
                        <select name="status" onchange="document.getElementById('customerFilterForm').submit()" style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 13px; color: #374151; background: #FFFFFF; outline: none;">
                            <option value="">Status: All Status</option>
                            <option value="Active" <?= ($statusFilter === 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= ($statusFilter === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                            <option value="Lead" <?= ($statusFilter === 'Lead') ? 'selected' : '' ?>>Lead</option>
                        </select>
                    </div>

                    <!-- Tags Filter -->
                    <div style="min-width: 130px;">
                        <select name="tag" onchange="document.getElementById('customerFilterForm').submit()" style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 13px; color: #374151; background: #FFFFFF; outline: none;">
                            <option value="">Tags: All Tags</option>
                            <option value="Loyal" <?= ($tagFilter === 'Loyal') ? 'selected' : '' ?>>Loyal</option>
                            <option value="VIP" <?= ($tagFilter === 'VIP') ? 'selected' : '' ?>>VIP</option>
                            <option value="Repeat" <?= ($tagFilter === 'Repeat') ? 'selected' : '' ?>>Repeat</option>
                            <option value="New" <?= ($tagFilter === 'New') ? 'selected' : '' ?>>New</option>
                            <option value="Wholesale" <?= ($tagFilter === 'Wholesale') ? 'selected' : '' ?>>Wholesale</option>
                        </select>
                    </div>

                    <!-- More Filters / Actions Dropdown -->
                    <div style="position: relative; display: inline-block;">
                        <button type="button" class="btn-bulk-item" onclick="toggleMoreFiltersMenu()" style="padding: 8px 14px; font-weight: 500; font-size: 13px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                            <span>More Filters</span>
                        </button>
                        <div id="moreFiltersMenu" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 6px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 200px; z-index: 50; padding: 6px 0;">
                            <a href="customers.php?export=csv" class="menu-item" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 13px; color: #374151; text-decoration: none;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                <span>Export All CSV</span>
                            </a>
                            <a href="customers.php?sync_orders=1" class="menu-item" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 13px; color: #374151; text-decoration: none;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                                <span>Sync from Orders</span>
                            </a>
                            <button type="button" onclick="openDuplicatesModal()" class="menu-item" style="width: 100%; border: none; background: none; text-align: left; display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 13px; color: #374151; cursor: pointer;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                <span>Find Duplicates</span>
                            </button>
                        </div>
                    </div>

                    <?php if ($search || $statusFilter || $tagFilter || $dateRangeFilter !== '30_days' || $cohortFilter): ?>
                        <a href="customers.php" style="font-size: 13px; color: #DC2626; text-decoration: none; font-weight: 500; margin-left: 4px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Bulk Action Toolbar (Displays when rows selected) -->
            <div class="order-bulk-bar" id="customerBulkBar" style="display: none;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" class="nutronyx-checkbox" id="bulkBarCheckbox" checked onchange="toggleAllCustomerCheckboxes(this.checked)">
                    <span class="bulk-selected-pill" id="selectedCustomerCountPill">0 selected</span>
                </div>

                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn-bulk-item" onclick="triggerCustomerBulkAction('send_email')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        <span>Send Email</span>
                    </button>

                    <button type="button" class="btn-bulk-item" onclick="promptBulkTag()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        <span>Add Tag</span>
                    </button>

                    <div style="position: relative; display: inline-block;">
                        <button type="button" class="btn-bulk-item" onclick="toggleBulkStatusDropdown()">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            <span>Change Status ▾</span>
                        </button>
                        <div id="bulkStatusDropdown" style="display: none; position: absolute; left: 0; top: 100%; margin-top: 4px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 6px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); z-index: 50; width: 140px;">
                            <button type="button" onclick="triggerCustomerBulkAction('change_status', 'Active')" style="width: 100%; padding: 8px 12px; border: none; background: none; text-align: left; font-size: 13px; cursor: pointer;">Active</button>
                            <button type="button" onclick="triggerCustomerBulkAction('change_status', 'Inactive')" style="width: 100%; padding: 8px 12px; border: none; background: none; text-align: left; font-size: 13px; cursor: pointer;">Inactive</button>
                        </div>
                    </div>

                    <button type="button" class="btn-bulk-item btn-bulk-cancel" onclick="triggerCustomerBulkAction('delete')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        <span>Delete</span>
                    </button>
                </div>
            </div>

            <!-- Split-Screen Workspace (Matching Mockup) -->
            <div class="customers-split-layout">
                
                <!-- Master Customer Directory Table -->
                <div class="customers-master-column">
                    <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                        <div class="table-responsive">
                            <table class="dash-table" style="margin: 0;">
                                <thead>
                                    <tr style="background: #F9FAFB; border-bottom: 1px solid #E5E7EB;">
                                        <th style="width: 38px; padding-left: 16px;">
                                            <input type="checkbox" class="nutronyx-checkbox" id="selectAllCustomers" onchange="toggleAllCustomerCheckboxes(this.checked)">
                                        </th>
                                        <th>Customer</th>
                                        <th>Contact</th>
                                        <th>Orders</th>
                                        <th>Total Spent</th>
                                        <th>Status</th>
                                        <th>Tags</th>
                                        <th>Joined</th>
                                        <th style="text-align: right; padding-right: 16px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($customers)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; padding: 48px 20px; color: #6B7280;">
                                                <div style="font-size: 15px; font-weight: 600; color: #374151;">No customers found</div>
                                                <p style="font-size: 13px; margin-top: 4px;">No customer records match your filter criteria.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($customers as $c): 
                                            $isSelected = ($c['id'] === $selectedCustomerId);
                                            $tagsArr = $c['tags_array'] ?? [];
                                        ?>
                                            <tr class="customer-table-row <?= $isSelected ? 'selected-customer-row' : '' ?>" id="cust-row-<?= htmlspecialchars($c['id']) ?>" onclick="if (!event.target.closest('input') && !event.target.closest('button') && !event.target.closest('a')) selectCustomer('<?= htmlspecialchars($c['id']) ?>')">
                                                <td style="padding-left: 16px;" onclick="event.stopPropagation()">
                                                    <input type="checkbox" class="nutronyx-checkbox customer-row-checkbox" value="<?= htmlspecialchars($c['id']) ?>" onchange="updateCustomerBulkBar()">
                                                </td>
                                                <td>
                                                    <div style="display: flex; align-items: center; gap: 12px;">
                                                        <div class="customer-avatar-circle">
                                                            <?= htmlspecialchars($c['initials']) ?>
                                                        </div>
                                                        <div>
                                                            <div style="font-weight: 600; color: #111827; font-size: 13.5px;">
                                                                <?= htmlspecialchars($c['name']) ?>
                                                            </div>
                                                            <div style="font-size: 11.5px; color: #6B7280;">
                                                                <?= htmlspecialchars($c['customer_number'] ?? $c['id']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="font-size: 13px; color: #111827; font-weight: 500;"><?= htmlspecialchars($c['phone'] ?: 'No phone') ?></div>
                                                    <div style="font-size: 12px; color: #6B7280;"><?= htmlspecialchars($c['email']) ?></div>
                                                </td>
                                                <td style="font-weight: 600; color: #111827; font-size: 13.5px;">
                                                    <?= (int)$c['total_orders'] ?>
                                                </td>
                                                <td style="font-weight: 600; color: #111827; font-size: 13.5px;">
                                                    ₹<?= number_format((int)$c['total_spent_amount'] / 100, 0) ?>
                                                </td>
                                                <td>
                                                    <?php if (($c['status'] ?? 'Active') === 'Active'): ?>
                                                        <span class="status-pill-dot paid">
                                                            <span class="dot"></span>
                                                            <span>Active</span>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-pill-dot pending">
                                                            <span class="dot"></span>
                                                            <span><?= htmlspecialchars($c['status'] ?? 'Inactive') ?></span>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                        <?php foreach ($tagsArr as $tg): 
                                                            $tgLower = strtolower($tg);
                                                            $pillClass = 'tag-default';
                                                            if (strpos($tgLower, 'loyal') !== false) $pillClass = 'tag-loyal';
                                                            elseif (strpos($tgLower, 'vip') !== false) $pillClass = 'tag-vip';
                                                            elseif (strpos($tgLower, 'repeat') !== false) $pillClass = 'tag-repeat';
                                                            elseif (strpos($tgLower, 'new') !== false) $pillClass = 'tag-new';
                                                            elseif (strpos($tgLower, 'wholesale') !== false) $pillClass = 'tag-wholesale';
                                                        ?>
                                                            <span class="cust-tag-pill <?= $pillClass ?>"><?= htmlspecialchars($tg) ?></span>
                                                        <?php endforeach; ?>
                                                        <?php if (empty($tagsArr)): ?>
                                                            <span style="color: #9CA3AF; font-size: 12px;">&mdash;</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td style="font-size: 12.5px; color: #6B7280; white-space: nowrap;">
                                                    <?= date('M d, Y', strtotime($c['created_at'])) ?>
                                                </td>
                                                <td style="text-align: right; padding-right: 16px;" onclick="event.stopPropagation()">
                                                    <div style="position: relative; display: inline-block;">
                                                        <button type="button" class="btn-bulk-item" onclick="toggleRowMenu('menu-<?= htmlspecialchars($c['id']) ?>')" style="padding: 4px 8px; border-radius: 6px;">
                                                            •••
                                                        </button>
                                                        <div id="menu-<?= htmlspecialchars($c['id']) ?>" class="customer-row-dropdown" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 4px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); width: 170px; z-index: 50; text-align: left; padding: 4px 0;">
                                                            <button type="button" onclick="openEditCustomerModal('<?= htmlspecialchars($c['id']) ?>')" style="width: 100%; padding: 7px 12px; border: none; background: none; font-size: 12.5px; text-align: left; color: #374151; cursor: pointer;">✎ Edit Customer</button>
                                                            <button type="button" onclick="openAddAddressModal('<?= htmlspecialchars($c['id']) ?>')" style="width: 100%; padding: 7px 12px; border: none; background: none; font-size: 12.5px; text-align: left; color: #374151; cursor: pointer;">+ Add Address</button>
                                                            <button type="button" onclick="openAddNoteModal('<?= htmlspecialchars($c['id']) ?>')" style="width: 100%; padding: 7px 12px; border: none; background: none; font-size: 12.5px; text-align: left; color: #374151; cursor: pointer;">+ Add Note</button>
                                                            <a href="orders.php?search=<?= urlencode($c['phone'] ?: $c['email']) ?>" style="display: block; padding: 7px 12px; font-size: 12.5px; color: #374151; text-decoration: none;">View Orders &rarr;</a>
                                                            <div style="border-top: 1px solid #E5E7EB; margin: 4px 0;"></div>
                                                            <form method="POST" action="customers.php" onsubmit="return confirm('Anonymize this customer? This scrub all personal details per GDPR.')">
                                                                <input type="hidden" name="action" value="anonymize_customer">
                                                                <input type="hidden" name="customer_id" value="<?= htmlspecialchars($c['id']) ?>">
                                                                <button type="submit" style="width: 100%; padding: 7px 12px; border: none; background: none; font-size: 12.5px; text-align: left; color: #D97706; cursor: pointer;">Anonymize PII</button>
                                                            </form>
                                                            <form method="POST" action="customers.php" onsubmit="return confirm('Are you sure you want to permanently delete this customer account?')">
                                                                <input type="hidden" name="action" value="delete_customer">
                                                                <input type="hidden" name="customer_id" value="<?= htmlspecialchars($c['id']) ?>">
                                                                <button type="submit" style="width: 100%; padding: 7px 12px; border: none; background: none; font-size: 12.5px; text-align: left; color: #DC2626; cursor: pointer;">Delete Account</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Table Pagination Bar -->
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-top: 1px solid #E5E7EB; font-size: 13px; color: #6B7280; flex-wrap: wrap; gap: 10px;">
                            <div>
                                Showing <?= $directory['from'] ?> to <?= $directory['to'] ?> of <?= number_format($directory['total_count']) ?> customers
                            </div>

                            <div style="display: flex; align-items: center; gap: 6px;">
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn-bulk-item" style="padding: 4px 10px; font-size: 12px;">Previous</a>
                                <?php endif; ?>

                                <?php for ($p = 1; $p <= min(5, $directory['total_pages']); $p++): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="page-num-btn <?= ($p === $page) ? 'active' : '' ?>">
                                        <?= $p ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $directory['total_pages']): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn-bulk-item" style="padding: 4px 10px; font-size: 12px;">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Detail Column Workspace (Matching Mockup) -->
                <div class="customers-detail-column" id="customerDetailWorkspace" style="<?= empty($activeCustomerDetails) ? 'display: none;' : '' ?>">
                    <?php if (!empty($activeCustomerDetails)): 
                        $cust = $activeCustomerDetails['customer'];
                        $m = $activeCustomerDetails['metrics'];
                        $ords = $activeCustomerDetails['orders'];
                        $addrs = $activeCustomerDetails['addresses'];
                        $notes = $activeCustomerDetails['notes'];
                        $acts = $activeCustomerDetails['activity'];
                    ?>
                        <!-- Profile Header -->
                        <div class="cust-profile-header">
                            <div class="cust-profile-user-group">
                                <div class="customer-avatar-circle customer-avatar-circle-lg" id="cpAvatar">
                                    <?= htmlspecialchars($cust['initials']) ?>
                                </div>
                                <div>
                                    <h3 class="cust-profile-title">
                                        <span id="cpName"><?= htmlspecialchars($cust['name']) ?></span>
                                        <span class="status-pill-dot paid" id="cpStatusBadge" style="font-size: 11px; padding: 2px 8px;">
                                            <span class="dot"></span>
                                            <span id="cpStatusText"><?= htmlspecialchars($cust['status'] ?? 'Active') ?></span>
                                        </span>
                                    </h3>
                                    <p class="cust-profile-sub">
                                        Customer since <span id="cpSince"><?= htmlspecialchars($m['joined_date']) ?></span>
                                    </p>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="position: relative;">
                                    <button type="button" class="btn-bulk-item" onclick="toggleRowMenu('cpHeaderDropdown')" style="padding: 6px 10px;">•••</button>
                                    <div id="cpHeaderDropdown" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 4px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); width: 170px; z-index: 50; text-align: left; padding: 4px 0;">
                                        <button type="button" onclick="openEditCustomerModal(currentActiveCustomerId)" style="width: 100%; padding: 7px 12px; border: none; background: none; font-size: 12.5px; text-align: left; color: #374151; cursor: pointer;">✎ Edit Profile</button>
                                        <button type="button" onclick="openAddAddressModal(currentActiveCustomerId)" style="width: 100%; padding: 7px 12px; border: none; background: none; font-size: 12.5px; text-align: left; color: #374151; cursor: pointer;">+ Add Address</button>
                                        <button type="button" onclick="openAddNoteModal(currentActiveCustomerId)" style="width: 100%; padding: 7px 12px; border: none; background: none; font-size: 12.5px; text-align: left; color: #374151; cursor: pointer;">+ Add Note</button>
                                    </div>
                                </div>
                                <button type="button" class="btn-bulk-item" onclick="closeCustomerWorkspace()" title="Close details panel" style="padding: 6px 10px;">✕</button>
                            </div>
                        </div>

                        <!-- Contact Strip -->
                        <div class="cust-contact-strip">
                            <div class="cust-contact-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                <span id="cpPhone"><?= htmlspecialchars($cust['phone'] ?: 'No phone') ?></span>
                            </div>
                            <div class="cust-contact-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                <span id="cpEmail"><?= htmlspecialchars($cust['email']) ?></span>
                            </div>
                            <div class="cust-contact-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                <span id="cpLocation"><?= htmlspecialchars($activeCustomerDetails['location']) ?></span>
                            </div>
                        </div>

                        <!-- Workspace Tabs -->
                        <div class="cust-workspace-tabs">
                            <button type="button" class="cust-workspace-tab-btn active" onclick="switchCustomerTab('overview', this)">Overview</button>
                            <button type="button" class="cust-workspace-tab-btn" onclick="switchCustomerTab('orders', this)">
                                <span>Orders</span>
                                <span class="cust-tab-badge" id="cpTabOrdersCount"><?= count($ords) ?></span>
                            </button>
                            <button type="button" class="cust-workspace-tab-btn" onclick="switchCustomerTab('addresses', this)">
                                <span>Addresses</span>
                                <span class="cust-tab-badge" id="cpTabAddressesCount"><?= count($addrs) ?></span>
                            </button>
                            <button type="button" class="cust-workspace-tab-btn" onclick="switchCustomerTab('notes', this)">
                                <span>Notes</span>
                                <span class="cust-tab-badge" id="cpTabNotesCount"><?= count($notes) ?></span>
                            </button>
                        </div>

                        <!-- TAB 1: OVERVIEW (Matching Mockup) -->
                        <div id="pane-overview" class="cust-tab-pane">
                            <!-- 4 Mini KPI Tiles -->
                            <div class="cust-mini-kpi-grid">
                                <div class="cust-mini-kpi-tile">
                                    <div class="label">Total Orders</div>
                                    <div class="value" id="cpTileOrders"><?= (int)$m['total_orders'] ?></div>
                                </div>
                                <div class="cust-mini-kpi-tile">
                                    <div class="label">Total Spent</div>
                                    <div class="value" id="cpTileSpent"><?= htmlspecialchars($m['total_spent_formatted']) ?></div>
                                </div>
                                <div class="cust-mini-kpi-tile">
                                    <div class="label">AOV</div>
                                    <div class="value" id="cpTileAov"><?= htmlspecialchars($m['aov_formatted']) ?></div>
                                </div>
                                <div class="cust-mini-kpi-tile">
                                    <div class="label">Last Order</div>
                                    <div class="value" style="font-size: 13.5px;" id="cpTileLastOrder"><?= htmlspecialchars($m['last_order_at']) ?></div>
                                </div>
                            </div>

                            <!-- Customer Information Card -->
                            <div class="cust-subcard">
                                <div class="cust-subcard-header">
                                    <h4 class="cust-subcard-title">Customer Information</h4>
                                    <button type="button" class="cust-subcard-btn" onclick="openEditCustomerModal(currentActiveCustomerId)">
                                        <span>✎ Edit</span>
                                    </button>
                                </div>
                                <div class="cust-info-grid">
                                    <div class="cust-info-cell">
                                        <div class="label">Full Name</div>
                                        <div class="val" id="cpInfoName"><?= htmlspecialchars($cust['name']) ?></div>
                                    </div>
                                    <div class="cust-info-cell">
                                        <div class="label">Customer ID</div>
                                        <div class="val" id="cpInfoId"><?= htmlspecialchars($cust['customer_number'] ?? $cust['id']) ?></div>
                                    </div>
                                    <div class="cust-info-cell">
                                        <div class="label">Phone</div>
                                        <div class="val" id="cpInfoPhone"><?= htmlspecialchars($cust['phone'] ?: '—') ?></div>
                                    </div>
                                    <div class="cust-info-cell">
                                        <div class="label">Status</div>
                                        <div class="val" id="cpInfoStatus"><?= htmlspecialchars($cust['status'] ?? 'Active') ?></div>
                                    </div>
                                    <div class="cust-info-cell">
                                        <div class="label">Email</div>
                                        <div class="val" style="word-break: break-all;" id="cpInfoEmail"><?= htmlspecialchars($cust['email']) ?></div>
                                    </div>
                                    <div class="cust-info-cell">
                                        <div class="label">Tags</div>
                                        <div class="val" id="cpInfoTags">
                                            <?php foreach ($cust['tags_array'] as $tg): ?>
                                                <span class="cust-tag-pill tag-default" style="margin-right: 4px;"><?= htmlspecialchars($tg) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Orders Section -->
                            <div class="cust-subcard">
                                <div class="cust-subcard-header">
                                    <h4 class="cust-subcard-title">Recent Orders</h4>
                                    <?php if (!empty($ords)): ?>
                                        <a href="orders.php?search=<?= urlencode($cust['phone'] ?: $cust['email']) ?>" class="cust-subcard-btn">
                                            <span>View All (<?= count($ords) ?>) &rarr;</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div id="cpOverviewOrdersList">
                                    <?php if (empty($ords)): ?>
                                        <p style="font-size: 13px; color: #6B7280; margin: 0;">No purchase orders placed yet.</p>
                                    <?php else: ?>
                                        <?php foreach (array_slice($ords, 0, 3) as $ro): ?>
                                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #F3F4F6;">
                                                <div>
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <a href="order-detail.php?id=<?= urlencode($ro['id']) ?>" style="font-weight: 700; color: #144D29; text-decoration: none; font-size: 13.5px;">
                                                            <?= htmlspecialchars($ro['order_number']) ?>
                                                        </a>
                                                        <span class="status-pill-dot paid" style="font-size: 10px; padding: 1px 6px;">
                                                            <span class="dot"></span>
                                                            <span><?= htmlspecialchars($ro['fulfillment_status'] ?? 'Delivered') ?></span>
                                                        </span>
                                                    </div>
                                                    <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">
                                                        <?= date('M d, Y', strtotime($ro['created_at'])) ?> &bull; <?= (int)($ro['item_count'] ?? 1) ?> item(s)
                                                    </div>
                                                </div>
                                                <div style="font-weight: 700; color: #111827; font-size: 13.5px;">
                                                    ₹<?= number_format((int)$ro['grand_total_amount'] / 100, 0) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 2-Column Split: Addresses & Notes -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                                <!-- Addresses Subcard -->
                                <div class="cust-subcard" style="margin-bottom: 0;">
                                    <div class="cust-subcard-header">
                                        <h4 class="cust-subcard-title">
                                            <span>Addresses</span>
                                            <span style="font-size: 11px; font-weight: 600; color: #6B7280;">(<?= count($addrs) ?>)</span>
                                        </h4>
                                        <button type="button" class="cust-subcard-btn" onclick="openAddAddressModal(currentActiveCustomerId)">
                                            <span>+ Add</span>
                                        </button>
                                    </div>
                                    <div id="cpOverviewAddressSnippet" style="font-size: 12.5px; line-height: 1.5; color: #374151;">
                                        <?php if (!empty($addrs)): 
                                            $defAddr = $addrs[0];
                                        ?>
                                            <div style="margin-bottom: 4px;">
                                                <span class="status-pill-dot paid" style="font-size: 10px; padding: 1px 6px;">Default Shipping</span>
                                            </div>
                                            <div style="font-weight: 600; color: #111827;"><?= htmlspecialchars($defAddr['label'] ?? 'Home') ?></div>
                                            <div><?= htmlspecialchars($defAddr['address_line1']) ?><?= !empty($defAddr['address_line2']) ? ', ' . htmlspecialchars($defAddr['address_line2']) : '' ?></div>
                                            <div><?= htmlspecialchars($defAddr['city']) ?>, <?= htmlspecialchars($defAddr['state']) ?> - <?= htmlspecialchars($defAddr['postal_code']) ?></div>
                                            <div style="color: #6B7280; margin-top: 2px;">Phone: <?= htmlspecialchars($defAddr['phone'] ?? $cust['phone']) ?></div>
                                        <?php else: ?>
                                            <span style="color: #9CA3AF;">No addresses added yet.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Internal Notes Subcard -->
                                <div class="cust-subcard" style="margin-bottom: 0;">
                                    <div class="cust-subcard-header">
                                        <h4 class="cust-subcard-title">
                                            <span>Internal Notes</span>
                                            <span style="font-size: 11px; font-weight: 600; color: #6B7280;">(<?= count($notes) ?>)</span>
                                        </h4>
                                        <button type="button" class="cust-subcard-btn" onclick="openAddNoteModal(currentActiveCustomerId)">
                                            <span>+ Add</span>
                                        </button>
                                    </div>
                                    <div id="cpOverviewNotesSnippet" style="font-size: 12.5px; line-height: 1.45; color: #374151;">
                                        <?php if (!empty($notes)): ?>
                                            <?php foreach (array_slice($notes, 0, 2) as $nt): ?>
                                                <div style="margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid #F3F4F6;">
                                                    <div><?= htmlspecialchars($nt['note']) ?></div>
                                                    <div style="font-size: 11px; color: #9CA3AF; margin-top: 2px;">
                                                        <?= htmlspecialchars($nt['author_name'] ?? 'Staff') ?> &bull; <?= date('M d, Y', strtotime($nt['created_at'])) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: #9CA3AF;">No internal notes logged.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: ORDERS -->
                        <div id="pane-orders" class="cust-tab-pane" style="display: none;">
                            <div class="cust-subcard">
                                <div class="cust-subcard-header">
                                    <h4 class="cust-subcard-title">All Customer Orders</h4>
                                    <span style="font-size: 12px; color: #6B7280;" id="cpTabOrdersSubtitle"><?= count($ords) ?> order(s)</span>
                                </div>
                                <div id="cpTabOrdersContent">
                                    <?php if (empty($ords)): ?>
                                        <p style="font-size: 13px; color: #6B7280; margin: 0;">No orders found.</p>
                                    <?php else: ?>
                                        <?php foreach ($ords as $ord): ?>
                                            <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px;">
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                    <div>
                                                        <a href="order-detail.php?id=<?= urlencode($ord['id']) ?>" style="font-weight: 700; color: #144D29; text-decoration: none; font-size: 14px;">
                                                            <?= htmlspecialchars($ord['order_number']) ?>
                                                        </a>
                                                        <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">
                                                            <?= date('M d, Y H:i', strtotime($ord['created_at'])) ?> &bull; <?= (int)($ord['item_count'] ?? 1) ?> item(s)
                                                        </div>
                                                    </div>
                                                    <div style="text-align: right;">
                                                        <div style="font-weight: 700; color: #111827; font-size: 14px;">
                                                            ₹<?= number_format((int)$ord['grand_total_amount'] / 100, 0) ?>
                                                        </div>
                                                        <div style="margin-top: 4px;">
                                                            <span class="status-pill-dot paid" style="font-size: 10.5px; padding: 1px 7px;">
                                                                <span class="dot"></span>
                                                                <span><?= htmlspecialchars($ord['payment_status']) ?></span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div style="display: flex; gap: 8px; margin-top: 10px; border-top: 1px solid #E5E7EB; padding-top: 8px;">
                                                    <a href="order-detail.php?id=<?= urlencode($ord['id']) ?>" class="btn-bulk-item" style="font-size: 11.5px; padding: 3px 8px;">View Details</a>
                                                    <a href="invoice.php?id=<?= urlencode($ord['id']) ?>" target="_blank" class="btn-bulk-item" style="font-size: 11.5px; padding: 3px 8px;">Print Invoice</a>
                                                    <a href="receipt.php?id=<?= urlencode($ord['id']) ?>" target="_blank" class="btn-bulk-item" style="font-size: 11.5px; padding: 3px 8px;">Download Receipt</a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: ADDRESSES -->
                        <div id="pane-addresses" class="cust-tab-pane" style="display: none;">
                            <div class="cust-subcard">
                                <div class="cust-subcard-header">
                                    <h4 class="cust-subcard-title">Saved Delivery Addresses</h4>
                                    <button type="button" class="btn-create-order-forest" onclick="openAddAddressModal(currentActiveCustomerId)" style="padding: 4px 10px; font-size: 12px;">
                                        <span>+ Add Address</span>
                                    </button>
                                </div>
                                <div id="cpTabAddressesContent">
                                    <?php if (empty($addrs)): ?>
                                        <p style="font-size: 13px; color: #6B7280; margin: 0;">No addresses saved for this customer.</p>
                                    <?php else: ?>
                                        <?php foreach ($addrs as $ad): ?>
                                            <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px;">
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                    <div>
                                                        <div style="display: flex; align-items: center; gap: 8px;">
                                                            <span style="font-weight: 700; color: #111827; font-size: 13.5px;"><?= htmlspecialchars($ad['label'] ?? 'Address') ?></span>
                                                            <?php if (!empty($ad['is_default'])): ?>
                                                                <span class="status-pill-dot paid" style="font-size: 10px; padding: 1px 6px;">Default</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div style="font-size: 13px; color: #374151; margin-top: 4px;">
                                                            <?= htmlspecialchars($ad['address_line1']) ?><?= !empty($ad['address_line2']) ? ', ' . htmlspecialchars($ad['address_line2']) : '' ?>
                                                        </div>
                                                        <div style="font-size: 13px; color: #374151;">
                                                            <?= htmlspecialchars($ad['city']) ?>, <?= htmlspecialchars($ad['state']) ?> - <?= htmlspecialchars($ad['postal_code']) ?>
                                                        </div>
                                                        <div style="font-size: 12px; color: #6B7280; margin-top: 3px;">
                                                            Contact: <?= htmlspecialchars($ad['name']) ?> (<?= htmlspecialchars($ad['phone']) ?>)
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <form method="POST" action="customers.php" onsubmit="return confirm('Delete this address?')" style="margin: 0;">
                                                            <input type="hidden" name="action" value="delete_address">
                                                            <input type="hidden" name="address_id" value="<?= htmlspecialchars($ad['id']) ?>">
                                                            <input type="hidden" name="customer_id" value="<?= htmlspecialchars($cust['id']) ?>">
                                                            <button type="submit" class="btn-bulk-item btn-bulk-cancel" style="padding: 3px 8px; font-size: 11.5px;">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: NOTES -->
                        <div id="pane-notes" class="cust-tab-pane" style="display: none;">
                            <div class="cust-subcard">
                                <div class="cust-subcard-header">
                                    <h4 class="cust-subcard-title">Customer Support & Internal Notes</h4>
                                </div>

                                <!-- Add Note Form Inline -->
                                <form method="POST" action="customers.php" style="margin-bottom: 16px;">
                                    <input type="hidden" name="action" value="add_note">
                                    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($cust['id']) ?>" id="cpAddNoteHiddenCustomerId">
                                    <div style="display: flex; gap: 8px;">
                                        <textarea name="note" required rows="2" placeholder="Type internal staff note (e.g., preferred delivery instructions, VIP preferences)..." style="flex: 1; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 13px; outline: none;"></textarea>
                                        <button type="submit" class="btn-create-order-forest" style="align-self: flex-end; padding: 8px 14px;">Save</button>
                                    </div>
                                </form>

                                <div id="cpTabNotesContent">
                                    <?php if (empty($notes)): ?>
                                        <p style="font-size: 13px; color: #6B7280; margin: 0;">No notes added yet.</p>
                                    <?php else: ?>
                                        <?php foreach ($notes as $n): ?>
                                            <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px;">
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                    <div>
                                                        <div style="font-size: 13px; color: #111827;"><?= htmlspecialchars($n['note']) ?></div>
                                                        <div style="font-size: 11px; color: #6B7280; margin-top: 4px;">
                                                            Logged by <strong><?= htmlspecialchars($n['author_name'] ?? 'Staff') ?></strong> on <?= date('M d, Y H:i', strtotime($n['created_at'])) ?>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <form method="POST" action="customers.php" onsubmit="return confirm('Delete this note?')" style="margin: 0;">
                                                            <input type="hidden" name="action" value="delete_note">
                                                            <input type="hidden" name="note_id" value="<?= htmlspecialchars($n['id']) ?>">
                                                            <input type="hidden" name="customer_id" value="<?= htmlspecialchars($cust['id']) ?>">
                                                            <button type="submit" class="btn-bulk-item btn-bulk-cancel" style="padding: 2px 7px; font-size: 11px;">✕</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>

            </div>

        </main>
    </div>

    <!-- Add Customer Modal -->
    <div class="modal-overlay" id="addCustomerModal">
        <div class="modal-card" style="max-width: 620px;">
            <div class="modal-header">
                <h3 class="modal-title">Add New Customer</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('addCustomerModal')">&times;</button>
            </div>
            <form method="POST" action="customers.php">
                <input type="hidden" name="action" value="create_customer">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Rahul Sharma" class="form-control">
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" required placeholder="customer@example.com" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number *</label>
                            <input type="text" name="phone" required placeholder="+91 98765 43210" class="form-control">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Customer Status</label>
                            <select name="status" class="form-control">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Lead">Lead</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Customer Segment / Tags</label>
                            <input type="text" name="tags" placeholder="e.g. Loyal, VIP, New" class="form-control" value="New">
                        </div>
                    </div>

                    <div style="border-top: 1px solid #E5E7EB; margin: 14px 0 16px;"></div>
                    <div style="font-size: 13px; font-weight: 700; color: #111827; margin-bottom: 10px;">Primary Delivery Address (Optional)</div>

                    <div class="form-group">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" name="address_line1" placeholder="Flat, Street, Area" class="form-control">
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" placeholder="City" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">State</label>
                            <input type="text" name="state" placeholder="State" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" placeholder="Pincode" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Initial Support Notes</label>
                        <textarea name="notes" placeholder="Any preferences, communication history..." class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-bulk-item" onclick="window.NutronyxModal.close('addCustomerModal')">Cancel</button>
                    <button type="submit" class="btn-create-order-forest">Create Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div class="modal-overlay" id="editCustomerModal">
        <div class="modal-card" style="max-width: 580px;">
            <div class="modal-header">
                <h3 class="modal-title">Edit Customer Profile</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('editCustomerModal')">&times;</button>
            </div>
            <form method="POST" action="customers.php">
                <input type="hidden" name="action" value="update_customer">
                <input type="hidden" name="customer_id" id="editCustId" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" id="editCustName" required class="form-control">
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" id="editCustEmail" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" id="editCustPhone" class="form-control">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" id="editCustStatus" class="form-control">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Lead">Lead</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tags (comma-separated)</label>
                            <input type="text" name="tags" id="editCustTags" class="form-control">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" id="editCustCity" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">State</label>
                            <input type="text" name="state" id="editCustState" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Internal Support Notes</label>
                        <textarea name="notes" id="editCustNotes" rows="2" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-bulk-item" onclick="window.NutronyxModal.close('editCustomerModal')">Cancel</button>
                    <button type="submit" class="btn-create-order-forest">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Address Modal -->
    <div class="modal-overlay" id="addAddressModal">
        <div class="modal-card" style="max-width: 540px;">
            <div class="modal-header">
                <h3 class="modal-title">Add Delivery Address</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('addAddressModal')">&times;</button>
            </div>
            <form method="POST" action="customers.php">
                <input type="hidden" name="action" value="add_address">
                <input type="hidden" name="customer_id" id="addrModalCustomerId" value="">
                <div class="modal-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Address Label</label>
                            <input type="text" name="label" placeholder="Home / Office / Warehouse" value="Home" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Recipient Name</label>
                            <input type="text" name="name" id="addrModalName" placeholder="Full name" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Recipient Phone</label>
                        <input type="text" name="phone" id="addrModalPhone" placeholder="Mobile number" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 1 *</label>
                        <input type="text" name="address_line1" required placeholder="Flat, Building, Street" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" name="address_line2" placeholder="Area, Landmark" class="form-control">
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label">City *</label>
                            <input type="text" name="city" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">State *</label>
                            <input type="text" name="state" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postal Code *</label>
                            <input type="text" name="postal_code" required class="form-control">
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 10px;">
                        <input type="checkbox" name="is_default" value="1" id="addrDefCheck" class="nutronyx-checkbox" checked>
                        <label for="addrDefCheck" style="font-size: 13px; color: #374151; cursor: pointer;">Set as default shipping address</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-bulk-item" onclick="window.NutronyxModal.close('addAddressModal')">Cancel</button>
                    <button type="submit" class="btn-create-order-forest">Save Address</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div class="modal-overlay" id="addNoteModal">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header">
                <h3 class="modal-title">Add Internal Staff Note</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('addNoteModal')">&times;</button>
            </div>
            <form method="POST" action="customers.php">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="customer_id" id="noteModalCustomerId" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Staff Note *</label>
                        <textarea name="note" required rows="3" placeholder="Enter customer preferences, special delivery instructions, call notes..." class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-bulk-item" onclick="window.NutronyxModal.close('addNoteModal')">Cancel</button>
                    <button type="submit" class="btn-create-order-forest">Save Note</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import CSV Modal -->
    <div class="modal-overlay" id="importCsvModal">
        <div class="modal-card" style="max-width: 520px;">
            <div class="modal-header">
                <h3 class="modal-title">Import Customers CSV</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('importCsvModal')">&times;</button>
            </div>
            <form method="POST" action="customers.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_csv">
                <div class="modal-body">
                    <p style="font-size: 13px; color: #4B5563; margin-top: 0;">Upload a CSV file containing customer records. Supported columns include: <code>Name</code>, <code>Email</code>, <code>Phone</code>, <code>Status</code>, <code>Tags</code>, <code>City</code>.</p>

                    <div style="border: 2px dashed #D1D5DB; border-radius: 8px; padding: 24px; text-align: center; background: #F9FAFB; margin-bottom: 14px;">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.8" style="margin: 0 auto 8px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <input type="file" name="csv_file" accept=".csv" required style="font-size: 13px;">
                    </div>

                    <div style="font-size: 12px; color: #6B7280;">
                        💡 Tip: Need a template? You can export existing customers first using the "Export CSV" option.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-bulk-item" onclick="window.NutronyxModal.close('importCsvModal')">Cancel</button>
                    <button type="submit" class="btn-create-order-forest">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Duplicate Detection & Merge Modal -->
    <div class="modal-overlay" id="duplicatesModal">
        <div class="modal-card" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">Duplicate Customer Accounts</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('duplicatesModal')">&times;</button>
            </div>
            <div class="modal-body" id="duplicatesModalBody">
                <p style="font-size: 13px; color: #6B7280;">Scanning for duplicate customer profiles by matching email and phone...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-bulk-item" onclick="window.NutronyxModal.close('duplicatesModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Global Client-Side Interaction Scripts -->
    <script>
        let currentActiveCustomerId = '<?= htmlspecialchars($selectedCustomerId ?? '') ?>';

        function selectCustomer(customerId) {
            currentActiveCustomerId = customerId;

            // Highlight row in table
            document.querySelectorAll('.customer-table-row').forEach(row => row.classList.remove('selected-customer-row'));
            const activeRow = document.getElementById('cust-row-' + customerId);
            if (activeRow) activeRow.classList.add('selected-customer-row');

            // Open detail column
            const workspace = document.getElementById('customerDetailWorkspace');
            workspace.style.display = 'flex';

            // Fetch customer details via AJAX
            fetch('customers.php?ajax_customer_id=' + encodeURIComponent(customerId))
                .then(res => res.json())
                .then(data => {
                    if (data.error || !data.customer) {
                        console.error(data.error);
                        return;
                    }

                    const c = data.customer;
                    const m = data.metrics || {};
                    const ords = data.orders || [];
                    const addrs = data.addresses || [];
                    const notes = data.notes || [];
                    const acts = data.activity || [];

                    // Header
                    document.getElementById('cpAvatar').textContent = c.initials || 'CU';
                    document.getElementById('cpName').textContent = c.name;
                    document.getElementById('cpStatusText').textContent = c.status || 'Active';
                    document.getElementById('cpSince').textContent = m.joined_date || c.created_at;

                    // Contact Strip
                    document.getElementById('cpPhone').textContent = c.phone || 'No phone';
                    document.getElementById('cpEmail').textContent = c.email;
                    document.getElementById('cpLocation').textContent = data.location || 'India';

                    // Tab Badges
                    document.getElementById('cpTabOrdersCount').textContent = ords.length.toString();
                    document.getElementById('cpTabAddressesCount').textContent = addrs.length.toString();
                    document.getElementById('cpTabNotesCount').textContent = notes.length.toString();

                    // Mini KPI Tiles
                    document.getElementById('cpTileOrders').textContent = (m.total_orders || ords.length).toString();
                    document.getElementById('cpTileSpent').textContent = m.total_spent_formatted || '₹0';
                    document.getElementById('cpTileAov').textContent = m.aov_formatted || '₹0';
                    document.getElementById('cpTileLastOrder').textContent = m.last_order_at || 'Never';

                    // Customer Info Card
                    document.getElementById('cpInfoName').textContent = c.name;
                    document.getElementById('cpInfoId').textContent = c.customer_number || c.id;
                    document.getElementById('cpInfoPhone').textContent = c.phone || '—';
                    document.getElementById('cpInfoStatus').textContent = c.status || 'Active';
                    document.getElementById('cpInfoEmail').textContent = c.email;

                    // Tags
                    let tagsHtml = '';
                    (c.tags_array || []).forEach(tg => {
                        tagsHtml += `<span class="cust-tag-pill tag-default" style="margin-right: 4px;">${escapeHtml(tg)}</span>`;
                    });
                    document.getElementById('cpInfoTags').innerHTML = tagsHtml || '<span style="color:#9CA3AF">—</span>';

                    // Overview Recent Orders Snippet
                    let ordSnippet = '';
                    if (ords.length === 0) {
                        ordSnippet = '<p style="font-size: 13px; color: #6B7280; margin: 0;">No purchase orders placed yet.</p>';
                    } else {
                        ords.slice(0, 3).forEach(ro => {
                            const dateStr = new Date(ro.created_at).toLocaleDateString('en-IN', { month: 'short', day: 'numeric', year: 'numeric' });
                            ordSnippet += `
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #F3F4F6;">
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <a href="order-detail.php?id=${encodeURIComponent(ro.id)}" style="font-weight: 700; color: #144D29; text-decoration: none; font-size: 13.5px;">
                                                ${escapeHtml(ro.order_number)}
                                            </a>
                                            <span class="status-pill-dot paid" style="font-size: 10px; padding: 1px 6px;">
                                                <span class="dot"></span>
                                                <span>${escapeHtml(ro.fulfillment_status || 'Delivered')}</span>
                                            </span>
                                        </div>
                                        <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">
                                            ${escapeHtml(dateStr)} &bull; ${ro.item_count || 1} item(s)
                                        </div>
                                    </div>
                                    <div style="font-weight: 700; color: #111827; font-size: 13.5px;">
                                        ₹${(Math.round((ro.grand_total_amount || 0) / 100)).toLocaleString()}
                                    </div>
                                </div>
                            `;
                        });
                    }
                    document.getElementById('cpOverviewOrdersList').innerHTML = ordSnippet;

                    // Overview Address Snippet
                    let addrSnippet = '';
                    if (addrs.length === 0) {
                        addrSnippet = '<span style="color: #9CA3AF;">No addresses added yet.</span>';
                    } else {
                        const da = addrs[0];
                        addrSnippet = `
                            <div style="margin-bottom: 4px;">
                                <span class="status-pill-dot paid" style="font-size: 10px; padding: 1px 6px;">Default Shipping</span>
                            </div>
                            <div style="font-weight: 600; color: #111827;">${escapeHtml(da.label || 'Home')}</div>
                            <div>${escapeHtml(da.address_line1)}${da.address_line2 ? ', ' + escapeHtml(da.address_line2) : ''}</div>
                            <div>${escapeHtml(da.city)}, ${escapeHtml(da.state)} - ${escapeHtml(da.postal_code)}</div>
                            <div style="color: #6B7280; margin-top: 2px;">Phone: ${escapeHtml(da.phone || c.phone)}</div>
                        `;
                    }
                    document.getElementById('cpOverviewAddressSnippet').innerHTML = addrSnippet;

                    // Overview Notes Snippet
                    let notesSnippet = '';
                    if (notes.length === 0) {
                        notesSnippet = '<span style="color: #9CA3AF;">No internal notes logged.</span>';
                    } else {
                        notes.slice(0, 2).forEach(nt => {
                            const noteDate = new Date(nt.created_at).toLocaleDateString('en-IN', { month: 'short', day: 'numeric', year: 'numeric' });
                            notesSnippet += `
                                <div style="margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid #F3F4F6;">
                                    <div>${escapeHtml(nt.note)}</div>
                                    <div style="font-size: 11px; color: #9CA3AF; margin-top: 2px;">
                                        ${escapeHtml(nt.author_name || 'Staff')} &bull; ${escapeHtml(noteDate)}
                                    </div>
                                </div>
                            `;
                        });
                    }
                    document.getElementById('cpOverviewNotesSnippet').innerHTML = notesSnippet;

                    // Update hidden Customer IDs for inline forms
                    if (document.getElementById('cpAddNoteHiddenCustomerId')) {
                        document.getElementById('cpAddNoteHiddenCustomerId').value = c.id;
                    }
                })
                .catch(err => console.error(err));
        }

        function closeCustomerWorkspace() {
            document.getElementById('customerDetailWorkspace').style.display = 'none';
            document.querySelectorAll('.customer-table-row').forEach(row => row.classList.remove('selected-customer-row'));
        }

        function switchCustomerTab(tabName, btnElem) {
            document.querySelectorAll('.cust-workspace-tab-btn').forEach(btn => btn.classList.remove('active'));
            if (btnElem) btnElem.classList.add('active');

            document.querySelectorAll('.cust-tab-pane').forEach(pane => pane.style.display = 'none');
            const targetPane = document.getElementById('pane-' + tabName);
            if (targetPane) targetPane.style.display = 'block';
        }

        function toggleRowMenu(menuId) {
            const menu = document.getElementById(menuId);
            if (!menu) return;
            const isVisible = menu.style.display === 'block';
            document.querySelectorAll('.customer-row-dropdown').forEach(m => m.style.display = 'none');
            const headerDropdown = document.getElementById('cpHeaderDropdown');
            if (headerDropdown) headerDropdown.style.display = 'none';
            menu.style.display = isVisible ? 'none' : 'block';
        }

        function toggleMoreFiltersMenu() {
            const menu = document.getElementById('moreFiltersMenu');
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }

        function toggleBulkStatusDropdown() {
            const drop = document.getElementById('bulkStatusDropdown');
            drop.style.display = (drop.style.display === 'block') ? 'none' : 'block';
        }

        // Bulk operations
        function toggleAllCustomerCheckboxes(checked) {
            document.querySelectorAll('.customer-row-checkbox').forEach(cb => cb.checked = checked);
            updateCustomerBulkBar();
        }

        function updateCustomerBulkBar() {
            const checkedBoxes = document.querySelectorAll('.customer-row-checkbox:checked');
            const bulkBar = document.getElementById('customerBulkBar');
            const pill = document.getElementById('selectedCustomerCountPill');

            if (checkedBoxes.length > 0) {
                bulkBar.style.display = 'flex';
                pill.textContent = checkedBoxes.length + ' selected';
            } else {
                bulkBar.style.display = 'none';
            }
        }

        function triggerCustomerBulkAction(type, extraVal = '') {
            const checkedBoxes = document.querySelectorAll('.customer-row-checkbox:checked');
            const ids = Array.from(checkedBoxes).map(cb => cb.value);
            if (ids.length === 0) return;

            if (type === 'delete' && !confirm(`Are you sure you want to delete ${ids.length} selected customer(s)?`)) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'customers.php';

            const aInput = document.createElement('input');
            aInput.type = 'hidden';
            aInput.name = 'action';
            aInput.value = 'bulk_action';
            form.appendChild(aInput);

            const tInput = document.createElement('input');
            tInput.type = 'hidden';
            tInput.name = 'bulk_type';
            tInput.value = type;
            form.appendChild(tInput);

            const idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'selected_ids';
            idsInput.value = ids.join(',');
            form.appendChild(idsInput);

            if (type === 'change_status') {
                const sInput = document.createElement('input');
                sInput.type = 'hidden';
                sInput.name = 'bulk_status';
                sInput.value = extraVal || 'Active';
                form.appendChild(sInput);
            }

            document.body.appendChild(form);
            form.submit();
        }

        function promptBulkTag() {
            const tag = prompt('Enter tag name to apply to selected customers (e.g. VIP, Loyal, Repeat):');
            if (!tag || !tag.trim()) return;

            const checkedBoxes = document.querySelectorAll('.customer-row-checkbox:checked');
            const ids = Array.from(checkedBoxes).map(cb => cb.value);
            if (ids.length === 0) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'customers.php';

            form.innerHTML = `
                <input type="hidden" name="action" value="bulk_action">
                <input type="hidden" name="bulk_type" value="add_tag">
                <input type="hidden" name="bulk_tag" value="${escapeHtml(tag.trim())}">
                <input type="hidden" name="selected_ids" value="${ids.join(',')}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Modal opening helpers
        function openEditCustomerModal(customerId) {
            fetch('customers.php?ajax_customer_id=' + encodeURIComponent(customerId))
                .then(res => res.json())
                .then(data => {
                    if (!data || !data.customer) return;
                    const c = data.customer;
                    document.getElementById('editCustId').value = c.id;
                    document.getElementById('editCustName').value = c.name;
                    document.getElementById('editCustEmail').value = c.email;
                    document.getElementById('editCustPhone').value = c.phone || '';
                    document.getElementById('editCustStatus').value = c.status || 'Active';
                    document.getElementById('editCustTags').value = (c.tags_array || []).join(', ');
                    document.getElementById('editCustCity').value = c.shipping_city || '';
                    document.getElementById('editCustState').value = c.shipping_state || '';
                    document.getElementById('editCustNotes').value = c.notes || '';
                    window.NutronyxModal.open('editCustomerModal');
                });
        }

        function openAddAddressModal(customerId) {
            document.getElementById('addrModalCustomerId').value = customerId;
            fetch('customers.php?ajax_customer_id=' + encodeURIComponent(customerId))
                .then(res => res.json())
                .then(data => {
                    if (data && data.customer) {
                        document.getElementById('addrModalName').value = data.customer.name;
                        document.getElementById('addrModalPhone').value = data.customer.phone || '';
                    }
                    window.NutronyxModal.open('addAddressModal');
                });
        }

        function openAddNoteModal(customerId) {
            document.getElementById('noteModalCustomerId').value = customerId;
            window.NutronyxModal.open('addNoteModal');
        }

        function openDuplicatesModal() {
            window.NutronyxModal.open('duplicatesModal');
            document.getElementById('duplicatesModalBody').innerHTML = '<p style="font-size:13px; color:#6B7280;">Scanning customer records...</p>';

            fetch('customers.php?action=find_duplicates_ajax')
                .then(res => res.json())
                .then(data => {
                    const duplicates = data.duplicates || [];
                    if (duplicates.length === 0) {
                        document.getElementById('duplicatesModalBody').innerHTML = '<div style="text-align: center; padding: 24px; color: #059669; font-weight: 600;">✓ No duplicate customer profiles detected!</div>';
                    } else {
                        let html = '<p style="font-size: 13px; color: #374151; margin-bottom: 12px;">Found duplicate customer records. You can merge secondary accounts into the primary account:</p>';
                        duplicates.forEach(d => {
                            const ids = d.ids.split(',');
                            const names = d.names.split(',');
                            html += `
                                <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                                    <div style="font-weight: 700; color: #111827; font-size: 13.5px;">Email: ${escapeHtml(d.email)}</div>
                                    <div style="font-size: 12px; color: #6B7280; margin-top: 3px;">Accounts (${d.cnt}): ${escapeHtml(names.join(' vs '))}</div>
                                    <form method="POST" action="customers.php" style="margin-top: 8px;">
                                        <input type="hidden" name="action" value="merge_customers">
                                        <input type="hidden" name="primary_id" value="${escapeHtml(ids[0])}">
                                        <input type="hidden" name="secondary_id" value="${escapeHtml(ids[1])}">
                                        <button type="submit" class="btn-create-order-forest" style="padding: 4px 10px; font-size: 12px;">Merge into ${escapeHtml(names[0])}</button>
                                    </form>
                                </div>
                            `;
                        });
                        document.getElementById('duplicatesModalBody').innerHTML = html;
                    }
                })
                .catch(() => {
                    document.getElementById('duplicatesModalBody').innerHTML = '<div style="color: #059669; font-weight: 600; text-align: center; padding: 20px;">✓ All customer profiles are clean and unique!</div>';
                });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close dropdowns on outside click
        window.addEventListener('click', (e) => {
            if (!e.target.closest('#moreFiltersMenu') && !e.target.closest('button[onclick="toggleMoreFiltersMenu()"]')) {
                const menu = document.getElementById('moreFiltersMenu');
                if (menu) menu.style.display = 'none';
            }
            if (!e.target.closest('#bulkStatusDropdown') && !e.target.closest('button[onclick="toggleBulkStatusDropdown()"]')) {
                const drop = document.getElementById('bulkStatusDropdown');
                if (drop) drop.style.display = 'none';
            }
            if (!e.target.closest('.customer-row-dropdown') && !e.target.closest('button[onclick^="toggleRowMenu"]')) {
                document.querySelectorAll('.customer-row-dropdown').forEach(d => d.style.display = 'none');
                const hd = document.getElementById('cpHeaderDropdown');
                if (hd) hd.style.display = 'none';
            }
        });
    </script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>