<?php
/**
 * NUTRONYX — Contact Page
 * "Tell us how we can help."
 * Luxury Botanical Editorial Experience
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not already active
if (empty($_SESSION['nutronyx_csrf_token'])) {
    $_SESSION['nutronyx_csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['nutronyx_csrf_token'];
$currentPage = 'contact';
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

    <meta name="description" content="Connect with the NUTRONYX Botanical Concierge. Whether you have questions regarding NUTRONYX ABC+, need order assistance, or wish to explore a partnership, we're here to help.">

    <!-- Google Fonts: Cormorant Garamond, Playfair Display & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Global Base & Component Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261124_v1">
    <!-- Scoped Contact Page Stylesheet -->
    <link rel="stylesheet" href="assets/css/contact.css?v=20261119_v1">
</head>
<body class="nutronyx-body contact-page-body">

    <!-- Header Navigation with Active Contact State (Preserved) -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main id="main-content">
        <!-- ==========================================================================
             CONTACT STAGE: "Tell us how we can help."
             Bespoke Luxury Wellness Layout matching Editorial Reference
             ========================================================================== -->
        <section class="contact-experience-section" id="contact-experience" aria-label="Contact NUTRONYX">
            <div class="contact-experience-container">
                <div class="contact-stage-grid">

                    <!-- ==============================================================
                         LEFT COLUMN: EDITORIAL NARRATIVE & CATEGORY TOUCHPOINTS
                         ============================================================== -->
                    <div class="contact-narrative-col">
                        
                        <!-- Eyebrow Pill -->
                        <div class="contact-eyebrow-pill">
                            <span>GET IN TOUCH</span>
                        </div>

                        <!-- Main Editorial Headline -->
                        <h1 class="contact-headline">
                            <span class="hl-line">Tell us how</span>
                            <span class="hl-line">we <em class="hl-italic-green">can help.</em></span>
                        </h1>

                        <!-- Supporting Copy -->
                        <p class="contact-narrative-subtext">
                            Whether you have a question about our products, need assistance with an order, have feedback, or would like to discuss a partnership, we’d love to hear from you.
                        </p>

                        <!-- Subtle Hairline Divider -->
                        <div class="contact-narrative-hairline" aria-hidden="true"></div>

                        <!-- Three Touchpoint Categories (Interactive with Form Subject) -->
                        <div class="contact-touchpoints-row" role="radiogroup" aria-label="Inquiry Categories">
                            
                            <!-- 1. Product Questions -->
                            <div class="touchpoint-item" data-topic="Product Questions" tabindex="0" role="radio" aria-checked="false" title="Inquire about products or ingredients">
                                <div class="touchpoint-icon-box" aria-hidden="true">
                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                                        <circle cx="9" cy="12" r="0.9" fill="currentColor"></circle>
                                        <circle cx="12" cy="12" r="0.9" fill="currentColor"></circle>
                                        <circle cx="15" cy="12" r="0.9" fill="currentColor"></circle>
                                    </svg>
                                </div>
                                <span class="touchpoint-label">Product<br>Questions</span>
                            </div>

                            <div class="touchpoint-divider" aria-hidden="true"></div>

                            <!-- 2. Order Support -->
                            <div class="touchpoint-item" data-topic="Order Support" tabindex="0" role="radio" aria-checked="false" title="Inquire about order or shipping">
                                <div class="touchpoint-icon-box" aria-hidden="true">
                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 3L2 8.5L12 14L22 8.5L12 3Z"></path>
                                        <path d="M2 8.5V16.5L12 22V14"></path>
                                        <path d="M22 8.5V16.5L12 22"></path>
                                        <path d="M12 14L16.5 11.5"></path>
                                        <path d="M12 14L7.5 11.5"></path>
                                    </svg>
                                </div>
                                <span class="touchpoint-label">Order<br>Support</span>
                            </div>

                            <div class="touchpoint-divider" aria-hidden="true"></div>

                            <!-- 3. Partnership Inquiries -->
                            <div class="touchpoint-item" data-topic="Partnership Inquiries" tabindex="0" role="radio" aria-checked="false" title="Explore retail or practitioner partnerships">
                                <div class="touchpoint-icon-box" aria-hidden="true">
                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 11l-2.5-2.5a2 2 0 0 0-2.8 0L11 10.2l-1.7-1.7a2 2 0 0 0-2.8 0L4 11"></path>
                                        <path d="M2 13l4.5 4.5a2 2 0 0 0 2.8 0L12 15l2.7 2.5a2 2 0 0 0 2.8 0L22 13"></path>
                                        <path d="M7 11.5l3.5 3.5"></path>
                                        <path d="M13.5 15l3.5-3.5"></path>
                                    </svg>
                                </div>
                                <span class="touchpoint-label">Partnership<br>Inquiries</span>
                            </div>

                        </div>

                    </div>


                    <!-- ==============================================================
                         RIGHT COLUMN: LUXURY ELEVATED CONTACT FORM CARD
                         ============================================================== -->
                    <div class="contact-form-col">
                        <div class="contact-luxury-card">

                            <!-- Live Feedback Alert Banner -->
                            <div id="formAlert" class="form-alert-banner" role="alert" aria-live="polite" style="display: none;">
                                <div class="alert-icon-box" aria-hidden="true">
                                    <svg class="alert-icon alert-icon-error" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="12"></line>
                                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                    </svg>
                                </div>
                                <p class="alert-message" id="alertMessageText"></p>
                            </div>

                            <!-- The Contact Form -->
                            <form id="nutronyxContactForm" class="contact-form-inner" method="POST" action="api/contact-handler.php" novalidate>
                                
                                <!-- Anti-Spam Security Traps (Honeypot, Timestamp & CSRF) -->
                                <div class="bot-catch-trap" aria-hidden="true" style="position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden;">
                                    <label for="bot_catch">Leave this field empty</label>
                                    <input type="text" name="bot_catch" id="bot_catch" tabindex="-1" autocomplete="off">
                                </div>
                                <input type="hidden" name="form_timestamp" id="formTimestamp" value="<?= time() ?>">
                                <input type="hidden" name="csrf_token" id="csrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                                <!-- Inputs 2x2 Grid -->
                                <div class="form-inputs-grid">
                                    
                                    <!-- Full Name * -->
                                    <div class="form-input-pill-wrap" id="wrap-full_name">
                                        <span class="field-icon" aria-hidden="true">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="12" cy="7" r="4"></circle>
                                            </svg>
                                        </span>
                                        <input type="text" 
                                               id="full_name" 
                                               name="full_name" 
                                               class="field-input-control" 
                                               placeholder="Full Name *" 
                                               required 
                                               autocomplete="name" 
                                               minlength="2" 
                                               maxlength="100">
                                    </div>

                                    <!-- Email Address * -->
                                    <div class="form-input-pill-wrap" id="wrap-email">
                                        <span class="field-icon" aria-hidden="true">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                                            </svg>
                                        </span>
                                        <input type="email" 
                                               id="email" 
                                               name="email" 
                                               class="field-input-control" 
                                               placeholder="Email Address *" 
                                               required 
                                               autocomplete="email" 
                                               maxlength="120">
                                    </div>

                                    <!-- Phone Number (Optional) -->
                                    <div class="form-input-pill-wrap" id="wrap-phone">
                                        <span class="field-icon" aria-hidden="true">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                            </svg>
                                        </span>
                                        <input type="tel" 
                                               id="phone" 
                                               name="phone" 
                                               class="field-input-control" 
                                               placeholder="Phone Number" 
                                               autocomplete="tel">
                                    </div>

                                    <!-- Subject -->
                                    <div class="form-input-pill-wrap" id="wrap-subject">
                                        <span class="field-icon" aria-hidden="true">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                                <line x1="7" y1="7" x2="7.01" y2="7"></line>
                                            </svg>
                                        </span>
                                        <input type="text" 
                                               id="subject" 
                                               name="subject" 
                                               class="field-input-control" 
                                               placeholder="Subject" 
                                               list="contactSubjectOptions"
                                               autocomplete="off">
                                        <datalist id="contactSubjectOptions">
                                            <option value="Product Questions">
                                            <option value="Order Support">
                                            <option value="Partnership Inquiries">
                                            <option value="Daily Ritual &amp; Dosage">
                                            <option value="General Conversation">
                                        </datalist>
                                    </div>

                                </div>

                                <!-- Full Width Message * -->
                                <div class="form-textarea-pill-wrap" id="wrap-message">
                                    <span class="field-icon textarea-icon" aria-hidden="true">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                                            <circle cx="9" cy="12" r="0.9" fill="currentColor"></circle>
                                            <circle cx="12" cy="12" r="0.9" fill="currentColor"></circle>
                                            <circle cx="15" cy="12" r="0.9" fill="currentColor"></circle>
                                        </svg>
                                    </span>
                                    <textarea id="message" 
                                              name="message" 
                                              class="field-textarea-control" 
                                              placeholder="Message *" 
                                              required 
                                              rows="4" 
                                              minlength="10" 
                                              maxlength="3000"></textarea>
                                </div>

                                <!-- Full-Width Pill Submit Button -->
                                <div class="form-submit-row">
                                    <button type="submit" id="submitBtn" class="btn-submit-pill" aria-label="Send Message to NUTRONYX">
                                        <span class="btn-text">Send Message</span>
                                        <span class="btn-arrow" aria-hidden="true">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                                <polyline points="12 5 19 12 12 19"></polyline>
                                            </svg>
                                        </span>
                                        <span class="btn-spinner" aria-hidden="true" style="display: none;">
                                            <svg class="spinner-svg" viewBox="0 0 50 50">
                                                <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="4"></circle>
                                            </svg>
                                        </span>
                                    </button>
                                </div>

                            </form>

                            <!-- Success State Confirmation View -->
                            <div id="formSuccessCard" class="form-success-card" style="display: none;" role="region" aria-label="Message Confirmation">
                                <div class="success-icon-badge" aria-hidden="true">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                </div>
                                <span class="success-eyebrow">MESSAGE DISPATCHED</span>
                                <h3 class="success-title">Your message is with our concierge.</h3>
                                <p class="success-message-text" id="successCustomText">
                                    Thank you. Your inquiry has been safely received. A certified botanical specialist will respond to your email within 24 hours.
                                </p>
                                
                                <div class="success-actions-wrap">
                                    <button type="button" id="sendAnotherBtn" class="btn-send-another">
                                        <span>Send Another Message</span>
                                        <span aria-hidden="true">&rarr;</span>
                                    </button>
                                    <a href="shop.php" class="btn-explore-shop">
                                        <span>Explore Shop Collection</span>
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ==========================================================================
             PARTNERSHIPS SECTION: "Let’s build healthier habits together."
             Editorial Partnership Overview with 4 Interactive Categories
             ========================================================================== -->
        <section class="contact-partnerships-section" id="contact-partnerships" aria-label="NUTRONYX Partnerships">
            <div class="partnership-container">
                <!-- Section Header -->
                <div class="partnership-header">
                    <!-- Eyebrow with Hairlines -->
                    <div class="partnership-eyebrow-wrap">
                        <span class="eyebrow-hairline" aria-hidden="true"></span>
                        <span class="partnership-eyebrow">PARTNERSHIPS</span>
                        <span class="eyebrow-hairline" aria-hidden="true"></span>
                    </div>

                    <!-- Headline -->
                    <h2 class="partnership-headline">
                        Let’s build <em class="hl-italic-green">healthier habits</em> together.
                    </h2>

                    <!-- Supporting Narrative -->
                    <p class="partnership-narrative">
                        Nutronyx works with retailers, wellness professionals, corporate teams, and distribution partners who believe in making daily nutrition simpler.
                    </p>
                </div>

                <!-- 4 Partner Cards Grid -->
                <div class="partnership-cards-grid">
                    
                    <!-- 1. Retail Stores -->
                    <div class="partner-card" data-partner-type="Retail Stores" tabindex="0" role="button" aria-label="Partner with us as a Retail Store">
                        <div class="partner-icon-badge" aria-hidden="true">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"></path>
                                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                                <path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"></path>
                                <path d="M2 7h20"></path>
                            </svg>
                        </div>
                        <h3 class="partner-card-title">Retail Stores</h3>
                        <p class="partner-card-desc">Bring Nutronyx to your customers</p>
                    </div>

                    <!-- 2. Distributors -->
                    <div class="partner-card" data-partner-type="Distributors" tabindex="0" role="button" aria-label="Partner with us as a Distributor">
                        <div class="partner-icon-badge" aria-hidden="true">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3L2 8.5L12 14L22 8.5L12 3Z"></path>
                                <path d="M2 8.5V16.5L12 22V14"></path>
                                <path d="M22 8.5V16.5L12 22"></path>
                                <path d="M12 14L16.5 11.5"></path>
                                <path d="M12 14L7.5 11.5"></path>
                            </svg>
                        </div>
                        <h3 class="partner-card-title">Distributors</h3>
                        <p class="partner-card-desc">Grow with a trusted wellness brand</p>
                    </div>

                    <!-- 3. Corporate Wellness -->
                    <div class="partner-card" data-partner-type="Corporate Wellness" tabindex="0" role="button" aria-label="Partner with us for Corporate Wellness">
                        <div class="partner-icon-badge" aria-hidden="true">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <h3 class="partner-card-title">Corporate Wellness</h3>
                        <p class="partner-card-desc">Support your team’s everyday health</p>
                    </div>

                    <!-- 4. Creators & Influencers -->
                    <div class="partner-card" data-partner-type="Creators & Influencers" tabindex="0" role="button" aria-label="Partner with us as a Creator or Influencer">
                        <div class="partner-icon-badge" aria-hidden="true">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                            </svg>
                        </div>
                        <h3 class="partner-card-title">Creators & Influencers</h3>
                        <p class="partner-card-desc">Collaborate to spread better nutrition</p>
                    </div>

                </div>

                <!-- Call to Action Pill Button -->
                <div class="partnership-cta-wrap">
                    <button type="button" class="btn-partner-cta" id="btnPartnerCta" aria-label="Partner with Nutronyx">
                        <span>Partner with Nutronyx</span>
                        <span class="btn-arrow" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </span>
                    </button>
                </div>
            </div>
        </section>
    </main>

    <!-- Shared Signature Site Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- GSAP 3 & ScrollTrigger CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>

    <!-- Scoped Contact Interaction Script -->
    <script src="assets/js/contact.js?v=20261117_v2"></script>

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