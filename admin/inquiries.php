<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\InquiryService;

AuthService::requireAuth();

$activePage = 'inquiries';
$activeWebsite = Context::getWebsite();
$notice = '';
$error = '';

InquiryService::ensureTables();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $id = trim($_POST['inquiry_id'] ?? '');
        $status = trim($_POST['status'] ?? 'CONTACTED');
        $notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : null;

        if (!empty($id)) {
            try {
                InquiryService::updateStatus($id, $status, $notes);
                $notice = "Inquiry status updated to {$status}.";
            } catch (\Throwable $e) {
                $error = 'Error updating inquiry: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete_inquiry') {
        $id = trim($_POST['inquiry_id'] ?? '');
        if (!empty($id)) {
            try {
                InquiryService::deleteInquiry($id);
                $notice = 'Inquiry deleted successfully.';
            } catch (\Throwable $e) {
                $error = 'Error deleting inquiry: ' . $e->getMessage();
            }
        }
    }
}

// Filters & State
$typeFilter = strtoupper(trim($_GET['type'] ?? 'ALL'));
$statusFilter = strtoupper(trim($_GET['status'] ?? 'ALL'));
$searchQuery = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 15)));

$data = InquiryService::getInquiries([
    'type'     => $typeFilter,
    'status'   => $statusFilter,
    'search'   => $searchQuery,
    'page'     => $page,
    'per_page' => $perPage
]);

$inquiries = $data['inquiries'];
$total = $data['total'];
$totalPages = $data['total_pages'];
$stats = $data['stats'];

// Helper to construct filter URLs
function getInquiryPageUrl(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    return 'inquiries.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiries & Leads &mdash; Nutronyx Admin Core</title>

    <!-- Google Fonts: Cormorant Garamond, Playfair Display & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin.css?v=20261119_white">
    <style>
    :root {
        --color-forest: #143D24;
        --color-forest-dark: #0E2C1A;
        --color-forest-light: #EAF4EE;
        --color-gold: #B8860B;
        --color-gold-light: #FEF3C7;
    }

    .inq-stage {
        padding: 24px 32px 64px;
        max-width: 1440px;
        margin: 0 auto;
    }

    /* KPI Grid */
    .kpi-deck {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-top: 24px;
        margin-bottom: 28px;
    }

    .kpi-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 14px;
        padding: 20px 22px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07);
    }

    .kpi-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .kpi-icon-box.total { background: #F3F4F6; color: #4B5563; }
    .kpi-icon-box.new { background: #FEF3C7; color: #D97706; }
    .kpi-icon-box.contact { background: #E8F5E9; color: #166534; }
    .kpi-icon-box.partner { background: #EDE9FE; color: #6D28D9; }

    .kpi-title {
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #6B7280;
        margin-bottom: 4px;
    }

    .kpi-val {
        font-size: 26px;
        font-weight: 700;
        color: #111827;
        line-height: 1.1;
    }

    /* Filter Tabs */
    .tabs-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .tab-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 600;
        text-decoration: none;
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        color: #4B5563;
        transition: all 0.2s ease;
    }

    .tab-pill:hover {
        border-color: #D1D5DB;
        color: #111827;
    }

    .tab-pill.active {
        background: var(--color-forest);
        color: #FFFFFF;
        border-color: var(--color-forest);
    }

    .tab-pill .pill-counter {
        font-size: 11px;
        padding: 2px 7px;
        border-radius: 12px;
        background: rgba(0, 0, 0, 0.06);
    }

    .tab-pill.active .pill-counter {
        background: rgba(255, 255, 255, 0.25);
        color: #FFFFFF;
    }

    /* Toolbar Filter Bar */
    .toolbar-bar {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .toolbar-search {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 260px;
        max-width: 440px;
        background: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 8px 12px;
    }

    .toolbar-search input {
        border: none;
        background: transparent;
        font-size: 13.5px;
        width: 100%;
        outline: none;
        color: #111827;
    }

    .toolbar-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .select-dropdown {
        padding: 8px 14px;
        border-radius: 8px;
        border: 1px solid #E5E7EB;
        background: #FFFFFF;
        font-size: 13px;
        color: #374151;
        outline: none;
        cursor: pointer;
    }

    /* Table Container */
    /* Table Container */
    .table-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 14px;
        overflow: visible;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    }

    .inq-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .inq-table th {
        background: #F9FAFB;
        padding: 13px 18px;
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #6B7280;
        border-bottom: 1px solid #E5E7EB;
    }

    .inq-table td {
        padding: 15px 18px;
        font-size: 13.5px;
        border-bottom: 1px solid #F3F4F6;
        vertical-align: middle;
        color: #374151;
    }

    .inq-table tr:last-child td {
        border-bottom: none;
    }

    .inq-table tr:hover {
        background: #FAF9F6;
    }

    /* Badges */
    .badge-type {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .badge-type.contact {
        background: #E8F5E9;
        color: #166534;
        border: 1px solid #C8E6C9;
    }

    .badge-type.partnership {
        background: #FEF3C7;
        color: #92400E;
        border: 1px solid #FDE68A;
    }

    /* Status dot pills */
    .status-pill-dot {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
    }
    .status-pill-dot .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }

    .contact-card-subtext {
        font-size: 12px;
        color: #6B7280;
        margin-top: 3px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }

    .contact-link {
        color: #143D24;
        text-decoration: none;
        font-weight: 500;
    }

    .contact-link:hover {
        text-decoration: underline;
    }

    .msg-excerpt {
        max-width: 320px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #4B5563;
        font-size: 13px;
        cursor: pointer;
        padding: 3px 6px;
        border-radius: 6px;
        background: #F9FAFB;
        border: 1px solid #F3F4F6;
        display: inline-block;
        transition: all 0.15s ease;
    }

    .msg-excerpt:hover {
        color: #111827;
        background: #F3F4F6;
        border-color: #E5E7EB;
    }

    /* Action Dropdown Styling */
    .action-dropdown-wrap {
        position: relative;
        display: inline-block;
    }
    .action-dots-btn {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        line-height: 1;
        cursor: pointer;
        color: #6B7280;
        transition: all 0.15s;
    }
    .action-dots-btn:hover {
        background: #F3F4F6;
        color: #111827;
        border-color: #D1D5DB;
    }
    .action-dropdown-menu {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 4px;
        width: 195px;
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        z-index: 1050;
        padding: 6px;
        text-align: left;
    }
    .action-dropdown-menu.show {
        display: block;
    }
    .action-menu-item {
        display: flex;
        align-items: center;
        gap: 9px;
        width: 100%;
        padding: 8px 10px;
        border-radius: 8px;
        border: none;
        background: transparent;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        text-align: left;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.12s ease;
    }
    .action-menu-item:hover {
        background: #F3F4F6;
        color: #111827;
    }
    .action-menu-item.danger {
        color: #DC2626;
    }
    .action-menu-item.danger:hover {
        background: #FEF2F2;
        color: #B91C1C;
    }

    .btn-action-view {
        background: #F3F4F6;
        border: 1px solid #E5E7EB;
        color: #374151;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    .btn-action-view:hover {
        background: #E5E7EB;
        color: #111827;
    }

    /* Modal Backdrop */
    .inq-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(17, 24, 39, 0.55);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .inq-modal-card {
        background: #FFFFFF;
        width: 100%;
        max-width: 680px;
        border-radius: 18px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 90vh;
    }

    .inq-modal-header {
        padding: 20px 28px;
        background: #143D24;
        color: #FFFFFF;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .inq-modal-body {
        padding: 28px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .modal-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        background: #FAF9F6;
        padding: 16px 20px;
        border-radius: 12px;
        border: 1px solid #EFEAE1;
    }

    .modal-detail-item .label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #8A9A90;
        margin-bottom: 2px;
    }

    .modal-detail-item .val {
        font-size: 14px;
        font-weight: 600;
        color: #14221A;
    }

    .modal-msg-box {
        background: #FAF9F6;
        border-left: 4px solid var(--color-forest);
        padding: 18px 20px;
        border-radius: 8px;
        font-size: 14px;
        line-height: 1.6;
        color: #1F2937;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .inq-modal-footer {
        padding: 18px 28px;
        background: #F9FAFB;
        border-top: 1px solid #E5E7EB;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .btn-reply-email {
        background: var(--color-forest);
        color: #FFFFFF !important;
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-reply-email:hover {
        background: var(--color-forest-dark);
    }
    </style>
</head>
<body class="admin-body">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="admin-layout-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main-stage">
            <div class="inq-stage">

                <!-- Header Row -->
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                    <div>
                        <h1 class="page-title-serif">Inquiries & Leads</h1>
                        <p class="page-subtitle-muted">
                            Incoming customer contact messages &amp; partnership proposals. Notifications are automatically emailed to <strong>nutronyx.offical@gmail.com</strong>.
                        </p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <a href="mailto:nutronyx.offical@gmail.com" class="btn-action-view" title="Open Gmail notification inbox">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            <span>nutronyx.offical@gmail.com</span>
                        </a>
                    </div>
                </div>

                <?php if (!empty($notice)): ?>
                    <div style="padding: 12px 18px; border-radius: 10px; background: #DCFCE7; border: 1px solid #BBF7D0; color: #166534; margin-top: 18px; font-size: 14px;">
                        <?= htmlspecialchars($notice) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div style="padding: 12px 18px; border-radius: 10px; background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; margin-top: 18px; font-size: 14px;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- KPI Deck -->
                <div class="kpi-deck">
                    <div class="kpi-card">
                        <div class="kpi-icon-box total">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                        </div>
                        <div>
                            <div class="kpi-title">Total Inquiries</div>
                            <div class="kpi-val"><?= number_format($stats['total']) ?></div>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-box new">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        </div>
                        <div>
                            <div class="kpi-title">New / Unread</div>
                            <div class="kpi-val" style="color: #D97706;"><?= number_format($stats['new']) ?></div>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-box contact">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </div>
                        <div>
                            <div class="kpi-title">Contact Messages</div>
                            <div class="kpi-val"><?= number_format($stats['contact']) ?></div>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-box partner">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                        </div>
                        <div>
                            <div class="kpi-title">Partnership Applications</div>
                            <div class="kpi-val" style="color: #6D28D9;"><?= number_format($stats['partnership']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Tabs: All, Contact, Partnership, New -->
                <div class="tabs-row">
                    <a href="<?= getInquiryPageUrl(['type' => 'ALL', 'page' => 1]) ?>" class="tab-pill <?= ($typeFilter === 'ALL' && $statusFilter !== 'NEW') ? 'active' : '' ?>">
                        <span>All Inquiries</span>
                        <span class="pill-counter"><?= $stats['total'] ?></span>
                    </a>
                    <a href="<?= getInquiryPageUrl(['type' => 'CONTACT', 'status' => 'ALL', 'page' => 1]) ?>" class="tab-pill <?= ($typeFilter === 'CONTACT') ? 'active' : '' ?>">
                        <span>Contact Messages</span>
                        <span class="pill-counter"><?= $stats['contact'] ?></span>
                    </a>
                    <a href="<?= getInquiryPageUrl(['type' => 'PARTNERSHIP', 'status' => 'ALL', 'page' => 1]) ?>" class="tab-pill <?= ($typeFilter === 'PARTNERSHIP') ? 'active' : '' ?>">
                        <span>Partnership Applications</span>
                        <span class="pill-counter"><?= $stats['partnership'] ?></span>
                    </a>
                    <a href="<?= getInquiryPageUrl(['status' => 'NEW', 'page' => 1]) ?>" class="tab-pill <?= ($statusFilter === 'NEW') ? 'active' : '' ?>">
                        <span>Action Required</span>
                        <span class="pill-counter" style="background: #FEE2E2; color: #991B1B;"><?= $stats['new'] ?></span>
                    </a>
                </div>

                <!-- Toolbar: Search + Status filter -->
                <form method="GET" action="inquiries.php" class="toolbar-bar">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">

                    <div class="toolbar-search">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="search" placeholder="Search by name, email, phone, company, or message..." value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>

                    <div class="toolbar-actions">
                        <select name="status" class="select-dropdown" onchange="this.form.submit()">
                            <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="NEW" <?= $statusFilter === 'NEW' ? 'selected' : '' ?>>New</option>
                            <option value="CONTACTED" <?= $statusFilter === 'CONTACTED' ? 'selected' : '' ?>>Contacted</option>
                            <option value="RESOLVED" <?= $statusFilter === 'RESOLVED' ? 'selected' : '' ?>>Resolved</option>
                        </select>

                        <button type="submit" class="btn-action-view">Filter</button>
                        <?php if ($searchQuery !== '' || $statusFilter !== 'ALL' || $typeFilter !== 'ALL'): ?>
                            <a href="inquiries.php" class="btn-action-view" style="color: #6B7280;">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Data Table -->
                <div class="table-card">
                    <table class="inq-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Submitter Contact</th>
                                <th>Category / Subject</th>
                                <th>Message Excerpt</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inquiries)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 48px 16px; color: #6B7280;">
                                        <div style="font-size: 16px; font-weight: 600; margin-bottom: 6px;">No inquiries found</div>
                                        <p style="font-size: 13px; margin: 0;">Inquiries submitted through the Contact and Partnership forms will appear here.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($inquiries as $inq): 
                                    $isPartner = ($inq['type'] === 'PARTNERSHIP');
                                    $datePart = date('d M Y', strtotime($inq['created_at']));
                                    $timePart = date('h:i A', strtotime($inq['created_at']));
                                    $st = strtoupper($inq['status'] ?? 'NEW');
                                ?>
                                    <tr>
                                        <!-- Date & Time -->
                                        <td>
                                            <div style="font-size: 13px; font-weight: 600; color: #111827; white-space: nowrap;"><?= $datePart ?></div>
                                            <div style="font-size: 11.5px; color: #6B7280; margin-top: 1px;"><?= $timePart ?></div>
                                        </td>

                                        <!-- Type -->
                                        <td>
                                            <span class="badge-type <?= $isPartner ? 'partnership' : 'contact' ?>">
                                                <?php if ($isPartner): ?>
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                                                    <span>Partnership</span>
                                                <?php else: ?>
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                                    <span>Contact</span>
                                                <?php endif; ?>
                                            </span>
                                        </td>

                                        <!-- Submitter Contact -->
                                        <td>
                                            <div style="font-weight: 700; color: #111827; font-size: 13.5px;"><?= htmlspecialchars($inq['name']) ?></div>
                                            <div class="contact-card-subtext">
                                                <a href="mailto:<?= htmlspecialchars($inq['email']) ?>" class="contact-link"><?= htmlspecialchars($inq['email']) ?></a>
                                                <?php if (!empty($inq['phone'])): ?>
                                                    <span style="color: #D1D5DB;">&bull;</span>
                                                    <a href="tel:<?= htmlspecialchars($inq['phone']) ?>" class="contact-link"><?= htmlspecialchars($inq['phone']) ?></a>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($inq['company'])): ?>
                                                <div style="font-size: 11.5px; color: #4B5563; margin-top: 3px; display: flex; align-items: center; gap: 4px;">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="9" y1="22" x2="9" y2="22.01"></line><line x1="15" y1="22" x2="15" y2="22.01"></line><line x1="12" y1="18" x2="12" y2="18.01"></line></svg>
                                                    <span><?= htmlspecialchars($inq['company']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Category / Subject -->
                                        <td>
                                            <span style="display: inline-block; padding: 3px 8px; background: #F3F4F6; border-radius: 6px; font-weight: 600; font-size: 12px; color: #374151;">
                                                <?= htmlspecialchars($inq['category'] ?: 'General Inquiry') ?>
                                            </span>
                                        </td>

                                        <!-- Message Excerpt -->
                                        <td>
                                            <div class="msg-excerpt" onclick="openDetailModal(<?= htmlspecialchars(json_encode($inq)) ?>)" title="Click to view full message">
                                                <?= htmlspecialchars(mb_strimwidth($inq['message'], 0, 75, '...')) ?>
                                            </div>
                                        </td>

                                        <!-- Status Dot Pill -->
                                        <td>
                                            <?php if ($st === 'NEW'): ?>
                                                <span class="status-pill-dot" style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A;">
                                                    <span class="dot" style="background: #D97706;"></span>
                                                    <span>New</span>
                                                </span>
                                            <?php elseif ($st === 'CONTACTED'): ?>
                                                <span class="status-pill-dot" style="background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD;">
                                                    <span class="dot" style="background: #0284C7;"></span>
                                                    <span>Contacted</span>
                                                </span>
                                            <?php elseif ($st === 'RESOLVED'): ?>
                                                <span class="status-pill-dot" style="background: #DCFCE7; color: #15803D; border: 1px solid #BBF7D0;">
                                                    <span class="dot" style="background: #10B981;"></span>
                                                    <span>Resolved</span>
                                                </span>
                                            <?php else: ?>
                                                <span class="status-pill-dot" style="background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB;">
                                                    <span class="dot" style="background: #9CA3AF;"></span>
                                                    <span><?= htmlspecialchars(ucfirst(strtolower($st))) ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 3-Dots Action Menu -->
                                        <td style="text-align: right; padding-right: 18px;" onclick="event.stopPropagation()">
                                            <div class="action-dropdown-wrap">
                                                <button type="button" class="action-dots-btn" onclick="toggleInqMenu('<?= $inq['id'] ?>', event)" title="Inquiry Actions">
                                                    &bull;&bull;&bull;
                                                </button>
                                                <div id="inqMenu_<?= $inq['id'] ?>" class="action-dropdown-menu">
                                                    <button type="button" class="action-menu-item" onclick="openDetailModal(<?= htmlspecialchars(json_encode($inq)) ?>)">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                        <span>View Full Message</span>
                                                    </button>
                                                    <a href="mailto:<?= htmlspecialchars($inq['email']) ?>?subject=Re: <?= urlencode($inq['category'] ?? 'Nutronyx Inquiry') ?>" class="action-menu-item">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                                        <span>Send Email Reply</span>
                                                    </a>
                                                    <div style="height: 1px; background: #F3F4F6; margin: 4px 0;"></div>
                                                    <form method="POST" action="inquiries.php" style="margin: 0;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="inquiry_id" value="<?= htmlspecialchars($inq['id']) ?>">
                                                        <input type="hidden" name="status" value="CONTACTED">
                                                        <button type="submit" class="action-menu-item">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                            <span>Mark Contacted</span>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="inquiries.php" style="margin: 0;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="inquiry_id" value="<?= htmlspecialchars($inq['id']) ?>">
                                                        <input type="hidden" name="status" value="RESOLVED">
                                                        <button type="submit" class="action-menu-item" style="color: #059669; font-weight: 600;">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                                            <span>Mark Resolved</span>
                                                        </button>
                                                    </form>
                                                    <div style="height: 1px; background: #F3F4F6; margin: 4px 0;"></div>
                                                    <form method="POST" action="inquiries.php" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this inquiry record?');">
                                                        <input type="hidden" name="action" value="delete_inquiry">
                                                        <input type="hidden" name="inquiry_id" value="<?= htmlspecialchars($inq['id']) ?>">
                                                        <button type="submit" class="action-menu-item danger">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                            <span>Delete Inquiry</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div style="padding: 16px 20px; border-top: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between; font-size: 13px; color: #6B7280;">
                            <div>Showing page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> items)</div>
                            <div style="display: flex; gap: 6px;">
                                <?php if ($page > 1): ?>
                                    <a href="<?= getInquiryPageUrl(['page' => $page - 1]) ?>" class="btn-action-view">&laquo; Prev</a>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="<?= getInquiryPageUrl(['page' => $i]) ?>" class="btn-action-view" style="<?= $i === $page ? 'background: #143D24; color: #FFF; border-color: #143D24;' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= getInquiryPageUrl(['page' => $page + 1]) ?>" class="btn-action-view">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <!-- Inquiry Details Modal -->
    <div class="inq-modal-backdrop" id="inquiryModal">
        <div class="inq-modal-card">
            <div class="inq-modal-header">
                <div>
                    <h3 style="margin: 0; font-size: 17px; font-weight: 700;" id="modalTitle">Inquiry Details</h3>
                    <p style="margin: 3px 0 0; font-size: 12px; color: #D1E7DD;" id="modalSubtitle">Reference ID</p>
                </div>
                <button type="button" onclick="closeDetailModal()" style="background: transparent; border: none; color: #FFFFFF; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            
            <div class="inq-modal-body">
                <div class="modal-detail-grid">
                    <div class="modal-detail-item">
                        <div class="label">Submitter Name</div>
                        <div class="val" id="modalName">-</div>
                    </div>
                    <div class="modal-detail-item">
                        <div class="label">Submission Type</div>
                        <div class="val" id="modalType">-</div>
                    </div>
                    <div class="modal-detail-item">
                        <div class="label">Email Address</div>
                        <div class="val"><a href="#" id="modalEmailLink" class="contact-link">-</a></div>
                    </div>
                    <div class="modal-detail-item">
                        <div class="label">Phone Number</div>
                        <div class="val"><a href="#" id="modalPhoneLink" class="contact-link">-</a></div>
                    </div>
                    <div class="modal-detail-item">
                        <div class="label">Company / Practice</div>
                        <div class="val" id="modalCompany">None / Individual</div>
                    </div>
                    <div class="modal-detail-item">
                        <div class="label">Category / Subject</div>
                        <div class="val" id="modalCategory">-</div>
                    </div>
                </div>

                <div>
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6B7280; margin-bottom: 6px;">
                        Full Message / Proposed Collaboration:
                    </div>
                    <div class="modal-msg-box" id="modalMessage"></div>
                </div>

                <!-- Status Update Form -->
                <form method="POST" action="inquiries.php" style="background: #F9FAFB; padding: 18px 20px; border-radius: 12px; border: 1px solid #E5E7EB;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="inquiry_id" id="modalFormInquiryId">

                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
                        <label for="statusSelect" style="font-size: 13px; font-weight: 700; color: #374151;">Update Lead Status:</label>
                        <select name="status" id="statusSelect" class="select-dropdown">
                            <option value="NEW">NEW</option>
                            <option value="CONTACTED">CONTACTED</option>
                            <option value="RESOLVED">RESOLVED</option>
                            <option value="ARCHIVED">ARCHIVED</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label for="modalAdminNotes" style="font-size: 12px; font-weight: 600; color: #6B7280; display: block; margin-bottom: 4px;">Internal Admin Notes:</label>
                        <textarea name="admin_notes" id="modalAdminNotes" rows="2" style="width: 100%; border: 1px solid #D1D5DB; border-radius: 8px; padding: 8px 12px; font-size: 13px; font-family: inherit; box-sizing: border-box;" placeholder="Add private notes about conversations or follow-up status..."></textarea>
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" class="btn-action-view" style="background: #143D24; color: #FFF; border-color: #143D24;">Save Status &amp; Notes</button>
                    </div>
                </form>
            </div>

            <div class="inq-modal-footer">
                <form method="POST" action="inquiries.php" onsubmit="return confirm('Are you sure you want to delete this inquiry record?');">
                    <input type="hidden" name="action" value="delete_inquiry">
                    <input type="hidden" name="inquiry_id" id="modalDeleteInquiryId">
                    <button type="submit" class="btn-action-view" style="color: #DC2626; border-color: #FECACA;">Delete Record</button>
                </form>

                <div style="display: flex; gap: 10px;">
                    <a href="#" id="modalDirectReplyBtn" class="btn-reply-email" target="_blank">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        <span>Reply via Email</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    const modal = document.getElementById('inquiryModal');

    function openDetailModal(inq) {
        document.getElementById('modalTitle').textContent = inq.type === 'PARTNERSHIP' ? 'Partnership Application' : 'Contact Message';
        document.getElementById('modalSubtitle').textContent = 'ID: ' + inq.id + ' • ' + inq.created_at;
        document.getElementById('modalName').textContent = inq.name || '-';
        document.getElementById('modalType').textContent = inq.type || 'CONTACT';
        
        const emailLink = document.getElementById('modalEmailLink');
        emailLink.textContent = inq.email || '-';
        emailLink.href = 'mailto:' + encodeURIComponent(inq.email);

        const phoneLink = document.getElementById('modalPhoneLink');
        phoneLink.textContent = inq.phone || 'Not provided';
        phoneLink.href = inq.phone ? ('tel:' + encodeURIComponent(inq.phone)) : '#';

        document.getElementById('modalCompany').textContent = inq.company || 'Not specified';
        document.getElementById('modalCategory').textContent = inq.category || 'General';
        document.getElementById('modalMessage').textContent = inq.message || '';

        document.getElementById('modalFormInquiryId').value = inq.id;
        document.getElementById('modalDeleteInquiryId').value = inq.id;
        document.getElementById('statusSelect').value = inq.status || 'NEW';
        document.getElementById('modalAdminNotes').value = inq.admin_notes || '';

        // Prefill email reply
        const replySubject = encodeURIComponent('Re: Nutronyx ' + (inq.category || inq.type));
        document.getElementById('modalDirectReplyBtn').href = 'mailto:' + encodeURIComponent(inq.email) + '?subject=' + replySubject;

        modal.style.display = 'flex';
    }

    function closeDetailModal() {
        modal.style.display = 'none';
    }

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeDetailModal();
        }
    });

    function toggleInqMenu(id, e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('inqMenu_' + id);
        const isShown = menu && menu.classList.contains('show');
        document.querySelectorAll('.action-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        if (!isShown && menu) {
            menu.classList.add('show');
        }
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.action-dropdown-wrap')) {
            document.querySelectorAll('.action-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        }
    });

    // Check if ?id= is in URL to auto-open
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const autoId = urlParams.get('id');
        if (autoId) {
            const tableRows = <?= json_encode($inquiries) ?>;
            const target = tableRows.find(item => item.id === autoId);
            if (target) {
                openDetailModal(target);
            }
        }
    });
    </script>
</body>
</html>
