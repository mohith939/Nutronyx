<?php
/**
 * NUTRONYX — Frequently Asked Questions & Knowledge Experience
 * Curiosity Meets Clarity — Comprehensive Botanical Guidance
 */

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$currentPage = 'faq';
$pageTitle = 'Frequently Asked Questions — Knowledge & Clarity | NUTRONYX';
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

    <meta name="description" content="Explore frequently asked questions about NUTRONYX ABC+, whole-plant natural sourcing, daily wellness preparation, purity guarantees, and order fulfillment.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Global Base & Component Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261124_v3">

    <style>
        /* Dedicated FAQ Page Styles */
        .faq-page-hero {
            position: relative;
            background: linear-gradient(180deg, #F5F1E8 0%, #FAF8F5 100%);
            padding: 150px 5vw 55px 5vw;
            text-align: center;
            overflow: hidden;
            border-bottom: 1px solid rgba(229, 223, 213, 0.7);
        }

        .faq-hero-container {
            max-width: 860px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .faq-hero-eyebrow {
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
            padding: 6px 16px;
            border-radius: 999px;
        }

        .faq-hero-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(38px, 4.8vw, 62px);
            font-weight: 600;
            color: #122B1E;
            line-height: 1.12;
            letter-spacing: -0.02em;
            margin: 0;
        }

        .faq-hero-title span {
            font-style: italic;
            color: #1E5736;
            font-weight: 500;
        }

        /* Main FAQ Section on dedicated page */
        .faq-page-main {
            padding: 80px 5vw 120px 5vw;
            background: #FAF8F5;
        }

        /* Bottom Assistance Stage */
        .faq-bottom-cta {
            background: #0D2F1F;
            color: #FAF6ED;
            padding: 70px 5vw;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .faq-bottom-cta-inner {
            max-width: 680px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .faq-bottom-cta h3 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(28px, 3.5vw, 42px);
            font-weight: 600;
            margin: 0 0 14px 0;
            color: #FFFFFF;
        }

        .faq-bottom-cta p {
            font-size: 15.5px;
            line-height: 1.6;
            color: rgba(250, 246, 237, 0.85);
            margin: 0 0 28px 0;
        }

        .faq-cta-btn-group {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn-faq-shop {
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

        .btn-faq-shop:hover {
            background: #D8BA70;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(196, 164, 90, 0.35);
        }

        .btn-faq-contact {
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

        .btn-faq-contact:hover {
            border-color: #FAF6ED;
            background: rgba(250, 246, 237, 0.1);
        }
    </style>
</head>
<body class="nutronyx-body faq-page-body">

    <!-- Header Navigation -->
    <?php include __DIR__ . "/includes/header.php"; ?>

    <main id="main-content">
        <!-- ==========================================================================
             FAQ HERO HEADER
             ========================================================================== -->
        <header class="faq-page-hero">
            <div class="faq-hero-container">
                <span class="faq-hero-eyebrow">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    Knowledge &amp; Clarity
                </span>
                <h1 class="faq-hero-title">Frequently Asked <span>Questions</span></h1>
            </div>
        </header>

        <!-- ==========================================================================
             FAQ ACCORDION & CONCIERGE EXPERIENCE
             ========================================================================== -->
        <section class="section-faq faq-page-main" id="nutronyx-faq" aria-label="The NUTRONYX Knowledge Experience">
            <div class="faq-container">
                <div class="faq-editorial-grid">
                    <!-- Left Sticky Editorial Column -->
                    <aside class="faq-sticky-intro">
                        <div class="faq-intro-wrap">
                            <span class="faq-eyebrow">YOUR WELLNESS QUESTIONS, ANSWERED</span>
                            <h2 class="faq-headline">
                                <span class="faq-headline-line">Curiosity</span>
                                <span class="faq-headline-line faq-headline-accent">Meets Clarity.</span>
                            </h2>
                            <p class="faq-intro-subtext">Find answers to common questions about our products, natural ingredients, and everyday wellness.</p>

                            <!-- Editorial Concierge Card -->
                            <div class="faq-concierge-card">
                                <div class="concierge-icon-wrap" aria-hidden="true">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                </div>
                                <div class="concierge-content">
                                    <h3 class="concierge-title">Have More Questions?</h3>
                                    <p class="concierge-text">Our team is here to help with product information, orders, and general enquiries.</p>
                                    <a href="contact" class="concierge-link" style="margin-bottom: 8px;">
                                        <span>Contact NUTRONYX Support</span>
                                        <span class="concierge-arrow" aria-hidden="true">↗</span>
                                    </a>
                                    <div style="font-size: 12px; color: #6B7280; margin-top: 6px;">
                                        Email: <a href="mailto:support@nutronyx.in" style="color: #144D29; text-decoration: underline;">support@nutronyx.in</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- Right Dominant Editorial FAQ Column -->
                    <div class="faq-accordion-col" role="region" aria-label="Frequently Asked Questions Accordion">
                        <div class="faq-items-list" id="faqAccordionList">
                            
                            <!-- Item 01 -->
                            <div class="faq-item" data-faq="1" data-category="ingredients">
                                <div class="faq-item-num" aria-hidden="true">01</div>
                                <div class="faq-item-body">
                                    <button type="button" class="faq-trigger" id="faq-btn-1" aria-expanded="false" aria-controls="faq-ans-1">
                                        <span class="faq-question">What is NUTRONYX ABC+?</span>
                                        <span class="faq-indicator" aria-hidden="true">
                                            <svg class="icon-plus" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                        </span>
                                    </button>
                                    <div class="faq-collapse" id="faq-ans-1" role="region" aria-labelledby="faq-btn-1">
                                        <div class="faq-collapse-inner">
                                            <p class="faq-answer-text">
                                                NUTRONYX ABC+ is a natural wellness powder made from five carefully selected ingredients: Amla, Beetroot, Carrot, Ginger, and Lemon. Designed to complement everyday nutrition, it offers a convenient way to incorporate plant-based ingredients into your daily routine.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Five natural ingredients • Everyday nutrition</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Item 02 -->
                            <div class="faq-item" data-faq="2" data-category="ingredients">
                                <div class="faq-item-num" aria-hidden="true">02</div>
                                <div class="faq-item-body">
                                    <button type="button" class="faq-trigger" id="faq-btn-2" aria-expanded="false" aria-controls="faq-ans-2">
                                        <span class="faq-question">What ingredients are included in ABC+?</span>
                                        <span class="faq-indicator" aria-hidden="true">
                                            <svg class="icon-plus" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                        </span>
                                    </button>
                                    <div class="faq-collapse" id="faq-ans-2" role="region" aria-labelledby="faq-btn-2">
                                        <div class="faq-collapse-inner">
                                            <p class="faq-answer-text">
                                                ABC+ contains five ingredients: Amla (Indian Gooseberry), Carrot, Beetroot, Lemon, and Ginger. Each ingredient is selected for its natural nutritional properties and contribution to the overall blend.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Amla • Carrot • Beetroot • Lemon • Ginger</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Item 03 -->
                            <div class="faq-item" data-faq="3" data-category="ingredients">
                                <div class="faq-item-num" aria-hidden="true">03</div>
                                <div class="faq-item-body">
                                    <button type="button" class="faq-trigger" id="faq-btn-3" aria-expanded="false" aria-controls="faq-ans-3">
                                        <span class="faq-question">What makes ABC+ different from ordinary supplements?</span>
                                        <span class="faq-indicator" aria-hidden="true">
                                            <svg class="icon-plus" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                        </span>
                                    </button>
                                    <div class="faq-collapse" id="faq-ans-3" role="region" aria-labelledby="faq-btn-3">
                                        <div class="faq-collapse-inner">
                                            <p class="faq-answer-text">
                                                ABC+ focuses on a carefully balanced combination of plant-based ingredients rather than relying on a single isolated nutrient. Its convenient powder format makes it easy to include natural nutrition in your everyday routine.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Thoughtful formulation • Plant-based ingredients</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Item 04 -->
                            <div class="faq-item" data-faq="4" data-category="ritual">
                                <div class="faq-item-num" aria-hidden="true">04</div>
                                <div class="faq-item-body">
                                    <button type="button" class="faq-trigger" id="faq-btn-4" aria-expanded="false" aria-controls="faq-ans-4">
                                        <span class="faq-question">How do I prepare ABC+?</span>
                                        <span class="faq-indicator" aria-hidden="true">
                                            <svg class="icon-plus" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                        </span>
                                    </button>
                                    <div class="faq-collapse" id="faq-ans-4" role="region" aria-labelledby="faq-btn-4">
                                        <div class="faq-collapse-inner">
                                            <p class="faq-answer-text">
                                                Add 1–2 teaspoons of ABC+ powder to a glass of water and stir thoroughly. You can also blend it into your favourite smoothie. Follow the serving instructions on the product packaging.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>1–2 teaspoons • Mix well • Enjoy</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Item 05 -->
                            <div class="faq-item" data-faq="5" data-category="ritual">
                                <div class="faq-item-num" aria-hidden="true">05</div>
                                <div class="faq-item-body">
                                    <button type="button" class="faq-trigger" id="faq-btn-5" aria-expanded="false" aria-controls="faq-ans-5">
                                        <span class="faq-question">When is the best time to consume ABC+?</span>
                                        <span class="faq-indicator" aria-hidden="true">
                                            <svg class="icon-plus" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                        </span>
                                    </button>
                                    <div class="faq-collapse" id="faq-ans-5" role="region" aria-labelledby="faq-btn-5">
                                        <div class="faq-collapse-inner">
                                            <p class="faq-answer-text">
                                                ABC+ can be enjoyed at a time that suits your daily routine, whether in the morning or during the day. Consistency and a balanced diet are more important than consuming it at a specific time.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Flexible timing • Everyday wellness</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Item 06 -->
                            <div class="faq-item" data-faq="6" data-category="storage">
                                <div class="faq-item-num" aria-hidden="true">06</div>
                                <div class="faq-item-body">
                                    <button type="button" class="faq-trigger" id="faq-btn-6" aria-expanded="false" aria-controls="faq-ans-6">
                                        <span class="faq-question">Is ABC+ suitable for everyday use?</span>
                                        <span class="faq-indicator" aria-hidden="true">
                                            <svg class="icon-plus" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                        </span>
                                    </button>
                                    <div class="faq-collapse" id="faq-ans-6" role="region" aria-labelledby="faq-btn-6">
                                        <div class="faq-collapse-inner">
                                            <p class="faq-answer-text">
                                                ABC+ is designed to complement everyday nutrition when consumed according to the recommended serving instructions. Individuals with allergies, medical conditions, or specific dietary requirements should review the ingredients and consult a qualified healthcare professional when appropriate.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Everyday nutrition • Follow recommended serving</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Item 07 -->
                            <div class="faq-item" data-faq="7" data-category="storage">
                                <div class="faq-item-num" aria-hidden="true">07</div>
                                <div class="faq-item-body">
                                    <button type="button" class="faq-trigger" id="faq-btn-7" aria-expanded="false" aria-controls="faq-ans-7">
                                        <span class="faq-question">How should I store ABC+?</span>
                                        <span class="faq-indicator" aria-hidden="true">
                                            <svg class="icon-plus" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                        </span>
                                    </button>
                                    <div class="faq-collapse" id="faq-ans-7" role="region" aria-labelledby="faq-btn-7">
                                        <div class="faq-collapse-inner">
                                            <p class="faq-answer-text">
                                                Store ABC+ in a cool, dry place away from direct sunlight, heat, and moisture. Always close the container tightly after use and use a clean, dry spoon. Follow the storage instructions and best-before date printed on the packaging.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Cool &amp; dry place • Keep tightly closed</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Item 08 -->
                            <div class="faq-item" data-faq="8" data-category="orders">
                                <div class="faq-item-num" aria-hidden="true">08</div>
                                <div class="faq-item-body">
                                    <button type="button" class="faq-trigger" id="faq-btn-8" aria-expanded="false" aria-controls="faq-ans-8">
                                        <span class="faq-question">Where can I purchase ABC+?</span>
                                        <span class="faq-indicator" aria-hidden="true">
                                            <svg class="icon-plus" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                        </span>
                                    </button>
                                    <div class="faq-collapse" id="faq-ans-8" role="region" aria-labelledby="faq-btn-8">
                                        <div class="faq-collapse-inner">
                                            <p class="faq-answer-text">
                                                You can purchase NUTRONYX ABC+ directly from our official website, nutronyx.in. For assistance with ordering, product information, or delivery-related questions, contact our customer support team.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Official website • Customer support</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- ==========================================================================
             BOTTOM CONCIERGE ASSISTANCE STRIP
             ========================================================================== -->
        <section class="faq-bottom-cta">
            <div class="faq-bottom-cta-inner">
                <h3>Ready to Make Wellness Part of Your Day?</h3>
                <p>Discover thoughtfully crafted natural nutrition designed to complement your everyday wellness routine.</p>
                <div class="faq-cta-btn-group">
                    <a href="shop" class="btn-faq-shop">
                        <span>Explore Our Products</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                    <a href="contact" class="btn-faq-contact">
                        <span>Contact Our Team</span>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <?php include __DIR__ . "/includes/footer.php"; ?>

    <!-- Scripts: GSAP & Main Interactions -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="assets/js/main.js?v=20261124_v3"></script>
</body>
</html>
