<?php
/**
 * NUTRONYX — Return & Refund Policy
 * Transparency, Quality Guarantees & Fair Customer Care
 */

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$currentPage = 'refund';
$pageTitle = 'Return & Refund Policy — Guarantees & Care | NUTRONYX';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="Learn about our return, replacement, refund, and cancellation policies to ensure a smooth, worry-free shopping experience with Nutronyx.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Global Base & Component Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261117_v2">

    <style>
        /* Dedicated Policy Page Styles (Matching FAQ & Privacy Experience) */
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
            max-width: 680px;
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
            margin: 12px 0 16px 0;
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
            max-width: 640px;
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
            max-width: 640px;
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
                <h1 class="policy-hero-title">Return &amp; <span>Refund Policy</span></h1>
                <p class="policy-hero-subtext">
                    Learn about our return, replacement, refund, and cancellation policies to ensure a smooth shopping experience with Nutronyx.
                </p>
            </div>
        </header>

        <!-- ==========================================================================
             EDITORIAL RETURN & REFUND POLICY EXPERIENCE (From Reference Images 1-6)
             ========================================================================== -->
        <section class="policy-page-main" id="refund-content" aria-label="Nutronyx Return and Refund Policy">
            <div class="policy-container">
                <div class="policy-editorial-grid">
                    
                    <!-- Left Sticky Editorial Column -->
                    <aside class="policy-sticky-intro">
                        <div class="faq-intro-wrap">
                            <span class="faq-eyebrow">POLICIES &amp; RESOLUTIONS</span>
                            <h2 class="faq-headline">
                                <span class="faq-headline-line">Clarity, Care &amp;</span>
                                <span class="faq-headline-line faq-headline-accent">Fair Resolutions.</span>
                            </h2>
                            <p class="faq-intro-subtext">
                                Every Nutronyx order is prepared with care. If something isn't right, we'll work with you to find a fair and timely resolution.
                            </p>

                            <!-- Table of Contents Quick Nav -->
                            <nav class="policy-toc-nav" aria-label="Return and Refund Policy Sections">
                                <h3 class="policy-toc-heading">SECTIONS</h3>
                                <ul class="policy-toc-list" role="list">
                                    <li><a href="#section-01" class="policy-toc-link"><span class="policy-toc-num">01</span> Returns</a></li>
                                    <li><a href="#section-02" class="policy-toc-link"><span class="policy-toc-num">02</span> Damaged / Incorrect</a></li>
                                    <li><a href="#section-03" class="policy-toc-link"><span class="policy-toc-num">03</span> Replacement or Refund</a></li>
                                    <li><a href="#section-04" class="policy-toc-link"><span class="policy-toc-num">04</span> Refunds</a></li>
                                    <li><a href="#section-05" class="policy-toc-link"><span class="policy-toc-num">05</span> Order Cancellation</a></li>
                                </ul>
                            </nav>

                            <!-- Concierge Assistance Card -->
                            <div class="faq-concierge-card" style="margin-top: 24px;">
                                <div class="concierge-icon-wrap" aria-hidden="true">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="23 4 23 10 17 10"></polyline>
                                        <polyline points="1 20 1 14 7 14"></polyline>
                                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                                    </svg>
                                </div>
                                <div class="concierge-content">
                                    <h3 class="concierge-title">Need help with an order?</h3>
                                    <p class="concierge-text">Contact our concierge team with your Order ID for prompt assistance.</p>
                                    <a href="mailto:support@nutronyx.in" class="concierge-link">
                                        <span>Email support@nutronyx.in</span>
                                        <span class="concierge-arrow" aria-hidden="true">↗</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- Right Column: Policy Content Sections 01 to 05 -->
                    <div class="policy-content-col" role="region" aria-label="Return and Refund Policy Articles">
                        <div class="policy-items-list">

                            <!-- Section 01: Returns -->
                            <article class="policy-card" id="section-01">
                                <div class="policy-num" aria-hidden="true">01</div>
                                <div class="policy-body">
                                    <h2 class="policy-title">Returns</h2>
                                    <p class="policy-text">
                                        For hygiene and safety reasons, food products cannot be returned once they have been delivered.
                                    </p>
                                    <p class="policy-text" style="margin-top: 10px; font-weight: 500;">
                                        We do not accept returns for:
                                    </p>
                                    <ul class="policy-bullet-list" role="list">
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Opened or used products</span>
                                        </li>
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Change of mind after delivery</span>
                                        </li>
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Incorrect orders placed by the customer</span>
                                        </li>
                                    </ul>
                                </div>
                            </article>

                            <!-- Section 02: Damaged, Defective, or Incorrect Product -->
                            <article class="policy-card" id="section-02">
                                <div class="policy-num" aria-hidden="true">02</div>
                                <div class="policy-body">
                                    <h2 class="policy-title">Damaged, Defective, or Incorrect Product</h2>
                                    <p class="policy-text">
                                        If you receive a product that is:
                                    </p>
                                    <ul class="policy-bullet-list" role="list">
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Damaged during transit</span>
                                        </li>
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Defective</span>
                                        </li>
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Different from the product you ordered</span>
                                        </li>
                                    </ul>
                                    <p class="policy-text">
                                        Please contact us within 48 hours of receiving your order.
                                    </p>
                                    <p class="policy-text" style="margin-top: 14px; font-weight: 500;">
                                        To help us review your request, please provide:
                                    </p>
                                    <ul class="policy-bullet-list" role="list">
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Your Order ID</span>
                                        </li>
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Clear photos of the product and its packaging</span>
                                        </li>
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>A brief description of the issue</span>
                                        </li>
                                    </ul>
                                    <p class="policy-text" style="margin-top: 12px; color: #7A8C80; font-size: 14.5px;">
                                        Requests made after 48 hours of delivery may not be eligible for replacement or refund.
                                    </p>
                                </div>
                            </article>

                            <!-- Section 03: Replacement or Refund -->
                            <article class="policy-card" id="section-03">
                                <div class="policy-num" aria-hidden="true">03</div>
                                <div class="policy-body">
                                    <h2 class="policy-title">Replacement or Refund</h2>
                                    <p class="policy-text">
                                        Once your request has been reviewed and approved, Nutronyx will, at its discretion:
                                    </p>
                                    <ul class="policy-bullet-list" role="list">
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Provide a replacement product, or</span>
                                        </li>
                                        <li class="policy-bullet-item">
                                            <span class="policy-bullet-dot" aria-hidden="true"></span>
                                            <span>Issue a refund to your original payment method if a replacement is not possible.</span>
                                        </li>
                                    </ul>
                                </div>
                            </article>

                            <!-- Section 04: Refunds -->
                            <article class="policy-card" id="section-04">
                                <div class="policy-num" aria-hidden="true">04</div>
                                <div class="policy-body">
                                    <h2 class="policy-title">Refunds</h2>
                                    <p class="policy-text">
                                        If your refund request is approved, the refund will be processed to your original payment method within 5–7 business days.
                                    </p>
                                    <p class="policy-text">
                                        The time taken for the amount to reflect in your account may vary depending on your bank or payment provider.
                                    </p>
                                </div>
                            </article>

                            <!-- Section 05: Order Cancellation -->
                            <article class="policy-card" id="section-05">
                                <div class="policy-num" aria-hidden="true">05</div>
                                <div class="policy-body">
                                    <h2 class="policy-title">Order Cancellation</h2>
                                    <p class="policy-text">
                                        Orders may be cancelled only before they are shipped.
                                    </p>
                                    <p class="policy-text">
                                        Once an order has been dispatched, it cannot be cancelled.
                                    </p>
                                </div>
                            </article>

                        </div>

                        <!-- ==================================================================
                             CUSTOMER SUPPORT CARD (From Reference Image 6)
                             ================================================================== -->
                        <section class="policy-support-section" aria-label="Customer Support Contact">
                            <span class="policy-support-eyebrow">CUSTOMER SUPPORT</span>
                            <h2 class="policy-support-title">Need help with a return or refund?</h2>
                            <p class="policy-support-desc">
                                If you have any questions regarding returns, refunds, replacements, or order cancellations, our support team is here to assist you.
                            </p>
                            <a href="mailto:support@nutronyx.in" class="policy-support-email-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                <span>support@nutronyx.in</span>
                            </a>
                            <p class="policy-support-note">
                                Every Nutronyx order is prepared with care. If something isn't right, we'll work with you to find a fair and timely resolution.
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
                    <a href="shop.php" class="btn-policy-shop">
                        <span>Shop ABC+ Ritual</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                    <a href="contact.php" class="btn-policy-contact">
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
    <script src="assets/js/main.js?v=20261117_v2"></script>

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
