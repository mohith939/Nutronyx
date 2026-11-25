<?php
declare(strict_types=1);

// Handle extensionless URL routing on local PHP server or environments without Apache mod_rewrite
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '';
$scriptDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$trimmedPath = trim($requestPath, '/');
if ($scriptDir !== '' && strpos($trimmedPath, $scriptDir) === 0) {
    $trimmedPath = trim(substr($trimmedPath, strlen($scriptDir)), '/');
}

if ($trimmedPath !== '' && $trimmedPath !== 'index') {
    $candidatePhp = __DIR__ . '/' . $trimmedPath . '.php';
    if (file_exists($candidatePhp) && is_file($candidatePhp)) {
        require $candidatePhp;
        exit;
    }
    $candidateDir = __DIR__ . '/' . $trimmedPath;
    if (is_dir($candidateDir) && file_exists($candidateDir . '/index.php')) {
        require $candidateDir . '/index.php';
        exit;
    }
}

require_once __DIR__ . '/src/bootstrap.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

use Nutronyx\Services\ProductService;
use Nutronyx\Services\ReviewService;

$flagshipProduct = ProductService::getProduct('prod_abc_powder', true);
$flagshipVariants = array_column($flagshipProduct['variants'] ?? [], null, 'id');
$showShowcase = ($flagshipProduct !== null && !empty($flagshipVariants));
$defaultVariantKey = isset($flagshipVariants['var_abc_250g']) ? 'var_abc_250g' : (array_key_first($flagshipVariants) ?? 'var_abc_250g');
$defaultVariantPrice = isset($flagshipVariants[$defaultVariantKey]) ? (int)round(($flagshipVariants[$defaultVariantKey]['sale_price_amount'] ?? 29900) / 100) : 299;

// Fetch approved customer reviews for the community section
$homeReviews = ReviewService::getApprovedReviewsForProduct('prod_abc_powder', 10);
if (count($homeReviews) < 3) {
    $homeReviews = ReviewService::getApprovedReviewsForProduct('all', 10);
}

// Location and routine metadata mapping for authentic customer stories
$reviewMetaMap = [
    'Ananya R.' => 'Mumbai, India • Daily Ritualist since Oct 2024',
    'Rohit K.' => 'Hyderabad, India • Daily Ritualist since Nov 2024',
    'Meghana S.' => 'Visakhapatnam, India • Daily Ritualist since Dec 2024',
    'Dr. Vikram M.' => 'Bengaluru, India • Daily Ritualist since Jan 2025',
    'Pooja N.' => 'Pune, India • Daily Ritualist since Feb 2025',
    'Kavita S.' => 'Chennai, India • Daily Ritualist since Feb 2025',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NUTRONYX</title>
    
    <!-- Favicon & Brand Icons (Google Search & Browsers) -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192x192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#174529">
    
    <!-- Google Fonts: Cormorant Garamond & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261124_v3">
</head>
<body class="nutronyx-body">

    <!-- Header Navigation -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- HERO SECTION VIEWPORT -->
    <main class="hero-viewport" id="home">
        <section class="hero-section" aria-label="Nutronyx Hero">
            <!-- Exact Background Artwork (Warm ivory, soft ambient light & edge botanical shadows) -->
            <div class="hero-bg-layer" aria-hidden="true"></div>

            <!-- Hero Main 3-Column Grid -->
            <div class="hero-inner-container">
                
                <!-- 1. LEFT HERO CONTENT -->
                <div class="hero-left-content">
                    <h1 class="hero-headline">
                        <span class="headline-green">Nature's Goodness.</span>
                        <span class="headline-green">Simplified.</span>
                    </h1>

                    <p class="hero-description">
                        Pure, natural nutrition for a healthier, happier you.
                    </p>

                    <div class="hero-cta-group">
                        <a href="shop" class="btn-discover">
                            <span>Shop ABC+</span>
                            <span class="cta-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </div>

                <!-- 2. CENTRAL ABC+ PRODUCT & BOTANICALS COMPOSITION -->
                <div class="hero-center-stage" aria-label="NUTRONYX ABC+ Daily Wellness Composition">
                    <div class="hero-pouch-composition" id="heroPouchComposition">
                        <img src="assets/images/hero-abc-pouch.png" alt="NUTRONYX ABC+ Daily Wellness Powder with 5 Whole Foods and Ruby Smoothie" class="hero-pouch-img">
                    </div>
                </div>

                <!-- 3. RIGHT INGREDIENT PANEL -->
                <aside class="hero-right-panel" aria-label="Key Ingredients">
                    <div class="panel-header">
                        <span class="panel-eyebrow">POWERED BY</span>
                        <h2 class="panel-title">5 NATURAL INGREDIENTS</h2>
                    </div>

                    <div class="panel-list-wrapper">
                        <!-- Delicate Vertical Guide Line -->
                        <div class="panel-connector-line" aria-hidden="true"></div>

                        <ul class="panel-items">
                            <li class="panel-item">
                                <div class="item-thumb-wrapper">
                                    <img src="assets/images/amla.png" alt="Amla" class="item-thumb">
                                </div>
                                <div class="item-details">
                                    <h3 class="item-name">Amla</h3>
                                </div>
                            </li>

                            <li class="panel-item">
                                <div class="item-thumb-wrapper">
                                    <img src="assets/images/beetroot.png" alt="Beetroot" class="item-thumb">
                                </div>
                                <div class="item-details">
                                    <h3 class="item-name">Beetroot</h3>
                                </div>
                            </li>

                            <li class="panel-item">
                                <div class="item-thumb-wrapper">
                                    <img src="assets/images/carrot.png" alt="Carrot" class="item-thumb">
                                </div>
                                <div class="item-details">
                                    <h3 class="item-name">Carrot</h3>
                                </div>
                            </li>

                            <li class="panel-item">
                                <div class="item-thumb-wrapper">
                                    <img src="assets/images/ginger.png" alt="Ginger" class="item-thumb">
                                </div>
                                <div class="item-details">
                                    <h3 class="item-name">Ginger</h3>
                                </div>
                            </li>

                            <li class="panel-item">
                                <div class="item-thumb-wrapper">
                                    <img src="assets/images/lemon-slice.png" alt="Lemon" class="item-thumb">
                                </div>
                                <div class="item-details">
                                    <h3 class="item-name">Lemon</h3>
                                </div>
                            </li>
                        </ul>
                    </div>
                </aside>

            </div>

            <!-- 4. BOTTOM-LEFT SCROLL INDICATOR -->
            <div class="hero-scroll-indicator" aria-hidden="true">
                <div class="scroll-accent-line"></div>
                <div class="scroll-action-row">
                    <div class="scroll-mouse-icon">
                        <span class="scroll-wheel"></span>
                    </div>
                    <span class="scroll-label">SCROLL TO EXPLORE</span>
                </div>
            </div>
        </section>
    </main>

    <!-- ========================================== -->
    <!-- SECTION 2: THE POWER WITHIN               -->
    <!-- ========================================== -->
    <section class="section-power" id="power-within" aria-label="The Power Within">
        <!-- Organic Pale-Green Background Curved Ribbons -->
        <div class="power-bg-decor" aria-hidden="true">
            <svg class="power-bg-wave power-wave-left" viewBox="0 0 400 800" fill="none" preserveAspectRatio="none">
                <path d="M-100 0 C 150 180, 220 420, -50 800 L -100 800 Z" fill="url(#waveGradLeft)" opacity="0.65" />
                <path d="M-80 80 C 180 260, 160 520, -60 750" stroke="#A9CCA9" stroke-width="2" stroke-dasharray="6 8" opacity="0.4" fill="none" />
                <defs>
                    <linearGradient id="waveGradLeft" x1="0" y1="0" x2="300" y2="600" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#E8F3E9" stop-opacity="0.8" />
                        <stop offset="60%" stop-color="#D7EAD8" stop-opacity="0.45" />
                        <stop offset="100%" stop-color="#FAF7F2" stop-opacity="0" />
                    </linearGradient>
                </defs>
            </svg>

            <svg class="power-bg-wave power-wave-right" viewBox="0 0 400 800" fill="none" preserveAspectRatio="none">
                <path d="M 500 150 C 250 320, 200 580, 500 800 L 500 800 Z" fill="url(#waveGradRight)" opacity="0.65" />
                <path d="M 460 220 C 260 380, 240 620, 480 760" stroke="#A9CCA9" stroke-width="2" stroke-dasharray="6 8" opacity="0.4" fill="none" />
                <defs>
                    <linearGradient id="waveGradRight" x1="400" y1="200" x2="100" y2="700" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#E8F3E9" stop-opacity="0.8" />
                        <stop offset="60%" stop-color="#D7EAD8" stop-opacity="0.45" />
                        <stop offset="100%" stop-color="#FAF7F2" stop-opacity="0" />
                    </linearGradient>
                </defs>
            </svg>

            <!-- Animated Floating & Rotating Botanical Background Icons -->
            <div class="bg-float-elem elem-leaf-1" aria-hidden="true">
                <svg class="rotate-cw-slow" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#689366" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L12 22" />
                    <path d="M12 2C7 5 3 10 3 15C3 19 6.5 22 12 22C17.5 22 21 19 21 15C21 10 17 5 12 2Z" />
                    <path d="M12 8C9 10 7 12 6 15" />
                    <path d="M12 13C15 14 17 16 18 18" />
                </svg>
            </div>

            <div class="bg-float-elem elem-star-1" aria-hidden="true">
                <svg class="rotate-cw-mid" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#7BA678" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2 Q 12 12 2 12 Q 12 12 12 22 Q 12 12 22 12 Q 12 12 12 2 Z" fill="#EAF4EC" />
                </svg>
            </div>

            <div class="bg-float-elem elem-sprig-1" aria-hidden="true">
                <svg class="rotate-ccw-slow" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#638F61" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 21C7 16 11 12 18 10" />
                    <path d="M18 10C18 6 15 4 11 5C8 6 7 9 8 12C10 12 14 11 18 10Z" />
                    <path d="M12 14C11 17 8 19 5 18C4 16 5 13 8 12C9 12 11 13 12 14Z" />
                </svg>
            </div>

            <div class="bg-float-elem elem-sun-1" aria-hidden="true">
                <svg class="rotate-cw-mid" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#7AA778" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="6" />
                    <line x1="12" y1="2" x2="12" y2="4" />
                    <line x1="12" y1="20" x2="12" y2="22" />
                    <line x1="2" y1="12" x2="4" y2="12" />
                    <line x1="20" y1="12" x2="22" y2="12" />
                    <line x1="4.93" y1="4.93" x2="6.34" y2="6.34" />
                    <line x1="17.66" y1="17.66" x2="19.07" y2="19.07" />
                    <line x1="4.93" y1="19.07" x2="6.34" y2="17.66" />
                    <line x1="17.66" y1="6.34" x2="19.07" y2="4.93" />
                </svg>
            </div>

            <div class="bg-float-elem elem-drop-1" aria-hidden="true">
                <svg class="rotate-gentle-sway" width="28" height="32" viewBox="0 0 24 28" fill="none" stroke="#6FA06C" stroke-width="1.35">
                    <path d="M12 2 C 7 10, 4 16, 4 20 C 4 24.4, 7.6 28, 12 28 C 16.4 28, 20 24.4, 20 20 C 20 16, 17 10, 12 2 Z" fill="#E4F2E6" opacity="0.75" />
                </svg>
            </div>

            <div class="bg-float-elem elem-berry-1" aria-hidden="true">
                <svg class="rotate-ccw-mid" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#689366" stroke-width="1.35">
                    <circle cx="10" cy="14" r="6" fill="#E8F4EA" opacity="0.8" />
                    <circle cx="16" cy="11" r="5" fill="#DEF0E1" opacity="0.8" />
                    <path d="M10 8 C 10 5, 13 3, 16 3" stroke-linecap="round" />
                </svg>
            </div>

            <div class="bg-float-elem elem-star-2" aria-hidden="true">
                <svg class="rotate-cw-slow" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#7BA678" stroke-width="1.45">
                    <path d="M12 2 Q 12 12 2 12 Q 12 12 12 22 Q 12 12 22 12 Q 12 12 12 2 Z" fill="#EAF5EC" />
                </svg>
            </div>
        </div>

        <div class="power-content-container">
            <!-- Section Header -->
            <div class="power-header">
                <div class="power-eyebrow-wrapper">
                    <span class="power-eyebrow-line"></span>
                    <span class="power-eyebrow">THE POWER WITHIN</span>
                    <span class="power-eyebrow-line"></span>
                </div>

                <h2 class="power-headline">
                    <span class="headline-green">Five Natural Ingredients.</span>
                    <span class="headline-burgundy">One Daily Ritual.</span>
                </h2>

                <p class="power-description">
                    A thoughtfully crafted blend of nature's finest ingredients,<br>
                    brought together for your everyday wellness ritual.
                </p>
            </div>

            <!-- Central 3D Elliptical Orbit Stage (Matching Image 2 & Section 4) -->
            <div class="power-orbit-stage" id="powerOrbitStage">
                <!-- Glowing 3D Elliptical Orbit Track -->
                <div class="power-orbit-track-wrap" aria-hidden="true">
                    <svg class="power-orbit-svg" viewBox="-400 -220 800 440" fill="none">
                        <g transform="rotate(-10)">
                            <ellipse cx="0" cy="0" rx="335" ry="135" class="power-orbit-glow-line" stroke="url(#powerOrbitGoldGlow)" stroke-width="2.5" fill="none"/>
                            <ellipse cx="0" cy="0" rx="335" ry="135" class="power-orbit-dash-line" stroke="rgba(247, 215, 125, 0.85)" stroke-width="1.3" stroke-dasharray="6 8" fill="none"/>
                        </g>
                        <defs>
                            <linearGradient id="powerOrbitGoldGlow" x1="-335" y1="-135" x2="335" y2="135" gradientUnits="userSpaceOnUse">
                                <stop offset="0%" stop-color="#F7D070" stop-opacity="0.95"/>
                                <stop offset="35%" stop-color="#FFE8A3" stop-opacity="1"/>
                                <stop offset="70%" stop-color="#E5B25D" stop-opacity="0.85"/>
                                <stop offset="100%" stop-color="#FAF1D2" stop-opacity="0.5"/>
                            </linearGradient>
                        </defs>
                    </svg>

                    <!-- Orbit Ambient Sparkles -->
                    <div class="power-sparkle p-sparkle-1"></div>
                    <div class="power-sparkle p-sparkle-2"></div>
                    <div class="power-sparkle p-sparkle-3"></div>
                </div>

                <!-- Center Cup / Powder Bowl with continuous rotation & levitation -->
                <div class="power-center-cup-container" id="powerCenterCup">
                    <div class="power-cup-radial-glow" aria-hidden="true"></div>
                    <div class="power-cup-rotator" id="powerCupRotator">
                        <img src="assets/images/five natural ingredients bowl image.png" alt="Natural Orange-Red Wellness Powder in Ceramic Bowl" class="power-cup-img">
                    </div>
                </div>

                <!-- 5 Orbiting Natural Ingredients (3D loop around cup) -->
                <div class="power-orbiting-group" id="powerOrbitingGroup">
                    <!-- 1. Amla -->
                    <div class="power-orbit-item" id="orbitAmla" data-ingredient="amla" role="button" tabindex="0" aria-label="Amla">
                        <div class="orbit-item-visual">
                            <img src="assets/images/amla.png" alt="Amla" class="orbit-ing-img">
                            <div class="orbit-item-halo"></div>
                        </div>
                        <div class="orbit-item-chip">
                            <span class="chip-dot"></span>
                            <span class="chip-name">Amla</span>
                        </div>
                    </div>

                    <!-- 2. Beetroot -->
                    <div class="power-orbit-item" id="orbitBeetroot" data-ingredient="beetroot" role="button" tabindex="0" aria-label="Beetroot">
                        <div class="orbit-item-visual">
                            <img src="assets/images/beetroot.png" alt="Beetroot" class="orbit-ing-img">
                            <div class="orbit-item-halo"></div>
                        </div>
                        <div class="orbit-item-chip">
                            <span class="chip-dot"></span>
                            <span class="chip-name">Beetroot</span>
                        </div>
                    </div>

                    <!-- 3. Carrot -->
                    <div class="power-orbit-item" id="orbitCarrot" data-ingredient="carrot" role="button" tabindex="0" aria-label="Carrot">
                        <div class="orbit-item-visual">
                            <img src="assets/images/carrot.png" alt="Carrot" class="orbit-ing-img">
                            <div class="orbit-item-halo"></div>
                        </div>
                        <div class="orbit-item-chip">
                            <span class="chip-dot"></span>
                            <span class="chip-name">Carrot</span>
                        </div>
                    </div>

                    <!-- 4. Ginger -->
                    <div class="power-orbit-item" id="orbitGinger" data-ingredient="ginger" role="button" tabindex="0" aria-label="Ginger">
                        <div class="orbit-item-visual">
                            <img src="assets/images/ginger.png" alt="Ginger" class="orbit-ing-img">
                            <div class="orbit-item-halo"></div>
                        </div>
                        <div class="orbit-item-chip">
                            <span class="chip-dot"></span>
                            <span class="chip-name">Ginger</span>
                        </div>
                    </div>

                    <!-- 5. Lemon -->
                    <div class="power-orbit-item" id="orbitLemon" data-ingredient="lemon" role="button" tabindex="0" aria-label="Lemon">
                        <div class="orbit-item-visual">
                            <img src="assets/images/lemon image.png" alt="Lemon" class="orbit-ing-img">
                            <div class="orbit-item-halo"></div>
                        </div>
                        <div class="orbit-item-chip">
                            <span class="chip-dot"></span>
                            <span class="chip-name">Lemon</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Unified 5-Ingredient Descriptive Cards Strip (Replacing 3 on left & 2 on right tags) -->
            <div class="power-ingredients-strip" id="powerIngredientsStrip">
                <!-- Card 1: Amla -->
                <div class="power-ing-card" data-ingredient="amla" tabindex="0">
                    <div class="ing-card-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2D6038" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="14" r="5" />
                            <circle cx="16" cy="11" r="4.5" />
                            <path d="M9 9 C 9 6, 12 4, 15 3" />
                            <path d="M12 5 C 14 6, 15 8, 15 9" />
                        </svg>
                    </div>
                    <div class="ing-card-content">
                        <h3 class="ing-card-title">Amla</h3>
                        <p class="ing-card-desc">A natural source of plant nutrients.</p>
                    </div>
                </div>

                <!-- Card 2: Beetroot -->
                <div class="power-ing-card" data-ingredient="beetroot" tabindex="0">
                    <div class="ing-card-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2D6038" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 21 C 8 21, 5 17, 5 13 C 5 9.5, 8 7.5, 12 7.5 C 16 7.5, 19 9.5, 19 13 C 19 17, 16 21, 12 21 Z" />
                            <path d="M12 7.5 C 12 4, 10 2, 8 2" />
                            <path d="M12 7.5 C 13.5 4.5, 15.5 3, 17 3" />
                            <path d="M12 21 L 12 23" />
                        </svg>
                    </div>
                    <div class="ing-card-content">
                        <h3 class="ing-card-title">Beetroot</h3>
                        <p class="ing-card-desc">Rich in naturally occurring nutrients.</p>
                    </div>
                </div>

                <!-- Card 3: Carrot -->
                <div class="power-ing-card" data-ingredient="carrot" tabindex="0">
                    <div class="ing-card-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2D6038" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 17 L 17 7" />
                            <path d="M17 7 C 19 5, 21 6, 21 8 C 21 10, 19 12, 17 12 C 15 12, 14 11, 13 9" />
                            <path d="M7 17 C 5.5 18.5, 4 20, 3 21 C 4 20, 5.5 18.5, 7 17 Z" />
                            <path d="M17 7 L 19 3" />
                            <path d="M17 7 L 21 5" />
                        </svg>
                    </div>
                    <div class="ing-card-content">
                        <h3 class="ing-card-title">Carrot</h3>
                        <p class="ing-card-desc">A wholesome plant ingredient.</p>
                    </div>
                </div>

                <!-- Card 4: Ginger -->
                <div class="power-ing-card" data-ingredient="ginger" tabindex="0">
                    <div class="ing-card-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2D6038" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 14 C 3 11, 5 8, 8 7 C 9 4, 12 3, 15 5 C 18 3, 21 5, 21 8 C 21 11, 19 13, 18 15 C 17 18, 14 20, 11 20 C 7 20, 4 17, 4 14 Z" />
                            <path d="M8 12 C 10 13, 13 13, 15 11" />
                        </svg>
                    </div>
                    <div class="ing-card-content">
                        <h3 class="ing-card-title">Ginger</h3>
                        <p class="ing-card-desc">A time-honored ingredient.</p>
                    </div>
                </div>

                <!-- Card 5: Lemon -->
                <div class="power-ing-card" data-ingredient="lemon" tabindex="0">
                    <div class="ing-card-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2D6038" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9" />
                            <line x1="12" y1="12" x2="12" y2="4" />
                            <line x1="12" y1="12" x2="18.5" y2="8" />
                            <line x1="12" y1="12" x2="18.5" y2="16" />
                            <line x1="12" y1="12" x2="12" y2="20" />
                            <line x1="12" y1="12" x2="5.5" y2="16" />
                            <line x1="12" y1="12" x2="5.5" y2="8" />
                        </svg>
                    </div>
                    <div class="ing-card-content">
                        <h3 class="ing-card-title">Lemon</h3>
                        <p class="ing-card-desc">Adds a fresh, natural zest.</p>
                    </div>
                </div>
            </div>

            <!-- Bottom CTA Button -->
            <div class="power-cta-row">
                <a href="shop" class="btn-explore-ingredients">
                    <span>Explore the Ingredients</span>
                    <span class="cta-arrow" aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- NUTRONYX ABC+ Botanical Product Showcase   -->
    <!-- ========================================== -->
    <?php if ($showShowcase): ?>
    <section class="section-showcase" id="product-showcase" aria-label="Featured Product Showcase — NUTRONYX ABC+">
        <div class="showcase-container">
            <!-- Two-Column Editorial Stage -->
            <div class="showcase-grid">
                <!-- 1. LEFT COLUMN: ACTUAL PRODUCT BOTANICAL VISUAL -->
                <div class="showcase-visual-col">
                    <div class="showcase-visual-frame">
                        <img src="assets/images/product-showcase-botanical.png" 
                             alt="NUTRONYX ABC+ Daily Wellness Powder with fresh Amla, Beetroot, Carrot, Ginger and Lemon on natural stone plinth" 
                             class="showcase-visual-img" 
                             loading="lazy">
                    </div>
                </div>

                <!-- 2. RIGHT COLUMN: EDITORIAL PRODUCT INFORMATION & INGREDIENT TABLE -->
                <div class="showcase-details-col">
                    <!-- Eyebrow -->
                    <div class="showcase-eyebrow-wrap">
                        <span class="showcase-eyebrow">NUTRONYX • ABC+</span>
                    </div>

                    <!-- Headlines -->
                    <h2 class="showcase-headline">
                        <span class="showcase-headline-line">Five Ingredients.</span>
                        <span class="showcase-headline-line">One Thoughtful Blend.</span>
                    </h2>

                    <!-- Product Description -->
                    <p class="showcase-description">
                        ABC+ is a carefully crafted blend of Beetroot, Carrot, Amla, Lemon, and Ginger, created to bring the goodness of five natural ingredients into your everyday routine. Rich in naturally occurring antioxidants and key nutrients, it provides Vitamin C, beta-carotene, and plant-based compounds that support everyday immunity, digestion, healthy circulation, and overall wellness. A simple, convenient way to make natural nutrition part of every day.
                    </p>

                    <!-- Product Specifications Bar with Interactive Size Pills -->
                    <div class="showcase-specs-row" aria-label="Product Specifications">
                        <div class="spec-item spec-variant-item" style="display:inline-flex; align-items:center; gap:8px;">
                            <span style="font-size:12px; font-weight:700; color:#526458; text-transform:uppercase; letter-spacing:0.5px;">Size:</span>
                            <div class="showcase-size-pills-row" style="display:inline-flex; gap:6px;">
                                <?php if (isset($flagshipVariants['var_abc_250g'])): ?>
                                    <button type="button" class="showcase-size-pill <?= ($defaultVariantKey === 'var_abc_250g') ? 'active' : '' ?>" id="scPill250" onclick="onShowcasePillClick('var_abc_250g', <?= (int)round(($flagshipVariants['var_abc_250g']['sale_price_amount'] ?? 29900) / 100) ?>, this)" style="padding:4px 10px; border-radius:6px; border:1.5px solid #143D24; background:<?= ($defaultVariantKey === 'var_abc_250g') ? '#143D24' : '#fff' ?>; color:<?= ($defaultVariantKey === 'var_abc_250g') ? '#fff' : '#374151' ?>; font-size:12px; font-weight:700; cursor:pointer;">
                                        200gm
                                    </button>
                                <?php endif; ?>
                                <?php if (isset($flagshipVariants['var_abc_500g'])): ?>
                                    <button type="button" class="showcase-size-pill <?= ($defaultVariantKey === 'var_abc_500g') ? 'active' : '' ?>" id="scPill500" onclick="onShowcasePillClick('var_abc_500g', <?= (int)round(($flagshipVariants['var_abc_500g']['sale_price_amount'] ?? 49900) / 100) ?>, this)" style="padding:4px 10px; border-radius:6px; border:1.5px solid <?= ($defaultVariantKey === 'var_abc_500g') ? '#143D24' : '#D1D5DB' ?>; background:<?= ($defaultVariantKey === 'var_abc_500g') ? '#143D24' : '#fff' ?>; color:<?= ($defaultVariantKey === 'var_abc_500g') ? '#fff' : '#374151' ?>; font-size:12px; font-weight:700; cursor:pointer;">
                                        400gm
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="spec-divider" aria-hidden="true">|</span>
                        <div class="spec-item spec-price">
                            <span class="spec-value" id="showcasePriceVal">₹<?= $defaultVariantPrice ?></span>
                        </div>
                    </div>

                    <!-- 5-Row Ingredient Information Table -->
                    <div class="showcase-ingredients-table" aria-label="Botanical Ingredients Breakdown">
                        <!-- Row 01: AMLA -->
                        <div class="ingredient-row">
                            <div class="ingredient-thumb-wrap">
                                <img src="assets/images/amla.png" alt="Amla" class="ingredient-thumb-img" loading="lazy">
                            </div>
                            <span class="row-v-divider" aria-hidden="true">|</span>
                            <div class="ingredient-name-cell">
                                <h3 class="ingredient-name">Amla</h3>
                            </div>
                            <span class="row-v-divider" aria-hidden="true">|</span>
                            <div class="ingredient-desc-cell">
                                <p class="ingredient-benefit">Immunity & everyday vitality</p>
                            </div>
                        </div>

                        <!-- Row 02: BEETROOT -->
                        <div class="ingredient-row">
                            <div class="ingredient-thumb-wrap">
                                <img src="assets/images/beetroot.png" alt="Beetroot" class="ingredient-thumb-img" loading="lazy">
                            </div>
                            <span class="row-v-divider" aria-hidden="true">|</span>
                            <div class="ingredient-name-cell">
                                <h3 class="ingredient-name">Beetroot</h3>
                            </div>
                            <span class="row-v-divider" aria-hidden="true">|</span>
                            <div class="ingredient-desc-cell">
                                <p class="ingredient-benefit">Supports blood circulation & stamina</p>
                            </div>
                        </div>

                        <!-- Row 03: CARROT -->
                        <div class="ingredient-row">
                            <div class="ingredient-thumb-wrap">
                                <img src="assets/images/carrot.png" alt="Carrot" class="ingredient-thumb-img" loading="lazy">
                            </div>
                            <span class="row-v-divider" aria-hidden="true">|</span>
                            <div class="ingredient-name-cell">
                                <h3 class="ingredient-name">Carrot</h3>
                            </div>
                            <span class="row-v-divider" aria-hidden="true">|</span>
                            <div class="ingredient-desc-cell">
                                <p class="ingredient-benefit">Rich in beta-carotene for skin & eye health</p>
                            </div>
                        </div>

                        <!-- Row 04: GINGER -->
                        <div class="ingredient-row">
                            <div class="ingredient-thumb-wrap">
                                <img src="assets/images/ginger.png" alt="Ginger" class="ingredient-thumb-img" loading="lazy">
                            </div>
                            <span class="row-v-divider" aria-hidden="true">|</span>
                            <div class="ingredient-name-cell">
                                <h3 class="ingredient-name">Ginger</h3>
                            </div>
                            <span class="row-v-divider" aria-hidden="true">|</span>
                            <div class="ingredient-desc-cell">
                                <p class="ingredient-benefit">Aids digestion & gut comfort</p>
                            </div>
                        </div>

                        <!-- Row 05: LEMON -->
                        <div class="ingredient-row">
                            <div class="ingredient-thumb-wrap">
                                <img src="assets/images/lemon-slice.png" alt="Lemon" class="ingredient-thumb-img" loading="lazy">
                            </div>
                            <span class="row-v-divider" aria-hidden="true">|</span>
                            <div class="ingredient-name-cell">
                                <h3 class="ingredient-name">Lemon</h3>
                            </div>
                            <span class="row-v-divider" aria-hidden="true">|</span>
                            <div class="ingredient-desc-cell">
                                <p class="ingredient-benefit">Helps in daily cleansing & natural freshness</p>
                            </div>
                        </div>
                    </div>

                    <!-- Primary Action CTA -->
                    <div class="showcase-cta-wrap">
                        <a href="shop?variant=<?= urlencode($defaultVariantKey) ?>" class="btn-shop-abc" id="showcaseCtaBtn">
                            <span>SHOP ABC+</span>
                            <span class="btn-arrow" aria-hidden="true">→</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ========================================== -->
    <!-- SECTION 3: EVERY MINUTE MATTERS            -->
    <!-- Matching Reference (media_1794654632253.png)-->
    <!-- 3D Rotating Clock & Nutrient Orbit System  -->
    <!-- ========================================== -->
    <section class="section-minute" id="every-minute-matters" aria-label="Every Minute Matters — Daily Nutrition in Rhythm">
        <!-- Ambient Atmosphere & Clean Warm Ivory Canvas -->
        <div class="minute-atmosphere-stage" aria-hidden="true">
            <div class="minute-sunlight-bloom"></div>
        </div>

        <div class="minute-container">
            <!-- Left Column: Editorial Brand Storytelling -->
            <div class="minute-content-col">
                <!-- Eyebrow Badge -->
                <div class="minute-eyebrow-wrapper">
                    <span class="minute-eyebrow-line"></span>
                    <span class="minute-eyebrow">EVERY MINUTE MATTERS</span>
                    <span class="minute-eyebrow-line"></span>
                </div>

                <!-- Editorial Headline -->
                <h2 class="minute-headline">
                    <span class="m-line m-title-primary">Your Day Moves Fast.</span>
                    <span class="m-line m-italic">Your Nutrition</span>
                    <span class="m-line m-italic">Shouldn't Be Left</span>
                    <span class="m-line m-italic m-last-line">Behind.</span>
                </h2>

                <!-- Narrative Subtitle -->
                <p class="minute-subtext">
                    Thoughtfully crafted natural nutrition, made to fit effortlessly into your everyday routine.
                </p>
            </div>

            <!-- Right Column: 3D Forest-Green Clock & Elliptical Nutrient Orbit System -->
            <div class="minute-visual-col">
                <div class="minute-orbit-stage" id="minuteOrbitStage">
                    <!-- Glowing 3D Elliptical Orbit Track -->
                    <div class="minute-orbit-track-wrap" aria-hidden="true">
                        <svg class="minute-orbit-svg" viewBox="-300 -300 600 600" fill="none">
                            <g transform="rotate(-18)">
                                <ellipse cx="0" cy="0" rx="268" ry="195" class="orbit-glow-line" stroke="url(#orbitGoldGlow)" stroke-width="2.5" fill="none"/>
                                <ellipse cx="0" cy="0" rx="268" ry="195" class="orbit-dash-line" stroke="rgba(255, 235, 175, 0.85)" stroke-width="1.2" stroke-dasharray="6 8" fill="none"/>
                            </g>
                            <defs>
                                <linearGradient id="orbitGoldGlow" x1="-268" y1="-195" x2="268" y2="195" gradientUnits="userSpaceOnUse">
                                    <stop offset="0%" stop-color="#F7D070" stop-opacity="0.9"/>
                                    <stop offset="40%" stop-color="#FFE8A3" stop-opacity="0.98"/>
                                    <stop offset="75%" stop-color="#E5B25D" stop-opacity="0.75"/>
                                    <stop offset="100%" stop-color="#FAF1D2" stop-opacity="0.45"/>
                                </linearGradient>
                            </defs>
                        </svg>

                        <!-- Luminous Sparkle Particles along Orbit -->
                        <div class="orbit-sparkle sparkle-1"></div>
                        <div class="orbit-sparkle sparkle-2"></div>
                        <div class="orbit-sparkle sparkle-3"></div>
                    </div>

                    <!-- 3D Forest-Green Rimmed Clock -->
                    <div class="minute-clock-container" id="minuteClock">
                        <div class="clock-outer-bevel">
                            <div class="clock-green-rim">
                                <div class="clock-gold-inner-rim">
                                    <!-- Rotating Clock Dial Face -->
                                    <div class="clock-dial-plate" id="clockDialPlate">
                                        <!-- Hour Ticks & Numbers -->
                                        <div class="clock-num clock-num-12">12</div>
                                        <div class="clock-num clock-num-3">3 <span class="num-dash">-</span></div>
                                        <div class="clock-num clock-num-6">6</div>
                                        <div class="clock-num clock-num-9"><span class="num-dash">-</span> 9</div>

                                        <!-- Hour Markers / Ticks -->
                                        <div class="clock-ticks-layer" aria-hidden="true">
                                            <span class="clock-tick tick-1"></span>
                                            <span class="clock-tick tick-2"></span>
                                            <span class="clock-tick tick-4"></span>
                                            <span class="clock-tick tick-5"></span>
                                            <span class="clock-tick tick-7"></span>
                                            <span class="clock-tick tick-8"></span>
                                            <span class="clock-tick tick-10"></span>
                                            <span class="clock-tick tick-11"></span>
                                        </div>

                                        <!-- Dial Subtle Radial Shading & Glare -->
                                        <div class="clock-dial-glare" aria-hidden="true"></div>
                                    </div>

                                    <!-- Rotating Clock Hands -->
                                    <div class="clock-hands-stage" aria-hidden="true">
                                        <!-- Hour Hand -->
                                        <div class="clock-hand clock-hand-hour" id="clockHandHour">
                                            <div class="hand-shape hand-shape-hour"></div>
                                        </div>
                                        <!-- Minute Hand -->
                                        <div class="clock-hand clock-hand-minute" id="clockHandMinute">
                                            <div class="hand-shape hand-shape-minute"></div>
                                        </div>
                                        <!-- Second Hand -->
                                        <div class="clock-hand clock-hand-second" id="clockHandSecond">
                                            <div class="hand-shape hand-shape-second"></div>
                                        </div>
                                        <!-- Central 3D Golden Hub Cap -->
                                        <div class="clock-center-hub"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5 Orbiting Floating 3D Glass Nutrient Bubbles -->
                    <div class="minute-nutrient-bubbles" id="nutrientBubblesGroup">
                        <!-- Bubble 1: Protein -->
                        <div class="nutrient-bubble-item" id="bubbleProtein" data-nutrient="protein">
                            <div class="nutrient-glass-orb">
                                <img src="assets/images/nutrients/protein.png" alt="Protein wholefood nutrition" class="nutrient-orb-img">
                                <div class="glass-specular-highlight"></div>
                            </div>
                            <div class="nutrient-tag-wrap">
                                <span class="nutrient-name">Protein</span>
                                <span class="nutrient-dash-line"></span>
                            </div>
                        </div>

                        <!-- Bubble 2: Vitamins -->
                        <div class="nutrient-bubble-item" id="bubbleVitamins" data-nutrient="vitamins">
                            <div class="nutrient-glass-orb">
                                <img src="assets/images/nutrients/vitamins.png" alt="Vitamins botanical golden softgels" class="nutrient-orb-img">
                                <div class="glass-specular-highlight"></div>
                            </div>
                            <div class="nutrient-tag-wrap">
                                <span class="nutrient-name">Vitamins</span>
                                <span class="nutrient-dash-line"></span>
                            </div>
                        </div>

                        <!-- Bubble 3: Minerals -->
                        <div class="nutrient-bubble-item" id="bubbleMinerals" data-nutrient="minerals">
                            <div class="nutrient-glass-orb">
                                <img src="assets/images/nutrients/minerals.png" alt="Minerals crystalline elemental nutrition" class="nutrient-orb-img">
                                <div class="glass-specular-highlight"></div>
                            </div>
                            <div class="nutrient-tag-wrap">
                                <span class="nutrient-name">Minerals</span>
                                <span class="nutrient-dash-line"></span>
                            </div>
                        </div>

                        <!-- Bubble 4: Antioxidants -->
                        <div class="nutrient-bubble-item" id="bubbleAntioxidants" data-nutrient="antioxidants">
                            <div class="nutrient-glass-orb">
                                <img src="assets/images/nutrients/antioxidants.png" alt="Antioxidants fresh green botanical leaves" class="nutrient-orb-img">
                                <div class="glass-specular-highlight"></div>
                            </div>
                            <div class="nutrient-tag-wrap">
                                <span class="nutrient-name">Antioxidants</span>
                                <span class="nutrient-dash-line"></span>
                            </div>
                        </div>

                        <!-- Bubble 5: Fiber -->
                        <div class="nutrient-bubble-item" id="bubbleFiber" data-nutrient="fiber">
                            <div class="nutrient-glass-orb">
                                <img src="assets/images/nutrients/fiber.png" alt="Fiber whole seeds and grains" class="nutrient-orb-img">
                                <div class="glass-specular-highlight"></div>
                            </div>
                            <div class="nutrient-tag-wrap">
                                <span class="nutrient-name">Fiber</span>
                                <span class="nutrient-dash-line"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Center Hallmark -->
        <div class="minute-bottom-hallmark">
            <span class="hallmark-dash-line"></span>
            <span class="hallmark-brand-text">NUTRONYX &bull; DAILY WELLNESS, MADE EASY</span>
            <span class="hallmark-dash-line"></span>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- SECTION 4: THE ABC+ DAILY RITUAL           -->
    <!-- A Simpler Way to Everyday Wellness         -->
    <!-- Matching Reference Composition (Image 1)   -->
    <!-- ========================================== -->
    <section class="section-ritual" id="ritual" aria-label="The ABC+ Daily Ritual">
        <div class="ritual-stage-wrapper">
            <!-- Background Lifestyle Photographic Atmosphere -->
            <div class="ritual-stage-bg">
                <img src="assets/images/daily-ritual-lifestyle.jpg" 
                     alt="Woman enjoying a peaceful morning wellness ritual with a cup of ABC+ botanical blend by the sunlit window" 
                     class="ritual-bg-photo" 
                     loading="lazy">
                <div class="ritual-gradient-scrim" aria-hidden="true"></div>
            </div>

            <!-- Foreground Editorial Content -->
            <div class="ritual-content-wrap">
                <div class="ritual-copy-card">
                    <!-- Eyebrow -->
                    <div class="ritual-eyebrow-container">
                        <span class="eyebrow-dash" aria-hidden="true"></span>
                        <span class="eyebrow-label">WELLNESS MADE SIMPLE</span>
                        <span class="eyebrow-dash" aria-hidden="true"></span>
                    </div>

                    <!-- Primary Headline -->
                    <h2 class="ritual-hero-title">
                        <span class="title-line">A Simpler Way to</span>
                        <span class="title-line">Everyday <em class="title-wellness-italic">Wellness.</em></span>
                    </h2>

                    <!-- Supporting Description -->
                    <p class="ritual-hero-sub">
                        Thoughtfully crafted natural nutrition, designed to fit effortlessly into your everyday routine
                    </p>

                    <!-- CTA Button -->
                    <div class="ritual-cta-block">
                        <a href="shop" class="btn-discover-ritual" aria-label="Explore Our Products">
                            <span class="btn-caption">Explore Our Products</span>
                            <span class="btn-icon" aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- SECTION 5: PHILOSOPHY — WELLNESS IN NATURAL HARMONY -->
    <!-- Premium 3D Ingredient Orbit Composition   -->
    <!-- Matching Reference Image 2 Art Direction   -->
    <!-- ========================================== -->
    <section class="section-philosophy" id="philosophy" aria-label="Our Philosophy — Wellness in Natural Harmony">
        <!-- Ambient Botanical Shadow & Warm Studio Lighting -->
        <div class="philo-ambient-backdrop" aria-hidden="true"></div>

        <div class="philo-container">
            <!-- 1. UPPER EDITORIAL HEADER -->
            <div class="philo-header-block">
                <div class="philo-eyebrow-wrapper">
                    <span class="philo-eyebrow-rule" aria-hidden="true"></span>
                    <span class="philo-eyebrow-text">OUR PHILOSOPHY</span>
                    <span class="philo-eyebrow-rule" aria-hidden="true"></span>
                </div>

                <h2 class="philo-headline">Wellness in Natural Harmony</h2>

                <p class="philo-subheadline">
                    A thoughtful balance of nature, simplicity, and everyday vitality.
                </p>
            </div>

            <!-- 2. CENTRAL 3D INGREDIENT ORBIT STAGE -->
            <div class="philo-orbit-stage" id="philoOrbitStage" aria-label="Botanical Ingredient Orbit Centerpiece">
                
                <!-- Perspective Luminous Elliptical Orbit Vector -->
                <svg class="philo-orbit-svg" viewBox="0 0 960 480" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <defs>
                        <!-- Luminous Gradient for Orbit Ring -->
                        <linearGradient id="orbitRingGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#E8D5B5" stop-opacity="0.3"/>
                            <stop offset="25%" stop-color="#FFFFFF" stop-opacity="0.95"/>
                            <stop offset="50%" stop-color="#E5C79E" stop-opacity="0.75"/>
                            <stop offset="75%" stop-color="#FFFFFF" stop-opacity="0.95"/>
                            <stop offset="100%" stop-color="#D4B68A" stop-opacity="0.35"/>
                        </linearGradient>
                        <filter id="orbitGlow" x="-20%" y="-20%" width="140%" height="140%">
                            <feGaussianBlur stdDeviation="3.5" result="blur"/>
                            <feMerge>
                                <feMergeNode in="blur"/>
                                <feMergeNode in="SourceGraphic"/>
                            </feMerge>
                        </filter>
                    </defs>

                    <!-- Outer Elliptical Luminous Track -->
                    <ellipse cx="480" cy="240" rx="420" ry="115" stroke="url(#orbitRingGrad)" stroke-width="2.2" filter="url(#orbitGlow)" class="orbit-path-glow" transform="rotate(-6 480 240)"/>
                    <ellipse cx="480" cy="240" rx="420" ry="115" stroke="#FFF7ED" stroke-width="1.2" stroke-opacity="0.75" transform="rotate(-6 480 240)"/>

                    <!-- Subtle Restrained Light Points on the Orbit -->
                    <circle cx="120" cy="210" r="3.5" fill="#FFF7ED" filter="url(#orbitGlow)" class="orbit-light-node"/>
                    <circle cx="280" cy="140" r="4" fill="#FFFFFF" filter="url(#orbitGlow)" class="orbit-light-node"/>
                    <circle cx="680" cy="145" r="4" fill="#FFFFFF" filter="url(#orbitGlow)" class="orbit-light-node"/>
                    <circle cx="840" cy="225" r="3.5" fill="#FFF7ED" filter="url(#orbitGlow)" class="orbit-light-node"/>
                    <circle cx="560" cy="350" r="4.5" fill="#FFFFFF" filter="url(#orbitGlow)" class="orbit-light-node"/>
                </svg>

                <!-- Central Focal Centerpiece: Ceramic Bowl Brimming with Red Powder on Pedestal -->
                <div class="philo-centerpiece" id="philoCenterpiece">
                    <div class="centerpiece-frame">
                        <img src="assets/images/wellness bowl image.png" 
                             alt="Handcrafted ceramic bowl brimming with terracotta-red ABC+ botanical powder with rising vitality plume" 
                             class="centerpiece-img"
                             loading="lazy">
                    </div>
                </div>

                <!-- Five Orbiting Botanical Ingredients with GSAP 3D Trajectory -->
                <div class="philo-orbit-system" id="philoOrbitSystem">
                    
                    <!-- 1. AMLA -->
                    <div class="orbit-ingredient ing-amla" data-index="0" data-name="Amla" aria-label="Amla">
                        <div class="ingredient-inner">
                            <img src="assets/images/amla.png" alt="Fresh green amla gooseberries with leaves" class="ing-img">
                        </div>
                    </div>

                    <!-- 2. BEETROOT -->
                    <div class="orbit-ingredient ing-beetroot" data-index="1" data-name="Beetroot" aria-label="Beetroot">
                        <div class="ingredient-inner">
                            <img src="assets/images/beetroot.png" alt="Ruby-red concentric sliced beetroot" class="ing-img">
                        </div>
                    </div>

                    <!-- 3. GINGER -->
                    <div class="orbit-ingredient ing-ginger" data-index="2" data-name="Ginger" aria-label="Ginger">
                        <div class="ingredient-inner">
                            <img src="assets/images/ginger.png" alt="Natural whole ginger root" class="ing-img">
                        </div>
                    </div>

                    <!-- 4. CARROT -->
                    <div class="orbit-ingredient ing-carrot" data-index="3" data-name="Carrot" aria-label="Carrot">
                        <div class="ingredient-inner">
                            <img src="assets/images/carrot.png" alt="Fresh crisp sliced carrot discs" class="ing-img">
                        </div>
                    </div>

                    <!-- 5. LEMON -->
                    <div class="orbit-ingredient ing-lemon" data-index="4" data-name="Lemon" aria-label="Lemon">
                        <div class="ingredient-inner">
                            <img src="assets/images/lemon-slice.png" alt="Juicy sunlit sliced lemon wedge" class="ing-img">
                        </div>
                    </div>

                </div>
            </div>

            <!-- 3. THREE EDITORIAL PHILOSOPHY CARDS -->
            <div class="philo-cards-row">
                
                <!-- CARD 01: ROOTED IN NATURE -->
                <article class="philo-card card-nature">
                    <div class="philo-card-icon-wrap" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
                            <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                        </svg>
                    </div>
                    <h3 class="philo-card-title">ROOTED IN NATURE</h3>
                    <p class="philo-card-text">
                        Inspired by nature, we carefully bring together natural ingredients for everyday wellness.
                    </p>
                </article>

                <!-- CARD 02: SIMPLICITY IN EVERYDAY WELLNESS -->
                <article class="philo-card card-simplicity">
                    <div class="philo-card-icon-wrap" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                        </svg>
                    </div>
                    <h3 class="philo-card-title">SIMPLICITY IN<br>EVERYDAY WELLNESS</h3>
                    <p class="philo-card-text">
                        We believe wellness should fit naturally into your daily routine, without unnecessary complexity.
                    </p>
                </article>

                <!-- CARD 03: THOUGHTFULLY CRAFTED -->
                <article class="philo-card card-thoughtful">
                    <div class="philo-card-icon-wrap" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <polyline points="9 12 11 14 15 10"/>
                        </svg>
                    </div>
                    <h3 class="philo-card-title">THOUGHTFULLY<br>CRAFTED</h3>
                    <p class="philo-card-text">
                        Every ingredient is thoughtfully selected to create a convenient, natural wellness blend.
                    </p>
                </article>

            </div>
        </div>
    </section>


    <!-- ========================================== -->
    <!-- SECTION 6: THE NUTRONYX BRAND MANIFESTO    -->
    <!-- Designed matching Section 6.5              -->
    <!-- ========================================== -->
    <section class="section-manifesto" id="manifesto" aria-label="Inspired by Nature — Made for Everyday Life">
        <!-- Clean Nature Background Canvas (Asset: manifesto-nature.png) -->
        <div class="manifesto-bg-stage" aria-hidden="true">
            <img src="assets/images/manifesto-nature.png?v=20261118_v6" alt="Sunlit rolling green hills bathed in golden morning light" class="manifesto-bg-img" loading="lazy">
        </div>

        <!-- Foreground Editorial Content -->
        <div class="manifesto-container">
            <div class="manifesto-content-panel">
                <div class="manifesto-eyebrow-wrap">
                    <span class="manifesto-eyebrow">THE STORY</span>
                </div>

                <h2 class="manifesto-headline">
                    <span class="manifesto-title-line">Inspired by Nature.</span>
                    <span class="manifesto-title-line">Made for <em class="manifesto-italic">Everyday Life.</em></span>
                </h2>
                
                <p class="manifesto-sub">
                    NUTRONYX began with a simple belief: natural nutrition should be thoughtful, accessible, and easy to include in everyday life.
                </p>

                <div class="manifesto-cta-wrap">
                    <a href="about" class="btn-manifesto-story" aria-label="Discover our Story">
                        <span>Discover our Story</span>
                        <span class="manifesto-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- SECTION 6.1: BRAND COMMITMENTS / PILLARS   -->
    <!-- Matching Reference Design media_1795027377778.png -->
    <!-- ========================================== -->
    <section class="section-manifesto-pillars" id="brand-pillars" aria-label="Our Core Commitments">
        <div class="manifesto-pillars-container">
            <div class="manifesto-pillars-grid">
                <!-- Pillar 1: ROOTED IN NATURE -->
                <div class="manifesto-pillar-card">
                    <div class="pillar-icon-badge" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1D4F32" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
                            <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                        </svg>
                    </div>
                    <h3 class="pillar-title">ROOTED IN NATURE</h3>
                    <p class="pillar-text">Inspired by nature, we carefully bring together natural ingredients for everyday wellness.</p>
                </div>

                <!-- Pillar 2: SIMPLICITY IN EVERYDAY WELLNESS -->
                <div class="manifesto-pillar-card">
                    <div class="pillar-icon-badge" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1D4F32" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                        </svg>
                    </div>
                    <h3 class="pillar-title">SIMPLICITY IN<br>EVERYDAY WELLNESS</h3>
                    <p class="pillar-text">We believe wellness should fit naturally into your daily routine, without unnecessary complexity.</p>
                </div>

                <!-- Pillar 3: THOUGHTFULLY CRAFTED -->
                <div class="manifesto-pillar-card">
                    <div class="pillar-icon-badge" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1D4F32" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
                            <path d="m9 12 2 2 4-4"/>
                        </svg>
                    </div>
                    <h3 class="pillar-title">THOUGHTFULLY<br>CRAFTED</h3>
                    <p class="pillar-text">Every ingredient is thoughtfully selected to create a convenient, natural wellness blend.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- SECTION 6.5: WELLNESS THAT LIVES BEYOND US -->
    <!-- Our Generational Promise                   -->
    <!-- Matching Reference (media_1794654065914.jpg)-->
    <!-- ========================================== -->
    <section class="section-generational-promise" id="generational-promise" aria-label="Wellness That Lives Beyond Us — Generational Promise">
        <!-- Clean Family Background Canvas (Asset: a-q.png) -->
        <div class="generational-bg-stage" aria-hidden="true">
            <img src="assets/images/a-q.png" alt="Multi-generational family in nature" class="generational-family-img" loading="lazy">
        </div>

        <!-- Foreground Editorial Content -->
        <div class="generational-container">
            <div class="generational-content-panel">
                <div class="generational-eyebrow-wrap">
                    <span class="generational-eyebrow">WELLNESS FOR EVERYDAY LIFE</span>
                </div>

                <h2 class="generational-headline">
                    <span class="gen-title-line">Thoughtful Nutrition.</span>
                    <span class="gen-title-line">For Every <em class="gen-italic">Generation.</em></span>
                </h2>
                
                <p class="generational-sub">
                    From everyday moments to lifelong traditions, we believe natural nutrition should be a simple part of caring for ourselves and those we love.
                </p>

                <div class="generational-cta-wrap">
                    <a href="shop" class="btn-generational-promise" aria-label="Explore Our Products">
                        <span>Explore Our Products</span>
                        <span class="promise-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================================================
         SECTION 8 — HOW TO ENJOY ABC+
         Continuous Animated Product Preparation Experience
         ========================================================================== -->
    <section class="section-enjoy" id="how-to-enjoy" aria-label="How to Enjoy ABC+">
        <div class="enjoy-container">
            <!-- Section Header -->
            <div class="enjoy-header text-center">
                <div class="enjoy-eyebrow-wrap">
                    <span class="enjoy-eyebrow-dash" aria-hidden="true"></span>
                    <span class="enjoy-eyebrow">HOW TO ENJOY ABC+</span>
                    <span class="enjoy-eyebrow-dash" aria-hidden="true"></span>
                </div>
                <h2 class="enjoy-headline">
                    Three Simple Steps.<br>
                    <span class="enjoy-headline-burgundy">One Daily Ritual.</span>
                </h2>
                <p class="enjoy-subtitle">A simple way to bring ABC+ into your everyday routine.</p>
            </div>

            <!-- Ritual Stages Flow -->
            <div class="enjoy-stages-track">
                <!-- STEP 1: SCOOP -->
                <div class="enjoy-stage-card stage-card-scoop" data-step="1">
                    <div class="stage-visual-wrapper">
                        <!-- Decorative SVG Ring with Drawing Stroke -->
                        <svg class="stage-ring-svg" viewBox="0 0 360 360" aria-hidden="true">
                            <circle class="stage-ring-bg" cx="180" cy="180" r="172"></circle>
                            <circle class="stage-ring-active" cx="180" cy="180" r="172"></circle>
                        </svg>

                        <!-- Circular Media Frame -->
                        <div class="stage-circle-inner">
                            <img src="assets/images/ritual-scoop.jpg?v=<?= file_exists(__DIR__ . '/assets/images/ritual-scoop.jpg') ? filemtime(__DIR__ . '/assets/images/ritual-scoop.jpg') : '1' ?>" alt="Scooping ABC+ whole food powder" class="stage-base-img scoop-base-img" loading="lazy">
                            <!-- Continuous Falling Granular Powder Canvas -->
                            <canvas class="stage-canvas canvas-powder" width="340" height="340" aria-hidden="true"></canvas>
                        </div>

                        <!-- Step Badge -->
                        <div class="stage-step-badge">
                            <span class="stage-step-num">01</span>
                        </div>
                    </div>

                    <!-- Stage Meta -->
                    <div class="stage-meta-info">
                        <div class="stage-title-wrap">
                            <h3 class="stage-title">SCOOP</h3>
                            <span class="stage-title-line" aria-hidden="true"></span>
                        </div>
                        <p class="stage-desc">
                            Add 1–2 teaspoons of ABC+ powder to a glass.
                        </p>
                    </div>
                </div>

                <!-- CONNECTOR 1 -->
                <div class="stage-connector" aria-hidden="true">
                    <div class="connector-line"></div>
                    <svg class="connector-arrow-svg" viewBox="0 0 40 24" fill="none">
                        <path d="M4 12H34M34 12L24 4M34 12L24 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <!-- STEP 2: MIX -->
                <div class="enjoy-stage-card stage-card-mix" data-step="2">
                    <div class="stage-visual-wrapper">
                        <!-- Decorative SVG Ring with Drawing Stroke -->
                        <svg class="stage-ring-svg" viewBox="0 0 360 360" aria-hidden="true">
                            <circle class="stage-ring-bg" cx="180" cy="180" r="172"></circle>
                            <circle class="stage-ring-active" cx="180" cy="180" r="172"></circle>
                        </svg>

                        <!-- Circular Media Frame -->
                        <div class="stage-circle-inner">
                            <img src="assets/images/ritual-mix.png?v=<?= file_exists(__DIR__ . '/assets/images/ritual-mix.png') ? filemtime(__DIR__ . '/assets/images/ritual-mix.png') : '1' ?>" alt="Mixing ABC+ powder with fresh water" class="stage-base-img mix-base-img" loading="lazy">
                            <!-- Continuous Pouring Water Stream & Ripples Canvas -->
                            <canvas class="stage-canvas canvas-water" width="340" height="340" aria-hidden="true"></canvas>
                        </div>

                        <!-- Step Badge -->
                        <div class="stage-step-badge">
                            <span class="stage-step-num">02</span>
                        </div>
                    </div>

                    <!-- Stage Meta -->
                    <div class="stage-meta-info">
                        <div class="stage-title-wrap">
                            <h3 class="stage-title">MIX</h3>
                            <span class="stage-title-line" aria-hidden="true"></span>
                        </div>
                        <p class="stage-desc">
                            Pour in warm water and stir well until evenly mixed.
                        </p>
                    </div>
                </div>

                <!-- CONNECTOR 2 -->
                <div class="stage-connector" aria-hidden="true">
                    <div class="connector-line"></div>
                    <svg class="connector-arrow-svg" viewBox="0 0 40 24" fill="none">
                        <path d="M4 12H34M34 12L24 4M34 12L24 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <!-- STEP 3: ENJOY -->
                <div class="enjoy-stage-card stage-card-enjoy" data-step="3">
                    <div class="stage-visual-wrapper">
                        <!-- Decorative SVG Ring with Drawing Stroke -->
                        <svg class="stage-ring-svg" viewBox="0 0 360 360" aria-hidden="true">
                            <circle class="stage-ring-bg" cx="180" cy="180" r="172"></circle>
                            <circle class="stage-ring-active" cx="180" cy="180" r="172"></circle>
                        </svg>

                        <!-- Circular Media Frame -->
                        <div class="stage-circle-inner">
                            <img src="assets/images/ritual-enjoy.jpg?v=<?= file_exists(__DIR__ . '/assets/images/ritual-enjoy.jpg') ? filemtime(__DIR__ . '/assets/images/ritual-enjoy.jpg') : '1' ?>" alt="Enjoying fresh sparkling ABC+ wellness drink" class="stage-base-img enjoy-base-img" loading="lazy">
                            <!-- Continuous Rising Effervescent Micro-Bubbles Canvas -->
                            <canvas class="stage-canvas canvas-bubbles" width="340" height="340" aria-hidden="true"></canvas>
                        </div>

                        <!-- Step Badge -->
                        <div class="stage-step-badge">
                            <span class="stage-step-num">03</span>
                        </div>
                    </div>

                    <!-- Stage Meta -->
                    <div class="stage-meta-info">
                        <div class="stage-title-wrap">
                            <h3 class="stage-title">ENJOY</h3>
                            <span class="stage-title-line" aria-hidden="true"></span>
                        </div>
                        <p class="stage-desc">
                            Enjoy your freshly prepared ABC+ drink as part of your daily routine.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Ritual Footer Note -->
            <div class="enjoy-footer-hint">
                <div class="enjoy-hint-pill">
                    <span class="hint-bullet" aria-hidden="true">✦</span>
                    <span class="hint-text">A little goodness, thoughtfully made for everyday life.</span>
                </div>
            </div>
        </div>
    </section>


    <!-- ==========================================================================
         SECTION 10 — THE NUTRONYX EXPERIENCE (WHAT OUR CUSTOMERS SAY)
         Bespoke Carousel Showcase with Verified Social Proof
         ========================================================================== -->
    <section class="section-experience" id="nutronyx-experience" aria-label="The NUTRONYX Experience — Customer Testimonials">
        <div class="experience-container">
            
            <!-- SECTION HEADER -->
            <div class="experience-header">
                <div class="experience-eyebrow-wrapper">
                    <span class="experience-eyebrow-rule" aria-hidden="true"></span>
                    <span class="experience-eyebrow">THE NUTRONYX COMMUNITY</span>
                    <span class="experience-eyebrow-rule" aria-hidden="true"></span>
                </div>
                
                <h2 class="experience-headline">Real Experiences.<br>Everyday Stories.</h2>
                
                <p class="experience-subtext">Discover how our customers are making NUTRONYX part of their everyday routines.</p>
                
                <!-- Central Botanical Leaf Emblem -->
                <div class="experience-header-leaf" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="#3D6A4E">
                        <path d="M17 8C8 10 5.9 16.17 3.82 21.34l1.89.66l.95-2.3c.48.17.98.3 1.34.3C19 20 22 3 22 3c-1 2-8 2.25-13 3.25S2 11.5 2 13.5s1.75 3.75 1.75 3.75C7 8 17 8 17 8z"/>
                    </svg>
                </div>
            </div>

            <!-- CAROUSEL WRAPPER WITH CLEAN EDITORIAL REVIEW CARDS -->
            <div class="experience-carousel-wrapper">
                <div class="experience-carousel-track" id="experienceCarouselTrack">
                    <?php foreach ($homeReviews as $idx => $hRev): 
                        $custName = $hRev['customer_name'] ?? 'Verified Customer';
                        $firstLetter = strtoupper(substr($custName, 0, 1));
                        $metaText = $reviewMetaMap[$custName] ?? (!empty($hRev['product_title']) ? $hRev['product_title'] : 'Verified Daily Ritualist');
                    ?>
                        <div class="experience-card experience-review-card <?= $idx === 0 ? 'is-active' : '' ?>" data-index="<?= $idx ?>" tabindex="0" role="group" aria-label="Review by <?= htmlspecialchars($custName) ?>">
                            <div class="review-card-top">
                                <div class="review-avatar-monogram" aria-hidden="true">
                                    <?= $firstLetter ?>
                                </div>
                                <div class="review-stars-quote">
                                    <div class="review-stars" aria-label="<?= (int)($hRev['rating'] ?? 5) ?> out of 5 stars">
                                        <?= str_repeat('★', (int)($hRev['rating'] ?? 5)) ?>
                                    </div>
                                    <?php if (!empty($hRev['title'])): ?>
                                        <div class="review-card-title">
                                            <?= htmlspecialchars($hRev['title']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <blockquote class="review-card-text">
                                        &ldquo;<?= htmlspecialchars($hRev['comment'] ?? '') ?>&rdquo;
                                    </blockquote>
                                    <?php if (!empty($hRev['admin_reply'])): ?>
                                        <div class="review-admin-reply">
                                            <strong>Nutronyx Response:</strong> <?= htmlspecialchars($hRev['admin_reply']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="review-author-meta">
                                <div class="author-name-line">
                                    <span class="author-name"><?= htmlspecialchars($custName) ?></span>
                                    <?php if (!empty($hRev['is_verified_buyer']) && (int)$hRev['is_verified_buyer'] === 1): ?>
                                        <span class="author-verified-tag" title="Verified Customer">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="#2E7D4E">
                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                            </svg>
                                            <span>Verified Buyer</span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="author-location-info">
                                    <span class="author-location-text"><?= htmlspecialchars($metaText) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- CAROUSEL CONTROLS -->
            <div class="experience-controls" aria-label="Testimonial navigation">
                <button type="button" class="carousel-btn btn-prev" aria-label="Previous review">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <div class="carousel-dots" role="tablist" aria-label="Slide indicators">
                    <?php foreach ($homeReviews as $idx => $hRev): ?>
                        <button type="button" class="dot <?= $idx === 0 ? 'is-active' : '' ?>" data-index="<?= $idx ?>" aria-label="Go to review <?= $idx + 1 ?>" role="tab" aria-selected="<?= $idx === 0 ? 'true' : 'false' ?>"></button>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="carousel-btn btn-next" aria-label="Next review">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>

            <!-- FOUR-PILLAR TRUST BAR -->
            <div class="experience-trust-bar">
                <!-- Metric 1: Happy Customers -->
                <div class="trust-stat-col">
                    <div class="trust-stat-icon-wrap" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#25533A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <div class="trust-stat-content">
                        <span class="stat-number">5,000+</span>
                        <span class="stat-label">Happy Customers</span>
                    </div>
                </div>

                <div class="trust-stat-divider" aria-hidden="true"></div>

                <!-- Metric 2: Average Rating -->
                <div class="trust-stat-col">
                    <div class="trust-stat-icon-wrap" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#25533A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2C8 6 6 10 6 13a6 6 0 0 0 12 0c0-3-2-7-6-11z"/>
                            <path d="M12 8v10"/>
                        </svg>
                    </div>
                    <div class="trust-stat-content">
                        <span class="stat-number">4.8/5</span>
                        <span class="stat-label">Average Rating</span>
                    </div>
                </div>

                <div class="trust-stat-divider" aria-hidden="true"></div>

                <!-- Metric 3: Real & Verified Reviews -->
                <div class="trust-stat-col">
                    <div class="trust-stat-icon-wrap" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#25533A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <polyline points="9 12 11 14 15 10"></polyline>
                        </svg>
                    </div>
                    <div class="trust-stat-content">
                        <span class="stat-number">100%</span>
                        <span class="stat-label">Real &amp; Verified Reviews</span>
                    </div>
                </div>

                <div class="trust-stat-divider" aria-hidden="true"></div>

                <!-- Metric 4: Trusted Across India -->
                <div class="trust-stat-col">
                    <div class="trust-stat-icon-wrap" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#25533A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                    </div>
                    <div class="trust-stat-content">
                        <span class="stat-title-lead">Trusted by</span>
                        <span class="stat-label">Customers Across India</span>
                    </div>
                </div>
            </div>

        </div>
    </section>



    <!-- ==========================================================================
         SECTION 13 — NUTRONYX PREMIUM SIGNATURE FOOTER
         A quieter kind of wellness. — Exact Editorial Reference Match
         ========================================================================== -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- GSAP 3 & ScrollTrigger CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    
    <!-- Interaction & Motion Script -->
    <script src="assets/js/main.js?v=20261124_v1"></script>
    <script>
    function onShowcasePillClick(variantId, price, btn) {
        if (arguments.length === 4) {
            btn = arguments[3];
        }
        document.querySelectorAll('.showcase-size-pill').forEach(b => {
            b.style.background = '#fff';
            b.style.borderColor = '#D1D5DB';
            b.style.color = '#374151';
        });
        if (btn) {
            btn.style.background = '#143D24';
            btn.style.borderColor = '#143D24';
            btn.style.color = '#fff';
        }
        
        const priceEl = document.getElementById('showcasePriceVal');
        const ctaBtn = document.getElementById('showcaseCtaBtn');
        
        if (priceEl) priceEl.textContent = '₹' + parseInt(price, 10).toLocaleString('en-IN');
        if (ctaBtn) ctaBtn.href = 'shop?variant=' + encodeURIComponent(variantId);
    }

    function onShowcaseVariantChange(variantId) {
        if (variantId === 'var_abc_500g') {
            onShowcasePillClick('var_abc_500g', 499, document.getElementById('scPill500'));
        } else {
            onShowcasePillClick('var_abc_250g', 299, document.getElementById('scPill250'));
        }
    }
    </script>
</body>
</html>




