/**
 * NUTRONYX SHOP PAGE
 * Section 01: Shop Hero & Section 02: Editorial Product Collection
 * GSAP 3 & ScrollTrigger Editorial Animation System + Interactive Commerce
 */

let flagshipQty = 1;
let activeVariantId = 'var_abc_250g';
let activeVariantBasePrice = 299;
let totalCartItems = 0;

document.addEventListener("DOMContentLoaded", () => {
    // Initialize variant selector if present on page
    const variantSelect = document.getElementById("dossierVariantSelect");
    if (variantSelect) {
        onDossierVariantChange(variantSelect.value);
    }
    // Respect user's motion preferences
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    // Header scroll background styling
    const siteHeader = document.querySelector(".site-header");
    if (siteHeader) {
        window.addEventListener("scroll", () => {
            if (window.scrollY > 30) {
                siteHeader.classList.add("scrolled");
            } else {
                siteHeader.classList.remove("scrolled");
            }
        }, { passive: true });
    }

    // Smooth scroll for explore collection CTA
    const exploreBtn = document.querySelector('a[href="#collection"]');
    if (exploreBtn) {
        exploreBtn.addEventListener("click", (e) => {
            const target = document.querySelector("#collection");
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: "smooth", block: "start" });
            }
        });
    }

    // Check if GSAP is available
    if (typeof gsap === "undefined") return;

    if (typeof ScrollTrigger !== "undefined") {
        gsap.registerPlugin(ScrollTrigger);
    }

    // Reduced motion handling
    if (prefersReducedMotion) {
        gsap.to([
            ".shop-hero-eyebrow-wrap",
            ".headline-line",
            ".shop-hero-description",
            ".shop-hero-actions",
            ".shop-hero-hallmarks",
            ".shop-stage-frame",
            ".shop-floating-ritual-card",
            ".shop-floating-ing-badge",
            ".collection-header-stage",
            ".flagship-editorial-stage"
        ], {
            opacity: 1,
            duration: 0.5,
            stagger: 0.08
        });
        return;
    }

    // =========================================================================
    // 1. SECTION 01: SHOP HERO ENTRANCE TIMELINE
    // =========================================================================
    const shopHero = document.querySelector(".shop-hero-section");
    if (shopHero) {
        const heroTl = gsap.timeline({ defaults: { ease: "power3.out" } });

        gsap.set(".shop-hero-eyebrow-wrap", { opacity: 0, y: 18 });
        gsap.set(".headline-line", { yPercent: 110, opacity: 0 });
        gsap.set(".shop-hero-description", { opacity: 0, y: 22 });
        gsap.set(".shop-hero-actions > *", { opacity: 0, y: 20 });
        gsap.set(".shop-hero-hallmarks", { opacity: 0, y: 15 });
        gsap.set(".shop-stage-halo", { opacity: 0, scale: 0.8 });
        gsap.set(".shop-stage-frame", { opacity: 0, y: 35, scale: 0.95 });
        gsap.set(".shop-floating-ritual-card", { opacity: 0, y: 30, scale: 0.9 });
        gsap.set(".shop-floating-ing-badge", { opacity: 0, scale: 0.8, y: -15 });
        gsap.set(".shop-bg-branch-top", { opacity: 0, x: 20, y: -15 });
        gsap.set(".shop-bg-leaf-left", { opacity: 0, x: -20 });

        heroTl
            .to([".shop-bg-branch-top", ".shop-bg-leaf-left"], {
                opacity: 1,
                x: 0,
                y: 0,
                duration: 1.2,
                ease: "power2.out"
            }, 0.1)
            .to(".shop-hero-eyebrow-wrap", {
                opacity: 1,
                y: 0,
                duration: 0.7
            }, 0.2)
            .to(".headline-line", {
                yPercent: 0,
                opacity: 1,
                duration: 1.05,
                stagger: 0.14,
                ease: "power4.out"
            }, 0.35)
            .to(".shop-stage-halo", {
                opacity: 1,
                scale: 1,
                duration: 1.4,
                ease: "power2.out"
            }, 0.4)
            .to(".shop-stage-frame", {
                opacity: 1,
                y: 0,
                scale: 1,
                duration: 1.2,
                ease: "power3.out"
            }, 0.45)
            .to(".shop-hero-description", {
                opacity: 1,
                y: 0,
                duration: 0.85
            }, 0.65)
            .to(".shop-hero-actions > *", {
                opacity: 1,
                y: 0,
                duration: 0.75,
                stagger: 0.12,
                ease: "back.out(1.4)"
            }, 0.8)
            .to(".shop-floating-ritual-card", {
                opacity: 1,
                y: 0,
                scale: 1,
                duration: 0.9,
                ease: "power3.out"
            }, 0.9)
            .to(".shop-floating-ing-badge", {
                opacity: 1,
                scale: 1,
                y: 0,
                duration: 0.8,
                ease: "back.out(1.6)"
            }, 0.95)
            .to(".shop-hero-hallmarks", {
                opacity: 1,
                y: 0,
                duration: 0.7
            }, 1.05);

        // Continuous ambient botanical swaying (top-right branch)
        gsap.to(".shop-bg-branch-top img", {
            rotation: 3.5,
            x: 6,
            y: 4,
            duration: 4.2,
            repeat: -1,
            yoyo: true,
            ease: "sine.inOut"
        });

        // Subtle scroll parallax on hero stage
        if (typeof ScrollTrigger !== "undefined") {
            gsap.to(".shop-stage-frame", {
                scrollTrigger: {
                    trigger: shopHero,
                    start: "top top",
                    end: "bottom top",
                    scrub: 1.2
                },
                y: 40,
                ease: "none"
            });
        }
    }

    // =========================================================================
    // 2. SECTION 02: EDITORIAL PRODUCT COLLECTION ANIMATIONS
    // =========================================================================
    const collectionSection = document.querySelector(".section-editorial-collection");
    if (collectionSection && typeof ScrollTrigger !== "undefined") {
        
        // Header Reveal
        const colHeader = collectionSection.querySelector(".collection-header-stage");
        if (colHeader) {
            gsap.set(colHeader.querySelectorAll(".heading-line"), { yPercent: 110, opacity: 0 });
            gsap.set(colHeader.querySelector(".collection-eyebrow-wrap"), { opacity: 0, y: 15 });
            gsap.set(colHeader.querySelector(".collection-supporting-text"), { opacity: 0, y: 20 });

            const headerTl = gsap.timeline({
                scrollTrigger: {
                    trigger: colHeader,
                    start: "top 82%",
                    toggleActions: "play none none reverse"
                }
            });

            headerTl
                .to(colHeader.querySelector(".collection-eyebrow-wrap"), {
                    opacity: 1,
                    y: 0,
                    duration: 0.6,
                    ease: "power2.out"
                })
                .to(colHeader.querySelectorAll(".heading-line"), {
                    yPercent: 0,
                    opacity: 1,
                    duration: 0.95,
                    stagger: 0.12,
                    ease: "power4.out"
                }, "-=0.3")
                .to(colHeader.querySelector(".collection-supporting-text"), {
                    opacity: 1,
                    y: 0,
                    duration: 0.75,
                    ease: "power2.out"
                }, "-=0.4");
        }

        // Flagship Stage Progressive Reveal
        const flagshipStage = collectionSection.querySelector(".flagship-editorial-stage");
        if (flagshipStage) {
            gsap.fromTo(flagshipStage, 
                { opacity: 0, y: 45 },
                {
                    opacity: 1,
                    y: 0,
                    duration: 1.05,
                    ease: "power3.out",
                    scrollTrigger: {
                        trigger: flagshipStage,
                        start: "top 80%",
                        toggleActions: "play none none reverse"
                    }
                }
            );

            // Progressive stagger for dossier elements
            gsap.fromTo(".dossier-ing-chip",
                { opacity: 0, y: 12, scale: 0.9 },
                {
                    opacity: 1,
                    y: 0,
                    scale: 1,
                    stagger: 0.08,
                    duration: 0.5,
                    ease: "back.out(1.4)",
                    scrollTrigger: {
                        trigger: ".dossier-ingredients-box",
                        start: "top 85%",
                        toggleActions: "play none none reverse"
                    }
                }
            );
        }

        // Archive Preview Reveal
        const archiveStage = collectionSection.querySelector(".collection-archive-stage");
        if (archiveStage) {
            gsap.fromTo(archiveStage,
                { opacity: 0, y: 30 },
                {
                    opacity: 1,
                    y: 0,
                    duration: 0.85,
                    ease: "power2.out",
                    scrollTrigger: {
                        trigger: archiveStage,
                        start: "top 86%",
                        toggleActions: "play none none reverse"
                    }
                }
            );
        }
    }
});

// =========================================================================
// 3. FLAGSHIP GALLERY CAROUSEL SCROLLER
// =========================================================================
const flagshipImages = [
    {
        src: 'assets/images/product-showcase-botanical.png',
        alt: 'NUTRONYX ABC+ Daily Wellness Powder with fresh whole botanicals on stone plinth'
    },
    {
        src: 'assets/images/ABC front image.jpeg',
        alt: 'NUTRONYX ABC+ Official Front Pouch Packaging'
    },
    {
        src: 'assets/images/ABC Back pouch.jpeg',
        alt: 'NUTRONYX ABC+ Official Back Pouch Nutrition & Ingredients Information'
    }
];
let currentFlagshipIndex = 0;

function setFlagshipIndex(index) {
    if (index < 0) index = flagshipImages.length - 1;
    if (index >= flagshipImages.length) index = 0;
    currentFlagshipIndex = index;

    const mainImg = document.getElementById("flagshipActiveImg");
    if (!mainImg) return;

    // Smooth image transition
    mainImg.style.opacity = "0.2";
    mainImg.style.transform = "scale(0.97)";

    setTimeout(() => {
        mainImg.src = flagshipImages[currentFlagshipIndex].src;
        mainImg.alt = flagshipImages[currentFlagshipIndex].alt;
        mainImg.style.opacity = "1";
        mainImg.style.transform = "scale(1)";
    }, 180);

    // Update dots
    document.querySelectorAll(".flagship-dot").forEach((dot, idx) => {
        const isActive = (idx === currentFlagshipIndex);
        dot.classList.toggle("active", isActive);
        dot.setAttribute("aria-selected", isActive ? "true" : "false");
    });
}

function stepFlagshipImg(delta) {
    setFlagshipIndex(currentFlagshipIndex + delta);
}

// Backward compatibility helper
function switchFlagshipImg(btn, newSrc) {
    const idx = flagshipImages.findIndex(img => img.src === newSrc);
    if (idx !== -1) {
        setFlagshipIndex(idx);
    } else {
        const mainImg = document.getElementById("flagshipActiveImg");
        if (mainImg) mainImg.src = newSrc;
    }
}

// Touch swipe & keyboard support for flagship carousel
document.addEventListener("DOMContentLoaded", function() {
    const stage = document.querySelector(".flagship-visual-stage");
    if (!stage) return;

    let touchStartX = 0;
    let touchEndX = 0;

    stage.addEventListener("touchstart", function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    stage.addEventListener("touchend", function(e) {
        touchEndX = e.changedTouches[0].screenX;
        const diff = touchEndX - touchStartX;
        if (Math.abs(diff) > 40) {
            if (diff > 0) {
                stepFlagshipImg(-1); // swipe right -> previous
            } else {
                stepFlagshipImg(1);  // swipe left -> next
            }
        }
    }, { passive: true });

    stage.addEventListener("keydown", function(e) {
        if (e.key === "ArrowLeft") {
            stepFlagshipImg(-1);
        } else if (e.key === "ArrowRight") {
            stepFlagshipImg(1);
        }
    });
});

// =========================================================================
// 4. DOSSIER QUANTITY STEPPER & COMMERCE
// =========================================================================
function selectDossierSize(variantId, price, guideText, btnEl) {
    // Support flexible signature if called with legacy arguments
    if (typeof guideText !== 'string' || (btnEl && typeof btnEl === 'string')) {
        // legacy signature: (variantId, price, servings, perServing, guideText, btnEl)
        guideText = arguments[4] || '';
        btnEl = arguments[5] || null;
    }

    activeVariantId = variantId;
    activeVariantBasePrice = price;

    document.querySelectorAll('.size-pill-btn').forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-checked', 'false');
    });

    if (btnEl) {
        btnEl.classList.add('active');
        btnEl.setAttribute('aria-checked', 'true');
    }

    const priceDisplay = document.getElementById("dossierPriceDisplay");
    const priceSub = document.getElementById("dossierPriceSub");
    const totalBtnPrice = document.getElementById("dossierTotalBtnPrice");
    const guideEl = document.getElementById("sizeServingsGuide");

    if (priceDisplay) priceDisplay.textContent = "₹" + price.toLocaleString("en-IN");
    if (priceSub) priceSub.textContent = "Inclusive of all taxes";
    if (guideEl) guideEl.textContent = guideText;
    if (totalBtnPrice) {
        totalBtnPrice.textContent = "₹" + (price * flagshipQty).toLocaleString("en-IN");
    }
}

function onDossierVariantChange(variantId) {
    if (variantId === 'var_abc_500g') {
        const btn500 = document.getElementById('pill_var_abc_500g');
        selectDossierSize('var_abc_500g', 499, 'Save ₹150 (Best Value)', btn500);
    } else {
        const btn250 = document.getElementById('pill_var_abc_250g');
        selectDossierSize('var_abc_250g', 299, 'Save ₹99', btn250);
    }
}

function changeDossierQty(delta) {
    flagshipQty += delta;
    if (flagshipQty < 1) flagshipQty = 1;
    if (flagshipQty > 10) flagshipQty = 10;

    const qtyValEl = document.getElementById("dossierQtyVal");
    const totalBtnPrice = document.getElementById("dossierTotalBtnPrice");

    if (qtyValEl) qtyValEl.textContent = flagshipQty;
    if (totalBtnPrice) {
        totalBtnPrice.textContent = "₹" + (activeVariantBasePrice * flagshipQty).toLocaleString("en-IN");
    }
}

function addDossierToCart(btn) {
    const qty = flagshipQty || 1;
    const vid = activeVariantId || 'var_abc_250g';

    // Server-side Cart API sync
    fetch('api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', variant_id: vid, quantity: qty })
    })
    .then(r => r.json())
    .then(data => {
        if (data.item_count !== undefined) {
            const badge = document.querySelector(".cart-badge");
            if (badge) {
                badge.textContent = data.item_count;
                badge.style.transform = "scale(1.35)";
                setTimeout(() => { badge.style.transform = "scale(1)"; }, 220);
            }
        }
    })
    .catch(err => console.error("Cart update error:", err));

    const activePill = document.querySelector(".size-pill-btn.active .pill-weight");
    const varLabel = activePill ? activePill.textContent.trim() : (activeVariantId === 'var_abc_500g' ? "400g" : "200g");
    showCartToast(`Added ${qty}x NUTRONYX ABC+ Daily Powder (${varLabel}) to your cart!`);

    // Button pulse animation
    btn.style.transform = "scale(0.97)";
    setTimeout(() => { btn.style.transform = ""; }, 160);
}

function showCartToast(message) {
    const toast = document.getElementById("cartToast");
    const msgEl = document.getElementById("cartToastMsg");
    if (toast && msgEl) {
        msgEl.textContent = message;
        toast.classList.add("show");
        setTimeout(() => {
            toast.classList.remove("show");
        }, 3400);
    }
}

// =========================================================================
// 5. FLAGSHIP PRODUCT EXPERIENCE SUITE — TOP-NOTCH MOTION CHOREOGRAPHY
// =========================================================================
document.addEventListener("DOMContentLoaded", () => {
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    initShopExperienceMotion(prefersReducedMotion);
});

function initShopExperienceMotion(prefersReducedMotion) {
    if (typeof gsap === "undefined") return;

    if (prefersReducedMotion) {
        gsap.set([
            ".shop-value-ribbon",
            ".value-badge-item",
            ".power-narrative-col",
            ".botanical-card",
            ".ritual-panoramic-card",
            ".ritual-checklist li",
            ".reviews-header",
            ".shop-review-card"
        ], { opacity: 1, y: 0, scale: 1, clearProps: "all" });
        return;
    }

    // --- 1. Value Badges Ribbon Reveal ---
    const ribbon = document.querySelector(".shop-value-ribbon");
    if (ribbon && typeof ScrollTrigger !== "undefined") {
        gsap.fromTo(".value-badge-item", 
            { opacity: 0, y: 20 },
            {
                opacity: 1,
                y: 0,
                duration: 0.6,
                stagger: 0.1,
                ease: "power2.out",
                scrollTrigger: {
                    trigger: ribbon,
                    start: "top 86%",
                    once: true
                }
            }
        );
    }

    // --- 2. The Power of ABC+ Entrance & 3D Interactive Tilt ---
    const powerSection = document.querySelector(".shop-power-section");
    if (powerSection && typeof ScrollTrigger !== "undefined") {
        const powerTl = gsap.timeline({
            scrollTrigger: {
                trigger: powerSection,
                start: "top 78%",
                once: true
            }
        });

        powerTl
            .fromTo(".power-narrative-col > *",
                { opacity: 0, y: 22 },
                { opacity: 1, y: 0, duration: 0.7, stagger: 0.1, ease: "power3.out" }
            )
            .fromTo(".botanical-card",
                { opacity: 0, y: 32, scale: 0.94 },
                { opacity: 1, y: 0, scale: 1, duration: 0.7, stagger: 0.08, ease: "back.out(1.4)" },
                "-=0.45"
            );

        // Continuous subtle organic floating for ingredient illustrations
        gsap.to(".botanical-thumb-img", {
            y: -6,
            duration: 2.4,
            repeat: -1,
            yoyo: true,
            ease: "sine.inOut",
            stagger: {
                each: 0.4,
                from: "random"
            }
        });

        // Interactive 3D mouse tilt on botanical cards for desktop
        const cards = powerSection.querySelectorAll(".botanical-card");
        cards.forEach((card) => {
            card.addEventListener("mousemove", (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                gsap.to(card, {
                    rotationY: (x / rect.width) * 14,
                    rotationX: (y / rect.height) * -14,
                    transformPerspective: 800,
                    duration: 0.35,
                    ease: "power1.out"
                });
            });

            card.addEventListener("mouseleave", () => {
                gsap.to(card, {
                    rotationX: 0,
                    rotationY: 0,
                    duration: 0.7,
                    ease: "power2.out"
                });
            });
        });
    }

    // --- 3. Panoramic Ritual Banner Reveal ---
    const ritualBanner = document.querySelector(".shop-ritual-banner-stage");
    if (ritualBanner && typeof ScrollTrigger !== "undefined") {
        const ritualTl = gsap.timeline({
            scrollTrigger: {
                trigger: ritualBanner,
                start: "top 78%",
                once: true
            }
        });

        ritualTl
            .fromTo(".ritual-panoramic-card",
                { opacity: 0, y: 35, scale: 0.97 },
                { opacity: 1, y: 0, scale: 1, duration: 0.85, ease: "power3.out" }
            )
            .fromTo(".ritual-banner-content > *",
                { opacity: 0, x: -20 },
                { opacity: 1, x: 0, duration: 0.6, stagger: 0.1, ease: "power2.out" },
                "-=0.45"
            )
            .fromTo(".ritual-checklist li",
                { opacity: 0, x: -14 },
                { opacity: 1, x: 0, duration: 0.5, stagger: 0.08, ease: "power2.out" },
                "-=0.3"
            );
    }

    // --- 4. Loved by Wellness Seekers Reviews Slider ---
    const reviewsStage = document.querySelector(".shop-reviews-stage");
    if (reviewsStage) {
        if (typeof ScrollTrigger !== "undefined") {
            const revTl = gsap.timeline({
                scrollTrigger: {
                    trigger: reviewsStage,
                    start: "top 78%",
                    once: true
                }
            });

            revTl
                .fromTo(".reviews-header > *",
                    { opacity: 0, y: 18 },
                    { opacity: 1, y: 0, duration: 0.65, stagger: 0.08, ease: "power2.out" }
                )
                .fromTo(".shop-review-card",
                    { opacity: 0, y: 25 },
                    { opacity: 1, y: 0, duration: 0.7, stagger: 0.1, ease: "power2.out" },
                    "-=0.35"
                );
        }

        // Carousel Logic for Review Cards
        const rCards = Array.from(reviewsStage.querySelectorAll(".shop-review-card"));
        const rDots = Array.from(reviewsStage.querySelectorAll(".shop-reviews-dots .r-dot"));
        const btnPrev = reviewsStage.querySelector(".btn-review-prev");
        const btnNext = reviewsStage.querySelector(".btn-review-next");

        let activeIndex = 1; // Start with Ananya R. in center

        function setFeaturedReview(newIdx) {
            const total = rCards.length;
            activeIndex = (newIdx + total) % total;

            rCards.forEach((c, idx) => {
                if (idx === activeIndex) {
                    c.classList.add("is-featured");
                    if (window.innerWidth <= 980) {
                        c.style.display = "flex";
                    }
                } else {
                    c.classList.remove("is-featured");
                    if (window.innerWidth <= 980) {
                        c.style.display = "none";
                    }
                }
            });

            rDots.forEach((d, idx) => {
                if (idx === activeIndex) {
                    d.classList.add("active");
                } else {
                    d.classList.remove("active");
                }
            });
        }

        if (btnPrev) {
            btnPrev.addEventListener("click", () => {
                setFeaturedReview(activeIndex - 1);
            });
        }

        if (btnNext) {
            btnNext.addEventListener("click", () => {
                setFeaturedReview(activeIndex + 1);
            });
        }

        rDots.forEach((dot) => {
            dot.addEventListener("click", () => {
                const idx = parseInt(dot.getAttribute("data-index"), 10);
                if (!isNaN(idx)) setFeaturedReview(idx);
            });
        });

        rCards.forEach((card, idx) => {
            card.addEventListener("click", () => {
                setFeaturedReview(idx);
            });
        });

        // Initialize state for current window width
        setFeaturedReview(1);
        window.addEventListener("resize", () => {
            setFeaturedReview(activeIndex);
        });
    }
}

// =========================================================================
// 6. DYNAMIC CATALOG MULTI-SKU & CART HANDLERS
// =========================================================================
function switchCatalogVariant(btn) {
    const card = btn.closest('.shop-product-card');
    if (!card) return;
    
    // Deactivate sibling buttons in this card
    card.querySelectorAll('.catalog-sku-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const vid = btn.getAttribute('data-variant-id');
    const sku = btn.getAttribute('data-sku');
    const priceFormatted = btn.getAttribute('data-price-formatted');
    const compare = btn.getAttribute('data-compare');
    const imgUrl = btn.getAttribute('data-img');
    const pid = card.getAttribute('data-product-id');
    
    card.setAttribute('data-active-variant', vid);
    
    // Update displayed price
    const priceEl = document.getElementById('price-' + pid);
    if (priceEl && priceFormatted) {
        priceEl.textContent = priceFormatted.replace('₹', '');
    }
    
    // Update compare-at price
    const compEl = document.getElementById('compare-' + pid);
    if (compEl) {
        if (compare) {
            compEl.textContent = compare;
            compEl.style.display = 'inline';
        } else {
            compEl.style.display = 'none';
        }
    }
    
    // Update SKU badge
    const skuEl = document.getElementById('sku-badge-' + pid);
    if (skuEl && sku) {
        skuEl.textContent = sku;
    }
    
    // Update image if specified
    const imgEl = document.getElementById('img-' + pid);
    if (imgEl && imgUrl) {
        imgEl.src = imgUrl;
    }
}

function addCatalogProductToCart(productId, btn) {
    const card = document.getElementById('card-' + productId);
    const vid = card ? card.getAttribute('data-active-variant') : '';
    if (!vid) return;
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>Adding...</span>';
    
    fetch('api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', variant_id: vid, quantity: 1 })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<span>✓ Added to Cart</span>';
        setTimeout(() => { btn.innerHTML = originalText; }, 1800);
        
        if (data.item_count !== undefined) {
            const badge = document.querySelector(".cart-badge");
            if (badge) {
                badge.textContent = data.item_count;
                badge.style.transform = "scale(1.35)";
                setTimeout(() => { badge.style.transform = "scale(1)"; }, 220);
            }
        }
        
        const productName = card.querySelector('.product-card-name')?.textContent.trim() || 'Product';
        const activeSku = card.querySelector('.catalog-sku-btn.active .sku-btn-title')?.textContent.trim() || '';
        showCartToast(`Added 1x ${productName} ${activeSku ? '(' + activeSku + ')' : ''} to your cart!`);
    })
    .catch(err => {
        console.error("Cart error:", err);
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

