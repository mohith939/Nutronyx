<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\AuditService;
use Nutronyx\Services\CategoryService;

AuthService::requireAuth();

$activePage = 'categories';
$activeWebsite = Context::getWebsite();
$wid = Context::getWebsiteId();
$db = Database::getConnection();

$notice = '';
$error = '';

// -----------------------------------------------------------------------------
// 1. AJAX API ENDPOINTS
// -----------------------------------------------------------------------------
if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['action'] ?? '';

    if ($action === 'get_details') {
        $catId = trim((string)($_GET['category_id'] ?? ''));
        $cat = CategoryService::getCategory($catId, $wid);
        if ($cat) {
            echo json_encode(['success' => true, 'category' => $cat]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Category not found.']);
        }
        exit;
    }

    if ($action === 'toggle_featured') {
        $catId = trim((string)($_POST['category_id'] ?? ''));
        if ($catId) {
            $newVal = CategoryService::toggleFeatured($catId, $wid);
            echo json_encode(['success' => true, 'is_featured' => $newVal]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Missing category ID.']);
        }
        exit;
    }

    if ($action === 'toggle_status') {
        $catId = trim((string)($_POST['category_id'] ?? ''));
        if ($catId) {
            $newStatus = CategoryService::toggleStatus($catId, $wid);
            echo json_encode(['success' => true, 'status' => $newStatus]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Missing category ID.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    exit;
}

// -----------------------------------------------------------------------------
// 2. FORM ACTION HANDLERS
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create or Update Category
    if ($action === 'create_category' || $action === 'add_category' || $action === 'update_category') {
        $isUpdate = ($action === 'update_category');
        $catId = trim((string)($_POST['category_id'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $status = strtoupper(trim((string)($_POST['status'] ?? 'ACTIVE')));
        $isFeatured = !empty($_POST['is_featured']) ? 1 : 0;
        $subcategories = trim((string)($_POST['subcategories'] ?? ''));
        $imageUrl = trim((string)($_POST['image_url'] ?? 'assets/images/about-commitment-still-life.jpg'));

        // Handle uploaded file
        if (isset($_FILES['category_image_file']) && $_FILES['category_image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['category_image_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $filename = 'cat_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $uploadDir = __DIR__ . '/../assets/images/categories/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $destPath = $uploadDir . $filename;
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $imageUrl = 'assets/images/categories/' . $filename;
                }
            }
        }

        if (empty($name)) {
            $error = "Category name is required.";
        } else {
            try {
                $data = [
                    'website_id' => $wid,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'image_url' => $imageUrl,
                    'status' => $status,
                    'is_featured' => $isFeatured,
                    'subcategories' => $subcategories
                ];

                if ($isUpdate && !empty($catId)) {
                    CategoryService::updateCategory($catId, $data, $wid);
                    $notice = "Category \"{$name}\" updated successfully.";
                } else {
                    $newId = CategoryService::createCategory($data);
                    $notice = "Category \"{$name}\" created successfully.";
                }
            } catch (\Throwable $e) {
                $error = "Failed to save category: " . $e->getMessage();
            }
        }
    }

    // Delete Category
    if ($action === 'delete_category') {
        $catId = trim((string)($_POST['category_id'] ?? ''));
        if ($catId) {
            try {
                CategoryService::deleteCategory($catId, $wid);
                $notice = "Category deleted successfully.";
            } catch (\Throwable $e) {
                $error = "Failed to delete category: " . $e->getMessage();
            }
        }
    }

    // Bulk Operations
    if ($action === 'bulk_action') {
        $subaction = trim((string)($_POST['bulk_subaction'] ?? ''));
        $rawIds = trim((string)($_POST['bulk_selected_ids'] ?? ''));
        $ids = array_filter(array_map('trim', explode(',', $rawIds)));

        if (!empty($ids)) {
            try {
                if ($subaction === 'make_featured') {
                    $cnt = CategoryService::bulkUpdateFeatured($ids, 1, $wid);
                    $notice = "Marked {$cnt} category(ies) as featured.";
                } elseif ($subaction === 'remove_featured') {
                    $cnt = CategoryService::bulkUpdateFeatured($ids, 0, $wid);
                    $notice = "Removed featured status from {$cnt} category(ies).";
                } elseif ($subaction === 'activate') {
                    $cnt = CategoryService::bulkUpdateStatus($ids, 'ACTIVE', $wid);
                    $notice = "Activated {$cnt} category(ies).";
                } elseif ($subaction === 'deactivate') {
                    $cnt = CategoryService::bulkUpdateStatus($ids, 'DRAFT', $wid);
                    $notice = "Deactivated {$cnt} category(ies) to draft.";
                } elseif ($subaction === 'delete') {
                    $cnt = CategoryService::bulkDelete($ids, $wid);
                    $notice = "Deleted {$cnt} category(ies) safely.";
                }
            } catch (\Throwable $e) {
                $error = "Bulk operation failed: " . $e->getMessage();
            }
        } else {
            $error = "No categories were selected for bulk action.";
        }
    }

    // Import Categories CSV
    if ($action === 'import_categories') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $filePath = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($filePath, 'r')) !== false) {
                $header = fgetcsv($handle, 1000, ',');
                $imported = 0;
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    if (empty($row[0])) continue;
                    $name = trim($row[0]);
                    $slug = !empty($row[1]) ? trim($row[1]) : '';
                    $desc = $row[2] ?? '';
                    $st = !empty($row[3]) ? strtoupper(trim($row[3])) : 'ACTIVE';
                    $feat = !empty($row[4]) ? (int)$row[4] : 0;
                    $subcats = $row[5] ?? '';

                    CategoryService::createCategory([
                        'website_id' => $wid,
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $desc,
                        'status' => $st,
                        'is_featured' => $feat,
                        'subcategories' => $subcats
                    ]);
                    $imported++;
                }
                fclose($handle);
                $notice = "Successfully imported {$imported} categories.";
            } else {
                $error = "Failed to read uploaded CSV file.";
            }
        } else {
            $error = "Please upload a valid CSV file.";
        }
    }
}

// -----------------------------------------------------------------------------
// 3. QUERY PARAMETERS & FILTERING
// -----------------------------------------------------------------------------
$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$featuredRaw = $_GET['featured'] ?? '';
$isFeatured = ($featuredRaw !== '' && $featuredRaw !== 'all') ? (int)$featuredRaw : null;
$sortBy = trim((string)($_GET['sort'] ?? 'newest'));
$viewMode = trim((string)($_GET['view'] ?? 'table'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, (int)($_GET['per_page'] ?? 10));

$categoryData = CategoryService::getCategories(
    $wid,
    $status,
    $isFeatured,
    $search,
    $sortBy,
    $page,
    $perPage
);

$categories = $categoryData['categories'];
$totalCategories = $categoryData['total'];
$totalPages = $categoryData['total_pages'];

$startNum = $totalCategories > 0 ? (($page - 1) * $perPage + 1) : 0;
$endNum = min($page * $perPage, $totalCategories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories &mdash; Nutronyx Admin Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        .category-thumb-circle {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #E5E7EB;
            background: #F9FAFB;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .category-thumb-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .cat-grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }
        .cat-card-item {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .cat-card-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
    </style>
</head>
<body class="admin-body">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="admin-layout-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main-stage" style="padding: 28px 32px; background: #F9FAFB;">

            <!-- ==================================================================
                 TOP PAGE HEADER (MATCHING PRODUCTS PAGE EXACTLY)
                 ================================================================== -->
            <div class="product-page-header">
                <div>
                    <h1 class="product-page-title">Categories</h1>
                    <p class="product-page-subtitle">Manage your product categories and organize your catalog.</p>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn-emerald-outline" onclick="window.NutronyxCategory.openImportModal()">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <span>Import</span>
                    </button>

                    <button type="button" class="btn-emerald-outline" onclick="window.NutronyxCategory.openExportModal()">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <span>Export</span>
                    </button>

                    <button type="button" class="btn-emerald-brand" onclick="window.NutronyxCategory.openCreateModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        <span>Create Category</span>
                    </button>
                </div>
            </div>

            <!-- Notice / Alerts -->
            <?php if (!empty($notice)): ?>
                <div class="dash-panel" style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; padding: 12px 18px; margin-bottom: 20px; font-weight: 500; border-radius: 10px; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span><?= htmlspecialchars($notice) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="dash-panel" style="background: #FEF2F2; border: 1px solid #FECACA; color: #B91C1C; padding: 12px 18px; margin-bottom: 20px; font-weight: 500; border-radius: 10px; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- ==================================================================
                 SEARCH & FILTER TOOLBAR (MATCHING PRODUCTS PAGE EXACTLY)
                 ================================================================== -->
            <div class="catalog-filter-card">
                <form method="GET" action="categories.php" id="catFilterForm" style="display: flex; gap: 12px; align-items: center; flex: 1; flex-wrap: wrap;">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
                    <!-- Search Input -->
                    <div class="catalog-search-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="search" class="catalog-search-input" placeholder="Search categories..." value="<?= htmlspecialchars($search ?? '') ?>" onkeydown="if(event.key === 'Enter') this.form.submit();">
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <select name="status" class="catalog-select" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="ACTIVE" <?= ($status === 'ACTIVE') ? 'selected' : '' ?>>Active</option>
                            <option value="DRAFT" <?= ($status === 'DRAFT') ? 'selected' : '' ?>>Draft</option>
                        </select>
                    </div>

                    <!-- Featured Filter -->
                    <div>
                        <select name="featured" class="catalog-select" onchange="this.form.submit()">
                            <option value="">All Featured</option>
                            <option value="1" <?= ($featuredRaw === '1') ? 'selected' : '' ?>>Featured Only</option>
                            <option value="0" <?= ($featuredRaw === '0') ? 'selected' : '' ?>>Not Featured</option>
                        </select>
                    </div>

                    <!-- Reset Button -->
                    <a href="categories.php" class="btn-emerald-outline" style="padding: 9px 12px; height: 40px; display: inline-flex; align-items: center; justify-content: center;" title="Reset filters">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                    </a>
                </form>
            </div>

            <!-- ==================================================================
                 BULK ACTIONS & CONTROLS TOOLBAR (MATCHING PRODUCTS PAGE EXACTLY)
                 ================================================================== -->
            <div class="catalog-bulk-bar">
                <div class="bulk-actions-left">
                    <input type="checkbox" id="selectAllCatCheckbox" class="catalog-checkbox" title="Select all categories" onchange="window.NutronyxCategory.toggleSelectAll(this)">

                    <span class="bulk-selected-pill" id="catSelectedBadge" style="display: none;">
                        <span id="catSelectedCount">0</span>&nbsp;selected
                    </span>

                    <!-- Bulk action buttons (hidden until at least 1 checkbox selected) -->
                    <div id="catBulkButtonsGroup" style="display: none; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="btn-bulk-action" onclick="window.NutronyxCategory.bulkAction('make_featured')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            <span>Make Featured</span>
                        </button>

                        <button type="button" class="btn-bulk-action" onclick="window.NutronyxCategory.bulkAction('remove_featured')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                            <span>Remove Featured</span>
                        </button>

                        <button type="button" class="btn-bulk-action" onclick="window.NutronyxCategory.bulkAction('activate')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span>Activate</span>
                        </button>

                        <button type="button" class="btn-bulk-action" onclick="window.NutronyxCategory.bulkAction('deactivate')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                            <span>Deactivate</span>
                        </button>

                        <button type="button" class="btn-bulk-action danger" onclick="window.NutronyxCategory.bulkAction('delete')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            <span>Delete</span>
                        </button>
                    </div>
                </div>

                <div class="bulk-actions-right">
                    <!-- Sort By Selector -->
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 13px; color: #6B7280;">Sort by:</span>
                        <select name="sort" class="catalog-select" style="height: 34px; padding-top: 0; padding-bottom: 0;" onchange="window.NutronyxCategory.updateSort(this.value)">
                            <option value="newest" <?= ($sortBy === 'newest') ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= ($sortBy === 'oldest') ? 'selected' : '' ?>>Oldest</option>
                            <option value="name_asc" <?= ($sortBy === 'name_asc') ? 'selected' : '' ?>>Name (A–Z)</option>
                            <option value="name_desc" <?= ($sortBy === 'name_desc') ? 'selected' : '' ?>>Name (Z–A)</option>
                            <option value="products_desc" <?= ($sortBy === 'products_desc') ? 'selected' : '' ?>>Most Products</option>
                        </select>
                    </div>

                    <!-- View Mode Switcher -->
                    <div class="view-mode-group">
                        <button type="button" class="view-mode-btn <?= ($viewMode !== 'grid') ? 'active' : '' ?>" title="Table View" onclick="window.NutronyxCategory.switchView('table')">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                        </button>
                        <button type="button" class="view-mode-btn <?= ($viewMode === 'grid') ? 'active' : '' ?>" title="Grid View" onclick="window.NutronyxCategory.switchView('grid')">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Hidden bulk action form -->
            <form id="catBulkForm" method="POST" action="categories.php" style="display: none;">
                <input type="hidden" name="action" value="bulk_action">
                <input type="hidden" name="bulk_subaction" id="catBulkSubactionField" value="">
                <input type="hidden" name="bulk_selected_ids" id="catBulkSelectedIdsField" value="">
            </form>

            <?php if ($viewMode === 'grid'): ?>
                <!-- ==============================================================
                     GRID CARD VIEW
                     ============================================================== -->
                <div class="cat-grid-container">
                    <?php if (empty($categories)): ?>
                        <div style="grid-column: 1 / -1; padding: 60px 20px; text-align: center; background: #FFF; border: 1px solid #E5E7EB; border-radius: 12px;">
                            <div style="font-size: 16px; font-weight: 600; color: #111827;">No matching categories found</div>
                            <p style="font-size: 13px; color: #6B7280; margin: 4px 0 16px;">Try adjusting your search criteria or create a new category.</p>
                            <a href="categories.php" class="btn-emerald-outline">Clear Filters</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): 
                            $isAct = (strtoupper($cat['status'] ?? 'ACTIVE') === 'ACTIVE');
                            $isFeat = ((int)($cat['is_featured'] ?? 0) === 1);
                            $rawImg = $cat['image_url'] ?? '';
                            $thumbSrc = ($rawImg !== '' && (str_starts_with($rawImg, 'http') || str_starts_with($rawImg, '/'))) 
                                ? $rawImg 
                                : ($rawImg !== '' ? ('../' . $rawImg) : '../assets/images/about-commitment-still-life.jpg');
                        ?>
                            <div class="cat-card-item">
                                <div style="height: 140px; background: #F9FAFB; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                    <img src="<?= htmlspecialchars($thumbSrc) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" style="max-height: 90%; max-width: 90%; object-fit: contain;" onerror="this.onerror=null; this.src='../assets/images/about-commitment-still-life.jpg';">
                                    <span style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.65); color: #FFF; font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 600;">
                                        <?= (int)$cat['product_count'] ?> Products
                                    </span>
                                </div>
                                <div style="padding: 16px; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                            <h3 style="font-size: 16px; font-weight: 700; color: #111827; margin: 0; font-family: 'Playfair Display', serif;">
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </h3>
                                            <button type="button" class="star-toggle-btn star-btn-<?= htmlspecialchars($cat['id']) ?>" onclick="window.NutronyxCategory.toggleFeatured('<?= htmlspecialchars($cat['id']) ?>', this)" title="<?= $isFeat ? 'Featured category' : 'Click to make featured' ?>">
                                                <?php if ($isFeat): ?>
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="#F59E0B" stroke="#F59E0B" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                                <?php else: ?>
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                                <?php endif; ?>
                                            </button>
                                        </div>
                                        <p style="font-size: 12.5px; color: #6B7280; margin: 0 0 10px; line-height: 1.4;">
                                            <?= htmlspecialchars($cat['description'] ?? 'No description provided.') ?>
                                        </p>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #F3F4F6; padding-top: 12px; margin-top: 10px;">
                                        <span class="status-pill-dot status-pill-<?= htmlspecialchars($cat['id']) ?> <?= $isAct ? 'active' : 'draft' ?>">
                                            <span class="dot" style="<?= $isAct ? '' : 'background:#6B7280;' ?>"></span>
                                            <span><?= $isAct ? 'Active' : 'Draft' ?></span>
                                        </span>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="products.php?category_id=<?= urlencode($cat['id']) ?>" class="btn-bulk-action" style="padding: 4px 8px; font-size: 11.5px;">View Products</a>
                                            <button type="button" class="btn-bulk-action" style="padding: 4px 8px; font-size: 11.5px;" onclick="window.NutronyxCategory.openEditFromRow('<?= htmlspecialchars($cat['id']) ?>')">Edit</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- ==============================================================
                     TABLE / LIST VIEW (MATCHING PRODUCTS CATALOG EXACTLY)
                     ============================================================== -->
                <div class="catalog-table-wrap">
                    <table class="catalog-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;"></th>
                                <th>Category</th>
                                <th style="text-align: center;">Products</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Featured</th>
                                <th style="text-align: right; padding-right: 20px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 48px 20px;">
                                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #F3F4F6; display: inline-flex; align-items: center; justify-content: center; color: #9CA3AF; margin-bottom: 12px;">
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                        </div>
                                        <div style="font-weight: 600; font-size: 16px; color: #111827;">No matching categories found</div>
                                        <p style="font-size: 13.5px; color: #6B7280; max-width: 420px; margin: 6px auto 16px;">Try adjusting your search criteria, clearing active filters, or creating a new category.</p>
                                        <a href="categories.php" class="btn-emerald-outline" style="font-size: 13px;">Clear All Filters</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): 
                                    $isAct = (strtoupper($cat['status'] ?? 'ACTIVE') === 'ACTIVE');
                                    $isFeat = ((int)($cat['is_featured'] ?? 0) === 1);
                                    $rawImg = $cat['image_url'] ?? '';
                                    $thumbSrc = ($rawImg !== '' && (str_starts_with($rawImg, 'http') || str_starts_with($rawImg, '/'))) 
                                        ? $rawImg 
                                        : ($rawImg !== '' ? ('../' . $rawImg) : '../assets/images/about-commitment-still-life.jpg');
                                ?>
                                    <tr id="row-<?= htmlspecialchars($cat['id']) ?>">
                                        <td style="text-align: center;">
                                            <input type="checkbox" class="catalog-checkbox cat-select-checkbox" value="<?= htmlspecialchars($cat['id']) ?>" onchange="window.NutronyxCategory.handleRowCheckbox()">
                                        </td>

                                        <!-- Category (Image + Name + Description) -->
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 14px;">
                                                <div class="category-thumb-circle" style="cursor: pointer;" onclick="window.NutronyxCategory.openEditFromRow('<?= htmlspecialchars($cat['id']) ?>')">
                                                    <img src="<?= htmlspecialchars($thumbSrc) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" onerror="this.onerror=null; this.src='../assets/images/about-commitment-still-life.jpg';">
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: #111827; font-size: 14px; cursor: pointer;" onclick="window.NutronyxCategory.openEditFromRow('<?= htmlspecialchars($cat['id']) ?>')">
                                                        <?= htmlspecialchars($cat['name']) ?>
                                                    </div>
                                                    <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">
                                                        <?= htmlspecialchars($cat['description'] ?? 'No description provided') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Products Count -->
                                        <td style="text-align: center; font-weight: 600; color: #111827; font-size: 14px;">
                                            <a href="products.php?category_id=<?= urlencode($cat['id']) ?>" style="color: #111827; text-decoration: underline; text-decoration-color: #D1D5DB; text-underline-offset: 3px;">
                                                <?= (int)$cat['product_count'] ?> products
                                            </a>
                                        </td>

                                        <!-- Status Badge -->
                                        <td style="text-align: center;">
                                            <span class="status-pill-dot status-pill-<?= htmlspecialchars($cat['id']) ?> <?= $isAct ? 'active' : 'draft' ?>">
                                                <span class="dot" style="<?= $isAct ? '' : 'background:#6B7280;' ?>"></span>
                                                <span><?= $isAct ? 'Active' : 'Draft' ?></span>
                                            </span>
                                        </td>

                                        <!-- Featured Star Toggle -->
                                        <td style="text-align: center;">
                                            <button type="button" class="star-toggle-btn star-btn-<?= htmlspecialchars($cat['id']) ?>" onclick="window.NutronyxCategory.toggleFeatured('<?= htmlspecialchars($cat['id']) ?>', this)" title="<?= $isFeat ? 'Featured category (click to unmark)' : 'Click to make featured' ?>" style="background: none; border: none; cursor: pointer; padding: 4px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; transition: transform 0.15s ease;">
                                                <?php if ($isFeat): ?>
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="#F59E0B" stroke="#F59E0B" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                                <?php else: ?>
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                                <?php endif; ?>
                                            </button>
                                        </td>

                                        <!-- Actions Dropdown (Matching Product Page UI) -->
                                        <td style="text-align: right; padding-right: 20px;">
                                            <div class="action-dropdown-wrap">
                                                <button type="button" class="action-dots-btn" onclick="window.NutronyxCategory.toggleActionMenu('menu-<?= htmlspecialchars($cat['id']) ?>', event)" title="More options">
                                                    &bull;&bull;&bull;
                                                </button>

                                                <div class="action-dropdown-menu" id="menu-<?= htmlspecialchars($cat['id']) ?>">
                                                    <button type="button" class="action-menu-item" onclick="window.NutronyxCategory.openEditFromRow('<?= htmlspecialchars($cat['id']) ?>')">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                        <span>Edit Category</span>
                                                    </button>
                                                    <a href="products.php?category_id=<?= urlencode($cat['id']) ?>" class="action-menu-item">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                                        <span>View Products (<?= (int)$cat['product_count'] ?>)</span>
                                                    </a>
                                                    <a href="../shop.php?category=<?= urlencode($cat['slug']) ?>" target="_blank" class="action-menu-item">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                                        <span>Preview Storefront</span>
                                                    </a>
                                                    <button type="button" class="action-menu-item" onclick="window.NutronyxCategory.toggleStatus('<?= htmlspecialchars($cat['id']) ?>', this)">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                                        <span>Toggle Status</span>
                                                    </button>
                                                    <div style="height: 1px; background: #F3F4F6; margin: 4px 0;"></div>
                                                    <button type="button" class="action-menu-item danger" onclick="window.NutronyxCategory.confirmDelete('<?= htmlspecialchars($cat['id']) ?>', '<?= htmlspecialchars(addslashes($cat['name'])) ?>')">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                        <span>Delete Category</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- ==================================================================
                 PAGINATION FOOTER (MATCHING MOCKUP EXACTLY)
                 ================================================================== -->
            <div class="catalog-pagination-bar">
                <div class="pagination-info">
                    Showing <strong><?= $startNum ?>–<?= $endNum ?></strong> of <strong><?= $totalCategories ?></strong> categories
                </div>

                <div class="pagination-controls">
                    <div class="per-page-selector">
                        <span>Rows per page</span>
                        <select class="catalog-select" style="height: 32px; padding-top: 0; padding-bottom: 0;" onchange="window.NutronyxCategory.updatePerPage(this.value)">
                            <option value="10" <?= ($perPage === 10) ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= ($perPage === 25) ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= ($perPage === 50) ? 'selected' : '' ?>>50</option>
                        </select>
                    </div>

                    <div class="page-buttons">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&per_page=<?= $perPage ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&featured=<?= urlencode((string)$featuredRaw) ?>&sort=<?= urlencode($sortBy) ?>&view=<?= urlencode($viewMode) ?>" class="btn-page" title="Previous page">
                                &lsaquo;
                            </a>
                        <?php else: ?>
                            <span class="btn-page disabled">&lsaquo;</span>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <?php if ($p === $page): ?>
                                <span class="btn-page active"><?= $p ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $p ?>&per_page=<?= $perPage ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&featured=<?= urlencode((string)$featuredRaw) ?>&sort=<?= urlencode($sortBy) ?>&view=<?= urlencode($viewMode) ?>" class="btn-page">
                                    <?= $p ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&per_page=<?= $perPage ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&featured=<?= urlencode((string)$featuredRaw) ?>&sort=<?= urlencode($sortBy) ?>&view=<?= urlencode($viewMode) ?>" class="btn-page" title="Next page">
                                &rsaquo;
                            </a>
                        <?php else: ?>
                            <span class="btn-page disabled">&rsaquo;</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- ==========================================================================
         MODAL: CREATE / EDIT CATEGORY
         ========================================================================== -->
    <div class="modal-overlay" id="categoryModal">
        <div class="modal-card modal-md">
            <div class="modal-header">
                <h3 class="modal-title" id="categoryModalTitle">Create Category</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('categoryModal')">&times;</button>
            </div>

            <form id="categoryForm" method="POST" action="categories.php" enctype="multipart/form-data">
                <input type="hidden" name="action" id="categoryFormAction" value="create_category">
                <input type="hidden" name="category_id" id="categoryIdField" value="">
                <input type="hidden" name="image_url" id="categoryImageUrl" value="assets/images/about-commitment-still-life.jpg">

                <div class="modal-body" style="padding: 24px; max-height: calc(85vh - 120px); overflow-y: auto;">
                    <div style="display: grid; grid-template-columns: 1fr 140px; gap: 20px; margin-bottom: 18px;">
                        <div>
                            <div class="form-group" style="margin-bottom: 14px;">
                                <label class="form-label" style="font-weight: 600;">Category Name *</label>
                                <input type="text" name="name" id="categoryName" required class="form-control" placeholder="e.g. Health Blends, Raw Powders..." onkeyup="if(!document.getElementById('categoryIdField').value){ document.getElementById('categorySlug').value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }">
                            </div>

                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600;">Slug (URL Identifier)</label>
                                <input type="text" name="slug" id="categorySlug" class="form-control" placeholder="health-blends">
                            </div>
                        </div>

                        <!-- Image Preview Box -->
                        <div style="text-align: center;">
                            <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 6px;">Image</label>
                            <div style="width: 120px; height: 120px; border-radius: 12px; border: 1px solid #E5E7EB; background: #F9FAFB; overflow: hidden; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; cursor: pointer;" onclick="document.getElementById('categoryFileInput').click()">
                                <img id="categoryPhotoPreview" src="../assets/images/about-commitment-still-life.jpg" alt="Preview" style="width: 100%; height: 100%; object-fit: contain; padding: 4px;">
                            </div>
                            <input type="file" id="categoryFileInput" name="category_image_file" accept="image/*" style="display: none;" onchange="window.NutronyxCategory.handleImageSelect(this)">
                            <button type="button" class="btn-bulk-action" style="font-size: 11px; padding: 4px 10px;" onclick="document.getElementById('categoryFileInput').click()">Upload File</button>
                        </div>
                    </div>

                    <!-- Preset images quick select -->
                    <div style="margin-bottom: 18px; background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 10px 12px;">
                        <span style="font-size: 11.5px; font-weight: 600; color: #4B5563; display: block; margin-bottom: 6px;">Quick Presets:</span>
                        <div style="display: flex; gap: 8px; align-items: center; overflow-x: auto;">
                            <img src="../assets/images/beetroot.png" alt="Beetroot" title="Beetroot Bowl" style="width: 32px; height: 32px; object-fit: contain; cursor: pointer; border: 1px solid #E5E7EB; border-radius: 6px; background: #FFF;" onclick="window.NutronyxCategory.selectPresetImage('assets/images/beetroot.png')">
                            <img src="../assets/images/ginger.png" alt="Ginger" title="Raw Ginger Powder" style="width: 32px; height: 32px; object-fit: contain; cursor: pointer; border: 1px solid #E5E7EB; border-radius: 6px; background: #FFF;" onclick="window.NutronyxCategory.selectPresetImage('assets/images/ginger.png')">
                            <img src="../assets/images/amla.png" alt="Amla" title="Fruit Amla" style="width: 32px; height: 32px; object-fit: contain; cursor: pointer; border: 1px solid #E5E7EB; border-radius: 6px; background: #FFF;" onclick="window.NutronyxCategory.selectPresetImage('assets/images/amla.png')">
                            <img src="../assets/images/carrot.png" alt="Carrot" title="Vegetable Carrots" style="width: 32px; height: 32px; object-fit: contain; cursor: pointer; border: 1px solid #E5E7EB; border-radius: 6px; background: #FFF;" onclick="window.NutronyxCategory.selectPresetImage('assets/images/carrot.png')">
                            <img src="../assets/images/about-story-mortar.jpg" alt="Mortar" title="Mortar Bowl" style="width: 32px; height: 32px; object-fit: cover; cursor: pointer; border: 1px solid #E5E7EB; border-radius: 6px; background: #FFF;" onclick="window.NutronyxCategory.selectPresetImage('assets/images/about-story-mortar.jpg')">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" style="font-weight: 600;">Description</label>
                        <textarea name="description" id="categoryDescription" rows="2" class="form-control" placeholder="Nutritious daily health blends..."></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600;">Status</label>
                            <select name="status" id="categoryStatus" class="form-control">
                                <option value="ACTIVE">Active (Storefront Visible)</option>
                                <option value="DRAFT">Draft (Hidden)</option>
                            </select>
                        </div>

                        <div class="form-group" style="display: flex; flex-direction: column; justify-content: center;">
                            <label class="form-label" style="font-weight: 600;">Featured Category</label>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; cursor: pointer; margin-top: 6px;">
                                <input type="checkbox" name="is_featured" id="categoryIsFeatured" value="1" class="catalog-checkbox">
                                <span>Display in Storefront Spotlight</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600;">Subcategories (Comma separated)</label>
                        <input type="text" name="subcategories" id="categorySubcategories" class="form-control" placeholder="Daily Powders, Cellular Nutrition, Superfood Extracts">
                        <small style="color: #6B7280; font-size: 12px; margin-top: 4px; display: block;">These appear as secondary filter options and variant classifications.</small>
                    </div>
                </div>

                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-emerald-outline" onclick="window.NutronyxModal.close('categoryModal')">Cancel</button>
                    <button type="submit" class="btn-emerald-brand" id="categorySubmitBtn">Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL: EXPORT CATEGORIES
         ========================================================================== -->
    <div class="modal-overlay" id="exportCategoryModal">
        <div class="modal-card modal-sm">
            <div class="modal-header">
                <h3 class="modal-title" style="font-size: 18px; font-weight: 700; color: #111827;">Export Categories</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('exportCategoryModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size: 13px; color: #6B7280; margin-bottom: 14px;">Select the scope of category data you wish to download as CSV:</p>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button type="button" class="btn-emerald-outline" onclick="window.location.href='export.php?type=categories'" style="justify-content: flex-start; padding: 12px 16px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <span>Export All Categories</span>
                    </button>
                    <button type="button" class="btn-emerald-outline" id="btnExportCatSelected" onclick="window.NutronyxCategory.exportSelected()" style="display: none; justify-content: flex-start; padding: 12px 16px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                        <span id="btnExportCatSelectedText">Export Selected Checkboxes Only</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL: IMPORT CATEGORIES
         ========================================================================== -->
    <div class="modal-overlay" id="importCategoryModal">
        <div class="modal-card modal-md">
            <div class="modal-header">
                <h3 class="modal-title" style="font-size: 18px; font-weight: 700; color: #111827;">Import Categories from CSV</h3>
                <button type="button" class="modal-close-btn" onclick="window.NutronyxModal.close('importCategoryModal')">&times;</button>
            </div>
            <form method="POST" action="categories.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_categories">
                <div class="modal-body" style="padding: 24px;">
                    <p style="font-size: 13.5px; color: #4B5563; margin-bottom: 14px;">Upload a CSV file containing category definitions. The file should have columns in this order: <code>Name, Slug, Description, Status, Is Featured, Subcategories</code></p>
                    <div style="border: 2px dashed #D1D5DB; border-radius: 12px; padding: 28px; text-align: center; background: #F9FAFB; cursor: pointer;" onclick="document.getElementById('catImportFileInput').click()">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" style="margin: 0 auto 10px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <div style="font-size: 14px; font-weight: 600; color: #111827;">Choose CSV file to upload</div>
                        <div style="font-size: 12px; color: #6B7280; margin-top: 4px;">Supports UTF-8 formatted .csv files up to 10MB</div>
                        <input type="file" id="catImportFileInput" name="csv_file" accept=".csv" required style="display: none;" onchange="if(this.files[0]){ document.getElementById('catImportFileName').textContent = this.files[0].name; }">
                    </div>
                    <div id="catImportFileName" style="margin-top: 8px; font-size: 12.5px; font-weight: 600; color: #065F46;"></div>
                </div>
                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-emerald-outline" onclick="window.NutronyxModal.close('importCategoryModal')">Cancel</button>
                    <button type="submit" class="btn-emerald-brand">Start Import</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
    // Close action dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.action-dropdown-wrap')) {
            document.querySelectorAll('.action-dropdown-menu.show').forEach(m => {
                m.classList.remove('show');
                m.style.top = '';
                m.style.bottom = '';
            });
        }
    });
    </script>
</body>
</html>
