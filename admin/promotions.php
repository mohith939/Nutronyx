<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Context;
use Nutronyx\Database;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\PromotionService;
use Nutronyx\Services\AnnouncementService;

AuthService::requireAuth();

$activePage = 'promotions';
$activeWebsite = Context::getWebsite();
$currentTab = $_GET['tab'] ?? 'coupons';
$notice = '';
$error = '';

PromotionService::ensureTables();
AnnouncementService::ensureTable();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create Coupon
    if ($action === 'create_coupon') {
        try {
            $id = PromotionService::createPromotion([
                'code' => trim($_POST['code']),
                'name' => trim($_POST['name'] ?? ''),
                'campaign_name' => trim($_POST['campaign_name'] ?? 'General Promotion'),
                'description' => trim($_POST['description'] ?? ''),
                'discount_type' => $_POST['discount_type'] ?? 'PERCENTAGE',
                'discount_value' => (int)($_POST['discount_value'] ?? 0),
                'minimum_order_rupees' => (int)($_POST['minimum_order_rupees'] ?? 0),
                'usage_limit' => (int)($_POST['usage_limit'] ?? 500),
                'per_customer_limit' => (int)($_POST['per_customer_limit'] ?? 1),
                'status' => $_POST['status'] ?? 'ACTIVE',
                'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] . ' 23:59:59' : null
            ]);
            $notice = "Coupon '" . strtoupper(trim($_POST['code'])) . "' created successfully.";
            $currentTab = 'coupons';
        } catch (\Throwable $e) {
            $error = 'Error creating coupon: ' . $e->getMessage();
        }
    }

    // 2. Edit Coupon
    if ($action === 'update_coupon') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            PromotionService::updatePromotion($id, [
                'code' => trim($_POST['code']),
                'name' => trim($_POST['name'] ?? ''),
                'campaign_name' => trim($_POST['campaign_name'] ?? 'General Promotion'),
                'discount_type' => $_POST['discount_type'] ?? 'PERCENTAGE',
                'discount_value' => (int)($_POST['discount_value'] ?? 0),
                'minimum_order_rupees' => (int)($_POST['minimum_order_rupees'] ?? 0),
                'usage_limit' => (int)($_POST['usage_limit'] ?? 500),
                'per_customer_limit' => (int)($_POST['per_customer_limit'] ?? 1),
                'status' => $_POST['status'] ?? 'ACTIVE',
                'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] : null
            ]);
            $notice = "Coupon updated successfully.";
            $currentTab = 'coupons';
        }
    }

    // 3. Duplicate Coupon
    if ($action === 'duplicate_coupon') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $newId = PromotionService::duplicatePromotion($id);
            if ($newId) {
                $notice = "Coupon duplicated successfully as a new draft/active code.";
            }
            $currentTab = 'coupons';
        }
    }

    // 4. Delete Coupon
    if ($action === 'delete_coupon') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            PromotionService::deletePromotion($id);
            $notice = "Coupon deleted successfully.";
            $currentTab = 'coupons';
        }
    }

    // 5. Toggle Active
    if ($action === 'toggle_active') {
        $id = $_POST['id'] ?? '';
        $active = (int)($_POST['active'] ?? 0);
        if ($id) {
            PromotionService::toggleActive($id, $active);
            $notice = "Coupon status updated to " . ($active ? 'Active' : 'Inactive') . ".";
            $currentTab = 'coupons';
        }
    }

    // 6. Create Campaign
    if ($action === 'create_campaign') {
        $db = Database::getConnection();
        $cid = 'camp_' . bin2hex(random_bytes(6));
        $stmt = $db->prepare("
            INSERT INTO promotional_campaigns (id, website_id, name, description, start_date, end_date, budget_amount, status)
            VALUES (:id, :wid, :name, :desc, :start, :end, :bgt, 'ACTIVE')
        ");
        $stmt->execute([
            ':id' => $cid,
            ':wid' => Context::getWebsiteId(),
            ':name' => trim($_POST['campaign_name']),
            ':desc' => trim($_POST['campaign_desc'] ?? ''),
            ':start' => $_POST['start_date'] ?? date('Y-m-d'),
            ':end' => $_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days')),
            ':bgt' => (int)($_POST['budget_rupees'] ?? 50000) * 100
        ]);
        $notice = "Promotional campaign '" . htmlspecialchars($_POST['campaign_name']) . "' launched successfully.";
        $currentTab = 'campaigns';
    }

    // 7. Create Automatic Discount
    if ($action === 'create_auto_discount') {
        $db = Database::getConnection();
        $aid = 'auto_' . bin2hex(random_bytes(6));
        $stmt = $db->prepare("
            INSERT INTO automatic_discounts (id, website_id, name, rule_type, discount_type, discount_value, min_cart_amount, status)
            VALUES (:id, :wid, :name, :rule, :type, :val, :min, 'ACTIVE')
        ");
        $stmt->execute([
            ':id' => $aid,
            ':wid' => Context::getWebsiteId(),
            ':name' => trim($_POST['rule_name']),
            ':rule' => $_POST['rule_type'] ?? 'CART_THRESHOLD',
            ':type' => $_POST['discount_type'] ?? 'PERCENTAGE',
            ':val' => (int)($_POST['discount_value'] ?? 0),
            ':min' => (int)($_POST['min_cart_rupees'] ?? 0) * 100
        ]);
        $notice = "Automatic discount rule created and active at checkout.";
        $currentTab = 'auto';
    }

    // 8. Save Top Announcement Ticker Bar
    if ($action === 'save_announcement') {
        try {
            $websiteId = Context::getWebsiteId() ?: 'store_nutronyx_in';
            AnnouncementService::updateAnnouncement($websiteId, [
                'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
                'sentence_1' => trim($_POST['sentence_1'] ?? ''),
                'icon_1' => trim($_POST['icon_1'] ?? 'truck'),
                'sentence_2' => trim($_POST['sentence_2'] ?? ''),
                'icon_2' => trim($_POST['icon_2'] ?? 'shield'),
                'sentence_3' => trim($_POST['sentence_3'] ?? ''),
                'icon_3' => trim($_POST['icon_3'] ?? 'leaf'),
                'bg_color' => trim($_POST['bg_color'] ?? '#0D2F1F'),
                'text_color' => trim($_POST['text_color'] ?? '#FAF6ED'),
                'speed_seconds' => max(10, min(120, (int)($_POST['speed_seconds'] ?? 28)))
            ]);
            $notice = "Announcement ticker settings saved successfully and updated live!";
            $currentTab = 'announcement';
        } catch (\Throwable $e) {
            $error = 'Error saving announcement settings: ' . $e->getMessage();
            $currentTab = 'announcement';
        }
    }
}

// Direct URL Toggle for backward compatibility
if (isset($_GET['toggle_promo']) && isset($_GET['id'])) {
    PromotionService::toggleActive($_GET['id'], (int)$_GET['toggle_promo']);
    header('Location: promotions.php?tab=coupons');
    exit;
}

// Fetch Real Data
$kpis = PromotionService::getKpis();
$promos = PromotionService::getPromotions();
$campaigns = PromotionService::getCampaigns();
$autoDiscounts = PromotionService::getAutomaticDiscounts();
$redemptions = PromotionService::getRedemptions(50);
$announcement = AnnouncementService::getAnnouncement(Context::getWebsiteId() ?: 'store_nutronyx_in');

// Default selected coupon for right-side overview card
$selectedPromo = !empty($promos) ? $promos[0] : null;
if (!empty($_GET['selected_id'])) {
    foreach ($promos as $p) {
        if ($p['id'] === $_GET['selected_id']) {
            $selectedPromo = $p;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discounts &amp; Coupons &mdash; Nutronyx Admin Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --brand-forest: #144D29;
            --brand-forest-dark: #0F3A1F;
            --brand-forest-light: #ECFDF5;
            --border-light: #E5E7EB;
            --border-card: #EAE6DF;
            --text-dark: #111827;
            --text-muted: #6B7280;
            --bg-page: #FAF9F6;
        }

        body.admin-body {
            background-color: var(--bg-page);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            margin: 0;
            padding: 0;
        }

        .admin-main-stage {
            background: #FAFAFA;
            min-height: calc(100vh - 68px);
            padding: clamp(20px, 2.5vw, 36px) clamp(24px, 3.5vw, 48px);
        }

        /* Page Header */
        .promo-header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }

        .promo-header-title {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: clamp(28px, 2.8vw, 36px);
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.02em;
            margin: 0 0 4px 0;
            line-height: 1.15;
        }

        .promo-header-subtitle {
            font-size: 13.5px;
            color: #6B7280;
            margin: 0;
        }

        .header-actions-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .store-selector-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            cursor: pointer;
        }

        .btn-header-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #FFFFFF;
            color: #374151;
            border: 1px solid #D1D5DB;
            border-radius: 10px;
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-header-secondary:hover {
            background: #F9FAFB;
            border-color: #9CA3AF;
        }

        .btn-header-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--brand-forest);
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(20, 77, 41, 0.2);
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-header-primary:hover {
            background: var(--brand-forest-dark);
            transform: translateY(-1px);
        }

        /* 5 KPI Summary Cards */
        .kpi-cards-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        @media (max-width: 1200px) {
            .kpi-cards-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (max-width: 768px) {
            .kpi-cards-grid {
                grid-template-columns: 1fr;
            }
        }

        .kpi-metric-card {
            background: #FFFFFF;
            border: 1px solid #EAE6DF;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            display: flex;
            align-items: flex-start;
            gap: 14px;
            position: relative;
        }

        .kpi-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 18px;
        }
        .kpi-icon-green { background: #ECFDF5; color: #166534; }
        .kpi-icon-amber { background: #FEF3C7; color: #D97706; }
        .kpi-icon-coral { background: #FEE2E2; color: #DC2626; }
        .kpi-icon-blue  { background: #EFF6FF; color: #2563EB; }

        .kpi-label {
            font-size: 12.5px;
            font-weight: 600;
            color: #6B7280;
            margin-bottom: 4px;
        }
        .kpi-value {
            font-size: 24px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.03em;
            line-height: 1.2;
            margin-bottom: 4px;
        }
        .kpi-trend {
            font-size: 11.5px;
            font-weight: 700;
            color: #166534;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        /* Segmented Navigation */
        .promo-nav-tabs {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 6px;
            margin-bottom: 24px;
            overflow-x: auto;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        .promo-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #4B5563;
            text-decoration: none;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .promo-tab-btn:hover {
            color: #111827;
            background: #F9FAFB;
        }
        .promo-tab-btn.active {
            background: var(--brand-forest);
            color: #FFFFFF;
            box-shadow: 0 2px 6px rgba(20, 77, 41, 0.25);
        }

        /* 2-Column Main Workspace */
        .promo-workspace-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 1150px) {
            .promo-workspace-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Left Table Card */
        .coupons-table-card {
            background: #FFFFFF;
            border: 1px solid #EAE6DF;
            border-radius: 14px;
            padding: 22px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .table-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .table-card-title {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            margin: 0 0 2px 0;
            letter-spacing: -0.01em;
        }
        .table-card-subtitle {
            font-size: 12.5px;
            color: #6B7280;
            margin: 0;
        }

        /* Filter Toolbar */
        .coupons-filter-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
            padding-bottom: 16px;
            border-bottom: 1px solid #F3F4F6;
        }

        .search-box-input {
            position: relative;
            flex: 1;
            min-width: 200px;
        }
        .search-box-input input {
            width: 100%;
            background: #FAF9F6;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 8px 12px 8px 34px;
            font-size: 13px;
            color: #111827;
            outline: none;
            transition: border-color 0.2s;
        }
        .search-box-input input:focus {
            border-color: var(--brand-forest);
            background: #FFFFFF;
        }
        .search-box-input svg {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            stroke: #9CA3AF;
        }

        .filter-select {
            background: #FAF9F6;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12.5px;
            font-weight: 600;
            color: #374151;
            outline: none;
            cursor: pointer;
        }

        /* Clean Table */
        .coupons-data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }
        .coupons-data-table th {
            padding: 10px 12px;
            font-size: 11.5px;
            font-weight: 700;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid #E5E7EB;
            background: transparent;
        }
        .coupons-data-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: middle;
            color: #1F2937;
        }
        .coupons-data-table tr.selected-row {
            background: #F0FDF4;
        }
        .coupons-data-table tr:hover {
            background: #FAFBF9;
            cursor: pointer;
        }

        /* Code Badge with Copy Icon */
        .code-badge-wrap {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #F3F4F6;
            border: 1px solid #E5E7EB;
            padding: 4px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12.5px;
            font-weight: 700;
            color: #111827;
        }
        .code-badge-copy-btn {
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            color: #6B7280;
            display: inline-flex;
            align-items: center;
        }
        .code-badge-copy-btn:hover {
            color: var(--brand-forest);
        }

        /* Discount Badges */
        .badge-discount-percent {
            background: #DCFCE7;
            color: #166534;
            font-weight: 700;
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-discount-fixed {
            background: #FFEDD5;
            color: #C2410C;
            font-weight: 700;
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 6px;
        }
        .badge-discount-shipping {
            background: #DBEAFE;
            color: #1E40AF;
            font-weight: 700;
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-discount-bogo {
            background: #F3E8FF;
            color: #7E22CE;
            font-weight: 700;
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Status Pills */
        .badge-status-active {
            background: #ECFDF5;
            color: #065F46;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-status-scheduled {
            background: #FEF3C7;
            color: #92400E;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-status-exhausted {
            background: #FEE2E2;
            color: #991B1B;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-status-inactive {
            background: #F3F4F6;
            color: #4B5563;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Progress Bar */
        .progress-bar-container {
            width: 90px;
            height: 6px;
            background: #E5E7EB;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 4px;
        }
        .progress-bar-fill {
            height: 100%;
            background: #10B981;
            border-radius: 10px;
        }

        /* Right Column Overview Card */
        .coupon-overview-card {
            background: #FFFFFF;
            border: 1px solid #EAE6DF;
            border-radius: 14px;
            padding: 22px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            position: sticky;
            top: 20px;
        }

        .overview-title {
            font-size: 16px;
            font-weight: 800;
            color: #111827;
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .overview-main-badge {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid #F3F4F6;
        }

        .overview-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 0;
            font-size: 13px;
        }
        .overview-detail-label {
            color: #6B7280;
        }
        .overview-detail-val {
            font-weight: 600;
            color: #111827;
        }

        .btn-view-details {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            background: var(--brand-forest);
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            margin: 18px 0 16px 0;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-view-details:hover {
            background: var(--brand-forest-dark);
        }

        .quick-actions-box {
            border-top: 1px solid #F3F4F6;
            padding-top: 14px;
        }
        .quick-actions-title {
            font-size: 13px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .quick-actions-btns {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-bottom: 14px;
        }
        .btn-qa {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            background: #FFFFFF;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            padding: 7px 4px;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-qa:hover {
            background: #F9FAFB;
            border-color: #9CA3AF;
        }

        /* Switch Toggle */
        .switch-toggle-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            background: #F9FAFB;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
        }
        .switch-lbl {
            font-size: 12.5px;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .switch-input {
            position: relative;
            display: inline-block;
            width: 38px;
            height: 22px;
        }
        .switch-input input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider-round {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #CBD5E1;
            transition: .3s;
            border-radius: 24px;
        }
        .slider-round:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .slider-round {
            background-color: var(--brand-forest);
        }
        input:checked + .slider-round:before {
            transform: translateX(16px);
        }

        /* Modal Styles */
        .modal-backdrop-custom {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-card-custom {
            background: #FFFFFF;
            width: 100%;
            max-width: 680px;
            max-height: 90vh;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        .modal-header-custom {
            padding: 18px 24px;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #FAFBF9;
        }
        .modal-body-custom {
            padding: 24px;
            overflow-y: auto;
        }
        .modal-footer-custom {
            padding: 16px 24px;
            border-top: 1px solid #E5E7EB;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #FAFBF9;
        }

        .form-section-title {
            font-size: 13.5px;
            font-weight: 700;
            color: #111827;
            margin: 18px 0 10px 0;
            display: flex;
            align-items: center;
            gap: 6px;
            border-bottom: 1px solid #F3F4F6;
            padding-bottom: 6px;
        }
        .form-section-title:first-child {
            margin-top: 0;
        }

        /* Toast notification */
        #promoToast {
            display: none;
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #111827;
            color: #FFFFFF;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            z-index: 100000;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body class="admin-body">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="admin-layout-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main-stage">
            
            <!-- Main Page Header -->
            <div class="promo-header-container">
                <div>
                    <h1 class="promo-header-title">Discounts &amp; Coupons</h1>
                    <p class="promo-header-subtitle">
                        Create and manage coupons, promotional campaigns, and automatic discounts for your store.
                    </p>
                </div>
                <div class="header-actions-group">
                    <div class="store-selector-pill">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        <span><?= htmlspecialchars($activeWebsite['name'] ?? 'Nutronyx Store') ?></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>

                    <button type="button" class="btn-header-secondary" onclick="window.openCreateCampaignModal()">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                        <span>Create Campaign</span>
                    </button>

                    <button type="button" class="btn-header-primary" onclick="window.openCreateCouponModal()">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        <span>Create Coupon</span>
                    </button>
                </div>
            </div>

            <!-- Notice / Alert Banner -->
            <?php if (!empty($notice)): ?>
                <div style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span><?= htmlspecialchars($notice) ?></span>
                </div>
            <?php endif; ?>

            <!-- Row of 5 KPI Summary Cards -->
            <div class="kpi-cards-grid">
                <!-- KPI 1 -->
                <div class="kpi-metric-card">
                    <div class="kpi-icon-box kpi-icon-green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    </div>
                    <div>
                        <div class="kpi-label">Total Coupons</div>
                        <div class="kpi-value"><?= number_format($kpis['total_coupons']) ?></div>
                        <div class="kpi-trend">
                            <span><?= $kpis['total_coupons'] > 0 ? 'Catalog coupon total' : 'No coupons created yet' ?></span>
                        </div>
                    </div>
                </div>

                <!-- KPI 2 -->
                <div class="kpi-metric-card">
                    <div class="kpi-icon-box kpi-icon-amber">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                    </div>
                    <div>
                        <div class="kpi-label">Active Coupons</div>
                        <div class="kpi-value"><?= number_format($kpis['active_coupons']) ?></div>
                        <div class="kpi-trend">
                            <span><?= $kpis['active_coupons'] > 0 ? 'Live at storefront' : '0 live at checkout' ?></span>
                        </div>
                    </div>
                </div>

                <!-- KPI 3 -->
                <div class="kpi-metric-card">
                    <div class="kpi-icon-box kpi-icon-blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                    <div>
                        <div class="kpi-label">Total Redemptions</div>
                        <div class="kpi-value"><?= number_format($kpis['total_redemptions']) ?></div>
                        <div class="kpi-trend">
                            <span><?= $kpis['total_redemptions'] > 0 ? number_format($kpis['total_redemptions']) . ' redemptions' : '0 orders redeemed' ?></span>
                        </div>
                    </div>
                </div>

                <!-- KPI 4 -->
                <div class="kpi-metric-card">
                    <div class="kpi-icon-box kpi-icon-coral">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="7" y1="8" x2="17" y2="8"></line><line x1="7" y1="12" x2="17" y2="12"></line><line x1="7" y1="16" x2="13" y2="16"></line></svg>
                    </div>
                    <div>
                        <div class="kpi-label">Total Discount Given</div>
                        <div class="kpi-value">₹<?= number_format($kpis['total_discount_given_rupees']) ?></div>
                        <div class="kpi-trend">
                            <span><?= $kpis['total_discount_given_rupees'] > 0 ? 'Discount granted' : '₹0 discounts given' ?></span>
                        </div>
                    </div>
                </div>

                <!-- KPI 5 -->
                <div class="kpi-metric-card">
                    <div class="kpi-icon-box kpi-icon-green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                    </div>
                    <div>
                        <div class="kpi-label">Revenue Generated</div>
                        <div class="kpi-value">₹<?= number_format($kpis['total_revenue_rupees']) ?></div>
                        <div class="kpi-trend">
                            <span><?= $kpis['total_revenue_rupees'] > 0 ? 'Gross promo revenue' : '₹0 attributed revenue' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Segmented Navigation (5 Tabs) -->
            <div class="promo-nav-tabs">
                <a href="promotions.php?tab=coupons" class="promo-tab-btn <?= ($currentTab === 'coupons') ? 'active' : '' ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    <span>Coupons</span>
                </a>

                <a href="promotions.php?tab=campaigns" class="promo-tab-btn <?= ($currentTab === 'campaigns') ? 'active' : '' ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                    <span>Campaigns</span>
                </a>

                <a href="promotions.php?tab=auto" class="promo-tab-btn <?= ($currentTab === 'auto') ? 'active' : '' ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    <span>Automatic Discounts</span>
                </a>

                <a href="promotions.php?tab=history" class="promo-tab-btn <?= ($currentTab === 'history') ? 'active' : '' ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Redemption History</span>
                </a>

                <a href="promotions.php?tab=analytics" class="promo-tab-btn <?= ($currentTab === 'analytics') ? 'active' : '' ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    <span>Analytics</span>
                </a>

                <a href="promotions.php?tab=announcement" class="promo-tab-btn <?= ($currentTab === 'announcement') ? 'active' : '' ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9H2v6h4l5 4V5z"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                    <span>Announcement Bar</span>
                </a>
            </div>

            <!-- ================= TAB 1: COUPONS MANAGEMENT (SPLIT VIEW) ================= -->
            <?php if ($currentTab === 'coupons'): ?>
                <div class="promo-workspace-grid">
                    
                    <!-- Left: Coupons Table & Filter Toolbar -->
                    <div class="coupons-table-card">
                        <div class="table-card-header">
                            <div>
                                <h2 class="table-card-title">Coupons (<?= count($promos) ?>)</h2>
                                <p class="table-card-subtitle">All your discount coupons in one place.</p>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn-header-secondary" onclick="window.exportCouponsCSV()">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    <span>Export</span>
                                </button>
                                <div style="position: relative; display: inline-block;">
                                    <button type="button" class="btn-header-secondary" onclick="window.toggleBulkMenu(event)">
                                        <span>Bulk Actions</span>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    </button>
                                    <div id="bulkActionsDropdown" style="display: none; position: absolute; right: 0; top: 38px; background: #FFF; border: 1px solid #E5E7EB; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 160px; z-index: 10;">
                                        <a href="javascript:void(0)" onclick="window.bulkSetStatus('ACTIVE')" style="display: block; padding: 8px 12px; font-size: 12.5px; color: #166534; text-decoration: none;">Enable Selected</a>
                                        <a href="javascript:void(0)" onclick="window.bulkSetStatus('INACTIVE')" style="display: block; padding: 8px 12px; font-size: 12.5px; color: #4B5563; text-decoration: none;">Disable Selected</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Toolbar -->
                        <div class="coupons-filter-toolbar">
                            <div class="search-box-input">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                <input type="text" id="couponSearchInp" placeholder="Search by code, campaign or name..." oninput="window.filterCouponsTable()">
                            </div>

                            <select class="filter-select" id="filterStatus" onchange="window.filterCouponsTable()">
                                <option value="ALL">Status: All</option>
                                <option value="ACTIVE">Active</option>
                                <option value="SCHEDULED">Scheduled</option>
                                <option value="EXHAUSTED">Exhausted</option>
                                <option value="INACTIVE">Inactive</option>
                            </select>

                            <select class="filter-select" id="filterType" onchange="window.filterCouponsTable()">
                                <option value="ALL">Type: All</option>
                                <option value="PERCENTAGE">Percentage</option>
                                <option value="FIXED_AMOUNT">Fixed Amount</option>
                                <option value="FREE_SHIPPING">Free Shipping</option>
                                <option value="BUY_X_GET_Y">Buy X Get Y</option>
                            </select>

                            <select class="filter-select" id="filterCampaign" onchange="window.filterCouponsTable()">
                                <option value="ALL">Campaign: All</option>
                                <option value="New Year Sale">New Year Sale</option>
                                <option value="Welcome Offer">Welcome Offer</option>
                                <option value="Freedom Week">Freedom Week</option>
                                <option value="Festive Sale">Festive Sale</option>
                                <option value="Summer Care">Summer Care</option>
                            </select>

                            <button type="button" class="btn-header-secondary" onclick="window.resetCouponFilters()" title="Reset Filters" style="padding: 8px 10px;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line></svg>
                            </button>
                        </div>

                        <!-- Data Table -->
                        <div style="overflow-x: auto;">
                            <table class="coupons-data-table" id="couponsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 28px;"><input type="checkbox" id="selectAllCheckbox" onchange="window.toggleSelectAll(this)"></th>
                                        <th>Coupon Code</th>
                                        <th>Campaign</th>
                                        <th>Discount</th>
                                        <th>Min. Order</th>
                                        <th>Redemption</th>
                                        <th>Discount Given</th>
                                        <th>Expiry</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($promos as $p): 
                                        $isSelected = ($selectedPromo && $selectedPromo['id'] === $p['id']);
                                        $type = $p['discount_type'];
                                        $val = (int)$p['discount_value'];
                                        $minRupees = (int)($p['minimum_order_amount'] ?? 0) / 100;
                                        $limit = (int)($p['usage_limit'] ?? 0);
                                        $used = (int)($p['usage_count'] ?? 0);
                                        $pctUsed = $limit > 0 ? min(100, round(($used / $limit) * 100)) : 0;
                                        $status = strtoupper($p['status'] ?? ($p['is_active'] ? 'ACTIVE' : 'INACTIVE'));
                                        $discountGiven = (int)($p['discount_given_amount'] ?? 0) / 100;
                                        $expiryStr = !empty($p['expires_at']) ? date('d M, Y', strtotime($p['expires_at'])) : 'No Expiry';
                                    ?>
                                    <tr class="coupon-row <?= $isSelected ? 'selected-row' : '' ?>" 
                                        data-id="<?= htmlspecialchars($p['id']) ?>"
                                        data-code="<?= htmlspecialchars($p['code']) ?>"
                                        data-campaign="<?= htmlspecialchars($p['campaign_name'] ?? '') ?>"
                                        data-type="<?= htmlspecialchars($type) ?>"
                                        data-status="<?= htmlspecialchars($status) ?>"
                                        onclick="window.selectCouponRow('<?= htmlspecialchars($p['id']) ?>')">
                                        
                                        <td onclick="event.stopPropagation();">
                                            <input type="checkbox" class="row-checkbox" value="<?= htmlspecialchars($p['id']) ?>">
                                        </td>

                                        <td>
                                            <div class="code-badge-wrap">
                                                <span><?= htmlspecialchars($p['code']) ?></span>
                                                <button type="button" class="code-badge-copy-btn" onclick="window.copyToClipboard('<?= htmlspecialchars($p['code']) ?>', event)" title="Copy Code">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                                </button>
                                            </div>
                                        </td>

                                        <td>
                                            <div style="font-weight: 600; color: #111827;"><?= htmlspecialchars(explode('&bull;', $p['campaign_name'] ?? 'General')[0]) ?></div>
                                            <div style="font-size: 11px; color: #6B7280;"><?= htmlspecialchars(explode('&bull;', $p['campaign_name'] ?? '')[1] ?? 'Standard') ?></div>
                                        </td>

                                        <td>
                                            <?php if ($type === 'PERCENTAGE'): ?>
                                                <span class="badge-discount-percent"><?= $val ?>% OFF</span>
                                            <?php elseif ($type === 'FIXED_AMOUNT' || $type === 'FIXED'): ?>
                                                <span class="badge-discount-fixed">₹<?= number_format($val / 100) ?> OFF</span>
                                            <?php elseif ($type === 'FREE_SHIPPING'): ?>
                                                <span class="badge-discount-shipping">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                                    <span>Free Shipping</span>
                                                </span>
                                            <?php elseif ($type === 'BUY_X_GET_Y'): ?>
                                                <span class="badge-discount-bogo">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line></svg>
                                                    <span>Buy 2 Get 1</span>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-discount-percent">Promo</span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="font-weight: 600;">₹<?= number_format($minRupees) ?></td>

                                        <td>
                                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                                <span style="font-size: 12px; font-weight: 600;"><?= $used ?> / <?= $limit > 0 ? $limit : '∞' ?></span>
                                                <?php if ($limit > 0): ?>
                                                    <span style="font-size: 11px; color: #6B7280;"><?= $pctUsed ?>%</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar-fill" style="width: <?= $limit > 0 ? $pctUsed : 35 ?>%;"></div>
                                            </div>
                                        </td>

                                        <td style="font-weight: 600; color: #111827;">₹<?= number_format($discountGiven) ?></td>

                                        <td style="font-size: 12px; color: #4B5563;"><?= $expiryStr ?></td>

                                        <td>
                                            <?php if ($status === 'ACTIVE'): ?>
                                                <span class="badge-status-active"><span style="width: 5px; height: 5px; border-radius: 50%; background: #10B981;"></span> Active</span>
                                            <?php elseif ($status === 'SCHEDULED'): ?>
                                                <span class="badge-status-scheduled"><span style="width: 5px; height: 5px; border-radius: 50%; background: #F59E0B;"></span> Scheduled</span>
                                            <?php elseif ($status === 'EXHAUSTED'): ?>
                                                <span class="badge-status-exhausted"><span style="width: 5px; height: 5px; border-radius: 50%; background: #EF4444;"></span> Exhausted</span>
                                            <?php else: ?>
                                                <span class="badge-status-inactive"><span style="width: 5px; height: 5px; border-radius: 50%; background: #9CA3AF;"></span> Inactive</span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="text-align: right;" onclick="event.stopPropagation();">
                                            <div style="position: relative; display: inline-block;">
                                                <button type="button" class="btn-header-secondary" onclick="window.toggleRowMenu('<?= $p['id'] ?>', event)" style="padding: 4px 8px; font-size: 12px;">•••</button>
                                                <div id="rowMenu_<?= $p['id'] ?>" class="row-dropdown-menu" style="display: none; position: absolute; right: 0; bottom: 30px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; box-shadow: 0 4px 14px rgba(0,0,0,0.12); width: 170px; z-index: 100; text-align: left;">
                                                    <a href="javascript:void(0)" onclick="window.openCouponDetailsDrawer('<?= $p['id'] ?>')" style="display: block; padding: 8px 12px; font-size: 12px; color: #111827; text-decoration: none;">View Details</a>
                                                    <a href="javascript:void(0)" onclick="window.openEditCouponModal('<?= $p['id'] ?>')" style="display: block; padding: 8px 12px; font-size: 12px; color: #111827; text-decoration: none;">Edit Coupon</a>
                                                    <a href="javascript:void(0)" onclick="window.duplicateCouponAction('<?= $p['id'] ?>')" style="display: block; padding: 8px 12px; font-size: 12px; color: #111827; text-decoration: none;">Duplicate</a>
                                                    <a href="javascript:void(0)" onclick="window.shareCouponLink('<?= $p['code'] ?>')" style="display: block; padding: 8px 12px; font-size: 12px; color: #111827; text-decoration: none;">Share Link</a>
                                                    <div style="height: 1px; background: #F3F4F6; margin: 3px 0;"></div>
                                                    <a href="javascript:void(0)" onclick="window.toggleCouponStatus('<?= $p['id'] ?>', <?= $p['is_active'] ? 0 : 1 ?>)" style="display: block; padding: 8px 12px; font-size: 12px; color: <?= $p['is_active'] ? '#DC2626' : '#166534' ?>; text-decoration: none;">
                                                        <?= $p['is_active'] ? 'Disable Coupon' : 'Enable Coupon' ?>
                                                    </a>
                                                    <a href="javascript:void(0)" onclick="window.deleteCouponAction('<?= $p['id'] ?>', '<?= $p['code'] ?>')" style="display: block; padding: 8px 12px; font-size: 12px; color: #991B1B; text-decoration: none;">Delete</a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #F3F4F6; font-size: 12.5px; color: #6B7280;">
                            <div>Showing 1&ndash;<?= min(10, count($promos)) ?> of <?= count($promos) ?> coupons</div>
                            <div style="display: flex; align-items: center; gap: 16px;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span>Rows per page</span>
                                    <select class="filter-select" style="padding: 4px 8px; font-size: 12px;">
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </select>
                                </div>
                                <div style="display: flex; gap: 4px;">
                                    <button type="button" class="btn-header-secondary" style="padding: 4px 8px; font-size: 11px;">&lt;</button>
                                    <button type="button" class="btn-header-primary" style="padding: 4px 10px; font-size: 11px; background: #166534;">1</button>
                                    <button type="button" class="btn-header-secondary" style="padding: 4px 10px; font-size: 11px;">2</button>
                                    <button type="button" class="btn-header-secondary" style="padding: 4px 10px; font-size: 11px;">3</button>
                                    <button type="button" class="btn-header-secondary" style="padding: 4px 8px; font-size: 11px;">&gt;</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Coupon Overview Panel -->
                    <div class="coupon-overview-card" id="couponOverviewPanel">
                        <?php if ($selectedPromo): 
                            $selType = $selectedPromo['discount_type'];
                            $selVal = (int)$selectedPromo['discount_value'];
                            $selMin = (int)($selectedPromo['minimum_order_amount'] ?? 0) / 100;
                            $selUsed = (int)($selectedPromo['usage_count'] ?? 0);
                            $selLimit = (int)($selectedPromo['usage_limit'] ?? 0);
                            $selPct = $selLimit > 0 ? min(100, round(($selUsed / $selLimit) * 100)) : 44;
                            $selGiven = (int)($selectedPromo['discount_given_amount'] ?? 0) / 100;
                            $selExp = !empty($selectedPromo['expires_at']) ? date('d M, Y', strtotime($selectedPromo['expires_at'])) : '31 Dec, 2026';
                            $selStatus = strtoupper($selectedPromo['status'] ?? ($selectedPromo['is_active'] ? 'ACTIVE' : 'INACTIVE'));
                        ?>
                            <div class="overview-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                <span>Coupon Overview</span>
                            </div>

                            <div class="overview-main-badge">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 18px; font-weight: 800; font-family: monospace; color: #111827;" id="ovCode"><?= htmlspecialchars($selectedPromo['code']) ?></span>
                                        <button type="button" class="code-badge-copy-btn" onclick="window.copyToClipboard('<?= htmlspecialchars($selectedPromo['code']) ?>', event)" title="Copy Code">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        </button>
                                    </div>
                                    <div style="font-size: 12px; color: #6B7280; margin-top: 2px;" id="ovCampaign"><?= htmlspecialchars(explode('&bull;', $selectedPromo['campaign_name'] ?? 'General')[0]) ?></div>
                                </div>
                                <span class="badge-status-active" id="ovStatusBadge">
                                    <span style="width: 5px; height: 5px; border-radius: 50%; background: #10B981;"></span> <?= htmlspecialchars(ucfirst(strtolower($selStatus))) ?>
                                </span>
                            </div>

                            <!-- Key Specifications -->
                            <div class="overview-detail-row">
                                <span class="overview-detail-label">Discount Type</span>
                                <span class="overview-detail-val" id="ovType">
                                    <?php 
                                    if ($selType === 'PERCENTAGE') echo "Percentage ({$selVal}% OFF)";
                                    elseif ($selType === 'FIXED_AMOUNT' || $selType === 'FIXED') echo "Fixed Amount (₹" . number_format($selVal / 100) . " OFF)";
                                    elseif ($selType === 'FREE_SHIPPING') echo "Free Standard Shipping";
                                    elseif ($selType === 'BUY_X_GET_Y') echo "Buy 2 Get 1 Free";
                                    else echo "Promotional";
                                    ?>
                                </span>
                            </div>

                            <div class="overview-detail-row">
                                <span class="overview-detail-label">Minimum Order</span>
                                <span class="overview-detail-val" id="ovMin">₹<?= number_format($selMin) ?></span>
                            </div>

                            <div style="padding: 6px 0;">
                                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                                    <span class="overview-detail-label">Total Redemptions</span>
                                    <span class="overview-detail-val" id="ovUsageCount"><?= $selUsed ?> / <?= $selLimit > 0 ? $selLimit : '∞' ?></span>
                                </div>
                                <div class="progress-bar-container" style="width: 100%; height: 7px;">
                                    <div class="progress-bar-fill" id="ovProgressFill" style="width: <?= $selPct ?>%;"></div>
                                </div>
                            </div>

                            <div class="overview-detail-row">
                                <span class="overview-detail-label">Discount Given</span>
                                <span class="overview-detail-val" id="ovGiven">₹<?= number_format($selGiven) ?></span>
                            </div>

                            <div class="overview-detail-row">
                                <span class="overview-detail-label">Expiry Date</span>
                                <span class="overview-detail-val" id="ovExpiry"><?= $selExp ?></span>
                            </div>

                            <!-- Primary View Details Button -->
                            <button type="button" class="btn-view-details" onclick="window.openCouponDetailsDrawer('<?= $selectedPromo['id'] ?>')">
                                <span>View Details</span>
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                            </button>

                            <!-- Quick Actions -->
                            <div class="quick-actions-box">
                                <div class="quick-actions-title">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                                    <span>Quick Actions</span>
                                </div>
                                <div class="quick-actions-btns">
                                    <button type="button" class="btn-qa" onclick="window.openEditCouponModal('<?= $selectedPromo['id'] ?>')">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                        <span>Edit</span>
                                    </button>
                                    <button type="button" class="btn-qa" onclick="window.duplicateCouponAction('<?= $selectedPromo['id'] ?>')">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        <span>Duplicate</span>
                                    </button>
                                    <button type="button" class="btn-qa" onclick="window.shareCouponLink('<?= $selectedPromo['code'] ?>')">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                                        <span>Share</span>
                                    </button>
                                </div>

                                <!-- Toggle Switch -->
                                <div class="switch-toggle-row">
                                    <div class="switch-lbl">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                        <span>Enable / Disable Coupon</span>
                                    </div>
                                    <label class="switch-input">
                                        <input type="checkbox" id="ovToggleInput" <?= !empty($selectedPromo['is_active']) ? 'checked' : '' ?> onchange="window.toggleCouponStatus('<?= $selectedPromo['id'] ?>', this.checked ? 1 : 0)">
                                        <span class="slider-round"></span>
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            <!-- ================= TAB 2: CAMPAIGNS WORKSPACE ================= -->
            <?php elseif ($currentTab === 'campaigns'): ?>
                <div class="coupons-table-card">
                    <div class="table-card-header">
                        <div>
                            <h2 class="table-card-title">Promotional Campaigns (<?= count($campaigns) ?>)</h2>
                            <p class="table-card-subtitle">Strategic sales drives, seasonal events, and influencer coupon pools.</p>
                        </div>
                        <button type="button" class="btn-header-primary" onclick="window.openCreateCampaignModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            <span>New Campaign</span>
                        </button>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; margin-top: 14px;">
                        <?php foreach ($campaigns as $camp): 
                            $bgt = (int)($camp['budget_amount'] ?? 0) / 100;
                            $cStatus = strtoupper($camp['status'] ?? 'ACTIVE');
                        ?>
                        <div style="background: #FFFFFF; border: 1.5px solid #EAE6DF; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                    <h3 style="font-size: 16px; font-weight: 800; color: #111827; margin: 0;"><?= htmlspecialchars($camp['name']) ?></h3>
                                    <span class="badge-status-<?= ($cStatus === 'ACTIVE') ? 'active' : 'inactive' ?>"><?= $cStatus ?></span>
                                </div>
                                <p style="font-size: 12.5px; color: #6B7280; line-height: 1.45; margin: 0 0 14px 0;">
                                    <?= htmlspecialchars($camp['description']) ?>
                                </p>
                                <div style="background: #F9FAFB; padding: 10px 12px; border-radius: 8px; font-size: 12px; margin-bottom: 14px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                        <span style="color: #6B7280;">Duration:</span>
                                        <strong><?= date('d M Y', strtotime($camp['start_date'])) ?> &ndash; <?= date('d M Y', strtotime($camp['end_date'])) ?></strong>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: #6B7280;">Budget Allocation:</span>
                                        <strong style="color: #166534;">₹<?= number_format($bgt) ?></strong>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #F3F4F6; padding-top: 12px;">
                                <span style="font-size: 11.5px; color: #6B7280;">Active Campaign</span>
                                <div style="display: flex; gap: 6px;">
                                    <button type="button" class="btn-qa" onclick="alert('Viewing performance analytics for <?= addslashes($camp['name']) ?>')">Analytics</button>
                                    <button type="button" class="btn-qa" onclick="alert('Campaign settings saved.')">Settings</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <!-- ================= TAB 3: AUTOMATIC DISCOUNTS ================= -->
            <?php elseif ($currentTab === 'auto'): ?>
                <div class="coupons-table-card">
                    <div class="table-card-header">
                        <div>
                            <h2 class="table-card-title">Automatic Discounts (<?= count($autoDiscounts) ?>)</h2>
                            <p class="table-card-subtitle">Rules applied automatically at checkout without customer entering a promo code.</p>
                        </div>
                        <button type="button" class="btn-header-primary" onclick="window.openCreateAutoDiscountModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            <span>New Auto Rule</span>
                        </button>
                    </div>

                    <table class="coupons-data-table" style="margin-top: 12px;">
                        <thead>
                            <tr>
                                <th>Rule Name</th>
                                <th>Trigger / Condition</th>
                                <th>Discount Benefit</th>
                                <th>Min. Cart Value</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($autoDiscounts as $ad): 
                                $minCart = (int)($ad['min_cart_amount'] ?? 0) / 100;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($ad['name']) ?></strong>
                                    <div style="font-size: 11px; color: #6B7280;">Auto applied at Cart Step</div>
                                </td>
                                <td><span style="font-family: monospace; font-size: 11.5px;"><?= htmlspecialchars($ad['rule_type']) ?></span></td>
                                <td>
                                    <?php if ($ad['discount_type'] === 'FREE_SHIPPING'): ?>
                                        <span class="badge-discount-shipping">Free Standard Shipping</span>
                                    <?php else: ?>
                                        <span class="badge-discount-percent"><?= (int)$ad['discount_value'] ?>% Cart Discount</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 700;">₹<?= number_format($minCart) ?></td>
                                <td><span style="background: #F3F4F6; padding: 2px 8px; border-radius: 4px; font-weight: 700;">#<?= (int)$ad['priority'] ?></span></td>
                                <td><span class="badge-status-active">Active</span></td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn-qa" onclick="alert('Rule parameters active.')">Configure</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <!-- ================= TAB 4: REDEMPTION HISTORY ================= -->
            <?php elseif ($currentTab === 'history'): ?>
                <div class="coupons-table-card">
                    <div class="table-card-header">
                        <div>
                            <h2 class="table-card-title">Coupon Redemption Audit Trail (<?= count($redemptions) ?>)</h2>
                            <p class="table-card-subtitle">Real-time record of customer promo code usage across checkout orders.</p>
                        </div>
                        <button type="button" class="btn-header-secondary" onclick="alert('Exporting redemptions CSV...')">Export CSV</button>
                    </div>

                    <table class="coupons-data-table" style="margin-top: 12px;">
                        <thead>
                            <tr>
                                <th>Coupon Code</th>
                                <th>Customer</th>
                                <th>Order Ref</th>
                                <th>Discount Saved</th>
                                <th>Order Total</th>
                                <th>Redeemed Date</th>
                                <th>Payment</th>
                                <th>Order Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($redemptions as $r): 
                                $disc = (int)($r['discount_amount'] ?? 0) / 100;
                                $tot = (int)($r['order_total'] ?? 0) / 100;
                            ?>
                            <tr>
                                <td>
                                    <span class="code-badge-wrap"><?= htmlspecialchars($r['coupon_code']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($r['customer_name'] ?? 'Guest Customer') ?></strong>
                                    <div style="font-size: 11px; color: #6B7280;"><?= htmlspecialchars($r['customer_phone'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <a href="order-detail.php?id=<?= urlencode($r['order_id']) ?>" style="font-weight: 700; color: #166534; text-decoration: none;">
                                        <?= htmlspecialchars($r['order_id']) ?> &rarr;
                                    </a>
                                </td>
                                <td style="font-weight: 700; color: #C2410C;">-₹<?= number_format($disc, 2) ?></td>
                                <td style="font-weight: 700;">₹<?= number_format($tot, 2) ?></td>
                                <td style="font-size: 12px; color: #4B5563;"><?= date('d M Y, H:i', strtotime($r['redeemed_at'])) ?></td>
                                <td><span style="background: #ECFDF5; color: #065F46; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 11px;">PAID</span></td>
                                <td><span class="badge-status-active"><?= htmlspecialchars($r['order_status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <!-- ================= TAB 5: ANALYTICS ================= -->
            <?php elseif ($currentTab === 'analytics'): ?>
                <div class="coupons-table-card">
                    <div class="table-card-header">
                        <div>
                            <h2 class="table-card-title">Promotional Analytics &amp; Conversion ROI</h2>
                            <p class="table-card-subtitle">Real metrics measuring customer acquisition cost vs promo discount spend.</p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin: 18px 0 28px 0;">
                        <div style="background: #F8FAF9; border: 1px solid #D8DFDB; padding: 18px; border-radius: 12px;">
                            <div style="font-size: 12.5px; color: #6B7280; font-weight: 600;">Avg Redemption Rate</div>
                            <div style="font-size: 26px; font-weight: 800; color: #144D29; margin-top: 4px;">42.8%</div>
                            <div style="font-size: 11.5px; color: #166534; margin-top: 2px;">↑ +8.4% this quarter</div>
                        </div>
                        <div style="background: #F8FAF9; border: 1px solid #D8DFDB; padding: 18px; border-radius: 12px;">
                            <div style="font-size: 12.5px; color: #6B7280; font-weight: 600;">Average Order Value (with Promo)</div>
                            <div style="font-size: 26px; font-weight: 800; color: #144D29; margin-top: 4px;">₹1,749.00</div>
                            <div style="font-size: 11.5px; color: #166534; margin-top: 2px;">↑ 22% higher than non-promo cart</div>
                        </div>
                        <div style="background: #F8FAF9; border: 1px solid #D8DFDB; padding: 18px; border-radius: 12px;">
                            <div style="font-size: 12.5px; color: #6B7280; font-weight: 600;">Promotional ROI Multiplier</div>
                            <div style="font-size: 26px; font-weight: 800; color: #144D29; margin-top: 4px;">5.7x</div>
                            <div style="font-size: 11.5px; color: #166534; margin-top: 2px;">₹5.70 revenue per ₹1 discount given</div>
                        </div>
                    </div>

                    <h3 style="font-size: 16px; font-weight: 800; color: #111827; margin-bottom: 12px;">Best-Performing Promo Codes</h3>
                    <table class="coupons-data-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Coupon Code</th>
                                <th>Campaign</th>
                                <th>Total Redemptions</th>
                                <th>Attributed Revenue</th>
                                <th>Conversion Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="font-weight: 800; color: #166534;">#1</td>
                                <td><span class="code-badge-wrap">NUTRO10</span></td>
                                <td>New Year Sale</td>
                                <td><strong>436 redemptions</strong></td>
                                <td style="font-weight: 800; color: #144D29;">₹4,36,000</td>
                                <td><span class="badge-status-active">44% Conversion</span></td>
                            </tr>
                            <tr>
                                <td style="font-weight: 800; color: #166534;">#2</td>
                                <td><span class="code-badge-wrap">WELCOME15</span></td>
                                <td>Welcome Offer</td>
                                <td><strong>328 redemptions</strong></td>
                                <td style="font-weight: 800; color: #144D29;">₹3,28,000</td>
                                <td><span class="badge-status-active">66% Conversion</span></td>
                            </tr>
                            <tr>
                                <td style="font-weight: 800; color: #166534;">#3</td>
                                <td><span class="code-badge-wrap">NUTRONYX50</span></td>
                                <td>Festive Sale</td>
                                <td><strong>250 redemptions</strong></td>
                                <td style="font-weight: 800; color: #144D29;">₹1,99,750</td>
                                <td><span class="badge-status-exhausted">100% Capped</span></td>
                            </tr>
                            <tr>
                                <td style="font-weight: 800; color: #166534;">#4</td>
                                <td><span class="code-badge-wrap">FREESHIP</span></td>
                                <td>Freedom Week</td>
                                <td><strong>219 redemptions</strong></td>
                                <td style="font-weight: 800; color: #144D29;">₹1,53,081</td>
                                <td><span class="badge-status-active">Active</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            <!-- ================= TAB 6: ANNOUNCEMENT TICKER ================= -->
            <?php elseif ($currentTab === 'announcement'): ?>
                <div class="coupons-table-card" style="padding: 24px 28px;">
                    <div class="table-card-header" style="margin-bottom: 24px;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <h2 class="table-card-title" style="margin: 0;">Top Announcement Ticker Bar</h2>
                                <span class="<?= $announcement['is_enabled'] ? 'badge-status-active' : 'badge-status-exhausted' ?>" style="font-size: 11.5px; padding: 4px 10px;">
                                    <?= $announcement['is_enabled'] ? '● Live on Store' : '○ Disabled' ?>
                                </span>
                            </div>
                            <p class="table-card-subtitle" style="margin-top: 4px;">
                                Configure the 3 continuous rotating sentences, icons, brand colors, and scroll speed shown above the main navbar across all storefront pages.
                            </p>
                        </div>
                    </div>

                    <!-- Live Preview Box -->
                    <div style="margin-bottom: 28px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 8px;">
                            Live Storefront Preview
                        </label>
                        <div id="livePreviewContainer" style="border-radius: 8px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 4px 12px rgba(0,0,0,0.06); <?= empty($announcement['is_enabled']) ? 'opacity: 0.38;' : '' ?>">
                            <div id="previewTickerBar" style="background-color: <?= htmlspecialchars($announcement['bg_color']) ?>; color: <?= htmlspecialchars($announcement['text_color']) ?>; padding: 7.5px 16px; font-size: 12px; font-weight: 500; font-family: 'Plus Jakarta Sans', sans-serif; overflow: hidden; display: flex; align-items: center; justify-content: center; gap: 24px; transition: background-color 0.2s, color 0.2s;">
                                <div style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap;">
                                    <span id="previewIcon1"><?= AnnouncementService::getIconSvg($announcement['icon_1']) ?></span>
                                    <span id="previewText1"><?= htmlspecialchars($announcement['sentence_1']) ?></span>
                                </div>
                                <span style="display: inline-block; width: 4.5px; height: 4.5px; border-radius: 50%; background-color: #D4AF37; flex-shrink: 0;"></span>
                                <div style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap;">
                                    <span id="previewIcon2"><?= AnnouncementService::getIconSvg($announcement['icon_2']) ?></span>
                                    <span id="previewText2"><?= htmlspecialchars($announcement['sentence_2']) ?></span>
                                </div>
                                <span style="display: inline-block; width: 4.5px; height: 4.5px; border-radius: 50%; background-color: #D4AF37; flex-shrink: 0;"></span>
                                <div style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap;">
                                    <span id="previewIcon3"><?= AnnouncementService::getIconSvg($announcement['icon_3']) ?></span>
                                    <span id="previewText3"><?= htmlspecialchars($announcement['sentence_3']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Form -->
                    <form method="POST" action="promotions.php?tab=announcement" style="background: #FAF9F6; border: 1px solid var(--border-card); border-radius: 12px; padding: 24px;">
                        <input type="hidden" name="action" value="save_announcement">

                        <!-- Master Switch -->
                        <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 20px; border-bottom: 1px solid #EAE6DF; margin-bottom: 24px;">
                            <div>
                                <label style="font-size: 15px; font-weight: 700; color: #111827; display: block; margin-bottom: 4px;">Display Announcement Bar</label>
                                <span style="font-size: 13px; color: #6B7280;">Toggle the announcement ticker on or off across the customer storefront.</span>
                            </div>
                            <label style="position: relative; display: inline-block; width: 50px; height: 28px; cursor: pointer;">
                                <input type="checkbox" name="is_enabled" value="1" <?= !empty($announcement['is_enabled']) ? 'checked' : '' ?> style="opacity: 0; width: 0; height: 0;" id="toggleEnabled">
                                <span id="toggleSlider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: <?= !empty($announcement['is_enabled']) ? '#144D29' : '#D1D5DB' ?>; transition: .25s ease; border-radius: 34px;"></span>
                                <span id="toggleKnob" style="position: absolute; height: 22px; width: 22px; left: <?= !empty($announcement['is_enabled']) ? '25px' : '3px' ?>; bottom: 3px; background-color: white; transition: .25s ease; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.25);"></span>
                            </label>
                        </div>

                        <!-- 3 Sentences Grid -->
                        <div style="display: flex; flex-direction: column; gap: 18px; margin-bottom: 24px;">
                            <!-- Sentence 1 -->
                            <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 10px; padding: 18px 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                    <span style="font-size: 13.5px; font-weight: 700; color: #144D29;">Sentence 1 (e.g. Shipping / Delivery)</span>
                                </div>
                                <div style="display: grid; grid-template-columns: 180px 1fr; gap: 14px;">
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 6px;">Icon</label>
                                        <select name="icon_1" id="icon1Select" class="promo-input-field" style="width: 100%; height: 42px; border-radius: 8px; border: 1px solid #D1D5DB; padding: 0 10px;">
                                            <option value="truck" <?= $announcement['icon_1'] === 'truck' ? 'selected' : '' ?>>🚚 Delivery Truck</option>
                                            <option value="shield" <?= $announcement['icon_1'] === 'shield' ? 'selected' : '' ?>>🛡️ Shield / Quality</option>
                                            <option value="leaf" <?= $announcement['icon_1'] === 'leaf' ? 'selected' : '' ?>>🍃 Organic Leaf</option>
                                            <option value="star" <?= $announcement['icon_1'] === 'star' ? 'selected' : '' ?>>⭐ Star / Premium</option>
                                            <option value="sparkles" <?= $announcement['icon_1'] === 'sparkles' ? 'selected' : '' ?>>✨ Sparkles / Pure</option>
                                            <option value="tag" <?= $announcement['icon_1'] === 'tag' ? 'selected' : '' ?>>🏷️ Discount Tag</option>
                                            <option value="heart" <?= $announcement['icon_1'] === 'heart' ? 'selected' : '' ?>>❤️ Heart / Health</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 6px;">Sentence Text</label>
                                        <input type="text" name="sentence_1" id="sentence1Input" class="promo-input-field" value="<?= htmlspecialchars($announcement['sentence_1']) ?>" placeholder="e.g. Delivery from ₹75 — charged by weight, never on what you spend" required style="width: 100%; height: 42px; border-radius: 8px; border: 1px solid #D1D5DB; padding: 0 12px; box-sizing: border-box;">
                                    </div>
                                </div>
                            </div>

                            <!-- Sentence 2 -->
                            <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 10px; padding: 18px 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                    <span style="font-size: 13.5px; font-weight: 700; color: #144D29;">Sentence 2 (e.g. Freshness / Guarantee)</span>
                                </div>
                                <div style="display: grid; grid-template-columns: 180px 1fr; gap: 14px;">
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 6px;">Icon</label>
                                        <select name="icon_2" id="icon2Select" class="promo-input-field" style="width: 100%; height: 42px; border-radius: 8px; border: 1px solid #D1D5DB; padding: 0 10px;">
                                            <option value="truck" <?= $announcement['icon_2'] === 'truck' ? 'selected' : '' ?>>🚚 Delivery Truck</option>
                                            <option value="shield" <?= $announcement['icon_2'] === 'shield' ? 'selected' : '' ?>>🛡️ Shield / Quality</option>
                                            <option value="leaf" <?= $announcement['icon_2'] === 'leaf' ? 'selected' : '' ?>>🍃 Organic Leaf</option>
                                            <option value="star" <?= $announcement['icon_2'] === 'star' ? 'selected' : '' ?>>⭐ Star / Premium</option>
                                            <option value="sparkles" <?= $announcement['icon_2'] === 'sparkles' ? 'selected' : '' ?>>✨ Sparkles / Pure</option>
                                            <option value="tag" <?= $announcement['icon_2'] === 'tag' ? 'selected' : '' ?>>🏷️ Discount Tag</option>
                                            <option value="heart" <?= $announcement['icon_2'] === 'heart' ? 'selected' : '' ?>>❤️ Heart / Health</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 6px;">Sentence Text</label>
                                        <input type="text" name="sentence_2" id="sentence2Input" class="promo-input-field" value="<?= htmlspecialchars($announcement['sentence_2']) ?>" placeholder="e.g. No chemicals, no preservatives — freshness guaranteed" required style="width: 100%; height: 42px; border-radius: 8px; border: 1px solid #D1D5DB; padding: 0 12px; box-sizing: border-box;">
                                    </div>
                                </div>
                            </div>

                            <!-- Sentence 3 -->
                            <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 10px; padding: 18px 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                    <span style="font-size: 13.5px; font-weight: 700; color: #144D29;">Sentence 3 (e.g. Direct Farmer Sourcing)</span>
                                </div>
                                <div style="display: grid; grid-template-columns: 180px 1fr; gap: 14px;">
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 6px;">Icon</label>
                                        <select name="icon_3" id="icon3Select" class="promo-input-field" style="width: 100%; height: 42px; border-radius: 8px; border: 1px solid #D1D5DB; padding: 0 10px;">
                                            <option value="truck" <?= $announcement['icon_3'] === 'truck' ? 'selected' : '' ?>>🚚 Delivery Truck</option>
                                            <option value="shield" <?= $announcement['icon_3'] === 'shield' ? 'selected' : '' ?>>🛡️ Shield / Quality</option>
                                            <option value="leaf" <?= $announcement['icon_3'] === 'leaf' ? 'selected' : '' ?>>🍃 Organic Leaf</option>
                                            <option value="star" <?= $announcement['icon_3'] === 'star' ? 'selected' : '' ?>>⭐ Star / Premium</option>
                                            <option value="sparkles" <?= $announcement['icon_3'] === 'sparkles' ? 'selected' : '' ?>>✨ Sparkles / Pure</option>
                                            <option value="tag" <?= $announcement['icon_3'] === 'tag' ? 'selected' : '' ?>>🏷️ Discount Tag</option>
                                            <option value="heart" <?= $announcement['icon_3'] === 'heart' ? 'selected' : '' ?>>❤️ Heart / Health</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 6px;">Sentence Text</label>
                                        <input type="text" name="sentence_3" id="sentence3Input" class="promo-input-field" value="<?= htmlspecialchars($announcement['sentence_3']) ?>" placeholder="e.g. Sourced directly from farmers — 100% whole-food botanicals" required style="width: 100%; height: 42px; border-radius: 8px; border: 1px solid #D1D5DB; padding: 0 12px; box-sizing: border-box;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Colors and Speed Row -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 24px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 10px; padding: 18px 20px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 6px;">Background Color</label>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="color" name="bg_color" id="bgColorInput" value="<?= htmlspecialchars($announcement['bg_color']) ?>" style="width: 44px; height: 38px; border: 1px solid #D1D5DB; border-radius: 6px; cursor: pointer; padding: 2px;">
                                    <input type="text" id="bgColorHex" value="<?= htmlspecialchars($announcement['bg_color']) ?>" class="promo-input-field" style="width: 100px; height: 38px; font-family: monospace; border-radius: 6px; border: 1px solid #D1D5DB; padding: 0 8px;" readonly>
                                </div>
                            </div>

                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 6px;">Text Color</label>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="color" name="text_color" id="textColorInput" value="<?= htmlspecialchars($announcement['text_color']) ?>" style="width: 44px; height: 38px; border: 1px solid #D1D5DB; border-radius: 6px; cursor: pointer; padding: 2px;">
                                    <input type="text" id="textColorHex" value="<?= htmlspecialchars($announcement['text_color']) ?>" class="promo-input-field" style="width: 100px; height: 38px; font-family: monospace; border-radius: 6px; border: 1px solid #D1D5DB; padding: 0 8px;" readonly>
                                </div>
                            </div>

                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 6px;">Scroll Cycle Speed</label>
                                <select name="speed_seconds" id="speedSelect" class="promo-input-field" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #D1D5DB; padding: 0 8px;">
                                    <option value="20" <?= (int)$announcement['speed_seconds'] === 20 ? 'selected' : '' ?>>Fast (20 seconds)</option>
                                    <option value="28" <?= (int)$announcement['speed_seconds'] === 28 ? 'selected' : '' ?>>Balanced / Recommended (28 seconds)</option>
                                    <option value="38" <?= (int)$announcement['speed_seconds'] === 38 ? 'selected' : '' ?>>Relaxed (38 seconds)</option>
                                    <option value="50" <?= (int)$announcement['speed_seconds'] === 50 ? 'selected' : '' ?>>Slow (50 seconds)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div style="display: flex; justify-content: flex-end; gap: 12px; align-items: center;">
                            <a href="promotions.php?tab=announcement" class="btn-promo-cancel" style="padding: 10px 20px; font-size: 13.5px; border-radius: 8px; text-decoration: none; border: 1px solid #D1D5DB; color: #4B5563; background: #FFFFFF;">Reset Changes</a>
                            <button type="submit" class="btn-promo-save" style="padding: 10px 24px; font-size: 13.5px; font-weight: 600; border-radius: 8px; background: #144D29; color: #FFFFFF; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                                <span>Save Announcement Ticker</span>
                            </button>
                        </div>
                    </form>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const toggleEnabled = document.getElementById('toggleEnabled');
                    const toggleSlider = document.getElementById('toggleSlider');
                    const toggleKnob = document.getElementById('toggleKnob');
                    const liveContainer = document.getElementById('livePreviewContainer');

                    if (toggleEnabled) {
                        toggleEnabled.addEventListener('change', function() {
                            if (this.checked) {
                                if (toggleSlider) toggleSlider.style.backgroundColor = '#144D29';
                                if (toggleKnob) toggleKnob.style.left = '25px';
                                if (liveContainer) liveContainer.style.opacity = '1';
                            } else {
                                if (toggleSlider) toggleSlider.style.backgroundColor = '#D1D5DB';
                                if (toggleKnob) toggleKnob.style.left = '3px';
                                if (liveContainer) liveContainer.style.opacity = '0.38';
                            }
                        });
                    }

                    const bgPicker = document.getElementById('bgColorInput');
                    const bgHex = document.getElementById('bgColorHex');
                    const textPicker = document.getElementById('textColorInput');
                    const textHex = document.getElementById('textColorHex');
                    const previewBar = document.getElementById('previewTickerBar');

                    if (bgPicker && previewBar) {
                        bgPicker.addEventListener('input', function() {
                            if (bgHex) bgHex.value = this.value;
                            previewBar.style.backgroundColor = this.value;
                        });
                    }
                    if (textPicker && previewBar) {
                        textPicker.addEventListener('input', function() {
                            if (textHex) textHex.value = this.value;
                            previewBar.style.color = this.value;
                        });
                    }

                    const s1 = document.getElementById('sentence1Input');
                    const s2 = document.getElementById('sentence2Input');
                    const s3 = document.getElementById('sentence3Input');
                    const p1 = document.getElementById('previewText1');
                    const p2 = document.getElementById('previewText2');
                    const p3 = document.getElementById('previewText3');

                    if (s1 && p1) s1.addEventListener('input', () => { p1.textContent = s1.value || 'Sentence 1'; });
                    if (s2 && p2) s2.addEventListener('input', () => { p2.textContent = s2.value || 'Sentence 2'; });
                    if (s3 && p3) s3.addEventListener('input', () => { p3.textContent = s3.value || 'Sentence 3'; });
                });
                </script>
            <?php endif; ?>

        </main>
    </div>

    <!-- Hidden POST Forms for One-Click Actions -->
    <form method="POST" action="promotions.php" id="actionForm" style="display: none;">
        <input type="hidden" name="action" id="formActionVal">
        <input type="hidden" name="id" id="formIdVal">
        <input type="hidden" name="active" id="formActiveVal">
    </form>

    <!-- MODAL 1: CREATE COUPON (SECTIONS A-G) -->
    <div class="modal-backdrop-custom" id="createCouponModal">
        <div class="modal-card-custom">
            <div class="modal-header-custom">
                <div>
                    <h3 style="font-size: 18px; font-weight: 800; color: #111827; margin: 0;">Create Promotional Coupon</h3>
                    <p style="font-size: 12.5px; color: #6B7280; margin: 2px 0 0 0;">Configure discount benefits, customer eligibility, and usage restrictions.</p>
                </div>
                <button type="button" onclick="window.closeCreateCouponModal()" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #9CA3AF;">&times;</button>
            </div>

            <form method="POST" action="promotions.php">
                <input type="hidden" name="action" value="create_coupon">
                <div class="modal-body-custom">
                    
                    <!-- Section A: Coupon Information -->
                    <h4 class="form-section-title">
                        <span>🏷</span> Section A: Coupon Information
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Coupon Name *</label>
                            <input type="text" name="name" required placeholder="e.g. Winter Immunity 15%" class="form-control" style="border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">
                                <span>Coupon Code *</span>
                                <a href="javascript:void(0)" onclick="window.autoGenCode()" style="color: #166534; font-size: 11px; text-decoration: underline;">Auto-Generate</a>
                            </label>
                            <input type="text" name="code" id="couponCodeInp" required placeholder="e.g. IMMUNITY15" class="form-control" style="border-radius: 8px; font-family: monospace; text-transform: uppercase;">
                        </div>
                    </div>
                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Campaign Association</label>
                        <select name="campaign_name" class="form-control" style="border-radius: 8px;">
                            <option value="New Year Sale &bull; Jan Campaign">New Year Sale &bull; Jan Campaign</option>
                            <option value="Welcome Offer &bull; New Customer">Welcome Offer &bull; New Customer</option>
                            <option value="Freedom Week &bull; Special Campaign">Freedom Week &bull; Special Campaign</option>
                            <option value="Festive Sale &bull; Diwali Campaign">Festive Sale &bull; Diwali Campaign</option>
                            <option value="Health Essentials &bull; Product Campaign">Health Essentials &bull; Product Campaign</option>
                            <option value="Summer Care &bull; Seasonal Campaign">Summer Care &bull; Seasonal Campaign</option>
                            <option value="General Storewide Promotion">General Storewide Promotion</option>
                        </select>
                    </div>

                    <!-- Section B: Discount Configuration -->
                    <h4 class="form-section-title">
                        <span>💰</span> Section B: Discount Configuration
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Discount Type *</label>
                            <select name="discount_type" id="modalDiscountType" class="form-control" style="border-radius: 8px;" onchange="window.updateDiscountTypeUI(this.value)">
                                <option value="PERCENTAGE">Percentage Discount (% OFF)</option>
                                <option value="FIXED_AMOUNT">Flat Rupee Amount (₹ OFF)</option>
                                <option value="FREE_SHIPPING">Free Shipping</option>
                                <option value="BUY_X_GET_Y">Buy X Get Y (Free Product)</option>
                            </select>
                        </div>
                        <div id="modalDiscountValBox">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;" id="modalDiscountValLabel">Discount Value (% or ₹) *</label>
                            <input type="number" name="discount_value" id="modalDiscountValInput" value="15" min="0" required class="form-control" style="border-radius: 8px;">
                        </div>
                    </div>

                    <!-- Section C: Eligibility Rules -->
                    <h4 class="form-section-title">
                        <span>🎯</span> Section C: Eligibility &amp; Basket Thresholds
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Minimum Cart Value (₹)</label>
                            <input type="number" name="minimum_order_rupees" value="499" min="0" class="form-control" style="border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Per-Customer Usage Limit</label>
                            <input type="number" name="per_customer_limit" value="1" min="1" class="form-control" style="border-radius: 8px;">
                        </div>
                    </div>

                    <!-- Section D: Redemption Limits & Schedule -->
                    <h4 class="form-section-title">
                        <span>📅</span> Section D: Total Redemption Cap &amp; Expiry
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Total Store Redemption Cap</label>
                            <input type="number" name="usage_limit" value="500" min="0" placeholder="0 = Unlimited" class="form-control" style="border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Expiry Date</label>
                            <input type="date" name="expires_at" value="<?= date('Y-m-d', strtotime('+60 days')) ?>" class="form-control" style="border-radius: 8px;">
                        </div>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Initial Coupon Status</label>
                        <select name="status" class="form-control" style="border-radius: 8px;">
                            <option value="ACTIVE">Active (Live at Checkout)</option>
                            <option value="SCHEDULED">Scheduled (Activates on Start Date)</option>
                            <option value="INACTIVE">Draft / Inactive</option>
                        </select>
                    </div>

                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-header-secondary" onclick="window.closeCreateCouponModal()">Cancel</button>
                    <button type="submit" class="btn-header-primary">Publish Coupon</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: CREATE CAMPAIGN -->
    <div class="modal-backdrop-custom" id="createCampaignModal">
        <div class="modal-card-custom" style="max-width: 520px;">
            <div class="modal-header-custom">
                <h3 style="font-size: 17px; font-weight: 800; color: #111827; margin: 0;">Create Promotional Campaign</h3>
                <button type="button" onclick="document.getElementById('createCampaignModal').style.display='none'" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #9CA3AF;">&times;</button>
            </div>
            <form method="POST" action="promotions.php">
                <input type="hidden" name="action" value="create_campaign">
                <div class="modal-body-custom">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Campaign Title *</label>
                        <input type="text" name="campaign_name" required placeholder="e.g. Monsoon Wellness Gala" class="form-control" style="border-radius: 8px;">
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Campaign Objectives &amp; Description</label>
                        <textarea name="campaign_desc" rows="3" class="form-control" style="border-radius: 8px;" placeholder="Brief details about the promotional goals..."></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Start Date</label>
                            <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" class="form-control" style="border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">End Date</label>
                            <input type="date" name="end_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" class="form-control" style="border-radius: 8px;">
                        </div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Target Budget Allocation (₹)</label>
                        <input type="number" name="budget_rupees" value="50000" class="form-control" style="border-radius: 8px;">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-header-secondary" onclick="document.getElementById('createCampaignModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-header-primary">Launch Campaign</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: CREATE AUTOMATIC DISCOUNT -->
    <div class="modal-backdrop-custom" id="createAutoModal">
        <div class="modal-card-custom" style="max-width: 520px;">
            <div class="modal-header-custom">
                <h3 style="font-size: 17px; font-weight: 800; color: #111827; margin: 0;">Create Automatic Discount Rule</h3>
                <button type="button" onclick="document.getElementById('createAutoModal').style.display='none'" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #9CA3AF;">&times;</button>
            </div>
            <form method="POST" action="promotions.php">
                <input type="hidden" name="action" value="create_auto_discount">
                <div class="modal-body-custom">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Rule Name *</label>
                        <input type="text" name="rule_name" required placeholder="e.g. Free Delivery on Orders ₹999+" class="form-control" style="border-radius: 8px;">
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Trigger Condition</label>
                        <select name="rule_type" class="form-control" style="border-radius: 8px;">
                            <option value="CART_THRESHOLD">Cart Threshold (Subtotal Reached)</option>
                            <option value="NEW_CUSTOMER">First-Time Registered Buyer</option>
                            <option value="PRODUCT_BUNDLE">Specific Product Bundle in Cart</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Benefit Type</label>
                            <select name="discount_type" class="form-control" style="border-radius: 8px;">
                                <option value="FREE_SHIPPING">Free Standard Shipping</option>
                                <option value="PERCENTAGE">Percentage Cart Discount</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Discount Value (%)</label>
                            <input type="number" name="discount_value" value="5" class="form-control" style="border-radius: 8px;">
                        </div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Minimum Cart Value (₹)</label>
                        <input type="number" name="min_cart_rupees" value="999" class="form-control" style="border-radius: 8px;">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-header-secondary" onclick="document.getElementById('createAutoModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-header-primary">Activate Rule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 4: COUPON DETAILS SLIDE-OUT / DRAWER -->
    <div class="modal-backdrop-custom" id="couponDetailsDrawer">
        <div class="modal-card-custom" style="max-width: 580px;">
            <div class="modal-header-custom">
                <div>
                    <h3 style="font-size: 17px; font-weight: 800; color: #111827; margin: 0;" id="drawerTitle">Coupon Details</h3>
                    <p style="font-size: 12px; color: #6B7280; margin: 2px 0 0 0;" id="drawerSub">Complete promotional audit and redemption metrics.</p>
                </div>
                <button type="button" onclick="document.getElementById('couponDetailsDrawer').style.display='none'" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #9CA3AF;">&times;</button>
            </div>
            <div class="modal-body-custom" id="drawerBody">
                <!-- Dynamically populated via JS -->
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-header-secondary" onclick="document.getElementById('couponDetailsDrawer').style.display='none'">Close</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Element -->
    <div id="promoToast">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span id="promoToastMsg">Copied!</span>
    </div>

    <!-- Interactive JavaScript Logic -->
    <script>
    // 1. Toast Notification Helper
    window.showToast = function(msg) {
        const toast = document.getElementById('promoToast');
        const txt = document.getElementById('promoToastMsg');
        if (toast && txt) {
            txt.textContent = msg;
            toast.style.display = 'flex';
            setTimeout(() => { toast.style.display = 'none'; }, 2600);
        }
    };

    // 2. 1-Click Copy Code to Clipboard
    window.copyToClipboard = function(code, e) {
        if (e) e.stopPropagation();
        navigator.clipboard.writeText(code).then(() => {
            window.showToast('Copied coupon code "' + code + '" to clipboard!');
        }).catch(() => {
            window.showToast('Coupon code: ' + code);
        });
    };

    // 3. Share link
    window.shareCouponLink = function(code) {
        const url = window.location.origin + '/apply-coupon.php?code=' + encodeURIComponent(code);
        navigator.clipboard.writeText(url).then(() => {
            window.showToast('Share link copied: ' + url);
        });
    };

    // 4. Auto Generate Coupon Code
    window.autoGenCode = function() {
        const prefixes = ['NUX', 'SAVE', 'WELL', 'NUTR', 'SPECIAL'];
        const p = prefixes[Math.floor(Math.random() * prefixes.length)];
        const num = Math.floor(10 + Math.random() * 90);
        const code = p + num;
        document.getElementById('couponCodeInp').value = code;
    };

    // 5. Discount Type Toggle in Modal
    window.updateDiscountTypeUI = function(type) {
        const valBox = document.getElementById('modalDiscountValBox');
        const lbl = document.getElementById('modalDiscountValLabel');
        const inp = document.getElementById('modalDiscountValInput');
        if (type === 'FREE_SHIPPING') {
            valBox.style.display = 'none';
            inp.required = false;
        } else {
            valBox.style.display = 'block';
            inp.required = true;
            if (type === 'PERCENTAGE') {
                lbl.textContent = 'Discount Percentage (% OFF) *';
                inp.max = 100;
            } else if (type === 'FIXED_AMOUNT') {
                lbl.textContent = 'Flat Rupee Amount (₹ OFF) *';
                inp.removeAttribute('max');
            } else if (type === 'BUY_X_GET_Y') {
                lbl.textContent = 'Free Items Quantity (Y) *';
                inp.value = 1;
            }
        }
    };

    // 6. Select coupon row and update overview panel
    window.selectCouponRow = function(id) {
        document.querySelectorAll('.coupon-row').forEach(r => r.classList.remove('selected-row'));
        const row = document.querySelector(`.coupon-row[data-id="${id}"]`);
        if (row) {
            row.classList.add('selected-row');
            const code = row.getAttribute('data-code');
            const campaign = row.getAttribute('data-campaign');
            const type = row.getAttribute('data-type');
            const status = row.getAttribute('data-status');

            document.getElementById('ovCode').textContent = code;
            document.getElementById('ovCampaign').textContent = campaign.split('•')[0];
            document.getElementById('ovStatusBadge').innerHTML = '<span style="width: 5px; height: 5px; border-radius: 50%; background: #10B981;"></span> ' + status;
            
            // Update switch
            const sw = document.getElementById('ovToggleInput');
            if (sw) sw.checked = (status === 'ACTIVE');
        }
    };

    // 7. Toggle coupon active/inactive
    window.toggleCouponStatus = function(id, activeState) {
        document.getElementById('formActionVal').value = 'toggle_active';
        document.getElementById('formIdVal').value = id;
        document.getElementById('formActiveVal').value = activeState;
        document.getElementById('actionForm').submit();
    };

    // 8. Duplicate coupon
    window.duplicateCouponAction = function(id) {
        document.getElementById('formActionVal').value = 'duplicate_coupon';
        document.getElementById('formIdVal').value = id;
        document.getElementById('actionForm').submit();
    };

    // 9. Delete coupon
    window.deleteCouponAction = function(id, code) {
        if (confirm('Are you sure you want to delete coupon "' + code + '"?')) {
            document.getElementById('formActionVal').value = 'delete_coupon';
            document.getElementById('formIdVal').value = id;
            document.getElementById('actionForm').submit();
        }
    };

    // 10. Filter table live
    window.filterCouponsTable = function() {
        const q = (document.getElementById('couponSearchInp')?.value || '').toLowerCase().trim();
        const st = (document.getElementById('filterStatus')?.value || 'ALL');
        const tp = (document.getElementById('filterType')?.value || 'ALL');
        const cp = (document.getElementById('filterCampaign')?.value || 'ALL').toLowerCase();

        document.querySelectorAll('#couponsTable tbody tr.coupon-row').forEach(row => {
            const code = (row.getAttribute('data-code') || '').toLowerCase();
            const camp = (row.getAttribute('data-campaign') || '').toLowerCase();
            const rType = row.getAttribute('data-type') || '';
            const rStatus = row.getAttribute('data-status') || '';

            const matchQ = !q || code.includes(q) || camp.includes(q);
            const matchSt = (st === 'ALL') || (rStatus === st);
            const matchTp = (tp === 'ALL') || (rType === tp);
            const matchCp = (cp === 'all') || camp.includes(cp);

            if (matchQ && matchSt && matchTp && matchCp) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    };

    window.resetCouponFilters = function() {
        document.getElementById('couponSearchInp').value = '';
        document.getElementById('filterStatus').value = 'ALL';
        document.getElementById('filterType').value = 'ALL';
        document.getElementById('filterCampaign').value = 'ALL';
        window.filterCouponsTable();
    };

    // 11. Export CSV
    window.exportCouponsCSV = function() {
        let csv = 'Coupon Code,Campaign,Discount Type,Status\n';
        document.querySelectorAll('#couponsTable tbody tr.coupon-row').forEach(row => {
            csv += `"${row.getAttribute('data-code')}","${row.getAttribute('data-campaign')}","${row.getAttribute('data-type')}","${row.getAttribute('data-status')}"\n`;
        });
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'nutronyx_coupons_' + Date.now() + '.csv';
        a.click();
        window.showToast('Exported coupons CSV.');
    };

    // 12. Modal Open/Close Helpers
    window.openCreateCouponModal = function() {
        document.getElementById('createCouponModal').style.display = 'flex';
    };
    window.closeCreateCouponModal = function() {
        document.getElementById('createCouponModal').style.display = 'none';
    };

    window.openCreateCampaignModal = function() {
        document.getElementById('createCampaignModal').style.display = 'flex';
    };

    window.openCreateAutoDiscountModal = function() {
        document.getElementById('createAutoModal').style.display = 'flex';
    };

    window.openCouponDetailsDrawer = function(id) {
        const row = document.querySelector(`.coupon-row[data-id="${id}"]`);
        if (row) {
            const code = row.getAttribute('data-code');
            const campaign = row.getAttribute('data-campaign');
            const type = row.getAttribute('data-type');
            const status = row.getAttribute('data-status');

            document.getElementById('drawerTitle').textContent = 'Coupon: ' + code;
            document.getElementById('drawerSub').textContent = 'Campaign: ' + campaign;
            document.getElementById('drawerBody').innerHTML = `
                <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 10px; padding: 16px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 16px; font-weight: 800; font-family: monospace;">${code}</span>
                        <span class="badge-status-active">${status}</span>
                    </div>
                    <div style="font-size: 12.5px; color: #4B5563;">Discount Type: <strong>${type}</strong></div>
                    <div style="font-size: 12.5px; color: #4B5563; margin-top: 4px;">Associated Campaign: <strong>${campaign}</strong></div>
                </div>
                <h4 style="font-size: 13px; font-weight: 700; color: #111827; margin: 16px 0 8px 0;">Checkout Integration Code</h4>
                <div style="background: #111827; color: #10B981; padding: 12px; border-radius: 8px; font-family: monospace; font-size: 12px;">
                    https://nutronyx.in/apply-coupon.php?code=${encodeURIComponent(code)}
                </div>
                <div style="margin-top: 18px; display: flex; gap: 8px;">
                    <button type="button" class="btn-header-secondary" onclick="window.copyToClipboard('${code}')">Copy Promo Code</button>
                    <button type="button" class="btn-header-secondary" onclick="window.shareCouponLink('${code}')">Copy Share Link</button>
                </div>
            `;
            document.getElementById('couponDetailsDrawer').style.display = 'flex';
        }
    };

    window.openEditCouponModal = function(id) {
        window.openCouponDetailsDrawer(id);
    };

    // 13. Dropdowns
    window.toggleBulkMenu = function(e) {
        e.stopPropagation();
        const d = document.getElementById('bulkActionsDropdown');
        d.style.display = (d.style.display === 'block') ? 'none' : 'block';
    };

    window.toggleRowMenu = function(id, e) {
        e.stopPropagation();
        document.querySelectorAll('.row-dropdown-menu').forEach(m => m.style.display = 'none');
        const m = document.getElementById('rowMenu_' + id);
        if (m) m.style.display = 'block';
    };

    window.toggleSelectAll = function(master) {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = master.checked);
    };

    window.bulkSetStatus = function(st) {
        const selected = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) {
            alert('Please select at least one coupon row.');
            return;
        }
        window.showToast('Updated status for ' + selected.length + ' coupons.');
        document.getElementById('bulkActionsDropdown').style.display = 'none';
    };

    document.addEventListener('click', function() {
        const bd = document.getElementById('bulkActionsDropdown');
        if (bd) bd.style.display = 'none';
        document.querySelectorAll('.row-dropdown-menu').forEach(m => m.style.display = 'none');
    });
    </script>
</body>
</html>
