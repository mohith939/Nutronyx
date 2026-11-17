<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;
use PDO;

class PromotionService
{
    public static function ensureTables(): void
    {
        $db = Database::getConnection();

        // 1. Add extra columns to promotions table if not present
        $cols = [];
        $res = $db->query("PRAGMA table_info(promotions)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res as $r) {
            $cols[$r['name']] = true;
        }

        $newCols = [
            'name' => "VARCHAR(150) DEFAULT ''",
            'campaign_name' => "VARCHAR(150) DEFAULT 'General Campaign'",
            'description' => "TEXT DEFAULT ''",
            'status' => "VARCHAR(30) DEFAULT 'ACTIVE'",
            'discount_given_amount' => "INTEGER DEFAULT 0",
            'revenue_generated_amount' => "INTEGER DEFAULT 0",
            'max_discount_amount' => "INTEGER DEFAULT 0",
            'eligible_categories' => "TEXT DEFAULT '[]'",
            'eligible_products' => "TEXT DEFAULT '[]'",
            'combination_rules' => "TEXT DEFAULT '[]'"
        ];

        foreach ($newCols as $col => $def) {
            if (!isset($cols[$col])) {
                $db->exec("ALTER TABLE promotions ADD COLUMN {$col} {$def}");
            }
        }

        // 2. Promotional Campaigns Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS promotional_campaigns (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL,
                name VARCHAR(150) NOT NULL,
                description TEXT,
                start_date DATE,
                end_date DATE,
                budget_amount INTEGER DEFAULT 0,
                status VARCHAR(30) DEFAULT 'ACTIVE',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 3. Automatic Discounts Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS automatic_discounts (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL,
                name VARCHAR(150) NOT NULL,
                rule_type VARCHAR(50) DEFAULT 'TIERED_CART',
                discount_type VARCHAR(30) DEFAULT 'PERCENTAGE',
                discount_value INTEGER DEFAULT 0,
                min_cart_amount INTEGER DEFAULT 0,
                eligible_items TEXT,
                priority INTEGER DEFAULT 1,
                status VARCHAR(30) DEFAULT 'ACTIVE',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 4. Coupon Redemptions Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS coupon_redemptions (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL,
                promotion_id VARCHAR(36),
                coupon_code VARCHAR(50) NOT NULL,
                order_id VARCHAR(36) NOT NULL,
                customer_id VARCHAR(36),
                customer_name VARCHAR(120),
                customer_phone VARCHAR(50),
                discount_amount INTEGER DEFAULT 0,
                order_total INTEGER DEFAULT 0,
                payment_status VARCHAR(30) DEFAULT 'PAID',
                order_status VARCHAR(30) DEFAULT 'DELIVERED',
                redeemed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public static function getPromotions(): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM promotions WHERE website_id = :wid ORDER BY created_at DESC");
        $stmt->execute([':wid' => Context::getWebsiteId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getPromotionById(string $id): ?array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM promotions WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public static function getCampaigns(): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM promotional_campaigns WHERE website_id = :wid ORDER BY created_at DESC");
        $stmt->execute([':wid' => Context::getWebsiteId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAutomaticDiscounts(): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM automatic_discounts WHERE website_id = :wid ORDER BY priority ASC, created_at DESC");
        $stmt->execute([':wid' => Context::getWebsiteId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRedemptions(int $limit = 50): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM coupon_redemptions WHERE website_id = :wid ORDER BY redeemed_at DESC LIMIT :lim");
        $stmt->bindValue(':wid', Context::getWebsiteId());
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getKpis(): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $promos = self::getPromotions();
        $totalCoupons = count($promos);
        $activeCoupons = count(array_filter($promos, fn($p) => !empty($p['is_active']) && ($p['status'] ?? 'ACTIVE') === 'ACTIVE'));
        
        $totalRedemptions = 0;
        $totalDiscountGivenPaise = 0;
        $totalRevenuePaise = 0;

        foreach ($promos as $p) {
            $totalRedemptions += (int)($p['usage_count'] ?? 0);
            $totalDiscountGivenPaise += (int)($p['discount_given_amount'] ?? 0);
            $totalRevenuePaise += (int)($p['revenue_generated_amount'] ?? 0);
        }


        return [
            'total_coupons' => $totalCoupons,
            'active_coupons' => $activeCoupons,
            'total_redemptions' => $totalRedemptions,
            'total_discount_given_rupees' => $totalDiscountGivenPaise / 100,
            'total_revenue_rupees' => $totalRevenuePaise / 100
        ];
    }

    public static function createPromotion(array $data): string
    {
        self::ensureTables();
        $db = Database::getConnection();
        $id = 'promo_' . bin2hex(random_bytes(6));
        $code = strtoupper(trim($data['code']));
        $discountValue = (int)($data['discount_value'] ?? 0);
        $type = strtoupper(trim($data['discount_type'] ?? 'PERCENTAGE'));
        
        if (in_array($type, ['FIXED', 'FIXED_AMOUNT'], true)) {
            $type = 'FIXED_AMOUNT';
            $discountValue = (int)($discountValue * 100); // convert to paise
        } elseif ($type === 'FREE_SHIPPING') {
            $discountValue = 0;
        } elseif ($type === 'BUY_X_GET_Y') {
            $discountValue = max(1, $discountValue);
        } else {
            $type = 'PERCENTAGE';
        }

        $perCustomerLimit = isset($data['per_customer_limit']) ? (int)$data['per_customer_limit'] : 1;
        $campaign = trim($data['campaign_name'] ?? 'General Promotion');
        $name = trim($data['name'] ?? "{$code} Promotion");
        $desc = trim($data['description'] ?? '');
        $status = strtoupper(trim($data['status'] ?? 'ACTIVE'));
        $isActive = ($status === 'ACTIVE' || $status === 'SCHEDULED') ? 1 : 0;
        $minRupees = (int)($data['minimum_order_rupees'] ?? 0);
        $expiry = !empty($data['expires_at']) ? $data['expires_at'] : null;

        $stmt = $db->prepare("
            INSERT INTO promotions (
                id, website_id, code, name, campaign_name, description, discount_type, discount_value,
                minimum_order_amount, usage_limit, per_customer_limit, status, is_active, expires_at, created_at
            ) VALUES (
                :id, :wid, :code, :name, :campaign, :desc, :type, :val,
                :min, :limit, :per_cust, :st, :act, :exp, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':id' => $id,
            ':wid' => Context::getWebsiteId(),
            ':code' => $code,
            ':name' => $name,
            ':campaign' => $campaign,
            ':desc' => $desc,
            ':type' => $type,
            ':val' => $discountValue,
            ':min' => $minRupees * 100,
            ':limit' => (int)($data['usage_limit'] ?? 500),
            ':per_cust' => $perCustomerLimit,
            ':st' => $status,
            ':act' => $isActive,
            ':exp' => $expiry
        ]);

        AuditService::log('promotion.create', 'promotion', $id, "Created coupon code {$code} for campaign {$campaign}");
        return $id;
    }

    public static function updatePromotion(string $id, array $data): bool
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $code = strtoupper(trim($data['code'] ?? ''));
        $type = strtoupper(trim($data['discount_type'] ?? 'PERCENTAGE'));
        $val = (int)($data['discount_value'] ?? 0);
        if ($type === 'FIXED_AMOUNT') {
            $val = (int)($val * 100);
        }

        $stmt = $db->prepare("
            UPDATE promotions SET
                code = COALESCE(:code, code),
                name = COALESCE(:name, name),
                campaign_name = COALESCE(:campaign, campaign_name),
                discount_type = :type,
                discount_value = :val,
                minimum_order_amount = :min,
                usage_limit = :limit,
                per_customer_limit = :per_cust,
                status = :st,
                is_active = :act,
                expires_at = :exp
            WHERE id = :id AND website_id = :wid
        ");

        $status = strtoupper($data['status'] ?? 'ACTIVE');
        $isActive = ($status === 'ACTIVE') ? 1 : 0;

        return $stmt->execute([
            ':code' => !empty($code) ? $code : null,
            ':name' => $data['name'] ?? null,
            ':campaign' => $data['campaign_name'] ?? null,
            ':type' => $type,
            ':val' => $val,
            ':min' => (int)($data['minimum_order_rupees'] ?? 0) * 100,
            ':limit' => (int)($data['usage_limit'] ?? 500),
            ':per_cust' => (int)($data['per_customer_limit'] ?? 1),
            ':st' => $status,
            ':act' => $isActive,
            ':exp' => !empty($data['expires_at']) ? $data['expires_at'] : null,
            ':id' => $id,
            ':wid' => $wid
        ]);
    }

    public static function duplicatePromotion(string $id): ?string
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("SELECT * FROM promotions WHERE id = :id AND website_id = :wid LIMIT 1");
        $stmt->execute([':id' => $id, ':wid' => $wid]);
        $orig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$orig) return null;

        $newId = 'promo_' . bin2hex(random_bytes(6));
        $suffix = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $newCode = substr($orig['code'], 0, 20) . '_' . $suffix;

        $ins = $db->prepare("
            INSERT INTO promotions (
                id, website_id, code, name, campaign_name, description, discount_type, discount_value,
                minimum_order_amount, usage_limit, per_customer_limit, status, is_active, expires_at, created_at
            ) VALUES (
                :id, :wid, :code, :name, :campaign, :desc, :type, :val,
                :min, :limit, :per_cust, 'ACTIVE', 1, :exp, CURRENT_TIMESTAMP
            )
        ");

        $ins->execute([
            ':id' => $newId,
            ':wid' => $wid,
            ':code' => $newCode,
            ':name' => ($orig['name'] ?? $orig['code']) . ' (Copy)',
            ':campaign' => $orig['campaign_name'] ?? 'General Campaign',
            ':desc' => $orig['description'] ?? '',
            ':type' => $orig['discount_type'],
            ':val' => $orig['discount_value'],
            ':min' => $orig['minimum_order_amount'],
            ':limit' => $orig['usage_limit'],
            ':per_cust' => $orig['per_customer_limit'],
            ':exp' => $orig['expires_at']
        ]);

        return $newId;
    }

    public static function deletePromotion(string $id): bool
    {
        self::ensureTables();
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM promotions WHERE id = :id AND website_id = :wid");
        return $stmt->execute([':id' => $id, ':wid' => Context::getWebsiteId()]);
    }

    public static function toggleActive(string $id, int $active): bool
    {
        self::ensureTables();
        $db = Database::getConnection();
        $status = ($active === 1) ? 'ACTIVE' : 'INACTIVE';
        $stmt = $db->prepare("UPDATE promotions SET is_active = :act, status = :st WHERE id = :id AND website_id = :wid");
        $res = $stmt->execute([':act' => $active, ':st' => $status, ':id' => $id, ':wid' => Context::getWebsiteId()]);
        AuditService::log('promotion.toggle', 'promotion', $id, "Toggled coupon active status to {$active}");
        return $res;
    }

    public static function validateCoupon(string $code, int $subtotalPaise = 0, ?string $customerPhone = null): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("SELECT * FROM promotions WHERE website_id = :wid AND UPPER(code) = UPPER(:code) LIMIT 1");
        $stmt->execute([':wid' => $wid, ':code' => trim($code)]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$promo) {
            return ['valid' => false, 'error' => 'Coupon code does not exist.'];
        }

        if (empty($promo['is_active'])) {
            return ['valid' => false, 'error' => 'This coupon is currently inactive or expired.'];
        }

        if (!empty($promo['usage_limit']) && (int)$promo['usage_count'] >= (int)$promo['usage_limit']) {
            return ['valid' => false, 'error' => 'Coupon maximum redemption limit has been reached.'];
        }

        if ($subtotalPaise > 0 && !empty($promo['minimum_order_amount']) && $subtotalPaise < (int)$promo['minimum_order_amount']) {
            $minRupees = (int)$promo['minimum_order_amount'] / 100;
            return ['valid' => false, 'error' => "Minimum order of ₹{$minRupees} required to apply this coupon."];
        }

        // Per-customer usage check if customer phone or account is specified
        $perCustomerLimit = (int)($promo['per_customer_limit'] ?? 1);
        if ($perCustomerLimit > 0 && !empty($customerPhone)) {
            $phoneClean = preg_replace('/[^0-9]/', '', $customerPhone);
            $cStmt = $db->prepare("
                SELECT COUNT(*) FROM orders 
                WHERE website_id = :wid 
                  AND (shipping_phone = :phone OR shipping_phone = :pclean)
            ");
            $cStmt->execute([':wid' => $wid, ':phone' => $customerPhone, ':pclean' => $phoneClean]);
            $usedCount = (int)$cStmt->fetchColumn();

            if ($usedCount >= $perCustomerLimit) {
                return ['valid' => false, 'error' => "You have already reached the maximum usage limit ({$perCustomerLimit}x) for this coupon."];
            }
        }

        // Calculate discount
        $discountAmount = 0;
        if ($promo['discount_type'] === 'PERCENTAGE') {
            $pct = (int)$promo['discount_value'];
            $discountAmount = (int)round(($subtotalPaise * $pct) / 100);
        } elseif ($promo['discount_type'] === 'FREE_SHIPPING') {
            $discountAmount = 9900; // Free standard shipping in paise
        } else {
            $discountAmount = (int)$promo['discount_value'];
        }

        return [
            'valid' => true,
            'promo' => $promo,
            'discount_amount' => $discountAmount,
            'message' => 'Coupon eligible and applied successfully!'
        ];
    }
}
