<?php
/**
 * NUTRONYX — Tenant Context Resolver
 * Multi-Website Execution Context & Isolation Helper
 */

declare(strict_types=1);

namespace Nutronyx;

use PDO;

class Context
{
    private static ?string $currentWebsiteId = null;
    private static ?array $currentWebsite = null;
    private static ?string $lockedWebsiteId = null;
    private static bool $isInitializing = false;

    /**
     * Check if the application is locked to a specific tenant / client website
     */
    public static function isLocked(): bool
    {
        if (self::$lockedWebsiteId !== null) {
            return true;
        }

        if (defined('NUTRONYX_TENANT_ID') && !empty(constant('NUTRONYX_TENANT_ID'))) {
            self::$lockedWebsiteId = (string)constant('NUTRONYX_TENANT_ID');
            return true;
        }

        $envTenant = getenv('NUTRONYX_WEBSITE_ID') ?: getenv('TENANT_WEBSITE_ID') ?: ($_ENV['NUTRONYX_WEBSITE_ID'] ?? $_ENV['TENANT_WEBSITE_ID'] ?? null);
        if (!empty($envTenant)) {
            self::$lockedWebsiteId = (string)$envTenant;
            return true;
        }

        return false;
    }

    /**
     * Programmatically lock the current execution context to a single tenant website
     */
    public static function setLockedWebsiteId(?string $id): void
    {
        self::$lockedWebsiteId = $id;
        if ($id !== null) {
            self::setWebsiteId($id);
        }
    }

    public static function init(): void
    {
        if (self::$currentWebsite !== null || self::$isInitializing) {
            return;
        }
        self::$isInitializing = true;

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        // 1. Check for Tenant Lock mode
        if (self::isLocked()) {
            self::$currentWebsiteId = self::$lockedWebsiteId;
            $_SESSION['nutronyx_active_website_id'] = self::$currentWebsiteId;
            unset($_GET['switch_website']);
        } else {
            // 2. Handle website switching query parameter with strict authorization check
            if (!empty($_GET['switch_website'])) {
                $switchId = trim((string)$_GET['switch_website']);
                $canSwitch = false;

                if (self::canSwitchStores()) {
                    $accessibleIds = array_column(self::getAccessibleWebsites(), 'id');
                    if (in_array($switchId, $accessibleIds, true)) {
                        $canSwitch = true;
                    }
                }

                if ($canSwitch) {
                    $_SESSION['nutronyx_active_website_id'] = $switchId;
                }
            }

            // 3. Resolve active website ID
            if (!empty($_SESSION['nutronyx_active_website_id'])) {
                self::$currentWebsiteId = $_SESSION['nutronyx_active_website_id'];
            } else {
                // If user is logged in, check their default or first allowed store
                $userDefault = null;
                if (!empty($_SESSION['nutronyx_admin_id'])) {
                    $accessible = self::getAccessibleWebsites();
                    if (!empty($accessible)) {
                        $userDefault = $accessible[0]['id'];
                    }
                }

                if ($userDefault) {
                    self::$currentWebsiteId = $userDefault;
                } else {
                    $db = Database::getConnection();
                    $stmt = $db->query("SELECT id FROM websites WHERE is_default = 1 LIMIT 1");
                    $defaultId = $stmt->fetchColumn();
                    self::$currentWebsiteId = $defaultId ?: 'web_nutronyx_d2c';
                }
                $_SESSION['nutronyx_active_website_id'] = self::$currentWebsiteId;
            }

            // 4. Verify current website is in user's accessible stores
            if (!empty($_SESSION['nutronyx_admin_id'])) {
                $accessibleIds = array_column(self::getAccessibleWebsites(), 'id');
                if (!empty($accessibleIds) && !in_array(self::$currentWebsiteId, $accessibleIds, true)) {
                    self::$currentWebsiteId = $accessibleIds[0];
                    $_SESSION['nutronyx_active_website_id'] = self::$currentWebsiteId;
                }
            }
        }

        // Load active website details
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM websites WHERE id = :id");
        $stmt->execute([':id' => self::$currentWebsiteId]);
        self::$currentWebsite = $stmt->fetch() ?: [
            'id' => self::$currentWebsiteId,
            'name' => 'Nutronyx D2C Storefront',
            'domain' => 'nutronyx.com'
        ];
        self::$isInitializing = false;
    }

    public static function getWebsiteId(): string
    {
        if (self::$currentWebsiteId === null) {
            self::init();
        }
        return self::$currentWebsiteId;
    }

    public static function getWebsite(): array
    {
        if (self::$currentWebsite === null) {
            self::init();
        }
        return self::$currentWebsite;
    }

    public static function setWebsiteId(string $id): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $_SESSION['nutronyx_active_website_id'] = $id;
        self::$currentWebsiteId = $id;
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM websites WHERE id = :id");
        $stmt->execute([':id' => $id]);
        self::$currentWebsite = $stmt->fetch() ?: [
            'id' => $id,
            'name' => 'Store',
            'domain' => 'nutronyx.com'
        ];
    }

    /**
     * Get accessible websites strictly filtered for the current tenant or staff user
     */
    public static function getAccessibleWebsites(): array
    {
        // 1. If locked to a specific website, return ONLY that website
        if (self::isLocked()) {
            return [self::getWebsite()];
        }

        // 2. Check currently authenticated user's store scoping
        if (!empty($_SESSION['nutronyx_admin_id'])) {
            $userRole = strtoupper((string)($_SESSION['nutronyx_admin_role'] ?? 'OPERATOR'));
            $storeAccessType = strtoupper((string)($_SESSION['nutronyx_admin_store_access'] ?? 'ALL'));

            // Super Admin with ALL access can access all active websites
            if ($userRole === 'SUPER_ADMIN' && $storeAccessType === 'ALL') {
                return self::getAllWebsites();
            }

            // Otherwise, strictly filter by user's assigned allowed_stores
            $allowed = $_SESSION['nutronyx_admin_allowed_stores'] ?? [];
            if (!empty($allowed) && is_array($allowed)) {
                $db = Database::getConnection();
                $placeholders = implode(',', array_fill(0, count($allowed), '?'));
                $stmt = $db->prepare("SELECT * FROM websites WHERE id IN ({$placeholders}) AND status = 'ACTIVE' ORDER BY is_default DESC, name ASC");
                $stmt->execute(array_values($allowed));
                $sites = $stmt->fetchAll();
                if (!empty($sites)) {
                    return $sites;
                }
            }
        }

        // Default fallback to active website or default website from database
        if (self::$currentWebsite !== null) {
            return [self::$currentWebsite];
        }
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM websites WHERE is_default = 1 LIMIT 1");
        $defaultSite = $stmt ? $stmt->fetch() : null;
        if ($defaultSite) {
            return [$defaultSite];
        }
        return [[
            'id' => self::$currentWebsiteId ?: 'web_nutronyx_d2c',
            'name' => 'Nutronyx Store',
            'domain' => 'nutronyx.com'
        ]];
    }

    /**
     * Determine if store switcher dropdown should be enabled/displayed
     * In single-client deployment mode, store switching is permanently disabled.
     */
    public static function canSwitchStores(): bool
    {
        if (getenv('ENABLE_MULTI_STORE_SWITCHER') === '1') {
            if (self::isLocked()) {
                return false;
            }
            $accessible = self::getAccessibleWebsites();
            return count($accessible) > 1;
        }

        return false;
    }

    public static function getAllWebsites(): array
    {
        $db = Database::getConnection();
        return $db->query("SELECT * FROM websites WHERE status = 'ACTIVE' ORDER BY is_default DESC, name ASC")->fetchAll();
    }

    /**
     * Format minor integer currency units (paise/cents) to INR currency format
     * e.g. 149900 -> ₹1,499.00
     */
    public static function formatMoney(int $amountPaise, string $currency = 'INR'): string
    {
        $rupees = $amountPaise / 100.0;
        return '₹' . number_format($rupees, 2);
    }
}
