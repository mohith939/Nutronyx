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
    <link rel="stylesheet" href="assets/css/style.css?v=20261117_v2">

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
                            <span class="faq-eyebrow">THE NUTRONYX KNOWLEDGE EXPERIENCE</span>
                            <h2 class="faq-headline">
                                <span class="faq-headline-line">Curiosity</span>
                                <span class="faq-headline-line faq-headline-accent">Meets Clarity.</span>
                            </h2>
                            <p class="faq-intro-subtext">Everything you want to know about making ABC+ part of your everyday ritual.</p>

                            <!-- Editorial Concierge Card -->
                            <div class="faq-concierge-card">
                                <div class="concierge-icon-wrap" aria-hidden="true">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                </div>
                                <div class="concierge-content">
                                    <h3 class="concierge-title">Have a specific question?</h3>
                                    <p class="concierge-text">Our wellness concierge is on hand to guide your daily ritual and address personal preferences.</p>
                                    <a href="contact.php" class="concierge-link" style="margin-bottom: 8px;">
                                        <span>Contact Ritual Concierge</span>
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
                                                NUTRONYX ABC+ is a daily wellness blend crafted from five pure whole-food ingredients: Amla (Indian Gooseberry), Beetroot, Carrot, Ginger, and Lemon. Formulated into a finely soluble powder, it delivers everyday vitality, sustained energy, and digestive harmony without synthetic vitamins, artificial sweeteners, or fillers.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Five whole ingredients · Zero synthetic additives</span>
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
                                                ABC+ contains exactly five unadulterated ingredients: fresh organic Amla for natural Vitamin C and immunity; Beetroot for natural nitrates and healthy blood circulation; Carrot for beta-carotene and antioxidant protection; Ginger for digestive warmth and gut comfort; and Lemon for gentle daily cleansing and natural freshness. Nothing else is added.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Amla · Beetroot · Carrot · Ginger · Lemon</span>
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
                                                Unlike conventional supplements that rely on synthetic isolates, chemical binders, maltodextrin fillers, or masked artificial flavors, ABC+ honors the whole plant matrix. The ingredients are gently dehydrated at controlled low temperatures to protect delicate phytonutrients and enzymes, allowing your body to absorb them as real, recognized nourishment.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Whole-plant matrix · Bioavailable whole-food nutrition</span>
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
                                                Preparation is a calm three-step ritual: Add one level scoop (approx. 10g) of ABC+ powder into a glass, pour 200ml of room temperature or lukewarm water (or blend into your favorite smoothie), and stir gently until dissolved into a rich, deep ruby elixir. Drink fresh and savor the natural earthy notes.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>1 scoop · 200ml water · Stir gently &amp; enjoy</span>
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
                                                We recommend enjoying ABC+ first thing in the morning on an empty stomach or alongside a light breakfast. Taking it at the start of your day supports optimal nutrient absorption, natural hydration, and calm, sustained vitality without the jittery spike and crash associated with caffeine.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Ideal as your first morning wellness ritual</span>
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
                                                Yes. ABC+ is formulated specifically as a daily nourishment ritual for lasting well-being. Because it contains exclusively pure whole foods with zero synthetic stimulants, chemical preservatives, or pharmaceuticals, it is inherently gentle and designed for consistent, lifelong daily use.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Gentle food-grade nutrition · Safe daily</span>
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
                                                Store your ABC+ canister in a cool, dry pantry away from direct sunlight, excess heat, and humidity. Always close the airtight lid tightly after use. Since our formula contains no synthetic anti-caking agents, using a clean, completely dry spoon ensures the powder remains fine, fragrant, and fresh throughout its shelf life.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Cool, dry place · Airtight seal · No anti-caking chemicals</span>
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
                                                ABC+ is available exclusively through our official online boutique at nutronyx.in and select wellness partners. Ordering directly guarantees that you receive fresh small-batch craftsmanship, authentic packaging, and direct support from our ritual concierge team.
                                            </p>
                                            <div class="faq-highlight-pill">
                                                <span class="pill-dot"></span>
                                                <span>Exclusive at nutronyx.in · Fresh small batches</span>
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
                <h3>Ready to Begin Your Daily Ritual?</h3>
                <p>Discover clean, sustained plant vitality with NUTRONYX ABC+. Pure 100% whole foods designed for lifelong health.</p>
                <div class="faq-cta-btn-group">
                    <a href="shop.php" class="btn-faq-shop">
                        <span>Shop ABC+ Ritual</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                    <a href="contact.php" class="btn-faq-contact">
                        <span>Talk to Concierge</span>
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
    <script src="assets/js/main.js?v=20261124_v1"></script>
</body>
</html>
