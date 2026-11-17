/**
 * NUTRONYX — About Page Script
 * Motion Choreography & Interactions
 */

document.addEventListener("DOMContentLoaded", () => {
    initAboutHeroMotion();
    initAboutStoryMotion();
    initAboutVisionMotion();
    initAboutCommitmentMotion();
    initAboutJourneyMotion();
});

function initAboutHeroMotion() {
    const section = document.getElementById("about-hero");
    if (!section) return;

    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (prefersReducedMotion || typeof gsap === "undefined") {
        gsap.set([
            ".about-hero-badge",
            ".about-main-headline",
            ".about-hero-description",
            ".about-cta-btn",
            ".about-stage-wrapper"
        ], { opacity: 1, y: 0, clearProps: "all" });
        return;
    }

    // Set Initial State
    gsap.set(".about-hero-badge", { opacity: 0, y: 20 });
    gsap.set(".about-main-headline", { opacity: 0, y: 28 });
    gsap.set(".about-hero-description", { opacity: 0, y: 20 });
    gsap.set(".about-cta-btn", { opacity: 0, y: 16 });
    gsap.set(".about-stage-wrapper", { opacity: 0, scale: 0.96, y: 24 });

    // Entrance Timeline
    const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

    tl.to(".about-hero-badge", {
        opacity: 1,
        y: 0,
        duration: 0.8
    })
    .to(".about-main-headline", {
        opacity: 1,
        y: 0,
        duration: 0.9
    }, "-=0.6")
    .to(".about-hero-description", {
        opacity: 1,
        y: 0,
        duration: 0.8
    }, "-=0.6")
    .to(".about-cta-btn", {
        opacity: 1,
        y: 0,
        duration: 0.6,
        stagger: 0.08
    }, "-=0.5")
    .to(".about-stage-wrapper", {
        opacity: 1,
        scale: 1,
        y: 0,
        duration: 1.0,
        ease: "power2.out"
    }, "-=0.8");
}

function initAboutStoryMotion() {
    const section = document.getElementById("our-story");
    if (!section) return;

    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (prefersReducedMotion || typeof gsap === "undefined" || typeof ScrollTrigger === "undefined") {
        gsap.set([
            ".story-header-block",
            ".story-timeline-item",
            ".story-timeline-rail",
            ".story-stage-wrapper"
        ], { opacity: 1, y: 0, clearProps: "all" });
        return;
    }

    // Set Initial Hidden State
    gsap.set(".story-header-block", { opacity: 0, y: 24 });
    gsap.set(".story-timeline-rail", { scaleY: 0, transformOrigin: "top center" });
    gsap.set(".story-timeline-item", { opacity: 0, x: -24 });
    gsap.set(".story-stage-wrapper", { opacity: 0, scale: 0.96, y: 28 });

    ScrollTrigger.create({
        trigger: section,
        start: "top 76%",
        once: true,
        onEnter: () => {
            const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

            tl.to(".story-header-block", {
                opacity: 1,
                y: 0,
                duration: 0.8
            })
            .to(".story-timeline-rail", {
                scaleY: 1,
                duration: 0.8,
                ease: "power2.inOut"
            }, "-=0.4")
            .to(".story-timeline-item", {
                opacity: 1,
                x: 0,
                duration: 0.6,
                stagger: 0.1,
                clearProps: "transform"
            }, "-=0.6")
            .to(".story-stage-wrapper", {
                opacity: 1,
                scale: 1,
                y: 0,
                duration: 1.0,
                ease: "power2.out"
            }, "-=0.8");
        }
    });
}

function initAboutVisionMotion() {
    const section = document.getElementById("vision");
    if (!section) return;

    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (prefersReducedMotion || typeof gsap === "undefined" || typeof ScrollTrigger === "undefined") {
        gsap.set([
            ".vision-header-block",
            ".vision-arch-frame",
            ".vision-center-divider",
            ".vision-pillar-item"
        ], { opacity: 1, y: 0, clearProps: "all" });
        return;
    }

    // Set Initial Hidden State
    gsap.set(".vision-header-block", { opacity: 0, y: 24 });
    gsap.set(".vision-arch-frame", { opacity: 0, scale: 0.95, y: 28 });
    gsap.set(".vision-center-divider", { scaleY: 0, transformOrigin: "center center" });
    gsap.set(".vision-pillar-item", { opacity: 0, x: 24 });

    ScrollTrigger.create({
        trigger: section,
        start: "top 76%",
        once: true,
        onEnter: () => {
            const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

            tl.to(".vision-header-block", {
                opacity: 1,
                y: 0,
                duration: 0.8
            })
            .to(".vision-arch-frame", {
                opacity: 1,
                scale: 1,
                y: 0,
                duration: 1.0,
                ease: "power2.out"
            }, "-=0.5")
            .to(".vision-center-divider", {
                scaleY: 1,
                duration: 0.8,
                ease: "power2.inOut"
            }, "-=0.7")
            .to(".vision-pillar-item", {
                opacity: 1,
                x: 0,
                duration: 0.65,
                stagger: 0.12,
                clearProps: "transform"
            }, "-=0.6");
        }
    });
}

function initAboutCommitmentMotion() {
    const section = document.getElementById("our-commitment");
    if (!section) return;

    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (prefersReducedMotion || typeof gsap === "undefined" || typeof ScrollTrigger === "undefined") {
        gsap.set([
            ".commit-header-block",
            ".commit-arch-frame",
            ".commit-center-rail",
            ".commit-pillar-item"
        ], { opacity: 1, y: 0, clearProps: "all" });
        return;
    }

    // Set Initial Hidden State
    gsap.set(".commit-header-block", { opacity: 0, y: 24 });
    gsap.set(".commit-arch-frame", { opacity: 0, scale: 0.95, y: 28 });
    gsap.set(".commit-center-rail", { scaleY: 0, transformOrigin: "top center" });
    gsap.set(".commit-pillar-item", { opacity: 0, x: 24 });

    ScrollTrigger.create({
        trigger: section,
        start: "top 76%",
        once: true,
        onEnter: () => {
            const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

            tl.to(".commit-header-block", {
                opacity: 1,
                y: 0,
                duration: 0.8
            })
            .to(".commit-arch-frame", {
                opacity: 1,
                scale: 1,
                y: 0,
                duration: 1.0,
                ease: "power2.out"
            }, "-=0.5")
            .to(".commit-center-rail", {
                scaleY: 1,
                duration: 0.8,
                ease: "power2.inOut"
            }, "-=0.7")
            .to(".commit-pillar-item", {
                opacity: 1,
                x: 0,
                duration: 0.65,
                stagger: 0.1,
                clearProps: "transform"
            }, "-=0.6");
        }
    });
}

function initAboutJourneyMotion() {
    const section = document.getElementById("wellness-journey");
    if (!section) return;

    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (prefersReducedMotion || typeof gsap === "undefined" || typeof ScrollTrigger === "undefined") {
        gsap.set([
            ".journey-header-block",
            ".journey-timeline-item",
            ".journey-timeline-rail",
            ".journey-cta-wrapper",
            ".journey-photo-frame"
        ], { opacity: 1, y: 0, clearProps: "all" });
        return;
    }

    // Set Initial Hidden State
    gsap.set(".journey-header-block", { opacity: 0, y: 24 });
    gsap.set(".journey-timeline-rail", { scaleY: 0, transformOrigin: "top center" });
    gsap.set(".journey-timeline-item", { opacity: 0, x: -24 });
    gsap.set(".journey-cta-wrapper", { opacity: 0, y: 16 });
    gsap.set(".journey-photo-frame", { opacity: 0, scale: 0.96, y: 28 });

    ScrollTrigger.create({
        trigger: section,
        start: "top 76%",
        once: true,
        onEnter: () => {
            const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

            tl.to(".journey-header-block", {
                opacity: 1,
                y: 0,
                duration: 0.8
            })
            .to(".journey-timeline-rail", {
                scaleY: 1,
                duration: 0.8,
                ease: "power2.inOut"
            }, "-=0.4")
            .to(".journey-timeline-item", {
                opacity: 1,
                x: 0,
                duration: 0.6,
                stagger: 0.1,
                clearProps: "transform"
            }, "-=0.6")
            .to(".journey-cta-wrapper", {
                opacity: 1,
                y: 0,
                duration: 0.6
            }, "-=0.4")
            .to(".journey-photo-frame", {
                opacity: 1,
                scale: 1,
                y: 0,
                duration: 1.0,
                ease: "power2.out"
            }, "-=0.8");
        }
    });
}
