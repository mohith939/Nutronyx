<?php
/**
 * NUTRONYX — Become a Partner
 * Exact Editorial Hero Design Matching Reference Composition
 */

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (empty($_SESSION['nutronyx_csrf_token'])) {
    $_SESSION['nutronyx_csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['nutronyx_csrf_token'];

$currentPage = 'partner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NUTRONYX</title>
    <!-- Favicon & Brand Icons -->
    <link rel=icon type=image/x-icon href="/favicon.ico">
    <link rel=icon type=image/png sizes="16x16" href="/favicon-16x16.png">
    <link rel=icon type=image/png sizes="32x32" href="/favicon-32x32.png">
    <link rel=icon type=image/png sizes="48x48" href="/favicon-48x48.png">
    <link rel=icon type=image/png sizes="96x96" href="/favicon-96x96.png">
    <link rel=icon type=image/png sizes="192x192" href="/favicon-192x192.png">
    <link rel=apple-touch-icon sizes="180x180" href="/apple-touch-icon.png">
    <link rel=manifest href="/site.webmanifest">
    <meta name=theme-color content="#174529">

    <meta name="description" content="Bring everyday wellness to more people. Partner with NUTRONYX and collaborate with a purpose-driven wellness brand.">

    <!-- Google Fonts: Cormorant Garamond, Playfair Display & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,400;1,500;1,600&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,400;1,500;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Global Base Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261124_v1">
    <!-- Scoped Partner Page Stylesheet -->
    <link rel="stylesheet" href="assets/css/partner.css?v=20261119_v2">
</head>
<body class="nutronyx-body partner-page-body">

    <!-- Header Navigation with Active Partner State -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main id="main-content">
        <!-- ==========================================================================
             SECTION 01: THE PARTNERSHIP HERO
             Exact Composition Match to Reference Artwork
             ========================================================================== -->
        <section class="partner-hero-section" id="partner-hero" aria-label="Section 01: Partnership Hero">
            
            <!-- Soft Dappled Botanical Shadows in Background -->
            <div class="partner-shadow-overlay" aria-hidden="true"></div>

            <div class="partner-hero-container">
                <div class="partner-hero-grid">
                    
                    <!-- ==============================================================
                         LEFT COLUMN: TYPOGRAPHY, CORE PILLARS & ACTIONS
                         ============================================================== -->
                    <div class="partner-hero-content-col">
                        
                        <!-- 1. Eyebrow Flanked by Fine Rules -->
                        <div class="partner-eyebrow-wrapper">
                            <span class="eyebrow-rule" aria-hidden="true"></span>
                            <span class="eyebrow-text">PARTNER WITH NUTRONYX</span>
                            <span class="eyebrow-rule" aria-hidden="true"></span>
                        </div>

                        <!-- 2. Primary Headline with Italic Accent -->
                        <h1 class="partner-main-headline">
                            <span class="headline-line">Bring Everyday</span>
                            <span class="headline-line">Wellness to <em class="headline-italic">More People.</em></span>
                        </h1>

                        <!-- 3. Supporting Description -->
                        <p class="partner-hero-description">
                            Collaborate with a purpose-driven wellness brand and create lasting value through clean, plant-based nutrition.
                        </p>

                        <!-- 4. Core Partnership Pillars (TITLES ONLY, NO SUBTITLES) -->
                        <div class="partner-pillars-row" aria-label="Core Partnership Pillars">
                            
                            <!-- Pillar 1: Commercial Integrity -->
                            <div class="pillar-card">
                                <div class="pillar-icon-box" aria-hidden="true">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 17l-5-5a2.828 2.828 0 1 1 4-4l1 1 1-1a2.828 2.828 0 1 1 4 4l-5 5z"/>
                                        <path d="M18 10l3 3a2.828 2.828 0 0 1-4 4l-1-1"/>
                                        <path d="M6 14l-3 3a2.828 2.828 0 0 0 4 4l1-1"/>
                                    </svg>
                                </div>
                                <h3 class="pillar-title">COMMERCIAL<br>INTEGRITY</h3>
                            </div>

                            <span class="pillar-sep-rule" aria-hidden="true"></span>

                            <!-- Pillar 2: Clean Whole-Food Purity -->
                            <div class="pillar-card">
                                <div class="pillar-icon-box" aria-hidden="true">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22v-8"/>
                                        <path d="M12 14c0-5.5 4.5-9 9-9 0 4.5-3.5 9-9 9z"/>
                                        <path d="M12 18c0-3.5-3-6-6-6 0 3 2.5 6 6 6z"/>
                                    </svg>
                                </div>
                                <h3 class="pillar-title">CLEAN<br>WHOLE-FOOD PURITY</h3>
                            </div>

                            <span class="pillar-sep-rule" aria-hidden="true"></span>

                            <!-- Pillar 3: Dedicated Concierge -->
                            <div class="pillar-card">
                                <div class="pillar-icon-box" aria-hidden="true">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                </div>
                                <h3 class="pillar-title">DEDICATED<br>CONCIERGE</h3>
                            </div>

                        </div>

                        <!-- 5. Dual Action Buttons -->
                        <div class="partner-actions-group">
                            <!-- Primary Button -->
                            <a href="#partner-application" class="btn-become-partner" aria-label="Become a Partner - Navigate to application">
                                <span class="btn-text">BECOME A PARTNER</span>
                                <span class="btn-arrow" aria-hidden="true">&rarr;</span>
                            </a>

                            <!-- Secondary Button -->
                            <a href="tel:9392633211" class="btn-schedule-call" aria-label="Schedule a Partnership Call - Call 93926 33211">
                                <span class="btn-cal-icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                </span>
                                <span class="btn-text">SCHEDULE A PARTNERSHIP CALL</span>
                            </a>
                        </div>

                        <!-- 6. Trust & Credential Verification Bar -->
                        <div class="partner-trust-credential-bar">
                            <span class="trust-caption">TRUSTED BY WELLNESS-FOCUSED BUSINESSES</span>
                            
                            <div class="trust-badges-wrapper">
                                <!-- Laurel Left -->
                                <span class="laurel-leaf laurel-left" aria-hidden="true">
                                    <svg width="22" height="28" viewBox="0 0 24 32" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <path d="M15 2C14 7 8 10 6 15C4 20 6 27 11 31"/>
                                        <path d="M12 7C9 7 7 9 7 13"/>
                                        <path d="M10 15C7 16 6 19 7 23"/>
                                    </svg>
                                </span>

                                <div class="trust-metric">
                                    <span class="metric-top">100%</span>
                                    <span class="metric-sub">Plant-Based</span>
                                </div>

                                <span class="trust-divider" aria-hidden="true"></span>

                                <div class="trust-metric">
                                    <span class="metric-top">Zero</span>
                                    <span class="metric-sub">Synthetics</span>
                                </div>

                                <span class="trust-divider" aria-hidden="true"></span>

                                <div class="trust-metric">
                                    <span class="metric-top">Made in</span>
                                    <span class="metric-sub">India</span>
                                </div>

                                <!-- Laurel Right -->
                                <span class="laurel-leaf laurel-right" aria-hidden="true">
                                    <svg width="22" height="28" viewBox="0 0 24 32" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <path d="M9 2C10 7 16 10 18 15C20 20 18 27 13 31"/>
                                        <path d="M12 7C15 7 17 9 17 13"/>
                                        <path d="M14 15C17 16 18 19 17 23"/>
                                    </svg>
                                </span>
                            </div>
                        </div>

                    </div>

                    <!-- ==============================================================
                         RIGHT COLUMN: EXACT PRODUCT SHOWCASE ARTWORK & PLINTH PLAQUE
                         ============================================================== -->
                    <div class="partner-hero-visual-col">
                        <div class="partner-stage-frame" id="partnerStageFrame">
                            
                            <!-- Main High-Resolution Showcase Photography -->
                            <img src="assets/images/partner-hero-stage.jpg" 
                                 alt="NUTRONYX ABC+ Daily Wellness Powder jar resting on natural stone plinth with fresh beetroot, amla, carrot, ginger and lemon" 
                                 class="partner-stage-artwork" 
                                 loading="eager">

                            <!-- Floating Glassmorphic Plinth Provenance Plaque -->
                            <div class="plinth-provenance-plaque">
                                <div class="plaque-quote-box">
                                    <span class="plaque-tag">NUTRONYX ALLIANCE ARCHIVE</span>
                                    <p class="plaque-quote-text">
                                        &ldquo;When commerce aligns with nature, growth becomes enduring.&rdquo;
                                    </p>
                                </div>
                                
                                <span class="plaque-vertical-rule" aria-hidden="true"></span>

                                <div class="plaque-stat-box">
                                    <span class="stat-top">100%</span>
                                    <span class="stat-title">WHOLE FOODS</span>
                                    <span class="stat-code">N&deg; 2026.01</span>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ==========================================================================
             SECTION 02: WHY PARTNER WITH NUTRONYX
             Premium Editorial Value Proposition & Alliance Principles
             ========================================================================== -->
        <!-- ==========================================================================
             SECTION 02: WHY PARTNER WITH NUTRONYX
             "Our Partnership Philosophy" — Premium Editorial Value Proposition
             ========================================================================== -->
        <section class="partner-why-section" id="why-partner" aria-label="Section 02: Why Partner with NUTRONYX">
            <!-- Architectural Background Ambience -->
            <div class="why-ambient-glow" aria-hidden="true"></div>

            <div class="why-container">
                <div class="why-editorial-grid">
                    
                    <!-- ==============================================================
                         LEFT COLUMN: NARRATIVE ANCHOR & PROVENANCE BOTANICAL FRAME
                         ============================================================== -->
                    <div class="why-anchor-col">
                        <div class="why-sticky-anchor">
                            
                            <!-- Eyebrow with Single Leading Line -->
                            <div class="why-eyebrow-wrapper">
                                <span class="why-eyebrow-rule" aria-hidden="true"></span>
                                <span class="why-eyebrow-text">OUR PARTNERSHIP PHILOSOPHY</span>
                            </div>

                            <!-- Primary Headline -->
                            <h2 class="why-main-headline">
                                <span class="why-hl-line">Better Wellness.</span>
                                <span class="why-hl-line"><em class="why-hl-italic">Stronger Partnerships.</em></span>
                            </h2>

                            <!-- Supporting Narrative Copy -->
                            <p class="why-supporting-copy">
                                We believe meaningful partnerships are built on shared values, uncompromising quality, and a vision for better everyday wellness.
                            </p>

                            <!-- Floating Provenance Plaque -->
                            <div class="why-botanical-art-stage">
                                <div class="why-provenance-pill">
                                    <div class="provenance-pill-icon" aria-hidden="true">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 22v-8"/>
                                            <path d="M12 14c0-5.5 4.5-9 9-9 0 4.5-3.5 9-9 9z"/>
                                            <path d="M12 18c0-3.5-3-6-6-6 0 3 2.5 6 6 6z"/>
                                        </svg>
                                    </div>
                                    <div class="provenance-pill-text">
                                        <span class="provenance-pill-title">PROVENANCE &bull; CRAFTSMANSHIP</span>
                                        <span class="provenance-pill-sub">Botanical Purity &bull; Built for Enduring Enterprise</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- ==============================================================
                         RIGHT COLUMN: FOUR ELEVATED PHILOSOPHY CARDS (DESCRIPTIONS REMOVED)
                         ============================================================== -->
                    <div class="why-principles-col">
                        <div class="why-philosophy-cards-stack">
                            
                            <!-- Card 01: Foundational Purity -->
                            <div class="why-phil-card" data-index="0">
                                <div class="phil-card-icon-badge" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22v-8"/>
                                        <path d="M12 14c0-5.5 4.5-9 9-9 0 4.5-3.5 9-9 9z"/>
                                        <path d="M12 18c0-3.5-3-6-6-6 0 3 2.5 6 6 6z"/>
                                    </svg>
                                </div>
                                <span class="phil-card-divider" aria-hidden="true"></span>
                                <div class="phil-card-content">
                                    <span class="phil-card-tag">FOUNDATIONAL PURITY</span>
                                    <h3 class="phil-card-title">Premium Product Standards</h3>
                                </div>
                            </div>

                            <!-- Card 02: Mutual Integrity -->
                            <div class="why-phil-card" data-index="1">
                                <div class="phil-card-icon-badge" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 11l-2.5-2.5a2 2 0 0 0-2.8 0L11 10.2l-1.7-1.7a2 2 0 0 0-2.8 0L4 11"></path>
                                        <path d="M2 13l4.5 4.5a2 2 0 0 0 2.8 0L12 15l2.7 2.5a2 2 0 0 0 2.8 0L22 13"></path>
                                        <path d="M7 11.5l3.5 3.5"></path>
                                        <path d="M13.5 15l3.5-3.5"></path>
                                    </svg>
                                </div>
                                <span class="phil-card-divider" aria-hidden="true"></span>
                                <div class="phil-card-content">
                                    <span class="phil-card-tag">MUTUAL INTEGRITY</span>
                                    <h3 class="phil-card-title">Shared Values</h3>
                                </div>
                            </div>

                            <!-- Card 03: Consistent Operations -->
                            <div class="why-phil-card" data-index="2">
                                <div class="phil-card-icon-badge" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                </div>
                                <span class="phil-card-divider" aria-hidden="true"></span>
                                <div class="phil-card-content">
                                    <span class="phil-card-tag">CONSISTENT OPERATIONS</span>
                                    <h3 class="phil-card-title">Reliable Collaboration</h3>
                                </div>
                            </div>

                            <!-- Card 04: Sustained Horizon -->
                            <div class="why-phil-card" data-index="3">
                                <div class="phil-card-icon-badge" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                        <polyline points="17 6 23 6 23 12"></polyline>
                                        <rect x="2" y="14" width="4" height="6" rx="1"></rect>
                                        <rect x="9" y="10" width="4" height="10" rx="1"></rect>
                                        <rect x="16" y="6" width="4" height="14" rx="1"></rect>
                                    </svg>
                                </div>
                                <span class="phil-card-divider" aria-hidden="true"></span>
                                <div class="phil-card-content">
                                    <span class="phil-card-tag">SUSTAINED HORIZON</span>
                                    <h3 class="phil-card-title">Long-Term Growth</h3>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ==========================================================================
             SECTION 03: WHO CAN PARTNER WITH US
             Premium Editorial Category Discovery Experience
             ========================================================================== -->
        <!-- ==========================================================================
             SECTION 03: WHO CAN PARTNER WITH US
             "Who Can Grow With Us?" — 6-Card Showcase Discovery Experience
             ========================================================================== -->
        <section class="partner-categories-section" id="who-can-partner" aria-label="Section 03: Partnership Opportunities">
            <div class="showcase-container">
                
                <!-- SECTION HEADER -->
                <div class="showcase-header">
                    <!-- Sprout Icon -->
                    <div class="showcase-sprout-icon" aria-hidden="true">
                        <svg width="28" height="24" viewBox="0 0 28 24" fill="none">
                            <path d="M14 20C14 12 6 8 2 10C2 17 8 21 14 20Z" fill="#1E5736"/>
                            <path d="M14 20C14 12 22 8 26 10C26 17 20 21 14 20Z" fill="#25533A"/>
                            <path d="M14 24V19" stroke="#1E5736" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>

                    <!-- Eyebrow with Hairlines -->
                    <div class="showcase-eyebrow-wrapper">
                        <span class="showcase-eyebrow-rule" aria-hidden="true"></span>
                        <span class="showcase-eyebrow-text">PARTNERSHIP OPPORTUNITIES</span>
                        <span class="showcase-eyebrow-rule" aria-hidden="true"></span>
                    </div>

                    <!-- Headline with Italic Green Accent -->
                    <h2 class="showcase-main-headline">
                        <span class="showcase-hl-text">Who Can <em class="showcase-hl-italic">Grow With Us?</em></span>
                    </h2>

                    <!-- Curved Green Flourish Under Headline -->
                    <div class="showcase-headline-flourish" aria-hidden="true">
                        <svg width="120" height="12" viewBox="0 0 120 12" fill="none">
                            <path d="M2 3C40 11 80 11 118 3" stroke="#1E5736" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </div>

                    <!-- Supporting Description -->
                    <p class="showcase-subheadline">
                        From independent retailers to wellness advocates, we welcome people and businesses who share our commitment to thoughtful everyday nutrition.
                    </p>
                </div>

                <!-- 6-CARD SHOWCASE HORIZONTAL ROW -->
                <div class="showcase-cards-row" role="list">
                    
                    <!-- 1. Retail Stores -->
                    <a href="#partner-application" class="showcase-card" role="listitem" data-category="retail" aria-label="Partner with us: Retail Stores">
                        <div class="showcase-card-inner">
                            <div class="showcase-icon-badge" aria-hidden="true">
                                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"></path>
                                    <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                                    <path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"></path>
                                    <path d="M2 7h20"></path>
                                </svg>
                            </div>
                            <h3 class="showcase-card-title">Retail<br>Stores</h3>
                        </div>
                    </a>

                    <!-- 2. Distributors -->
                    <a href="#partner-application" class="showcase-card" role="listitem" data-category="distributors" aria-label="Partner with us: Distributors">
                        <div class="showcase-card-inner">
                            <div class="showcase-icon-badge" aria-hidden="true">
                                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 3L2 8.5L12 14L22 8.5L12 3Z"></path>
                                    <path d="M2 8.5V16.5L12 22V14"></path>
                                    <path d="M22 8.5V16.5L12 22"></path>
                                    <path d="M12 14L16.5 11.5"></path>
                                    <path d="M12 14L7.5 11.5"></path>
                                </svg>
                            </div>
                            <h3 class="showcase-card-title">Distributors</h3>
                        </div>
                    </a>

                    <!-- 3. Corporate Wellness -->
                    <a href="#partner-application" class="showcase-card" role="listitem" data-category="corporate" aria-label="Partner with us: Corporate Wellness">
                        <div class="showcase-card-inner">
                            <div class="showcase-icon-badge" aria-hidden="true">
                                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <h3 class="showcase-card-title">Corporate<br>Wellness</h3>
                        </div>
                    </a>

                    <!-- 4. Gyms & Fitness Centres -->
                    <a href="#partner-application" class="showcase-card" role="listitem" data-category="fitness" aria-label="Partner with us: Gyms & Fitness Centres">
                        <div class="showcase-card-inner">
                            <div class="showcase-icon-badge" aria-hidden="true">
                                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6.5 6.5l11 11"></path>
                                    <path d="M21 21l-3-3"></path>
                                    <path d="M3 3l3 3"></path>
                                    <path d="M18 22l4-4-1.5-1.5-4 4L18 22z"></path>
                                    <path d="M2 6l4-4 1.5 1.5-4 4L2 6z"></path>
                                    <path d="M14 10l-4-4"></path>
                                    <path d="M18 14l-4-4"></path>
                                </svg>
                            </div>
                            <h3 class="showcase-card-title">Gyms &amp;<br>Fitness Centres</h3>
                        </div>
                    </a>

                    <!-- 5. Wellness Professionals -->
                    <a href="#partner-application" class="showcase-card" role="listitem" data-category="professionals" aria-label="Partner with us: Wellness Professionals">
                        <div class="showcase-card-inner">
                            <div class="showcase-icon-badge" aria-hidden="true">
                                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2C8 6 6 10 6 13a6 6 0 0 0 12 0c0-3-2-7-6-11z"></path>
                                    <path d="M6 13c-2 0-4 1-4 3 0 4 6 6 10 6s10-2 10-6c0-2-2-3-4-3"></path>
                                    <path d="M12 22V13"></path>
                                </svg>
                            </div>
                            <h3 class="showcase-card-title">Wellness<br>Professionals</h3>
                        </div>
                    </a>

                    <!-- 6. Creators & Influencers -->
                    <a href="#partner-application" class="showcase-card" role="listitem" data-category="creators" aria-label="Partner with us: Creators & Influencers">
                        <div class="showcase-card-inner">
                            <div class="showcase-icon-badge" aria-hidden="true">
                                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                                </svg>
                            </div>
                            <h3 class="showcase-card-title">Creators &amp;<br>Influencers</h3>
                        </div>
                    </a>
                </div>

            </div>
        </section>

        <!-- ==========================================================================
             SECTION 04: BUILT FOR PARTNERSHIPS THAT LAST
             Premium Trust & Partnership Architecture (Bilateral Keystone Composition)
             ========================================================================== -->
        <section class="partner-trust-section" id="built-for-trust" aria-label="Section 04: Built for Partnerships That Last">
            
            <!-- Atmospheric Lighting & Architectural Coordinates -->
            <div class="trust-bg-ambience" aria-hidden="true">
                <div class="trust-ambient-orb"></div>
            </div>

            <div class="trust-container">
                
                <!-- SECTION HEADER -->
                <div class="trust-header-block">
                    <div class="trust-eyebrow-wrapper">
                        <span class="trust-eyebrow-rule" aria-hidden="true"></span>
                        <span class="trust-eyebrow-text">BUILT ON TRUST</span>
                        <span class="trust-eyebrow-rule" aria-hidden="true"></span>
                    </div>

                    <h2 class="trust-main-headline">
                        <span class="trust-hl-line">Built for Partnerships <em class="trust-hl-italic">That Last.</em></span>
                    </h2>

                    <p class="trust-supporting-copy">
                        Strong partnerships are built on more than products. They grow through shared values, dependable collaboration, and a commitment to creating lasting value.
                    </p>
                </div>

                <!-- BILATERAL KEYSTONE COMPOSITION (3-COLUMN ARCHITECTURAL SPREAD) -->
                <div class="trust-bilateral-grid">
                    
                    <!-- LEFT WING: FOUNDATIONAL PILLARS (01 & 02) -->
                    <div class="trust-wing trust-wing-left">
                        
                        <!-- ADVANTAGE 01 -->
                        <article class="trust-advantage-card card-left" data-index="0">
                            <div class="advantage-header">
                                <span class="advantage-num-badge">01</span>
                                <span class="advantage-meta-tag">FOUNDATIONAL PURITY</span>
                            </div>
                            <h3 class="advantage-title">PREMIUM PRODUCT STANDARDS</h3>
                            <p class="advantage-desc">
                                Thoughtfully developed wellness products with a commitment to ingredient integrity and quality.
                            </p>
                            <div class="advantage-foot-hairline" aria-hidden="true"></div>
                        </article>

                        <!-- ADVANTAGE 02 -->
                        <article class="trust-advantage-card card-left" data-index="1">
                            <div class="advantage-header">
                                <span class="advantage-num-badge">02</span>
                                <span class="advantage-meta-tag">OPERATIONAL INTEGRITY</span>
                            </div>
                            <h3 class="advantage-title">RELIABLE SUPPLY CHAIN</h3>
                            <p class="advantage-desc">
                                A dependable approach to product availability and business coordination.
                            </p>
                            <div class="advantage-foot-hairline" aria-hidden="true"></div>
                        </article>

                    </div>

                    <!-- CENTER KEYSTONE: VISUAL MONOLITH & ARCHIVAL PROVENANCE -->
                    <div class="trust-keystone-stage" id="trustKeystoneStage">
                        <div class="keystone-frame">
                            <img src="assets/images/trust-keystone-altar.jpg" 
                                 alt="Architectural limestone pedestal with amber apothecary jar, fresh amla, ginger, and handcrafted brass scales" 
                                 class="keystone-image"
                                 loading="lazy">
                            
                            <!-- Floating Keystone Hallmark Plaque -->
                            <div class="keystone-hallmark-plaque">
                                <div class="hallmark-icon" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    </svg>
                                </div>
                                <div class="hallmark-info">
                                    <span class="hallmark-tag">PERENNIAL STANDARD</span>
                                    <span class="hallmark-title">COMMERCIAL INTEGRITY</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT WING: HORIZONS & SUPPORT (03 & 04) -->
                    <div class="trust-wing trust-wing-right">
                        
                        <!-- ADVANTAGE 03 -->
                        <article class="trust-advantage-card card-right" data-index="2">
                            <div class="advantage-header">
                                <span class="advantage-num-badge">03</span>
                                <span class="advantage-meta-tag">COLLABORATIVE CONCIERGE</span>
                            </div>
                            <h3 class="advantage-title">DEDICATED PARTNERSHIP SUPPORT</h3>
                            <p class="advantage-desc">
                                A collaborative approach that values communication, clarity, and meaningful business relationships.
                            </p>
                            <div class="advantage-foot-hairline" aria-hidden="true"></div>
                        </article>

                        <!-- ADVANTAGE 04 -->
                        <article class="trust-advantage-card card-right" data-index="3">
                            <div class="advantage-header">
                                <span class="advantage-num-badge">04</span>
                                <span class="advantage-meta-tag">MUTUAL HORIZON</span>
                            </div>
                            <h3 class="advantage-title">LONG-TERM GROWTH</h3>
                            <p class="advantage-desc">
                                Building opportunities through shared ambition, thoughtful collaboration, and lasting value.
                            </p>
                            <div class="advantage-foot-hairline" aria-hidden="true"></div>
                        </article>

                    </div>

                </div>

                <!-- BOTTOM ARCHITECTURAL COVENANT RIBBON -->
                <div class="trust-covenant-ribbon">
                    <div class="covenant-item">
                        <span class="covenant-dot" aria-hidden="true"></span>
                        <span class="covenant-text">BOTANICAL PURITY</span>
                    </div>
                    <span class="covenant-divider" aria-hidden="true">&bull;</span>
                    <div class="covenant-item">
                        <span class="covenant-dot" aria-hidden="true"></span>
                        <span class="covenant-text">OPERATIONAL CLARITY</span>
                    </div>
                    <span class="covenant-divider" aria-hidden="true">&bull;</span>
                    <div class="covenant-item">
                        <span class="covenant-dot" aria-hidden="true"></span>
                        <span class="covenant-text">PERSONAL COORDINATION</span>
                    </div>
                    <span class="covenant-divider" aria-hidden="true">&bull;</span>
                    <div class="covenant-item">
                        <span class="covenant-dot" aria-hidden="true"></span>
                        <span class="covenant-text">SUSTAINED HORIZONS</span>
                    </div>
                </div>

            </div>
        </section>

        <!-- ==========================================================================
             SECTION 05: THE PARTNERSHIP JOURNEY
             Premium Editorial Process Experience (Alternating Promenade Architecture)
             ========================================================================== -->
        <section class="partner-journey-section" id="partnership-journey" aria-label="Section 05: The Partnership Journey">
            
            <!-- Atmospheric Lighting & Coordinate Grid -->
            <div class="journey-bg-ambience" aria-hidden="true">
                <div class="journey-ambient-glow"></div>
            </div>

            <div class="journey-container">
                
                <!-- LAYER 1: EDITORIAL INTRODUCTION -->
                <div class="journey-header-block">
                    <div class="journey-eyebrow-wrapper">
                        <span class="journey-eyebrow-rule" aria-hidden="true"></span>
                        <span class="journey-eyebrow-text">HOW WE BEGIN</span>
                        <span class="journey-eyebrow-rule" aria-hidden="true"></span>
                    </div>

                    <h2 class="journey-main-headline">
                        <span class="journey-hl-line">Every Great Partnership</span>
                        <span class="journey-hl-line"><em class="journey-hl-italic">Begins With a Conversation.</em></span>
                    </h2>

                    <p class="journey-supporting-copy">
                        A thoughtful, straightforward process designed to help us understand your business, explore shared opportunities, and build meaningful partnerships.
                    </p>
                </div>

                <!-- LAYER 2: THE PARTNERSHIP JOURNEY (ALTERNATING PROMENADE) -->
                <div class="journey-promenade" id="journeyPromenade">
                    
                    <!-- Continuous Central Spine Track -->
                    <div class="journey-spine-track" aria-hidden="true">
                        <div class="journey-spine-line" id="journeySpineLine"></div>
                    </div>

                    <!-- STEP 01: SUBMIT YOUR APPLICATION (LEFT) -->
                    <div class="journey-step-row step-row-left" data-step="1">
                        <div class="journey-step-card card-left">
                            <div class="step-card-header">
                                <span class="step-num-display">01</span>
                                <span class="step-meta-tag">STAGE I &bull; INITIAL INQUIRY</span>
                            </div>
                            <h3 class="step-title">SUBMIT YOUR APPLICATION</h3>
                            <p class="step-desc">
                                Tell us about your business, your interests, and the kind of partnership you're looking to build.
                            </p>
                            <div class="step-card-accent" aria-hidden="true"></div>
                        </div>
                        <div class="journey-step-node" aria-hidden="true">
                            <span class="node-ring">
                                <span class="node-dot"></span>
                            </span>
                        </div>
                        <div class="journey-step-spacer" aria-hidden="true"></div>
                    </div>

                    <!-- STEP 02: APPLICATION REVIEW (RIGHT) -->
                    <div class="journey-step-row step-row-right" data-step="2">
                        <div class="journey-step-spacer" aria-hidden="true"></div>
                        <div class="journey-step-node" aria-hidden="true">
                            <span class="node-ring">
                                <span class="node-dot"></span>
                            </span>
                        </div>
                        <div class="journey-step-card card-right">
                            <div class="step-card-header">
                                <span class="step-num-display">02</span>
                                <span class="step-meta-tag">STAGE II &bull; STRATEGIC REVIEW</span>
                            </div>
                            <h3 class="step-title">APPLICATION REVIEW</h3>
                            <p class="step-desc">
                                Our team reviews your information to understand your business and partnership goals.
                            </p>
                            <div class="step-card-accent" aria-hidden="true"></div>
                        </div>
                    </div>

                    <!-- STEP 03: BUSINESS DISCUSSION (LEFT) -->
                    <div class="journey-step-row step-row-left" data-step="3">
                        <div class="journey-step-card card-left">
                            <div class="step-card-header">
                                <span class="step-num-display">03</span>
                                <span class="step-meta-tag">STAGE III &bull; DIRECT DIALOGUE</span>
                            </div>
                            <h3 class="step-title">BUSINESS DISCUSSION</h3>
                            <p class="step-desc">
                                We connect to discuss your interests, explore opportunities, and understand how we can collaborate.
                            </p>
                            <div class="step-card-accent" aria-hidden="true"></div>
                        </div>
                        <div class="journey-step-node" aria-hidden="true">
                            <span class="node-ring">
                                <span class="node-dot"></span>
                            </span>
                        </div>
                        <div class="journey-step-spacer" aria-hidden="true"></div>
                    </div>

                    <!-- STEP 04: WELCOME TO NUTRONYX (RIGHT) -->
                    <div class="journey-step-row step-row-right" data-step="4">
                        <div class="journey-step-spacer" aria-hidden="true"></div>
                        <div class="journey-step-node" aria-hidden="true">
                            <span class="node-ring">
                                <span class="node-dot"></span>
                            </span>
                        </div>
                        <div class="journey-step-card card-right">
                            <div class="step-card-header">
                                <span class="step-num-display">04</span>
                                <span class="step-meta-tag">STAGE IV &bull; ALLIANCE INITIATION</span>
                            </div>
                            <h3 class="step-title">WELCOME TO NUTRONYX</h3>
                            <p class="step-desc">
                                When there's a shared vision and a strong foundation, we begin building our partnership together.
                            </p>
                            <div class="step-card-accent" aria-hidden="true"></div>
                        </div>
                    </div>

                </div>

                <!-- LAYER 3: CLOSING STATEMENT & APPLICATION BRIDGE -->
                <div class="journey-closing-bridge">
                    <div class="closing-crest" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M12 2C8 6 6 10 6 13a6 6 0 0 0 12 0c0-3-2-7-6-11z"/>
                            <path d="M12 8v10"/>
                        </svg>
                    </div>
                    <blockquote class="closing-quote-statement">
                        &ldquo;Every successful partnership begins with a conversation.&rdquo;
                    </blockquote>
                    <p class="closing-guidance-sub">
                        Ready to discuss how we can grow together? Share your details with our team.
                    </p>
                    <div class="closing-cta-action">
                        <a href="#partner-application" class="btn-journey-start" aria-label="Start the conversation - Proceed to application">
                            <span class="btn-journey-text">START THE CONVERSATION</span>
                            <span class="btn-journey-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </div>

            </div>
        </section>

        <!-- ==========================================================================
             SECTION 13: THE PARTNERSHIP APPLICATION
             "Begin Your Alliance with Nutronyx."
             ========================================================================== -->
        <section class="partner-app-section" id="partner-application" aria-label="Section 13: Partnership Application">
            <div class="partner-app-container">
                
                <div class="partner-app-header">
                    <div class="partner-app-eyebrow">
                        <span class="eyebrow-dash" aria-hidden="true"></span>
                        <span class="eyebrow-text">STAGE V &bull; STRATEGIC APPLICATION</span>
                        <span class="eyebrow-dash" aria-hidden="true"></span>
                    </div>
                    <h2 class="partner-app-title">Begin Your Alliance with Nutronyx.</h2>
                    <p class="partner-app-subtitle">
                        Whether you are a wellness center, retail curator, healthcare practitioner, or brand creator, let’s explore how we can collaborate.
                    </p>
                </div>

                <div class="partner-card-wrapper">
                    <!-- Alert notification container -->
                    <div class="partner-form-alert" id="partnerFormAlert" style="display: none;" role="alert" aria-live="polite">
                        <span class="alert-icon" id="partnerAlertIcon"></span>
                        <span class="alert-text" id="partnerAlertText"></span>
                    </div>

                    <!-- Application Form -->
                    <form id="partnerApplicationForm" class="partner-form-inner" method="POST" action="api/partner-handler.php" novalidate>
                        <!-- Honeypot & Timing Trap -->
                        <div style="position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden;" aria-hidden="true">
                            <label for="partner_bot_catch">Leave empty</label>
                            <input type="text" name="bot_catch" id="partner_bot_catch" tabindex="-1" autocomplete="off">
                        </div>
                        <input type="hidden" name="form_timestamp" id="partnerFormTimestamp" value="<?= time() ?>">
                        <input type="hidden" name="csrf_token" id="partnerCsrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="partner-grid-2col">
                            <div class="partner-field-wrap" id="wrap-partner-name">
                                <label for="partner_name" class="partner-label">Full Name <span class="req">*</span></label>
                                <input type="text" id="partner_name" name="full_name" class="partner-input" placeholder="e.g. Dr. Ananya Sharma" required autocomplete="name">
                            </div>
                            <div class="partner-field-wrap" id="wrap-partner-company">
                                <label for="partner_company" class="partner-label">Company / Brand / Practice <span class="req">*</span></label>
                                <input type="text" id="partner_company" name="company" class="partner-input" placeholder="e.g. Veda Wellness Sanctuary" required>
                            </div>
                        </div>

                        <div class="partner-grid-2col">
                            <div class="partner-field-wrap" id="wrap-partner-email">
                                <label for="partner_email" class="partner-label">Business Email <span class="req">*</span></label>
                                <input type="email" id="partner_email" name="email" class="partner-input" placeholder="name@organization.com" required autocomplete="email">
                            </div>
                            <div class="partner-field-wrap" id="wrap-partner-phone">
                                <label for="partner_phone" class="partner-label">Phone / WhatsApp Number <span class="req">*</span></label>
                                <input type="tel" id="partner_phone" name="phone" class="partner-input" placeholder="+91 98765 43210" required autocomplete="tel">
                            </div>
                        </div>

                        <div class="partner-field-wrap" id="wrap-partner-category">
                            <label for="partner_category" class="partner-label">Partnership Track / Category <span class="req">*</span></label>
                            <div class="partner-select-wrap">
                                <select id="partner_category" name="category" class="partner-select" required>
                                    <option value="" disabled selected>Select your partnership track...</option>
                                    <option value="Corporate Wellness &amp; Executive Gifting">Corporate Wellness &amp; Executive Gifting</option>
                                    <option value="Distributors &amp; Retail Alliances">Distributors &amp; Retail Alliances</option>
                                    <option value="Healthcare, Nutritionists &amp; Functional Clinics">Healthcare, Nutritionists &amp; Functional Clinics</option>
                                    <option value="Content Creators &amp; Wellness Ambassadors">Content Creators &amp; Wellness Ambassadors</option>
                                    <option value="Luxury Hospitality, Spas &amp; Wellness Retreats">Luxury Hospitality, Spas &amp; Wellness Retreats</option>
                                    <option value="Gyms, Fitness Centers &amp; Sports Nutrition">Gyms, Fitness Centers &amp; Sports Nutrition</option>
                                    <option value="Other Strategic Alliances">Other Strategic Alliances</option>
                                </select>
                                <span class="select-chevron" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </span>
                            </div>
                        </div>

                        <div class="partner-field-wrap" id="wrap-partner-message">
                            <label for="partner_message" class="partner-label">Proposed Collaboration / Notes <span class="req">*</span></label>
                            <textarea id="partner_message" name="message" class="partner-textarea" rows="4" placeholder="Tell us about your organization, anticipated reach, and how you envision collaborating with Nutronyx..." required></textarea>
                        </div>

                        <div class="partner-form-footer">
                            <div class="partner-form-reassurance">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                <span>Direct review by Nutronyx Founding Team. Response within 24-48 hours.</span>
                            </div>
                            <button type="submit" id="partnerSubmitBtn" class="partner-submit-btn">
                                <span class="btn-text">SUBMIT PARTNERSHIP APPLICATION</span>
                                <span class="btn-icon" aria-hidden="true">&rarr;</span>
                            </button>
                        </div>
                    </form>

                    <!-- Success Card View -->
                    <div id="partnerSuccessCard" class="partner-success-card" style="display: none;">
                        <div class="success-icon-wrap">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        <h3 class="success-title">Application Successfully Received</h3>
                        <p class="success-desc" id="partnerSuccessDesc">
                            Thank you. Your partnership proposal has been submitted to the Nutronyx Strategic Alliances team and our leadership has been alerted via email.
                        </p>
                        <div class="success-actions">
                            <button type="button" id="partnerResetBtn" class="btn-partner-another">Submit Another Application</button>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </main>

    <!-- ==========================================================================
         SIGNATURE EDITORIAL FOOTER (SECTION 13)
         "Nature, Thoughtfully."
         Exact Replicated Brand Footer
         ========================================================================== -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- GSAP 3 & ScrollTrigger CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>

    <!-- Scoped Partner Page Interaction Script -->
    <script src="assets/js/partner.js?v=20261117_v2"></script>

    <!-- Finstics Tap Handler -->
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
</body>
</html>
