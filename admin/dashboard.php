<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\ReportingService;
use Nutronyx\Services\OrderService;
use Nutronyx\Services\InventoryService;
use Nutronyx\Services\NotificationService;
use Nutronyx\SDK\ModuleLoader;

AuthService::requireAuth();

$activePage = 'dashboard';
$activeWebsite = Context::getWebsite();

// Notification Actions
if (isset($_GET['mark_notifications_read'])) {
    NotificationService::markAllAsRead();
    header('Location: dashboard.php');
    exit;
}

$currentUser = AuthService::user();
$adminName = 'Mohith';
if (!empty($currentUser['name'])) {
    $parts = explode(' ', trim($currentUser['name']));
    $adminName = $parts[0];
}

// Active Date Range handling
$range = $_GET['range'] ?? '30d';
$startDateInput = $_GET['start_date'] ?? '';
$endDateInput = $_GET['end_date'] ?? '';

if (!empty($startDateInput) || !empty($endDateInput) || $range === 'custom') {
    $range = 'custom';
}

$rangeLabelMap = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    '7d' => 'Last 7 Days',
    '30d' => 'Last 30 Days',
    'month' => 'This Month',
    'last_month' => 'Last Month',
    'all' => 'All Time',
    'custom' => 'Custom Range',
];
$selectedRangeLabel = $rangeLabelMap[$range] ?? 'Last 30 Days';

// Load Real Dashboard Overview Data from Reporting Engine
$dashboardData = ReportingService::getDashboardOverview($range, $startDateInput ?: null, $endDateInput ?: null);
$kpis = $dashboardData['kpis'];
$chart = $dashboardData['chart'];
$actionAlerts = $dashboardData['action_center']['alerts'];
$pipeline = $dashboardData['pipeline'];
$insights = $dashboardData['insights'];

// Date Span text matching the active database period
switch ($range) {
    case 'today':
        $dateSpanText = date('M d, Y');
        break;
    case 'yesterday':
        $dateSpanText = date('M d, Y', strtotime('-1 day'));
        break;
    case '7d':
        $dateSpanText = date('M d, Y', strtotime('-6 days')) . ' – ' . date('M d, Y');
        break;
    case 'month':
        $dateSpanText = date('M 01, Y') . ' – ' . date('M d, Y');
        break;
    case 'last_month':
        $dateSpanText = date('M 01, Y', strtotime('first day of last month')) . ' – ' . date('M t, Y', strtotime('last month'));
        break;
    case 'all':
        $dateSpanText = date('M d, Y', strtotime($dashboardData['start_date'])) . ' – ' . date('M d, Y', strtotime($dashboardData['end_date']));
        break;
    case 'custom':
        $dateSpanText = date('M d, Y', strtotime($dashboardData['start_date'])) . ' – ' . date('M d, Y', strtotime($dashboardData['end_date']));
        $selectedRangeLabel = 'Custom (' . date('M d', strtotime($dashboardData['start_date'])) . ' – ' . date('M d, Y', strtotime($dashboardData['end_date'])) . ')';
        break;
    case '30d':
    default:
        $dateSpanText = date('M d, Y', strtotime('-29 days')) . ' – ' . date('M d, Y');
        break;
}

// Fetch real recent orders
$db = Nutronyx\Database::getConnection();
$recentOrdersStmt = $db->prepare("
    SELECT o.id, o.order_number, o.created_at, o.grand_total_amount, o.order_status, o.payment_status,
           c.name as customer_name
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.website_id = :wid
    ORDER BY o.created_at DESC
    LIMIT 5
");
$recentOrdersStmt->execute([':wid' => $activeWebsite['id']]);
$recentOrders = $recentOrdersStmt->fetchAll();

// Chart calculations for SVG Rendering
$chartPoints = $chart['points'];
$pointCount = count($chartPoints);
$maxChartRevPaise = max(10000, (int)$chart['max_val']);
$maxOrdersCount = 1;
foreach ($chartPoints as $cp) {
    if ($cp['orders'] > $maxOrdersCount) {
        $maxOrdersCount = $cp['orders'];
    }
}
$maxOrdersScale = max(10, (int)(ceil($maxOrdersCount / 5) * 5));

$svgW = 580;
$svgH = 215;
$plotX1 = 46;
$plotX2 = 534;
$plotYTop = 24;
$plotYBottom = 186;
$plotW = $plotX2 - $plotX1;
$plotH = $plotYBottom - $plotYTop;

$revPolyPoints = [];
$barElements = [];
$dateLabels = [];

$labelInterval = max(1, (int)floor($pointCount / 7));

foreach ($chartPoints as $idx => $pt) {
    $x = $pointCount > 1 ? ($plotX1 + ($idx / ($pointCount - 1)) * $plotW) : ($plotX1 + $plotW / 2);
    $y = $plotYBottom - (($pt['revenue'] / $maxChartRevPaise) * $plotH);
    $revPolyPoints[] = round($x, 1) . ' ' . round($y, 1);

    // Orders Bar
    $barH = max(2, ($pt['orders'] / $maxOrdersScale) * $plotH);
    $barY = $plotYBottom - $barH;
    $barX = $x - 4;
    $barElements[] = sprintf(
        '<rect x="%.1f" y="%.1f" width="8" height="%.1f" rx="2" fill="#D1FAE5"><title>%s: %d orders (%s)</title></rect>',
        $barX, $barY, $barH, htmlspecialchars($pt['label']), $pt['orders'], $pt['revenue_display']
    );

    // Date Label selection
    if ($idx % $labelInterval === 0 || $idx === $pointCount - 1) {
        $dateLabels[] = sprintf(
            '<text x="%.1f" y="204" text-anchor="middle" font-size="10.5" fill="#6B7280" font-family="inherit">%s</text>',
            $x, htmlspecialchars($pt['label'])
        );
    }
}

$revPolylineStr = implode(' L ', $revPolyPoints);
$revAreaPath = !empty($revPolyPoints) 
    ? "M {$plotX1} {$plotYBottom} L {$revPolylineStr} L {$plotX2} {$plotYBottom} Z"
    : "M {$plotX1} {$plotYBottom} L {$plotX2} {$plotYBottom} Z";
$revLinePath = !empty($revPolyPoints) ? "M {$revPolylineStr}" : "";
$lastPointCoords = !empty($revPolyPoints) ? explode(' ', end($revPolyPoints)) : [$plotX2, $plotYBottom];

// Donut Chart Stage Math
$pipeTotal = max(0, (int)$pipeline['total']);
$stageDelivered = 0;
$stagePreparing = 0;
$stageDispatching = 0;
$stagePending = 0;

foreach ($pipeline['stages'] as $st) {
    if (in_array($st['key'], ['delivered', 'fulfilled'], true)) {
        $stageDelivered += (int)$st['count'];
    } elseif ($st['key'] === 'processing') {
        $stagePreparing += (int)$st['count'];
    } elseif ($st['key'] === 'ready_to_ship') {
        $stageDispatching += (int)$st['count'];
    } elseif (in_array($st['key'], ['pending_payment', 'cancelled'], true)) {
        $stagePending += (int)$st['count'];
    }
}

$pipeDeliveredPct = $pipeTotal > 0 ? round(($stageDelivered / $pipeTotal) * 100, 1) : 0;
$pipePreparingPct = $pipeTotal > 0 ? round(($stagePreparing / $pipeTotal) * 100, 1) : 0;
$pipeDispatchingPct = $pipeTotal > 0 ? round(($stageDispatching / $pipeTotal) * 100, 1) : 0;
$pipePendingPct = $pipeTotal > 0 ? round(($stagePending / $pipeTotal) * 100, 1) : 0;

$circumference = 326.73; // 2 * pi * 52
$dashDelivered = round(($pipeDeliveredPct / 100) * $circumference, 2);
$dashPreparing = round(($pipePreparingPct / 100) * $circumference, 2);
$dashDispatching = round(($pipeDispatchingPct / 100) * $circumference, 2);
$dashPending = round(($pipePendingPct / 100) * $circumference, 2);

$offsetDelivered = 0;
$offsetPreparing = -$dashDelivered;
$offsetDispatching = -($dashDelivered + $dashPreparing);
$offsetPending = -($dashDelivered + $dashPreparing + $dashDispatching);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Overview &mdash; Nutronyx Admin Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=20261119_white">
    <style>
        /* ==========================================================================
           EXACT REPLICA DESIGN SYSTEM FOR NUTRONYX DASHBOARD
           ========================================================================== */
        body.admin-body {
            background-color: #F9FAFB !important;
            color: #111827;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }



        .dashboard-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px clamp(16px, 2.5vw, 36px) 60px;
            box-sizing: border-box;
        }

        /* Welcome Header */
        .dash-welcome-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }
        .dash-welcome-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(26px, 2.5vw, 32px);
            font-weight: 700;
            color: #111827;
            line-height: 1.2;
            margin: 0;
        }
        .dash-welcome-subtitle {
            font-size: 14px;
            color: #6B7280;
            margin: 5px 0 0 0;
        }

        .dash-date-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }
        .date-dropdown-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 9px 15px;
            font-size: 13px;
            font-weight: 600;
            color: #1F2937;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            transition: all 0.15s ease;
        }
        .date-dropdown-btn:hover {
            border-color: #D1D5DB;
            background: #F9FAFB;
        }
        .date-span-pill {
            display: inline-flex;
            align-items: center;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 500;
            color: #4B5563;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            white-space: nowrap;
        }

        .range-popover-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            z-index: 100;
            min-width: 170px;
            overflow: hidden;
            padding: 6px 0;
        }
        .range-popover-menu.active {
            display: block;
        }
        .range-option-item {
            display: block;
            padding: 8px 16px;
            font-size: 13px;
            color: #374151;
            text-decoration: none;
            transition: background 0.15s;
        }
        .range-option-item:hover {
            background: #F3F4F6;
            color: #111827;
        }
        .range-option-item.active {
            font-weight: 700;
            color: #143D24;
            background: #F0FDF4;
        }

        /* Generic Card Styling */
        .replica-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03), 0 1px 2px rgba(0, 0, 0, 0.02);
            padding: 22px 24px;
            box-sizing: border-box;
            position: relative;
        }

        /* 3 Primary KPI Cards Grid */
        .kpi-quad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }
        @media (max-width: 1024px) {
            .kpi-quad-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .kpi-quad-grid { grid-template-columns: 1fr; }
        }

        .kpi-item-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }
        .kpi-item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
            border-color: #10B981;
        }
        .kpi-item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .kpi-item-top-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .kpi-circle-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #ECFDF5;
            color: #10B981;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .kpi-item-label {
            font-size: 13.5px;
            font-weight: 500;
            color: #374151;
        }
        .kpi-badge-green {
            background: #ECFDF5;
            color: #059669;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .kpi-big-number {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin: 12px 0 10px;
            line-height: 1;
            letter-spacing: -0.01em;
        }
        .kpi-item-bottom {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
        }
        .kpi-vs-text {
            font-size: 12px;
            color: #9CA3AF;
        }

        /* Middle Section Grid (Chart + Action Center + Fulfillment) */
        .middle-tri-grid {
            display: grid;
            grid-template-columns: 1.85fr 1fr 1fr;
            gap: 18px;
            margin-bottom: 22px;
        }
        @media (max-width: 1100px) {
            .middle-tri-grid { grid-template-columns: 1fr; }
        }

        .section-card-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }

        /* Action Center Rows */
        .action-row-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 0;
            border-bottom: 1px solid #F3F4F6;
            text-decoration: none;
            color: inherit;
            transition: background 0.15s;
        }
        .action-row-item:last-child {
            border-bottom: none;
            padding-bottom: 4px;
        }
        .action-row-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .action-icon-square {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .action-icon-green { background: #ECFDF5; color: #10B981; }
        .action-icon-amber { background: #FFFBEB; color: #F59E0B; }
        .action-count-bold {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            min-width: 20px;
        }
        .action-label-text {
            font-size: 13px;
            color: #374151;
            font-weight: 500;
        }
        .action-chevron-right {
            color: #9CA3AF;
            font-size: 14px;
        }

        /* Fulfillment Donut Section */
        .fulfillment-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 14px;
        }
        .donut-wrap {
            position: relative;
            width: 140px;
            height: 140px;
            flex-shrink: 0;
        }
        .donut-center-info {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            pointer-events: none;
        }
        .donut-center-number {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            line-height: 1.1;
        }
        .donut-center-label {
            font-size: 11px;
            color: #6B7280;
            margin-top: 2px;
        }
        .fulfillment-legend-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
        }
        .legend-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
        }
        .legend-dot-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #374151;
            font-weight: 500;
        }
        .legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .legend-value-bold {
            font-weight: 700;
            color: #111827;
        }

        /* Bottom Section Grid (Top Products + Top Categories + Recent Orders) */
        .bottom-tri-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 18px;
        }
        @media (max-width: 1100px) {
            .bottom-tri-grid { grid-template-columns: 1fr; }
        }

        /* Product Row */
        .mock-product-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 8px;
            border-bottom: 1px solid #F3F4F6;
            cursor: pointer;
            border-radius: 8px;
            transition: background 0.15s ease;
        }
        .mock-product-row:hover {
            background: #F9FAFB;
        }
        .mock-product-row:last-child {
            border-bottom: none;
            padding-bottom: 2px;
        }
        .mock-product-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .mock-product-thumb {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
            object-fit: cover;
            background: #F9FAFB;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }
        .mock-product-name {
            font-size: 13.5px;
            font-weight: 600;
            color: #111827;
            line-height: 1.3;
        }
        .mock-product-sold {
            font-size: 12px;
            color: #6B7280;
            margin-top: 2px;
        }
        .mock-product-rev {
            font-size: 14.5px;
            font-weight: 700;
            color: #111827;
            text-align: right;
            white-space: nowrap;
        }

        /* Categories Row */
        .mock-category-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 8px;
            cursor: pointer;
            border-radius: 8px;
            transition: background 0.15s ease;
        }
        .mock-category-row:hover {
            background: #F9FAFB;
        }
        .mock-category-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 140px;
        }
        .mock-category-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ECFDF5;
            color: #10B981;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .mock-category-title {
            font-size: 13px;
            font-weight: 600;
            color: #111827;
            white-space: nowrap;
        }
        .mock-category-progress-track {
            flex: 1;
            height: 7px;
            background: #F3F4F6;
            border-radius: 9999px;
            overflow: hidden;
        }
        .mock-category-progress-fill {
            height: 100%;
            background: #143D24;
            border-radius: 9999px;
        }
        .mock-category-pct {
            font-size: 13px;
            font-weight: 600;
            color: #111827;
            min-width: 35px;
            text-align: right;
        }

        /* Orders Table */
        .mock-orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .mock-orders-table th {
            font-size: 12px;
            font-weight: 500;
            color: #9CA3AF;
            text-align: left;
            padding: 8px 6px;
            border-bottom: 1px solid #F3F4F6;
        }
        .mock-orders-table td {
            font-size: 13px;
            color: #111827;
            padding: 10px 6px;
            border-bottom: 1px solid #F9FAFB;
            vertical-align: middle;
        }
        .mock-orders-table tr:last-child td {
            border-bottom: none;
        }
        .mock-orders-table tbody tr {
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .mock-orders-table tbody tr:hover {
            background: #F9FAFB;
        }
        .order-status-pill {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 6px;
            white-space: nowrap;
            text-align: center;
        }
        .pill-delivered { background: #DCFCE7; color: #15803D; }
        .pill-dispatching { background: #FEF3C7; color: #B45309; }
        .pill-preparing { background: #DBEAFE; color: #1D4ED8; }
    </style>
</head>
<body class="admin-body">

    <!-- Top Navigation Bar -->
    <?php include __DIR__ . '/includes/header.php'; ?>



    <div class="admin-layout-container">
        <!-- Sidebar Navigation (Opens smoothly as slide-out drawer via hamburger) -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Dashboard Stage -->
        <main class="dashboard-container">
            
            <!-- Welcome Header Row -->
            <div class="dash-welcome-row">
                <div>
                    <h1 class="dash-welcome-title">Hello, <?= htmlspecialchars($adminName) ?></h1>
                    <p class="dash-welcome-subtitle">Here's what's happening with your store today.</p>
                </div>

                <div class="dash-date-controls">
                    <!-- Date Range Dropdown Trigger -->
                    <button type="button" class="date-dropdown-btn" id="dateRangeBtn" onclick="toggleRangeMenu()">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span><?= htmlspecialchars($selectedRangeLabel) ?></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </button>

                    <!-- Dropdown Options Menu -->
                    <div class="range-popover-menu" id="rangePopoverMenu" style="width: 250px;">
                        <a href="?range=today" class="range-option-item <?= ($range === 'today') ? 'active' : '' ?>">Today</a>
                        <a href="?range=yesterday" class="range-option-item <?= ($range === 'yesterday') ? 'active' : '' ?>">Yesterday</a>
                        <a href="?range=7d" class="range-option-item <?= ($range === '7d') ? 'active' : '' ?>">Last 7 Days</a>
                        <a href="?range=30d" class="range-option-item <?= ($range === '30d') ? 'active' : '' ?>">Last 30 Days</a>
                        <a href="?range=month" class="range-option-item <?= ($range === 'month') ? 'active' : '' ?>">This Month</a>
                        <a href="?range=last_month" class="range-option-item <?= ($range === 'last_month') ? 'active' : '' ?>">Last Month</a>
                        <a href="?range=all" class="range-option-item <?= ($range === 'all') ? 'active' : '' ?>">All Time</a>
                        
                        <div style="border-top: 1px solid #E5E7EB; margin-top: 6px; padding: 10px 14px 6px;">
                            <div style="font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">Custom Date Range</div>
                            <form method="GET" action="dashboard.php" style="display: flex; flex-direction: column; gap: 8px;">
                                <input type="hidden" name="range" value="custom">
                                <div>
                                    <label style="display: block; font-size: 11px; color: #6B7280; margin-bottom: 3px; font-weight: 500;">Start Date</label>
                                    <input type="date" name="start_date" value="<?= htmlspecialchars(!empty($startDateInput) ? $startDateInput : substr($dashboardData['start_date'], 0, 10)) ?>" required style="width: 100%; padding: 6px 8px; font-size: 12px; border: 1px solid #D1D5DB; border-radius: 6px; box-sizing: border-box; background: #FFF; outline: none;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 11px; color: #6B7280; margin-bottom: 3px; font-weight: 500;">End Date</label>
                                    <input type="date" name="end_date" value="<?= htmlspecialchars(!empty($endDateInput) ? $endDateInput : substr($dashboardData['end_date'], 0, 10)) ?>" required style="width: 100%; padding: 6px 8px; font-size: 12px; border: 1px solid #D1D5DB; border-radius: 6px; box-sizing: border-box; background: #FFF; outline: none;">
                                </div>
                                <button type="submit" style="margin-top: 4px; width: 100%; background: #144D29; color: #FFF; border: none; padding: 8px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: background 0.15s;">Apply Custom Range</button>
                            </form>
                        </div>
                    </div>

                    <!-- Date Span Text Pill -->
                    <div class="date-span-pill">
                        <?= htmlspecialchars($dateSpanText) ?>
                    </div>
                </div>
            </div>

            <!-- ROW 1: 3 Primary KPI Cards -->
            <div class="kpi-quad-grid">
                <!-- Card 1: Revenue -->
                <div class="kpi-item-card" onclick="window.location.href='payments.php'" title="View Payments &amp; Revenue">
                    <div>
                        <div class="kpi-item-top">
                            <div class="kpi-item-top-left">
                                <div class="kpi-circle-icon">
                                    <!-- Stacked Coins Icon -->
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <ellipse cx="12" cy="7" rx="8" ry="3"></ellipse>
                                        <path d="M4 7v6c0 1.66 3.58 3 8 3s8-1.34 8-3V7"></path>
                                        <path d="M4 13v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"></path>
                                    </svg>
                                </div>
                                <span class="kpi-item-label">Revenue</span>
                            </div>
                            <span class="<?= ($kpis['revenue_delta']['pct'] >= 0) ? 'kpi-badge-green' : 'kpi-badge-amber' ?>">
                                <?= ($kpis['revenue_delta']['pct'] >= 0 ? '&uarr; ' : '&darr; ') . htmlspecialchars($kpis['revenue_delta']['formatted']) ?>
                            </span>
                        </div>
                        <div class="kpi-big-number"><?= $kpis['net_revenue_formatted'] ?></div>
                    </div>
                    <div class="kpi-item-bottom">
                        <span class="kpi-vs-text">vs <?= htmlspecialchars($dashboardData['prev_range_title']) ?></span>
                        <!-- Green Sparkline Curve -->
                        <svg width="74" height="26" viewBox="0 0 74 26" fill="none">
                            <defs>
                                <linearGradient id="sparkGrad1" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#10B981" stop-opacity="0.3"/>
                                    <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                                </linearGradient>
                            </defs>
                            <path d="M 0 22 C 15 20, 25 24, 38 14 C 50 4, 60 12, 74 5 L 74 26 L 0 26 Z" fill="url(#sparkGrad1)"/>
                            <path d="M 0 22 C 15 20, 25 24, 38 14 C 50 4, 60 12, 74 5" stroke="#10B981" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>

                <!-- Card 2: Orders -->
                <div class="kpi-item-card" onclick="window.location.href='orders.php'" title="View All Orders">
                    <div>
                        <div class="kpi-item-top">
                            <div class="kpi-item-top-left">
                                <div class="kpi-circle-icon">
                                    <!-- Shopping Bag Icon -->
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                        <line x1="3" y1="6" x2="21" y2="6"></line>
                                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                                    </svg>
                                </div>
                                <span class="kpi-item-label">Orders</span>
                            </div>
                            <span class="<?= ($kpis['orders_delta']['pct'] >= 0) ? 'kpi-badge-green' : 'kpi-badge-amber' ?>">
                                <?= ($kpis['orders_delta']['pct'] >= 0 ? '&uarr; ' : '&darr; ') . htmlspecialchars($kpis['orders_delta']['formatted']) ?>
                            </span>
                        </div>
                        <div class="kpi-big-number"><?= number_format($kpis['total_orders']) ?></div>
                    </div>
                    <div class="kpi-item-bottom">
                        <span class="kpi-vs-text">vs <?= htmlspecialchars($dashboardData['prev_range_title']) ?></span>
                        <svg width="74" height="26" viewBox="0 0 74 26" fill="none">
                            <defs>
                                <linearGradient id="sparkGrad2" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#10B981" stop-opacity="0.3"/>
                                    <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                                </linearGradient>
                            </defs>
                            <path d="M 0 24 C 18 20, 32 15, 48 10 C 60 6, 68 8, 74 3 L 74 26 L 0 26 Z" fill="url(#sparkGrad2)"/>
                            <path d="M 0 24 C 18 20, 32 15, 48 10 C 60 6, 68 8, 74 3" stroke="#10B981" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>

                <!-- Card 3: Average Order Value -->
                <div class="kpi-item-card" onclick="window.location.href='orders.php'" title="View Orders &amp; Order Values">
                    <div>
                        <div class="kpi-item-top">
                            <div class="kpi-item-top-left">
                                <div class="kpi-circle-icon">
                                    <!-- Price Tag Icon -->
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                        <line x1="7" y1="7" x2="7.01" y2="7"></line>
                                    </svg>
                                </div>
                                <span class="kpi-item-label">Average Order Value</span>
                            </div>
                            <span class="<?= ($kpis['aov_delta']['pct'] >= 0) ? 'kpi-badge-green' : 'kpi-badge-amber' ?>">
                                <?= ($kpis['aov_delta']['pct'] >= 0 ? '&uarr; ' : '&darr; ') . htmlspecialchars($kpis['aov_delta']['formatted']) ?>
                            </span>
                        </div>
                        <div class="kpi-big-number"><?= $kpis['aov_formatted'] ?></div>
                    </div>
                    <div class="kpi-item-bottom">
                        <span class="kpi-vs-text">vs <?= htmlspecialchars($dashboardData['prev_range_title']) ?></span>
                        <svg width="74" height="26" viewBox="0 0 74 26" fill="none">
                            <defs>
                                <linearGradient id="sparkGrad3" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#10B981" stop-opacity="0.3"/>
                                    <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                                </linearGradient>
                            </defs>
                            <path d="M 0 20 C 20 22, 34 16, 46 12 C 58 8, 66 10, 74 6 L 74 26 L 0 26 Z" fill="url(#sparkGrad3)"/>
                            <path d="M 0 20 C 20 22, 34 16, 46 12 C 58 8, 66 10, 74 6" stroke="#10B981" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>

            </div>

            <!-- ROW 2: Middle Section (Revenue Chart + Action Center + Order Fulfillment) -->
            <div class="middle-tri-grid">
                <!-- 1. Revenue & Orders Combination Chart -->
                <div class="replica-card" onclick="window.location.href='orders.php'" style="cursor: pointer;" title="View Revenue &amp; Orders details">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <h2 class="section-card-title">Revenue &amp; Orders</h2>
                            <span style="font-size: 14px; color: #166534; font-weight: 600;">&rarr;</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 18px; font-size: 12.5px; color: #4B5563;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span style="width: 8px; height: 8px; background: #143D24; border-radius: 50%; display: inline-block;"></span>
                                <span style="font-weight: 500;">Revenue</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span style="width: 8px; height: 8px; background: #A7D7A9; border-radius: 50%; display: inline-block;"></span>
                                <span style="font-weight: 500;">Orders</span>
                            </div>
                        </div>
                    </div>

                    <!-- Dual Axis Combination SVG Chart -->
                    <div style="width: 100%; height: 215px; position: relative;">
                        <svg viewBox="0 0 580 215" preserveAspectRatio="none" style="width: 100%; height: 100%; overflow: visible;">
                            <defs>
                                <linearGradient id="chartRevAreaGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#143D24" stop-opacity="0.22"/>
                                    <stop offset="85%" stop-color="#143D24" stop-opacity="0.02"/>
                                    <stop offset="100%" stop-color="#143D24" stop-opacity="0.0"/>
                                </linearGradient>
                            </defs>

                            <!-- Horizontal Gridlines & Y-Axis Labels -->
                            <?php
                            $gridLevels = [
                                ['y' => 24, 'lineY' => 20, 'frac' => 1.0],
                                ['y' => 58, 'lineY' => 54, 'frac' => 0.8],
                                ['y' => 92, 'lineY' => 88, 'frac' => 0.6],
                                ['y' => 126, 'lineY' => 122, 'frac' => 0.4],
                                ['y' => 160, 'lineY' => 156, 'frac' => 0.2],
                            ];
                            foreach ($gridLevels as $gl):
                                $valPaise = (int)round($maxChartRevPaise * $gl['frac']);
                                $valRupees = (int)round($valPaise / 100);
                                $valLabel = ($valRupees >= 100000) ? '₹' . round($valRupees / 100000, 1) . 'L' : (($valRupees >= 1000) ? '₹' . round($valRupees / 1000, 1) . 'k' : '₹' . $valRupees);
                                $ordersLabel = (int)round($maxOrdersScale * $gl['frac']);
                            ?>
                            <text x="35" y="<?= $gl['y'] ?>" text-anchor="end" font-size="10.5" fill="#9CA3AF" font-family="inherit"><?= $valLabel ?></text>
                            <text x="548" y="<?= $gl['y'] ?>" text-anchor="start" font-size="10.5" fill="#9CA3AF" font-family="inherit"><?= $ordersLabel ?></text>
                            <line x1="42" y1="<?= $gl['lineY'] ?>" x2="540" y2="<?= $gl['lineY'] ?>" stroke="#F3F4F6" stroke-dasharray="3,3" stroke-width="1"/>
                            <?php endforeach; ?>

                            <!-- Row 6: ₹0 | 0 -->
                            <text x="35" y="190" text-anchor="end" font-size="10.5" fill="#9CA3AF" font-family="inherit">&#8377;0</text>
                            <text x="548" y="190" text-anchor="start" font-size="10.5" fill="#9CA3AF" font-family="inherit">0</text>
                            <line x1="42" y1="186" x2="540" y2="186" stroke="#E5E7EB" stroke-width="1"/>

                            <!-- Orders Vertical Bars (Light Pastel Green #D1FAE5) -->
                            <?= implode("\n                            ", $barElements) ?>

                            <!-- Revenue Area Gradient Fill -->
                            <path d="<?= htmlspecialchars($revAreaPath) ?>" fill="url(#chartRevAreaGrad)"/>

                            <!-- Revenue Smooth Bezier Curve Line (Dark Forest Green #143D24) -->
                            <path d="<?= htmlspecialchars($revLinePath) ?>" 
                                  fill="none" stroke="#143D24" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>

                            <!-- Final Endpoint Prominent Dot -->
                            <circle cx="<?= $lastPointCoords[0] ?>" cy="<?= $lastPointCoords[1] ?>" r="5" fill="#143D24" stroke="#FFFFFF" stroke-width="2"/>

                            <!-- X-Axis Date Labels -->
                            <?= implode("\n                            ", $dateLabels) ?>
                        </svg>
                    </div>
                </div>

                <!-- 2. Action Center -->
                <div class="replica-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <h2 class="section-card-title"><a href="orders.php" style="color: inherit; text-decoration: none;">Action Center &rarr;</a></h2>
                        <a href="orders.php" style="color: #9CA3AF; text-decoration: none; font-size: 16px; font-weight: 600;">&rsaquo;</a>
                    </div>

                    <div>
                        <!-- 1. Orders awaiting fulfillment -->
                        <a href="orders.php?tab=PROCESSING" class="action-row-item">
                            <div class="action-row-left">
                                <div class="action-icon-square action-icon-green">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                    </svg>
                                </div>
                                <span class="action-count-bold"><?= (int)($actionAlerts[0]['count'] ?? 0) ?></span>
                                <span class="action-label-text">Orders awaiting fulfillment</span>
                            </div>
                            <span class="action-chevron-right">&rsaquo;</span>
                        </a>

                        <!-- 2. Low stock products -->
                        <a href="inventory.php?filter=low_stock" class="action-row-item">
                            <div class="action-row-left">
                                <div class="action-icon-square action-icon-amber">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                        <line x1="12" y1="9" x2="12" y2="13"></line>
                                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                    </svg>
                                </div>
                                <span class="action-count-bold"><?= (int)($actionAlerts[1]['count'] ?? 0) ?></span>
                                <span class="action-label-text">Low stock products</span>
                            </div>
                            <span class="action-chevron-right">&rsaquo;</span>
                        </a>

                        <!-- 3. Payment failures -->
                        <a href="orders.php?payment_status=UNPAID" class="action-row-item">
                            <div class="action-row-left">
                                <div class="action-icon-square action-icon-green">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                        <line x1="1" y1="10" x2="23" y2="10"></line>
                                    </svg>
                                </div>
                                <span class="action-count-bold"><?= (int)($actionAlerts[2]['count'] ?? 0) ?></span>
                                <span class="action-label-text">Payment failures</span>
                            </div>
                            <span class="action-chevron-right">&rsaquo;</span>
                        </a>

                        <!-- 4. Delayed shipments -->
                        <a href="orders.php?delayed=1" class="action-row-item">
                            <div class="action-row-left">
                                <div class="action-icon-square action-icon-green">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="1" y="3" width="15" height="13"></rect>
                                        <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                    </svg>
                                </div>
                                <span class="action-count-bold"><?= (int)($actionAlerts[3]['count'] ?? 0) ?></span>
                                <span class="action-label-text">Delayed shipments</span>
                            </div>
                            <span class="action-chevron-right">&rsaquo;</span>
                        </a>
                    </div>
                </div>

                <!-- 3. Order Fulfillment Donut Chart -->
                <div class="replica-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <h2 class="section-card-title"><a href="orders.php" style="color: inherit; text-decoration: none;">Order Fulfillment &rarr;</a></h2>
                        <a href="orders.php" style="color: #9CA3AF; text-decoration: none; font-size: 16px; font-weight: 600;">&rsaquo;</a>
                    </div>

                    <div class="fulfillment-body">
                        <!-- High Precision SVG Donut -->
                        <div class="donut-wrap" onclick="window.location.href='orders.php'" style="cursor: pointer;" title="View Orders Pipeline">
                            <svg width="140" height="140" viewBox="0 0 140 140">
                                <!-- Radius 52, Circumference = 2 * PI * 52 ≈ 326.73 -->
                                <circle cx="70" cy="70" r="52" fill="none" stroke="#E5E7EB" stroke-width="15" />
                                
                                <?php if ($pipeTotal > 0): ?>
                                <!-- Delivered (Dark Forest Green #143D24) -->
                                <circle cx="70" cy="70" r="52" fill="none" stroke="#143D24" stroke-width="15"
                                        stroke-dasharray="<?= $dashDelivered ?> <?= $circumference ?>" stroke-dashoffset="<?= $offsetDelivered ?>" transform="rotate(-90 70 70)" />

                                <!-- Preparing (Medium Green #4ADE80) -->
                                <circle cx="70" cy="70" r="52" fill="none" stroke="#4ADE80" stroke-width="15"
                                        stroke-dasharray="<?= $dashPreparing ?> <?= $circumference ?>" stroke-dashoffset="<?= $offsetPreparing ?>" transform="rotate(-90 70 70)" />

                                <!-- Dispatching (Soft Green #BBF7D0) -->
                                <circle cx="70" cy="70" r="52" fill="none" stroke="#BBF7D0" stroke-width="15"
                                        stroke-dasharray="<?= $dashDispatching ?> <?= $circumference ?>" stroke-dashoffset="<?= $offsetDispatching ?>" transform="rotate(-90 70 70)" />

                                <!-- Pending (Light Pale Grey #D1D5DB) -->
                                <circle cx="70" cy="70" r="52" fill="none" stroke="#D1D5DB" stroke-width="15"
                                        stroke-dasharray="<?= $dashPending ?> <?= $circumference ?>" stroke-dashoffset="<?= $offsetPending ?>" transform="rotate(-90 70 70)" />
                                <?php endif; ?>
                            </svg>

                            <div class="donut-center-info">
                                <div class="donut-center-number"><?= number_format($pipeTotal) ?></div>
                                <div class="donut-center-label">Total Orders</div>
                            </div>
                        </div>

                        <!-- Legend List -->
                        <div class="fulfillment-legend-list">
                            <a href="orders.php?tab=FULFILLED" class="legend-row" style="text-decoration: none; color: inherit; cursor: pointer;" title="Filter Fulfilled Orders">
                                <div class="legend-dot-label">
                                    <span class="legend-dot" style="background: #143D24;"></span>
                                    <span>Delivered</span>
                                </div>
                                <span class="legend-value-bold"><?= $pipeDeliveredPct ?>%</span>
                            </a>

                            <a href="orders.php?tab=PROCESSING" class="legend-row" style="text-decoration: none; color: inherit; cursor: pointer;" title="Filter Processing Orders">
                                <div class="legend-dot-label">
                                    <span class="legend-dot" style="background: #4ADE80;"></span>
                                    <span>Preparing</span>
                                </div>
                                <span class="legend-value-bold"><?= $pipePreparingPct ?>%</span>
                            </a>

                            <a href="orders.php?tab=DISPATCHED" class="legend-row" style="text-decoration: none; color: inherit; cursor: pointer;" title="Filter Dispatched Orders">
                                <div class="legend-dot-label">
                                    <span class="legend-dot" style="background: #BBF7D0;"></span>
                                    <span>Dispatching</span>
                                </div>
                                <span class="legend-value-bold"><?= $pipeDispatchingPct ?>%</span>
                            </a>

                            <a href="orders.php?tab=PENDING" class="legend-row" style="text-decoration: none; color: inherit; cursor: pointer;" title="Filter Pending Orders">
                                <div class="legend-dot-label">
                                    <span class="legend-dot" style="background: #D1D5DB;"></span>
                                    <span>Pending</span>
                                </div>
                                <span class="legend-value-bold"><?= $pipePendingPct ?>%</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ROW 3: Bottom Section (Top Products + Top Categories + Recent Orders) -->
            <div class="bottom-tri-grid">
                <!-- 1. Top Selling Products -->
                <div class="replica-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <h2 class="section-card-title">Top Selling Products</h2>
                        <a href="products.php" style="font-size: 13px; font-weight: 500; color: #111827; text-decoration: none;">View All &rarr;</a>
                    </div>

                    <div>
                        <?php 
                        $topProdsList = array_slice($insights['top_products'], 0, 3);
                        if (empty($topProdsList)): 
                        ?>
                            <div style="padding: 24px 0; text-align: center; color: #9CA3AF; font-size: 13px;">
                                No product sales recorded in this period.
                            </div>
                        <?php else: ?>
                            <?php foreach ($topProdsList as $tp): ?>
                            <div class="mock-product-row" onclick="window.location.href='products.php?search=<?= urlencode($tp['title']) ?>'" title="View <?= htmlspecialchars($tp['title']) ?> in Catalog">
                                <div class="mock-product-left">
                                    <div class="mock-product-thumb">
                                        <?php if (!empty($tp['image_url']) && file_exists(__DIR__ . '/../' . ltrim($tp['image_url'], '/'))): ?>
                                            <img src="../<?= htmlspecialchars(ltrim($tp['image_url'], '/')) ?>" alt="<?= htmlspecialchars($tp['title']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
                                        <?php else: ?>
                                            <!-- Botanical Jar Illustration matching the mockup -->
                                            <svg width="34" height="34" viewBox="0 0 40 40" fill="none">
                                                <rect x="10" y="6" width="20" height="5" rx="1.5" fill="#4B5563"/>
                                                <rect x="8" y="11" width="24" height="23" rx="3" fill="#143D24"/>
                                                <rect x="9" y="15" width="22" height="15" fill="#ECFDF5"/>
                                                <text x="20" y="24" text-anchor="middle" font-size="6" font-weight="bold" fill="#143D24" font-family="sans-serif"><?= htmlspecialchars(substr($tp['title'], 0, 4)) ?></text>
                                                <line x1="13" y1="26" x2="27" y2="26" stroke="#10B981" stroke-width="0.8"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="mock-product-name"><?= htmlspecialchars($tp['title']) ?></div>
                                        <div class="mock-product-sold"><?= number_format($tp['units_sold']) ?> sold</div>
                                    </div>
                                </div>
                                <div class="mock-product-rev"><?= Context::formatMoney((int)$tp['total_revenue']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Top Categories -->
                <div class="replica-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <h2 class="section-card-title"><a href="categories.php" style="color: inherit; text-decoration: none;">Top Categories &rarr;</a></h2>
                        <a href="categories.php" style="font-size: 13px; font-weight: 500; color: #111827; text-decoration: none;">View All &rarr;</a>
                    </div>

                    <div>
                        <?php 
                        $topCatsList = array_slice($insights['top_categories'], 0, 4);
                        $totalCatRev = max(1, array_sum(array_column($topCatsList, 'total_revenue')));
                        if (empty($topCatsList)):
                        ?>
                            <div style="padding: 24px 0; text-align: center; color: #9CA3AF; font-size: 13px;">
                                No category sales recorded in this period.
                            </div>
                        <?php else: ?>
                            <?php 
                            $catIcons = [
                                '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"></path><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"></path>',
                                '<path d="M2.27 21.7s9.87-3.5 12.73-6.36a4.5 4.5 0 0 0-6.36-6.37C5.77 11.84 2.27 21.7 2.27 21.7zM16 8l5-5M17 5l4 4"></path>',
                                '<path d="M7 20h10"></path><path d="M10 20c0-3 1.5-6 2-10"></path><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-1.5.4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-2-3.2.2-1.2 1.6-1.5 2.7-1.1 1 .4 1.5.7 1.8.9z"></path><path d="M14.1 6c-1.1.8-1.8 2.2-2.3 3.7 1.5.4 3.5.4 4.8-.3 1.2-.6 2.3-1.9 2-3.2-.2-1.2-1.6-1.5-2.7-1.1-1 .4-1.5.7-1.8.9z"></path>',
                                '<path d="M12 20.94c1.5 0 2.75 1.06 4 1.06 3 0 6-8 6-12.22A4.91 4.91 0 0 0 17 5c-2.22 0-4 1.44-5 2-1-.56-2.78-2-5-2a4.9 4.9 0 0 0-5 4.78C2 14 5 22 8 22c1.25 0 2.5-1.06 4-1.06Z"></path><path d="M10 2c1 .5 2 2 2 5"></path>'
                            ];
                            foreach ($topCatsList as $cIdx => $tc): 
                                $catPct = round(($tc['total_revenue'] / $totalCatRev) * 100);
                                $iconPath = $catIcons[$cIdx % count($catIcons)];
                            ?>
                            <div class="mock-category-row" onclick="window.location.href='categories.php'" title="Manage Categories">
                                <div class="mock-category-left">
                                    <div class="mock-category-icon">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <?= $iconPath ?>
                                        </svg>
                                    </div>
                                    <span class="mock-category-title"><?= htmlspecialchars($tc['category_name']) ?></span>
                                </div>
                                <div class="mock-category-progress-track">
                                    <div class="mock-category-progress-fill" style="width: <?= $catPct ?>%;"></div>
                                </div>
                                <span class="mock-category-pct"><?= $catPct ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. Recent Orders -->
                <div class="replica-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                        <h2 class="section-card-title"><a href="orders.php" style="color: inherit; text-decoration: none;">Recent Orders &rarr;</a></h2>
                        <a href="orders.php" style="font-size: 13px; font-weight: 500; color: #111827; text-decoration: none;">View All &rarr;</a>
                    </div>

                    <table class="mock-orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th style="text-align: right;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #9CA3AF; padding: 24px; cursor: pointer;" onclick="window.location.href='orders.php'">No recent orders found. Click to view all orders.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $ro): 
                                    $statusPillClass = 'pill-delivered';
                                    $statusLabel = 'Delivered';
                                    $st = strtoupper($ro['order_status']);
                                    if ($st === 'PROCESSING') {
                                        $statusPillClass = 'pill-preparing';
                                        $statusLabel = 'Preparing';
                                    } elseif (in_array($st, ['DISPATCHED', 'READY_TO_SHIP'], true)) {
                                        $statusPillClass = 'pill-dispatching';
                                        $statusLabel = 'Dispatching';
                                    } elseif (in_array($st, ['PENDING', 'PENDING_PAYMENT'], true)) {
                                        $statusPillClass = 'pill-dispatching';
                                        $statusLabel = 'Pending';
                                    } elseif ($st === 'CANCELLED') {
                                        $statusPillClass = 'pill-dispatching';
                                        $statusLabel = 'Cancelled';
                                    }
                                ?>
                                <tr onclick="window.location.href='orders.php?search=<?= urlencode($ro['order_number']) ?>'" title="View Order #<?= htmlspecialchars($ro['order_number']) ?>">
                                    <td><a href="orders.php?search=<?= urlencode($ro['order_number']) ?>" style="font-weight: 600; color: #111827; text-decoration: none;">#<?= htmlspecialchars($ro['order_number']) ?></a></td>
                                    <td><?= htmlspecialchars($ro['customer_name'] ?? 'Guest Customer') ?></td>
                                    <td style="font-weight: 600;"><?= Context::formatMoney((int)$ro['grand_total_amount']) ?></td>
                                    <td style="text-align: right;">
                                        <span class="order-status-pill <?= $statusPillClass ?>"><?= $statusLabel ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- Client-Side Drawer & Popover Interactivity -->
    <script>
        function toggleRangeMenu() {
            const menu = document.getElementById('rangePopoverMenu');
            if (menu) {
                menu.classList.toggle('active');
            }
        }

        // Close on outside click
        document.addEventListener('click', function(e) {
            const btn = document.getElementById('dateRangeBtn');
            const menu = document.getElementById('rangePopoverMenu');
            if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('active');
            }
        });
    </script>
</body>
</html>
