<?php
declare(strict_types=1);

use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\NotificationService;

// Mark notifications as read action
if (isset($_GET['mark_notifications_read'])) {
    NotificationService::markAllRead();
    $cleanUri = preg_replace('/[?&]mark_notifications_read=1/', '', $_SERVER['REQUEST_URI']);
    header('Location: ' . ($cleanUri ?: 'dashboard.php'));
    exit;
}

$activeWebsite = Context::getWebsite();
$accessibleWebsites = Context::getAccessibleWebsites();
$canSwitchStores = Context::canSwitchStores();
$currentUser = AuthService::user();

$notifUnreadCount = NotificationService::getUnreadCount();
$headerNotifications = NotificationService::getNotifications(8);

$adminName = 'Mohith';
if (!empty($currentUser['name'])) {
    $parts = explode(' ', trim($currentUser['name']));
    $adminName = $parts[0];
}
?>
<header class="dash-header" style="background: #FFFFFF; border-bottom: 1px solid #F3F4F6; height: 68px; padding: 0 clamp(16px, 2.5vw, 36px); display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100;">
    <div style="display: flex; align-items: center; gap: clamp(12px, 1.8vw, 24px);">
        <!-- Hamburger Menu Drawer Toggle ("that bar") -->
        <button type="button" class="mobile-nav-toggle" id="mobileSidebarToggle" aria-label="Toggle navigation menu" title="Toggle navigation menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <!-- Search Bar Input -->
        <form method="GET" action="orders.php" style="margin: 0; position: relative;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); pointer-events: none;">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="search" placeholder="Search orders, products, customers..." style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 9999px; padding: 9px 18px 9px 38px; font-size: 13px; width: clamp(260px, 32vw, 420px); outline: none; transition: border-color 0.2s, background-color 0.2s; font-family: inherit; color: #1F2937;" onfocus="this.style.background='#FFFFFF'; this.style.borderColor='#143D24';" onblur="this.style.background='#F9FAFB'; this.style.borderColor='#E5E7EB';">
        </form>
    </div>

    <!-- Right Header Tools -->
    <div style="display: flex; align-items: center; gap: clamp(14px, 2vw, 24px);">
        <!-- Live Notifications Bell Dropdown -->
        <div style="position: relative;" id="notifDropdownContainer">
            <button type="button" id="notifBellBtn" onclick="toggleNotificationDropdown()" title="Store Notifications & Live Updates" style="position: relative; background: none; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 6px; color: #1F2937;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span style="position: absolute; top: 5px; right: 6px; width: 8px; height: 8px; background: #10B981; border-radius: 50%; border: 2px solid #FFFFFF;"></span>
            </button>

            <!-- Notification Dropdown Menu -->
            <div id="notifDropdownMenu" style="display: none; position: absolute; right: 0; top: calc(100% + 10px); width: 340px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 14px; box-shadow: 0 12px 32px rgba(0,0,0,0.12); z-index: 9999; overflow: hidden;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #FAF9F6; border-bottom: 1px solid #E5E7EB;">
                    <div style="font-weight: 700; font-size: 13.5px; color: #14221A; display: flex; align-items: center; gap: 6px;">
                        <span>Store Activity Updates</span>
                        <?php if ($notifUnreadCount > 0): ?>
                            <span style="background: #E8F5E9; color: #1B5E20; font-size: 11px; padding: 1px 6px; border-radius: 9999px; font-weight: 700;"><?= $notifUnreadCount ?> new</span>
                        <?php endif; ?>
                    </div>
                    <a href="?mark_notifications_read=1" style="font-size: 11.5px; color: var(--admin-forest); text-decoration: none; font-weight: 600;">Mark all read</a>
                </div>

                <div style="max-height: 320px; overflow-y: auto;">
                    <?php if (empty($headerNotifications)): ?>
                        <div style="padding: 24px; text-align: center; color: #789080; font-size: 12.5px;">
                            No recent updates recorded yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($headerNotifications as $hn): ?>
                            <a href="<?= htmlspecialchars($hn['link']) ?>" style="display: block; padding: 12px 16px; border-bottom: 1px solid #F0F4F1; text-decoration: none; transition: background 0.15s; background: <?= $hn['is_read'] ? '#FFFFFF' : '#F6FBF7' ?>;" onmouseover="this.style.background='#F0F6F2'" onmouseout="this.style.background='<?= $hn['is_read'] ? '#FFFFFF' : '#F6FBF7' ?>'">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 3px;">
                                    <span style="font-size: 12.5px; font-weight: 700; color: #14221A;">
                                        <?= htmlspecialchars($hn['title']) ?>
                                    </span>
                                    <span style="font-size: 11px; color: #789080; white-space: nowrap;"><?= htmlspecialchars($hn['time_ago']) ?></span>
                                </div>
                                <div style="font-size: 12px; color: #586B5F; line-height: 1.4;">
                                    <?= htmlspecialchars($hn['message']) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- User Profile Pill & Dropdown -->
        <div style="position: relative;" id="userProfileDropdownWrap">
            <div onclick="toggleProfileDropdown()" style="display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: #143D24; color: #FFFFFF; font-weight: 700; font-size: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <?= strtoupper(substr($adminName, 0, 1)) ?>
                </div>
                <div style="display: flex; flex-direction: column; text-align: left;">
                    <span style="font-size: 13.5px; font-weight: 700; color: #111827; line-height: 1.2;"><?= htmlspecialchars($adminName) ?></span>
                    <span style="font-size: 11px; color: #6B7280; line-height: 1.2;">Admin</span>
                </div>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </div>

            <!-- Profile Dropdown Menu -->
            <div id="userProfileDropdownMenu" style="display: none; position: absolute; right: 0; top: calc(100% + 12px); width: 220px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 9999; padding: 6px 0; overflow: hidden;">
                <div style="padding: 10px 14px; border-bottom: 1px solid #F3F4F6; font-size: 12px; color: #6B7280;">
                    Signed in as <strong style="color: #111827;"><?= htmlspecialchars($currentUser['email'] ?? 'nutronyx@gmail.com') ?></strong>
                </div>

                <div style="padding: 8px 14px; border-bottom: 1px solid #F3F4F6;">
                    <label style="display: block; font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: #9CA3AF; margin-bottom: 4px;">Store Context</label>
                    <div style="font-size: 12px; font-weight: 600; color: #14221A; background: #FAF9F6; padding: 6px 10px; border-radius: 6px; border: 1px solid #E5E7EB; display: flex; align-items: center; gap: 6px;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #166534; display: inline-block; flex-shrink: 0;"></span>
                        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($activeWebsite['name']) ?></span>
                    </div>
                </div>

                <a href="../track-order.php" target="_blank" style="display: flex; align-items: center; gap: 8px; padding: 9px 14px; font-size: 12.5px; color: #374151; text-decoration: none;" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                    <span>Customer Tracker</span>
                </a>

                <a href="logout.php" style="display: flex; align-items: center; gap: 8px; padding: 9px 14px; font-size: 12.5px; color: #DC2626; text-decoration: none;" onmouseover="this.style.background='#FDF2F2'" onmouseout="this.style.background='transparent'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span>Sign Out</span>
                </a>
            </div>
        </div>
    </div>
</header>

<script>
    function toggleNotificationDropdown() {
        const menu = document.getElementById('notifDropdownMenu');
        const pMenu = document.getElementById('userProfileDropdownMenu');
        if (pMenu) pMenu.style.display = 'none';
        if (menu) {
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
    }

    function toggleProfileDropdown() {
        const menu = document.getElementById('userProfileDropdownMenu');
        const nMenu = document.getElementById('notifDropdownMenu');
        if (nMenu) nMenu.style.display = 'none';
        if (menu) {
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
    }

    // Close on outside click
    document.addEventListener('click', function(e) {
        const notifContainer = document.getElementById('notifDropdownContainer');
        const notifMenu = document.getElementById('notifDropdownMenu');
        if (notifContainer && notifMenu && !notifContainer.contains(e.target)) {
            notifMenu.style.display = 'none';
        }

        const profileContainer = document.getElementById('userProfileDropdownWrap');
        const profileMenu = document.getElementById('userProfileDropdownMenu');
        if (profileContainer && profileMenu && !profileContainer.contains(e.target)) {
            profileMenu.style.display = 'none';
        }
    });
</script>
