<?php
declare(strict_types=1);

use Nutronyx\Context;

$activePage = $activePage ?? 'dashboard';
$activeWebsite = Context::getWebsite();
$accessibleWebsites = Context::getAccessibleWebsites();
$canSwitchStores = Context::canSwitchStores();

$currentUser = \Nutronyx\Services\AuthService::user();
$currentUserRole = strtoupper($currentUser['role'] ?? 'STORE_MANAGER');
$isSuperAdmin = ($currentUserRole === 'SUPER_ADMIN');
$restrictedNavIds = ['shipping', 'payments', 'roles'];

$unreadInquiries = 0;
try {
    $unreadInquiries = \Nutronyx\Services\InquiryService::getUnreadCount();
} catch (\Throwable $e) {}

$navItems = [
    'General' => [
        ['id' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => '<circle cx="12" cy="12" r="10"></circle><polygon points="12 8 8 12 12 16 16 12 12 8"></polygon>'],
    ],
    'Catalog' => [
        ['id' => 'products', 'label' => 'Products & SKUs', 'url' => 'products.php', 'icon' => '<polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline>'],
        ['id' => 'categories', 'label' => 'Categories & Subcategories', 'url' => 'categories.php', 'icon' => '<rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>'],
    ],
    'Operations' => [
        ['id' => 'orders', 'label' => 'Orders & Fulfillments', 'url' => 'orders.php', 'icon' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>'],
        ['id' => 'customers', 'label' => 'Customers', 'url' => 'customers.php', 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'],
        ['id' => 'inventory', 'label' => 'Inventory & Stocks', 'url' => 'inventory.php', 'icon' => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>'],
        ['id' => 'shipping', 'label' => 'Shipping & Logistics', 'url' => 'shipping.php', 'icon' => '<rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle>'],
        ['id' => 'payments', 'label' => 'Payment Gateway (Cashfree)', 'url' => 'payments.php', 'icon' => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line>'],
        ['id' => 'templates', 'label' => 'Invoice & Receipt Templates', 'url' => 'templates.php', 'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>'],
    ],
    'Marketing & Trust' => [
        ['id' => 'inquiries', 'label' => 'Inquiries & Leads', 'url' => 'inquiries.php', 'icon' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline>'],
        ['id' => 'promotions', 'label' => 'Promotions & Ticker', 'url' => 'promotions.php', 'icon' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>'],
        ['id' => 'reviews', 'label' => 'Reviews Moderation', 'url' => 'reviews.php', 'icon' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>'],
    ],
    'Governance' => [
        ['id' => 'roles', 'label' => 'Roles & Permissions', 'url' => 'roles.php', 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'],
    ]
];
?>
<!-- Universal Drawer Backdrop Overlay -->
<div class="dash-sidebar-backdrop" id="sidebarBackdrop"></div>

<aside class="dash-sidebar" id="mainDashSidebar" aria-label="Main Navigation Drawer">
    <!-- Sleek Drawer Header with Brand Badge & Close Button -->
    <div class="drawer-mobile-header">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 28px; height: 28px; border-radius: 7px; background: #143D24; display: flex; align-items: center; justify-content: center; color: #FFFFFF; font-weight: 700; font-size: 14px; font-family: var(--admin-font-serif);">
                N
            </div>
            <div>
                <span style="font-family: var(--admin-font-serif); font-size: 17px; font-weight: 600; color: #14221A; display: block; line-height: 1.1;">Nutronyx</span>
                <span style="font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #8A9A90;">Admin Core</span>
            </div>
        </div>
        <button type="button" class="drawer-close-btn" id="sidebarCloseBtn" aria-label="Close navigation drawer" title="Close navigation">&times;</button>
    </div>

    <!-- Storefront Display inside Drawer (Single Store Panel) -->
    <div class="drawer-mobile-storefront" style="margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--admin-border-subtle);">
        <label style="font-size: 10.5px; font-weight: 700; color: #8A9A90; text-transform: uppercase; letter-spacing: 0.06em; display: block; margin-bottom: 6px;">Active Storefront:</label>
        <div style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #E2DBD0; background: #FAF9F6; font-size: 12.5px; font-weight: 600; color: #14221A; display: flex; align-items: center; gap: 8px;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background: #166534; display: inline-block; flex-shrink: 0;"></span>
            <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($activeWebsite['name']) ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navItems as $sectionTitle => $sectionItems): ?>
            <div>
                <span class="nav-section-title"><?= htmlspecialchars($sectionTitle) ?></span>
                <ul class="nav-item-list">
                    <?php foreach ($sectionItems as $item): 
                        $isActive = ($activePage === $item['id']);
                        $isRestricted = (!$isSuperAdmin && in_array($item['id'], $restrictedNavIds, true));
                    ?>
                        <li>
                            <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-item-link <?= $isActive ? 'active' : '' ?>" data-nav-id="<?= htmlspecialchars($item['id']) ?>">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="<?= $isActive ? '2.2' : '1.8' ?>" stroke-linecap="round" stroke-linejoin="round" class="nav-item-icon">
                                    <?= $item['icon'] ?>
                                </svg>
                                <span style="display: flex; align-items: center; justify-content: space-between; flex: 1; min-width: 0;">
                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($item['label']) ?></span>
                                    <span style="display: inline-flex; align-items: center;">
                                        <?php if ($item['id'] === 'inquiries' && $unreadInquiries > 0): ?>
                                            <span style="background: #143D24; color: #FFFFFF; font-size: 10px; font-weight: 700; border-radius: 10px; padding: 1px 7px; margin-left: 6px;" title="<?= $unreadInquiries ?> new inquiry"><?= $unreadInquiries ?></span>
                                        <?php endif; ?>
                                        <?php if ($isRestricted): ?>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-left: 6px;" title="Restricted to Super Admin">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; 
        unset($sectionItems, $item, $navItems);
        ?>
    </nav>
</aside>

<!-- Universal Client-Side Navigation Drawer Controller -->
<script>
(function() {
    function initUniversalSidebarDrawer() {
        if (window.__sidebarDrawerInitialized) return;

        const toggleBtn = document.getElementById('mobileSidebarToggle');
        const sidebar = document.getElementById('mainDashSidebar') || document.querySelector('.dash-sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const closeBtn = document.getElementById('sidebarCloseBtn');

        if (!sidebar) return;
        window.__sidebarDrawerInitialized = true;

        function openDrawer() {
            sidebar.classList.add('mobile-open');
            sidebar.classList.add('sidebar-drawer-open');
            if (backdrop) backdrop.classList.add('active');
            document.body.classList.add('sidebar-open-lock');
        }

        function closeDrawer() {
            sidebar.classList.remove('mobile-open');
            sidebar.classList.remove('sidebar-drawer-open');
            if (backdrop) backdrop.classList.remove('active');
            document.body.classList.remove('sidebar-open-lock');
        }

        function toggleDrawer() {
            if (sidebar.classList.contains('mobile-open') || sidebar.classList.contains('sidebar-drawer-open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        }

        window.NutronyxSidebar = {
            open: openDrawer,
            close: closeDrawer,
            toggle: toggleDrawer
        };

        // Single direct onclick on toggle button ("that bar")
        if (toggleBtn) {
            toggleBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleDrawer();
            };
        }

        // Close button (x inside drawer header)
        if (closeBtn) {
            closeBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeDrawer();
            };
        }

        // Backdrop click closes drawer
        if (backdrop) {
            backdrop.onclick = function(e) {
                e.preventDefault();
                closeDrawer();
            };
        }

        // When user clicks ANY page link inside sidebar: close the navigation bar immediately!
        const navLinks = sidebar.querySelectorAll('a');
        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                closeDrawer();
            });
        });

        // Close on ESC key
        if (!document.__sidebarDrawerEscBound) {
            document.__sidebarDrawerEscBound = true;
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && (sidebar.classList.contains('mobile-open') || sidebar.classList.contains('sidebar-drawer-open'))) {
                    closeDrawer();
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUniversalSidebarDrawer);
    } else {
        initUniversalSidebarDrawer();
    }
})();
</script>
