<?php
$currentPage = $currentPage ?? 'home';
$isShop = ($currentPage === 'shop');
$isContact = ($currentPage === 'contact');
$isPartner = ($currentPage === 'partner');
$isAbout = ($currentPage === 'about');
$isHome = ($currentPage === 'home');
$isCart = ($currentPage === 'cart');
$isFaq = ($currentPage === 'faq');
$homePath = !$isHome ? 'index.php' : '';

if (!class_exists('Nutronyx\Services\CartService')) {
    require_once __DIR__ . '/../src/bootstrap.php';
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$cartCount = \Nutronyx\Services\CartService::getItemCount();
$announcement = \Nutronyx\Services\AnnouncementService::getAnnouncement();
$isAnnouncementActive = (!empty($announcement) && !empty($announcement['is_enabled']));
?>
<header class="site-header <?= $isAnnouncementActive ? 'has-announcement' : '' ?>" id="siteHeader">
    <?php if ($isAnnouncementActive): 
        $speed = (int)($announcement['speed_seconds'] ?? 30);
        $bgColor = htmlspecialchars($announcement['bg_color'] ?? '#0D2F1F');
        $textColor = htmlspecialchars($announcement['text_color'] ?? '#FAF6ED');

        $announcementItems = [];
        for ($i = 1; $i <= 4; $i++) {
            $sentKey = "sentence_{$i}";
            $iconKey = "icon_{$i}";
            if (!empty($announcement[$sentKey])) {
                $announcementItems[] = [
                    'text' => $announcement[$sentKey],
                    'icon' => \Nutronyx\Services\AnnouncementService::getIconSvg($announcement[$iconKey] ?? 'truck')
                ];
            }
        }
    ?>
    <!-- Top Announcement Ticker Bar (Exact Reference Match) -->
    <div class="announcement-ticker-bar" style="background-color: <?= $bgColor ?>; color: <?= $textColor ?>;" aria-label="Announcement Notice">
        <div class="scroller-track" style="animation-duration: <?= $speed ?>s;">
            <!-- Loop block 1 -->
            <div class="scroller-content">
                <?php foreach ($announcementItems as $item): ?>
                    <span class="scroller-item"><span class="scroller-icon"><?= $item['icon'] ?></span><span class="scroller-text"><?= htmlspecialchars($item['text']) ?></span></span>
                    <span class="scroller-bullet" aria-hidden="true">&#8226;</span>
                <?php endforeach; ?>
            </div>
            <!-- Loop block 2 (Seamless Infinite Duplicate) -->
            <div class="scroller-content" aria-hidden="true">
                <?php foreach ($announcementItems as $item): ?>
                    <span class="scroller-item"><span class="scroller-icon"><?= $item['icon'] ?></span><span class="scroller-text"><?= htmlspecialchars($item['text']) ?></span></span>
                    <span class="scroller-bullet" aria-hidden="true">&#8226;</span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="header-main-nav">
        <div class="header-container">
            <!-- Brand Logo -->
            <a href="<?= !$isHome ? 'index.php' : '#home' ?>" class="brand-logo" aria-label="Nutronyx Home">
                <img src="assets/images/logo.png?v=20261119_v3" alt="Nutronyx Logo" class="logo-img">
            </a>

            <!-- Center Navigation -->
            <nav class="main-nav" aria-label="Main Navigation">
                <ul class="nav-list">
                    <li class="nav-item"><a href="<?= $homePath ?>#home" class="nav-link <?= $isHome ? 'active' : '' ?>">Home</a></li>
                    <li class="nav-item"><a href="shop.php" class="nav-link <?= $isShop ? 'active' : '' ?>">Shop</a></li>
                    <li class="nav-item"><a href="about.php" class="nav-link <?= $isAbout ? 'active' : '' ?>">About</a></li>
                    <li class="nav-item"><a href="partner.php" class="nav-link <?= $isPartner ? 'active' : '' ?>">Partner</a></li>
                    <li class="nav-item"><a href="contact.php" class="nav-link <?= $isContact ? 'active' : '' ?>">Contact</a></li>
                </ul>
            </nav>

            <!-- Right Header Icons -->
            <div class="header-actions">
                <a href="shop.php" class="header-shop-btn <?= $isShop ? 'active' : '' ?>">Buy Now</a>
                <a href="cart.php" class="action-btn cart-btn <?= $isCart ? 'active' : '' ?>" aria-label="Shopping Cart" title="Shopping Cart">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                    <span class="cart-badge" id="headerCartBadge"><?= $cartCount ?></span>
                </a>
                
                <!-- Mobile Menu Toggle -->
                <button class="mobile-toggle" aria-label="Toggle Menu" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </div>
</header>
<script>
(function() {
    function initNutronyxHeader() {
        const header = document.querySelector(".site-header");
        if (!header) return;

        const updateScroll = () => {
            if (window.scrollY > 15) {
                header.classList.add("scrolled");
            } else {
                header.classList.remove("scrolled");
            }
        };

        window.addEventListener("scroll", updateScroll, { passive: true });
        updateScroll();

        // Mobile menu toggle
        const mobileToggle = header.querySelector(".mobile-toggle");
        const mainNav = header.querySelector(".main-nav");
        if (mobileToggle && mainNav) {
            if (mobileToggle.dataset.headerBound === "true") return;
            mobileToggle.dataset.headerBound = "true";

            mobileToggle.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                const isOpen = mainNav.classList.contains("mobile-open");
                if (isOpen) {
                    mainNav.classList.remove("mobile-open");
                    mobileToggle.setAttribute("aria-expanded", "false");
                } else {
                    mainNav.classList.add("mobile-open");
                    mobileToggle.setAttribute("aria-expanded", "true");
                }
            });

            // Close when clicking outside
            document.addEventListener("click", (e) => {
                if (!header.contains(e.target) && mainNav.classList.contains("mobile-open")) {
                    mainNav.classList.remove("mobile-open");
                    mobileToggle.setAttribute("aria-expanded", "false");
                }
            });

            // Close when clicking any nav link
            mainNav.querySelectorAll(".nav-link").forEach(link => {
                link.addEventListener("click", () => {
                    mainNav.classList.remove("mobile-open");
                    mobileToggle.setAttribute("aria-expanded", "false");
                });
            });
        }
        // Sync cart badge with localStorage
        const badge = document.getElementById("headerCartBadge");
        if (badge) {
            const serverCount = parseInt(badge.textContent.trim(), 10) || 0;
            localStorage.setItem("nutronyx_cart_count", String(serverCount));
            window.addEventListener("storage", (e) => {
                if (e.key === "nutronyx_cart_count" && badge) {
                    badge.textContent = e.newValue || "0";
                }
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initNutronyxHeader);
    } else {
        initNutronyxHeader();
    }
})();
</script>
