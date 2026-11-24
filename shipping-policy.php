<?php
/**
 * NUTRONYX — Shipping Policy
 * Careful Packing, Reliable Delivery & Transparent Fulfillment
 */

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$currentPage = 'shipping';
$pageTitle = 'Shipping Policy — Delivery, Care & Fulfillment | NUTRONYX';
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

    <meta name="description" content="Learn how we process, pack and deliver every Nutronyx order with care, transparency and reliability across India.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Global Base & Component Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261124_v1">

    <style>
        /* Dedicated Policy Page Styles (Matching FAQ Experience) */
        .policy-page-hero {
            position: relative;
            background: linear-gradient(180deg, #F5F1E8 0%, #FAF8F5 100%);
            padding: 150px 5vw 55px 5vw;
            text-align: center;
            overflow: hidden;
            border-bottom: 1px solid rgba(229, 223, 213, 0.7);
        }

        .policy-hero-container {
            max-width: 860px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .policy-hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-sans);
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: #1E5736;
            margin-bottom: 16px;
            background: rgba(30, 87, 54, 0.08);
            padding: 6px 18px;
            border-radius: 999px;
        }

        .policy-hero-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(38px, 4.8vw, 62px);
            font-weight: 600;
            color: #122B1E;
            line-height: 1.12;
            letter-spacing: -0.02em;
            margin: 0 0 16px 0;
        }

        .policy-hero-title span {
            font-style: italic;
            color: #1E5736;
            font-weight: 500;
        }

        .policy-hero-subtext {
            font-family: var(--font-sans);
            font-size: clamp(15px, 1.3vw, 17.5px);
            line-height: 1.65;
            color: #586B5F;
            max-width: 640px;
            margin: 0 auto;
        }

        /* Main Content Section */
        .policy-page-main {
            padding: 80px 5vw 120px 5vw;
            background: #FAF8F5;
        }

        .policy-container {
            max-width: 1320px;
            margin: 0 auto;
            width: 100%;
        }

        .policy-editorial-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 84px;
            align-items: flex-start;
        }

        @media (max-width: 1024px) {
            .policy-editorial-grid {
                grid-template-columns: 1fr;
                gap: 50px;
            }
        }

        /* Sticky Left Sidebar */
        .policy-sticky-intro {
            position: sticky;
            top: 120px;
            z-index: 2;
        }

        .policy-toc-nav {
            margin-top: 28px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(18, 51, 33, 0.08);
            border-radius: 20px;
            padding: 22px 24px;
            box-shadow: 0 10px 30px rgba(18, 51, 33, 0.03);
        }

        .policy-toc-heading {
            font-family: var(--font-sans);
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #1E5736;
            margin: 0 0 14px 0;
        }

        .policy-toc-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .policy-toc-link {
            font-family: var(--font-sans);
            font-size: 13.5px;
            color: #4A5E52;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            line-height: 1.4;
        }

        .policy-toc-num {
            font-family: var(--font-serif);
            font-size: 13px;
            font-weight: 600;
            color: #9C8C74;
            min-width: 20px;
        }

        .policy-toc-link:hover {
            color: #1E5736;
            transform: translateX(4px);
        }

        .policy-toc-link.is-active {
            color: #1E5736;
            font-weight: 600;
        }

        /* Policy Items List (Styled identically to FAQ) */
        .policy-items-list {
            border-top: 1px solid rgba(18, 51, 33, 0.12);
        }

        .policy-card {
            display: grid;
            grid-template-columns: 56px 1fr;
            gap: 24px;
            align-items: flex-start;
            padding: 40px 0;
            border-bottom: 1px solid rgba(18, 51, 33, 0.12);
            scroll-margin-top: 130px;
            transition: all 0.3s ease;
        }

        @media (max-width: 640px) {
            .policy-card {
                grid-template-columns: 44px 1fr;
                gap: 16px;
                padding: 30px 0;
            }
        }

        .policy-num {
            font-family: var(--font-serif);
            font-size: clamp(28px, 2.4vw, 36px);
            font-weight: 400;
            color: #9C8C74;
            line-height: 1.25;
            user-select: none;
            padding-top: 2px;
        }

        .policy-body {
            display: flex;
            flex-direction: column;
        }

        .policy-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(22px, 2vw, 28px);
            font-weight: 600;
            color: #123321;
            line-height: 1.3;
            letter-spacing: -0.01em;
            margin: 0 0 16px 0;
        }

        .policy-text {
            font-family: var(--font-sans);
            font-size: 16px;
            line-height: 1.7;
            color: #4A5E52;
            margin: 0 0 14px 0;
        }

        .policy-text:last-child {
            margin-bottom: 0;
        }

        /* Custom Bullet List */
        .policy-bullet-list {
            list-style: none;
            padding: 0;
            margin: 14px 0 14px 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .policy-bullet-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-family: var(--font-sans);
            font-size: 15.5px;
            line-height: 1.6;
            color: #4A5E52;
        }

        .policy-bullet-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #1E5736;
            margin-top: 9px;
            flex-shrink: 0;
        }

        /* Dedicated Support Box (From User Reference Image 6) */
        .policy-support-section {
            margin-top: 70px;
            background: linear-gradient(135deg, rgba(245, 241, 232, 0.8) 0%, rgba(250, 248, 245, 0.95) 100%);
            border: 1px solid rgba(18, 51, 33, 0.1);
            border-radius: 24px;
            padding: 56px 40px;
            text-align: center;
            box-shadow: 0 12px 36px rgba(18, 51, 33, 0.04);
            position: relative;
            overflow: hidden;
        }

        .policy-support-eyebrow {
            font-family: var(--font-sans);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: #9C8C74;
            margin-bottom: 14px;
            display: inline-block;
        }

        .policy-support-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(30px, 3.2vw, 42px);
            font-weight: 600;
            color: #123321;
            line-height: 1.2;
            letter-spacing: -0.015em;
            margin: 0 0 16px 0;
        }

        .policy-support-desc {
            font-family: var(--font-sans);
            font-size: 16px;
            line-height: 1.65;
            color: #586B5F;
            max-width: 620px;
            margin: 0 auto 28px auto;
        }

        .policy-support-email-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #1E5736;
            color: #FAF6ED;
            padding: 14px 34px;
            border-radius: 999px;
            font-family: var(--font-sans);
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            letter-spacing: 0.02em;
            box-shadow: 0 6px 20px rgba(30, 87, 54, 0.22);
            transition: all 0.25s ease;
        }

        .policy-support-email-btn:hover {
            background: #123321;
            transform: translateY(-2px);
            box-shadow: 0 10px 26px rgba(18, 51, 33, 0.3);
            color: #FFFFFF;
        }

        .policy-support-note {
            font-family: var(--font-sans);
            font-size: 14px;
            line-height: 1.6;
            color: #7A8C80;
            max-width: 620px;
            margin: 32px auto 0 auto;
            border-top: 1px solid rgba(18, 51, 33, 0.08);
            padding-top: 24px;
        }

        /* Bottom Assistance Stage */
        .policy-bottom-cta {
            background: #0D2F1F;
            color: #FAF6ED;
            padding: 70px 5vw;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .policy-bottom-cta-inner {
            max-width: 680px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .policy-bottom-cta h3 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(28px, 3.5vw, 42px);
            font-weight: 600;
            margin: 0 0 14px 0;
            color: #FFFFFF;
        }

        .policy-bottom-cta p {
            font-size: 15.5px;
            line-height: 1.6;
            color: rgba(250, 246, 237, 0.85);
            margin: 0 0 28px 0;
        }

        .policy-cta-btn-group {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn-policy-shop {
            background: #C4A45A;
            color: #122B1E;
            padding: 14px 32px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s ease;
        }

        .btn-policy-shop:hover {
            background: #D8BA70;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(196, 164, 90, 0.35);
        }

        .btn-policy-contact {
            background: transparent;
            color: #FAF6ED;
            border: 1.5px solid rgba(250, 246, 237, 0.4);
            padding: 13px 28px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s ease;
        }

        .btn-policy-contact:hover {
            border-color: #FAF6ED;
            background: rgba(250, 246, 237, 0.1);
        }
    </style>
</head>
<body class="nutronyx-body policy-page-body">

    <!-- Header Navigation -->
    <?php include __DIR__ . "/includes/header.php"; ?>

    <main id="main-content">
        <!-- ==========================================================================
             POLICIES HERO HEADER (From Reference Image 1)
             ========================================================================== -->
        <header class="policy-page-hero">
            <div class="policy-hero-container">
                <span class="policy-hero-eyebrow">
                    POLICIES
                </span>
                <h1 class="policy-hero-title">Shipping <span>Policy</span></h1>
                <p class="policy-hero-subtext">
                    Learn how we process, pack and deliver every Nutronyx order with care, transparency and reliability.
                </p>
            </div>
        </header>

        <!-- ==========================================================================
             EDITORIAL SHIPPING POLICY EXPERIENCE (From Reference Images 1-6)
             ========================================================================== -->
        <section class="policy-page-main" id="shipping-content" aria-label="Nutronyx Shipping Policy">
            <div class="policy-container">
                <div class="policy-editorial-grid">
                    
                    <!-- Left Sticky Editorial Column -->
                    <aside class="policy-sticky-intro">
                        <div class="faq-intro-wrap">
                            <span class="faq-eyebrow">POLICIES &amp; FULFILLMENT</span>
                            <h2 class="faq-headline">
                                <span class="faq-headline-line">Careful Packing,</span>
                                <span class="faq-headline-line faq-headline-accent">Reliable Delivery.</span>
                            </h2>
                            <p class="faq-intro-subtext">
                                Every order is packed with care and delivered with the same commitment to quality that goes into every Nutronyx product.
                            </p>

                            <!-- Table of Contents Quick Nav -->
                            <nav class="policy-toc-nav" aria-label="Shipping Policy Sections">
                                <h3 class="policy-toc-heading">SECTIONS</h3>
                                <ul class="policy-toc-list" role="list">
                                    <li><a href="#section-01" class="policy-toc-link"><span class="policy-toc-num">01</span> Order Processing</a></li>
                                    <li><a href="#section-02" class="policy-toc-link"><span class="policy-toc-num">02</span> Shipping Time</a></li>
                                    <li><a href="#section-03" class="policy-toc-link"><span class="policy-toc-num">03</span> Shipping Charges</a></li>
                                    <li><a href="#section-04" class="policy-toc-link"><span class="policy-toc-num">04</span> Order Tracking</a></li>
                                    <li><a href="#section-05" class="policy-toc-link"><span class="policy-toc-num">05</span> Delivery Delays</a></li>
                                    <li><a href="#section-06" class="policy-toc-link"><span class="policy-toc-num">06</span> Incorrect Information</a></li>
                                </ul>
                            </nav>

                            <!-- Track Order Quick Badge -->
                            <div class="faq-concierge-card" style="margin-top: 24px;">
                                <div class="concierge-icon-wrap" aria-hidden="true">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="1" y="3" width="15" height="13"></rect>
                                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                    </svg>
                                </div>
                                <div class="concierge-content">
                                    <h3 class="concierge-title">Track an Existing Order</h3>
                                    <p class="concierge-text">Check your live dispatch and courier tracking status anytime.</p>
                                    <a href="track-order" class="concierge-link">
                                        <span>Go to Order Tracker</span>
                                        <span class="concierge-arrow" aria-hidden="true">↗</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- Right Column: Policy Content Sections 01 to 06 -->
                    <div class="policy-content-col" role="region" aria-label="Shipping Policy Articles">
                        <div class="policy-items-list">

                            <!-- Section 01: Order Processing -->
                            <article class="policy-card" id="section-01">
                                <div class="policy-num" aria-hidden="true">01</div>
                                <div class="policy-body">
                                    <h2 class="policy-title">Order Processing</h2>
                                    <p class="policy-text">
                                        Orders are processed within 1–2 business days after successful payment confirmation.
                                    </p>
                                    <p class="policy-text">
                                        Orders placed on Sundays or public holidays will be processed on the next business day.
                                    </p>
                                </div>
                            </article>

                            <!-- Section 02: Shipping Time -->
                            <article class="policy-card" id="section-02">
                                <div class="policy-num" aria-hidden="true">02</div>
                                <div class="policy-body">
                                    <h2 class="policy-title">Shipping Time</h2>
                                    <p class="policy-text">
                                        Standard delivery typically takes 3–7 business days depending on your location.
                                    </p>
                                    <p class="policy-text">
                                        Delivery timelines may vary for remote or rural areas.
                                    </p>
                                </div>
                            </article>

                            <!-- Section 03: Shipping Charges -->
                            <article class="policy-card" id="section-03">
                                <div class="policy-num" aria-hidden="true">03</div>
                                <div class="policy-body">
                                    <h2 class="policy-title">Shipping Charges</h2>
                                    <p class="policy-text">
                                        Shipping charges, if applicable, will be displayed during checkout before you complete your purchase.
                                    </p>
                                    <p class="policy-text">
                                        Any free shipping offers will be clearly mentioned on the website.
                                    </p>
                                </div>
                            </article>

                            <!-- Section 04: Order Tracking -->
                            <article class="policy-card" id="section-04">
                                <div class="policy-num" aria-hidden="true">04</div>
                                <div class="policy-body">
                                    <h2 class="policy-title">Order Tracking</h2>
                                    <p class="policy-text">
                                        Once your order has been shipped, you will receive a confirmation via email and/or SMS/WhatsApp (where available) with your tracking details.
                                    </p>
                                </div>
                            </article>

                            <!-- Section 05: Delivery Delays -->
                            <article class="policy-card" id="section-05">
                                <div class="policy-num" aria-hidden="true">05</div>
                                <div class="policy-body">
                                    <h2 class="policy-title">Delivery Delays</h2>
                                    <p class="policy-text">
                                        While we strive to deliver your order within the estimated timeframe, delays may occur due to:
                                    </p>
                                    <ul class="policy-bullet-list" role="list">
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Weather conditions</span>
                                        </li>
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Public holidays</span>
                                        </li>
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Courier disruptions</span>
                                        </li>
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Other unforeseen circumstances</span>
                                        </li>
                                    </ul>
                                    <p class="policy-text">
                                        We appreciate your patience and understanding.
                                    </p>
                                </div>
                            </article>

                            <!-- Section 06: Incorrect Shipping Information -->
                            <article class="policy-card" id="section-06">
                                <div class="policy-num" aria-hidden="true">06</div>
                                <div class="policy-body">
                                    <h2 class="policy-title">Incorrect Shipping Information</h2>
                                    <p class="policy-text">
                                        Please ensure that your shipping address and contact details are accurate.
                                    </p>
                                    <p class="policy-text">
                                        Nutronyx is not responsible for delays or failed deliveries caused by incorrect or incomplete information provided by the customer.
                                    </p>
                                </div>
                            </article>

                        </div>

                        <!-- ==================================================================
                             ORDER SUPPORT CARD (From Reference Image 6)
                             ================================================================== -->
                        <section class="policy-support-section" aria-label="Shipping Support Contact">
                            <span class="policy-support-eyebrow">SUPPORT</span>
                            <h2 class="policy-support-title">Need help with your order?</h2>
                            <p class="policy-support-desc">
                                If you have any questions regarding your shipment or delivery, our team will be happy to assist you.
                            </p>
                            <a href="mailto:support@nutronyx.in" class="policy-support-email-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                <span>support@nutronyx.in</span>
                            </a>
                            <p class="policy-support-note">
                                Every order is packed with care and delivered with the same commitment to quality that goes into every Nutronyx product.
                            </p>
                        </section>

                    </div>

                </div>
            </div>
        </section>

        <!-- ==========================================================================
             BOTTOM ASSISTANCE STRIP (Matching FAQ & Privacy Page)
             ========================================================================== -->
        <section class="policy-bottom-cta">
            <div class="policy-bottom-cta-inner">
                <h3>Ready to Begin Your Daily Ritual?</h3>
                <p>Discover clean, sustained plant vitality with NUTRONYX ABC+. Pure 100% whole foods designed for lifelong health.</p>
                <div class="policy-cta-btn-group">
                    <a href="shop" class="btn-policy-shop">
                        <span>Shop ABC+ Ritual</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                    <a href="contact" class="btn-policy-contact">
                        <span>Talk to Concierge</span>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <?php include __DIR__ . "/includes/footer.php"; ?>

    <!-- Scripts: GSAP & Smooth Scrolling -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="assets/js/main.js?v=20261124_v1"></script>

    <script>
        // Smooth scroll & active state tracking for TOC links
        document.addEventListener("DOMContentLoaded", () => {
            const tocLinks = document.querySelectorAll('.policy-toc-link');
            const sections = document.querySelectorAll('.policy-card');

            tocLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    const targetId = link.getAttribute('href');
                    if (targetId && targetId.startsWith('#')) {
                        const target = document.querySelector(targetId);
                        if (target) {
                            e.preventDefault();
                            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }
                });
            });

            // IntersectionObserver for active TOC highlighting
            if ('IntersectionObserver' in window && sections.length > 0) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const id = entry.target.getAttribute('id');
                            tocLinks.forEach(l => {
                                if (l.getAttribute('href') === '#' + id) {
                                    l.classList.add('is-active');
                                } else {
                                    l.classList.remove('is-active');
                                }
                            });
                        }
                    });
                }, {
                    rootMargin: '-20% 0px -70% 0px'
                });

                sections.forEach(s => observer.observe(s));
            }
        });
    </script>
</body>
</html>
