<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

use Nutronyx\Services\ReviewService;
use Nutronyx\Services\ProductService;

$currentPage = 'shop';
$reviewNotice = '';
$reviewError = '';
$reviewSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_customer_review') {
    $submitRes = ReviewService::submitCustomerReview($_POST);
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($submitRes);
        exit;
    }
    if ($submitRes['success']) {
        $reviewSuccess = true;
        $reviewNotice = $submitRes['message'];
    } else {
        $reviewError = $submitRes['error'] ?? 'Unable to submit review.';
    }
}

// Fetch approved reviews for the storefront
$storeReviews = ReviewService::getApprovedReviewsForProduct('prod_abc_powder', 6);
if (count($storeReviews) < 3) {
    $storeReviews = ReviewService::getApprovedReviewsForProduct('all', 6);
}
$ratingStats = ReviewService::getProductRatingStats('prod_abc_powder');

// Fetch all active products and their variants for the catalog section
$catalogProducts = ProductService::getProducts(null, 'ACTIVE');
$catalogProductsWithDetails = [];
foreach ($catalogProducts as $cProd) {
    $pDet = ProductService::getProductDetails($cProd['id']);
    if ($pDet) {
        $catalogProductsWithDetails[] = $pDet;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The NUTRONYX Collection &mdash; Nature, Made Part of Your Everyday</title>
    <meta name="description" content="Discover thoughtfully created wellness essentials inspired by nature and designed for everyday rituals. Explore the NUTRONYX ABC+ collection.">
    
    <!-- Google Fonts: Cormorant Garamond, Playfair Display & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Global Base & Component Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261117_v2">
    <!-- Scoped Shop Page Stylesheet -->
    <link rel="stylesheet" href="assets/css/shop.css?v=20261117_v2">
</head>
<body class="nutronyx-body shop-page-body">

    <!-- Header Navigation with Active Shop State -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main id="main-content">
        <!-- ==========================================================================
             SECTION 01: EDITORIAL PRODUCT COLLECTION
             Concept: "Find Your Everyday Ritual."
             Art-directed flagship presentation of NUTRONYX ABC+
             ========================================================================== -->
        <section class="section-editorial-collection" id="collection" aria-label="Section 02: The Product Collection">
            <div class="collection-inner-container">
                
                <!-- 1. EDITORIAL SECTION HEADER -->
                <div class="collection-header-stage">
                    <div class="collection-eyebrow-wrap">
                        <span class="eyebrow-dash" aria-hidden="true"></span>
                        <span class="collection-eyebrow">THE COLLECTION</span>
                    </div>

                    <h2 class="collection-main-heading">
                        <span class="collection-heading-mask">
                            <span class="heading-line">Find Your</span>
                        </span>
                        <span class="collection-heading-mask">
                            <span class="heading-line"><em class="heading-italic">Everyday Ritual.</em></span>
                        </span>
                    </h2>

                    <p class="collection-supporting-text">
                        Explore thoughtfully crafted nutrition essentials inspired by nature, designed to fit effortlessly into everyday life.
                    </p>
                </div>

                <!-- 2. FLAGSHIP ASYMMETRICAL EDITORIAL STAGE (ABC+) -->
                <div class="flagship-editorial-stage">

                    <!-- A. Left Side: Oversized Product Stage -->
                    <div class="flagship-visual-stage" aria-label="Flagship Product Image Carousel">
                        <!-- Left & Right Carousel Scroll Navigation -->
                        <button type="button" class="flagship-nav-btn prev" onclick="stepFlagshipImg(-1)" aria-label="Previous product image">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </button>
                        <button type="button" class="flagship-nav-btn next" onclick="stepFlagshipImg(1)" aria-label="Next product image">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>

                        <!-- Main Image Display (Expanded & Crisp) -->
                        <div class="flagship-main-img-wrap">
                            <img id="flagshipActiveImg" 
                                 src="assets/images/product-showcase-botanical.png" 
                                 alt="NUTRONYX ABC+ Daily Wellness Powder with fresh whole foods" 
                                 class="flagship-main-img" 
                                 loading="eager">
                        </div>

                        <!-- Minimal Carousel Dots Indicator -->
                        <div class="flagship-dots" role="tablist" aria-label="Product Image Navigation Dots">
                            <button type="button" class="flagship-dot active" onclick="setFlagshipIndex(0)" role="tab" aria-selected="true" aria-label="Image 1 of 3: Product Plinth"></button>
                            <button type="button" class="flagship-dot" onclick="setFlagshipIndex(1)" role="tab" aria-selected="false" aria-label="Image 2 of 3: Front Pouch Packaging"></button>
                            <button type="button" class="flagship-dot" onclick="setFlagshipIndex(2)" role="tab" aria-selected="false" aria-label="Image 3 of 3: Back Pouch Nutrition Information"></button>
                        </div>
                    </div>

                    <!-- B. Right Side: Editorial Dossier & Direct Commerce -->
                    <div class="flagship-editorial-dossier">
                        <span class="dossier-eyebrow">SIGNATURE DAILY WELLNESS POWDER</span>
                        <h3 class="dossier-title">NUTRONYX ABC+</h3>
                        
                        <p class="dossier-desc">
                            A thoughtful blend of Amla, Beetroot, Carrot, Ginger and Lemon for a simple, nourishing daily routine. Crafted with 100% whole raw foods and zero refined sugars.
                        </p>

                        <!-- Authentic Specifications Bar -->
                        <div class="dossier-specs-row" aria-label="Product Specifications">
                            <div class="dossier-spec-item">
                                <strong>100% Natural Foods</strong>
                            </div>
                            <span class="dossier-spec-sep" aria-hidden="true">|</span>
                            <div class="dossier-spec-item">
                                <span>Daily Morning Scoop</span>
                            </div>
                        </div>

                        <!-- Five Whole-Food Ingredients Breakdown -->
                        <div class="dossier-ingredients-box">
                            <span class="dossier-ing-label">Five Whole-Food Ingredients:</span>
                            <div class="dossier-ingredients-list">
                                <span class="dossier-ing-chip"><img src="assets/images/amla.png" alt="Amla" style="width: 16px; height: 16px; object-fit: contain;"> <strong>Amla</strong> Immunity</span>
                                <span class="dossier-ing-chip"><img src="assets/images/beetroot.png" alt="Beetroot" style="width: 16px; height: 16px; object-fit: contain;"> <strong>Beetroot</strong> Stamina</span>
                                <span class="dossier-ing-chip"><img src="assets/images/carrot.png" alt="Carrot" style="width: 16px; height: 16px; object-fit: contain;"> <strong>Carrot</strong> Vitality</span>
                                <span class="dossier-ing-chip"><img src="assets/images/ginger.png" alt="Ginger" style="width: 16px; height: 16px; object-fit: contain;"> <strong>Ginger</strong> Digestion</span>
                                <span class="dossier-ing-chip"><img src="assets/images/lemon-slice.png" alt="Lemon" style="width: 16px; height: 16px; object-fit: contain;"> <strong>Lemon</strong> Freshness</span>
                            </div>
                        </div>

                        <!-- Select Size Pills (Exact match to Reference Image) -->
                        <div class="dossier-size-selector-wrap" aria-label="Select Size">
                            <div class="size-selector-header">
                                <span class="size-selector-title">Select Size</span>
                                <span class="size-selector-guide" id="sizeServingsGuide"><?= (($_GET['variant'] ?? '') === 'var_abc_500g') ? '2 Months Ritual (Save 17%)' : '1 Month Ritual' ?></span>
                            </div>
                            <div class="size-pills-row" role="radiogroup" aria-label="Choose product size">
                                <button type="button" 
                                        class="size-pill-btn <?= (($_GET['variant'] ?? '') === 'var_abc_500g') ? '' : 'active' ?>" 
                                        id="pill_var_abc_250g" 
                                        role="radio"
                                        aria-checked="<?= (($_GET['variant'] ?? '') === 'var_abc_500g') ? 'false' : 'true' ?>"
                                        onclick="selectDossierSize('var_abc_250g', 299, '1 Month Ritual', this)">
                                    <span class="pill-weight">200gm</span>
                                    <span class="pill-badge">Standard Pack</span>
                                </button>
                                <button type="button" 
                                        class="size-pill-btn <?= (($_GET['variant'] ?? '') === 'var_abc_500g') ? 'active' : '' ?>" 
                                        id="pill_var_abc_500g" 
                                        role="radio"
                                        aria-checked="<?= (($_GET['variant'] ?? '') === 'var_abc_500g') ? 'true' : 'false' ?>"
                                        onclick="selectDossierSize('var_abc_500g', 499, '2 Months Ritual (Save 17%)', this)">
                                    <span class="pill-weight">400gm</span>
                                    <span class="pill-badge pill-badge-value">Best Value</span>
                                </button>
                            </div>
                        </div>

                        <!-- Actual Price Display -->
                        <div class="dossier-price-row">
                            <span class="dossier-price-val" id="dossierPriceDisplay"><?= (($_GET['variant'] ?? '') === 'var_abc_500g') ? '₹499' : '₹299' ?></span>
                            <span class="dossier-price-sub" id="dossierPriceSub">Inclusive of all taxes</span>
                        </div>

                        <!-- Direct Commerce Actions: Quantity Stepper + Add to Cart -->
                        <div class="dossier-commerce-group">
                            <div class="dossier-qty-stepper" aria-label="Select Quantity">
                                <button type="button" class="btn-dossier-qty" onclick="changeDossierQty(-1)" aria-label="Decrease quantity">&minus;</button>
                                <span class="dossier-qty-val" id="dossierQtyVal">1</span>
                                <button type="button" class="btn-dossier-qty" onclick="changeDossierQty(1)" aria-label="Increase quantity">&plus;</button>
                            </div>

                            <button type="button" class="btn-dossier-add" onclick="addDossierToCart(this)" aria-label="Add NUTRONYX ABC+ to cart">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                    <line x1="3" y1="6" x2="21" y2="6"></line>
                                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                                </svg>
                                <span>ADD TO CART &bull; <span id="dossierTotalBtnPrice"><?= (($_GET['variant'] ?? '') === 'var_abc_500g') ? '₹499' : '₹299' ?></span></span>
                            </button>
                        </div>

                        <!-- Purity Guarantees -->
                        <div class="dossier-guarantees">
                            <div class="guarantee-note">
                                <span class="check-icon">&check;</span>
                                <span>Pan-India Delivery &bull; Shipping Charges Apply</span>
                            </div>
                            <div class="guarantee-note">
                                <span class="check-icon">&check;</span>
                                <span>100% Raw Ingredients &bull; Zero Additives</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==========================================================
                     1. THE PURITY & VALUE BADGES RIBBON
                     ========================================================== -->
                <div class="shop-value-ribbon">
                    <div class="value-badge-item">
                        <div class="badge-icon-wrap" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#25533A" stroke-width="1.8">
                                <path d="M12 2C8 6 6 10 6 13a6 6 0 0 0 12 0c0-3-2-7-6-11z"/>
                                <path d="M12 8v10"/>
                            </svg>
                        </div>
                        <div class="badge-text-wrap">
                            <span class="badge-title">100% Natural</span>
                            <span class="badge-sub">Whole-Food Ingredients</span>
                        </div>
                    </div>

                    <div class="value-badge-item">
                        <div class="badge-icon-wrap" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#25533A" stroke-width="1.8">
                                <path d="M10 2v7.31L4.69 18.25A2 2 0 0 0 6.4 21h11.2a2 2 0 0 0 1.71-2.75L14 9.31V2"/>
                                <line x1="8.5" y1="2" x2="15.5" y2="2"/>
                                <line x1="2" y1="2" x2="22" y2="22"/>
                            </svg>
                        </div>
                        <div class="badge-text-wrap">
                            <span class="badge-title">No Added Sugar</span>
                            <span class="badge-sub">No Artificial Preservatives</span>
                        </div>
                    </div>

                    <div class="value-badge-item">
                        <div class="badge-icon-wrap" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#25533A" stroke-width="1.8">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                <polyline points="9 12 11 14 15 10"/>
                            </svg>
                        </div>
                        <div class="badge-text-wrap">
                            <span class="badge-title">Quality Focused</span>
                            <span class="badge-sub">Thoughtfully Crafted</span>
                        </div>
                    </div>

                    <div class="value-badge-item">
                        <div class="badge-icon-wrap" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#25533A" stroke-width="1.8">
                                <rect x="1" y="3" width="15" height="13"/>
                                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                                <circle cx="5.5" cy="18.5" r="2.5"/>
                                <circle cx="18.5" cy="18.5" r="2.5"/>
                            </svg>
                        </div>
                        <div class="badge-text-wrap">
                            <span class="badge-title">Pan-India Delivery</span>
                            <span class="badge-sub">Shipping Charges Apply</span>
                        </div>
                    </div>
                </div>

                <!-- ==========================================================
                     2. THE POWER OF ABC+ — 5 WHOLE BOTANICALS. ONE DAILY RITUAL.
                     ========================================================== -->
                <div class="shop-power-section" id="power-of-abc">

                    <div class="power-container-inner">
                        <div class="power-narrative-col">
                            <span class="power-eyebrow">THE POWER OF ABC+</span>
                            <h3 class="power-title">
                                <span>5 Natural Ingredients.</span>
                                <span>One Thoughtful Blend.</span>
                            </h3>
                            <p class="power-desc">
                                A carefully crafted combination of Amla, Beetroot, Carrot, Ginger and Lemon, bringing natural plant-based goodness into your everyday routine.
                            </p>
                            <a href="#shop-catalog" class="btn-power-explore" aria-label="Explore the products">
                                <span>Explore the products</span>
                                <span class="btn-arrow" aria-hidden="true">&rarr;</span>
                            </a>
                        </div>

                        <!-- 5 Botanical Ingredient Cards -->
                        <div class="power-cards-grid">
                            <!-- Amla -->
                            <div class="botanical-card" data-tilt>
                                <div class="botanical-thumb-wrap">
                                    <img src="assets/images/amla.png" alt="Amla (Indian Gooseberry)" class="botanical-thumb-img" loading="lazy">
                                </div>
                                <h4 class="botanical-name">Amla</h4>
                                <span class="botanical-benefit">Immunity &amp; Antioxidants</span>
                            </div>

                            <!-- Beetroot -->
                            <div class="botanical-card" data-tilt>
                                <div class="botanical-thumb-wrap">
                                    <img src="assets/images/beetroot.png" alt="Fresh Beetroot" class="botanical-thumb-img" loading="lazy">
                                </div>
                                <h4 class="botanical-name">Beetroot</h4>
                                <span class="botanical-benefit">Stamina &amp; Blood Health</span>
                            </div>

                            <!-- Carrot -->
                            <div class="botanical-card" data-tilt>
                                <div class="botanical-thumb-wrap">
                                    <img src="assets/images/carrot.png" alt="Crisp Carrot" class="botanical-thumb-img" loading="lazy">
                                </div>
                                <h4 class="botanical-name">Carrot</h4>
                                <span class="botanical-benefit">Vitality &amp; Eye Health</span>
                            </div>

                            <!-- Ginger -->
                            <div class="botanical-card" data-tilt>
                                <div class="botanical-thumb-wrap">
                                    <img src="assets/images/ginger.png" alt="Fresh Ginger" class="botanical-thumb-img" loading="lazy">
                                </div>
                                <h4 class="botanical-name">Ginger</h4>
                                <span class="botanical-benefit">Digestion &amp; Metabolism</span>
                            </div>

                            <!-- Lemon -->
                            <div class="botanical-card" data-tilt>
                                <div class="botanical-thumb-wrap">
                                    <img src="assets/images/lemon.png" alt="Fresh Lemon" class="botanical-thumb-img" loading="lazy">
                                </div>
                                <h4 class="botanical-name">Lemon</h4>
                                <span class="botanical-benefit">Freshness &amp; Nutrient Absorption</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==========================================================
                     THE BOTANICAL COLLECTION — PRODUCTS & MULTI-SKU CARDS
                     Dynamically renders all active store products with variants,
                     live pricing per SKU, and direct Add-to-Cart.
                     ========================================================== -->
                <section class="shop-catalog-section" id="shop-catalog" aria-label="NUTRONYX Product Collection">
                    <div class="shop-catalog-container">
                        <div class="shop-catalog-header">
                            <span class="catalog-eyebrow">THE NUTRONYX COLLECTION</span>
                            <h2 class="catalog-title">Explore Our Everyday Essentials</h2>
                            <p class="catalog-sub">Discover thoughtfully crafted natural nutrition products, made with carefully selected ingredients to complement your everyday wellness routine.</p>
                        </div>

                        <div class="shop-catalog-grid">
                            <?php foreach ($catalogProductsWithDetails as $cProduct): ?>
                                <?php
                                    $firstVar = $cProduct['variants'][0] ?? null;
                                    $pImg = !empty($cProduct['image_url']) ? $cProduct['image_url'] : 'assets/images/product-showcase-botanical.png';
                                    $cProdId = htmlspecialchars($cProduct['id']);
                                    $cFirstVid = htmlspecialchars($firstVar['id'] ?? '');
                                ?>
                                <div class="shop-product-card" id="card-<?= $cProdId ?>" data-product-id="<?= $cProdId ?>" data-active-variant="<?= $cFirstVid ?>">
                                    <!-- Product Visual Stage -->
                                    <div class="product-card-visual">
                                        <div class="product-badge-organic">100% Whole Food</div>
                                        <img src="<?= htmlspecialchars($pImg) ?>" alt="<?= htmlspecialchars($cProduct['title']) ?>" class="product-card-img" id="img-<?= $cProdId ?>" loading="lazy">
                                    </div>

                                    <!-- Product Content -->
                                    <div class="product-card-body">
                                        <div class="product-card-meta">
                                            <span class="product-card-cat"><?= htmlspecialchars($cProduct['category_name'] ?: 'Daily Wellness') ?></span>
                                            <?php if (!empty($cProduct['primary_sku'])): ?>
                                                <span class="product-card-sku" id="sku-badge-<?= $cProdId ?>"><?= htmlspecialchars($firstVar['sku'] ?? $cProduct['primary_sku']) ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <h3 class="product-card-name"><?= htmlspecialchars($cProduct['title']) ?></h3>
                                        <p class="product-card-tagline"><?= htmlspecialchars($cProduct['short_description'] ?: 'Pure nutrition designed for simple everyday wellness rituals.') ?></p>

                                        <!-- SKU / Size Selection Pills -->
                                        <?php if (!empty($cProduct['variants']) && count($cProduct['variants']) > 1): ?>
                                            <div class="product-sku-picker" aria-label="Select Size / SKU">
                                                <span class="sku-picker-label">Select SKU / Size:</span>
                                                <div class="sku-pills-wrap">
                                                    <?php foreach ($cProduct['variants'] as $vIdx => $vr): ?>
                                                        <?php
                                                            $vrPrice = (float)($vr['price_amount'] / 100);
                                                            $vrCompare = !empty($vr['compare_at_amount']) ? (float)($vr['compare_at_amount'] / 100) : 0;
                                                            $vrImg = !empty($vr['image_url']) ? $vr['image_url'] : $pImg;
                                                        ?>
                                                        <button type="button" 
                                                                class="catalog-sku-btn <?= $vIdx === 0 ? 'active' : '' ?>"
                                                                data-product-id="<?= $cProdId ?>"
                                                                data-variant-id="<?= htmlspecialchars($vr['id']) ?>"
                                                                data-sku="<?= htmlspecialchars($vr['sku']) ?>"
                                                                data-price="<?= number_format($vrPrice, 2, '.', '') ?>"
                                                                data-price-formatted="₹<?= number_format($vrPrice) ?>"
                                                                data-compare="<?= $vrCompare > 0 ? '₹' . number_format($vrCompare) : '' ?>"
                                                                data-img="<?= htmlspecialchars($vrImg) ?>"
                                                                onclick="switchCatalogVariant(this)">
                                                            <span class="sku-btn-title"><?= htmlspecialchars($vr['title']) ?></span>
                                                            <span class="sku-btn-price">₹<?= number_format($vrPrice) ?></span>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Pricing Row & Action -->
                                        <div class="product-card-footer">
                                            <div class="product-price-block">
                                                <div class="price-primary-row">
                                                    <span class="price-currency">₹</span>
                                                    <span class="price-amount" id="price-<?= $cProdId ?>"><?= number_format(($firstVar['price_amount'] ?? 29900) / 100) ?></span>
                                                    <?php if (!empty($firstVar['compare_at_amount'])): ?>
                                                        <span class="price-compare" id="compare-<?= $cProdId ?>">₹<?= number_format($firstVar['compare_at_amount'] / 100) ?></span>
                                                    <?php else: ?>
                                                        <span class="price-compare" id="compare-<?= $cProdId ?>" style="display:none;"></span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="price-tax-note">Inclusive of all taxes &bull; Free Delivery</span>
                                            </div>

                                            <button type="button" 
                                                    class="btn-catalog-cart" 
                                                    id="btn-add-<?= $cProdId ?>"
                                                    onclick="addCatalogProductToCart('<?= $cProdId ?>', this)">
                                                <span>Add to Cart</span>
                                                <span class="btn-cart-arrow" aria-hidden="true">&rarr;</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <!-- ==========================================================
                     3. A SIMPLE DAILY RITUAL — REAL NUTRITION FOR REAL LIFE
                     ========================================================== -->
                <div class="shop-ritual-banner-stage">
                    <div class="ritual-panoramic-card">
                        <!-- Left Overlay Content -->
                        <div class="ritual-banner-content">
                            <span class="ritual-banner-eyebrow">A SIMPLE DAILY RITUAL</span>
                            <h3 class="ritual-banner-headline">
                                <span>Real Nutrition</span>
                                <span>for Real Life.</span>
                            </h3>

                            <ul class="ritual-checklist">
                                <li>
                                    <span class="checklist-icon" aria-hidden="true">&check;</span>
                                    <span>Easy to incorporate into your everyday routine</span>
                                </li>
                                <li>
                                    <span class="checklist-icon" aria-hidden="true">&check;</span>
                                    <span>Made with carefully selected natural ingredients</span>
                                </li>
                                <li>
                                    <span class="checklist-icon" aria-hidden="true">&check;</span>
                                    <span>Thoughtfully crafted for everyday nourishment</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Right / Background Visual Canvas -->
                        <div class="ritual-banner-visual">
                            <img src="assets/images/shop-ritual-banner.jpg" 
                                 alt="NUTRONYX ABC+ jar alongside a freshly mixed ruby-red smoothie with fresh whole amla, beetroot, carrots, ginger, and lemon on an oak kitchen counter" 
                                 class="ritual-banner-img" 
                                 loading="lazy">
                        </div>
                    </div>
                </div>

                <!-- ==========================================================
                     4. REAL PEOPLE, REAL RESULTS — LOVED BY WELLNESS SEEKERS
                     ========================================================== -->
                <div class="shop-reviews-stage" id="shop-customer-reviews">

                    <div class="reviews-header">
                        <span class="reviews-eyebrow">CUSTOMER EXPERIENCES</span>
                        <h3 class="reviews-headline">Loved by Wellness Seekers</h3>
                        <p class="reviews-sub">Discover what our customers have to say about their NUTRONYX experience.</p>

                        <?php if ($reviewSuccess): ?>
                            <div class="review-success-card" style="margin: 22px auto 26px auto; max-width: 620px; padding: 26px 24px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px; text-align: center; box-shadow: 0 4px 18px rgba(0,0,0,0.05);">
                                <div style="width: 36px; height: 36px; border-radius: 50%; border: 2.2px solid #144D29; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; color: #144D29;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#144D29" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </div>
                                <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 19px; font-weight: 700; color: #111827; margin: 0 0 8px 0; letter-spacing: -0.2px;">Thank You for Sharing Your Experience!</h4>
                                <p style="font-size: 14px; color: #4B5563; margin: 0; line-height: 1.6;">
                                    Your review has been submitted successfully. We appreciate your feedback and thank you for being part of the NUTRONYX community.
                                </p>
                            </div>
                        <?php elseif (!empty($reviewError)): ?>
                            <div style="margin: 16px auto; max-width: 500px; padding: 12px 18px; background: #FDE8E8; border: 1px solid #F98080; border-radius: 8px; color: #9B1C1C; font-size: 13.5px; font-weight: 600;">
                                <?= htmlspecialchars($reviewError) ?>
                            </div>
                        <?php endif; ?>

                        <div class="reviews-cta-wrap">
                            <button type="button" class="btn-write-review" onclick="openStoreReviewModal()">
                                <span>Write a Review</span>
                            </button>
                        </div>
                    </div>

                    <!-- Reviews Grid / Carousel Slider -->
                    <div class="shop-reviews-wrapper">
                        <button type="button" class="btn-review-nav btn-review-prev" aria-label="Previous customer review">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </button>

                        <div class="shop-reviews-grid" id="shopReviewsGrid">
                            <?php foreach ($storeReviews as $idx => $sRev): ?>
                                <div class="shop-review-card <?= $idx === 1 ? 'is-featured' : '' ?>" data-index="<?= $idx ?>">
                                    <div class="review-card-top">
                                        <div style="width: 48px; height: 48px; border-radius: 50%; background: #144D29; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; flex-shrink: 0;">
                                            <?= strtoupper(substr($sRev['customer_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <div class="review-stars-quote">
                                            <div class="review-stars" aria-label="<?= (int)$sRev['rating'] ?> out of 5 stars">
                                                <?= str_repeat('★', (int)($sRev['rating'] ?? 5)) ?>
                                            </div>
                                            <?php if (!empty($sRev['title'])): ?>
                                                <div style="font-weight: 700; font-size: 14px; color: #14221A; margin-bottom: 4px;">
                                                    <?= htmlspecialchars($sRev['title']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <blockquote class="review-text">
                                                &ldquo;<?= htmlspecialchars($sRev['comment'] ?? '') ?>&rdquo;
                                            </blockquote>

                                            <?php if (!empty($sRev['admin_reply'])): ?>
                                                <div style="margin-top: 10px; padding: 8px 12px; background: #F0FDF4; border-left: 3px solid #166534; border-radius: 4px; font-size: 11.5px; color: #166534; text-align: left; line-height: 1.4;">
                                                    <strong>Nutronyx Response:</strong> <?= htmlspecialchars($sRev['admin_reply']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="review-author-meta">
                                        <div class="author-name-line">
                                            <span class="author-name"><?= htmlspecialchars($sRev['customer_name'] ?? 'Verified Customer') ?></span>
                                            <?php if (!empty($sRev['is_verified_buyer']) && (int)$sRev['is_verified_buyer'] === 1): ?>
                                                <span class="author-verified-tag" title="Verified Customer">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="#2E7D4E">
                                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                                    </svg>
                                                    <span>Verified Buyer</span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($sRev['product_title'])): ?>
                                            <div class="author-product-info">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.65; flex-shrink: 0;"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"></path><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"></path><path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"></path></svg>
                                                <span class="author-product-title"><?= htmlspecialchars($sRev['product_title']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="btn-review-nav btn-review-next" aria-label="Next customer review">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    </div>

                    <!-- Pagination Indicator Dots -->
                    <div class="shop-reviews-dots">
                        <?php foreach ($storeReviews as $idx => $sRev): ?>
                            <span class="r-dot <?= $idx === 1 ? 'active' : '' ?>" data-index="<?= $idx ?>"></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Cart Toast Notification -->
    <div class="cart-toast" id="cartToast" aria-live="polite">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8A9B70" stroke-width="2.5">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <span id="cartToastMsg">Added NUTRONYX ABC+ to your daily ritual cart!</span>
    </div>

    <!-- ==========================================================================
         SIGNATURE FOOTER
         Preserved luxury signature branding with FINSTICS experience badge
         ========================================================================== -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- GSAP 3 & ScrollTrigger CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    
    <!-- Shop Hero & Section 02 Interaction Script -->
    <script src="assets/js/shop.js?v=20261117_v2"></script>

    <!-- Footer Common Handlers -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const finsticsBadge = document.querySelector(".finstics-box-link");
        if (finsticsBadge) {
            finsticsBadge.addEventListener("click", () => {
                window.location.href = "tel:8340956469";
            });
        }
    });
    </script>

    <!-- Customer Review Submission Modal (Top-level Viewport Portal) -->
    <div id="storeReviewModal" class="nutronyx-modal-backdrop" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle">
        <div class="nutronyx-modal-dialog">
            <button type="button" class="nutronyx-modal-close" onclick="closeStoreReviewModal()" aria-label="Close modal">&times;</button>
            
            <!-- Review Form View -->
            <div id="storeReviewFormWrap">
                <h3 id="reviewModalTitle" class="review-modal-title">Share Your Experience</h3>
                <p class="review-modal-sub">We value genuine feedback from our wellness community.</p>

                <div id="reviewModalErrorNotice" class="review-modal-alert-error" style="display: none;"></div>

                <form id="storeReviewForm" method="POST" action="shop.php" onsubmit="handleReviewSubmit(event)">
                    <input type="hidden" name="action" value="submit_customer_review">
                    <input type="hidden" name="product_id" value="prod_abc_powder">

                    <div class="review-form-group">
                        <label for="reviewRating" class="review-form-label">Your Rating <span class="required">*</span></label>
                        <select id="reviewRating" name="rating" required class="review-form-select">
                            <option value="5">★★★★★ — Exceptional</option>
                            <option value="4">★★★★☆ — Very Good</option>
                            <option value="3">★★★☆☆ — Good</option>
                            <option value="2">★★☆☆☆ — Fair</option>
                            <option value="1">★☆☆☆☆ — Poor</option>
                        </select>
                    </div>

                    <div class="review-form-row">
                        <div class="review-form-group">
                            <label for="reviewName" class="review-form-label">Your Name <span class="required">*</span></label>
                            <input type="text" id="reviewName" name="customer_name" required placeholder="e.g. Meenakshi S." class="review-form-input">
                        </div>
                        <div class="review-form-group">
                            <label for="reviewEmail" class="review-form-label">Email Address</label>
                            <input type="email" id="reviewEmail" name="customer_email" placeholder="you@example.com" class="review-form-input">
                        </div>
                    </div>

                    <div class="review-form-group">
                        <label for="reviewTitle" class="review-form-label">Review Title</label>
                        <input type="text" id="reviewTitle" name="title" placeholder="Give your experience a short title" class="review-form-input">
                    </div>

                    <div class="review-form-group">
                        <label for="reviewComment" class="review-form-label">Your Experience <span class="required">*</span></label>
                        <textarea id="reviewComment" name="comment" required rows="4" placeholder="Tell us about the taste, quality, packaging and your overall experience with NUTRONYX" class="review-form-textarea"></textarea>
                    </div>

                    <div class="review-form-actions">
                        <button type="button" class="btn-review-cancel" onclick="closeStoreReviewModal()">Cancel</button>
                        <button type="submit" id="btnSubmitReview" class="btn-review-submit">
                            <span>Submit Review</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Review Success Confirmation View (Matches Image 2) -->
            <div id="storeReviewSuccessWrap" style="display: none; text-align: center; padding: 26px 12px 14px;">
                <div style="width: 44px; height: 44px; border-radius: 50%; border: 2.5px solid #144D29; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #144D29;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#144D29" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 20px; font-weight: 700; color: #111827; margin: 0 0 10px 0; letter-spacing: -0.2px;">Thank You for Sharing Your Experience!</h3>
                <p style="font-size: 14px; line-height: 1.6; color: #4B5563; max-width: 420px; margin: 0 auto 24px auto;">
                    Your review has been submitted successfully. We appreciate your feedback and thank you for being part of the NUTRONYX community.
                </p>
                <button type="button" class="btn-review-submit" onclick="closeStoreReviewModal()" style="min-width: 120px;">
                    <span>Close</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Review Modal Interactions & AJAX Submission -->
    <script>
    function openStoreReviewModal() {
        const modal = document.getElementById('storeReviewModal');
        if (!modal) return;
        const formWrap = document.getElementById('storeReviewFormWrap');
        const successWrap = document.getElementById('storeReviewSuccessWrap');
        const errNotice = document.getElementById('reviewModalErrorNotice');
        if (formWrap) formWrap.style.display = 'block';
        if (successWrap) successWrap.style.display = 'none';
        if (errNotice) { errNotice.style.display = 'none'; errNotice.textContent = ''; }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeStoreReviewModal() {
        const modal = document.getElementById('storeReviewModal');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    async function handleReviewSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = document.getElementById('btnSubmitReview');
        const errNotice = document.getElementById('reviewModalErrorNotice');
        
        if (errNotice) { errNotice.style.display = 'none'; errNotice.textContent = ''; }
        
        const formData = new FormData(form);
        const comment = (formData.get('comment') || '').toString().trim();
        if (comment.length < 10) {
            if (errNotice) {
                errNotice.textContent = 'Please provide at least 10 characters for your experience.';
                errNotice.style.display = 'block';
            }
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.querySelector('span').textContent = 'Submitting...';
        }

        try {
            const response = await fetch('shop.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('storeReviewFormWrap').style.display = 'none';
                document.getElementById('storeReviewSuccessWrap').style.display = 'block';
                form.reset();
            } else {
                if (errNotice) {
                    errNotice.textContent = result.error || 'Unable to submit review. Please try again.';
                    errNotice.style.display = 'block';
                }
            }
        } catch (err) {
            form.submit();
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.querySelector('span').textContent = 'Submit Review';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('storeReviewModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeStoreReviewModal();
                }
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeStoreReviewModal();
            }
        });
    });
    </script>
</body>
</html>
