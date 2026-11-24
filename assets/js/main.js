/**
 * NUTRONYX — HOMEPAGE HERO (SECTION 1)
 * GSAP & ScrollTrigger Scroll-Driven Animation
 * Purely responsive to scroll progress; zero mouse parallax or autoplay.
 */

document.addEventListener("DOMContentLoaded", () => {
    // 1. Check for prefers-reduced-motion
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (prefersReducedMotion) {
        console.info("Nutronyx: prefers-reduced-motion detected. Disabling scroll animations.");
        return;
    }

    // 4. Register GSAP ScrollTrigger
    if (typeof gsap === "undefined" || typeof ScrollTrigger === "undefined") {
        console.warn("Nutronyx: GSAP or ScrollTrigger not loaded.");
        return;
    }

    gsap.registerPlugin(ScrollTrigger);

    // 5. Create Scroll-Driven Scrubbed Animation
    const heroTimeline = gsap.timeline({
        scrollTrigger: {
            trigger: ".hero-section",
            start: "top top",
            end: "bottom top",
            scrub: 1.2, // Cinematic, smooth scrub response
            invalidateOnRefresh: true,
        }
    });

    // Fade out the scroll indicator early
    heroTimeline.to(".hero-scroll-indicator", {
        opacity: 0,
        y: 20,
        duration: 0.25,
        ease: "power2.out"
    }, 0);

    // Central ABC+ Pouch Composition: Smooth Scroll Parallax
    heroTimeline.to(".hero-pouch-composition", {
        y: -60,
        scale: 0.94,
        opacity: 0.75,
        duration: 1,
        ease: "sine.inOut"
    }, 0);

    // Continuous Gentle Ambient Floating Motion & 3D Interactive Tilt
    if (!prefersReducedMotion) {
        gsap.to(".hero-pouch-composition", {
            y: "-=14",
            rotation: 0.8,
            duration: 4.6,
            repeat: -1,
            yoyo: true,
            ease: "sine.inOut"
        });

        const centerStage = document.querySelector(".hero-center-stage");
        const pouchComp = document.querySelector(".hero-pouch-composition");
        if (centerStage && pouchComp) {
            centerStage.addEventListener("mousemove", (e) => {
                const rect = centerStage.getBoundingClientRect();
                const xPos = (e.clientX - rect.left) / rect.width - 0.5;
                const yPos = (e.clientY - rect.top) / rect.height - 0.5;
                gsap.to(pouchComp, {
                    rotationY: xPos * 8,
                    rotationX: -yPos * 8,
                    duration: 0.5,
                    ease: "power1.out"
                });
            });
            centerStage.addEventListener("mouseleave", () => {
                gsap.to(pouchComp, {
                    rotationY: 0,
                    rotationX: 0,
                    duration: 0.8,
                    ease: "power2.out"
                });
            });
        }
    }

    // Left and Right content soft cinematic transition (Desktop only for right panel)
    heroTimeline.to(".hero-left-content", {
        y: -40,
        opacity: 0.45,
        duration: 1,
        ease: "power1.inOut"
    }, 0);

    // Only apply scroll dimming on wide desktop viewports; keep right panel 100% bright and crisp on mobile
    if (window.innerWidth > 991) {
        heroTimeline.to(".hero-right-panel", {
            y: -30,
            opacity: 0.7,
            duration: 1,
            ease: "power1.inOut"
        }, 0);
    } else {
        gsap.set(".hero-right-panel", { opacity: 1, y: 0 });
    }

    // ==========================================
    // SECTION 2: THE POWER WITHIN ANIMATION
    // Integrated Scroll-Driven Sequence (Phases A -> G)
    // ==========================================
    const powerSection = document.querySelector(".section-power");
    if (powerSection) {
        // Initialize SVG stroke-dasharray and stroke-dashoffset for connecting lines
        const connPaths = document.querySelectorAll(".conn-path");
        connPaths.forEach(path => {
            const length = path.getTotalLength ? path.getTotalLength() : 300;
            gsap.set(path, {
                strokeDasharray: length,
                strokeDashoffset: length
            });
        });

        // Anchor dots initial state
        gsap.set(".line-dot", { scale: 0, opacity: 0, transformOrigin: "center center" });

        // Phase A: Initial states for headers
        gsap.set([".power-eyebrow-wrapper", ".power-headline", ".power-description"], {
            opacity: 0,
            y: 40
        });

        // Phase B: Ingredients initial offset positions (with xPercent/yPercent: -50 preserved)
        gsap.set(".ing-amla", { xPercent: -50, yPercent: -50, y: -70, scale: 0.88, opacity: 0, rotation: -10 });
        gsap.set(".ing-beetroot", { xPercent: -50, yPercent: -50, x: -100, y: -20, scale: 0.88, opacity: 0, rotation: -22 });
        gsap.set(".ing-carrot", { xPercent: -50, yPercent: -50, x: 100, y: -30, scale: 0.88, opacity: 0, rotation: 28 });
        gsap.set(".ing-ginger", { xPercent: -50, yPercent: -50, x: -80, y: 60, scale: 0.88, opacity: 0, rotation: 10 });
        gsap.set(".ing-lemon", { xPercent: -50, yPercent: -50, x: 80, y: 60, scale: 0.88, opacity: 0, rotation: 22 });
        gsap.set(".ing-leaves", { xPercent: -50, yPercent: -50, y: 40, scale: 0.7, opacity: 0, rotation: -50 });

        // Phase C & D: Cards initial state
        gsap.set(".power-card", { opacity: 0, y: 30, scale: 0.95 });

        // Phase E: Powder bowl & CTA initial state (with xPercent: -50 preserved)
        gsap.set(".power-powder-bowl", { xPercent: -50, y: 65, opacity: 0, scale: 0.92 });
        gsap.set(".btn-explore-ingredients", { opacity: 0, y: 25 });

        // Integrated ScrollTrigger Scrub Timeline
        const powerTimeline = gsap.timeline({
            scrollTrigger: {
                trigger: ".section-power",
                start: "top 80%",
                end: "center 50%",
                scrub: 1, // Smooth scroll synchronization
                invalidateOnRefresh: true,
            }
        });

        // A. Section Entrance (0.0 to 0.25)
        powerTimeline
            .to(".power-eyebrow-wrapper", { opacity: 1, y: 0, duration: 0.15, ease: "power2.out" }, 0)
            .to(".power-headline", { opacity: 1, y: 0, duration: 0.2, ease: "power2.out" }, 0.05)
            .to(".power-description", { opacity: 1, y: 0, duration: 0.2, ease: "power2.out" }, 0.1);

        // B. Ingredient Assembly (0.15 to 0.5)
        powerTimeline
            .to(".ing-amla", { x: 0, y: 0, scale: 1, opacity: 1, rotation: 5, duration: 0.35, ease: "power2.out" }, 0.15)
            .to(".ing-beetroot", { x: 0, y: 0, scale: 1, opacity: 1, rotation: -10, duration: 0.35, ease: "power2.out" }, 0.18)
            .to(".ing-carrot", { x: 0, y: 0, scale: 1, opacity: 1, rotation: 18, duration: 0.35, ease: "power2.out" }, 0.2)
            .to(".ing-ginger", { x: 0, y: 0, scale: 1, opacity: 1, rotation: -4, duration: 0.35, ease: "power2.out" }, 0.22)
            .to(".ing-lemon", { x: 0, y: 0, scale: 1, opacity: 1, rotation: 12, duration: 0.35, ease: "power2.out" }, 0.24)
            .to(".ing-leaves", { x: 0, y: 0, scale: 1, opacity: 0.85, rotation: -35, duration: 0.3, ease: "power2.out" }, 0.25);

        // C. Connecting Line Animation (0.35 to 0.65)
        powerTimeline
            .to(".conn-path-amla", { strokeDashoffset: 0, duration: 0.25, ease: "power1.inOut" }, 0.35)
            .to(".dot-amla", { scale: 1, opacity: 1, duration: 0.1, ease: "back.out(2)" }, 0.55)
            
            .to(".conn-path-beetroot", { strokeDashoffset: 0, duration: 0.25, ease: "power1.inOut" }, 0.38)
            .to(".dot-beetroot", { scale: 1, opacity: 1, duration: 0.1, ease: "back.out(2)" }, 0.58)

            .to(".conn-path-ginger", { strokeDashoffset: 0, duration: 0.25, ease: "power1.inOut" }, 0.42)
            .to(".dot-ginger", { scale: 1, opacity: 1, duration: 0.1, ease: "back.out(2)" }, 0.62)

            .to(".conn-path-carrot", { strokeDashoffset: 0, duration: 0.25, ease: "power1.inOut" }, 0.4)
            .to(".dot-carrot", { scale: 1, opacity: 1, duration: 0.1, ease: "back.out(2)" }, 0.6)

            .to(".conn-path-lemon", { strokeDashoffset: 0, duration: 0.25, ease: "power1.inOut" }, 0.44)
            .to(".dot-lemon", { scale: 1, opacity: 1, duration: 0.1, ease: "back.out(2)" }, 0.64);

        // D. Ingredient Card Reveal (0.45 to 0.72)
        powerTimeline
            .to(".card-amla", { opacity: 1, y: 0, scale: 1, duration: 0.2, ease: "power2.out" }, 0.46)
            .to(".card-beetroot", { opacity: 1, y: 0, scale: 1, duration: 0.2, ease: "power2.out" }, 0.5)
            .to(".card-ginger", { opacity: 1, y: 0, scale: 1, duration: 0.2, ease: "power2.out" }, 0.54)
            .to(".card-carrot", { opacity: 1, y: 0, scale: 1, duration: 0.2, ease: "power2.out" }, 0.52)
            .to(".card-lemon", { opacity: 1, y: 0, scale: 1, duration: 0.2, ease: "power2.out" }, 0.56);

        // E. Powder Bowl Entrance (0.52 to 0.8)
        powerTimeline
            .to(".power-powder-bowl", { y: 0, opacity: 1, scale: 1, duration: 0.3, ease: "power2.out" }, 0.52)
            .to(".btn-explore-ingredients", { opacity: 1, y: 0, duration: 0.2, ease: "power2.out" }, 0.65);

        // Window load refresh for ScrollTrigger
        window.addEventListener("load", () => {
            ScrollTrigger.refresh();
        });

        // F & G. Complete Composition settles into place with smooth transition
    }

    // ==========================================
    // SECTION 3: THE NUTRONYX DIFFERENCE
    // Abstract Liquid Wellness Experience Sequence
    // Stages 1 to 7
    // ==========================================
    const differenceSection = document.querySelector(".section-difference");
    if (differenceSection) {
        // Initial setup for Section 3 elements
        // Stage 1: Header
        gsap.set([".diff-eyebrow-wrapper", ".diff-headline", ".diff-description"], {
            opacity: 0,
            y: 40
        });

        // Stage 2 & 3: Liquid Shapes & Glass Ribbons
        gsap.set(".liquid-base-wave", { scale: 0.85, opacity: 0, y: 50, transformOrigin: "center center" });
        gsap.set(".liquid-core-body", { scale: 0.82, opacity: 0, y: 35, transformOrigin: "center center" });
        gsap.set(".liquid-sculpt-wave", { scale: 0.8, opacity: 0, y: 45, transformOrigin: "center center" });
        gsap.set(".ribbon-upper", { scale: 0.88, opacity: 0, rotation: -6, transformOrigin: "center center" });
        gsap.set(".ribbon-lower", { scale: 0.88, opacity: 0, rotation: 6, transformOrigin: "center center" });

        // Stage 4: Light Highlights & Drops & Floating Pills
        gsap.set(".liquid-highlight", { opacity: 0, scale: 0.9, transformOrigin: "center center" });
        gsap.set(".liquid-drop", { opacity: 0, scale: 0.4, transformOrigin: "center center" });
        gsap.set(".diff-floating-pill", { opacity: 0, y: 25, scale: 0.9 });

        // Stage 5: Brand Pillar Cards
        gsap.set(".diff-pillar-card", { opacity: 0, y: 45, scale: 0.96 });

        // Stage 6: Closing statement & CTA
        gsap.set([".diff-closing-statement", ".btn-difference-cta"], { opacity: 0, y: 30 });

        // Integrated ScrollTrigger Scrub Timeline
        const differenceTimeline = gsap.timeline({
            scrollTrigger: {
                trigger: ".section-difference",
                start: "top 78%",
                end: "center 48%",
                scrub: 1.1, // Smooth, cinematic scroll progression
                invalidateOnRefresh: true,
            }
        });

        // STAGE 1: Section Introduction (0.0 to 0.22)
        differenceTimeline
            .to(".diff-eyebrow-wrapper", { opacity: 1, y: 0, duration: 0.15, ease: "power2.out" }, 0)
            .to(".diff-headline", { opacity: 1, y: 0, duration: 0.2, ease: "power2.out" }, 0.04)
            .to(".diff-description", { opacity: 1, y: 0, duration: 0.2, ease: "power2.out" }, 0.08);

        // STAGE 2: Abstract Liquid Emergence (0.12 to 0.45)
        differenceTimeline
            .to(".liquid-base-wave", { scale: 1, opacity: 1, y: 0, duration: 0.35, ease: "sine.out" }, 0.12)
            .to(".liquid-core-body", { scale: 1, opacity: 1, y: 0, duration: 0.35, ease: "sine.out" }, 0.15)
            .to(".liquid-sculpt-wave", { scale: 1, opacity: 0.9, y: 0, duration: 0.35, ease: "sine.out" }, 0.18);

        // STAGE 3: Organic Flow of Translucent Glass Ribbons (0.22 to 0.58)
        differenceTimeline
            .to(".ribbon-upper", { scale: 1, opacity: 1, rotation: 0, duration: 0.35, ease: "power2.out" }, 0.22)
            .to(".ribbon-lower", { scale: 1, opacity: 1, rotation: 0, duration: 0.35, ease: "power2.out" }, 0.26);

        // STAGE 4: Dimensional Depth, Highlights & Droplets (0.32 to 0.65)
        differenceTimeline
            .to(".liquid-highlight", { opacity: 1, scale: 1, duration: 0.3, ease: "sine.out" }, 0.32)
            .to(".liquid-drop", { opacity: 1, scale: 1, duration: 0.25, stagger: 0.03, ease: "back.out(1.8)" }, 0.36)
            .to(".diff-floating-pill", { opacity: 1, y: 0, scale: 1, duration: 0.25, stagger: 0.08, ease: "power2.out" }, 0.42);

        // STAGE 5: Brand Pillars Sequential Reveal (0.45 to 0.78)
        differenceTimeline
            .to(".pillar-card-1", { opacity: 1, y: 0, scale: 1, duration: 0.24, ease: "power2.out" }, 0.45)
            .to(".pillar-card-2", { opacity: 1, y: 0, scale: 1, duration: 0.24, ease: "power2.out" }, 0.52)
            .to(".pillar-card-3", { opacity: 1, y: 0, scale: 1, duration: 0.24, ease: "power2.out" }, 0.59);

        // STAGE 6: Closing Message & CTA Button (0.62 to 0.9)
        differenceTimeline
            .to(".diff-closing-statement", { opacity: 1, y: 0, duration: 0.22, ease: "power2.out" }, 0.64)
            .to(".btn-difference-cta", { opacity: 1, y: 0, duration: 0.24, ease: "power2.out" }, 0.7);

        // STAGE 7: Settle into complete harmonious composition
    }

    // ==========================================
    // SECTION 4: THE ABC+ DAILY RITUAL
    // A Simpler Way to Everyday Wellness
    // Matching Reference Composition (Image 1)
    // ==========================================
    const ritualSection = document.querySelector(".section-ritual");
    if (ritualSection) {
        const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

        if (prefersReducedMotion) {
            gsap.set([
                ".ritual-eyebrow-container",
                ".ritual-hero-title",
                ".ritual-hero-sub",
                ".btn-discover-ritual"
            ], { opacity: 1, y: 0, clearProps: "all" });
        } else {
            gsap.set([
                ".ritual-eyebrow-container",
                ".ritual-hero-title",
                ".ritual-hero-sub",
                ".btn-discover-ritual"
            ], { opacity: 0, y: 24 });

            gsap.set(".ritual-bg-photo", { scale: 1.05 });

            ScrollTrigger.create({
                trigger: ".section-ritual",
                start: "top 75%",
                once: true,
                onEnter: () => {
                    const ritualTl = gsap.timeline({ defaults: { ease: "power3.out" } });

                    ritualTl
                        .to(".ritual-bg-photo", {
                            scale: 1,
                            duration: 1.4,
                            ease: "power2.out"
                        })
                        .to(".ritual-eyebrow-container", {
                            opacity: 1,
                            y: 0,
                            duration: 0.75
                        }, "-=1.1")
                        .to(".ritual-hero-title", {
                            opacity: 1,
                            y: 0,
                            duration: 0.85
                        }, "-=0.6")
                        .to(".ritual-hero-sub", {
                            opacity: 1,
                            y: 0,
                            duration: 0.8
                        }, "-=0.65")
                        .to(".btn-discover-ritual", {
                            opacity: 1,
                            y: 0,
                            duration: 0.75,
                            clearProps: "transform"
                        }, "-=0.6");
                }
            });
        }
    }

    // ==========================================
    // SECTION 5: PHILOSOPHY (WELLNESS IN NATURAL HARMONY)
    // 3D Parametric Elliptical Ingredient Orbit
    // Matching Reference Image 2 Art Direction
    // ==========================================
    const philosophySection = document.querySelector(".section-philosophy");
    if (philosophySection) {
        const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        const orbitStage = document.getElementById("philoOrbitStage");
        const ingredientElements = document.querySelectorAll(".orbit-ingredient");
        const philoCards = document.querySelectorAll(".philo-card");

        // Initial setup for entrance sequence
        if (!prefersReducedMotion) {
            gsap.set(".philo-header-block > *", { opacity: 0, y: 22 });
            gsap.set(".philo-centerpiece", { opacity: 0, scale: 0.92, y: 15 });
            gsap.set(".philo-orbit-svg", { opacity: 0, scale: 0.96 });
            gsap.set(ingredientElements, { opacity: 0, scale: 0.8 });
            gsap.set(philoCards, { opacity: 0, y: 30 });
        }

        // 1. Scroll-Triggered Entrance Sequence
        ScrollTrigger.create({
            trigger: ".section-philosophy",
            start: "top 78%",
            once: true,
            onEnter: () => {
                if (prefersReducedMotion) {
                    gsap.set([
                        ".philo-header-block > *",
                        ".philo-centerpiece",
                        ".philo-orbit-svg",
                        ingredientElements,
                        philoCards
                    ], { opacity: 1, y: 0, scale: 1, clearProps: "all" });
                    return;
                }

                const entranceTl = gsap.timeline({ defaults: { ease: "power3.out" } });

                entranceTl
                    // Header text stagger
                    .to(".philo-header-block > *", {
                        opacity: 1,
                        y: 0,
                        duration: 0.8,
                        stagger: 0.14
                    })
                    // Centerpiece bowl reveal
                    .to(".philo-centerpiece", {
                        opacity: 1,
                        scale: 1,
                        y: 0,
                        duration: 1.1,
                        ease: "power2.out"
                    }, "-=0.5")
                    // Luminous orbit ring & light nodes
                    .to(".philo-orbit-svg", {
                        opacity: 1,
                        scale: 1,
                        duration: 1.0,
                        ease: "power2.out"
                    }, "-=0.8")
                    // Ingredients fade-in
                    .to(ingredientElements, {
                        opacity: 1,
                        scale: 1,
                        duration: 0.9,
                        stagger: 0.08,
                        ease: "back.out(1.2)"
                    }, "-=0.7")
                    // Philosophy cards entrance
                    .to(philoCards, {
                        opacity: 1,
                        y: 0,
                        duration: 0.75,
                        stagger: 0.12,
                        clearProps: "transform"
                    }, "-=0.6");
            }
        });

        // 2. Continuous 3D Elliptical Orbit Mathematics
        // Initial angular offsets corresponding directly to Reference Image 2 positions
        // Amla (top-left), Beetroot (mid-left), Ginger (top-right), Carrot (mid-right), Lemon (lower-right)
        const initialOffsets = [3.55, 2.58, 5.62, 0.42, 1.48];
        const tiltDeg = -6; // Match -6deg rotation of the SVG ellipse
        const tiltRad = tiltDeg * (Math.PI / 180);
        const cosTilt = Math.cos(tiltRad);
        const sinTilt = Math.sin(tiltRad);

        const orbitState = { angle: 0 };

        function updateOrbitPositions() {
            if (!orbitStage) return;

            const stageRect = orbitStage.getBoundingClientRect();
            const width = stageRect.width || 960;
            const height = stageRect.height || 480;

            // Responsive orbital radii based on stage dimensions
            const rx = Math.min(width * 0.42, 420);
            const ry = Math.min(height * 0.28, 120);

            ingredientElements.forEach((el, index) => {
                const currentAngle = (orbitState.angle + initialOffsets[index]) % (2 * Math.PI);

                // Parametric ellipse
                const rawX = rx * Math.cos(currentAngle);
                const rawY = ry * Math.sin(currentAngle);

                // Apply orbit tilt transformation
                const x = rawX * cosTilt - rawY * sinTilt;
                const y = rawX * sinTilt + rawY * cosTilt;

                // Depth value: sin(currentAngle) determines front/back perspective
                // depth > 0 is foreground (passes in front of bowl at z-index 20)
                // depth < 0 is background (passes behind bowl at z-index 5)
                const depth = Math.sin(currentAngle);
                const scale = 1.0 + depth * 0.12;
                const zIndex = depth >= 0 ? 20 : 5;
                const opacity = 0.94 + depth * 0.06;

                gsap.set(el, {
                    x: x,
                    y: y,
                    scale: scale,
                    zIndex: zIndex,
                    opacity: opacity
                });
            });
        }

        // Initialize positions immediately
        updateOrbitPositions();

        // Responsive resize listener for orbital radii
        window.addEventListener("resize", updateOrbitPositions, { passive: true });

        // Continuous GSAP Orbit Tween
        let orbitTween = null;
        if (!prefersReducedMotion) {
            orbitTween = gsap.to(orbitState, {
                angle: 2 * Math.PI,
                duration: 42, // Slow, hypnotic, elegant revolution
                repeat: -1,
                ease: "none",
                onUpdate: updateOrbitPositions
            });

            // Pause animation when out of viewport for 0% idle CPU overhead
            ScrollTrigger.create({
                trigger: ".section-philosophy",
                start: "top bottom",
                end: "bottom top",
                onEnter: () => orbitTween && orbitTween.play(),
                onLeave: () => orbitTween && orbitTween.pause(),
                onEnterBack: () => orbitTween && orbitTween.play(),
                onLeaveBack: () => orbitTween && orbitTween.pause()
            });

            // Tactile deceleration on hover
            if (orbitStage) {
                orbitStage.addEventListener("mouseenter", () => {
                    if (orbitTween) gsap.to(orbitTween, { timeScale: 0.22, duration: 0.6 });
                });
                orbitStage.addEventListener("mouseleave", () => {
                    if (orbitTween) gsap.to(orbitTween, { timeScale: 1.0, duration: 0.8 });
                });
            }
        }
    }


    // Section 6 (Brand Manifesto) matches Section 6.5 editorial layout with smooth native scroll
    // ========================================================
    // SECTION 7: FEATURED PRODUCT SHOWCASE (GSAP ScrollTrigger)
    // ========================================================
    // SECTION 7: FEATURED PRODUCT SHOWCASE (BOTANICAL UPGRADE)
    // ========================================================
    const showcaseSection = document.querySelector(".section-showcase");
    if (showcaseSection) {
        // Initial setup for Product Frame & Narrative
        gsap.set(".showcase-visual-frame", {
            opacity: 0,
            scale: 0.96,
            y: 40
        });

        gsap.set([".showcase-eyebrow-wrap", ".showcase-headline-line", ".showcase-description", ".showcase-specs-row"], {
            opacity: 0,
            y: 25
        });

        gsap.set(".ingredient-row", {
            opacity: 0,
            x: 20
        });

        gsap.set(".showcase-cta-wrap", {
            opacity: 0,
            y: 20
        });

        // Entrance timeline triggered on viewport approach
        ScrollTrigger.create({
            trigger: ".section-showcase",
            start: "top 78%",
            once: true,
            onEnter: () => {
                const showcaseTl = gsap.timeline();
                showcaseTl
                    .to(".showcase-visual-frame", {
                        opacity: 1,
                        scale: 1,
                        y: 0,
                        duration: 0.8,
                        ease: "power2.out"
                    }, 0)
                    .to([".showcase-eyebrow-wrap", ".showcase-headline-line", ".showcase-description", ".showcase-specs-row"], {
                        opacity: 1,
                        y: 0,
                        stagger: 0.08,
                        duration: 0.6,
                        ease: "power2.out"
                    }, 0.15)
                    .to(".ingredient-row", {
                        opacity: 1,
                        x: 0,
                        stagger: 0.09,
                        duration: 0.5,
                        ease: "power2.out"
                    }, 0.35)
                    .to(".showcase-cta-wrap", {
                        opacity: 1,
                        y: 0,
                        duration: 0.5,
                        ease: "power2.out"
                    }, 0.65);
            }
        });

        // Subtle parallax image drift on scroll through the section
        gsap.to(".showcase-visual-img", {
            y: -15,
            ease: "none",
            scrollTrigger: {
                trigger: ".section-showcase",
                start: "top bottom",
                end: "bottom top",
                scrub: 1.2
            }
        });
    }

    /* ==========================================================================
       SECTION 8 — HOW TO ENJOY ABC+
       Continuous Animated Ritual Experience
       ========================================================================== */
    const enjoySection = document.querySelector(".section-enjoy");
    if (enjoySection) {
        // --- 1. Continuous Animations Setup ---
        let enjoyAnimationActive = false;
        let enjoyAnimFrameId = null;

        // Stage 1: Powder Granular Stream Canvas
        const powderCanvas = enjoySection.querySelector(".canvas-powder");
        const powderCtx = powderCanvas ? powderCanvas.getContext("2d") : null;

        // Stage 2: Pouring Water & Ripples Canvas
        const waterCanvas = enjoySection.querySelector(".canvas-water");
        const waterCtx = waterCanvas ? waterCanvas.getContext("2d") : null;

        // Stage 3: Effervescent Micro-Bubbles Canvas
        const bubblesCanvas = enjoySection.querySelector(".canvas-bubbles");
        const bubblesCtx = bubblesCanvas ? bubblesCanvas.getContext("2d") : null;

        // Color palettes
        const powderColors = [
            "#B83924", "#C84B31", "#D95338", "#E26A45", "#A12D1C", 
            "#D24D28", "#E66F43", "#8C2218", "#E05A3A"
        ];

        // 1. Powder particle state
        const powderParticles = [];
        const maxPowder = 90;

        function createPowderParticle() {
            return {
                x: 168 + (Math.random() - 0.5) * 12,
                y: 138 + Math.random() * 5,
                vx: (Math.random() - 0.5) * 0.25,
                vy: 0.22 + Math.random() * 0.20, // Ultra-slow, gentle floating drift
                radius: 1.0 + Math.random() * 1.5,
                color: powderColors[Math.floor(Math.random() * powderColors.length)],
                alpha: 0.75 + Math.random() * 0.25,
                life: 0,
                maxLife: 260 + Math.random() * 80
            };
        }

        // 2. Water Stream, Ripples & Swirling Clouds state
        let waterTime = 0;
        const waterRipples = [];
        const swirlingClouds = [];

        function createRipple() {
            return {
                x: 170,
                y: 118,
                radiusX: 6,
                radiusY: 2.5,
                maxRadius: 40 + Math.random() * 16,
                alpha: 0.50,
                growth: 0.35 + Math.random() * 0.25
            };
        }

        function createCloudParticle() {
            return {
                x: 168 + (Math.random() - 0.5) * 16,
                y: 130 + Math.random() * 12,
                vx: (Math.random() - 0.5) * 0.35,
                vy: 0.10 + Math.random() * 0.14, // Ultra-slow gentle liquid dispersion
                radius: 4 + Math.random() * 6,
                maxRadius: 26 + Math.random() * 14,
                color: powderColors[Math.floor(Math.random() * powderColors.length)],
                alpha: 0.35 + Math.random() * 0.25,
                swirlSpeed: (Math.random() - 0.5) * 0.02
            };
        }

        // 3. Effervescent Bubbles state
        const bubbles = [];
        const maxBubbles = 65;

        function createBubble() {
            return {
                x: 105 + Math.random() * 130, // within the glass body
                y: 275 + Math.random() * 15,  // bottom of glass
                radius: 0.8 + Math.random() * 2.0,
                vy: - (0.24 + Math.random() * 0.32), // Gentle, slow champagne effervescence
                baseX: 0,
                wobbleSpeed: 0.015 + Math.random() * 0.02,
                wobbleAmp: 0.5 + Math.random() * 0.9,
                wobbleOffset: Math.random() * Math.PI * 2,
                alpha: 0.4 + Math.random() * 0.5,
                sparkle: Math.random() > 0.65
            };
        }

        // Pre-populate bubbles
        for (let i = 0; i < maxBubbles; i++) {
            const b = createBubble();
            b.y = 100 + Math.random() * 180;
            b.baseX = b.x;
            bubbles.push(b);
        }

        // Main Animation Loop
        function renderEnjoyVisuals() {
            if (!enjoyAnimationActive) return;

            // --- A. Render Powder Stream ---
            if (powderCtx) {
                powderCtx.clearRect(0, 0, 340, 340);

                // Emit new powder particles at a gentle, slow cadence
                if (powderParticles.length < maxPowder && Math.random() < 0.55) {
                    powderParticles.push(createPowderParticle());
                }

                // Draw subtle stream haze/core
                const streamGrad = powderCtx.createLinearGradient(168, 138, 168, 275);
                streamGrad.addColorStop(0, "rgba(217, 83, 56, 0.45)");
                streamGrad.addColorStop(0.5, "rgba(184, 57, 36, 0.35)");
                streamGrad.addColorStop(1, "rgba(161, 45, 28, 0.2)");

                powderCtx.beginPath();
                powderCtx.moveTo(164, 142);
                powderCtx.lineTo(174, 142);
                powderCtx.lineTo(178, 270);
                powderCtx.lineTo(160, 270);
                powderCtx.closePath();
                powderCtx.fillStyle = streamGrad;
                powderCtx.filter = "blur(3px)";
                powderCtx.fill();
                powderCtx.filter = "none";

                // Draw individual falling grains with ultra-gentle, slow-motion descent
                for (let i = powderParticles.length - 1; i >= 0; i--) {
                    const p = powderParticles[i];
                    p.x += p.vx + Math.sin(p.life * 0.04) * 0.12; // soft botanical flutter
                    p.y += p.vy;
                    p.vy = Math.min(p.vy + 0.004, 0.65); // Ultra-gentle gravity capped at 0.65
                    p.life++;

                    powderCtx.beginPath();
                    powderCtx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                    powderCtx.fillStyle = p.color;
                    powderCtx.globalAlpha = p.alpha;
                    powderCtx.fill();

                    if (p.y > 275 || p.life > p.maxLife) {
                        powderParticles.splice(i, 1);
                    }
                }
                powderCtx.globalAlpha = 1;

                // Subtle accumulating powder mound glow at base
                powderCtx.beginPath();
                powderCtx.ellipse(168, 272, 38, 9, 0, 0, Math.PI * 2);
                powderCtx.fillStyle = "rgba(184, 57, 36, 0.25)";
                powderCtx.filter = "blur(4px)";
                powderCtx.fill();
                powderCtx.filter = "none";
            }

            // --- B. Render Water Stream & Swirl ---
            if (waterCtx) {
                waterCtx.clearRect(0, 0, 340, 340);
                waterTime += 0.012; // Slow, calm water wave progression

                // 1. Inflowing Crystal Water Stream from top (slow, calm wave)
                const streamWaver = Math.sin(waterTime * 1.5) * 0.9;
                const waterStreamGrad = waterCtx.createLinearGradient(166, 0, 174, 120);
                waterStreamGrad.addColorStop(0, "rgba(255, 255, 255, 0.75)");
                waterStreamGrad.addColorStop(0.5, "rgba(225, 245, 255, 0.65)");
                waterStreamGrad.addColorStop(1, "rgba(255, 255, 255, 0.85)");

                waterCtx.beginPath();
                waterCtx.moveTo(165 + streamWaver, 0);
                waterCtx.bezierCurveTo(166, 40, 164 + streamWaver, 80, 166, 118);
                waterCtx.lineTo(174, 118);
                waterCtx.bezierCurveTo(174 + streamWaver, 80, 172, 40, 173 + streamWaver, 0);
                waterCtx.closePath();
                waterCtx.fillStyle = waterStreamGrad;
                waterCtx.fill();

                // Stream center highlight
                waterCtx.beginPath();
                waterCtx.moveTo(169 + streamWaver * 0.7, 0);
                waterCtx.lineTo(170 + streamWaver * 0.3, 116);
                waterCtx.strokeStyle = "rgba(255, 255, 255, 0.9)";
                waterCtx.lineWidth = 1.5;
                waterCtx.stroke();

                // 2. Ripple rings on liquid surface (calm, slow expansion)
                if (Math.random() < 0.035) {
                    waterRipples.push(createRipple());
                }

                for (let i = waterRipples.length - 1; i >= 0; i--) {
                    const r = waterRipples[i];
                    r.radiusX += r.growth * 0.35;
                    r.radiusY += r.growth * 0.12;
                    r.alpha -= 0.0035;

                    if (r.alpha <= 0 || r.radiusX > r.maxRadius) {
                        waterRipples.splice(i, 1);
                        continue;
                    }

                    waterCtx.beginPath();
                    waterCtx.ellipse(r.x, r.y, r.radiusX, r.radiusY, 0, 0, Math.PI * 2);
                    waterCtx.strokeStyle = `rgba(255, 255, 255, ${r.alpha.toFixed(2)})`;
                    waterCtx.lineWidth = 1.2;
                    waterCtx.stroke();
                }

                // 3. Swirling powder clouds in water (gentle, slow dissolution)
                if (swirlingClouds.length < 18 && Math.random() < 0.12) {
                    swirlingClouds.push(createCloudParticle());
                }

                for (let i = swirlingClouds.length - 1; i >= 0; i--) {
                    const c = swirlingClouds[i];
                    c.x += c.vx;
                    c.y += c.vy;
                    c.radius += 0.08;
                    c.alpha -= 0.002;

                    if (c.alpha <= 0 || c.radius > c.maxRadius || c.y > 270) {
                        swirlingClouds.splice(i, 1);
                        continue;
                    }

                    waterCtx.beginPath();
                    waterCtx.arc(c.x, c.y, c.radius, 0, Math.PI * 2);
                    waterCtx.fillStyle = c.color;
                    waterCtx.globalAlpha = c.alpha * 0.55;
                    waterCtx.filter = "blur(4px)";
                    waterCtx.fill();
                    waterCtx.filter = "none";
                }
                waterCtx.globalAlpha = 1;
            }

            // --- C. Render Rising Effervescent Micro-Bubbles ---
            if (bubblesCtx) {
                bubblesCtx.clearRect(0, 0, 340, 340);

                for (let i = 0; i < bubbles.length; i++) {
                    const b = bubbles[i];
                    b.y += b.vy; // Gentle, slow upward drift
                    const wobble = Math.sin(waterTime * 1.4 + b.wobbleOffset) * b.wobbleAmp;
                    const curX = b.baseX + wobble;

                    // Bubble body
                    bubblesCtx.beginPath();
                    bubblesCtx.arc(curX, b.y, b.radius, 0, Math.PI * 2);
                    bubblesCtx.fillStyle = `rgba(255, 255, 255, ${b.alpha.toFixed(2)})`;
                    bubblesCtx.fill();

                    // Subtle golden/amber highlight
                    if (b.sparkle && b.radius > 1.3) {
                        bubblesCtx.beginPath();
                        bubblesCtx.arc(curX - b.radius * 0.3, b.y - b.radius * 0.3, b.radius * 0.45, 0, Math.PI * 2);
                        bubblesCtx.fillStyle = "rgba(255, 245, 220, 0.85)";
                        bubblesCtx.fill();
                    }

                    // Reset when reaching top liquid foam level (y ≈ 96)
                    if (b.y < 96) {
                        b.y = 270 + Math.random() * 15;
                        b.baseX = 105 + Math.random() * 130;
                    }
                }
            }

            enjoyAnimFrameId = requestAnimationFrame(renderEnjoyVisuals);
        }

        // --- 2. GSAP Scroll Scrub Sequence: Reveal Items One by One ---
        const stageCardScoop = enjoySection.querySelector(".stage-card-scoop");
        const stageCardMix = enjoySection.querySelector(".stage-card-mix");
        const stageCardEnjoy = enjoySection.querySelector(".stage-card-enjoy");
        const stageConnectors = enjoySection.querySelectorAll(".stage-connector");

        gsap.set([".enjoy-eyebrow-wrap", ".enjoy-headline", ".enjoy-subtitle"], {
            opacity: 0,
            y: 35
        });

        gsap.set([stageCardScoop, stageCardMix, stageCardEnjoy], {
            opacity: 0,
            y: 65,
            scale: 0.94
        });

        gsap.set(stageConnectors, {
            opacity: 0,
            scale: 0.6
        });

        gsap.set(".stage-ring-active", {
            strokeDasharray: 1081,
            strokeDashoffset: 1081
        });

        gsap.set(".enjoy-footer-hint", {
            opacity: 0,
            y: 25
        });

        // Scrubbed ScrollTrigger Timeline: Items move up ONE BY ONE as user scrolls
        const enjoyScrollTimeline = gsap.timeline({
            scrollTrigger: {
                trigger: ".section-enjoy",
                start: "top 78%",
                end: "center 42%",
                scrub: 1.1,
                invalidateOnRefresh: true,
            }
        });

        enjoyScrollTimeline
            // Step 0: Header elements
            .to(".enjoy-eyebrow-wrap", { opacity: 1, y: 0, duration: 0.12, ease: "power2.out" }, 0)
            .to(".enjoy-headline", { opacity: 1, y: 0, duration: 0.18, ease: "power2.out" }, 0.04)
            .to(".enjoy-subtitle", { opacity: 1, y: 0, duration: 0.16, ease: "power2.out" }, 0.08)

            // Step 1: SCOOP moves up first
            .to(stageCardScoop, { opacity: 1, y: 0, scale: 1, duration: 0.28, ease: "power2.out" }, 0.14)
            .to(".stage-card-scoop .stage-ring-active", { strokeDashoffset: 0, duration: 0.28, ease: "power1.inOut" }, 0.16)

            // Connector 1 reveals
            .to(stageConnectors[0], { opacity: 0.8, scale: 1, duration: 0.16, ease: "back.out(1.5)" }, 0.32)

            // Step 2: MIX moves up second
            .to(stageCardMix, { opacity: 1, y: 0, scale: 1, duration: 0.28, ease: "power2.out" }, 0.40)
            .to(".stage-card-mix .stage-ring-active", { strokeDashoffset: 0, duration: 0.28, ease: "power1.inOut" }, 0.42)

            // Connector 2 reveals
            .to(stageConnectors[1], { opacity: 0.8, scale: 1, duration: 0.16, ease: "back.out(1.5)" }, 0.58)

            // Step 3: ENJOY moves up third
            .to(stageCardEnjoy, { opacity: 1, y: 0, scale: 1, duration: 0.28, ease: "power2.out" }, 0.66)
            .to(".stage-card-enjoy .stage-ring-active", { strokeDashoffset: 0, duration: 0.28, ease: "power1.inOut" }, 0.68)

            // Footer hint reveals
            .to(".enjoy-footer-hint", { opacity: 1, y: 0, duration: 0.2, ease: "power2.out" }, 0.84);

        // Viewport monitoring: Pause continuous loops when section is offscreen
        ScrollTrigger.create({
            trigger: ".section-enjoy",
            start: "top bottom",
            end: "bottom top",
            onEnter: () => {
                if (!enjoyAnimationActive) {
                    enjoyAnimationActive = true;
                    enjoyAnimFrameId = requestAnimationFrame(renderEnjoyVisuals);
                }
            },
            onEnterBack: () => {
                if (!enjoyAnimationActive) {
                    enjoyAnimationActive = true;
                    enjoyAnimFrameId = requestAnimationFrame(renderEnjoyVisuals);
                }
            },
            onLeave: () => {
                enjoyAnimationActive = false;
                if (enjoyAnimFrameId) cancelAnimationFrame(enjoyAnimFrameId);
            },
            onLeaveBack: () => {
                enjoyAnimationActive = false;
                if (enjoyAnimFrameId) cancelAnimationFrame(enjoyAnimFrameId);
            }
        });
    }

    // =========================================================================
    // SECTION 9 — BOTANICAL EXPLOSION (NATURE HEALS)
    // =========================================================================
    const explosionSection = document.querySelector(".section-explosion");
    if (explosionSection) {
        const particlesCanvas = explosionSection.querySelector(".explosion-particles-canvas");
        const ctx = particlesCanvas ? particlesCanvas.getContext("2d") : null;

        // Particle System
        const particleCount = 55;
        const colors = [
            "rgba(184, 57, 36, 0.75)",  // Beetroot ruby
            "rgba(226, 106, 69, 0.75)", // Carrot orange
            "rgba(61, 120, 83, 0.7)",   // Herbal leaf green
            "rgba(224, 169, 72, 0.8)",  // Golden turmeric/lemon amber
            "rgba(240, 218, 165, 0.85)" // Warm ivory pollen
        ];

        const particles = [];
        for (let i = 0; i < particleCount; i++) {
            const angle = Math.random() * Math.PI * 2;
            const maxDist = 80 + Math.random() * 260;
            const size = 2 + Math.random() * 4.5;
            const color = colors[Math.floor(Math.random() * colors.length)];
            const curve = (Math.random() - 0.5) * 0.8;
            particles.push({ angle, maxDist, size, color, curve });
        }

        const particleProgress = { value: 0 };

        function drawParticles(p) {
            if (!ctx || !particlesCanvas) return;
            const width = particlesCanvas.width;
            const height = particlesCanvas.height;
            const cx = width / 2;
            const cy = height / 2;

            ctx.clearRect(0, 0, width, height);
            if (p <= 0.02) return;

            // Ease explosion expansion
            const expansion = Math.pow(p, 0.75);
            const globalAlpha = p < 0.8 ? p / 0.8 : (1 - (p - 0.8) / 0.2);

            for (let i = 0; i < particles.length; i++) {
                const pt = particles[i];
                const currentDist = pt.maxDist * expansion;
                const currentAngle = pt.angle + pt.curve * expansion;
                const x = cx + Math.cos(currentAngle) * currentDist;
                const y = cy + Math.sin(currentAngle) * currentDist;

                ctx.save();
                ctx.globalAlpha = Math.max(0, Math.min(1, globalAlpha));
                ctx.fillStyle = pt.color;
                ctx.beginPath();
                ctx.arc(x, y, pt.size * (0.8 + 0.4 * (1 - p)), 0, Math.PI * 2);
                ctx.fill();
                ctx.restore();
            }
        }

        const isMobile = window.innerWidth <= 768;
        const sf = isMobile ? 0.52 : 1.0;

        // Set initial states for ingredients
        gsap.set(".exp-item-amla", { x: -30 * sf, y: -20 * sf, scale: 0.45, rotation: 0, opacity: 0.6 });
        gsap.set(".exp-item-amla-mini", { x: -20 * sf, y: -30 * sf, scale: 0.35, rotation: 0, opacity: 0.5 });
        gsap.set(".exp-item-beetroot", { x: -20 * sf, y: 10 * sf, scale: 0.5, rotation: 0, opacity: 0.7 });
        gsap.set(".exp-item-ginger", { x: -10 * sf, y: 20 * sf, scale: 0.45, rotation: 0, opacity: 0.65 });
        gsap.set(".exp-item-carrot", { x: 20 * sf, y: 20 * sf, scale: 0.45, rotation: 0, opacity: 0.65 });
        gsap.set(".exp-item-lemon-slice", { x: 25 * sf, y: -20 * sf, scale: 0.45, rotation: 0, opacity: 0.65 });
        gsap.set(".exp-item-lemon-whole", { x: 30 * sf, y: 0, scale: 0.4, rotation: 0, opacity: 0.6 });
        gsap.set(".exp-item-leaf-1", { x: -15 * sf, y: -15 * sf, scale: 0.3, rotation: 0, opacity: 0.5 });
        gsap.set(".exp-item-leaf-2", { x: 15 * sf, y: -25 * sf, scale: 0.3, rotation: 0, opacity: 0.5 });
        gsap.set(".exp-item-leaf-3", { x: 20 * sf, y: 15 * sf, scale: 0.3, rotation: 0, opacity: 0.5 });

        // Build Master ScrollTrigger Scrub Timeline
        const explosionTimeline = gsap.timeline({
            scrollTrigger: {
                trigger: explosionSection,
                start: "top 75%",
                end: "bottom 30%",
                scrub: 1.2,
                invalidateOnRefresh: true,
            }
        });

        // 1. Particle progression
        explosionTimeline.to(particleProgress, {
            value: 1,
            ease: "none",
            duration: 1,
            onUpdate: () => drawParticles(particleProgress.value)
        }, 0);

        // 2. Landscape subtle parallax
        explosionTimeline.fromTo(".landscape-hills-img", 
            { y: 35, scale: 1.08 },
            { y: -15, scale: 1.0, ease: "none", duration: 1 },
            0
        );

        // 3. Typography subtle expansion / drift
        explosionTimeline.fromTo(".typo-nature",
            { x: -25, opacity: 0.8 },
            { x: 0, opacity: 1, ease: "power1.out", duration: 0.85 },
            0
        );
        explosionTimeline.fromTo(".typo-heals",
            { x: 25, opacity: 0.8 },
            { x: 0, opacity: 1, ease: "power1.out", duration: 0.85 },
            0
        );

        // 4. Botanical Explosion outward trajectories
        explosionTimeline
            // Amla main (top-left)
            .to(".exp-item-amla", {
                x: -235 * sf,
                y: -145 * sf,
                rotation: -35,
                scale: isMobile ? 0.95 : 1.15,
                opacity: 1,
                ease: "power2.out",
                duration: 0.95
            }, 0.05)
            // Amla mini (higher left)
            .to(".exp-item-amla-mini", {
                x: -140 * sf,
                y: -225 * sf,
                rotation: 42,
                scale: isMobile ? 0.6 : 0.75,
                opacity: 0.95,
                ease: "power2.out",
                duration: 0.92
            }, 0.06)
            // Beetroot (mid-left power anchor)
            .to(".exp-item-beetroot", {
                x: -175 * sf,
                y: 65 * sf,
                rotation: -24,
                scale: isMobile ? 0.95 : 1.22,
                opacity: 1,
                ease: "power2.out",
                duration: 0.98
            }, 0.03)
            // Ginger (lower-center left)
            .to(".exp-item-ginger", {
                x: -60 * sf,
                y: 165 * sf,
                rotation: 28,
                scale: isMobile ? 0.88 : 1.08,
                opacity: 1,
                ease: "power2.out",
                duration: 0.94
            }, 0.05)
            // Carrot (diagonally down-right dynamic hero)
            .to(".exp-item-carrot", {
                x: 215 * sf,
                y: 145 * sf,
                rotation: 45,
                scale: isMobile ? 0.95 : 1.25,
                opacity: 1,
                ease: "power2.out",
                duration: 0.96
            }, 0.04)
            // Lemon Slice (upper-right vibrant burst)
            .to(".exp-item-lemon-slice", {
                x: 185 * sf,
                y: -135 * sf,
                rotation: 55,
                scale: isMobile ? 0.9 : 1.12,
                opacity: 1,
                ease: "power2.out",
                duration: 0.95
            }, 0.05)
            // Whole Lemon (mid-right depth)
            .to(".exp-item-lemon-whole", {
                x: 245 * sf,
                y: 10 * sf,
                rotation: -18,
                scale: isMobile ? 0.8 : 0.95,
                opacity: 1,
                ease: "power2.out",
                duration: 0.92
            }, 0.07)
            // Botanical Leaves (scattered)
            .to(".exp-item-leaf-1", {
                x: -210 * sf,
                y: -200 * sf,
                rotation: -55,
                scale: isMobile ? 0.8 : 1.0,
                opacity: 0.95,
                ease: "power2.out",
                duration: 0.9
            }, 0.08)
            .to(".exp-item-leaf-2", {
                x: 120 * sf,
                y: -210 * sf,
                rotation: 65,
                scale: isMobile ? 0.7 : 0.85,
                opacity: 0.9,
                ease: "power2.out",
                duration: 0.9
            }, 0.09)
            .to(".exp-item-leaf-3", {
                x: 220 * sf,
                y: 190 * sf,
                rotation: 25,
                scale: isMobile ? 0.75 : 0.92,
                opacity: 0.9,
                ease: "power2.out",
                duration: 0.9
            }, 0.08);
    }

    // =========================================================================
    // SECTION 10 — THE NUTRONYX EXPERIENCE (WHAT OUR CUSTOMERS SAY)
    // =========================================================================
    const experienceSection = document.querySelector(".section-experience");
    if (experienceSection) {
        const cards = Array.from(experienceSection.querySelectorAll(".experience-card"));
        const dots = Array.from(experienceSection.querySelectorAll(".carousel-dots .dot"));
        const prevBtn = experienceSection.querySelector(".experience-controls .btn-prev");
        const nextBtn = experienceSection.querySelector(".experience-controls .btn-next");
        const track = experienceSection.querySelector("#experienceCarouselTrack");

        let currentIndex = 1; // Start with Ananya R. (index 1) in the center
        let isTransitioning = false;

        function updateCarousel(newIndex, direction = 1) {
            if (isTransitioning || cards.length === 0) return;
            isTransitioning = true;

            const total = cards.length;
            const targetIndex = (newIndex + total) % total;
            const prevIdx = (targetIndex - 1 + total) % total;
            const nextIdx = (targetIndex + 1) % total;

            // Update card states
            cards.forEach((card, idx) => {
                card.classList.remove("is-active", "is-prev", "is-next");
                if (idx === targetIndex) {
                    card.classList.add("is-active");
                    card.setAttribute("aria-hidden", "false");
                    card.setAttribute("tabindex", "0");
                } else if (idx === prevIdx) {
                    card.classList.add("is-prev");
                    card.setAttribute("aria-hidden", "true");
                    card.setAttribute("tabindex", "-1");
                } else if (idx === nextIdx) {
                    card.classList.add("is-next");
                    card.setAttribute("aria-hidden", "true");
                    card.setAttribute("tabindex", "-1");
                }
            });

            // Re-order DOM elements in track so layout remains visually centered: [prev, active, next]
            if (track) {
                track.appendChild(cards[prevIdx]);
                track.appendChild(cards[targetIndex]);
                track.appendChild(cards[nextIdx]);
            }

            // Animate transition with GSAP if available
            if (typeof gsap !== "undefined") {
                gsap.fromTo(cards[targetIndex], 
                    { scale: 0.94, opacity: 0.8 }, 
                    { scale: 1.0, opacity: 1, duration: 0.45, ease: "power2.out" }
                );
            }

            // Update pagination dots
            dots.forEach((dot, idx) => {
                if (idx === targetIndex) {
                    dot.classList.add("is-active");
                    dot.setAttribute("aria-selected", "true");
                } else {
                    dot.classList.remove("is-active");
                    dot.setAttribute("aria-selected", "false");
                }
            });

            currentIndex = targetIndex;
            setTimeout(() => {
                isTransitioning = false;
            }, 300);
        }

        // Initialize state
        updateCarousel(1);

        // Prev & Next Buttons
        if (prevBtn) {
            prevBtn.addEventListener("click", () => {
                updateCarousel(currentIndex - 1, -1);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener("click", () => {
                updateCarousel(currentIndex + 1, 1);
            });
        }

        // Dot Navigation
        dots.forEach((dot) => {
            dot.addEventListener("click", () => {
                const targetIdx = parseInt(dot.getAttribute("data-index"), 10);
                if (!isNaN(targetIdx) && targetIdx !== currentIndex) {
                    updateCarousel(targetIdx, targetIdx > currentIndex ? 1 : -1);
                }
            });
        });

        // Click side card to navigate directly to it
        cards.forEach((card, idx) => {
            card.addEventListener("click", () => {
                if (!card.classList.contains("is-active")) {
                    const cardIdx = parseInt(card.getAttribute("data-slide"), 10);
                    if (!isNaN(cardIdx)) {
                        updateCarousel(cardIdx);
                    }
                }
            });
        });

        // Keyboard Navigation (ArrowLeft / ArrowRight)
        experienceSection.addEventListener("keydown", (e) => {
            if (e.key === "ArrowLeft") {
                e.preventDefault();
                updateCarousel(currentIndex - 1, -1);
            } else if (e.key === "ArrowRight") {
                e.preventDefault();
                updateCarousel(currentIndex + 1, 1);
            }
        });

        // Touch Swipe Support
        let touchStartX = 0;
        let touchEndX = 0;

        if (track) {
            track.addEventListener("touchstart", (e) => {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            track.addEventListener("touchend", (e) => {
                touchEndX = e.changedTouches[0].screenX;
                const diff = touchStartX - touchEndX;
                if (Math.abs(diff) > 45) {
                    if (diff > 0) {
                        updateCarousel(currentIndex + 1, 1);
                    } else {
                        updateCarousel(currentIndex - 1, -1);
                    }
                }
            }, { passive: true });
        }

        // Section Entrance Timeline with ScrollTrigger
        if (typeof gsap !== "undefined" && typeof ScrollTrigger !== "undefined") {
            const expTimeline = gsap.timeline({
                scrollTrigger: {
                    trigger: experienceSection,
                    start: "top 76%",
                    once: true
                }
            });

            expTimeline
                .fromTo(".experience-eyebrow-wrapper", 
                    { y: 18, opacity: 0 },
                    { y: 0, opacity: 1, duration: 0.6, ease: "power2.out" }
                )
                .fromTo(".experience-headline", 
                    { y: 24, opacity: 0 },
                    { y: 0, opacity: 1, duration: 0.7, ease: "power3.out" },
                    "-=0.4"
                )
                .fromTo(".experience-subtext", 
                    { y: 16, opacity: 0 },
                    { y: 0, opacity: 1, duration: 0.6, ease: "power2.out" },
                    "-=0.35"
                )
                .fromTo(".experience-header-leaf",
                    { scale: 0.5, opacity: 0 },
                    { scale: 1, opacity: 1, duration: 0.5, ease: "back.out(2)" },
                    "-=0.3"
                )
                .fromTo(".experience-card.is-active",
                    { y: 30, opacity: 0, scale: 0.96 },
                    { y: 0, opacity: 1, scale: 1, duration: 0.85, ease: "power3.out" },
                    "-=0.3"
                )
                .fromTo([".experience-card.is-prev", ".experience-card.is-next"],
                    { opacity: 0, y: 20 },
                    { opacity: 0.65, y: 0, duration: 0.65, stagger: 0.1, ease: "power2.out" },
                    "-=0.55"
                )
                .fromTo(".experience-controls",
                    { y: 16, opacity: 0 },
                    { y: 0, opacity: 1, duration: 0.5, ease: "power2.out" },
                    "-=0.3"
                )
                .fromTo(".trust-stat-col",
                    { y: 16, opacity: 0 },
                    { y: 0, opacity: 1, duration: 0.5, stagger: 0.08, ease: "power2.out" },
                    "-=0.25"
                );
        }
    }

    // =========================================================================
    // SECTION 11 — NUTRONYX PREMIUM FAQ EXPERIENCE
    // =========================================================================
    const faqSection = document.querySelector(".section-faq");
    if (faqSection) {
        const faqItems = faqSection.querySelectorAll(".faq-item");

        faqItems.forEach((item) => {
            const trigger = item.querySelector(".faq-trigger");
            if (!trigger) return;

            trigger.addEventListener("click", () => {
                const isOpen = item.classList.contains("is-open");

                // Close all other items (enforcing single open item rule)
                faqItems.forEach((otherItem) => {
                    if (otherItem !== item && otherItem.classList.contains("is-open")) {
                        otherItem.classList.remove("is-open");
                        const otherTrigger = otherItem.querySelector(".faq-trigger");
                        if (otherTrigger) {
                            otherTrigger.setAttribute("aria-expanded", "false");
                        }
                    }
                });

                // Toggle current item
                if (isOpen) {
                    item.classList.remove("is-open");
                    trigger.setAttribute("aria-expanded", "false");
                } else {
                    item.classList.add("is-open");
                    trigger.setAttribute("aria-expanded", "true");
                }

                // Smoothly notify ScrollTrigger of layout change
                setTimeout(() => {
                    ScrollTrigger.refresh();
                }, 450);
            });
        });

        // Section ScrollTrigger Entrance
        const faqTimeline = gsap.timeline({
            scrollTrigger: {
                trigger: faqSection,
                start: "top 78%",
                toggleActions: "play none none reverse",
                invalidateOnRefresh: true,
            }
        });

        faqTimeline
            .fromTo(".faq-eyebrow", 
                { y: 20, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.55, ease: "power2.out" }
            )
            .fromTo(".faq-headline .faq-headline-line", 
                { y: 35, opacity: 0 },
                { y: 0, opacity: 1, stagger: 0.12, duration: 0.65, ease: "power3.out" },
                "-=0.35"
            )
            .fromTo(".faq-intro-subtext", 
                { y: 20, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.55, ease: "power2.out" },
                "-=0.3"
            )
            .fromTo(".faq-concierge-card",
                { y: 25, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.65, ease: "power3.out" },
                "-=0.35"
            )
            .fromTo(".faq-item",
                { y: 30, opacity: 0 },
                { y: 0, opacity: 1, stagger: 0.08, duration: 0.6, ease: "power2.out" },
                "-=0.4"
            );
    }

    // =========================================================================
    // SECTION 13 — NUTRONYX PREMIUM SIGNATURE FOOTER
    // =========================================================================
    const footerSection = document.querySelector(".section-footer");
    if (footerSection) {
        const footerTimeline = gsap.timeline({
            scrollTrigger: {
                trigger: footerSection,
                start: "top 85%",
                toggleActions: "play none none reverse",
                invalidateOnRefresh: true,
            }
        });

        footerTimeline
            .fromTo(".footer-top-bar",
                { opacity: 0, y: 15 },
                { opacity: 1, y: 0, duration: 0.5, ease: "power2.out" }
            )
            .fromTo(".footer-col",
                { y: 30, opacity: 0 },
                { y: 0, opacity: 1, stagger: 0.08, duration: 0.6, ease: "power2.out" },
                "-=0.2"
            )
            .fromTo(".footer-middle-tier",
                { y: 20, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.55, ease: "power2.out" },
                "-=0.2"
            )
            .fromTo([".footer-banner-img", ".footer-branch-overlay"],
                { y: 35, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.8, ease: "power3.out" },
                "-=0.3"
            );

        // Continuous organic waving / swaying breeze animation for background leaves (runs without moving cursor)
        gsap.to(".branch-top-img", {
            rotation: 4,
            x: 5,
            y: 4,
            duration: 3.8,
            repeat: -1,
            yoyo: true,
            ease: "sine.inOut"
        });

        // Explicit tap / click handler for Finstics digital experience badge
        const finsticsBadge = footerSection.querySelector(".finstics-box-link");
        if (finsticsBadge) {
            finsticsBadge.addEventListener("click", (e) => {
                // Ensure browser directly opens phone dialer / tel link
                window.location.href = "tel:8340956469";
            });
        }

        // Smooth scroll for footer navigation links
        const footerNavLinks = footerSection.querySelectorAll('a[href^="#"]');
        footerNavLinks.forEach((link) => {
            link.addEventListener("click", (e) => {
                const targetId = link.getAttribute("href");
                if (targetId && targetId !== "#") {
                    const targetEl = document.querySelector(targetId);
                    if (targetEl) {
                        e.preventDefault();
                        targetEl.scrollIntoView({ behavior: "smooth" });
                    }
                }
            });
        });
    }

    /* ==========================================================================
       SECTION 3: EVERY MINUTE MATTERS — 3D CLOCK & NUTRIENT ORBIT
       Continuous Clock Rotation & 3D Elliptical Nutrient Orbit System
       ========================================================================== */
    function initEveryMinuteMatters() {
        const section = document.querySelector("#every-minute-matters");
        if (!section || typeof gsap === "undefined") return;

        const clockDial = section.querySelector("#clockDialPlate");
        const handHour = section.querySelector("#clockHandHour");
        const handMinute = section.querySelector("#clockHandMinute");
        const handSecond = section.querySelector("#clockHandSecond");
        const orbitStage = section.querySelector("#minuteOrbitStage");
        const bubbles = [
            section.querySelector("#bubbleProtein"),
            section.querySelector("#bubbleVitamins"),
            section.querySelector("#bubbleMinerals"),
            section.querySelector("#bubbleAntioxidants"),
            section.querySelector("#bubbleFiber")
        ].filter(Boolean);

        const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

        // 1. CONTINUOUS CLOCK ROTATION ANIMATIONS
        if (!prefersReducedMotion) {
            // Smooth continuous sweep of Second Hand (15s per full revolution)
            if (handSecond) {
                gsap.to(handSecond, {
                    rotation: "+=360",
                    duration: 15,
                    ease: "none",
                    repeat: -1,
                    transformOrigin: "1px 1px"
                });
            }

            // Smooth continuous sweep of Minute Hand (75s per revolution)
            if (handMinute) {
                gsap.to(handMinute, {
                    rotation: "+=360",
                    duration: 75,
                    ease: "none",
                    repeat: -1,
                    transformOrigin: "1px 1px"
                });
            }

            // Smooth continuous sweep of Hour Hand (300s per revolution)
            if (handHour) {
                gsap.to(handHour, {
                    rotation: "+=360",
                    duration: 300,
                    ease: "none",
                    repeat: -1,
                    transformOrigin: "1px 1px"
                });
            }

            // Dial plate remains upright and stationary — only clock hands ("legs") rotate
        }

        // 2. 3D ELLIPTICAL NUTRIENT ORBIT SYSTEM
        if (bubbles.length === 5 && orbitStage) {
            // Initial angular positions around the ellipse (in degrees)
            const baseAngles = [-100, -28, 44, 116, 188];
            const orbitProgress = { progress: 0 };
            const tiltAngle = -18 * (Math.PI / 180); // -18 degrees in radians matching SVG transform="rotate(-18)"

            function updateOrbitPositions() {
                const stageWidth = orbitStage.offsetWidth || 560;
                // SVG viewBox is -300 -300 600 600, ellipse rx=268, ry=195
                const scale = stageWidth / 600;
                const rx = 268 * scale;
                const ry = 195 * scale;

                bubbles.forEach((bubble, idx) => {
                    const currentDeg = (baseAngles[idx] + orbitProgress.progress * 360) % 360;
                    const rad = currentDeg * (Math.PI / 180);

                    // Unrotated ellipse coordinate
                    const unrotX = rx * Math.cos(rad);
                    const unrotY = ry * Math.sin(rad);

                    // Rotate coordinate by tilt angle (-18 deg)
                    const posX = unrotX * Math.cos(tiltAngle) - unrotY * Math.sin(tiltAngle);
                    const posY = unrotX * Math.sin(tiltAngle) + unrotY * Math.cos(tiltAngle);

                    // 3D Depth Layering:
                    // When unrotY < 0, bubble is behind the clock (scale down, lower z-index)
                    // When unrotY >= 0, bubble is in front of the clock (scale up, higher z-index)
                    const depthNormalized = (unrotY + ry) / (2 * ry); // 0 to 1
                    const scaleFactor = 0.82 + depthNormalized * 0.32; // 0.82 to 1.14
                    const zIndex = unrotY < 0 ? 3 : 14;

                    gsap.set(bubble, {
                        x: posX,
                        y: posY,
                        scale: scaleFactor,
                        zIndex: zIndex,
                        xPercent: -50,
                        yPercent: -50
                    });
                });
            }

            // Initial positioning
            updateOrbitPositions();

            // Continuous smooth orbit rotation (32s per complete revolution)
            if (!prefersReducedMotion) {
                const orbitTween = gsap.to(orbitProgress, {
                    progress: 1,
                    duration: 32,
                    ease: "none",
                    repeat: -1,
                    onUpdate: updateOrbitPositions
                });

                // Window resize recalculation for responsiveness
                window.addEventListener("resize", () => {
                    updateOrbitPositions();
                }, { passive: true });

                // Pause / slow on hover for enhanced user inspection
                orbitStage.addEventListener("mouseenter", () => {
                    gsap.to(orbitTween, { timeScale: 0.25, duration: 0.8 });
                });
                orbitStage.addEventListener("mouseleave", () => {
                    gsap.to(orbitTween, { timeScale: 1, duration: 0.8 });
                });
            }
        }

        // 4. SCROLLTRIGGER CINEMATIC ENTRANCE
        if (typeof ScrollTrigger !== "undefined" && !prefersReducedMotion) {
            const tl = gsap.timeline({
                scrollTrigger: {
                    trigger: section,
                    start: "top 78%",
                    toggleActions: "play none none none"
                }
            });

            tl.fromTo(section.querySelectorAll(".minute-eyebrow-wrapper"), {
                opacity: 0,
                y: 18
            }, {
                opacity: 1,
                y: 0,
                duration: 0.7,
                stagger: 0.15,
                ease: "power2.out"
            })
            .fromTo(section.querySelectorAll(".minute-headline .m-line"), {
                opacity: 0,
                y: 28
            }, {
                opacity: 1,
                y: 0,
                duration: 0.8,
                stagger: 0.12,
                ease: "power3.out"
            }, "-=0.4")
            .fromTo(section.querySelector(".minute-subtext"), {
                opacity: 0,
                y: 16
            }, {
                opacity: 1,
                y: 0,
                duration: 0.7,
                ease: "power2.out"
            }, "-=0.4")
            .fromTo(section.querySelector(".minute-clock-container"), {
                opacity: 0,
                scale: 0.88
            }, {
                opacity: 1,
                scale: 1,
                duration: 1,
                ease: "back.out(1.2)"
            }, "-=0.8")
            .fromTo(section.querySelectorAll(".nutrient-bubble-item"), {
                opacity: 0,
                scale: 0.5
            }, {
                opacity: 1,
                scale: 1,
                duration: 0.8,
                stagger: 0.1,
                ease: "back.out(1.4)"
            }, "-=0.6");
        }
    }

    // Call Section 3 initialization
    initEveryMinuteMatters();
});


