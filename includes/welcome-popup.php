<?php
/**
 * NUTRONYX — Welcome Discount Modal Popup
 * Triggered on first visit per browsing session
 */
?>
<!-- Welcome 10% Discount Lead-Capture Modal -->
<div id="nutronyxWelcomeModal" class="nutronyx-welcome-modal" role="dialog" aria-modal="true" aria-labelledby="welcomeModalTitle" style="display: none;">
    <div class="welcome-modal-backdrop" id="welcomeModalBackdrop"></div>
    
    <div class="welcome-modal-dialog">
        <!-- Close Button (Top Right) -->
        <button type="button" class="welcome-modal-close" id="welcomeModalClose" aria-label="Close welcome offer popup">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <div class="welcome-modal-card">
            <!-- Left Column: Form & Narrative -->
            <div class="welcome-modal-content">
                <div id="welcomeFormContainer">
                    <h2 class="welcome-title" id="welcomeModalTitle">
                        Welcome to <span class="welcome-brand-name">NUTRONYX</span>
                    </h2>
                    
                    <p class="welcome-subtitle">
                        Enjoy 10% off your first order and stay connected for exclusive offers and product updates.
                    </p>

                    <form id="welcomeLeadForm" class="welcome-form" novalidate>
                        <!-- Phone Input Group (IN +91 | Enter mobile number) -->
                        <div class="welcome-input-group" id="welcomeInputGroup">
                            <div class="welcome-country-badge" aria-hidden="true">
                                <span class="country-abbr">IN</span>
                                <span class="country-code">+91</span>
                            </div>
                            <span class="input-divider" aria-hidden="true"></span>
                            <input type="tel" 
                                   id="welcomeMobileInput" 
                                   name="mobile" 
                                   class="welcome-mobile-input" 
                                   placeholder="Enter mobile number" 
                                   maxlength="10" 
                                   pattern="[6-9][0-9]{9}" 
                                   inputmode="numeric" 
                                   required 
                                   autocomplete="tel-national"
                                   aria-label="10-digit mobile number">
                        </div>

                        <!-- Validation Error Alert -->
                        <div id="welcomeErrorMsg" class="welcome-error-msg" style="display: none;"></div>

                        <!-- CTA Button -->
                        <button type="submit" class="welcome-submit-btn" id="welcomeSubmitBtn">
                            <span>Join Now</span>
                        </button>
                    </form>

                    <!-- Legal Disclaimer with Privacy Policy link -->
                    <p class="welcome-disclaimer">
                        By joining, you agree to receive promotional messages, product updates and cart reminders from Nutronyx. You may unsubscribe at any time. View our <a href="privacy-policy" target="_blank" class="welcome-policy-link">Privacy Policy</a>.
                    </p>
                </div>

                <!-- Success Screen (Shown on valid submission to avail coupon) -->
                <div id="welcomeSuccessContainer" class="welcome-success-screen" style="display: none;">
                    <div class="welcome-success-icon" aria-hidden="true">
                        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#143D24" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <h3 class="welcome-success-title">You're on the list!</h3>
                    <p class="welcome-success-desc">
                        Enjoy <strong>10% off</strong> your first order with your welcome coupon:
                    </p>
                    
                    <!-- Avail Coupon Banner -->
                    <div class="welcome-coupon-banner avail" id="welcomeCouponBox" role="button" tabindex="0" title="Click to copy coupon code">
                        <span class="coupon-banner-sub">USE CODE AT CHECKOUT</span>
                        <div class="coupon-code-row">
                            <span class="coupon-banner-code" id="welcomeCouponCode">FIRST10</span>
                            <button type="button" class="btn-copy-code" id="btnCopyCode" aria-label="Copy code">
                                Copy
                            </button>
                        </div>
                    </div>

                    <p id="copyFeedback" class="copy-feedback-text">Code FIRST10 copied to clipboard!</p>
                    <a href="shop" class="welcome-shop-btn">
                        <span>Shop Now with 10% Off &rarr;</span>
                    </a>
                </div>
            </div>

            <!-- Right Column: Visual Brand Identity (Replaces packaging cover with Logo) -->
            <div class="welcome-modal-media" aria-hidden="true">
                <div class="welcome-media-branding">
                    <div class="brand-logo-frame">
                        <img src="assets/images/logo.png?v=20261119_v3" 
                             alt="NUTRONYX" 
                             class="welcome-brand-logo-img">
                    </div>
                    <span class="welcome-brand-dash"></span>
                    <p class="welcome-brand-tagline">A quieter kind of wellness.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ==========================================================================
   NUTRONYX WELCOME POPUP STYLES
   Bespoke luxury presentation matching reference image
   ========================================================================== */
.nutronyx-welcome-modal {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.35s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.35s ease;
}

.nutronyx-welcome-modal.is-active {
    opacity: 1;
    visibility: visible;
}

.welcome-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(14, 38, 26, 0.65);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    cursor: pointer;
}

.welcome-modal-dialog {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 740px;
    transform: scale(0.92) translateY(12px);
    transition: transform 0.38s cubic-bezier(0.16, 1, 0.3, 1);
}

.nutronyx-welcome-modal.is-active .welcome-modal-dialog {
    transform: scale(1) translateY(0);
}

.welcome-modal-card {
    background: #FFFFFF;
    border-radius: 18px;
    box-shadow: 0 30px 70px rgba(0, 0, 0, 0.28), 0 0 0 1px rgba(18, 51, 33, 0.08);
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    overflow: hidden;
    position: relative;
}

/* Close Button (Top right of modal card) */
.welcome-modal-close {
    position: absolute;
    top: -14px;
    right: -14px;
    width: 38px;
    height: 38px;
    background: #FFFFFF;
    border: 1px solid rgba(18, 51, 33, 0.12);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #123321;
    cursor: pointer;
    z-index: 20;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18);
    transition: all 0.2s ease;
}

.welcome-modal-close:hover {
    background: #143D24;
    color: #FFFFFF;
    transform: rotate(90deg);
}

/* Left Content Column */
.welcome-modal-content {
    padding: 38px 34px 34px 34px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: #FFFFFF;
}

.welcome-title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 24px;
    font-weight: 700;
    line-height: 1.25;
    color: #112A1B;
    margin: 0 0 10px 0;
    text-align: center;
    letter-spacing: -0.015em;
}

.welcome-brand-name {
    font-family: var(--font-sans, 'Plus Jakarta Sans', sans-serif);
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 0.05em;
    color: #143D24;
}

.welcome-subtitle {
    font-family: var(--font-sans, 'Plus Jakarta Sans', sans-serif);
    font-size: 13.5px;
    line-height: 1.5;
    color: #4B5563;
    margin: 0 0 18px 0;
    text-align: center;
}

/* Coupon Banner (USE CODE AT CHECKOUT / FIRST10) */
.welcome-coupon-banner {
    background: #F7F5EE;
    border: 1px solid #EBE4D5;
    border-radius: 10px;
    padding: 12px 18px;
    text-align: center;
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
}

.coupon-banner-sub {
    font-family: var(--font-sans, 'Plus Jakarta Sans', sans-serif);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: #5A6D60;
    text-transform: uppercase;
}

.coupon-banner-code {
    font-family: var(--font-sans, 'Plus Jakarta Sans', sans-serif);
    font-size: 21px;
    font-weight: 800;
    letter-spacing: 0.12em;
    color: #143D24;
    line-height: 1.15;
}

/* Form Styles */
.welcome-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.welcome-input-group {
    display: flex;
    align-items: center;
    border: 1.5px solid #D1D5DB;
    border-radius: 9px;
    overflow: hidden;
    background: #FFFFFF;
    padding: 0 14px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.welcome-input-group:focus-within {
    border-color: #143D24;
    box-shadow: 0 0 0 3px rgba(20, 61, 36, 0.12);
}

.welcome-country-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    font-family: var(--font-sans, sans-serif);
    font-size: 14px;
    font-weight: 700;
    color: #1F2937;
    user-select: none;
    flex-shrink: 0;
}

.country-abbr {
    font-size: 12.5px;
    font-weight: 700;
    color: #1F2937;
}

.country-code {
    font-size: 14px;
    font-weight: 700;
    color: #1F2937;
}

.input-divider {
    width: 1px;
    height: 22px;
    background: #D1D5DB;
    margin: 0 12px;
    display: inline-block;
    flex-shrink: 0;
}

.welcome-mobile-input {
    border: none;
    outline: none;
    background: transparent;
    padding: 13px 0;
    font-family: var(--font-sans, sans-serif);
    font-size: 14.5px;
    color: #111827;
    width: 100%;
}

.welcome-mobile-input::placeholder {
    color: #9CA3AF;
}

.welcome-error-msg {
    font-family: var(--font-sans, sans-serif);
    font-size: 12px;
    color: #DC2626;
    text-align: center;
    background: #FEF2F2;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #FCA5A5;
}

.welcome-submit-btn {
    background: #143D24;
    color: #FFFFFF;
    border: none;
    border-radius: 9px;
    padding: 14px 20px;
    font-family: var(--font-sans, sans-serif);
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.02em;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: 0 4px 14px rgba(20, 61, 36, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
}

.welcome-submit-btn:hover {
    background: #0E2C1A;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(20, 61, 36, 0.35);
}

.welcome-submit-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.welcome-disclaimer {
    font-family: var(--font-sans, sans-serif);
    font-size: 11px;
    line-height: 1.5;
    color: #6B7280;
    margin: 16px 0 0 0;
    text-align: center;
}

.welcome-policy-link {
    color: #143D24;
    text-decoration: underline;
    font-weight: 600;
}

/* Success Screen Styles */
.welcome-success-screen {
    text-align: center;
    padding: 14px 0;
}

.welcome-success-icon {
    margin-bottom: 12px;
}

.welcome-success-title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 24px;
    font-weight: 700;
    color: #143D24;
    margin: 0 0 8px 0;
}

.welcome-success-desc {
    font-family: var(--font-sans, sans-serif);
    font-size: 14px;
    color: #4A5E52;
    margin: 0 0 18px 0;
}

.welcome-coupon-box {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: #F7F5EE;
    border: 1.5px dashed #B8860B;
    border-radius: 10px;
    padding: 10px 18px;
    cursor: pointer;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.welcome-coupon-box:hover {
    background: #EFE8DA;
    border-color: #143D24;
}

.coupon-code-text {
    font-family: var(--font-sans, sans-serif);
    font-size: 19px;
    font-weight: 800;
    letter-spacing: 0.12em;
    color: #143D24;
}

.btn-copy-code {
    background: #143D24;
    color: #FFFFFF;
    border: none;
    border-radius: 6px;
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.copy-feedback-text {
    font-size: 12.5px;
    color: #143D24;
    font-weight: 600;
    margin: 0 0 16px 0;
}

.welcome-shop-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    background: #143D24;
    color: #FFFFFF;
    border-radius: 9px;
    padding: 13px 20px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(20, 61, 36, 0.25);
    transition: all 0.2s ease;
    margin-top: 10px;
}

.welcome-shop-btn:hover {
    background: #0E2C1A;
    transform: translateY(-1px);
    color: #FFFFFF;
}

/* Right Media Column — Clean Luxury Logo Branding */
.welcome-modal-media {
    position: relative;
    overflow: hidden;
    background: #FAF8F5;
    border-left: 1px solid #ECE7DE;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 30px;
}

.welcome-media-branding {
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
    max-width: 240px;
}

.brand-logo-frame {
    display: flex;
    align-items: center;
    justify-content: center;
}

.welcome-brand-logo-img {
    width: 180px;
    max-width: 100%;
    height: auto;
    object-fit: contain;
    display: block;
    image-rendering: -webkit-optimize-contrast;
}

.welcome-brand-dash {
    width: 36px;
    height: 1.5px;
    background: #8A9B70;
    display: block;
}

.welcome-brand-tagline {
    font-family: var(--font-serif, 'Playfair Display', Georgia, serif);
    font-size: 15px;
    font-style: italic;
    color: #143D24;
    margin: 0;
    line-height: 1.4;
}

.welcome-coupon-banner.avail {
    background: #F7F5EE;
    border: 1.5px dashed #B8860B;
    border-radius: 10px;
    padding: 14px 20px;
    text-align: center;
    margin: 16px auto 12px;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.welcome-coupon-banner.avail:hover {
    background: #EFE8DA;
    border-color: #143D24;
}

.coupon-code-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

/* Responsive Handling */
@media (max-width: 680px) {
    .welcome-modal-close {
        top: 10px;
        right: 10px;
        width: 32px;
        height: 32px;
    }

    .welcome-modal-card {
        grid-template-columns: 1fr;
        max-height: 92vh;
        overflow-y: auto;
        border-radius: 16px;
    }

    .welcome-modal-media {
        display: none;
    }

    .welcome-modal-content {
        padding: 34px 20px 24px 20px;
    }

    .welcome-title {
        font-size: 22px;
    }

    .welcome-brand-name {
        font-size: 20px;
    }

    .welcome-subtitle {
        font-size: 13px;
        margin-bottom: 16px;
    }

    .welcome-coupon-banner {
        padding: 10px 14px;
        margin-bottom: 16px;
    }

    .coupon-banner-code {
        font-size: 19px;
    }
}
</style>

<script>
/**
 * NUTRONYX Welcome Popup Controller
 * Activates on visit per browsing session
 * Multi-layer persistence prevents re-prompts once submitted or dismissed
 */
(function() {
    const SUBMITTED_KEY = 'nutronyx_welcome_lead_submitted';
    const POPUP_SEEN_KEY = 'nutronyx_welcome_popup_seen';
    const POPUP_DELAY_MS = 1400; // 1.4s delay for natural page loading
    const ACTIVE_COUPON = 'FIRST10';

    function hasUserCompletedOrDismissed() {
        try {
            // 1. Permanent submission check (once submitted, never show phone prompt again)
            if (localStorage.getItem(SUBMITTED_KEY) === '1') {
                return true;
            }
            // 2. Cookie submission check
            if (document.cookie.indexOf(SUBMITTED_KEY + '=1') !== -1) {
                return true;
            }
            // 3. Current session dismissed check
            if (sessionStorage.getItem(POPUP_SEEN_KEY) === '1') {
                return true;
            }
            // 4. LocalStorage popup seen check (valid for 30 days)
            if (localStorage.getItem(POPUP_SEEN_KEY) === '1') {
                return true;
            }
            // 5. Cookie seen check
            if (document.cookie.indexOf(POPUP_SEEN_KEY + '=1') !== -1) {
                return true;
            }
        } catch (e) {
            // Storage access restriction fallback
        }
        return false;
    }

    function initWelcomeModal() {
        if (hasUserCompletedOrDismissed()) {
            return;
        }

        const modal = document.getElementById('nutronyxWelcomeModal');
        const closeBtn = document.getElementById('welcomeModalClose');
        const backdrop = document.getElementById('welcomeModalBackdrop');
        const form = document.getElementById('welcomeLeadForm');
        const mobileInput = document.getElementById('welcomeMobileInput');
        const submitBtn = document.getElementById('welcomeSubmitBtn');
        const errorMsg = document.getElementById('welcomeErrorMsg');
        const formContainer = document.getElementById('welcomeFormContainer');
        const successContainer = document.getElementById('welcomeSuccessContainer');
        const couponBox = document.getElementById('welcomeCouponBox');
        const btnCopy = document.getElementById('btnCopyCode');
        const copyFeedback = document.getElementById('copyFeedback');

        if (!modal) return;

        function showModal() {
            modal.style.display = 'flex';
            // Force reflow for CSS transition
            void modal.offsetWidth;
            modal.classList.add('is-active');
            document.body.classList.add('welcome-modal-open');
        }

        function closeModal() {
            modal.classList.remove('is-active');
            document.body.classList.remove('welcome-modal-open');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 360);
            try {
                sessionStorage.setItem(POPUP_SEEN_KEY, '1');
                localStorage.setItem(POPUP_SEEN_KEY, '1');
                document.cookie = POPUP_SEEN_KEY + '=1; path=/; max-age=2592000; SameSite=Lax';
            } catch (e) {}
        }

        function markCompletedPermanently() {
            try {
                localStorage.setItem(SUBMITTED_KEY, '1');
                localStorage.setItem(POPUP_SEEN_KEY, '1');
                sessionStorage.setItem(POPUP_SEEN_KEY, '1');
                document.cookie = SUBMITTED_KEY + '=1; path=/; max-age=31536000; SameSite=Lax';
                document.cookie = POPUP_SEEN_KEY + '=1; path=/; max-age=31536000; SameSite=Lax';
            } catch (e) {}
        }

        // Schedule popup entrance
        setTimeout(() => {
            if (!hasUserCompletedOrDismissed()) {
                showModal();
            }
        }, POPUP_DELAY_MS);

        // Close triggers
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (backdrop) backdrop.addEventListener('click', closeModal);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('is-active')) {
                closeModal();
            }
        });

        // Numeric-only input filter (10 digits)
        if (mobileInput) {
            mobileInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
                if (errorMsg) errorMsg.style.display = 'none';
            });
        }

        // Form submission
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const mobile = (mobileInput?.value || '').trim();

                // Validation
                if (!/^[6-9]\d{9}$/.test(mobile)) {
                    if (errorMsg) {
                        errorMsg.textContent = 'Please enter a valid 10-digit mobile number.';
                        errorMsg.style.display = 'block';
                    }
                    if (mobileInput) mobileInput.focus();
                    return;
                }

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span>Activating FIRST10...</span>';
                }

                // Copy coupon immediately
                copyCode(ACTIVE_COUPON);

                try {
                    const response = await fetch('api/lead-capture.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ mobile: mobile })
                    });

                    const res = await response.json();

                    // Mark completed permanently in storage so prompt never shows again
                    markCompletedPermanently();

                    if (formContainer) formContainer.style.display = 'none';
                    if (successContainer) successContainer.style.display = 'block';
                } catch (err) {
                    // Even if network fails, grant the coupon client-side and remember completion
                    markCompletedPermanently();
                    if (formContainer) formContainer.style.display = 'none';
                    if (successContainer) successContainer.style.display = 'block';
                }
            });
        }

        // Copy coupon handler
        function copyCode(code) {
            const targetCode = code || ACTIVE_COUPON;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(targetCode).then(() => {
                    showCopyFeedback();
                }).catch(() => fallbackCopy(targetCode));
            } else {
                fallbackCopy(targetCode);
            }
        }

        function fallbackCopy(text) {
            const temp = document.createElement('input');
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            showCopyFeedback();
        }

        function showCopyFeedback() {
            if (copyFeedback) copyFeedback.style.display = 'block';
            if (btnCopy) btnCopy.textContent = 'Copied!';
            setTimeout(() => {
                if (btnCopy) btnCopy.textContent = 'Copy';
            }, 3000);
        }

        if (couponBox) couponBox.addEventListener('click', () => copyCode(ACTIVE_COUPON));
        if (btnCopy) btnCopy.addEventListener('click', (e) => {
            e.stopPropagation();
            copyCode(ACTIVE_COUPON);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWelcomeModal);
    } else {
        initWelcomeModal();
    }
})();
</script>
