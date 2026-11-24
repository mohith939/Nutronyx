<?php
/**
 * NUTRONYX — About Us Page
 * Section 01: Hero — "Built on nature. Driven by purpose."
 * Luxury Botanical Editorial Experience
 */

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$currentPage = "about";
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

    <meta name="description" content="At Nutronyx, we believe better health begins with better choices. We carefully select natural ingredients and thoughtfully craft them into simple, convenient nutrition.">

    <!-- Google Fonts: Cormorant Garamond, Playfair Display & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Global Base & Component Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261124_v1">
    <!-- Scoped About Page Stylesheet -->
    <link rel="stylesheet" href="assets/css/about.css?v=20261119_v1">
</head>
<body class="nutronyx-body about-page-body">

    <!-- Header Navigation with Active About State -->
    <?php include __DIR__ . "/includes/header.php"; ?>

    <main id="main-content">
        <!-- ==========================================================================
             SECTION 01: ABOUT US HERO
             "Built on nature. Driven by purpose. Crafted for everyday wellness."
             ========================================================================== -->
        <section class="about-hero-section" id="about-hero" aria-label="About Us &mdash; Built on nature. Driven by purpose.">
            
            <!-- Atmospheric Botanical Sunlight Shadows (Top-Left) -->
            <div class="about-shadow-top-left" aria-hidden="true"></div>

            <!-- Flowing Delicate Wave Contour Ribbons (Bottom) -->
            <div class="about-bottom-waves" aria-hidden="true">
                <svg viewBox="0 0 1440 120" fill="none" preserveAspectRatio="none">
                    <path d="M0,70 C360,110 680,30 1020,70 C1240,95 1360,40 1440,60 L1440,120 L0,120 Z" fill="rgba(168, 185, 138, 0.35)"/>
                    <path d="M0,85 C280,45 540,105 860,65 C1140,25 1320,80 1440,55 L1440,120 L0,120 Z" fill="rgba(124, 157, 17, 0.22)"/>
                    <path d="M0,98 C320,70 640,110 980,80 C1200,60 1340,95 1440,85 L1440,120 L0,120 Z" fill="rgba(215, 207, 192, 0.3)"/>
                </svg>
            </div>

            <div class="about-container">
                <div class="about-hero-grid">

                    <!-- ==============================================================
                         LEFT COLUMN: EDITORIAL CONTENT & 3-BUTTON GROUP
                         ============================================================== -->
                    <div class="about-hero-copy-col">
                        
                        <!-- Pill Eyebrow Badge -->
                        <div class="about-hero-badge" aria-label="Section: About Us">
                            <span>ABOUT US</span>
                        </div>

                        <!-- Main Editorial Headline (3 Distinct Lines) -->
                        <h1 class="about-main-headline">
                            <span class="about-hl-line">Built on nature.</span>
                            <span class="about-hl-line"><em class="about-hl-italic">Driven by purpose.</em></span>
                            <span class="about-hl-line about-hl-line-3">Crafted for everyday wellness.</span>
                        </h1>

                        <!-- Supporting Narrative -->
                        <p class="about-hero-description">
                            At Nutronyx, we believe better health begins with better choices. We carefully select natural ingredients and thoughtfully craft them into simple, convenient nutrition designed to become part of your everyday routine.
                        </p>

                        <!-- 3-Button Single Row Group -->
                        <div class="about-btn-group" role="group" aria-label="About Us navigation actions">
                            
                            <!-- 1. Our Story (Solid Dark Green Pill) -->
                            <a href="#our-story" class="about-cta-btn btn-solid" aria-label="Explore Our Story">
                                <span class="about-btn-icon" aria-hidden="true">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"></path>
                                        <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"></path>
                                    </svg>
                                </span>
                                <span>Our Story</span>
                                <span class="about-btn-arrow" aria-hidden="true">&rarr;</span>
                            </a>

                            <!-- 2. Mission (Outline Soft Pill) -->
                            <a href="#mission" class="about-cta-btn btn-outline" aria-label="Read Our Mission">
                                <span class="about-btn-icon" aria-hidden="true">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <circle cx="12" cy="12" r="6"></circle>
                                        <circle cx="12" cy="12" r="2"></circle>
                                    </svg>
                                </span>
                                <span>Mission</span>
                                <span class="about-btn-arrow" aria-hidden="true">&rarr;</span>
                            </a>

                            <!-- 3. Vision (Outline Soft Pill) -->
                            <a href="#vision" class="about-cta-btn btn-outline" aria-label="Discover Our Vision">
                                <span class="about-btn-icon" aria-hidden="true">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m8 3 4 8 5-5 5 15H2L8 3z"></path>
                                    </svg>
                                </span>
                                <span>Vision</span>
                                <span class="about-btn-arrow" aria-hidden="true">&rarr;</span>
                            </a>

                        </div>

                    </div>

                    <!-- ==============================================================
                         RIGHT COLUMN: HIGH-RES SUNRISE MOUNTAIN PHOTOGRAPHIC STAGE
                         ============================================================== -->
                    <div class="about-hero-visual-col">
                        <div class="about-stage-wrapper">
                            
                            <!-- Layered Accent Ghost Frame -->
                            <div class="about-stage-ghost-frame" aria-hidden="true"></div>

                            <!-- Main Rounded Photo Frame -->
                            <div class="about-stage-photo-box">
                                <img src="assets/images/about-hero-sunrise.jpg" 
                                     alt="Golden morning sunburst breaking over misty green mountain ridges and lush forest valley" 
                                     class="about-stage-img" 
                                     loading="eager">
                            </div>

                        </div>
                    </div>

                </div>
            </div>

        </section>

        <!-- ==========================================================================
             SECTION 02: OUR STORY
             "A Simple Vision for Everyday Wellness."
             ========================================================================== -->
        <section class="about-story-section" id="our-story" aria-label="Section 02: Our Story">
            
            <!-- Atmospheric Top-Right Leaf Shadow Overlay -->
            <div class="story-shadow-top-right" aria-hidden="true"></div>

            <!-- Flowing Bottom Delicate Wave Ribbons -->
            <div class="story-bottom-waves" aria-hidden="true">
                <svg viewBox="0 0 1440 120" fill="none" preserveAspectRatio="none">
                    <path d="M0,80 C320,110 640,40 980,80 C1200,105 1360,50 1440,70 L1440,120 L0,120 Z" fill="rgba(168, 185, 138, 0.3)"/>
                    <path d="M0,65 C280,100 540,45 860,85 C1140,115 1320,55 1440,75 L1440,120 L0,120 Z" fill="rgba(124, 157, 17, 0.18)"/>
                    <path d="M0,95 C360,60 700,105 1040,75 C1240,60 1360,90 1440,80 L1440,120 L0,120 Z" fill="rgba(215, 207, 192, 0.25)"/>
                </svg>
            </div>

            <div class="story-container">
                <div class="story-editorial-grid">

                    <!-- ==============================================================
                         LEFT COLUMN: HEADER & 4-MILESTONE TIMELINE RAIL
                         ============================================================== -->
                    <div class="story-content-col">
                        
                        <!-- Eyebrow with Twin-Leaf Sprout Icon -->
                        <div class="story-header-block">
                            <div class="story-sprout-icon" aria-hidden="true">
                                <svg width="24" height="20" viewBox="0 0 28 24" fill="none">
                                    <path d="M14 20C14 12 6 8 2 10C2 17 8 21 14 20Z" fill="#1E5736"/>
                                    <path d="M14 20C14 12 22 8 26 10C26 17 20 21 14 20Z" fill="#25533A"/>
                                    <path d="M14 24V19" stroke="#1E5736" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <div class="story-eyebrow-wrapper">
                                <span class="story-eyebrow-rule" aria-hidden="true"></span>
                                <span class="story-eyebrow-text">OUR STORY</span>
                                <span class="story-eyebrow-rule" aria-hidden="true"></span>
                            </div>

                            <!-- Main Editorial Headline -->
                            <h2 class="story-main-headline">
                                <span class="story-hl-line">A Simple Vision for</span>
                                <span class="story-hl-line"><em class="story-hl-italic">Everyday Wellness.</em></span>
                            </h2>
                        </div>

                        <!-- 4-Milestone Timeline Rail -->
                        <div class="story-timeline-track" role="list">
                            <div class="story-timeline-rail" aria-hidden="true"></div>

                            <!-- Item 1: Sprout / Inception -->
                            <div class="story-timeline-item" role="listitem">
                                <div class="story-node-badge" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"></path>
                                        <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"></path>
                                    </svg>
                                </div>
                                <div class="story-node-content">
                                    <p class="story-node-text">
                                        Nutronyx began with one simple vision—to make natural nutrition more accessible for everyone.
                                    </p>
                                </div>
                            </div>

                            <!-- Item 2: Mortar & Pestle / Formulation -->
                            <div class="story-timeline-item" role="listitem">
                                <div class="story-node-badge" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4.5 10c0 4.5 3.5 8 7.5 8s7.5-3.5 7.5-8H4.5z"></path>
                                        <path d="M9 18v2a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-2"></path>
                                        <path d="m16 4-3.5 6"></path>
                                        <path d="M15 3a1.5 1.5 0 0 1 2.12 2.12L16 6"></path>
                                    </svg>
                                </div>
                                <div class="story-node-content">
                                    <p class="story-node-text">
                                        Our journey started with ABC+, a thoughtfully crafted blend developed using carefully selected natural ingredients to support everyday wellness.
                                    </p>
                                </div>
                            </div>

                            <!-- Item 3: Shield / Quality & Trust -->
                            <div class="story-timeline-item" role="listitem">
                                <div class="story-node-badge" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                        <path d="m9 12 2 2 4-4"></path>
                                    </svg>
                                </div>
                                <div class="story-node-content">
                                    <p class="story-node-text">
                                        Rather than following trends, we focus on creating products people can trust through quality, transparency, and consistency.
                                    </p>
                                </div>
                            </div>

                            <!-- Item 4: Chart Arrow / Sustained Growth -->
                            <div class="story-timeline-item" role="listitem">
                                <div class="story-node-badge" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 3v18h18"></path>
                                        <path d="m19 9-5 5-4-4-3 3"></path>
                                        <path d="M14 9h5v5"></path>
                                    </svg>
                                </div>
                                <div class="story-node-content">
                                    <p class="story-node-text">
                                        As Nutronyx continues to grow, we remain committed to developing clean, innovative nutrition products that help people build healthier habits every day.
                                    </p>
                                </div>
                            </div>

                        </div>

                    </div>

                    <!-- ==============================================================
                         RIGHT COLUMN: LUXURY PHOTOGRAPHIC STAGE
                         ============================================================== -->
                    <div class="story-visual-col">
                        <div class="story-stage-wrapper">
                            
                            <!-- Layered Accent Ghost Frame -->
                            <div class="story-stage-ghost-frame" aria-hidden="true"></div>

                            <!-- Main Rounded Photo Frame -->
                            <div class="story-stage-photo-box">
                                <img src="assets/images/about-story-mortar.jpg" 
                                     alt="Stone mortar with fresh botanical herbs, whole almonds, and ceramic bowl of golden wellness powder on sunlit stone plinth" 
                                     class="story-stage-img" 
                                     loading="lazy">
                            </div>

                        </div>
                    </div>

                </div>
            </div>

        </section>

        <!-- ==========================================================================
             SECTION 03: OUR VISION
             "A More Healthy and Wellness-Focused India."
             ========================================================================== -->
        <section class="about-vision-section" id="vision" aria-label="Section 03: Our Vision">
            
            <!-- Atmospheric Top-Right Leaf Shadow Overlay -->
            <div class="vision-shadow-top-right" aria-hidden="true"></div>

            <!-- Flowing Bottom Delicate Wave Ribbons -->
            <div class="vision-bottom-waves" aria-hidden="true">
                <svg viewBox="0 0 1440 120" fill="none" preserveAspectRatio="none">
                    <path d="M0,75 C340,115 660,35 1000,75 C1220,100 1350,45 1440,65 L1440,120 L0,120 Z" fill="rgba(168, 185, 138, 0.32)"/>
                    <path d="M0,88 C260,40 520,110 840,60 C1120,20 1300,75 1440,50 L1440,120 L0,120 Z" fill="rgba(124, 157, 17, 0.2)"/>
                    <path d="M0,100 C300,65 620,115 960,85 C1180,65 1320,95 1440,90 L1440,120 L0,120 Z" fill="rgba(215, 207, 192, 0.28)"/>
                </svg>
            </div>

            <div class="vision-container">
                <div class="vision-editorial-grid">

                    <!-- ==============================================================
                         LEFT COLUMN: HEADER & ARCHITECTURAL ARCH PHOTO STAGE
                         ============================================================== -->
                    <div class="vision-left-col">
                        
                        <!-- Eyebrow Block -->
                        <div class="vision-header-block">
                            <div class="vision-sprout-icon" aria-hidden="true">
                                <svg width="24" height="20" viewBox="0 0 28 24" fill="none">
                                    <path d="M14 20C14 12 6 8 2 10C2 17 8 21 14 20Z" fill="#1E5736"/>
                                    <path d="M14 20C14 12 22 8 26 10C26 17 20 21 14 20Z" fill="#25533A"/>
                                    <path d="M14 24V19" stroke="#1E5736" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <div class="vision-eyebrow-wrapper">
                                <span class="vision-eyebrow-rule" aria-hidden="true"></span>
                                <span class="vision-eyebrow-text">OUR VISION</span>
                                <span class="vision-eyebrow-rule" aria-hidden="true"></span>
                            </div>

                            <!-- Main Editorial Headline with Flourish -->
                            <h2 class="vision-main-headline">
                                <span class="vision-hl-line">A More Healthy</span>
                                <span class="vision-hl-line">and Wellness-Focused</span>
                                <span class="vision-hl-line"><em class="vision-hl-italic">India.</em></span>
                            </h2>

                            <!-- Curved Green Flourish Under Headline -->
                            <div class="vision-headline-flourish" aria-hidden="true">
                                <svg width="140" height="12" viewBox="0 0 140 12" fill="none">
                                    <path d="M2 3C46 11 94 11 138 3" stroke="#1E5736" stroke-width="2.4" stroke-linecap="round"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Asymmetrical Sweeping Arch Landscape Photo Frame -->
                        <div class="vision-arch-frame">
                            <img src="assets/images/about-vision-landscape.jpg" 
                                 alt="Sunrise rising over lush green Western Ghats misty tea hills with foreground leaves catching dawn light" 
                                 class="vision-arch-img" 
                                 loading="lazy">
                        </div>

                    </div>

                    <!-- Center Subtle Vertical Hairline Divider -->
                    <div class="vision-center-divider" aria-hidden="true"></div>

                    <!-- ==============================================================
                         RIGHT COLUMN: 3 VISION PILLARS MATRIX
                         ============================================================== -->
                    <div class="vision-pillars-col" role="list">

                        <!-- Pillar 01: Trust & Brand Leadership -->
                        <div class="vision-pillar-item" role="listitem">
                            <div class="vision-pillar-badge" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                                    <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                                    <path d="M4 22h16"></path>
                                    <path d="M10 14.66V17c0 .55-.45 1-1 1H8v4h8v-4h-1c-.55 0-1-.45-1-1v-2.34"></path>
                                    <path d="M6 4h12v6a6 6 0 0 1-12 0V4z"></path>
                                    <polygon points="12 6 12.8 7.6 14.5 7.8 13.2 9 13.5 10.7 12 9.9 10.5 10.7 10.8 9 9.5 7.8 11.2 7.6 12 6"></polygon>
                                </svg>
                            </div>
                            <div class="vision-pillar-content">
                                <h3 class="vision-pillar-title">To become one of India’s most trusted wellness brands,</h3>
                                <p class="vision-pillar-desc">
                                    recognised for quality, transparency, and thoughtfully crafted nutrition.
                                </p>
                            </div>
                        </div>

                        <div class="vision-pillar-divider" aria-hidden="true"></div>

                        <!-- Pillar 02: Healthier Choices & High Standards -->
                        <div class="vision-pillar-item" role="listitem">
                            <div class="vision-pillar-badge" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"></path>
                                    <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"></path>
                                </svg>
                            </div>
                            <div class="vision-pillar-content">
                                <h3 class="vision-pillar-title">To help people make healthier choices</h3>
                                <p class="vision-pillar-desc">
                                    through products that combine carefully selected natural ingredients with uncompromising quality standards.
                                </p>
                            </div>
                        </div>

                        <div class="vision-pillar-divider" aria-hidden="true"></div>

                        <!-- Pillar 03: Enduring Enterprise & Generations to Come -->
                        <div class="vision-pillar-item" role="listitem">
                            <div class="vision-pillar-badge" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <div class="vision-pillar-content">
                                <h3 class="vision-pillar-title">To build a brand for the long run</h3>
                                <p class="vision-pillar-desc">
                                    that customers can rely on every day—creating wellness products that inspire confidence, consistency, and healthier lifestyles for generations to come.
                                </p>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

        </section>

        <!-- ==========================================================================
             SECTION 04: OUR COMMITMENT
             "Quality, Integrity and Long-Term Trust."
             ========================================================================== -->
        <section class="about-commitment-section" id="our-commitment" aria-label="Section 04: Our Commitment">
            
            <!-- Atmospheric Top-Right Leaf Shadow Overlay -->
            <div class="commit-shadow-top-right" aria-hidden="true"></div>

            <!-- Flowing Bottom Delicate Wave Ribbons -->
            <div class="commit-bottom-waves" aria-hidden="true">
                <svg viewBox="0 0 1440 120" fill="none" preserveAspectRatio="none">
                    <path d="M0,80 C320,110 640,40 980,80 C1200,105 1360,50 1440,70 L1440,120 L0,120 Z" fill="rgba(168, 185, 138, 0.3)"/>
                    <path d="M0,65 C280,100 540,45 860,85 C1140,115 1320,55 1440,75 L1440,120 L0,120 Z" fill="rgba(124, 157, 17, 0.18)"/>
                    <path d="M0,95 C360,60 700,105 1040,75 C1240,60 1360,90 1440,80 L1440,120 L0,120 Z" fill="rgba(215, 207, 192, 0.25)"/>
                </svg>
            </div>

            <div class="commit-container">
                <div class="commit-editorial-grid">

                    <!-- ==============================================================
                         LEFT COLUMN: HEADER & STILL LIFE ARCH PHOTO STAGE
                         ============================================================== -->
                    <div class="commit-left-col">
                        
                        <!-- Eyebrow Block -->
                        <div class="commit-header-block">
                            <div class="commit-sprout-icon" aria-hidden="true">
                                <svg width="24" height="20" viewBox="0 0 28 24" fill="none">
                                    <path d="M14 20C14 12 6 8 2 10C2 17 8 21 14 20Z" fill="#1E5736"/>
                                    <path d="M14 20C14 12 22 8 26 10C26 17 20 21 14 20Z" fill="#25533A"/>
                                    <path d="M14 24V19" stroke="#1E5736" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <div class="commit-eyebrow-wrapper">
                                <span class="commit-eyebrow-rule" aria-hidden="true"></span>
                                <span class="commit-eyebrow-text">OUR COMMITMENT</span>
                                <span class="commit-eyebrow-rule" aria-hidden="true"></span>
                            </div>

                            <!-- Main Editorial Headline with Flourish -->
                            <h2 class="commit-main-headline">
                                <span class="commit-hl-line">Quality, Integrity</span>
                                <span class="commit-hl-line">and Long-Term</span>
                                <span class="commit-hl-line"><em class="commit-hl-italic">Trust.</em></span>
                            </h2>

                            <!-- Curved Green Flourish Under Headline -->
                            <div class="commit-headline-flourish" aria-hidden="true">
                                <svg width="140" height="12" viewBox="0 0 140 12" fill="none">
                                    <path d="M2 3C46 11 94 11 138 3" stroke="#1E5736" stroke-width="2.4" stroke-linecap="round"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Asymmetrical Sweeping Arch Still Life Photo Frame -->
                        <div class="commit-arch-frame">
                            <img src="assets/images/about-commitment-still-life.jpg" 
                                 alt="Botanical wellness powder in ceramic bowl, red beetroot powder, and sliced beetroot on sunlit stone plinth" 
                                 class="commit-arch-img" 
                                 loading="lazy">
                        </div>

                    </div>

                    <!-- Center Timeline Rail with Node Indicators -->
                    <div class="commit-center-rail" aria-hidden="true">
                        <div class="commit-rail-line"></div>
                        <span class="commit-rail-dot dot-1"></span>
                        <span class="commit-rail-dot dot-2"></span>
                        <span class="commit-rail-dot dot-3"></span>
                        <span class="commit-rail-dot dot-4"></span>
                    </div>

                    <!-- ==============================================================
                         RIGHT COLUMN: 4 COMMITMENT PILLARS MATRIX
                         ============================================================== -->
                    <div class="commit-pillars-col" role="list">

                        <!-- Pillar 01: Uncompromising Quality -->
                        <div class="commit-pillar-item" role="listitem">
                            <div class="commit-pillar-badge" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="8" r="7"></circle>
                                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                                    <polygon points="12 4.5 13.09 6.7 15.5 7.06 13.75 8.76 14.16 11.16 12 10.02 9.84 11.16 10.25 8.76 8.5 7.06 10.91 6.7 12 4.5"></polygon>
                                </svg>
                            </div>
                            <div class="commit-pillar-content">
                                <h3 class="commit-pillar-title">Uncompromising Quality</h3>
                                <p class="commit-pillar-desc">
                                    From sourcing carefully selected ingredients to maintaining high standards in manufacturing and product safety, we deliver consistency in every detail.
                                </p>
                            </div>
                        </div>

                        <div class="commit-pillar-divider" aria-hidden="true"></div>

                        <!-- Pillar 02: Honest Transparency -->
                        <div class="commit-pillar-item" role="listitem">
                            <div class="commit-pillar-badge" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    <path d="m9 12 2 2 4-4"></path>
                                </svg>
                            </div>
                            <div class="commit-pillar-content">
                                <h3 class="commit-pillar-title">Honest Transparency</h3>
                                <p class="commit-pillar-desc">
                                    We are committed to providing clear and honest product information, because we believe transparency is just as important as quality.
                                </p>
                            </div>
                        </div>

                        <div class="commit-pillar-divider" aria-hidden="true"></div>

                        <!-- Pillar 03: Continuous Improvement -->
                        <div class="commit-pillar-item" role="listitem">
                            <div class="commit-pillar-badge" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10 2v7.31L4.62 19.38A2 2 0 0 0 6.35 22h11.3a2 2 0 0 0 1.73-2.62L14 9.31V2"></path>
                                    <line x1="8.5" y1="2" x2="15.5" y2="2"></line>
                                    <path d="M14 15.5c-1 0-2.5-.5-3.5-1.5 0 2 1.5 3.5 3.5 3.5s3.5-1.5 3.5-3.5c0-.8-.3-1.6-.8-2.2"></path>
                                </svg>
                            </div>
                            <div class="commit-pillar-content">
                                <h3 class="commit-pillar-title">Continuous Improvement</h3>
                                <p class="commit-pillar-desc">
                                    We constantly refine and improve our formulations to bring you better, more effective nutrition products.
                                </p>
                            </div>
                        </div>

                        <div class="commit-pillar-divider" aria-hidden="true"></div>

                        <!-- Pillar 04: You at the Heart -->
                        <div class="commit-pillar-item" role="listitem">
                            <div class="commit-pillar-badge" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <div class="commit-pillar-content">
                                <h3 class="commit-pillar-title">You at the Heart</h3>
                                <p class="commit-pillar-desc">
                                    Our promise is simple—to create nutrition products that you can confidently make part of your everyday wellness routine.
                                </p>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

        </section>

        <!-- ==========================================================================
             SECTION 05: JOIN OUR WELLNESS JOURNEY
             "Wellness Journey"
             ========================================================================== -->
        <section class="about-journey-section" id="wellness-journey" aria-label="Section 05: Join Our Wellness Journey">
            
            <!-- Atmospheric Top-Right Leaf Shadow Overlay -->
            <div class="journey-shadow-top-right" aria-hidden="true"></div>

            <!-- Flowing Bottom Delicate Wave Ribbons -->
            <div class="journey-bottom-waves" aria-hidden="true">
                <svg viewBox="0 0 1440 120" fill="none" preserveAspectRatio="none">
                    <path d="M0,70 C360,110 680,30 1020,70 C1240,95 1360,40 1440,60 L1440,120 L0,120 Z" fill="rgba(168, 185, 138, 0.35)"/>
                    <path d="M0,85 C280,45 540,105 860,65 C1140,25 1320,80 1440,55 L1440,120 L0,120 Z" fill="rgba(124, 157, 17, 0.22)"/>
                    <path d="M0,98 C320,70 640,110 980,80 C1200,60 1340,95 1440,85 L1440,120 L0,120 Z" fill="rgba(215, 207, 192, 0.3)"/>
                </svg>
            </div>

            <div class="journey-container">
                <div class="journey-editorial-grid">

                    <!-- ==============================================================
                         LEFT COLUMN: HEADER & 4-STEP NARRATIVE TIMELINE + CTA
                         ============================================================== -->
                    <div class="journey-left-col">
                        
                        <!-- Eyebrow Block -->
                        <div class="journey-header-block">
                            <div class="journey-sprout-icon" aria-hidden="true">
                                <svg width="24" height="20" viewBox="0 0 28 24" fill="none">
                                    <path d="M14 20C14 12 6 8 2 10C2 17 8 21 14 20Z" fill="#1E5736"/>
                                    <path d="M14 20C14 12 22 8 26 10C26 17 20 21 14 20Z" fill="#25533A"/>
                                    <path d="M14 24V19" stroke="#1E5736" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <div class="journey-eyebrow-wrapper">
                                <span class="journey-eyebrow-rule" aria-hidden="true"></span>
                                <span class="journey-eyebrow-text">JOIN OUR</span>
                                <span class="journey-eyebrow-rule" aria-hidden="true"></span>
                            </div>

                            <!-- Main Editorial Headline with Flourish -->
                            <h2 class="journey-main-headline">
                                <span class="journey-hl-text">Wellness <em class="journey-hl-italic">Journey</em></span>
                            </h2>

                            <!-- Curved Green Flourish Under Headline -->
                            <div class="journey-headline-flourish" aria-hidden="true">
                                <svg width="150" height="12" viewBox="0 0 150 12" fill="none">
                                    <path d="M2 3C50 11 100 11 148 3" stroke="#1E5736" stroke-width="2.4" stroke-linecap="round"/>
                                </svg>
                            </div>
                        </div>

                        <!-- 4-Step Narrative Timeline Rail -->
                        <div class="journey-timeline-track" role="list">
                            <div class="journey-timeline-rail" aria-hidden="true"></div>

                            <!-- Step 1: Gratitude / Welcome -->
                            <div class="journey-timeline-item" role="listitem">
                                <div class="journey-node-badge" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"></path>
                                        <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"></path>
                                    </svg>
                                </div>
                                <div class="journey-node-content">
                                    <p class="journey-node-text">
                                        Thank you for taking the time to learn about Nutronyx.
                                    </p>
                                </div>
                            </div>

                            <!-- Step 2: Inception & Quality Formulation -->
                            <div class="journey-timeline-item" role="listitem">
                                <div class="journey-node-badge" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4.5 10c0 4.5 3.5 8 7.5 8s7.5-3.5 7.5-8H4.5z"></path>
                                        <path d="M9 18v2a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-2"></path>
                                        <path d="m16 4-3.5 6"></path>
                                        <path d="M15 3a1.5 1.5 0 0 1 2.12 2.12L16 6"></path>
                                    </svg>
                                </div>
                                <div class="journey-node-content">
                                    <p class="journey-node-text">
                                        Every product we create begins with a simple belief—that better health starts with thoughtful daily choices. Our journey is driven by quality, transparency, and a genuine commitment to helping people build healthier habits through natural nutrition.
                                    </p>
                                </div>
                            </div>

                            <!-- Step 3: Heartfelt Honoured Partnership -->
                            <div class="journey-timeline-item" role="listitem">
                                <div class="journey-node-badge" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path>
                                    </svg>
                                </div>
                                <div class="journey-node-content">
                                    <p class="journey-node-text">
                                        Whether you’re beginning your wellness journey or continuing one you’ve already started, we’re honoured to be a part of it.
                                    </p>
                                </div>
                            </div>

                            <!-- Step 4: Community Habit Building -->
                            <div class="journey-timeline-item" role="listitem">
                                <div class="journey-node-badge" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                </div>
                                <div class="journey-node-content">
                                    <p class="journey-node-text">
                                        Together, let’s build healthier routines, make better choices, and embrace a more natural approach to everyday wellness.
                                    </p>
                                </div>
                            </div>

                        </div>

                        <!-- Solid Pill CTA Button -->
                        <div class="journey-cta-wrapper">
                            <a href="shop" class="journey-cta-btn" aria-label="Be a Part of Our Journey">
                                <span>Be a Part of Our Journey</span>
                                <span class="journey-cta-arrow" aria-hidden="true">&rarr;</span>
                            </a>
                        </div>

                    </div>

                    <!-- ==============================================================
                         RIGHT COLUMN: PANORAMIC LIFESTYLE PHOTOGRAPHIC STAGE
                         ============================================================== -->
                    <div class="journey-right-col">
                        <div class="journey-photo-frame">
                            <img src="assets/images/about-journey-lifestyle.jpg" 
                                 alt="Serene woman meditating peacefully in morning sunlight on scenic tea plantation hill with glass bottle of green wellness juice" 
                                 class="journey-photo-img" 
                                 loading="lazy">
                        </div>
                    </div>

                </div>
            </div>

        </section>
    </main>

    <!-- Shared Signature Site Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- GSAP 3 & ScrollTrigger CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>

    <!-- Scoped About Page Script -->
    <script src="assets/js/about.js?v=20261117_v2"></script>

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
