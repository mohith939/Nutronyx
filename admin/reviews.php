<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Context;
use Nutronyx\Database;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\ReviewService;
use Nutronyx\Services\AuditService;

AuthService::requireAuth();

$activePage = 'reviews';
$activeWebsite = Context::getWebsite();
$notice = '';
$error = '';

ReviewService::ensureTables();

if (!function_exists('getReviewsPageUrl')) {
    function getReviewsPageUrl(int $targetPage): string
    {
        $params = $_GET;
        $params['page'] = $targetPage;
        return 'reviews.php?' . http_build_query($params);
    }
}

// Handle Form Submissions

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create Manual Admin Review
    if ($action === 'create_admin_review') {
        try {
            $prodVal = $_POST['product_selection'] ?? '';
            $prodParts = explode('|', $prodVal);
            $productId = $prodParts[0] ?? '';
            $variantId = $prodParts[1] ?? null;

            if (empty($productId)) {
                throw new \Exception('Please select a target catalog product.');
            }

            $customerId = !empty($_POST['customer_id']) && $_POST['customer_id'] !== 'guest' ? $_POST['customer_id'] : null;
            $customerName = trim($_POST['customer_name'] ?? '');

            $id = ReviewService::createAdminReview([
                'product_id' => $productId,
                'variant_id' => $variantId,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'rating' => (int)($_POST['rating'] ?? 5),
                'title' => trim($_POST['title'] ?? ''),
                'comment' => trim($_POST['comment'] ?? ''),
                'status' => $_POST['status'] ?? 'APPROVED',
                'created_at' => !empty($_POST['created_at']) ? $_POST['created_at'] : date('Y-m-d H:i:s'),
                'admin_reply' => trim($_POST['admin_reply'] ?? '') ?: null,
                'internal_notes' => trim($_POST['internal_notes'] ?? '') ?: null
            ]);

            $notice = 'Product review successfully created and saved.';
        } catch (\Throwable $e) {
            $error = 'Error adding review: ' . $e->getMessage();
        }
    }

    // 2. Moderate Review
    if ($action === 'moderate') {
        try {
            $id = $_POST['review_id'] ?? '';
            $status = $_POST['status'] ?? 'APPROVED';
            $reply = isset($_POST['admin_reply']) ? trim($_POST['admin_reply']) : null;
            $notes = isset($_POST['internal_notes']) ? trim($_POST['internal_notes']) : null;

            if ($id) {
                ReviewService::moderate($id, $status, $reply, $notes);
                $notice = "Review status updated to {$status}.";
            }
        } catch (\Throwable $e) {
            $error = 'Moderation error: ' . $e->getMessage();
        }
    }

    // 3. Edit Review
    if ($action === 'update_review') {
        try {
            $id = $_POST['review_id'] ?? '';
            if ($id) {
                ReviewService::updateReview($id, [
                    'rating' => (int)($_POST['rating'] ?? 5),
                    'title' => trim($_POST['title'] ?? ''),
                    'comment' => trim($_POST['comment'] ?? ''),
                    'customer_name' => trim($_POST['customer_name'] ?? ''),
                    'status' => $_POST['status'] ?? 'APPROVED',
                    'internal_notes' => trim($_POST['internal_notes'] ?? '') ?: null,
                    'admin_reply' => trim($_POST['admin_reply'] ?? '') ?: null
                ]);
                $notice = 'Review details updated successfully.';
            }
        } catch (\Throwable $e) {
            $error = 'Error updating review: ' . $e->getMessage();
        }
    }

    // 4. Staff Response Save
    if ($action === 'save_staff_reply') {
        try {
            $id = $_POST['review_id'] ?? '';
            $reply = trim($_POST['admin_reply'] ?? '');
            if ($id && !empty($reply)) {
                ReviewService::saveStaffReply($id, $reply);
                $notice = 'Official staff response published.';
            }
        } catch (\Throwable $e) {
            $error = 'Error saving staff reply: ' . $e->getMessage();
        }
    }

    // 5. Delete Staff Response
    if ($action === 'delete_staff_reply') {
        try {
            $id = $_POST['review_id'] ?? '';
            if ($id) {
                ReviewService::deleteStaffReply($id);
                $notice = 'Staff response removed.';
            }
        } catch (\Throwable $e) {
            $error = 'Error removing staff reply: ' . $e->getMessage();
        }
    }

    // 6. Delete Review
    if ($action === 'delete_review') {
        try {
            $id = $_POST['review_id'] ?? '';
            if ($id) {
                ReviewService::deleteReview($id);
                $notice = 'Review permanently deleted.';
            }
        } catch (\Throwable $e) {
            $error = 'Error deleting review: ' . $e->getMessage();
        }
    }

    // 7. Bulk Action
    if ($action === 'bulk_action') {
        try {
            $bulkType = $_POST['bulk_type'] ?? '';
            $selectedIds = $_POST['selected_ids'] ?? [];
            if (!empty($selectedIds) && is_array($selectedIds)) {
                $count = ReviewService::bulkModerate($selectedIds, $bulkType);
                $notice = "Bulk action '{$bulkType}' applied to {$count} reviews.";
            }
        } catch (\Throwable $e) {
            $error = 'Bulk action error: ' . $e->getMessage();
        }
    }
}

// Filters & State
$statusFilter = $_GET['status'] ?? 'ALL';
$ratingFilter = isset($_GET['rating']) && $_GET['rating'] !== '' ? (int)$_GET['rating'] : null;
$productFilter = $_GET['product_id'] ?? 'ALL';
$searchQuery = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort_by'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 10)));

// Fetch paginated reviews and analytics
$reviewsData = ReviewService::getReviews([
    'status' => $statusFilter,
    'rating' => $ratingFilter,
    'product_id' => $productFilter,
    'search' => $searchQuery,
    'sort_by' => $sortBy,
    'page' => $page,
    'per_page' => $perPage
]);

$reviews = $reviewsData['reviews'];
$totalReviews = $reviewsData['total'];
$totalPages = $reviewsData['total_pages'];
$offset = $reviewsData['offset'];

// Fetch Analytics KPIs
$analytics = ReviewService::getReviewAnalytics();

// Fetch products with variants for the Add Review dropdown
$db = Database::getConnection();
$pStmt = $db->prepare("
    SELECT p.id, p.title, p.image_url, pv.id as variant_id, pv.title as variant_title, pv.weight_grams 
    FROM products p 
    LEFT JOIN product_variants pv ON p.id = pv.product_id 
    WHERE p.website_id = :wid 
    ORDER BY p.title ASC, pv.weight_grams ASC
");
$pStmt->execute([':wid' => Context::getWebsiteId()]);
$catalogProducts = $pStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing customers for reviewer picker
$cStmt = $db->prepare("SELECT id, name, email, phone FROM customers WHERE website_id = :wid ORDER BY name ASC");
$cStmt->execute([':wid' => Context::getWebsiteId()]);
$customerList = $cStmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct products for filter dropdown
$distinctProdStmt = $db->prepare("SELECT id, title FROM products WHERE website_id = :wid ORDER BY title ASC");
$distinctProdStmt->execute([':wid' => Context::getWebsiteId()]);
$filterProducts = $distinctProdStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews & Customer Trust &mdash; Nutronyx Admin Core</title>
    
    <!-- Google Fonts: Cormorant Garamond, Playfair Display & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
    :root {
        --color-forest: #144D29;
        --color-forest-dark: #0F3B1F;
        --color-forest-light: #EAF4EE;
        --color-cream: #FAF9F6;
        --color-border: #E5E7EB;
        --color-text-main: #111827;
        --color-text-muted: #6B7280;
        --color-gold: #F59E0B;
        --color-gold-bg: #FEF3C7;
        --color-success-bg: #DEF7EC;
        --color-success-text: #03543F;
        --color-pending-bg: #FEF08A;
        --color-pending-text: #854D0E;
        --color-rejected-bg: #FDE8E8;
        --color-rejected-text: #9B1C1C;
        --color-hidden-bg: #F3F4F6;
        --color-hidden-text: #4B5563;
        --color-purple-bg: #F3E8FF;
        --color-purple-text: #7E22CE;
    }

    body.admin-body {
        background-color: var(--color-cream);
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        color: var(--color-text-main);
    }

    /* Page Layout */
    .reviews-stage {
        padding: 24px 32px 60px;
        max-width: 1560px;
        margin: 0 auto;
    }

    .page-title-serif {
        font-family: 'Cormorant Garamond', 'Playfair Display', Georgia, serif;
        font-size: 32px;
        font-weight: 700;
        color: #111827;
        line-height: 1.2;
        margin: 0 0 6px 0;
        letter-spacing: -0.5px;
    }

    .page-subtitle-muted {
        font-size: 14px;
        color: #6B7280;
        margin: 0;
        font-weight: 400;
    }

    .btn-forest-primary {
        background-color: var(--color-forest);
        color: #FFFFFF;
        font-size: 13.5px;
        font-weight: 600;
        border-radius: 8px;
        padding: 10px 18px;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(20, 77, 41, 0.15);
    }
    .btn-forest-primary:hover {
        background-color: var(--color-forest-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(20, 77, 41, 0.22);
    }

    /* 4 Top KPI Cards */
    .kpi-row-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-top: 24px;
        margin-bottom: 24px;
    }

    .kpi-stat-card {
        background: #FFFFFF;
        border: 1px solid var(--color-border);
        border-radius: 16px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 18px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-stat-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }

    .kpi-icon-circle {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .kpi-icon-green {
        background-color: #ECFDF5;
        color: var(--color-forest);
    }
    .kpi-icon-amber {
        background-color: #FFFBEB;
        color: #D97706;
    }

    .kpi-stat-content {
        display: flex;
        flex-direction: column;
    }
    .kpi-stat-label {
        font-size: 13px;
        font-weight: 600;
        color: #6B7280;
        margin-bottom: 4px;
    }
    .kpi-stat-value {
        font-size: 26px;
        font-weight: 700;
        color: #111827;
        line-height: 1.1;
        letter-spacing: -0.5px;
        display: flex;
        align-items: baseline;
        gap: 4px;
    }
    .kpi-stat-suffix {
        font-size: 15px;
        font-weight: 500;
        color: #9CA3AF;
    }

    /* Status Filter Tabs (Pill style) */
    .filter-tabs-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .status-pill-tab {
        padding: 8px 18px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        background: #FFFFFF;
        color: #4B5563;
        border: 1px solid var(--color-border);
    }
    .status-pill-tab:hover {
        background: #F9FAFB;
        color: #111827;
    }
    .status-pill-tab.active {
        background: var(--color-forest);
        color: #FFFFFF;
        border-color: var(--color-forest);
        box-shadow: 0 2px 6px rgba(20, 77, 41, 0.2);
    }

    /* Search & Filter Toolbar */
    .reviews-toolbar-card {
        background: #FFFFFF;
        border: 1px solid var(--color-border);
        border-radius: 14px;
        padding: 14px 20px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .search-input-group {
        position: relative;
        flex: 1;
        min-width: 280px;
        max-width: 460px;
    }
    .search-input-group svg {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #9CA3AF;
        pointer-events: none;
    }
    .search-input-control {
        width: 100%;
        padding: 10px 14px 10px 42px;
        border: 1px solid var(--color-border);
        border-radius: 8px;
        font-size: 13.5px;
        color: #111827;
        background-color: #FAF9F6;
        transition: all 0.2s;
        box-sizing: border-box;
    }
    .search-input-control:focus {
        outline: none;
        background-color: #FFFFFF;
        border-color: var(--color-forest);
        box-shadow: 0 0 0 3px rgba(20, 77, 41, 0.1);
    }

    .toolbar-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .dropdown-select {
        padding: 9px 14px;
        border: 1px solid var(--color-border);
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        background-color: #FFFFFF;
        cursor: pointer;
        outline: none;
        transition: border 0.2s;
    }
    .dropdown-select:focus {
        border-color: var(--color-forest);
    }

    /* Table Container */
    .table-surface-card {
        background: #FFFFFF;
        border: 1px solid var(--color-border);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }

    .reviews-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }
    .reviews-table th {
        background-color: #FAFAFA;
        padding: 14px 18px;
        font-size: 11.5px;
        font-weight: 700;
        color: #6B7280;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        border-bottom: 1px solid var(--color-border);
    }
    .reviews-table td {
        padding: 16px 18px;
        border-bottom: 1px solid #F3F4F6;
        vertical-align: middle;
        font-size: 13.5px;
    }
    .reviews-table tr:hover td {
        background-color: #FBFDFB;
    }
    .reviews-table tr:last-child td {
        border-bottom: none;
    }

    /* Table Cells */
    .product-cell {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .product-thumb {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        object-fit: cover;
        border: 1px solid var(--color-border);
        background-color: #F9FAFB;
        flex-shrink: 0;
    }
    .product-info-text {
        display: flex;
        flex-direction: column;
    }
    .product-name-bold {
        font-weight: 600;
        color: #111827;
        font-size: 13.5px;
        line-height: 1.3;
    }
    .product-variant-muted {
        font-size: 12px;
        color: #6B7280;
        margin-top: 2px;
    }

    .reviewer-cell {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .reviewer-name {
        font-weight: 600;
        color: #111827;
        font-size: 13.5px;
    }

    /* Badges */
    .badge-verified {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 600;
        background-color: var(--color-success-bg);
        color: var(--color-success-text);
        padding: 2px 8px;
        border-radius: 4px;
        width: fit-content;
    }
    .badge-admin-added {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 600;
        background-color: var(--color-purple-bg);
        color: var(--color-purple-text);
        padding: 2px 8px;
        border-radius: 4px;
        width: fit-content;
    }
    .badge-unverified {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 500;
        background-color: #F3F4F6;
        color: #4B5563;
        border: 1px solid #E5E7EB;
        padding: 2px 8px;
        border-radius: 4px;
        width: fit-content;
    }

    .star-rating-gold {
        color: var(--color-gold);
        letter-spacing: 2px;
        font-size: 14.5px;
        white-space: nowrap;
    }

    .review-content-cell {
        max-width: 380px;
    }
    .review-headline {
        font-weight: 600;
        color: #111827;
        font-size: 13.5px;
        margin-bottom: 2px;
    }
    .review-body-snippet {
        font-size: 12.5px;
        color: #4B5563;
        line-height: 1.45;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .review-reply-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 6px;
        padding: 3px 8px;
        background-color: #F0FDF4;
        border: 1px solid #BBF7D0;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        color: #166534;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11.5px;
        font-weight: 600;
        letter-spacing: 0.2px;
        line-height: 1.3;
    }
    .status-badge.status-approved {
        background-color: #DCFCE7;
        color: #166534;
        border: 1px solid #BBF7D0;
    }
    .status-badge.status-pending {
        background-color: #FEF3C7;
        color: #92400E;
        border: 1px solid #FDE68A;
    }
    .status-badge.status-rejected {
        background-color: #FEE2E2;
        color: #991B1B;
        border: 1px solid #FECACA;
    }
    .status-badge.status-hidden {
        background-color: #F3F4F6;
        color: #4B5563;
        border: 1px solid #E5E7EB;
    }

    .action-icons-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .btn-icon-action {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid var(--color-border);
        background: #FFFFFF;
        color: #4B5563;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-icon-action:hover {
        background-color: #F9FAFB;
        color: var(--color-forest);
        border-color: #D1D5DB;
    }

    /* Dropdown menu */
    .dropdown-menu-container {
        position: relative;
        display: inline-block;
    }
    .dropdown-menu-popover {
        position: fixed;
        background: #FFFFFF;
        border: 1px solid var(--color-border);
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.12), 0 8px 10px -6px rgba(0,0,0,0.08);
        min-width: 175px;
        z-index: 99999;
        padding: 6px;
        display: none;
    }
    .dropdown-menu-popover.show {
        display: block;
    }
    .dropdown-menu-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        font-size: 13px;
        color: #374151;
        text-decoration: none;
        border-radius: 6px;
        transition: background 0.15s;
        cursor: pointer;
        border: none;
        background: transparent;
        width: 100%;
        text-align: left;
    }
    .dropdown-menu-item:hover {
        background-color: #F3F4F6;
        color: #111827;
    }
    .dropdown-menu-item.danger:hover {
        background-color: #FEE2E2;
        color: #991B1B;
    }

    /* Pagination Footer */
    .pagination-bar {
        padding: 16px 24px;
        background: #FFFFFF;
        border-top: 1px solid var(--color-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .pagination-info {
        font-size: 13px;
        color: #6B7280;
    }
    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .page-btn {
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        border-radius: 6px;
        border: 1px solid var(--color-border);
        background: #FFFFFF;
        color: #374151;
        font-size: 13px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s;
    }
    .page-btn:hover {
        background: #F9FAFB;
        border-color: #D1D5DB;
    }
    .page-btn.active {
        background: var(--color-forest);
        color: #FFFFFF;
        border-color: var(--color-forest);
        font-weight: 600;
    }

    /* Slide-out Drawer */
    .drawer-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.45);
        backdrop-filter: blur(2px);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .drawer-overlay.open {
        opacity: 1;
        visibility: visible;
    }

    .drawer-panel {
        position: fixed;
        top: 0;
        right: 0;
        width: 100%;
        max-width: 540px;
        height: 100%;
        background: #FFFFFF;
        box-shadow: -8px 0 24px rgba(0, 0, 0, 0.12);
        z-index: 1001;
        transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
    }
    .drawer-overlay.open .drawer-panel {
        transform: translateX(0);
    }

    .drawer-header {
        padding: 20px 28px;
        border-bottom: 1px solid var(--color-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .drawer-title {
        font-size: 18px;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }
    .drawer-close-btn {
        background: none;
        border: none;
        font-size: 20px;
        color: #9CA3AF;
        cursor: pointer;
        padding: 4px;
        line-height: 1;
        border-radius: 6px;
    }
    .drawer-close-btn:hover {
        color: #111827;
        background-color: #F3F4F6;
    }

    .drawer-body {
        padding: 24px 28px;
        overflow-y: auto;
        flex: 1;
    }

    .drawer-footer {
        padding: 18px 28px;
        border-top: 1px solid var(--color-border);
        background: #FAFAFA;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
    }

    .form-group-custom {
        margin-bottom: 18px;
    }
    .form-label-custom {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
    }
    .form-input-custom, .form-select-custom, .form-textarea-custom {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--color-border);
        border-radius: 8px;
        font-size: 13.5px;
        color: #111827;
        background-color: #FFFFFF;
        box-sizing: border-box;
        transition: all 0.2s;
    }
    .form-input-custom:focus, .form-select-custom:focus, .form-textarea-custom:focus {
        outline: none;
        border-color: var(--color-forest);
        box-shadow: 0 0 0 3px rgba(20, 77, 41, 0.1);
    }
    .form-char-count {
        text-align: right;
        font-size: 11.5px;
        color: #9CA3AF;
        margin-top: 4px;
    }

    /* Star Clicker */
    .interactive-stars {
        display: inline-flex;
        gap: 6px;
        font-size: 22px;
        cursor: pointer;
        color: #D1D5DB;
    }
    .interactive-stars .star-item {
        transition: color 0.15s, transform 0.15s;
    }
    .interactive-stars .star-item.active,
    .interactive-stars .star-item.hover {
        color: var(--color-gold);
        transform: scale(1.1);
    }

    .btn-neutral-outline {
        background: #FFFFFF;
        border: 1px solid var(--color-border);
        color: #374151;
        font-size: 13.5px;
        font-weight: 600;
        padding: 9px 18px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
    }
    .btn-neutral-outline:hover {
        background-color: #F9FAFB;
        border-color: #D1D5DB;
    }

    /* Bulk actions bar */
    .bulk-action-bar {
        background-color: #111827;
        color: #FFFFFF;
        padding: 12px 20px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }
    .bulk-action-bar.show {
        display: flex;
    }
    .bulk-count-label {
        font-size: 13.5px;
        font-weight: 600;
    }
    .bulk-btn-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .btn-bulk-item {
        background-color: rgba(255,255,255,0.12);
        color: #FFFFFF;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s;
    }
    .btn-bulk-item:hover {
        background-color: rgba(255,255,255,0.22);
    }
    .btn-bulk-item.danger {
        background-color: rgba(239, 68, 68, 0.2);
        border-color: rgba(239, 68, 68, 0.4);
        color: #FCA5A5;
    }
    .btn-bulk-item.danger:hover {
        background-color: rgba(239, 68, 68, 0.4);
    }

    @media (max-width: 1024px) {
        .kpi-row-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .reviews-stage {
            padding: 16px;
        }
    }
    @media (max-width: 640px) {
        .kpi-row-grid {
            grid-template-columns: 1fr;
        }
        .reviews-table th:nth-child(4),
        .reviews-table td:nth-child(4) {
            display: none;
        }
    }
    </style>
</head>
<body class="admin-body">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="admin-layout-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main-stage">
            <div class="reviews-stage">
                
                <!-- Main Page Header -->
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                    <div>
                        <h1 class="page-title-serif">Reviews & Customer Trust</h1>
                        <p class="page-subtitle-muted">Manage product reviews, build trust, and showcase real customer experiences.</p>
                    </div>
                    <div>
                        <button type="button" class="btn-forest-primary" onclick="openAddReviewDrawer()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <span>Add Review</span>
                        </button>
                    </div>
                </div>

                <!-- Notices / Error Banners -->
                <?php if (!empty($notice)): ?>
                    <div style="margin-top: 18px; padding: 12px 18px; background: #DEF7EC; border: 1px solid #BCF0DA; border-radius: 10px; color: #03543F; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span><?= htmlspecialchars($notice) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div style="margin-top: 18px; padding: 12px 18px; background: #FDE8E8; border: 1px solid #F8B4B4; border-radius: 10px; color: #9B1C1C; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Top 4 KPI Summary Cards (Matching media_1794577389303.png) -->
                <div class="kpi-row-grid">
                    <!-- KPI 1: Average Rating -->
                    <div class="kpi-stat-card">
                        <div class="kpi-icon-circle kpi-icon-green">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                        </div>
                        <div class="kpi-stat-content">
                            <span class="kpi-stat-label">Average Rating</span>
                            <div class="kpi-stat-value">
                                <?= number_format($analytics['average_rating'], 1) ?>
                                <span class="kpi-stat-suffix">/ 5</span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 2: Total Reviews -->
                    <div class="kpi-stat-card">
                        <div class="kpi-icon-circle kpi-icon-green">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <div class="kpi-stat-content">
                            <span class="kpi-stat-label">Total Reviews</span>
                            <div class="kpi-stat-value">
                                <?= number_format($analytics['total_reviews']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 3: Pending Reviews -->
                    <div class="kpi-stat-card">
                        <div class="kpi-icon-circle kpi-icon-amber">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <div class="kpi-stat-content">
                            <span class="kpi-stat-label">Pending Reviews</span>
                            <div class="kpi-stat-value" style="color: <?= $analytics['pending_reviews'] > 0 ? '#B45309' : '#111827' ?>;">
                                <?= number_format($analytics['pending_reviews']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 4: Verified Reviews -->
                    <div class="kpi-stat-card">
                        <div class="kpi-icon-circle kpi-icon-green">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                <polyline points="9 12 11 14 15 10"></polyline>
                            </svg>
                        </div>
                        <div class="kpi-stat-content">
                            <span class="kpi-stat-label">Verified Reviews</span>
                            <div class="kpi-stat-value">
                                <?= number_format($analytics['verified_reviews']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Filter Tabs (Matching media_1794577389303.png) -->
                <div class="filter-tabs-row">
                    <a href="reviews.php?status=ALL<?= $ratingFilter ? "&rating={$ratingFilter}" : '' ?><?= $productFilter !== 'ALL' ? "&product_id={$productFilter}" : '' ?>" 
                       class="status-pill-tab <?= $statusFilter === 'ALL' ? 'active' : '' ?>">
                        <span>All Reviews (<?= number_format($analytics['total_reviews']) ?>)</span>
                    </a>

                    <a href="reviews.php?status=PENDING<?= $ratingFilter ? "&rating={$ratingFilter}" : '' ?><?= $productFilter !== 'ALL' ? "&product_id={$productFilter}" : '' ?>" 
                       class="status-pill-tab <?= $statusFilter === 'PENDING' ? 'active' : '' ?>">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <span>Pending (<?= number_format($analytics['pending_reviews']) ?>)</span>
                    </a>

                    <a href="reviews.php?status=APPROVED<?= $ratingFilter ? "&rating={$ratingFilter}" : '' ?><?= $productFilter !== 'ALL' ? "&product_id={$productFilter}" : '' ?>" 
                       class="status-pill-tab <?= $statusFilter === 'APPROVED' ? 'active' : '' ?>">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>Approved (<?= number_format($analytics['approved_reviews']) ?>)</span>
                    </a>

                    <a href="reviews.php?status=REJECTED<?= $ratingFilter ? "&rating={$ratingFilter}" : '' ?><?= $productFilter !== 'ALL' ? "&product_id={$productFilter}" : '' ?>" 
                       class="status-pill-tab <?= $statusFilter === 'REJECTED' ? 'active' : '' ?>">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        <span>Rejected (<?= number_format($analytics['rejected_reviews']) ?>)</span>
                    </a>

                    <?php if ($analytics['hidden_reviews'] > 0): ?>
                    <a href="reviews.php?status=HIDDEN<?= $ratingFilter ? "&rating={$ratingFilter}" : '' ?><?= $productFilter !== 'ALL' ? "&product_id={$productFilter}" : '' ?>" 
                       class="status-pill-tab <?= $statusFilter === 'HIDDEN' ? 'active' : '' ?>">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        <span>Hidden (<?= number_format($analytics['hidden_reviews']) ?>)</span>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Search & Filters Toolbar (Matching media_1794577389303.png) -->
                <form method="GET" action="reviews.php" id="filterForm">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                    <div class="reviews-toolbar-card">
                        <div class="search-input-group">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" 
                                   placeholder="Search by product, reviewer or review..." 
                                   class="search-input-control" 
                                   onkeydown="if(event.key==='Enter') this.form.submit();">
                        </div>

                        <div class="toolbar-actions">
                            <!-- Rating Filter Dropdown -->
                            <select name="rating" class="dropdown-select" onchange="this.form.submit()">
                                <option value="">★ All Ratings</option>
                                <option value="5" <?= $ratingFilter === 5 ? 'selected' : '' ?>>5 Stars (★★★★★)</option>
                                <option value="4" <?= $ratingFilter === 4 ? 'selected' : '' ?>>4 Stars (★★★★☆)</option>
                                <option value="3" <?= $ratingFilter === 3 ? 'selected' : '' ?>>3 Stars (★★★☆☆)</option>
                                <option value="2" <?= $ratingFilter === 2 ? 'selected' : '' ?>>2 Stars (★★☆☆☆)</option>
                                <option value="1" <?= $ratingFilter === 1 ? 'selected' : '' ?>>1 Star (★☆☆☆☆)</option>
                            </select>

                            <!-- Product Filter Dropdown -->
                            <select name="product_id" class="dropdown-select" onchange="this.form.submit()">
                                <option value="ALL">All Products</option>
                                <?php foreach ($filterProducts as $fp): ?>
                                    <option value="<?= htmlspecialchars($fp['id']) ?>" <?= $productFilter === $fp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($fp['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Sorting Dropdown -->
                            <select name="sort_by" class="dropdown-select" onchange="this.form.submit()">
                                <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                <option value="rating_high" <?= $sortBy === 'rating_high' ? 'selected' : '' ?>>Highest Rating</option>
                                <option value="rating_low" <?= $sortBy === 'rating_low' ? 'selected' : '' ?>>Lowest Rating</option>
                            </select>

                            <?php if (!empty($searchQuery) || $ratingFilter || $productFilter !== 'ALL' || $statusFilter !== 'ALL'): ?>
                                <a href="reviews.php" class="btn-neutral-outline" style="text-decoration: none; padding: 7px 12px; font-size: 12.5px;">
                                    Reset Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <!-- Bulk Action Bar -->
                <form id="bulkForm" method="POST" action="reviews.php">
                    <input type="hidden" name="action" value="bulk_action">
                    <input type="hidden" name="bulk_type" id="bulkActionType" value="">
                    <div id="bulkBar" class="bulk-action-bar">
                        <div class="bulk-count-label">
                            <span id="selectedCount">0</span> reviews selected
                        </div>
                        <div class="bulk-btn-group">
                            <button type="button" class="btn-bulk-item" onclick="submitBulk('APPROVE')">✓ Approve Selected</button>
                            <button type="button" class="btn-bulk-item" onclick="submitBulk('REJECT')">✕ Reject Selected</button>
                            <button type="button" class="btn-bulk-item" onclick="submitBulk('HIDE')">Hide Selected</button>
                            <button type="button" class="btn-bulk-item danger" onclick="if(confirm('Permanently delete selected reviews?')) submitBulk('DELETE');">Delete Selected</button>
                        </div>
                    </div>

                    <!-- Reviews Table (Matching media_1794577389303.png) -->
                    <div class="table-surface-card">
                        <div style="overflow-x: auto; overflow-y: visible; min-height: 160px; -webkit-overflow-scrolling: touch;">
                            <table class="reviews-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;">
                                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" style="cursor: pointer; width: 16px; height: 16px;">
                                        </th>
                                        <th style="min-width: 200px;">Product</th>
                                        <th style="min-width: 150px;">Reviewer</th>
                                        <th style="min-width: 100px;">Rating</th>
                                        <th style="min-width: 220px;">Review Content</th>
                                        <th style="min-width: 110px;">Status</th>
                                        <th style="width: 80px; min-width: 80px; text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($reviews)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 48px 24px; color: #6B7280;">
                                                <div style="font-size: 16px; font-weight: 600; color: #111827; margin-bottom: 4px;">No reviews found</div>
                                                <p style="font-size: 13.5px; margin: 0;">Try adjusting your search criteria or filter options.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($reviews as $rev): ?>
                                            <tr>
                                                <td style="text-align: center;">
                                                    <input type="checkbox" name="selected_ids[]" value="<?= htmlspecialchars($rev['id']) ?>" 
                                                           class="row-select-checkbox" onchange="updateSelectedCount()" 
                                                           style="cursor: pointer; width: 16px; height: 16px;">
                                                </td>

                                                <!-- Product Info -->
                                                <td>
                                                    <div class="product-cell">
                                                        <img src="../<?= htmlspecialchars($rev['product_image'] ?: 'assets/images/product-abc.jpg') ?>" 
                                                             alt="" class="product-thumb"
                                                             onerror="this.src='../assets/images/powder-bowl.png'">
                                                        <div class="product-info-text">
                                                            <span class="product-name-bold"><?= htmlspecialchars($rev['product_title'] ?? 'Nutronyx Blend') ?></span>
                                                            <span class="product-variant-muted"><?= htmlspecialchars($rev['variant_title'] ?: ($rev['variant_weight'] ? $rev['variant_weight'] . ' g' : 'Standard Pack')) ?></span>
                                                        </div>
                                                    </div>
                                                </td>

                                                <!-- Reviewer Info -->
                                                <td>
                                                    <div class="reviewer-cell">
                                                        <span class="reviewer-name"><?= htmlspecialchars($rev['customer_name'] ?? 'Customer') ?></span>
                                                        <?php if (!empty($rev['is_verified_buyer']) && (int)$rev['is_verified_buyer'] === 1): ?>
                                                            <span class="badge-verified">
                                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                                <span>Verified Purchase</span>
                                                            </span>
                                                        <?php elseif (($rev['review_source'] ?? '') === 'ADMIN_ADDED'): ?>
                                                            <span class="badge-admin-added">
                                                                <span>Admin Added</span>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge-unverified">
                                                                <span>Customer Submitted</span>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>

                                                <!-- Rating -->
                                                <td>
                                                    <div class="star-rating-gold" aria-label="<?= (int)$rev['rating'] ?> stars">
                                                        <?= str_repeat('★', (int)($rev['rating'] ?? 5)) ?><?= str_repeat('☆', max(0, 5 - (int)($rev['rating'] ?? 5))) ?>
                                                    </div>
                                                </td>

                                                <!-- Review Content -->
                                                <td>
                                                    <div class="review-content-cell">
                                                        <?php if (!empty($rev['title'])): ?>
                                                            <div class="review-headline"><?= htmlspecialchars($rev['title']) ?></div>
                                                        <?php endif; ?>
                                                        <div class="review-body-snippet">
                                                            <?= htmlspecialchars($rev['comment'] ?? '') ?>
                                                        </div>
                                                        <?php if (!empty($rev['admin_reply'])): ?>
                                                            <div class="review-reply-pill">
                                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 17 4 12 9 7"></polyline><path d="M20 18v-2a4 4 0 0 0-4-4H4"></path></svg>
                                                                <span>Staff Replied</span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>

                                                <!-- Status Badge -->
                                                <td>
                                                    <span class="status-badge status-<?= strtolower($rev['status']) ?>">
                                                        ● <?= ucfirst(strtolower($rev['status'])) ?>
                                                    </span>
                                                </td>

                                                <!-- Row Actions (Eye & More) -->
                                                <td>
                                                    <div class="action-icons-group">
                                                        <button type="button" class="btn-icon-action" title="View & Moderate Details" 
                                                                onclick="openDetailsDrawer(<?= htmlspecialchars(json_encode($rev), ENT_QUOTES, 'UTF-8') ?>)">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                                <circle cx="12" cy="12" r="3"></circle>
                                                            </svg>
                                                        </button>

                                                        <div class="dropdown-menu-container">
                                                            <button type="button" class="btn-icon-action" title="More Actions" onclick="toggleRowMenu(event, '<?= $rev['id'] ?>')">
                                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                                    <circle cx="12" cy="12" r="1"></circle>
                                                                    <circle cx="19" cy="12" r="1"></circle>
                                                                    <circle cx="5" cy="12" r="1"></circle>
                                                                </svg>
                                                            </button>
                                                            <div id="rowMenu_<?= $rev['id'] ?>" class="dropdown-menu-popover">
                                                                <button type="button" class="dropdown-menu-item" onclick="openDetailsDrawer(<?= htmlspecialchars(json_encode($rev), ENT_QUOTES, 'UTF-8') ?>)">
                                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                                    <span>View Details</span>
                                                                </button>
                                                                
                                                                <?php if ($rev['status'] !== 'APPROVED'): ?>
                                                                    <button type="button" class="dropdown-menu-item" onclick="directModerate('<?= $rev['id'] ?>', 'APPROVED')">
                                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                                        <span>Approve Review</span>
                                                                    </button>
                                                                <?php endif; ?>

                                                                <?php if ($rev['status'] !== 'REJECTED'): ?>
                                                                    <button type="button" class="dropdown-menu-item" onclick="directModerate('<?= $rev['id'] ?>', 'REJECTED')">
                                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                                                        <span>Reject Review</span>
                                                                    </button>
                                                                <?php endif; ?>

                                                                <?php if ($rev['status'] !== 'HIDDEN'): ?>
                                                                    <button type="button" class="dropdown-menu-item" onclick="directModerate('<?= $rev['id'] ?>', 'HIDDEN')">
                                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                                                        <span>Hide Review</span>
                                                                    </button>
                                                                <?php endif; ?>

                                                                <button type="button" class="dropdown-menu-item danger" onclick="confirmDeleteReview('<?= $rev['id'] ?>')">
                                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                                    <span>Delete Review</span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer (Matching media_1794577389303.png) -->
                        <div class="pagination-bar">
                            <div class="pagination-info">
                                Showing <?= $totalReviews > 0 ? ($offset + 1) : 0 ?>&ndash;<?= min($offset + $perPage, $totalReviews) ?> of <?= number_format($totalReviews) ?> reviews
                            </div>

                            <div style="display: flex; align-items: center; gap: 16px;">
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #6B7280;">
                                    <span>Rows per page</span>
                                    <select onchange="changePerPage(this.value)" class="dropdown-select" style="padding: 4px 8px; font-size: 12.5px;">
                                        <option value="5" <?= $perPage === 5 ? 'selected' : '' ?>>5</option>
                                        <option value="10" <?= $perPage === 10 ? 'selected' : '' ?>>10</option>
                                        <option value="25" <?= $perPage === 25 ? 'selected' : '' ?>>25</option>
                                        <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
                                    </select>
                                </div>

                                <div class="pagination-controls">
                                    <?php if ($page > 1): ?>
                                        <a href="<?= getReviewsPageUrl($page - 1) ?>" class="page-btn" title="Previous Page">&lt;</a>
                                    <?php endif; ?>

                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                        <?php if ($p === 1 || $p === $totalPages || abs($p - $page) <= 1): ?>
                                            <a href="<?= getReviewsPageUrl($p) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>">
                                                <?= $p ?>
                                            </a>
                                        <?php elseif ($p === 2 && $page > 3): ?>
                                            <span style="color: #9CA3AF; padding: 0 4px;">...</span>
                                        <?php elseif ($p === $totalPages - 1 && $page < $totalPages - 2): ?>
                                            <span style="color: #9CA3AF; padding: 0 4px;">...</span>
                                        <?php endif; ?>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <a href="<?= getReviewsPageUrl($page + 1) ?>" class="page-btn" title="Next Page">&gt;</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

            </div>
        </main>
    </div>

    <!-- ==============================================================
         SLIDE-OUT DRAWER 1: ADD REVIEW (Exact Match to media_1794577389303.png)
         ============================================================== -->
    <div id="addReviewDrawer" class="drawer-overlay" onclick="handleDrawerOverlayClick(event, 'addReviewDrawer')">
        <div class="drawer-panel">
            <div class="drawer-header">
                <h3 class="drawer-title">Add Review</h3>
                <button type="button" class="drawer-close-btn" onclick="closeDrawer('addReviewDrawer')">&times;</button>
            </div>
            
            <form id="addReviewForm" method="POST" action="reviews.php" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
                <input type="hidden" name="action" value="create_admin_review">
                
                <div class="drawer-body">
                    <!-- Product Selection -->
                    <div class="form-group-custom">
                        <label class="form-label-custom">Product *</label>
                        <select name="product_selection" required class="form-select-custom" id="addProdSelect">
                            <option value="">Select Catalog Product...</option>
                            <?php foreach ($catalogProducts as $cp): ?>
                                <?php $comboVal = $cp['id'] . '|' . ($cp['variant_id'] ?? ''); ?>
                                <option value="<?= htmlspecialchars($comboVal) ?>">
                                    <?= htmlspecialchars($cp['title']) ?> <?= !empty($cp['variant_title']) ? '(' . htmlspecialchars($cp['variant_title']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Reviewer Info -->
                    <div class="form-group-custom">
                        <label class="form-label-custom">Reviewer *</label>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <select name="customer_id" id="addCustSelect" class="form-select-custom" onchange="toggleCustomerType(this.value)">
                                <option value="guest">Custom Reviewer Display Name</option>
                                <optgroup label="Select Existing Customer">
                                    <?php foreach ($customerList as $cust): ?>
                                        <option value="<?= htmlspecialchars($cust['id']) ?>" data-name="<?= htmlspecialchars($cust['name']) ?>">
                                            <?= htmlspecialchars($cust['name']) ?> (<?= htmlspecialchars($cust['phone'] ?: $cust['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>

                            <input type="text" name="customer_name" id="addCustName" placeholder="Enter Reviewer Display Name (e.g. Ravi Kumar)" 
                                   required class="form-input-custom">
                        </div>
                    </div>

                    <!-- Star Rating -->
                    <div class="form-group-custom">
                        <label class="form-label-custom">Rating *</label>
                        <div class="interactive-stars" id="starSelector" onmouseleave="resetStarHover()">
                            <span class="star-item active" data-rating="1" onmouseover="hoverStar(1)" onclick="setRating(1)">★</span>
                            <span class="star-item active" data-rating="2" onmouseover="hoverStar(2)" onclick="setRating(2)">★</span>
                            <span class="star-item active" data-rating="3" onmouseover="hoverStar(3)" onclick="setRating(3)">★</span>
                            <span class="star-item active" data-rating="4" onmouseover="hoverStar(4)" onclick="setRating(4)">★</span>
                            <span class="star-item active" data-rating="5" onmouseover="hoverStar(5)" onclick="setRating(5)">★</span>
                        </div>
                        <input type="hidden" name="rating" id="addRatingInput" value="5">
                    </div>

                    <!-- Review Title -->
                    <div class="form-group-custom">
                        <label class="form-label-custom">Review Title *</label>
                        <input type="text" name="title" required placeholder="Short title (e.g., Great product!)" class="form-input-custom">
                    </div>

                    <!-- Review Content -->
                    <div class="form-group-custom">
                        <label class="form-label-custom">Review Content *</label>
                        <textarea name="comment" required rows="4" maxlength="500" placeholder="Write the review details..." 
                                  class="form-textarea-custom" oninput="updateCharCount(this, 'commentCount', 500)"></textarea>
                        <div class="form-char-count" id="commentCount">0 / 500</div>
                    </div>

                    <!-- Review Date & Status (2 Columns) -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group-custom">
                            <label class="form-label-custom">Review Date *</label>
                            <input type="date" name="created_at" value="<?= date('Y-m-d') ?>" required class="form-input-custom">
                        </div>
                        <div class="form-group-custom">
                            <label class="form-label-custom">Status *</label>
                            <select name="status" class="form-select-custom">
                                <option value="APPROVED">● Approved</option>
                                <option value="PENDING">● Pending</option>
                            </select>
                        </div>
                    </div>

                    <!-- Optional Admin Response -->
                    <div class="form-group-custom">
                        <label class="form-label-custom">Admin Response (Optional)</label>
                        <textarea name="admin_reply" rows="3" maxlength="300" placeholder="Your reply to the review..." 
                                  class="form-textarea-custom" oninput="updateCharCount(this, 'replyCount', 300)"></textarea>
                        <div class="form-char-count" id="replyCount">0 / 300</div>
                    </div>

                    <!-- Internal Notes -->
                    <div class="form-group-custom">
                        <label class="form-label-custom">Internal Moderation Notes (Staff Only)</label>
                        <textarea name="internal_notes" rows="2" placeholder="Private internal verification notes..." class="form-textarea-custom"></textarea>
                    </div>
                </div>

                <div class="drawer-footer">
                    <button type="button" class="btn-neutral-outline" onclick="closeDrawer('addReviewDrawer')">Cancel</button>
                    <button type="submit" class="btn-forest-primary">Save Review</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==============================================================
         SLIDE-OUT DRAWER 2: REVIEW DETAILS & MODERATION
         ============================================================== -->
    <div id="detailsDrawer" class="drawer-overlay" onclick="handleDrawerOverlayClick(event, 'detailsDrawer')">
        <div class="drawer-panel" style="max-width: 620px;">
            <div class="drawer-header">
                <h3 class="drawer-title">Review Details & Moderation</h3>
                <button type="button" class="drawer-close-btn" onclick="closeDrawer('detailsDrawer')">&times;</button>
            </div>

            <div class="drawer-body" id="detailsDrawerBody">
                <!-- Dynamically populated via JS -->
            </div>
        </div>
    </div>

    <!-- Hidden Form for Direct Actions -->
    <form id="actionForm" method="POST" action="reviews.php" style="display: none;">
        <input type="hidden" name="action" id="actionFormAction">
        <input type="hidden" name="review_id" id="actionFormId">
        <input type="hidden" name="status" id="actionFormStatus">
    </form>

    <script src="../assets/js/admin.js"></script>
    <script>
    // Global Drawers
    function openAddReviewDrawer() {
        document.getElementById('addReviewDrawer').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer(id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
    }

    function handleDrawerOverlayClick(e, id) {
        if (e.target.id === id) {
            closeDrawer(id);
        }
    }

    // Customer Selection Handling
    function toggleCustomerType(val) {
        const nameInput = document.getElementById('addCustName');
        const select = document.getElementById('addCustSelect');
        const selectedOpt = select.options[select.selectedIndex];
        
        if (val !== 'guest' && selectedOpt.dataset.name) {
            nameInput.value = selectedOpt.dataset.name;
        }
    }

    // Interactive Star Rating for Add Review
    let currentRating = 5;
    function hoverStar(rating) {
        const stars = document.querySelectorAll('#starSelector .star-item');
        stars.forEach((s, idx) => {
            if (idx < rating) {
                s.classList.add('hover');
            } else {
                s.classList.remove('hover');
            }
        });
    }

    function resetStarHover() {
        const stars = document.querySelectorAll('#starSelector .star-item');
        stars.forEach(s => s.classList.remove('hover'));
    }

    function setRating(rating) {
        currentRating = rating;
        document.getElementById('addRatingInput').value = rating;
        const stars = document.querySelectorAll('#starSelector .star-item');
        stars.forEach((s, idx) => {
            if (idx < rating) {
                s.classList.add('active');
            } else {
                s.classList.remove('active');
            }
        });
    }

    // Character Counter
    function updateCharCount(el, counterId, max) {
        const count = el.value.length;
        document.getElementById(counterId).innerText = count + ' / ' + max;
    }

    // Checkbox and Bulk Actions
    function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll('.row-select-checkbox');
        checkboxes.forEach(cb => cb.checked = master.checked);
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checked = document.querySelectorAll('.row-select-checkbox:checked');
        const count = checked.length;
        document.getElementById('selectedCount').innerText = count;
        const bar = document.getElementById('bulkBar');
        if (count > 0) {
            bar.classList.add('show');
        } else {
            bar.classList.remove('show');
        }
    }

    function submitBulk(actionType) {
        document.getElementById('bulkActionType').value = actionType;
        document.getElementById('bulkForm').submit();
    }

    // Row Menu Popover
    let activeMenu = null;
    function toggleRowMenu(e, id) {
        e.stopPropagation();
        const btn = e.currentTarget;
        const menu = document.getElementById('rowMenu_' + id);
        if (activeMenu && activeMenu !== menu) {
            activeMenu.classList.remove('show');
        }
        
        const isShowing = menu.classList.contains('show');
        if (isShowing) {
            menu.classList.remove('show');
            activeMenu = null;
        } else {
            const rect = btn.getBoundingClientRect();
            menu.style.top = (rect.bottom + 4) + 'px';
            menu.style.left = Math.max(10, rect.right - 175) + 'px';
            menu.classList.add('show');
            activeMenu = menu;
        }
    }

    document.addEventListener('click', function(e) {
        if (activeMenu && !activeMenu.contains(e.target)) {
            activeMenu.classList.remove('show');
            activeMenu = null;
        }
    });

    window.addEventListener('scroll', function() {
        if (activeMenu) {
            activeMenu.classList.remove('show');
            activeMenu = null;
        }
    }, true);

    // Direct Moderation Actions
    function directModerate(id, status) {
        document.getElementById('actionFormAction').value = 'moderate';
        document.getElementById('actionFormId').value = id;
        document.getElementById('actionFormStatus').value = status;
        document.getElementById('actionForm').submit();
    }

    function confirmDeleteReview(id) {
        if (confirm('Are you sure you want to permanently delete this customer review?')) {
            document.getElementById('actionFormAction').value = 'delete_review';
            document.getElementById('actionFormId').value = id;
            document.getElementById('actionForm').submit();
        }
    }

    function changePerPage(val) {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', val);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    // Open Review Details Drawer
    function openDetailsDrawer(rev) {
        const container = document.getElementById('detailsDrawerBody');
        const starsHtml = '★'.repeat(parseInt(rev.rating || 5)) + '☆'.repeat(Math.max(0, 5 - parseInt(rev.rating || 5)));
        
        let verifiedBadge = '';
        if (parseInt(rev.is_verified_buyer) === 1) {
            verifiedBadge = '<span class="badge-verified" style="font-size: 12px; padding: 4px 10px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg> Verified Purchase</span>';
        } else if (rev.review_source === 'ADMIN_ADDED') {
            verifiedBadge = '<span class="badge-admin-added" style="font-size: 12px; padding: 4px 10px;">Admin Added Testimonial</span>';
        } else {
            verifiedBadge = '<span class="badge-unverified" style="font-size: 12px; padding: 4px 10px;">Customer Submitted (Unverified)</span>';
        }

        let orderInfo = '';
        if (rev.order_number) {
            orderInfo = `
                <div style="margin-top: 12px; padding: 12px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 12.5px;">
                    <div style="font-weight: 600; color: #1E293B; margin-bottom: 2px;">Associated Verified Order:</div>
                    <div style="color: #475569;">
                        Order <strong>${escapeHtml(rev.order_number)}</strong> &bull; Status: <span style="text-transform: capitalize;">${escapeHtml(rev.order_status || 'Delivered')}</span>
                    </div>
                </div>
            `;
        }

        container.innerHTML = `
            <div style="display: flex; gap: 16px; align-items: center; padding-bottom: 18px; border-bottom: 1px solid var(--color-border);">
                <img src="../${escapeHtml(rev.product_image || 'assets/images/product-abc.jpg')}" 
                     style="width: 54px; height: 54px; border-radius: 10px; object-fit: cover; border: 1px solid var(--color-border);"
                     onerror="this.src='../assets/images/powder-bowl.png'">
                <div>
                    <h4 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 700; color: #111827;">${escapeHtml(rev.product_title || 'Product')}</h4>
                    <div style="font-size: 13px; color: #6B7280;">Variant: ${escapeHtml(rev.variant_title || 'Standard Pack')}</div>
                </div>
            </div>

            <!-- Reviewer Meta -->
            <div style="margin-top: 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                <div>
                    <div style="font-size: 15px; font-weight: 700; color: #111827;">${escapeHtml(rev.customer_name || 'Customer')}</div>
                    <div style="font-size: 12px; color: #6B7280;">Submitted on: ${escapeHtml(rev.created_at || '')}</div>
                </div>
                <div>
                    ${verifiedBadge}
                </div>
            </div>

            ${orderInfo}

            <!-- Review Content Card -->
            <div style="margin-top: 18px; padding: 16px; background: #FAF9F6; border: 1px solid var(--color-border); border-radius: 12px;">
                <div style="color: var(--color-gold); font-size: 17px; margin-bottom: 6px;">${starsHtml}</div>
                ${rev.title ? `<div style="font-weight: 700; font-size: 14.5px; color: #111827; margin-bottom: 6px;">${escapeHtml(rev.title)}</div>` : ''}
                <div style="font-size: 13.5px; color: #374151; line-height: 1.5; white-space: pre-wrap;">${escapeHtml(rev.comment || '')}</div>
            </div>

            <!-- Moderation Decision Form -->
            <form method="POST" action="reviews.php" style="margin-top: 24px;">
                <input type="hidden" name="action" value="moderate">
                <input type="hidden" name="review_id" value="${escapeHtml(rev.id)}">

                <div class="form-group-custom">
                    <label class="form-label-custom">Moderation Decision *</label>
                    <select name="status" class="form-select-custom">
                        <option value="APPROVED" ${rev.status === 'APPROVED' ? 'selected' : ''}>● APPROVED (Public on Storefront)</option>
                        <option value="PENDING" ${rev.status === 'PENDING' ? 'selected' : ''}>● PENDING (Under Review Queue)</option>
                        <option value="REJECTED" ${rev.status === 'REJECTED' ? 'selected' : ''}>● REJECTED (Hide from Storefront)</option>
                        <option value="HIDDEN" ${rev.status === 'HIDDEN' ? 'selected' : ''}>● HIDDEN (Archived / Staff Only)</option>
                    </select>
                </div>

                <!-- Staff Response -->
                <div class="form-group-custom">
                    <label class="form-label-custom">Official Public Staff Response</label>
                    <textarea name="admin_reply" rows="3" class="form-textarea-custom" placeholder="Thank you for sharing your experience with our botanical blend...">${escapeHtml(rev.admin_reply || '')}</textarea>
                    ${rev.admin_reply_author ? `<div style="font-size: 11.5px; color: #6B7280; margin-top: 4px;">Last authored by <strong>${escapeHtml(rev.admin_reply_author)}</strong> &bull; ${escapeHtml(rev.admin_reply_at || '')}</div>` : ''}
                </div>

                <!-- Internal Notes -->
                <div class="form-group-custom">
                    <label class="form-label-custom">Internal Moderation Notes (Private)</label>
                    <textarea name="internal_notes" rows="2" class="form-textarea-custom" placeholder="Private internal review audit notes...">${escapeHtml(rev.internal_notes || '')}</textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-forest-primary" style="flex: 1; justify-content: center;">Save Decision</button>
                    <button type="button" class="btn-neutral-outline" onclick="closeDrawer('detailsDrawer')">Close</button>
                </div>
            </form>
        `;

        document.getElementById('detailsDrawer').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    </script>
</body>
</html>