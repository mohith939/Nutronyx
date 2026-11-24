/**
 * NUTRONYX — Contact Page Interactions & GSAP Motion
 * "Tell us how we can help." & "Let’s build healthier habits together."
 * Category touchpoints, real-time validation, asynchronous dispatch,
 * and partnership interactive redirects.
 */

document.addEventListener("DOMContentLoaded", () => {
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    // 1. Initialize GSAP Entrance Animations
    initContactEntrance(prefersReducedMotion);

    // 2. Initialize Category Touchpoint Selectors
    initCategoryTouchpoints();

    // 3. Initialize Contact Form AJAX Submission
    initContactForm();

    // 4. Initialize Partnership Cards & CTA Redirection
    initPartnershipInteractions();
});

/**
 * GSAP Timeline for Editorial Entrance
 */
function initContactEntrance(prefersReducedMotion) {
    if (typeof gsap === "undefined") return;

    if (prefersReducedMotion) {
        gsap.set([
            ".contact-eyebrow-pill",
            ".contact-headline .hl-line",
            ".contact-narrative-subtext",
            ".contact-narrative-hairline",
            ".touchpoint-item",
            ".contact-luxury-card",
            ".partner-card"
        ], { opacity: 1, y: 0, clearProps: "transform" });
        return;
    }

    const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

    tl.fromTo(".contact-eyebrow-pill", 
        { opacity: 0, y: 16 }, 
        { opacity: 1, y: 0, duration: 0.7, delay: 0.15 }
    )
    .fromTo(".contact-headline .hl-line", 
        { opacity: 0, y: 28 }, 
        { opacity: 1, y: 0, duration: 0.9, stagger: 0.12, ease: "power4.out" }, 
        "-=0.4"
    )
    .fromTo(".contact-narrative-subtext", 
        { opacity: 0, y: 18 }, 
        { opacity: 1, y: 0, duration: 0.8 }, 
        "-=0.5"
    )
    .fromTo(".contact-narrative-hairline", 
        { scaleX: 0, opacity: 0, transformOrigin: "left center" }, 
        { scaleX: 1, opacity: 1, duration: 0.7, ease: "expo.out" }, 
        "-=0.4"
    )
    .fromTo(".touchpoint-item", 
        { opacity: 0, y: 16 }, 
        { opacity: 1, y: 0, duration: 0.6, stagger: 0.1 }, 
        "-=0.3"
    )
    .fromTo(".contact-luxury-card", 
        { opacity: 0, y: 35, scale: 0.98 }, 
        { opacity: 1, y: 0, scale: 1, duration: 1.1, ease: "power4.out" }, 
        "-=0.8"
    );

    // ScrollTrigger for Partnerships Section
    if (typeof ScrollTrigger !== "undefined") {
        gsap.fromTo(".partner-card", 
            { opacity: 0, y: 30 },
            {
                opacity: 1,
                y: 0,
                duration: 0.8,
                stagger: 0.12,
                ease: "power3.out",
                scrollTrigger: {
                    trigger: ".partnership-cards-grid",
                    start: "top 85%",
                    once: true
                }
            }
        );
    }
}

/**
 * Interactive Touchpoints: Clicking category pre-populates Subject in the form
 */
function initCategoryTouchpoints() {
    const touchpoints = document.querySelectorAll(".touchpoint-item");
    const subjectInput = document.getElementById("subject");
    const subjectWrap = document.getElementById("wrap-subject");

    touchpoints.forEach(item => {
        const selectTopic = () => {
            const topic = item.getAttribute("data-topic");
            
            // Toggle selected state
            touchpoints.forEach(tp => {
                tp.classList.remove("is-selected");
                tp.setAttribute("aria-checked", "false");
            });
            item.classList.add("is-selected");
            item.setAttribute("aria-checked", "true");

            // Update Subject input
            if (subjectInput && topic) {
                subjectInput.value = topic;

                // Brief glow feedback on subject field
                if (subjectWrap) {
                    subjectWrap.classList.add("touchpoint-highlight");
                    setTimeout(() => {
                        subjectWrap.classList.remove("touchpoint-highlight");
                    }, 600);
                }
            }
        };

        item.addEventListener("click", selectTopic);
        item.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                selectTopic();
            }
        });
    });
}

/**
 * Interactive Partnership Cards & CTA:
 * Clicking any partner card or the CTA button scrolls up to the contact form,
 * pre-selects the "Partnership Inquiries" touchpoint, populates the subject,
 * highlights the form, and focuses the message textarea.
 */
function initPartnershipInteractions() {
    const partnerCards = document.querySelectorAll(".partner-card");
    const partnerCta = document.getElementById("btnPartnerCta");
    const formSection = document.getElementById("contact-experience");
    const subjectInput = document.getElementById("subject");
    const subjectWrap = document.getElementById("wrap-subject");
    const messageInput = document.getElementById("message");
    const partnerTouchpoint = document.querySelector('.touchpoint-item[data-topic="Partnership Inquiries"]');
    const allTouchpoints = document.querySelectorAll(".touchpoint-item");

    function redirectToForm(partnerType) {
        if (!formSection) return;

        // 1. Smooth scroll to the form above
        formSection.scrollIntoView({ behavior: "smooth", block: "center" });

        // 2. Select "Partnership Inquiries" touchpoint in the upper section
        allTouchpoints.forEach(tp => {
            tp.classList.remove("is-selected");
            tp.setAttribute("aria-checked", "false");
        });
        if (partnerTouchpoint) {
            partnerTouchpoint.classList.add("is-selected");
            partnerTouchpoint.setAttribute("aria-checked", "true");
        }

        // 3. Populate Subject field with specific partnership track
        const subjectText = partnerType ? `Partnership Inquiry — ${partnerType}` : "Partnership Inquiries";
        if (subjectInput) {
            subjectInput.value = subjectText;
        }

        // 4. Subtle pulse highlight on form card
        const luxuryCard = document.querySelector(".contact-luxury-card");
        if (luxuryCard) {
            luxuryCard.style.transition = "box-shadow 0.4s ease, border-color 0.4s ease";
            luxuryCard.style.borderColor = "#1E5736";
            luxuryCard.style.boxShadow = "0 0 0 4px rgba(30, 87, 54, 0.18), 0 30px 70px rgba(20, 48, 35, 0.1)";
            setTimeout(() => {
                luxuryCard.style.borderColor = "";
                luxuryCard.style.boxShadow = "";
            }, 1200);
        }

        if (subjectWrap) {
            subjectWrap.classList.add("touchpoint-highlight");
            setTimeout(() => {
                subjectWrap.classList.remove("touchpoint-highlight");
            }, 1200);
        }

        // 5. Focus message textarea after scroll finishes
        setTimeout(() => {
            if (messageInput) {
                messageInput.focus();
            }
        }, 650);
    }

    partnerCards.forEach(card => {
        const type = card.getAttribute("data-partner-type") || "Partnerships";
        card.addEventListener("click", () => redirectToForm(type));
        card.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                redirectToForm(type);
            }
        });
    });

    if (partnerCta) {
        partnerCta.addEventListener("click", () => redirectToForm("General Partnership"));
    }
}

/**
 * Asynchronous AJAX Form Submission & Client-Side Validation
 */
function initContactForm() {
    const form = document.getElementById("nutronyxContactForm");
    const submitBtn = document.getElementById("submitBtn");
    const alertBanner = document.getElementById("formAlert");
    const alertText = document.getElementById("alertMessageText");
    const successCard = document.getElementById("formSuccessCard");
    const sendAnotherBtn = document.getElementById("sendAnotherBtn");

    if (!form || !submitBtn) return;

    // Clear error states on input
    const inputs = form.querySelectorAll("input, textarea");
    inputs.forEach(input => {
        input.addEventListener("input", () => {
            const wrap = input.closest(".form-input-pill-wrap, .form-textarea-pill-wrap");
            if (wrap) wrap.classList.remove("has-error");
            hideAlert();
        });
    });

    // Form Submission
    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        hideAlert();

        const fullName = document.getElementById("full_name")?.value.trim() || "";
        const email = document.getElementById("email")?.value.trim() || "";
        const subject = document.getElementById("subject")?.value.trim() || "";
        const message = document.getElementById("message")?.value.trim() || "";

        let hasError = false;

        // Validation: Full Name
        if (fullName.length < 2) {
            setFieldError("wrap-full_name");
            showAlert("Please provide your full name (minimum 2 characters).");
            hasError = true;
        }

        // Validation: Email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email || !emailRegex.test(email)) {
            setFieldError("wrap-email");
            showAlert("Please enter a valid email address.");
            hasError = true;
        }

        // Validation: Subject
        if (subject.length < 2) {
            setFieldError("wrap-subject");
            showAlert("Please enter a subject for your message.");
            hasError = true;
        }

        // Validation: Message
        if (message.length < 10) {
            setFieldError("wrap-message");
            showAlert("Please enter a message with at least 10 characters.");
            hasError = true;
        }

        if (hasError) return;

        // Button Loading State
        setLoading(true);

        const formData = new FormData(form);

        try {
            const response = await fetch("api/contact-handler.php", {
                method: "POST",
                body: formData,
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            });

            const data = await response.json();

            setLoading(false);

            if (data.success) {
                // Show success view
                form.style.display = "none";
                if (successCard) {
                    successCard.style.display = "block";
                }
                form.reset();
            } else {
                showAlert(data.message || "An unexpected error occurred. Please try again.");
                if (data.errors) {
                    for (const key of Object.keys(data.errors)) {
                        setFieldError("wrap-" + key);
                    }
                }
            }
        } catch (err) {
            setLoading(false);
            showAlert("Connection error. Please verify your connection or try again shortly.");
        }
    });

    // Send Another Message Button
    if (sendAnotherBtn && successCard) {
        sendAnotherBtn.addEventListener("click", () => {
            successCard.style.display = "none";
            form.style.display = "block";
            form.reset();
            hideAlert();
            // Reset categories
            document.querySelectorAll(".touchpoint-item").forEach(tp => {
                tp.classList.remove("is-selected");
                tp.setAttribute("aria-checked", "false");
            });
        });
    }

    function setFieldError(wrapId) {
        const wrap = document.getElementById(wrapId);
        if (wrap) wrap.classList.add("has-error");
    }

    function showAlert(msg) {
        if (!alertBanner || !alertText) return;
        alertText.textContent = msg;
        alertBanner.className = "form-alert-banner is-error";
        alertBanner.style.display = "flex";
    }

    function hideAlert() {
        if (!alertBanner) return;
        alertBanner.style.display = "none";
    }

    function setLoading(isLoading) {
        const btnText = submitBtn.querySelector(".btn-text");
        const btnArrow = submitBtn.querySelector(".btn-arrow");
        const btnSpinner = submitBtn.querySelector(".btn-spinner");

        submitBtn.disabled = isLoading;
        if (isLoading) {
            if (btnText) btnText.style.display = "none";
            if (btnArrow) btnArrow.style.display = "none";
            if (btnSpinner) btnSpinner.style.display = "inline-flex";
        } else {
            if (btnText) btnText.style.display = "inline";
            if (btnArrow) btnArrow.style.display = "inline-flex";
            if (btnSpinner) btnSpinner.style.display = "none";
        }
    }
}\n