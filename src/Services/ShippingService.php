<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;

class ShippingService
{
    private static function ensureIntegrationSchema(): void
    {
        $db = Database::getConnection();
        $db->exec("
            CREATE TABLE IF NOT EXISTS shipping_integrations (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL,
                carrier_code VARCHAR(50) NOT NULL,
                carrier_name VARCHAR(100) NOT NULL,
                api_key TEXT,
                api_secret TEXT,
                account_number VARCHAR(100),
                environment VARCHAR(20) DEFAULT 'SANDBOX', -- SANDBOX, PRODUCTION
                status VARCHAR(20) DEFAULT 'DISCONNECTED', -- CONNECTED, DISCONNECTED
                webhook_url VARCHAR(255),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (website_id, carrier_code)
            );
        ");

        $wid = Context::getWebsiteId();

        // Nutronyx exclusively operates on Ekart Logistics Enterprise API
        $defaultPartners = [
            ['ekart', 'Ekart Logistics Enterprise', '', '', '', 'PRODUCTION', 'DISCONNECTED']
        ];

        // Purge any legacy carriers so only Ekart remains
        $db->exec("DELETE FROM shipping_integrations WHERE carrier_code != 'ekart'");

        $insert = $db->prepare("
            INSERT OR IGNORE INTO shipping_integrations (id, website_id, carrier_code, carrier_name, api_key, api_secret, account_number, environment, status)
            VALUES (:id, :wid, :code, :name, :key, :secret, :acct, :env, :status)
        ");
        foreach ($defaultPartners as $p) {
            $insert->execute([
                ':id' => 'ship_int_' . md5($wid . '_' . $p[0]),
                ':wid' => $wid,
                ':code' => $p[0],
                ':name' => $p[1],
                ':key' => $p[2],
                ':secret' => $p[3],
                ':acct' => $p[4],
                ':env' => $p[5],
                ':status' => $p[6]
            ]);
        }
    }

    public static function getMethods(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM shipping_methods WHERE website_id = :wid ORDER BY charge_amount ASC");
        $stmt->execute([':wid' => Context::getWebsiteId()]);
        return $stmt->fetchAll();
    }

    public static function updateMethod(string $id, int $chargeAmountPaise, int $minFreeAmountPaise, int $isActive): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE shipping_methods 
            SET charge_amount = :charge, min_order_free_amount = :free, is_active = :act
            WHERE id = :id AND website_id = :wid
        ");
        $res = $stmt->execute([
            ':charge' => $chargeAmountPaise,
            ':free' => $minFreeAmountPaise,
            ':act' => $isActive,
            ':id' => $id,
            ':wid' => Context::getWebsiteId()
        ]);
        AuditService::log('shipping.update', 'shipping_method', $id, "Updated shipping method rates");
        return $res;
    }

    public static function getIntegrations(): array
    {
        self::ensureIntegrationSchema();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM shipping_integrations WHERE website_id = :wid ORDER BY (status = 'CONNECTED') DESC, carrier_name ASC");
        $stmt->execute([':wid' => Context::getWebsiteId()]);
        return $stmt->fetchAll();
    }

    public static function saveIntegration(string $carrierCode, array $data): bool
    {
        self::ensureIntegrationSchema();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $status = (!empty($data['api_key'])) ? 'CONNECTED' : 'DISCONNECTED';

        // Check existing
        $stmtEx = $db->prepare("SELECT * FROM shipping_integrations WHERE carrier_code = :code AND website_id = :wid LIMIT 1");
        $stmtEx->execute([':code' => $carrierCode, ':wid' => $wid]);
        $existing = $stmtEx->fetch() ?: [];

        $apiKey = !empty($data['api_key']) ? trim($data['api_key']) : ($existing['api_key'] ?? '');
        $apiSecret = (!empty($data['api_secret']) && $data['api_secret'] !== '•••••••••••••') 
            ? trim($data['api_secret']) 
            : ($existing['api_secret'] ?? '');
        $acct = !empty($data['account_number']) ? trim($data['account_number']) : ($existing['account_number'] ?? '');
        $env = !empty($data['environment']) ? trim($data['environment']) : ($existing['environment'] ?? 'SANDBOX');

        if (!empty($existing['id'])) {
            $stmt = $db->prepare("
                UPDATE shipping_integrations 
                SET api_key = :key, api_secret = :secret, account_number = :acct, 
                    environment = :env, status = :status, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $res = $stmt->execute([
                ':key' => $apiKey,
                ':secret' => $apiSecret,
                ':acct' => $acct,
                ':env' => $env,
                ':status' => $status,
                ':id' => $existing['id']
            ]);
        } else {
            $newId = 'ship_int_' . md5($wid . '_' . $carrierCode);
            $stmt = $db->prepare("
                INSERT INTO shipping_integrations (id, website_id, carrier_code, carrier_name, api_key, api_secret, account_number, environment, status, updated_at)
                VALUES (:id, :wid, :code, :name, :key, :secret, :acct, :env, :status, CURRENT_TIMESTAMP)
            ");
            $res = $stmt->execute([
                ':id' => $newId,
                ':wid' => $wid,
                ':code' => $carrierCode,
                ':name' => ucfirst($carrierCode),
                ':key' => $apiKey,
                ':secret' => $apiSecret,
                ':acct' => $acct,
                ':env' => $env,
                ':status' => $status
            ]);
        }

        AuditService::log('shipping_api.update', 'carrier_integration', $carrierCode, "Updated API keys for {$carrierCode} (Status: {$status})");
        return $res;
    }

    public static function getSettings(): array
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $stmt = $db->prepare("SELECT * FROM shipping_settings WHERE website_id = :wid LIMIT 1");
        $stmt->execute([':wid' => $wid]);
        $row = $stmt->fetch();
        if (!$row) {
            $default = [
                'website_id' => $wid,
                'min_order_amount' => 19900,
                'max_weight_grams' => 25000,
                'default_rate_amount' => 4900,
                'free_shipping_threshold' => 99900,
                'cod_charge_amount' => 4000,
                'gst_percentage' => 18.0,
                'min_delivery_days' => 2,
                'max_delivery_days' => 5,
                'shipping_enabled' => 1,
                'cod_enabled' => 1,
                'charge_gst' => 1,
                'delivery_method' => 'courier',
                'shopper_delivery_name' => 'Standard Ground Express',
                'shopper_delivery_note' => 'Delivered securely via our verified courier partner network.'
            ];
            $db->prepare("
                INSERT OR REPLACE INTO shipping_settings 
                (website_id, min_order_amount, max_weight_grams, default_rate_amount, free_shipping_threshold, cod_charge_amount, gst_percentage, min_delivery_days, max_delivery_days, shipping_enabled, cod_enabled, charge_gst, delivery_method, shopper_delivery_name, shopper_delivery_note)
                VALUES (:wid, :min_o, :max_w, :def_r, :free_t, :cod_c, :gst_p, :min_d, :max_d, :s_en, :cod_en, :c_gst, :d_m, :s_nm, :s_nt)
            ")->execute([
                ':wid' => $wid,
                ':min_o' => $default['min_order_amount'],
                ':max_w' => $default['max_weight_grams'],
                ':def_r' => $default['default_rate_amount'],
                ':free_t' => $default['free_shipping_threshold'],
                ':cod_c' => $default['cod_charge_amount'],
                ':gst_p' => $default['gst_percentage'],
                ':min_d' => $default['min_delivery_days'],
                ':max_d' => $default['max_delivery_days'],
                ':s_en' => $default['shipping_enabled'],
                ':cod_en' => $default['cod_enabled'],
                ':c_gst' => $default['charge_gst'],
                ':d_m' => $default['delivery_method'],
                ':s_nm' => $default['shopper_delivery_name'],
                ':s_nt' => $default['shopper_delivery_note']
            ]);
            return $default;
        }
        return $row;
    }

    public static function saveSettings(array $data): bool
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $stmt = $db->prepare("
            INSERT OR REPLACE INTO shipping_settings 
            (website_id, min_order_amount, max_weight_grams, default_rate_amount, free_shipping_threshold, cod_charge_amount, gst_percentage, min_delivery_days, max_delivery_days, shipping_enabled, cod_enabled, charge_gst, delivery_method, shopper_delivery_name, shopper_delivery_note, updated_at)
            VALUES (:wid, :min_o, :max_w, :def_r, :free_t, :cod_c, :gst_p, :min_d, :max_d, :s_en, :cod_en, :c_gst, :d_m, :s_nm, :s_nt, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([
            ':wid' => $wid,
            ':min_o' => (int)($data['min_order_amount'] ?? 0),
            ':max_w' => (int)($data['max_weight_grams'] ?? 25000),
            ':def_r' => (int)($data['default_rate_amount'] ?? 4900),
            ':free_t' => (int)($data['free_shipping_threshold'] ?? 99900),
            ':cod_c' => (int)($data['cod_charge_amount'] ?? 4000),
            ':gst_p' => (float)($data['gst_percentage'] ?? 18.0),
            ':min_d' => (int)($data['min_delivery_days'] ?? 2),
            ':max_d' => (int)($data['max_delivery_days'] ?? 5),
            ':s_en' => !empty($data['shipping_enabled']) ? 1 : 0,
            ':cod_en' => !empty($data['cod_enabled']) ? 1 : 0,
            ':c_gst' => !empty($data['charge_gst']) ? 1 : 0,
            ':d_m' => $data['delivery_method'] ?? 'courier',
            ':s_nm' => trim($data['shopper_delivery_name'] ?? 'Standard Delivery'),
            ':s_nt' => trim($data['shopper_delivery_note'] ?? '')
        ]);
    }

    public static function getPriorityRules(): array
    {
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $stmt = $db->prepare("SELECT * FROM shipping_priority_rules WHERE website_id = :wid ORDER BY priority ASC");
        $stmt->execute([':wid' => $wid]);
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            // Seed 2 initial rules
            $seed = [
                ['rule_1', 1, 'Free Shipping on Cart Over ₹999', 'MIN_ORDER', '99900', 0, 1],
                ['rule_2', 2, 'Heavy Weight Surcharge (>5kg)', 'MIN_WEIGHT', '5000', 9900, 1]
            ];
            $ins = $db->prepare("
                INSERT OR IGNORE INTO shipping_priority_rules (id, website_id, priority, name, condition_type, condition_value, rate_amount, is_active)
                VALUES (:id, :wid, :p, :nm, :ct, :cv, :r, :a)
            ");
            foreach ($seed as $s) {
                $ins->execute([
                    ':id' => $s[0],
                    ':wid' => $wid,
                    ':p' => $s[1],
                    ':nm' => $s[2],
                    ':ct' => $s[3],
                    ':cv' => $s[4],
                    ':r' => $s[5],
                    ':a' => $s[6]
                ]);
            }
            $stmt->execute([':wid' => $wid]);
            return $stmt->fetchAll();
        }
        return $rows;
    }

    public static function addPriorityRule(string $name, string $conditionType, string $conditionValue, int $ratePaise, int $priority = 1): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO shipping_priority_rules (id, website_id, priority, name, condition_type, condition_value, rate_amount, is_active)
            VALUES (:id, :wid, :p, :nm, :ct, :cv, :r, 1)
        ");
        return $stmt->execute([
            ':id' => 'rule_' . bin2hex(random_bytes(5)),
            ':wid' => Context::getWebsiteId(),
            ':p' => $priority,
            ':nm' => $name,
            ':ct' => $conditionType,
            ':cv' => $conditionValue,
            ':r' => $ratePaise
        ]);
    }

    public static function deletePriorityRule(string $ruleId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM shipping_priority_rules WHERE id = :id AND website_id = :wid");
        return $stmt->execute([':id' => $ruleId, ':wid' => Context::getWebsiteId()]);
    }
}
