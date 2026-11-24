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
                        Welcome To<br><span class="welcome-brand-name">NUTRONYX</span>
                    </h2>
                    
                    <p class="welcome-subtitle">
                        Add your phone number to get instant 10% discount on your first purchase.
                    </p>

                    <form id="welcomeLeadForm" class="welcome-form" novalidate>
                        <!-- Phone Input Group (+91 + Mobile) -->
                        <div class="welcome-input-group" id="welcomeInputGroup">
                            <div class="welcome-country-badge" aria-hidden="true">
                                <span class="country-flag">🇮🇳</span>
                                <span class="country-code">+91</span>
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </div>
                            <input type="tel" 
                                   id="welcomeMobileInput" 
                                   name="mobile" 
                                   class="welcome-mobile-input" 
                                   placeholder="Mobile" 
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

                    <!-- Legal Disclaimer -->
                    <p class="welcome-disclaimer">
                        By signing up via text you agree to receive recurring automated marketing messages and shopping cart reminders at the phone number provided. Reply STOP to unsubscribe.
                    </p>
                </div>

                <!-- Success Screen (Shown on valid submission) -->
                <div id="welcomeSuccessContainer" class="welcome-success-screen" style="display: none;">
                    <div class="welcome-success-icon" aria-hidden="true">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1E5736" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <h3 class="welcome-success-title">You're on the list!</h3>
                    <p class="welcome-success-desc">
                        Enjoy <strong>10% off</strong> your first order with this welcome coupon:
                    </p>
                    <div class="welcome-coupon-box" id="welcomeCouponBox" role="button" tabindex="0" title="Click to copy coupon code">
                        <span class="coupon-code-text" id="welcomeCouponCode">WELCOME10</span>
                        <button type="button" class="btn-copy-code" id="btnCopyCode" aria-label="Copy code">
                            Copy
                        </button>
                    </div>
                    <p id="copyFeedback" class="copy-feedback-text" style="display: none;">Code copied to clipboard!</p>
                    <a href="shop" class="welcome-shop-btn">
                        <span>Shop Now with 10% Off &rarr;</span>
                    </a>
                </div>
            </div>

            <!-- Right Column: Visual Lifestyle Product Imagery -->
            <div class="welcome-modal-media" aria-hidden="true">
                <img src="assets/images/ABC%20front%20image.jpeg" 
                     alt="NUTRONYX ABC+ Botanical Nutrition" 
                     class="welcome-product-img" 
                     loading="eager">
                <div class="welcome-media-overlay"></div>
                <div class="welcome-media-badge">
                    <span>100% Pure Botanical Nutrition</span>
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
    background: rgba(14, 38, 26, 0.62);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    cursor: pointer;
}

.welcome-modal-dialog {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 760px;
    transform: scale(0.92) translateY(12px);
    transition: transform 0.38s cubic-bezier(0.16, 1, 0.3, 1);
}

.nutronyx-welcome-modal.is-active .welcome-modal-dialog {
    transform: scale(1) translateY(0);
}

.welcome-modal-card {
    background: #FFFFFF;
    border-radius: 20px;
    box-shadow: 0 30px 70px rgba(0, 0, 0, 0.28), 0 0 0 1px rgba(18, 51, 33, 0.08);
    display: grid;
    grid-template-columns: 1.08fr 0.92fr;
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
    background: #123321;
    color: #FFFFFF;
    transform: rotate(90deg);
}

/* Left Content Column */
.welcome-modal-content {
    padding: 42px 36px 36px 36px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: #FFFFFF;
}

.welcome-title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 26px;
    font-weight: 700;
    line-height: 1.22;
    color: #111827;
    margin: 0 0 10px 0;
    text-align: center;
    letter-spacing: -0.015em;
}

.welcome-brand-name {
    font-family: var(--font-sans, 'Plus Jakarta Sans', sans-serif);
    font-size: 23px;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: #1E5736;
}

.welcome-subtitle {
    font-family: var(--font-sans, 'Plus Jakarta Sans', sans-serif);
    font-size: 13.5px;
    line-height: 1.5;
    color: #4B5563;
    margin: 0 0 22px 0;
    text-align: center;
}

/* Form Styles */
.welcome-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.welcome-input-group {
    display: flex;
    align-items: stretch;
    border: 1.5px solid #D1D5DB;
    border-radius: 8px;
    overflow: hidden;
    background: #FFFFFF;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.welcome-input-group:focus-within {
    border-color: #1E5736;
    box-shadow: 0 0 0 3px rgba(30, 87, 54, 0.15);
}

.welcome-country-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 12px;
    background: #F9FAFB;
    border-right: 1.5px solid #E5E7EB;
    font-family: var(--font-sans, sans-serif);
    font-size: 13.5px;
    font-weight: 600;
    color: #374151;
    user-select: none;
    flex-shrink: 0;
}

.country-flag {
    font-size: 15px;
    line-height: 1;
}

.welcome-mobile-input {
    border: none;
    outline: none;
    background: transparent;
    padding: 12px 14px;
    font-family: var(--font-sans, sans-serif);
    font-size: 15px;
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
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #FCA5A5;
}

.welcome-submit-btn {
    background: #112319;
    color: #FFFFFF;
    border: none;
    border-radius: 8px;
    padding: 13px 20px;
    font-family: var(--font-sans, sans-serif);
    font-size: 14.5px;
    font-weight: 700;
    letter-spacing: 0.02em;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: 0 4px 14px rgba(17, 35, 25, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
}

.welcome-submit-btn:hover {
    background: #1E5736;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(30, 87, 54, 0.35);
}

.welcome-submit-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.welcome-disclaimer {
    font-family: var(--font-sans, sans-serif);
    font-size: 10.5px;
    line-height: 1.45;
    color: #9CA3AF;
    margin: 14px 0 0 0;
    text-align: center;
}

/* Success Screen Styles */
.welcome-success-screen {
    text-align: center;
    padding: 10px 0;
}

.welcome-success-icon {
    margin-bottom: 12px;
}

.welcome-success-title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 24px;
    font-weight: 700;
    color: #123321;
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
    background: #F4EFE6;
    border: 1.5px dashed #C4A45A;
    border-radius: 10px;
    padding: 10px 18px;
    cursor: pointer;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.welcome-coupon-box:hover {
    background: #EFE8DA;
    border-color: #1E5736;
}

.coupon-code-text {
    font-family: var(--font-sans, sans-serif);
    font-size: 18px;
    font-weight: 800;
    letter-spacing: 0.12em;
    color: #1E5736;
}

.btn-copy-code {
    background: #1E5736;
    color: #FFFFFF;
    border: none;
    border-radius: 6px;
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.copy-feedback-text {
    font-size: 12px;
    color: #1E5736;
    font-weight: 600;
    margin: 0 0 16px 0;
}

.welcome-shop-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    background: #1E5736;
    color: #FFFFFF;
    border-radius: 8px;
    padding: 12px 20px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(30, 87, 54, 0.25);
    transition: all 0.2s ease;
    margin-top: 10px;
}

.welcome-shop-btn:hover {
    background: #123321;
    transform: translateY(-1px);
    color: #FFFFFF;
}

/* Right Media Column */
.welcome-modal-media {
    position: relative;
    overflow: hidden;
    background: #F3EFE8;
    min-height: 380px;
}

.welcome-product-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
    transition: transform 0.6s ease;
}

.welcome-modal-card:hover .welcome-product-img {
    transform: scale(1.03);
}

.welcome-media-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(18, 51, 33, 0.05) 0%, rgba(18, 51, 33, 0.35) 100%);
    pointer-events: none;
}

.welcome-media-badge {
    position: absolute;
    bottom: 18px;
    left: 18px;
    right: 18px;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-radius: 999px;
    padding: 8px 16px;
    text-align: center;
    font-family: var(--font-sans, sans-serif);
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #1E5736;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
        max-height: 90vh;
        overflow-y: auto;
    }

    .welcome-modal-media {
        display: none;
    }

    .welcome-modal-content {
        padding: 36px 22px 28px 22px;
    }

    .welcome-title {
        font-size: 23px;
    }

    .welcome-brand-name {
        font-size: 20px;
    }
}
</style>

<script>
/**
 * NUTRONYX Welcome Popup Controller
 * Activates on visit per browsing session
 */
(function() {
    const STORAGE_KEY = 'nutronyx_welcome_popup_seen';
    const POPUP_DELAY_MS = 1400; // 1.4s delay for natural page loading

    function initWelcomeModal() {
        // Check if already dismissed or completed in this session
        try {
            if (sessionStorage.getItem(STORAGE_KEY)) {
                return;
            }
        } catch (e) {
            // Fallback if sessionStorage is disabled
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
                sessionStorage.setItem(STORAGE_KEY, '1');
            } catch (e) {}
        }

        // Schedule popup entrance
        setTimeout(() => {
            showModal();
        }, POPUP_DELAY_MS);

        // Close triggers
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (backdrop) backdrop.addEventListener('click', closeModal);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('is-active')) {
                closeModal();
            }
        });

        // Numeric-only input filter
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
                    submitBtn.innerHTML = '<span>Activating Discount...</span>';
                }

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

                    if (res.success) {
                        if (formContainer) formContainer.style.display = 'none';
                        if (successContainer) successContainer.style.display = 'block';
                        try {
                            sessionStorage.setItem(STORAGE_KEY, '1');
                        } catch (e) {}
                    } else {
                        if (errorMsg) {
                            errorMsg.textContent = res.message || 'Please check your mobile number and try again.';
                            errorMsg.style.display = 'block';
                        }
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<span>Join Now</span>';
                        }
                    }
                } catch (err) {
                    // Even if network fails, grant the coupon client-side
                    if (formContainer) formContainer.style.display = 'none';
                    if (successContainer) successContainer.style.display = 'block';
                    try {
                        sessionStorage.setItem(STORAGE_KEY, '1');
                    } catch (e) {}
                }
            });
        }

        // Copy coupon handler
        function copyCode() {
            const code = 'WELCOME10';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(() => {
                    showCopyFeedback();
                }).catch(() => fallbackCopy(code));
            } else {
                fallbackCopy(code);
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
            }, 2500);
        }

        if (couponBox) couponBox.addEventListener('click', copyCode);
        if (btnCopy) btnCopy.addEventListener('click', (e) => {
            e.stopPropagation();
            copyCode();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWelcomeModal);
    } else {
        initWelcomeModal();
    }
})();
</script>
