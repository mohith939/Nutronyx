<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\InventoryService;

AuthService::requireAuth();

$activePage = 'inventory';
$activeWebsite = Context::getWebsite();
$successMsg = '';
$errorMsg = '';

// Handle Adjust Stock POST Action
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    try {
        $variantId = trim($_POST['variant_id'] ?? '');
        $delta = (int)($_POST['delta_quantity'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Manual stock adjustment');
        $reference = trim($_POST['reference_number'] ?? '');
        $adjType = $_POST['adjustment_type'] ?? 'increase';

        if ($adjType === 'decrease') {
            $delta = -abs($delta);
        } elseif ($adjType === 'increase') {
            $delta = abs($delta);
        }

        if (empty($variantId)) {
            throw new \InvalidArgumentException('Please select a product variant to adjust.');
        }

        InventoryService::adjustStock($variantId, $delta, $reason, $reference ?: null);
        $successMsg = "Stock successfully adjusted by " . ($delta > 0 ? "+{$delta}" : "{$delta}") . " units.";
    } catch (\Throwable $e) {
        $errorMsg = 'Error adjusting stock: ' . $e->getMessage();
    }
}

// Handle Reconcile Stock POST Action
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reconcile_stock') {
    try {
        $variantId = trim($_POST['variant_id'] ?? '');
        $physicalCount = (int)($_POST['physical_quantity'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Physical stock count reconciliation');
        $approvedBy = trim($_POST['approved_by'] ?? 'Warehouse Supervisor');

        $recRes = InventoryService::reconcileStock($variantId, $physicalCount, $reason, $approvedBy);
        $successMsg = "Reconciliation completed. Discrepancy of {$recRes['discrepancy']} units applied to ledger.";
    } catch (\Throwable $e) {
        $errorMsg = 'Error reconciling stock: ' . $e->getMessage();
    }
}

// Handle Bulk Stock Update POST Action
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    try {
        $variantIds = isset($_POST['selected_variant_ids']) ? explode(',', $_POST['selected_variant_ids']) : [];
        $variantIds = array_filter(array_map('trim', $variantIds));
        $bulkOp = $_POST['bulk_operation'] ?? 'adjust';

        if (empty($variantIds)) {
            throw new \InvalidArgumentException('Please select at least one item.');
        }

        if ($bulkOp === 'adjust') {
            $delta = (int)($_POST['bulk_delta'] ?? 0);
            $reason = trim($_POST['bulk_reason'] ?? 'Bulk stock adjustment');
            $cnt = InventoryService::bulkAdjustStock($variantIds, $delta, $reason);
            $successMsg = "Successfully adjusted stock for {$cnt} item(s).";
        } elseif ($bulkOp === 'threshold') {
            $threshold = (int)($_POST['bulk_threshold'] ?? 20);
            $cnt = InventoryService::bulkUpdateThreshold($variantIds, $threshold);
            $successMsg = "Successfully updated low-stock threshold for {$cnt} item(s).";
        }
    } catch (\Throwable $e) {
        $errorMsg = 'Error in bulk update: ' . $e->getMessage();
    }
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $selectedIds = !empty($_GET['ids']) ? explode(',', $_GET['ids']) : null;
    InventoryService::exportInventoryCsv($selectedIds);
    exit;
}

// Handle AJAX Product Details
if (isset($_GET['ajax_product_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $details = InventoryService::getProductInventoryDetails($_GET['ajax_product_id']);
    echo json_encode($details ?: ['error' => 'Product not found']);
    exit;
}

// Query parameters
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? null;
$stockStatus = $_GET['stock_status'] ?? null;
$stockHealth = $_GET['stock_health'] ?? null;
$tab = $_GET['tab'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$inventoryData = InventoryService::getInventoryList(
    $search,
    $category,
    $stockStatus,
    $stockHealth,
    $tab,
    $page,
    $perPage
);
$inventoryItems = $inventoryData['items'];
$tabCounts = $inventoryData['tab_counts'];

// Selected product for Right Details Pane (only if explicitly specified via ?product_id=)
$selectedProductId = !empty($_GET['product_id']) ? trim((string)$_GET['product_id']) : null;
$activeProductDetails = $selectedProductId ? InventoryService::getProductInventoryDetails($selectedProductId) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory & Stock &mdash; Nutronyx Admin Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body inventory-page-layout">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="admin-layout-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main-stage">

            <!-- Alerts -->
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

            <!-- Page Header (Matching Mockup) -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 style="font-family: 'Playfair Display', Georgia, serif; font-size: 28px; font-weight: 700; color: #111827; margin: 0; letter-spacing: -0.02em;">Inventory & Stock</h1>
                    <p style="font-size: 13.5px; color: #6B7280; margin-top: 4px; margin-bottom: 0;">Track stock levels, manage adjustments, and keep your inventory accurate.</p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn-bulk-item" onclick="openAdjustModal('')" style="font-weight: 600; padding: 8px 14px; font-size: 13px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 10 12 15 17 10"></polyline><polyline points="17 14 12 9 7 14"></polyline></svg>
                        <span>Adjust Stock</span>
                    </button>
                    <button type="button" class="btn-bulk-item" onclick="openBulkUpdateModal()" style="font-weight: 600; padding: 8px 14px; font-size: 13px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                        <span>Bulk Update</span>
                    </button>
                    <button type="button" class="btn-bulk-item" onclick="openReportsModal()" style="font-weight: 600; padding: 8px 14px; font-size: 13px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                        <span>Reports</span>
                    </button>
                </div>
            </div>

            <!-- Tab Navigation Bar (Matching Mockup) -->
            <div class="order-tabs-bar" style="margin-bottom: 16px;">
                <a href="?tab=all" class="order-tab-btn <?= ($tab === 'all' || empty($tab)) ? 'active' : '' ?>">
                    <span>All Inventory</span>
                    <span class="order-tab-counter"><?= $tabCounts['all'] ?></span>
                </a>
                <a href="?tab=low_stock" class="order-tab-btn <?= ($tab === 'low_stock') ? 'active' : '' ?>">
                    <span>Low Stock</span>
                    <span class="order-tab-counter" style="background: #FEF3C7; color: #92400E;"><?= $tabCounts['low_stock'] ?></span>
                </a>
                <a href="?tab=out_of_stock" class="order-tab-btn <?= ($tab === 'out_of_stock') ? 'active' : '' ?>">
                    <span>Out of Stock</span>
                    <span class="order-tab-counter" style="background: #FEE2E2; color: #991B1B;"><?= $tabCounts['out_of_stock'] ?></span>
                </a>
                <a href="?tab=needs_reorder" class="order-tab-btn <?= ($tab === 'needs_reorder') ? 'active' : '' ?>">
                    <span>Needs Reorder</span>
                    <span class="order-tab-counter"><?= $tabCounts['needs_reorder'] ?></span>
                </a>
            </div>

            <!-- Filter Toolbar (Matching Mockup) -->
            <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px; padding: 14px 18px; margin-bottom: 16px;">
                <form method="GET" action="inventory.php" id="inventoryFilterForm" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

                    <!-- Search Input -->
                    <div style="position: relative; flex: 1; min-width: 240px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="search" placeholder="Search by product name, SKU or variant..." value="<?= htmlspecialchars($search) ?>" style="width: 100%; padding: 8px 12px 8px 36px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 13px; color: #111827; outline: none;">
                    </div>

                    <!-- Category Filter -->
                    <div style="min-width: 150px;">
                        <select name="category" onchange="document.getElementById('inventoryFilterForm').submit()" style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 13px; color: #374151; background: #FFFFFF; outline: none;">
                            <option value="">Category: All Categories</option>
                            <option value="cat_wellness" <?= ($category === 'cat_wellness') ? 'selected' : '' ?>>Daily Wellness</option>
                            <option value="cat_protein" <?= ($category === 'cat_protein') ? 'selected' : '' ?>>Botanical Powders</option>
                            <option value="cat_lifestyle" <?= ($category === 'cat_lifestyle') ? 'selected' : '' ?>>Retail Cases</option>
                        </select>
                    </div>

                    <!-- Stock Status Filter -->
                    <div style="min-width: 140px;">
                        <select name="stock_status" onchange="document.getElementById('inventoryFilterForm').submit()" style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 13px; color: #374151; background: #FFFFFF; outline: none;">
                            <option value="">Stock Status: All Status</option>
                            <option value="in_stock" <?= ($stockStatus === 'in_stock') ? 'selected' : '' ?>>In Stock</option>
                            <option value="out_of_stock" <?= ($stockStatus === 'out_of_stock') ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>

                    <!-- Stock Health Filter -->
                    <div style="min-width: 140px;">
                        <select name="stock_health" onchange="document.getElementById('inventoryFilterForm').submit()" style="width: 100%; padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 13px; color: #374151; background: #FFFFFF; outline: none;">
                            <option value="">Stock Health: All Health</option>
                            <option value="healthy" <?= ($stockHealth === 'healthy') ? 'selected' : '' ?>>Healthy</option>
                            <option value="low_stock" <?= ($stockHealth === 'low_stock') ? 'selected' : '' ?>>Low Stock</option>
                            <option value="out_of_stock" <?= ($stockHealth === 'out_of_stock') ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>

                    <!-- More Filters / Export Button -->
                    <a href="inventory.php?export=csv" class="btn-bulk-item" style="padding: 8px 14px; font-size: 13px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                        <span>More Filters</span>
                    </a>

                    <?php if ($search || $category || $stockStatus || $stockHealth || $tab !== 'all'): ?>
                        <a href="inventory.php" style="font-size: 13px; color: #DC2626; text-decoration: none; font-weight: 500; margin-left: 4px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Bulk Actions Bar (Matching Mockup) -->
            <div class="order-bulk-bar" id="inventoryBulkBar" style="display: none;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" class="nutronyx-checkbox" id="bulkSelectAllToggle" onchange="toggleAllInventoryCheckboxes(this.checked)">
                    <span class="bulk-selected-pill" id="selectedCountPill">0 selected</span>
                </div>

                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn-bulk-item" onclick="openBulkAdjustFromSelection()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 10 12 15 17 10"></polyline><polyline points="17 14 12 9 7 14"></polyline></svg>
                        <span>Adjust Stock</span>
                    </button>

                    <button type="button" class="btn-bulk-item" onclick="openBulkUpdateModal()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                        <span>Bulk Update</span>
                    </button>

                    <button type="button" class="btn-bulk-item" onclick="exportSelectedInventory()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <span>Export</span>
                    </button>

                    <button type="button" class="btn-bulk-item btn-bulk-cancel" onclick="clearSelectedCheckboxes()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        <span>Delete</span>
                    </button>
                </div>

                <div style="display: flex; align-items: center; gap: 4px;">
                    <button type="button" class="page-num-btn active" style="width: 32px; height: 32px;" title="Table View">☰</button>
                    <button type="button" class="page-num-btn" style="width: 32px; height: 32px;" title="Grid View">⊞</button>
                </div>
            </div>

            <!-- Split-Screen Workspace (Master Directory Table + Right Detail Column) -->
            <div class="inventory-split-layout">
                
                <!-- Master Column: Inventory Directory Table -->
                <div class="inventory-master-column">
                    <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                        <div class="table-responsive">
                            <table class="dash-table" style="margin: 0;">
                                <thead>
                                    <tr style="background: #F9FAFB; border-bottom: 1px solid #E5E7EB;">
                                        <th style="width: 38px; padding-left: 16px;">
                                            <input type="checkbox" class="nutronyx-checkbox" id="selectAllMaster" onchange="toggleAllInventoryCheckboxes(this.checked)">
                                        </th>
                                        <th>Product / Variant</th>
                                        <th>SKU</th>
                                        <th>On Hand</th>
                                        <th>Reserved</th>
                                        <th>Available</th>
                                        <th>Stock Health</th>
                                        <th>Status</th>
                                        <th style="text-align: right; padding-right: 16px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($inventoryItems)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; padding: 48px 20px; color: #6B7280;">
                                                <div style="font-size: 15px; font-weight: 600; color: #374151;">No inventory records found</div>
                                                <p style="font-size: 13px; margin-top: 4px;">No products match your filter parameters.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $rowIdx = 0;
                                        foreach ($inventoryItems as $it): 
                                            $rowIdx++;
                                            $isSelected = ($it['product_id'] === $selectedProductId);
                                            $isChecked = false;
                                        ?>
                                            <tr class="inventory-table-row <?= $isSelected ? 'selected-inventory-row' : '' ?>" 
                                                data-product-id="<?= htmlspecialchars($it['product_id']) ?>" 
                                                onclick="if (!event.target.closest('input') && !event.target.closest('button') && !event.target.closest('a')) selectInventoryProduct('<?= htmlspecialchars($it['product_id']) ?>')">
                                                
                                                <td style="padding-left: 16px;" onclick="event.stopPropagation()">
                                                    <input type="checkbox" class="nutronyx-checkbox inventory-row-checkbox" value="<?= htmlspecialchars($it['variant_id']) ?>" data-product-id="<?= htmlspecialchars($it['product_id']) ?>" <?= $isChecked ? 'checked' : '' ?> onchange="updateInventoryBulkBar()">
                                                </td>
                                                <td>
                                                    <div style="display: flex; align-items: center; gap: 12px;">
                                                        <img src="../<?= htmlspecialchars($it['product_image']) ?>" alt="<?= htmlspecialchars($it['product_title']) ?>" style="width: 36px; height: 36px; border-radius: 6px; object-fit: cover; background: #F3F4F6; border: 1px solid #E5E7EB; flex-shrink: 0;" onerror="this.src='../assets/images/product-abc.jpg'">
                                                        <div>
                                                            <div style="font-weight: 600; color: #111827; font-size: 13.5px;">
                                                                <?= htmlspecialchars($it['product_title']) ?>
                                                            </div>
                                                            <div style="font-size: 12px; color: #6B7280;">
                                                                <?= htmlspecialchars($it['variant_title']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="font-size: 13px; color: #374151; font-weight: 500;">
                                                    <?= htmlspecialchars($it['sku']) ?>
                                                </td>
                                                <td style="font-weight: 600; color: #111827; font-size: 13.5px;">
                                                    <?= number_format((int)$it['on_hand']) ?>
                                                </td>
                                                <td style="font-weight: 500; color: #6B7280; font-size: 13.5px;">
                                                    <?= number_format((int)$it['reserved']) ?>
                                                </td>
                                                <td style="font-weight: 600; color: #144D29; font-size: 13.5px;">
                                                    <?= number_format((int)$it['available']) ?>
                                                </td>
                                                <td>
                                                    <span class="stock-health-pill <?= $it['health_class'] ?>">
                                                        <span class="dot"></span>
                                                        <span><?= htmlspecialchars($it['stock_health']) ?></span>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="stock-status-badge <?= $it['status_class'] ?>">
                                                        <?= htmlspecialchars($it['status_badge']) ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: right; padding-right: 16px;" onclick="event.stopPropagation()">
                                                    <div style="position: relative; display: inline-block;">
                                                        <button type="button" class="btn-bulk-item" onclick="toggleRowMenu('inv-menu-<?= htmlspecialchars($it['variant_id']) ?>')" style="padding: 4px 8px; border-radius: 6px;">
                                                            •••
                                                        </button>
                                                        <div id="inv-menu-<?= htmlspecialchars($it['variant_id']) ?>" class="inventory-row-dropdown" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 4px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); width: 170px; z-index: 50; text-align: left; padding: 4px 0;">
                                                            <button type="button" onclick="openAdjustModal('<?= htmlspecialchars($it['variant_id']) ?>')" style="width: 100%; padding: 7px 12px; border: none; background: none; font-size: 12.5px; text-align: left; color: #374151; cursor: pointer;">⇅ Adjust Stock</button>
                                                            <a href="products.php?search=<?= urlencode($it['product_title']) ?>" style="display: block; padding: 7px 12px; font-size: 12.5px; color: #374151; text-decoration: none;">View in Products &rarr;</a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Footer Pagination (Matching Mockup) -->
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-top: 1px solid #E5E7EB; font-size: 13px; color: #6B7280; flex-wrap: wrap; gap: 10px;">
                            <div>
                                Showing <?= $inventoryData['from'] ?>–<?= $inventoryData['to'] ?> of <?= number_format($inventoryData['total_count']) ?> items
                            </div>

                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div>
                                    Rows per page
                                    <select style="border: 1px solid #D1D5DB; border-radius: 6px; padding: 3px 6px; font-size: 12px; margin-left: 4px; background: #FFFFFF;">
                                        <option value="10">10 ⌄</option>
                                        <option value="25">25 ⌄</option>
                                        <option value="50">50 ⌄</option>
                                    </select>
                                </div>

                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <a href="?page=<?= max(1, $page - 1) ?>" class="page-num-btn" style="text-decoration: none;">&lt;</a>
                                    <a href="?page=1" class="page-num-btn <?= ($page === 1) ? 'active' : '' ?>" style="text-decoration: none;">1</a>
                                    <a href="?page=2" class="page-num-btn <?= ($page === 2) ? 'active' : '' ?>" style="text-decoration: none;">2</a>
                                    <a href="?page=3" class="page-num-btn <?= ($page === 3) ? 'active' : '' ?>" style="text-decoration: none;">3</a>
                                    <span>...</span>
                                    <a href="?page=18" class="page-num-btn <?= ($page === 18) ? 'active' : '' ?>" style="text-decoration: none;">18</a>
                                    <a href="?page=<?= min(18, $page + 1) ?>" class="page-num-btn" style="text-decoration: none;">&gt;</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Detail Column: Product Inventory Workspace (Matching Mockup) -->
                <div class="inventory-detail-column" id="inventoryDetailWorkspace" style="<?= empty($activeProductDetails) ? 'display: none;' : '' ?>">
                    <?php if (!empty($activeProductDetails)): 
                        $prod = $activeProductDetails['product'];
                        $so = $activeProductDetails['stock_overview'];
                        $vars = $activeProductDetails['variants'];
                        $movs = $activeProductDetails['movements'];
                        $ords = $activeProductDetails['orders'];
                        $val = $activeProductDetails['valuation'];
                    ?>
                        <!-- Header -->
                        <div class="inv-profile-header">
                            <div class="inv-header-info">
                                <img src="../<?= htmlspecialchars($prod['image_url']) ?>" alt="<?= htmlspecialchars($prod['title']) ?>" id="dtProductImg" class="inv-product-thumb" onerror="this.src='../assets/images/product-abc.jpg'">
                                <div>
                                    <h3 class="inv-header-title" id="dtProductTitle"><?= htmlspecialchars($prod['title']) ?></h3>
                                    <p class="inv-header-sku">
                                        SKU: <span id="dtProductSku"><?= htmlspecialchars($activeProductDetails['primary_sku']) ?></span>
                                    </p>
                                    <div style="margin-top: 4px;">
                                        <span class="stock-status-badge badge-in" id="dtProductStatus" style="font-size: 10.5px; padding: 1px 7px;">
                                            ● Active
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="btn-bulk-item" onclick="closeInventoryWorkspace()" title="Close details panel" style="padding: 6px 10px;">✕</button>
                        </div>

                        <!-- Workspace Tabs -->
                        <div class="cust-workspace-tabs" style="margin-top: 8px;">
                            <button type="button" class="cust-workspace-tab-btn active" onclick="switchInvTab('inventory', this)">Inventory</button>
                            <button type="button" class="cust-workspace-tab-btn" onclick="switchInvTab('orders', this)">
                                <span>Orders</span>
                                <span class="cust-tab-badge" id="dtTabOrdersCount"><?= count($ords) ?></span>
                            </button>
                            <button type="button" class="cust-workspace-tab-btn" onclick="switchInvTab('movements', this)">
                                <span>Movements</span>
                                <span class="cust-tab-badge" id="dtTabMovementsCount"><?= count($movs) ?></span>
                            </button>
                            <button type="button" class="cust-workspace-tab-btn" onclick="switchInvTab('reports', this)">Reports</button>
                        </div>

                        <!-- TAB 1: INVENTORY (Exact Match to Mockup) -->
                        <div id="inv-pane-inventory" class="inv-tab-pane">
                            
                            <!-- Stock Overview Section -->
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px; margin-bottom: 8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#144D29" stroke-width="2.2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                                <span style="font-size: 14px; font-weight: 700; color: #111827;">Stock Overview</span>
                            </div>

                            <div class="inv-overview-strip">
                                <div class="inv-overview-tile">
                                    <div class="tile-label">On Hand</div>
                                    <div class="tile-value" id="dtOnHand"><?= number_format((int)$so['on_hand']) ?></div>
                                </div>
                                <div class="inv-overview-tile">
                                    <div class="tile-label">Reserved</div>
                                    <div class="tile-value" id="dtReserved"><?= number_format((int)$so['reserved']) ?></div>
                                </div>
                                <div class="inv-overview-tile available-box">
                                    <div class="tile-label">Available</div>
                                    <div class="tile-value" id="dtAvailable"><?= number_format((int)$so['available']) ?></div>
                                </div>
                            </div>

                            <!-- Variants List Section -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="font-size: 14px; font-weight: 700; color: #111827;">Variants (<span id="dtVariantCount"><?= count($vars) ?></span>)</span>
                            </div>

                            <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; overflow: hidden; margin-bottom: 20px;">
                                <table class="dash-table" style="font-size: 12.5px; margin: 0;">
                                    <thead>
                                        <tr style="background: #F9FAFB;">
                                            <th>Variant</th>
                                            <th>SKU</th>
                                            <th>On Hand</th>
                                            <th>Available</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dtVariantsBody">
                                        <?php foreach ($vars as $v): ?>
                                            <tr>
                                                <td style="font-weight: 600; color: #111827;"><?= htmlspecialchars($v['variant_title']) ?></td>
                                                <td style="color: #6B7280; font-family: monospace; font-size: 11.5px;"><?= htmlspecialchars($v['sku']) ?></td>
                                                <td style="font-weight: 600; color: #111827;"><?= number_format((int)$v['on_hand']) ?></td>
                                                <td style="font-weight: 600; color: #144D29;"><?= number_format((int)$v['available']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Quick Actions Section -->
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                <span style="font-size: 14px; font-weight: 700; color: #111827;">Quick Actions</span>
                            </div>

                            <div class="inv-quick-actions-grid">
                                <button type="button" class="btn-inv-action-solid" onclick="openAdjustModalForActiveProduct()">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="7 10 12 15 17 10"></polyline><polyline points="17 14 12 9 7 14"></polyline></svg>
                                    <span>Adjust Stock</span>
                                </button>
                                <button type="button" class="btn-inv-action-outline" onclick="openBulkUpdateModal()">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                                    <span>Update Stock (Bulk)</span>
                                </button>
                                <button type="button" class="btn-inv-action-outline" onclick="switchInvTab('movements')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                                    <span>View Movements</span>
                                </button>
                            </div>

                            <!-- Recent Movements Section (Matching Mockup) -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                    <span style="font-size: 14px; font-weight: 700; color: #111827;">Recent Movements</span>
                                </div>
                                <button type="button" onclick="switchInvTab('movements')" style="border: none; background: none; font-size: 12.5px; font-weight: 600; color: #144D29; cursor: pointer;">View All &rarr;</button>
                            </div>

                            <div class="inv-movement-list" id="dtRecentMovementsList">
                                <?php foreach (array_slice($movs, 0, 3) as $mv): 
                                    $isAdd = ($mv['quantity_delta'] >= 0);
                                    $sign = $isAdd ? '+' : '';
                                ?>
                                    <div class="inv-movement-item">
                                        <div class="inv-mov-left">
                                            <div class="inv-mov-icon <?= $isAdd ? 'added' : 'deducted' ?>">
                                                <?= $isAdd ? '↑' : '↓' ?>
                                            </div>
                                            <div>
                                                <div class="inv-mov-title"><?= $isAdd ? 'Stock Added' : 'Stock Deducted' ?></div>
                                                <div class="inv-mov-desc"><?= htmlspecialchars($mv['reason']) ?></div>
                                            </div>
                                        </div>
                                        <div class="inv-mov-right">
                                            <div class="inv-mov-delta <?= $isAdd ? 'positive' : 'negative' ?>">
                                                <?= $sign ?><?= (int)$mv['quantity_delta'] ?> units
                                            </div>
                                            <div class="inv-mov-time">
                                                <?= date('M j, Y, h:i A', strtotime($mv['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        </div>

                        <!-- TAB 2: ORDERS -->
                        <div id="inv-pane-orders" class="inv-tab-pane" style="display: none;">
                            <div style="font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 12px;">Recent Orders Containing this Product</div>
                            <div id="dtOrdersList">
                                <?php if (empty($ords)): ?>
                                    <p style="font-size: 13px; color: #6B7280;">No orders found for this product.</p>
                                <?php else: ?>
                                    <?php foreach ($ords as $ord): ?>
                                        <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                <div>
                                                    <a href="order-detail.php?id=<?= urlencode($ord['id']) ?>" style="font-weight: 700; color: #144D29; text-decoration: none; font-size: 13.5px;">
                                                        <?= htmlspecialchars($ord['order_number']) ?>
                                                    </a>
                                                    <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">
                                                        <?= date('M d, Y', strtotime($ord['created_at'])) ?> &bull; <?= (int)$ord['quantity'] ?> units
                                                    </div>
                                                </div>
                                                <div style="text-align: right;">
                                                    <div style="font-weight: 700; color: #111827; font-size: 13.5px;">
                                                        ₹<?= number_format((int)$ord['total_amount'] / 100, 0) ?>
                                                    </div>
                                                    <span class="stock-status-badge badge-in" style="font-size: 10px; padding: 1px 6px;">
                                                        <?= htmlspecialchars($ord['fulfillment_status'] ?? 'Delivered') ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- TAB 3: MOVEMENTS -->
                        <div id="inv-pane-movements" class="inv-tab-pane" style="display: none;">
                            <div style="font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 12px;">Complete Stock Movement Ledger</div>
                            <div class="inv-movement-list" id="dtAllMovementsList">
                                <?php foreach ($movs as $mv): 
                                    $isAdd = ($mv['quantity_delta'] >= 0);
                                    $sign = $isAdd ? '+' : '';
                                ?>
                                    <div class="inv-movement-item">
                                        <div class="inv-mov-left">
                                            <div class="inv-mov-icon <?= $isAdd ? 'added' : 'deducted' ?>">
                                                <?= $isAdd ? '↑' : '↓' ?>
                                            </div>
                                            <div>
                                                <div class="inv-mov-title"><?= htmlspecialchars($mv['movement_type']) ?></div>
                                                <div class="inv-mov-desc"><?= htmlspecialchars($mv['reason']) ?></div>
                                                <div style="font-size: 11px; color: #9CA3AF; margin-top: 2px;">
                                                    Actor: <?= htmlspecialchars($mv['actor_email'] ?? 'system') ?>
                                                    <?= !empty($mv['reference_number']) ? ' &bull; Ref: ' . htmlspecialchars($mv['reference_number']) : '' ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="inv-mov-right">
                                            <div class="inv-mov-delta <?= $isAdd ? 'positive' : 'negative' ?>">
                                                <?= $sign ?><?= (int)$mv['quantity_delta'] ?> units
                                            </div>
                                            <div class="inv-mov-time">
                                                <?= date('M j, Y, h:i A', strtotime($mv['created_at'])) ?>
                                            </div>
                                            <div style="font-size: 11px; color: #6B7280; margin-top: 2px;">
                                                Bal: <?= number_format((int)$mv['balance_after']) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- TAB 4: REPORTS -->
                        <div id="inv-pane-reports" class="inv-tab-pane" style="display: none;">
                            <div style="font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 12px;">Stock Valuation & Turnover Metrics</div>
                            <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 14px; margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px solid #E5E7EB;">
                                    <span style="font-size: 13px; color: #6B7280;">Unit Cost Price</span>
                                    <span style="font-size: 13.5px; font-weight: 700; color: #111827;"><?= htmlspecialchars($val['cost_price_formatted']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #E5E7EB;">
                                    <span style="font-size: 13px; color: #6B7280;">On-Hand Stock</span>
                                    <span style="font-size: 13.5px; font-weight: 700; color: #111827;"><?= number_format($val['total_units']) ?> units</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding-top: 8px;">
                                    <span style="font-size: 13px; font-weight: 700; color: #144D29;">Total Stock Valuation</span>
                                    <span style="font-size: 15px; font-weight: 700; color: #144D29;"><?= htmlspecialchars($val['total_valuation_formatted']) ?></span>
                                </div>
                            </div>

                            <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; padding: 14px;">
                                <div style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">Inventory Health Diagnostics</div>
                                <p style="font-size: 12.5px; color: #6B7280; margin: 0; line-height: 1.5;">
                                    Stock level is optimized with <strong><?= number_format((int)$so['available']) ?></strong> available units. Average turnover cycle is approximately 28 days with zero backorder risk.
                                </p>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>

            </div>

        </main>
    </div>

    <!-- Adjust Stock Modal -->
    <div class="modal-overlay" id="adjustStockModal">
        <div class="modal-card" style="max-width: 520px;">
            <div class="modal-header">
                <h3 class="modal-title">Adjust Stock Level</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('adjustStockModal')">&times;</button>
            </div>
            <form method="POST" action="inventory.php">
                <input type="hidden" name="action" value="adjust_stock">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Product Variant *</label>
                        <select name="variant_id" id="modalAdjustVariantSelect" required class="form-control">
                            <?php foreach ($inventoryItems as $it): ?>
                                <option value="<?= htmlspecialchars($it['variant_id']) ?>">
                                    <?= htmlspecialchars($it['product_title']) ?> - <?= htmlspecialchars($it['variant_title']) ?> (<?= htmlspecialchars($it['sku']) ?>) - Curr: <?= (int)$it['on_hand'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Adjustment Type</label>
                            <select name="adjustment_type" class="form-control">
                                <option value="increase">Increase (+ Stock)</option>
                                <option value="decrease">Decrease (- Stock)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Quantity *</label>
                            <input type="number" name="delta_quantity" required min="1" placeholder="e.g. 50" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adjustment Reason *</label>
                        <select name="reason" class="form-control">
                            <option value="Manual adjustment">Manual adjustment</option>
                            <option value="New stock arrival / Purchase receipt">New stock arrival / Purchase receipt</option>
                            <option value="Damaged goods write-off">Damaged goods write-off</option>
                            <option value="Expired stock removal">Expired stock removal</option>
                            <option value="Physical inventory count discrepancy">Physical inventory count discrepancy</option>
                            <option value="Internal usage / Sampling">Internal usage / Sampling</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reference Number (PO, Batch, AWB)</label>
                        <input type="text" name="reference_number" placeholder="e.g. PO-2026-098" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-bulk-item" onclick="window.NutronyxModal.close('adjustStockModal')">Cancel</button>
                    <button type="submit" class="btn-inv-action-solid">Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Update Modal -->
    <div class="modal-overlay" id="bulkUpdateModal">
        <div class="modal-card" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title">Bulk Stock Update</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('bulkUpdateModal')">&times;</button>
            </div>
            <form method="POST" action="inventory.php">
                <input type="hidden" name="action" value="bulk_update">
                <input type="hidden" name="selected_variant_ids" id="bulkModalVariantIds" value="">
                <div class="modal-body">
                    <p style="font-size: 13px; color: #4B5563; margin-top: 0;">Apply batch adjustments to <strong id="bulkSelectedCountText">3 selected items</strong>:</p>

                    <div class="form-group">
                        <label class="form-label">Operation Type</label>
                        <select name="bulk_operation" class="form-control" onchange="toggleBulkOpFields(this.value)">
                            <option value="adjust">Add / Deduct Stock (+ / -)</option>
                            <option value="threshold">Update Low Stock Alert Threshold</option>
                        </select>
                    </div>

                    <div id="bulkAdjustFields">
                        <div class="form-group">
                            <label class="form-label">Quantity Delta (+ to increase, - to decrease)</label>
                            <input type="number" name="bulk_delta" value="50" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Adjustment Reason</label>
                            <input type="text" name="bulk_reason" value="Bulk stock receipt" class="form-control">
                        </div>
                    </div>

                    <div id="bulkThresholdFields" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">New Low-Stock Threshold</label>
                            <input type="number" name="bulk_threshold" value="30" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-bulk-item" onclick="window.NutronyxModal.close('bulkUpdateModal')">Cancel</button>
                    <button type="submit" class="btn-inv-action-solid">Apply to All Selected</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Reports Modal -->
    <div class="modal-overlay" id="inventoryReportsModal">
        <div class="modal-card" style="max-width: 580px;">
            <div class="modal-header">
                <h3 class="modal-title">Inventory Reports & Accounting</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('inventoryReportsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <a href="inventory.php?export=csv" class="inv-overview-tile" style="text-decoration: none; display: block;">
                        <div class="tile-label">Export All Stock</div>
                        <div style="font-weight: 700; color: #144D29; font-size: 14px; margin-top: 4px;">Download CSV &rarr;</div>
                        <div style="font-size: 11px; color: #6B7280; margin-top: 4px;">Full catalogue on-hand, reserved, and threshold report.</div>
                    </a>
                    <a href="inventory.php?tab=low_stock&export=csv" class="inv-overview-tile" style="text-decoration: none; display: block;">
                        <div class="tile-label">Low Stock Reorder</div>
                        <div style="font-weight: 700; color: #B45309; font-size: 14px; margin-top: 4px;">Download CSV &rarr;</div>
                        <div style="font-size: 11px; color: #6B7280; margin-top: 4px;">Procurement replenishment list for warehouse restocking.</div>
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-bulk-item" onclick="window.NutronyxModal.close('inventoryReportsModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Client-Side Interactive Controller -->
    <script>
        let currentActiveProductId = '<?= htmlspecialchars($selectedProductId ?? '') ?>';

        function selectInventoryProduct(productId) {
            currentActiveProductId = productId;

            // Highlight table rows for selected product
            document.querySelectorAll('.inventory-table-row').forEach(r => {
                if (r.getAttribute('data-product-id') === productId) {
                    r.classList.add('selected-inventory-row');
                } else {
                    r.classList.remove('selected-inventory-row');
                }
            });

            // Show drawer
            const workspace = document.getElementById('inventoryDetailWorkspace');
            workspace.style.display = 'flex';

            // Fetch product inventory bundle via AJAX
            fetch('inventory.php?ajax_product_id=' + encodeURIComponent(productId))
                .then(res => res.json())
                .then(data => {
                    if (data.error || !data.product) {
                        console.error(data.error);
                        return;
                    }

                    const p = data.product;
                    const so = data.stock_overview || {};
                    const vars = data.variants || [];
                    const movs = data.movements || [];
                    const ords = data.orders || [];
                    const val = data.valuation || {};

                    // Header
                    document.getElementById('dtProductTitle').textContent = p.title;
                    document.getElementById('dtProductSku').textContent = data.primary_sku || 'NX-SKU-001';
                    const imgElem = document.getElementById('dtProductImg');
                    if (imgElem) {
                        imgElem.src = '../' + (p.image_url || 'assets/images/product-abc.jpg');
                        imgElem.alt = p.title;
                    }

                    // Stock Overview Tiles
                    document.getElementById('dtOnHand').textContent = Number(so.on_hand || 0).toLocaleString();
                    document.getElementById('dtReserved').textContent = Number(so.reserved || 0).toLocaleString();
                    document.getElementById('dtAvailable').textContent = Number(so.available || 0).toLocaleString();

                    // Variants Table
                    document.getElementById('dtVariantCount').textContent = vars.length.toString();
                    let varsHtml = '';
                    vars.forEach(v => {
                        varsHtml += `
                            <tr>
                                <td style="font-weight: 600; color: #111827;">${escapeHtml(v.variant_title)}</td>
                                <td style="color: #6B7280; font-family: monospace; font-size: 11.5px;">${escapeHtml(v.sku)}</td>
                                <td style="font-weight: 600; color: #111827;">${Number(v.on_hand || 0).toLocaleString()}</td>
                                <td style="font-weight: 600; color: #144D29;">${Number(v.available || 0).toLocaleString()}</td>
                            </tr>
                        `;
                    });
                    document.getElementById('dtVariantsBody').innerHTML = varsHtml;

                    // Tab Counts
                    document.getElementById('dtTabOrdersCount').textContent = ords.length.toString();
                    document.getElementById('dtTabMovementsCount').textContent = movs.length.toString();

                    // Recent Movements List (Snippet)
                    let movsHtml = '';
                    movs.slice(0, 3).forEach(m => {
                        const isAdd = (m.quantity_delta >= 0);
                        const sign = isAdd ? '+' : '';
                        const dateStr = new Date(m.created_at).toLocaleString('en-IN', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                        movsHtml += `
                            <div class="inv-movement-item">
                                <div class="inv-mov-left">
                                    <div class="inv-mov-icon ${isAdd ? 'added' : 'deducted'}">
                                        ${isAdd ? '↑' : '↓'}
                                    </div>
                                    <div>
                                        <div class="inv-mov-title">${isAdd ? 'Stock Added' : 'Stock Deducted'}</div>
                                        <div class="inv-mov-desc">${escapeHtml(m.reason || '')}</div>
                                    </div>
                                </div>
                                <div class="inv-mov-right">
                                    <div class="inv-mov-delta ${isAdd ? 'positive' : 'negative'}">
                                        ${sign}${Number(m.quantity_delta || 0)} units
                                    </div>
                                    <div class="inv-mov-time">${escapeHtml(dateStr)}</div>
                                </div>
                            </div>
                        `;
                    });
                    document.getElementById('dtRecentMovementsList').innerHTML = movsHtml;
                })
                .catch(err => console.error(err));
        }

        function closeInventoryWorkspace() {
            document.getElementById('inventoryDetailWorkspace').style.display = 'none';
            document.querySelectorAll('.inventory-table-row').forEach(r => r.classList.remove('selected-inventory-row'));
        }

        function switchInvTab(tabName, btnElem) {
            document.querySelectorAll('.cust-workspace-tab-btn').forEach(btn => btn.classList.remove('active'));
            if (btnElem) {
                btnElem.classList.add('active');
            } else {
                document.querySelectorAll('.cust-workspace-tab-btn').forEach(btn => {
                    if (btn.textContent.trim().toLowerCase().includes(tabName)) btn.classList.add('active');
                });
            }

            document.querySelectorAll('.inv-tab-pane').forEach(p => p.style.display = 'none');
            const target = document.getElementById('inv-pane-' + tabName);
            if (target) target.style.display = 'block';
        }

        function toggleRowMenu(menuId) {
            const menu = document.getElementById(menuId);
            if (!menu) return;
            const isVisible = (menu.style.display === 'block');
            document.querySelectorAll('.inventory-row-dropdown').forEach(m => m.style.display = 'none');
            menu.style.display = isVisible ? 'none' : 'block';
        }

        // Checkbox & Bulk Actions
        function toggleAllInventoryCheckboxes(checked) {
            document.querySelectorAll('.inventory-row-checkbox').forEach(cb => cb.checked = checked);
            updateInventoryBulkBar();
        }

        function clearSelectedCheckboxes() {
            document.querySelectorAll('.inventory-row-checkbox').forEach(cb => cb.checked = false);
            updateInventoryBulkBar();
        }

        function updateInventoryBulkBar() {
            const checked = document.querySelectorAll('.inventory-row-checkbox:checked');
            const bulkBar = document.getElementById('inventoryBulkBar');
            const pill = document.getElementById('selectedCountPill');

            if (checked.length > 0) {
                bulkBar.style.display = 'flex';
                pill.textContent = checked.length + ' selected';
            } else {
                bulkBar.style.display = 'none';
            }
        }

        function openBulkUpdateModal() {
            const checked = document.querySelectorAll('.inventory-row-checkbox:checked');
            const ids = Array.from(checked).map(c => c.value);
            document.getElementById('bulkModalVariantIds').value = ids.join(',');
            document.getElementById('bulkSelectedCountText').textContent = (ids.length > 0 ? ids.length : 'all') + ' selected item(s)';
            window.NutronyxModal.open('bulkUpdateModal');
        }

        function openBulkAdjustFromSelection() {
            openBulkUpdateModal();
        }

        function openBulkReconcileFromSelection() {
            window.NutronyxModal.open('reconcileStockModal');
        }

        function exportSelectedInventory() {
            const checked = document.querySelectorAll('.inventory-row-checkbox:checked');
            const ids = Array.from(checked).map(c => c.value);
            window.location.href = 'inventory.php?export=csv' + (ids.length > 0 ? '&ids=' + encodeURIComponent(ids.join(',')) : '');
        }

        function toggleBulkOpFields(op) {
            document.getElementById('bulkAdjustFields').style.display = (op === 'adjust') ? 'block' : 'none';
            document.getElementById('bulkThresholdFields').style.display = (op === 'threshold') ? 'block' : 'none';
        }

        function openAdjustModal(variantId) {
            if (variantId) {
                document.getElementById('modalAdjustVariantSelect').value = variantId;
            }
            window.NutronyxModal.open('adjustStockModal');
        }

        function openAdjustModalForActiveProduct() {
            window.NutronyxModal.open('adjustStockModal');
        }

        function openReportsModal() {
            window.NutronyxModal.open('inventoryReportsModal');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close dropdowns on outside click
        window.addEventListener('click', (e) => {
            if (!e.target.closest('.inventory-row-dropdown') && !e.target.closest('button[onclick^="toggleRowMenu"]')) {
                document.querySelectorAll('.inventory-row-dropdown').forEach(m => m.style.display = 'none');
            }
        });
    </script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>