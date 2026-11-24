/**
 * NUTRONYX — Become a Partner
 * Sections 01 & 02: GSAP Motion Choreography & Interactive Micro-Interactions
 */

document.addEventListener("DOMContentLoaded", () => {
    // Detect reduced motion preference
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    // Initialize Section 01: Hero Animation Sequence
    initPartnerHeroMotion(prefersReducedMotion);

    // Initialize Section 01: Subtle Stage 3D Tilt
    if (!prefersReducedMotion) {
        initStageTiltEffect();
    }

    // Initialize Section 02: Why Partner Motion Sequence
    initPartnerWhyMotion(prefersReducedMotion);

    // Initialize Section 03: Categories Discovery Experience
    initPartnerCategoriesDiscovery(prefersReducedMotion);

    // Initialize Section 04: Built for Partnerships That Last
    initPartnerTrustMotion(prefersReducedMotion);

    // Initialize Section 05: The Partnership Journey
    initPartnerJourneyMotion(prefersReducedMotion);

    // Initialize Section 13: Partner Application Form
    initPartnerApplicationForm();
});

/**
 * Orchestrates the introductory GSAP choreography for Section 01 (Hero)
 */
function initPartnerHeroMotion(prefersReducedMotion) {
    if (typeof gsap === "undefined") {
        console.warn("NUTRONYX: GSAP library not found. Falling back to default layout.");
        return;
    }

    if (prefersReducedMotion) {
        gsap.set([
            ".partner-eyebrow-wrapper",
            ".partner-main-headline",
            ".partner-hero-description",
            ".pillar-card",
            ".partner-actions-group",
            ".partner-trust-credential-bar",
            ".partner-stage-frame",
            ".plinth-provenance-plaque"
        ], { opacity: 1, y: 0, scale: 1, clearProps: "all" });
        return;
    }

    // Master Hero Timeline
    const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

    tl
        // 1. Eyebrow Flanked Rule Reveal
        .fromTo(".partner-eyebrow-wrapper", 
            { opacity: 0, y: 16 }, 
            { opacity: 1, y: 0, duration: 0.75, delay: 0.1 }
        )
        // 2. Primary Headline Lines
        .fromTo(".partner-main-headline .headline-line", 
            { y: 30, opacity: 0 }, 
            { y: 0, opacity: 1, duration: 1.0, stagger: 0.15, ease: "power3.out" }, 
            "-=0.45"
        )
        // 3. Supporting Description
        .fromTo(".partner-hero-description", 
            { opacity: 0, y: 18 }, 
            { opacity: 1, y: 0, duration: 0.8 }, 
            "-=0.6"
        )
        // 4. Pillars Matrix Stagger
        .fromTo(".pillar-card", 
            { opacity: 0, y: 20 }, 
            { opacity: 1, y: 0, duration: 0.7, stagger: 0.1, ease: "power2.out" }, 
            "-=0.55"
        )
        // 5. Dual Buttons Group
        .fromTo(".partner-actions-group", 
            { opacity: 0, y: 16 }, 
            { opacity: 1, y: 0, duration: 0.75 }, 
            "-=0.5"
        )
        // 6. Trust Credentials Bar
        .fromTo(".partner-trust-credential-bar", 
            { opacity: 0, y: 14 }, 
            { opacity: 1, y: 0, duration: 0.7 }, 
            "-=0.5"
        )
        // 7. Right Visual Stage Entrance
        .fromTo(".partner-stage-frame", 
            { opacity: 0, scale: 0.96, y: 25 }, 
            { opacity: 1, scale: 1, y: 0, duration: 1.1, ease: "power3.out" }, 
            "-=1.1"
        )
        // 8. Floating Glassmorphic Plaque Entrance
        .fromTo(".plinth-provenance-plaque", 
            { opacity: 0, y: 25 }, 
            { opacity: 1, y: 0, duration: 0.85, ease: "power2.out" }, 
            "-=0.6"
        );
}

/**
 * Subtle interactive hover tilt for Section 01 visual stage
 */
function initStageTiltEffect() {
    const stageFrame = document.getElementById("partnerStageFrame");
    const heroSection = document.getElementById("partner-hero");

    if (!stageFrame || !heroSection) return;

    heroSection.addEventListener("mousemove", (e) => {
        const rect = heroSection.getBoundingClientRect();
        const mouseX = e.clientX - rect.left - rect.width / 2;
        const mouseY = e.clientY - rect.top - rect.height / 2;

        const tiltX = (mouseY / rect.height) * -6;
        const tiltY = (mouseX / rect.width) * 6;

        gsap.to(stageFrame, {
            rotationX: tiltX,
            rotationY: tiltY,
            transformPerspective: 1000,
            duration: 0.8,
            ease: "power1.out"
        });
    });

    heroSection.addEventListener("mouseleave", () => {
        gsap.to(stageFrame, {
            rotationX: 0,
            rotationY: 0,
            duration: 1.0,
            ease: "power2.out"
        });
    });
}

/**
 * Orchestrates the GSAP ScrollTrigger choreography for Section 02 (Why Partner)
 */
function initPartnerWhyMotion(prefersReducedMotion) {
    if (typeof gsap === "undefined" || typeof ScrollTrigger === "undefined") {
        return;
    }

    const whySection = document.getElementById("why-partner");
    if (!whySection) return;

    if (prefersReducedMotion) {
        gsap.set([
            ".why-eyebrow-wrapper",
            ".why-main-headline",
            ".why-supporting-copy",
            ".why-botanical-art-stage",
            ".why-phil-card"
        ], { opacity: 1, y: 0, scale: 1, clearProps: "all" });
        return;
    }

    // Initial hidden state
    gsap.set([
        ".why-eyebrow-wrapper",
        ".why-main-headline",
        ".why-supporting-copy",
        ".why-botanical-art-stage"
    ], { opacity: 0, y: 24 });

    gsap.set(".why-phil-card", { opacity: 0, y: 28 });

    // Scroll-triggered reveal timeline
    ScrollTrigger.create({
        trigger: whySection,
        start: "top 76%",
        once: true,
        onEnter: () => {
            const whyTl = gsap.timeline({ defaults: { ease: "power3.out" } });

            whyTl
                // Left anchor header text cascade
                .to(".why-eyebrow-wrapper", {
                    opacity: 1,
                    y: 0,
                    duration: 0.7
                })
                .to(".why-main-headline", {
                    opacity: 1,
                    y: 0,
                    duration: 0.85
                }, "-=0.5")
                .to(".why-supporting-copy", {
                    opacity: 1,
                    y: 0,
                    duration: 0.75
                }, "-=0.6")
                // Left botanical art reveal
                .to(".why-botanical-art-stage", {
                    opacity: 1,
                    y: 0,
                    duration: 0.9,
                    ease: "power2.out"
                }, "-=0.6")
                // Right philosophy cards cascade
                .to(".why-phil-card", {
                    opacity: 1,
                    y: 0,
                    duration: 0.8,
                    stagger: 0.12,
                    ease: "power2.out",
                    clearProps: "transform"
                }, "-=0.7");
        }
    });
}

/**
 * Orchestrates Section 03: Who Can Partner With Us
 * 6-Card Showcase Grid Entrance & Interactive Motion
 */
function initPartnerCategoriesDiscovery(prefersReducedMotion) {
    const section = document.getElementById("who-can-partner");
    if (!section) return;

    if (prefersReducedMotion || typeof gsap === "undefined" || typeof ScrollTrigger === "undefined") {
        gsap.set([".showcase-header", ".showcase-card"], { opacity: 1, y: 0, clearProps: "all" });
        return;
    }

    gsap.set(".showcase-header", { opacity: 0, y: 24 });
    gsap.set(".showcase-card", { opacity: 0, y: 32 });

    ScrollTrigger.create({
        trigger: section,
        start: "top 76%",
        once: true,
        onEnter: () => {
            const tl = gsap.timeline({ defaults: { ease: "power3.out" } });
            tl.to(".showcase-header", {
                opacity: 1,
                y: 0,
                duration: 0.8
            })
            .to(".showcase-card", {
                opacity: 1,
                y: 0,
                duration: 0.7,
                stagger: 0.08,
                ease: "power2.out",
                clearProps: "transform"
            }, "-=0.5");
        }
    });

    // Category click handler: smooth scroll & pre-select in application form
    const categoryMap = {
        "retail": "Retail Store",
        "distributors": "Distributor / Wholesaler",
        "corporate": "Corporate Wellness",
        "fitness": "Gym / Fitness Centre",
        "professionals": "Health & Wellness Professional",
        "creators": "Content Creator / Influencer"
    };

    section.querySelectorAll(".showcase-card").forEach(card => {
        card.addEventListener("click", () => {
            const cat = card.dataset.category;
            const select = document.getElementById("partner_category");
            if (select && categoryMap[cat]) {
                select.value = categoryMap[cat];
            }
        });
    });
}


/**
 * Orchestrates Section 04: Built for Partnerships That Last
 * Bilateral Keystone Architecture Reveal & Interactive Subtle Tilting
 */
function initPartnerTrustMotion(prefersReducedMotion) {
    const section = document.getElementById("built-for-trust");
    if (!section) return;

    if (typeof gsap === "undefined" || typeof ScrollTrigger === "undefined") return;

    if (prefersReducedMotion) {
        gsap.set([
            ".trust-header-block",
            ".trust-keystone-stage",
            ".card-left",
            ".card-right",
            ".trust-covenant-ribbon"
        ], { opacity: 1, y: 0, x: 0, scale: 1, clearProps: "all" });
        return;
    }

    // Initial hidden states
    gsap.set(".trust-header-block", { opacity: 0, y: 24 });
    gsap.set(".trust-keystone-stage", { opacity: 0, scale: 0.96, y: 16 });
    gsap.set(".card-left", { opacity: 0, x: -26 });
    gsap.set(".card-right", { opacity: 0, x: 26 });
    gsap.set(".trust-covenant-ribbon", { opacity: 0, y: 18 });

    ScrollTrigger.create({
        trigger: section,
        start: "top 76%",
        once: true,
        onEnter: () => {
            const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

            tl
                // 1. Header block reveal
                .to(".trust-header-block", {
                    opacity: 1,
                    y: 0,
                    duration: 0.85
                })
                // 2. Central Keystone visual monolith reveal
                .to(".trust-keystone-stage", {
                    opacity: 1,
                    scale: 1,
                    y: 0,
                    duration: 1.0,
                    ease: "power2.out"
                }, "-=0.55")
                // 3. Left wing advantages (01 & 02) slide in
                .to(".card-left", {
                    opacity: 1,
                    x: 0,
                    duration: 0.8,
                    stagger: 0.14,
                    clearProps: "transform"
                }, "-=0.75")
                // 4. Right wing advantages (03 & 04) slide in
                .to(".card-right", {
                    opacity: 1,
                    x: 0,
                    duration: 0.8,
                    stagger: 0.14,
                    clearProps: "transform"
                }, "-=0.75")
                // 5. Bottom covenant ribbon reveal
                .to(".trust-covenant-ribbon", {
                    opacity: 1,
                    y: 0,
                    duration: 0.7
                }, "-=0.4");
        }
    });

    // Subtle interactive mouse parallax on Keystone frame for desktop
    const keystoneStage = document.getElementById("trustKeystoneStage");
    if (keystoneStage && window.innerWidth > 1080) {
        section.addEventListener("mousemove", (e) => {
            const rect = section.getBoundingClientRect();
            const mouseX = e.clientX - rect.left - rect.width / 2;
            const mouseY = e.clientY - rect.top - rect.height / 2;

            gsap.to(keystoneStage, {
                rotationY: (mouseX / rect.width) * 4,
                rotationX: (mouseY / rect.height) * -4,
                transformPerspective: 1000,
                duration: 0.8,
                ease: "power1.out"
            });
        });

        section.addEventListener("mouseleave", () => {
            gsap.to(keystoneStage, {
                rotationX: 0,
                rotationY: 0,
                duration: 1.0,
                ease: "power2.out"
            });
        });
    }
}

/**
 * Orchestrates Section 05: The Partnership Journey
 * Alternating Promenade ScrollTrigger Progression & Node Micro-Interactions
 */
function initPartnerJourneyMotion(prefersReducedMotion) {
    const section = document.getElementById("partnership-journey");
    if (!section) return;

    if (typeof gsap === "undefined" || typeof ScrollTrigger === "undefined") return;

    if (prefersReducedMotion) {
        gsap.set([
            ".journey-header-block",
            ".journey-spine-line",
            ".journey-step-row",
            ".journey-step-card",
            ".node-ring",
            ".journey-closing-bridge"
        ], { opacity: 1, y: 0, x: 0, scale: 1, scaleY: 1, clearProps: "all" });
        return;
    }

    // Initial hidden states
    gsap.set(".journey-header-block", { opacity: 0, y: 24 });
    gsap.set(".journey-spine-line", { scaleY: 0 });
    gsap.set(".journey-step-row", { opacity: 0, y: 26 });
    gsap.set(".card-left", { x: -20 });
    gsap.set(".card-right", { x: 20 });
    gsap.set(".node-ring", { scale: 0.5, opacity: 0 });
    gsap.set(".journey-closing-bridge", { opacity: 0, y: 22 });

    ScrollTrigger.create({
        trigger: section,
        start: "top 76%",
        once: true,
        onEnter: () => {
            const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

            tl
                // 1. Header block reveal
                .to(".journey-header-block", {
                    opacity: 1,
                    y: 0,
                    duration: 0.85
                })
                // 2. Spine track draws downwards
                .to(".journey-spine-line", {
                    scaleY: 1,
                    duration: 1.1,
                    ease: "power2.inOut"
                }, "-=0.5")
                // 3. Step rows cascade along the spine
                .to(".journey-step-row", {
                    opacity: 1,
                    y: 0,
                    duration: 0.75,
                    stagger: 0.14
                }, "-=0.85")
                // 4. Milestone nodes pop in
                .to(".node-ring", {
                    scale: 1,
                    opacity: 1,
                    duration: 0.6,
                    stagger: 0.12,
                    ease: "back.out(1.5)"
                }, "-=0.8")
                // 5. Cards settle horizontally
                .to([".card-left", ".card-right"], {
                    x: 0,
                    duration: 0.65,
                    stagger: 0.1,
                    clearProps: "transform"
                }, "-=0.7")
                // 6. Layer 3 closing bridge reveal
                .to(".journey-closing-bridge", {
                    opacity: 1,
                    y: 0,
                    duration: 0.8
                }, "-=0.35");
        }
    });
}

/**
 * Handles Partnership Application Form Submission & Interactivity
 */
function initPartnerApplicationForm() {
    const form = document.getElementById("partnerApplicationForm");
    const submitBtn = document.getElementById("partnerSubmitBtn");
    const alertBox = document.getElementById("partnerFormAlert");
    const alertText = document.getElementById("partnerAlertText");
    const alertIcon = document.getElementById("partnerAlertIcon");
    const successCard = document.getElementById("partnerSuccessCard");
    const successDesc = document.getElementById("partnerSuccessDesc");
    const resetBtn = document.getElementById("partnerResetBtn");

    if (!form) return;

    // Smooth scroll for all anchor triggers
    document.querySelectorAll('a[href="#partner-application"]').forEach(anchor => {
        anchor.addEventListener("click", (e) => {
            const target = document.getElementById("partner-application");
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: "smooth", block: "start" });
                const firstInput = document.getElementById("partner_name");
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 600);
                }
            }
        });
    });

    // Clear error highlights on user input
    form.querySelectorAll("input, select, textarea").forEach(el => {
        el.addEventListener("input", () => {
            const wrap = el.closest(".partner-field-wrap");
            if (wrap) wrap.classList.remove("has-error");
            hideAlert();
        });
    });

    function showAlert(msg, isError = true) {
        if (!alertBox || !alertText) return;
        alertBox.className = `partner-form-alert ${isError ? 'alert-error' : 'alert-success'}`;
        alertText.textContent = msg;
        if (alertIcon) {
            alertIcon.innerHTML = isError 
                ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
                : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        }
        alertBox.style.display = "flex";
        alertBox.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }

    function hideAlert() {
        if (alertBox) {
            alertBox.style.display = "none";
        }
    }

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        hideAlert();

        const nameInput = document.getElementById("partner_name");
        const companyInput = document.getElementById("partner_company");
        const emailInput = document.getElementById("partner_email");
        const phoneInput = document.getElementById("partner_phone");
        const categoryInput = document.getElementById("partner_category");
        const messageInput = document.getElementById("partner_message");

        const name = nameInput ? nameInput.value.trim() : "";
        const company = companyInput ? companyInput.value.trim() : "";
        const email = emailInput ? emailInput.value.trim() : "";
        const phone = phoneInput ? phoneInput.value.trim() : "";
        const category = categoryInput ? categoryInput.value.trim() : "";
        const message = messageInput ? messageInput.value.trim() : "";

        let hasError = false;

        if (name.length < 2) {
            document.getElementById("wrap-partner-name")?.classList.add("has-error");
            showAlert("Please enter your full name (minimum 2 characters).");
            hasError = true;
        } else if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            document.getElementById("wrap-partner-email")?.classList.add("has-error");
            showAlert("Please provide a valid email address.");
            hasError = true;
        } else if (!phone || !/^[0-9+\-\s().]{7,25}$/.test(phone)) {
            document.getElementById("wrap-partner-phone")?.classList.add("has-error");
            showAlert("Please enter a valid phone or WhatsApp number.");
            hasError = true;
        } else if (!category) {
            document.getElementById("wrap-partner-category")?.classList.add("has-error");
            showAlert("Please select your partnership category.");
            hasError = true;
        } else if (message.length < 10) {
            document.getElementById("wrap-partner-message")?.classList.add("has-error");
            showAlert("Please tell us about your partnership interest in at least 10 characters.");
            hasError = true;
        }

        if (hasError) return;

        // Button loading state
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.querySelector(".btn-text").textContent = "SUBMITTING PROPOSAL...";
        }

        const formData = new FormData(form);

        try {
            const res = await fetch("api/partner-handler.php", {
                method: "POST",
                body: formData,
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            });

            const data = await res.json();

            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.querySelector(".btn-text").textContent = "SUBMIT PARTNERSHIP APPLICATION";
            }

            if (data.success) {
                form.style.display = "none";
                hideAlert();
                if (successCard) {
                    if (successDesc) {
                        successDesc.textContent = data.message;
                    }
                    successCard.style.display = "block";
                    successCard.scrollIntoView({ behavior: "smooth", block: "center" });
                }
            } else {
                showAlert(data.message || "Failed to submit application. Please verify your details.");
                if (data.errors) {
                    Object.keys(data.errors).forEach(field => {
                        const wrap = document.getElementById(`wrap-partner-${field}`);
                        if (wrap) wrap.classList.add("has-error");
                    });
                }
            }
        } catch (err) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.querySelector(".btn-text").textContent = "SUBMIT PARTNERSHIP APPLICATION";
            }
            showAlert("Connection error. Please try again or email us at nutronyx.offical@gmail.com.");
        }
    });

    if (resetBtn) {
        resetBtn.addEventListener("click", () => {
            form.reset();
            const timestampInput = document.getElementById("partnerFormTimestamp");
            if (timestampInput) timestampInput.value = Math.floor(Date.now() / 1000);
            if (successCard) successCard.style.display = "none";
            form.style.display = "flex";
            hideAlert();
        });
    }
}

