<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Context;
use Nutronyx\Database;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\ProductService;

AuthService::requireAuth();

$activePage = 'products';
$successMsg = '';
$errorMsg = '';
$activeWebsite = Context::getWebsite();

// AJAX API Endpoint Dispatcher
if (isset($_REQUEST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'] ?? '';

    try {
        if ($action === 'toggle_visibility' && !empty($_POST['product_id'])) {
            ProductService::toggleStorefrontVisibility($_POST['product_id']);
            echo json_encode(['success' => true, 'message' => 'Product visibility updated.']);
            exit;
        }

        if ($action === 'toggle_variant_visibility' && !empty($_POST['variant_id'])) {
            ProductService::toggleVariantVisibility($_POST['variant_id']);
            echo json_encode(['success' => true, 'message' => 'Variant visibility updated.']);
            exit;
        }

        if ($action === 'get_details' && !empty($_GET['product_id'])) {
            $details = ProductService::getProductDetails($_GET['product_id']);
            if (!$details) {
                echo json_encode(['success' => false, 'error' => 'Product not found.']);
                exit;
            }
            echo json_encode(['success' => true, 'product' => $details]);
            exit;
        }

        if ($action === 'duplicate' && !empty($_POST['product_id'])) {
            $newId = ProductService::duplicateProduct($_POST['product_id']);
            echo json_encode(['success' => true, 'new_product_id' => $newId, 'message' => 'Product duplicated successfully.']);
            exit;
        }

        if ($action === 'save_relations' && !empty($_POST['product_id'])) {
            $type = $_POST['relation_type'] ?? 'related';
            $ids = !empty($_POST['related_ids']) ? (is_array($_POST['related_ids']) ? $_POST['related_ids'] : explode(',', $_POST['related_ids'])) : [];
            ProductService::saveRelations($_POST['product_id'], $type, $ids);
            echo json_encode(['success' => true, 'message' => ucfirst($type) . ' products updated.']);
            exit;
        }

        if ($action === 'bulk_status' && !empty($_POST['ids']) && !empty($_POST['status'])) {
            $ids = is_array($_POST['ids']) ? $_POST['ids'] : explode(',', $_POST['ids']);
            $count = ProductService::bulkUpdateStatus($ids, $_POST['status']);
            echo json_encode(['success' => true, 'affected' => $count, 'message' => "Updated status for {$count} products."]);
            exit;
        }

        if ($action === 'bulk_delete' && !empty($_POST['ids'])) {
            $ids = is_array($_POST['ids']) ? $_POST['ids'] : explode(',', $_POST['ids']);
            $count = ProductService::bulkDelete($ids);
            echo json_encode(['success' => true, 'affected' => $count, 'message' => "Deleted {$count} products."]);
            exit;
        }

        if ($action === 'bulk_stock' && !empty($_POST['ids']) && isset($_POST['quantity'])) {
            $ids = is_array($_POST['ids']) ? $_POST['ids'] : explode(',', $_POST['ids']);
            $mode = $_POST['mode'] ?? 'set';
            $count = ProductService::bulkUpdateStock($ids, (int)$_POST['quantity'], $mode);
            echo json_encode(['success' => true, 'affected' => $count, 'message' => "Updated stock across {$count} variants."]);
            exit;
        }

        if ($action === 'bulk_category' && !empty($_POST['ids']) && !empty($_POST['category_id'])) {
            $ids = is_array($_POST['ids']) ? $_POST['ids'] : explode(',', $_POST['ids']);
            $count = ProductService::bulkChangeCategory($ids, $_POST['category_id']);
            echo json_encode(['success' => true, 'affected' => $count, 'message' => "Assigned category to {$count} products."]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown AJAX action.']);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle Form Submissions (Standard HTTP POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['action'] ?? '';

    try {
        if ($formAction === 'create_product') {
            ProductService::createProduct($_POST);
            $successMsg = 'Product and variants created successfully.';
        } elseif ($formAction === 'update_product' && !empty($_POST['product_id'])) {
            ProductService::updateProduct($_POST['product_id'], $_POST);
            $successMsg = 'Product details updated successfully.';
        } elseif ($formAction === 'delete_product' && !empty($_POST['product_id'])) {
            ProductService::deleteProduct($_POST['product_id']);
            $successMsg = 'Product and associated variant records safely removed.';
        } elseif ($formAction === 'duplicate_product' && !empty($_POST['product_id'])) {
            $newId = ProductService::duplicateProduct($_POST['product_id']);
            $successMsg = "Product duplicated successfully (#{$newId}).";
        } elseif ($formAction === 'bulk_action' && !empty($_POST['selected_ids'])) {
            $subAction = $_POST['bulk_subaction'] ?? '';
            $ids = array_filter(explode(',', (string)$_POST['selected_ids']));

            if ($subAction === 'publish') {
                $c = ProductService::bulkUpdateStatus($ids, 'ACTIVE');
                $successMsg = "Published {$c} products to storefront.";
            } elseif ($subAction === 'unpublish') {
                $c = ProductService::bulkUpdateStatus($ids, 'DRAFT');
                $successMsg = "Moved {$c} products to draft.";
            } elseif ($subAction === 'archive') {
                $c = ProductService::bulkUpdateStatus($ids, 'ARCHIVED');
                $successMsg = "Archived {$c} products.";
            } elseif ($subAction === 'delete') {
                $c = ProductService::bulkDelete($ids);
                $successMsg = "Safely deleted {$c} products.";
            } elseif ($subAction === 'update_stock' && isset($_POST['bulk_stock_qty'])) {
                $mode = $_POST['bulk_stock_mode'] ?? 'set';
                $c = ProductService::bulkUpdateStock($ids, (int)$_POST['bulk_stock_qty'], $mode);
                $successMsg = "Updated inventory stock across {$c} product variants.";
            } elseif ($subAction === 'change_category' && !empty($_POST['bulk_category_id'])) {
                $c = ProductService::bulkChangeCategory($ids, $_POST['bulk_category_id']);
                $successMsg = "Updated category for {$c} products.";
            }
        } elseif ($formAction === 'import_catalog_csv' && isset($_FILES['import_file'])) {
            $file = $_FILES['import_file'];
            if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
                $content = file_get_contents($file['tmp_name']);
                $lines = array_filter(explode("\n", str_replace("\r", "", $content)));
                $header = null;
                $importedCount = 0;
                $db = Database::getConnection();

                foreach ($lines as $line) {
                    $cols = str_getcsv($line);
                    if (!$header) {
                        $header = array_map('strtolower', array_map('trim', $cols));
                        continue;
                    }
                    if (count($cols) < 2) continue;
                    $row = array_combine(array_slice($header, 0, count($cols)), $cols);
                    $title = $row['product title'] ?? $row['title'] ?? $row['name'] ?? null;
                    $sku = $row['variant sku'] ?? $row['sku'] ?? null;
                    $price = (float)($row['regular price (inr)'] ?? $row['price'] ?? 499);
                    $stock = (int)($row['inventory on hand'] ?? $row['stock'] ?? 100);

                    if (!empty($title)) {
                        ProductService::createProduct([
                            'title' => $title,
                            'sku' => $sku,
                            'price_rupees' => $price,
                            'stock_quantity' => $stock,
                            'status' => 'ACTIVE'
                        ]);
                        $importedCount++;
                    }
                }
                $successMsg = "Successfully imported {$importedCount} products into catalog.";
            } else {
                $errorMsg = 'Please select a valid CSV catalog file to import.';
            }
        }
    } catch (\Throwable $e) {
        $errorMsg = $e->getMessage();
    }
}

// Quick URL status toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    ProductService::updateStatus($_GET['id'], $_GET['toggle_status']);
    header('Location: products.php');
    exit;
}

// Filter, Search, Pagination Parameters
$search = $_GET['search'] ?? null;
$categoryFilter = $_GET['category'] ?? null;
$statusFilter = $_GET['status'] ?? null;
$stockFilter = $_GET['stock'] ?? null;
$minPrice = $_GET['min_price'] ?? null;
$maxPrice = $_GET['max_price'] ?? null;
$hasVariants = $_GET['has_variants'] ?? null;
$sortBy = $_GET['sort'] ?? 'newest';
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, (int)($_GET['per_page'] ?? 10));

// Query Data
$catalogResult = ProductService::getProductsAdvanced([
    'search' => $search,
    'category_id' => $categoryFilter,
    'status' => $statusFilter,
    'stock_filter' => $stockFilter,
    'min_price' => $minPrice,
    'max_price' => $maxPrice,
    'has_variants' => $hasVariants,
    'sort_by' => $sortBy,
    'page' => $pageNum,
    'per_page' => $perPage
]);

$products = $catalogResult['products'];
$totalProducts = $catalogResult['total'];
$totalPages = $catalogResult['total_pages'];
$counts = $catalogResult['counts'];
$categories = ProductService::getCategories();
$page = $pageNum;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products &mdash; Nutronyx Admin Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=20261119_v1">
    <style>
        .product-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .product-page-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 30px;
            font-weight: 700;
            color: #111827;
            margin: 0;
            letter-spacing: -0.01em;
        }
        .product-page-subtitle {
            font-size: 13.5px;
            color: #6B7280;
            margin-top: 4px;
            margin-bottom: 0;
        }
        .product-thumb-box {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #E5E7EB;
            background: #F9FAFB;
            flex-shrink: 0;
        }
        .product-thumb-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .variant-thumb-box {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #E5E7EB;
            background: #FFFFFF;
            flex-shrink: 0;
        }
        .variant-thumb-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
    </style>
</head>
<body class="admin-body">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="admin-layout-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main-stage" style="padding: 28px 32px; background: #F9FAFB;">
            
            <!-- TOP PAGE HEADER (MATCHING MOCKUP) -->
            <div class="product-page-header">
                <div>
                    <h1 class="product-page-title">Products</h1>
                    <p class="product-page-subtitle">Manage your product catalog, variants, inventory and visibility.</p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <!-- Import Button -->
                    <button type="button" class="btn-emerald-outline" onclick="window.NutronyxModal.open('importModal')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <span>Import</span>
                    </button>
                    <!-- Export Button -->
                    <button type="button" class="btn-emerald-outline" onclick="window.NutronyxProduct.openExportModal()">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <span>Export</span>
                    </button>
                    <!-- Add Product Button -->
                    <button type="button" class="btn-emerald-brand" onclick="window.NutronyxProduct.openAdd()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        <span>Add Product</span>
                    </button>
                </div>
            </div>

            <!-- SUCCESS & ERROR ALERTS -->
            <?php if (!empty($successMsg)): ?>
                <div class="dash-panel" style="background: #ECFDF5; border-color: #A7F3D0; color: #065F46; padding: 14px 18px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; border-radius: 12px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span><?= htmlspecialchars($successMsg) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($errorMsg)): ?>
                <div class="dash-panel" style="background: #FEF2F2; border-color: #FECACA; color: #991B1B; padding: 14px 18px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; border-radius: 12px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span><?= htmlspecialchars($errorMsg) ?></span>
                </div>
            <?php endif; ?>

            <!-- SEARCH & FILTER BAR (MATCHING MOCKUP) -->
            <div class="catalog-filter-card">
                <form method="GET" action="products.php" id="catalogFilterForm" style="display: flex; gap: 12px; align-items: center; flex: 1; flex-wrap: wrap;">
                    <!-- Search Input -->
                    <div class="catalog-search-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="search" class="catalog-search-input" placeholder="Search products by name, SKU..." value="<?= htmlspecialchars($search ?? '') ?>" onkeydown="if(event.key === 'Enter') this.form.submit();">
                    </div>

                    <!-- Category Dropdown -->
                    <div>
                        <select name="category" class="catalog-select" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['id']) ?>" <?= ($categoryFilter === $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status Dropdown -->
                    <div>
                        <select name="status" class="catalog-select" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="ACTIVE" <?= ($statusFilter === 'ACTIVE') ? 'selected' : '' ?>>Active</option>
                            <option value="DRAFT" <?= ($statusFilter === 'DRAFT') ? 'selected' : '' ?>>Draft</option>
                            <option value="ARCHIVED" <?= ($statusFilter === 'ARCHIVED') ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>

                    <!-- Stock Dropdown -->
                    <div>
                        <select name="stock" class="catalog-select" onchange="this.form.submit()">
                            <option value="">All Stock</option>
                            <option value="in_stock" <?= ($stockFilter === 'in_stock') ? 'selected' : '' ?>>In Stock</option>
                            <option value="low_stock" <?= ($stockFilter === 'low_stock') ? 'selected' : '' ?>>Low Stock</option>
                            <option value="out_of_stock" <?= ($stockFilter === 'out_of_stock') ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>

                    <!-- Price Range Min - Max -->
                    <div class="price-range-inputs">
                        <span>₹</span>
                        <input type="number" name="min_price" placeholder="Min" value="<?= htmlspecialchars((string)($minPrice ?? '')) ?>">
                        <span>–</span>
                        <span>₹</span>
                        <input type="number" name="max_price" placeholder="Max" value="<?= htmlspecialchars((string)($maxPrice ?? '')) ?>">
                    </div>

                    <!-- More Filters Toggle Button -->
                    <button type="button" class="btn-emerald-outline" onclick="window.NutronyxProduct.toggleMoreFilters()" style="padding: 9px 12px; height: 40px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                        <span>More Filters</span>
                    </button>

                    <!-- Refresh / Reset Button -->
                    <a href="products.php" class="btn-emerald-outline" style="padding: 9px 12px; height: 40px; display: inline-flex; align-items: center; justify-content: center;" title="Reset filters">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                    </a>
                </form>
            </div>

            <!-- COLLAPSIBLE "MORE FILTERS" PANEL -->
            <div id="moreFiltersPanel" style="display: none; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px; padding: 14px 18px; margin-bottom: 16px;">
                <div style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 10px;">Advanced Catalog Filters</div>
                <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                    <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #4B5563; cursor: pointer;">
                        <input type="radio" name="has_variants" value="" <?= empty($hasVariants) ? 'checked' : '' ?> onchange="document.getElementById('catalogFilterForm').submit()"> All Variant Types
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #4B5563; cursor: pointer;">
                        <input type="radio" name="has_variants" value="yes" <?= ($hasVariants === 'yes') ? 'checked' : '' ?> onchange="document.getElementById('catalogFilterForm').submit()"> Products with Multiple Variants
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #4B5563; cursor: pointer;">
                        <input type="radio" name="has_variants" value="no" <?= ($hasVariants === 'no') ? 'checked' : '' ?> onchange="document.getElementById('catalogFilterForm').submit()"> Single SKU Products Only
                    </label>
                </div>
            </div>

            <!-- BULK ACTIONS TOOLBAR (MATCHING MOCKUP) -->
            <div class="catalog-bulk-bar">
                <div class="bulk-actions-left">
                    <input type="checkbox" id="selectAllCheckbox" class="catalog-checkbox" title="Select all products" onchange="window.NutronyxProduct.toggleSelectAll(this)">
                    
                    <span class="bulk-selected-pill" id="selectedCountBadge" style="display: none;">
                        <span id="selectedCountNumber">0</span>&nbsp;selected
                    </span>

                    <!-- Bulk action buttons: hidden until at least 1 checkbox is selected -->
                    <div id="bulkButtonsGroup" style="display: none; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="btn-bulk-action" onclick="window.NutronyxProduct.executeBulk('publish')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span>Publish</span>
                        </button>

                        <button type="button" class="btn-bulk-action" onclick="window.NutronyxProduct.executeBulk('unpublish')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                            <span>Unpublish</span>
                        </button>

                        <button type="button" class="btn-bulk-action" onclick="window.NutronyxProduct.openBulkStockModal()">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                            <span>Update Stock</span>
                        </button>

                        <button type="button" class="btn-bulk-action" onclick="window.NutronyxProduct.openBulkCategoryModal()">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                            <span>Change Category</span>
                        </button>

                        <button type="button" class="btn-bulk-action danger" onclick="window.NutronyxProduct.executeBulk('delete')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            <span>Delete</span>
                        </button>
                    </div>
                </div>

                <div class="bulk-actions-right">
                    <!-- Sort By Selector -->
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 13px; color: #6B7280;">Sort by:</span>
                        <select name="sort" class="catalog-select" style="height: 34px; padding-top: 0; padding-bottom: 0;" onchange="window.NutronyxProduct.updateSort(this.value)">
                            <option value="newest" <?= ($sortBy === 'newest') ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= ($sortBy === 'oldest') ? 'selected' : '' ?>>Oldest</option>
                            <option value="title_asc" <?= ($sortBy === 'title_asc') ? 'selected' : '' ?>>Name (A-Z)</option>
                            <option value="title_desc" <?= ($sortBy === 'title_desc') ? 'selected' : '' ?>>Name (Z-A)</option>
                            <option value="price_asc" <?= ($sortBy === 'price_asc') ? 'selected' : '' ?>>Price (Low &rarr; High)</option>
                            <option value="price_desc" <?= ($sortBy === 'price_desc') ? 'selected' : '' ?>>Price (High &rarr; Low)</option>
                            <option value="stock_asc" <?= ($sortBy === 'stock_asc') ? 'selected' : '' ?>>Stock (Low &rarr; High)</option>
                            <option value="stock_desc" <?= ($sortBy === 'stock_desc') ? 'selected' : '' ?>>Stock (High &rarr; Low)</option>
                        </select>
                    </div>

                    <!-- View Switcher (List vs Grid) -->
                    <div class="view-mode-group">
                        <button type="button" class="view-mode-btn active" title="List View">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                        </button>
                        <button type="button" class="view-mode-btn" title="Grid View" onclick="window.NutronyxToast.info('Grid catalog view available in next release.')">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- PRODUCT CATALOG TABLE (MATCHING MOCKUP WITH ACCORDION VARIANTS) -->
            <div class="catalog-table-wrap">
                <table class="catalog-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;"></th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Inventory</th>
                            <th>Variants</th>
                            <th>Status</th>
                            <th>Visibility</th>
                            <th style="text-align: right; padding-right: 20px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 48px 20px;">
                                    <div style="width: 56px; height: 56px; border-radius: 50%; background: #F3F4F6; display: inline-flex; align-items: center; justify-content: center; color: #9CA3AF; margin-bottom: 12px;">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                    </div>
                                    <div style="font-weight: 600; font-size: 16px; color: #111827;">No matching products found</div>
                                    <p style="font-size: 13.5px; color: #6B7280; max-width: 420px; margin: 6px auto 16px;">Try adjusting your search criteria, clearing active filters, or creating a new product.</p>
                                    <a href="products.php" class="btn-emerald-outline" style="font-size: 13px;">Clear All Filters</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $idx => $p): 
                                $pTotalStock = (int)($p['total_stock'] ?? 0);
                                $isOut = $pTotalStock <= 0;
                                $statusClass = $isOut ? 'out-of-stock' : strtolower($p['status']);
                                $statusLabel = $isOut ? 'Out of Stock' : ucfirst(strtolower($p['status']));
                                $variantCount = count($p['variants'] ?? []);
                                $isExpanded = false; // Always start collapsed by default
                                
                                $rawImg = $p['image_url'] ?? '';
                                $thumbSrc = ($rawImg !== '' && (str_starts_with($rawImg, 'http') || str_starts_with($rawImg, '/'))) 
                                    ? $rawImg 
                                    : ($rawImg !== '' ? ('../' . $rawImg) : '../assets/images/about-commitment-still-life.jpg');
                            ?>
                                <!-- Main Product Row -->
                                <tr id="row-<?= htmlspecialchars($p['id']) ?>">
                                    <td style="text-align: center;">
                                        <input type="checkbox" class="catalog-checkbox product-select-checkbox" value="<?= htmlspecialchars($p['id']) ?>" onchange="window.NutronyxProduct.handleRowCheckbox()">
                                    </td>
                                    
                                    <!-- Product Info (Image + Title + SKU) -->
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 14px;">
                                            <div class="product-thumb-box" onclick="window.NutronyxProduct.openDetails('<?= $p['id'] ?>')" style="cursor: pointer;">
                                                <img src="<?= htmlspecialchars($thumbSrc) ?>" alt="<?= htmlspecialchars($p['title']) ?>" onerror="this.onerror=null; this.src='../assets/images/about-commitment-still-life.jpg';">
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: #111827; font-size: 14px; cursor: pointer;" onclick="window.NutronyxProduct.openDetails('<?= $p['id'] ?>')">
                                                    <?= htmlspecialchars($p['title']) ?>
                                                </div>
                                                <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">
                                                    SKU: <?= htmlspecialchars($p['primary_sku'] ?? 'N/A') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Category Pill -->
                                    <td>
                                        <span class="category-badge-pill">
                                            <?= htmlspecialchars($p['category_name'] ?? 'Health Blends') ?>
                                        </span>
                                    </td>

                                    <!-- Price (Showing range when variants differ) -->
                                    <td style="font-weight: 600; color: #111827; font-size: 14px; white-space: nowrap;">
                                        <?php
                                            $minP = (int)($p['min_price'] ?? 0);
                                            $maxP = (int)($p['max_price'] ?? $minP);
                                            if ($minP > 0 && $maxP > $minP) {
                                                echo '₹' . number_format($minP / 100) . ' – ₹' . number_format($maxP / 100);
                                            } elseif ($minP > 0) {
                                                echo '₹' . number_format($minP / 100);
                                            } else {
                                                $fallbackP = (int)($p['price_amount'] ?? $p['sale_price_amount'] ?? 0);
                                                echo '₹' . number_format($fallbackP / 100);
                                            }
                                        ?>
                                    </td>

                                    <!-- Inventory -->
                                    <td style="font-weight: 600; font-size: 14px; color: <?= $isOut ? '#EF4444' : '#111827' ?>;">
                                        <?= $isOut ? '0' : number_format($pTotalStock) ?>
                                    </td>

                                    <!-- Variants Expander Accordion -->
                                    <td>
                                        <?php if ($variantCount > 0): ?>
                                            <button type="button" class="variant-expander <?= $isExpanded ? 'open' : '' ?>" onclick="window.NutronyxProduct.toggleVariantAccordion('<?= $p['id'] ?>', this)">
                                                <span><?= $variantCount ?> variants</span>
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                            </button>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: #9CA3AF;">1 standard</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Status Dot Badge -->
                                    <td>
                                        <span class="status-pill-dot <?= $statusClass ?>">
                                            <span class="dot"></span>
                                            <span><?= $statusLabel ?></span>
                                        </span>
                                    </td>

                                    <!-- Storefront Visibility iOS Switch -->
                                    <td>
                                        <label class="toggle-switch" title="Toggle storefront visibility">
                                            <input type="checkbox" <?= ($p['is_visible_storefront'] == 1) ? 'checked' : '' ?> onchange="window.NutronyxProduct.toggleVisibility('<?= $p['id'] ?>', this)">
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </td>

                                    <!-- Row Actions Dropdown Menu (•••) -->
                                    <td style="text-align: right; padding-right: 20px;">
                                        <div class="action-dropdown-wrap">
                                            <button type="button" class="action-dots-btn" onclick="window.NutronyxProduct.toggleActionMenu('menu-<?= $p['id'] ?>', event)" title="More options">
                                                &bull;&bull;&bull;
                                            </button>
                                            
                                            <div class="action-dropdown-menu" id="menu-<?= $p['id'] ?>">
                                                <button type="button" class="action-menu-item" onclick='window.NutronyxProduct.openEdit(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                    <span>Edit Product</span>
                                                </button>

                                                <button type="button" class="action-menu-item" onclick="window.NutronyxProduct.duplicate('<?= $p['id'] ?>')">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                                    <span>Duplicate</span>
                                                </button>

                                                <a href="../shop.php?product=<?= urlencode($p['slug']) ?>" target="_blank" class="action-menu-item">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                                    <span>Preview</span>
                                                </a>

                                                <button type="button" class="action-menu-item" onclick="window.NutronyxProduct.openDetails('<?= $p['id'] ?>')">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                                    <span>Manage Images</span>
                                                </button>

                                                <button type="button" class="action-menu-item" onclick="window.NutronyxProduct.openRelations('<?= $p['id'] ?>', '<?= addslashes(htmlspecialchars($p['title'])) ?>')">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                    <span>Related Products</span>
                                                </button>

                                                <button type="button" class="action-menu-item" onclick="window.NutronyxProduct.archiveProduct('<?= $p['id'] ?>')">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>
                                                    <span>Archive</span>
                                                </button>

                                                <div style="height: 1px; background: #F3F4F6; margin: 4px 0;"></div>

                                                <button type="button" class="action-menu-item danger" onclick="window.NutronyxProduct.confirmDelete('<?= $p['id'] ?>', '<?= addslashes(htmlspecialchars($p['title'])) ?>')">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    <span>Delete</span>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <!-- NESTED VARIANT ROWS (EXPANDABLE SUBTABLE ACCORDION) -->
                                <?php if (!empty($p['variants'])): ?>
                                    <tr class="nested-variant-tr <?= $isExpanded ? 'open' : '' ?>" id="variant-subtable-<?= htmlspecialchars($p['id']) ?>">
                                        <td colspan="9" class="nested-variant-container">
                                            <table class="nested-variant-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 220px;">Variant</th>
                                                        <th>SKU</th>
                                                        <th>Price</th>
                                                        <th>Inventory</th>
                                                        <th style="width: 120px;">Visibility</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($p['variants'] as $v): 
                                                        $vStock = (int)($v['stock_quantity'] ?? $v['quantity_on_hand'] ?? 0);
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                                    <div class="variant-thumb-box">
                                                                        <img src="<?= htmlspecialchars($thumbSrc) ?>" alt="<?= htmlspecialchars($v['title']) ?>">
                                                                    </div>
                                                                    <span style="font-weight: 600; color: #1F2937;"><?= htmlspecialchars($v['title']) ?></span>
                                                                </div>
                                                            </td>
                                                            <td style="color: #6B7280; font-family: monospace; font-size: 12px;">
                                                                <?= htmlspecialchars($v['sku']) ?>
                                                            </td>
                                                            <td style="font-weight: 600; color: #111827;">
                                                                ₹<?= number_format(((int)($v['price_amount'] ?? 0)) / 100) ?>
                                                            </td>
                                                            <td style="font-weight: 600; color: <?= $vStock <= 0 ? '#EF4444' : '#111827' ?>;">
                                                                <?= number_format($vStock) ?>
                                                            </td>
                                                            <td>
                                                                <label class="toggle-switch">
                                                                    <input type="checkbox" <?= ($v['is_visible'] == 1) ? 'checked' : '' ?> onchange="window.NutronyxProduct.toggleVariantVisibility('<?= $v['id'] ?>', this)">
                                                                    <span class="toggle-slider"></span>
                                                                </label>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- FOOTER PAGINATION BAR (MATCHING MOCKUP) -->
            <div class="catalog-pagination-bar">
                <div class="pagination-left">
                    Showing <strong><?= ($totalProducts > 0) ? ((($page - 1) * $perPage) + 1) : 0 ?>–<?= min($page * $perPage, $totalProducts) ?></strong> of <strong><?= number_format($totalProducts) ?></strong> products
                </div>

                <div class="pagination-right">
                    <!-- Rows Per Page -->
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #6B7280;">
                        <span>Rows per page</span>
                        <select class="catalog-select" style="height: 34px; padding-top: 0; padding-bottom: 0;" onchange="window.NutronyxProduct.updatePerPage(this.value)">
                            <option value="10" <?= ($perPage === 10) ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= ($perPage === 25) ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= ($perPage === 50) ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= ($perPage === 100) ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>

                    <!-- Page Numbers -->
                    <div class="pagination-pages">
                        <!-- Prev Page -->
                        <a href="<?= ($page > 1) ? '?page=' . ($page - 1) . '&per_page=' . $perPage . '&category=' . urlencode($categoryFilter ?? '') . '&status=' . urlencode($statusFilter ?? '') . '&search=' . urlencode($search ?? '') : '#' ?>" class="page-btn <?= ($page <= 1) ? 'disabled' : '' ?>">
                            &lsaquo;
                        </a>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        if ($startPage > 1) {
                            echo '<a href="?page=1&per_page=' . $perPage . '" class="page-btn">1</a>';
                            if ($startPage > 2) echo '<span style="color:#9CA3AF; padding: 0 4px;">...</span>';
                        }
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $activeClass = ($i === $page) ? 'active' : '';
                            echo '<a href="?page=' . $i . '&per_page=' . $perPage . '&category=' . urlencode($categoryFilter ?? '') . '&status=' . urlencode($statusFilter ?? '') . '&search=' . urlencode($search ?? '') . '" class="page-btn ' . $activeClass . '">' . $i . '</a>';
                        }
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) echo '<span style="color:#9CA3AF; padding: 0 4px;">...</span>';
                            echo '<a href="?page=' . $totalPages . '&per_page=' . $perPage . '" class="page-btn">' . $totalPages . '</a>';
                        }
                        ?>

                        <!-- Next Page -->
                        <a href="<?= ($page < $totalPages) ? '?page=' . ($page + 1) . '&per_page=' . $perPage . '&category=' . urlencode($categoryFilter ?? '') . '&status=' . urlencode($statusFilter ?? '') . '&search=' . urlencode($search ?? '') : '#' ?>" class="page-btn <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            &rsaquo;
                        </a>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- ==========================================================================
         MODAL 1: ADD / EDIT PRODUCT MODAL WITH VARIANT MATRIX GENERATOR
         ========================================================================== -->
    <div class="modal-overlay" id="productModal">
        <div class="modal-card modal-xl">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" id="productModalTitle" style="font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; color: #111827;">Add product</h3>
                    <p style="font-size: 13px; color: #6B7280; margin-top: 4px;">Configure product details, multi-tier pricing, inventory allocations, and variant matrices.</p>
                </div>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('productModal')">&times;</button>
            </div>

            <form method="POST" action="products.php" id="productForm">
                <input type="hidden" name="action" id="productFormAction" value="create_product">
                <input type="hidden" name="product_id" id="productIdField" value="">
                <input type="hidden" name="image_url" id="productImageUrl" value="assets/images/about-commitment-still-life.jpg">

                <div class="modal-body">
                    
                    <!-- 1. PRODUCT IMAGES SECTION -->
                    <div class="product-form-section">
                        <label class="product-section-label" style="font-weight: 600; font-size: 13.5px; color: #111827;">Product images *</label>
                        <div class="product-upload-dropzone" onclick="document.getElementById('productFileInput').click()">
                            <input type="file" id="productFileInput" accept="image/*" style="display:none;" onchange="window.NutronyxProduct.handleImageSelect(this)">
                            <div class="dropzone-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                            </div>
                            <div class="dropzone-title"><strong>Click to upload</strong> or drag product photo here</div>
                            <div class="dropzone-subtext">PNG, JPG, WebP up to 15 MB &middot; 1/10 used</div>
                        </div>

                        <div class="product-image-preview-row" style="margin-top: 12px; display: flex; align-items: center; gap: 14px;">
                            <div class="product-photo-card" style="width: 60px; height: 60px; border-radius: 8px; overflow: hidden; border: 1px solid #E5E7EB;">
                                <img id="productPhotoPreview" src="../assets/images/about-commitment-still-life.jpg" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div style="font-size: 12.5px; color: #6B7280;">
                                Primary featured image shown across storefront catalog cards, checkout summaries, and invoices.
                            </div>
                        </div>
                    </div>

                    <!-- 2. NAME & SLUG -->
                    <div class="form-group" style="margin-top: 18px;">
                        <label class="form-label">Product name *</label>
                        <input type="text" name="title" id="productTitle" required class="form-control" placeholder="e.g. ABC+ Health Blend" onkeyup="window.NutronyxProduct.syncSlug()">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Product slug</label>
                        <input type="text" name="slug" id="productSlug" class="form-control" placeholder="auto-generated from name">
                    </div>

                    <!-- 3. DESCRIPTIONS -->
                    <div class="form-group">
                        <label class="form-label">Short description</label>
                        <textarea name="short_description" id="productShortDesc" class="form-control" rows="2" placeholder="Brief summary for catalog cards and search snippets..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full description</label>
                        <textarea name="description" id="productFullDesc" class="form-control" rows="4" placeholder="Comprehensive product story, ingredients, formulation benefits, and usage instructions..."></textarea>
                    </div>

                    <!-- 4. CATEGORY & STATUS -->
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Category *</label>
                            <select name="category_id" id="productCategory" required class="form-control">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" id="productStatus" class="form-control">
                                <option value="ACTIVE">Active (Published)</option>
                                <option value="DRAFT">Draft (Unpublished)</option>
                                <option value="ARCHIVED">Archived</option>
                            </select>
                        </div>
                    </div>

                    <!-- 5. PRICING & SKU -->
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label">Regular price (₹) *</label>
                            <input type="number" step="0.01" name="price_rupees" id="productRegularPrice" required class="form-control" placeholder="499">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sale / Compare price (₹)</label>
                            <input type="number" step="0.01" name="sale_price" id="productSalePrice" class="form-control" placeholder="e.g. 599">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cost price (₹)</label>
                            <input type="number" step="0.01" name="cost_price" id="productCostPrice" class="form-control" placeholder="for profit margins">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Primary SKU *</label>
                            <input type="text" name="sku" id="productSku" class="form-control" placeholder="e.g. NX-ABC-001">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Inventory on hand</label>
                            <input type="number" name="stock_quantity" id="productStockQty" class="form-control" value="100">
                        </div>
                    </div>

                    <!-- 6. ACCORDION: STOREFRONT VISIBILITY & AVAILABILITY -->
                    <div class="product-accordions">
                        <div class="product-accordion-item open">
                            <div class="product-accordion-header" onclick="window.NutronyxProduct.toggleAccordion(this)">
                                <span class="product-accordion-title">Storefront Visibility &amp; Channels</span>
                                <span class="product-accordion-chevron">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </span>
                            </div>
                            <div class="product-accordion-content">
                                <div class="toggle-row" style="margin-bottom: 12px;">
                                    <span class="toggle-label">Visible in Storefront (Public access)</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="is_visible_storefront" value="1" id="productVisStore" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-row" style="margin-bottom: 12px;">
                                    <span class="toggle-label">Visible in Catalog / Collections Listing</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="is_visible_catalog" value="1" id="productVisCat" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-row" style="margin-bottom: 12px;">
                                    <span class="toggle-label">Visible in Storefront Search Results</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="is_visible_search" value="1" id="productVisSearch" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="toggle-row">
                                    <span class="toggle-label">Featured Product (Pinned to homepage &amp; spotlights)</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="is_featured" value="1" id="productFeatured">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 7. ACCORDION: VARIANT MATRIX GENERATOR -->
                        <div class="product-accordion-item">
                            <div class="product-accordion-header" onclick="window.NutronyxProduct.toggleAccordion(this)">
                                <span class="product-accordion-title">Product Variants &amp; Matrix Generator</span>
                                <span class="product-accordion-chevron">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </span>
                            </div>
                            <div class="product-accordion-content">
                                <p style="font-size: 12.5px; color: #6B7280; margin-bottom: 14px;">
                                    Define multi-dimensional product attributes (Size, Weight, Volume, Material, Color, Flavors).
                                </p>
                                
                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label class="form-label">Attribute Name</label>
                                        <input type="text" id="attrNameInput" class="form-control" placeholder="e.g. Weight">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Options (comma-separated)</label>
                                        <input type="text" id="attrValuesInput" class="form-control" placeholder="e.g. 150 g, 300 g, 500 g">
                                    </div>
                                </div>

                                <button type="button" class="btn-emerald-outline" onclick="window.NutronyxProduct.generateVariantRowsFromInputs()" style="margin-bottom: 16px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                    <span>Generate Variant Combinations</span>
                                </button>

                                <div id="generatedVariantsContainer" style="display: none;">
                                    <div style="font-size: 13px; font-weight: 600; color: #111827; margin-bottom: 8px;">Configured Variant Matrix</div>
                                    <table class="nested-variant-table" id="variantsMatrixTable" style="margin-bottom: 14px;">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>SKU</th>
                                                <th>Price (₹)</th>
                                                <th>Stock</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="variantsMatrixTbody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer" style="padding: 16px 28px; background: #FFFFFF; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" class="btn-emerald-outline" onclick="window.NutronyxModal.close('productModal')">Cancel</button>
                    <button type="submit" class="btn-emerald-brand" id="productSubmitBtn">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 2: RELATED PRODUCTS / CROSS-SELL / UPSELL MANAGER
         ========================================================================== -->
    <div class="modal-overlay" id="relatedProductsModal">
        <div class="modal-card modal-lg">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" style="font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; color: #111827;">Manage Product Relationships</h3>
                    <p style="font-size: 13px; color: #6B7280; margin-top: 4px;" id="relationModalSubtitle">Connect complementary catalog items</p>
                </div>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('relatedProductsModal')">&times;</button>
            </div>
            
            <div class="modal-body">
                <input type="hidden" id="relCurrentProductId" value="">
                
                <div style="display: flex; gap: 10px; margin-bottom: 16px; border-bottom: 1px solid #E5E7EB; padding-bottom: 12px;">
                    <button type="button" class="btn-emerald-brand" id="tabRelRelated" onclick="window.NutronyxProduct.switchRelTab('related')">Related Products</button>
                    <button type="button" class="btn-emerald-outline" id="tabRelUpsell" onclick="window.NutronyxProduct.switchRelTab('upsell')">Upsell Products</button>
                    <button type="button" class="btn-emerald-outline" id="tabRelCrossSell" onclick="window.NutronyxProduct.switchRelTab('cross_sell')">Cross-Sell Products</button>
                </div>

                <div style="margin-bottom: 14px;">
                    <input type="text" id="relCandidateSearch" class="catalog-search-input" placeholder="Search products by title or SKU to link..." onkeyup="window.NutronyxProduct.filterRelCandidates(this.value)">
                </div>

                <div style="max-height: 280px; overflow-y: auto; border: 1px solid #E5E7EB; border-radius: 10px; padding: 10px;" id="relCandidateList">
                    <?php foreach ($products as $cand): ?>
                        <label class="rel-candidate-item" data-id="<?= $cand['id'] ?>" data-name="<?= strtolower($cand['title']) ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; border-bottom: 1px solid #F3F4F6; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" class="rel-checkbox" value="<?= $cand['id'] ?>">
                                <div>
                                    <div style="font-weight: 600; font-size: 13.5px; color: #111827;"><?= htmlspecialchars($cand['title']) ?></div>
                                    <div style="font-size: 11.5px; color: #6B7280;"><?= htmlspecialchars($cand['primary_sku'] ?? '') ?> &middot; ₹<?= number_format(((int)($cand['min_price'] ?? 0)) / 100) ?></div>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="modal-footer" style="padding: 16px 28px; background: #FFFFFF; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn-emerald-outline" onclick="window.NutronyxModal.close('relatedProductsModal')">Close</button>
                <button type="button" class="btn-emerald-brand" onclick="window.NutronyxProduct.saveCurrentRelations()">Save Relationships</button>
            </div>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 3: BULK UPDATE STOCK MODAL
         ========================================================================== -->
    <div class="modal-overlay" id="bulkStockModal">
        <div class="modal-card modal-sm">
            <div class="modal-header">
                <h3 class="modal-title" style="font-size: 18px; font-weight: 700; color: #111827;">Bulk Update Inventory</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('bulkStockModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Adjustment Mode</label>
                    <select id="bulkStockMode" class="form-control">
                        <option value="set">Set exact stock level</option>
                        <option value="add">Add to current stock (+)</option>
                        <option value="subtract">Subtract from current stock (-)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-top: 14px;">
                    <label class="form-label">Quantity</label>
                    <input type="number" id="bulkStockQtyInput" class="form-control" value="100" min="0">
                </div>
            </div>
            <div class="modal-footer" style="padding: 14px 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-emerald-outline" onclick="window.NutronyxModal.close('bulkStockModal')">Cancel</button>
                <button type="button" class="btn-emerald-brand" onclick="window.NutronyxProduct.submitBulkStock()">Apply Stock</button>
            </div>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 4: BULK CHANGE CATEGORY MODAL
         ========================================================================== -->
    <div class="modal-overlay" id="bulkCategoryModal">
        <div class="modal-card modal-sm">
            <div class="modal-header">
                <h3 class="modal-title" style="font-size: 18px; font-weight: 700; color: #111827;">Bulk Change Category</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('bulkCategoryModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Target Category</label>
                    <select id="bulkCategorySelect" class="form-control">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer" style="padding: 14px 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-emerald-outline" onclick="window.NutronyxModal.close('bulkCategoryModal')">Cancel</button>
                <button type="button" class="btn-emerald-brand" onclick="window.NutronyxProduct.submitBulkCategory()">Assign Category</button>
            </div>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 5: CSV CATALOG IMPORT MODAL WITH VALIDATION PREVIEW
         ========================================================================== -->
    <div class="modal-overlay" id="importModal">
        <div class="modal-card modal-md">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" style="font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; color: #111827;">Import Product Catalog</h3>
                    <p style="font-size: 13px; color: #6B7280; margin-top: 4px;">Upload standard CSV with Product Name, SKU, Price, and Stock.</p>
                </div>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('importModal')">&times;</button>
            </div>
            <form method="POST" action="products.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_catalog_csv">
                <div class="modal-body">
                    <div class="product-upload-dropzone" onclick="document.getElementById('catalogImportFileInput').click()">
                        <input type="file" name="import_file" id="catalogImportFileInput" accept=".csv" required style="display:none;" onchange="document.getElementById('importFileName').textContent = this.files[0]?.name || ''">
                        <div class="dropzone-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        </div>
                        <div class="dropzone-title"><strong>Click to select CSV file</strong></div>
                        <div class="dropzone-subtext" id="importFileName">UTF-8 encoded .csv up to 10 MB</div>
                    </div>

                    <div style="margin-top: 16px; font-size: 12.5px; color: #6B7280; line-height: 1.6;">
                        <strong>Supported CSV Columns:</strong> Product Title, SKU, Category, Regular Price (INR), Inventory On Hand, Status.<br>
                        <a href="export.php?type=products" style="color: #10B981; text-decoration: underline;">Download sample catalog template</a>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 14px 24px; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-emerald-outline" onclick="window.NutronyxModal.close('importModal')">Cancel</button>
                    <button type="submit" class="btn-emerald-brand">Start Import</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 6: EXPORT OPTIONS MODAL
         ========================================================================== -->
    <div class="modal-overlay" id="exportModal">
        <div class="modal-card modal-sm">
            <div class="modal-header">
                <h3 class="modal-title" style="font-size: 18px; font-weight: 700; color: #111827;">Export Product Catalog</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('exportModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size: 13px; color: #6B7280; margin-bottom: 14px;">Select the scope of data you wish to download as CSV:</p>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button type="button" class="btn-emerald-outline" onclick="window.location.href='export.php?type=products'" style="justify-content: flex-start; padding: 12px 16px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <span>Export Complete Catalog (All SKUs &amp; Variants)</span>
                    </button>
                    <button type="button" class="btn-emerald-outline" id="btnExportSelected" onclick="window.NutronyxProduct.exportSelected()" style="display: none; justify-content: flex-start; padding: 12px 16px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                        <span id="btnExportSelectedText">Export Selected Checkboxes Only</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================================================
         DRAWER: IN-DEPTH PRODUCT DETAILS DRAWER
         ========================================================================== -->
    <div class="modal-overlay" id="productDetailsDrawer">
        <div class="modal-card modal-lg" style="margin-left: auto; margin-right: 0; height: 100vh; max-height: 100vh; border-radius: 0; display: flex; flex-direction: column;">
            <div class="modal-header" style="background: #FFFFFF; border-bottom: 1px solid #E5E7EB;">
                <div>
                    <h3 class="modal-title" id="drawerProductTitle" style="font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; color: #111827;">Product Details</h3>
                    <span id="drawerProductSku" style="font-size: 12px; color: #6B7280; font-family: monospace;">SKU: NX-000</span>
                </div>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('productDetailsDrawer')">&times;</button>
            </div>
            
            <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 24px;">
                <div id="drawerContent">
                    <div style="text-align: center; padding: 40px; color: #9CA3AF;">Loading product information...</div>
                </div>
            </div>

            <div class="modal-footer" style="background: #FFFFFF; border-top: 1px solid #E5E7EB; padding: 16px 24px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-emerald-outline" onclick="window.NutronyxModal.close('productDetailsDrawer')">Close</button>
                <button type="button" class="btn-emerald-brand" id="drawerEditBtn">Edit Product</button>
            </div>
        </div>
    </div>

    <!-- Hidden Bulk Form for POST Submission -->
    <form method="POST" action="products.php" id="bulkForm" style="display:none;">
        <input type="hidden" name="action" value="bulk_action">
        <input type="hidden" name="bulk_subaction" id="bulkSubactionField" value="">
        <input type="hidden" name="selected_ids" id="bulkSelectedIdsField" value="">
        <input type="hidden" name="bulk_stock_qty" id="bulkStockQtyField" value="">
        <input type="hidden" name="bulk_stock_mode" id="bulkStockModeField" value="set">
        <input type="hidden" name="bulk_category_id" id="bulkCategoryIdField" value="">
    </form>

    <script src="../assets/js/admin.js"></script>
    <script>
        // Enhanced Product Controller Scripts
        document.addEventListener('click', function(e) {
            // Close any open action menus when clicking outside
            if (!e.target.closest('.action-dropdown-wrap')) {
                document.querySelectorAll('.action-dropdown-menu.show').forEach(m => m.classList.remove('show'));
            }
        });
    </script>
</body>
</html>
