<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;

class TaxService
{
    public static function initTables(): void
    {
        $db = Database::getConnection();

        // 1. GST Settings Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS gst_settings (
                website_id VARCHAR(36) PRIMARY KEY,
                legal_name VARCHAR(150) NOT NULL DEFAULT 'Nutronyx Botanicals Private Limited',
                gstin VARCHAR(20) NOT NULL DEFAULT '36AAACN1234F1Z8',
                state_code VARCHAR(50) NOT NULL DEFAULT '36 - Telangana',
                pan_number VARCHAR(20) NOT NULL DEFAULT 'AAACN1234F',
                pricing_mode VARCHAR(20) NOT NULL DEFAULT 'INCLUSIVE',
                shipping_gst_rate REAL NOT NULL DEFAULT 18.0,
                is_gst_enabled INTEGER NOT NULL DEFAULT 1,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Ensure is_gst_enabled column exists if table was created previously
        try {
            $cols = $db->query("PRAGMA table_info(gst_settings)")->fetchAll();
            $hasGstCol = false;
            foreach ($cols as $col) {
                if ($col['name'] === 'is_gst_enabled') {
                    $hasGstCol = true;
                    break;
                }
            }
            if (!$hasGstCol) {
                $db->exec("ALTER TABLE gst_settings ADD COLUMN is_gst_enabled INTEGER NOT NULL DEFAULT 1");
            }
        } catch (\Throwable $e) {}

        // Seed default GST settings
        $db->exec("
            INSERT OR IGNORE INTO gst_settings (website_id, legal_name, gstin, state_code, pan_number, pricing_mode, shipping_gst_rate, is_gst_enabled)
            VALUES ('web_nutronyx_d2c', 'Nutronyx Botanicals Private Limited', '36AAACN1234F1Z8', '36 - Telangana', 'AAACN1234F', 'INCLUSIVE', 18.0, 1)
        ");

        // 2. HSN Tax Mappings Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS hsn_tax_mappings (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL,
                hsn_code VARCHAR(20) NOT NULL,
                commodity_title VARCHAR(150) NOT NULL,
                gst_rate REAL NOT NULL,
                cgst_rate REAL NOT NULL,
                sgst_rate REAL NOT NULL,
                igst_rate REAL NOT NULL,
                description TEXT,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $wid = Context::getWebsiteId();
        $cnt = (int)$db->query("SELECT count(*) FROM hsn_tax_mappings WHERE website_id = '{$wid}'")->fetchColumn();
        if ($cnt === 0) {
            $defaults = [
                ['hsn_0901', $wid, '0901', 'Roasted Coffee & Botanical Infusions', 5.0, 2.5, 2.5, 5.0, 'Standard 5% rate for packaged organic roasted coffee & herbal tea'],
                ['hsn_2106', $wid, '2106', 'Dietary Botanical Supplements & Nutraceuticals', 18.0, 9.0, 9.0, 18.0, 'Standard 18% GST for packaged health & wellness nutraceuticals'],
                ['hsn_3004', $wid, '3004', 'Classical Ayurvedic & Herbal Preparations', 12.0, 6.0, 6.0, 12.0, 'Medicinal grade herbal extracts and wellness tinctures'],
                ['hsn_1211', $wid, '1211', 'Raw Whole Dried Botanicals & Seeds', 0.0, 0.0, 0.0, 0.0, 'Nil GST / 0% Exempted whole raw farm botanicals'],
                ['hsn_9968', $wid, '9968', 'Postal, Courier & Surface Express Logistics', 18.0, 9.0, 9.0, 18.0, 'Statutory 18% freight charge GST']
            ];
            $stmt = $db->prepare("INSERT INTO hsn_tax_mappings (id, website_id, hsn_code, commodity_title, gst_rate, cgst_rate, sgst_rate, igst_rate, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($defaults as $d) {
                $stmt->execute($d);
            }
        }
    }

    public static function getGstSettings(): array
    {
        self::initTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("SELECT * FROM gst_settings WHERE website_id = :wid");
        $stmt->execute([':wid' => $wid]);
        $row = $stmt->fetch();

        if (!$row) {
            return [
                'website_id' => $wid,
                'legal_name' => 'Nutronyx Botanicals Private Limited',
                'gstin' => '36AAACN1234F1Z8',
                'state_code' => '36 - Telangana',
                'pan_number' => 'AAACN1234F',
                'pricing_mode' => 'INCLUSIVE',
                'shipping_gst_rate' => 18.0,
                'is_gst_enabled' => 1
            ];
        }

        $row['is_gst_enabled'] = (int)($row['is_gst_enabled'] ?? 1);
        return $row;
    }

    public static function toggleGst(bool $enable): bool
    {
        self::initTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("UPDATE gst_settings SET is_gst_enabled = :en, updated_at = CURRENT_TIMESTAMP WHERE website_id = :wid");
        $res = $stmt->execute([
            ':en' => $enable ? 1 : 0,
            ':wid' => $wid
        ]);
        $statusStr = $enable ? 'ENABLED (GST Registered)' : 'DISABLED (Unregistered/Exempt)';
        AuditService::log('gst.toggle', 'tax', $wid, "Toggled GST collection: {$statusStr}");
        return $res;
    }

    public static function updateGstSettings(array $data): bool
    {
        self::initTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $isGst = isset($data['is_gst_enabled']) ? (int)$data['is_gst_enabled'] : 1;

        $stmt = $db->prepare("
            INSERT INTO gst_settings (website_id, legal_name, gstin, state_code, pan_number, pricing_mode, shipping_gst_rate, is_gst_enabled, updated_at)
            VALUES (:wid, :legal, :gstin, :state, :pan, :pmode, :shiprate, :is_gst, CURRENT_TIMESTAMP)
            ON CONFLICT(website_id) DO UPDATE SET
                legal_name = excluded.legal_name,
                gstin = excluded.gstin,
                state_code = excluded.state_code,
                pan_number = excluded.pan_number,
                pricing_mode = excluded.pricing_mode,
                shipping_gst_rate = excluded.shipping_gst_rate,
                is_gst_enabled = excluded.is_gst_enabled,
                updated_at = CURRENT_TIMESTAMP
        ");

        $res = $stmt->execute([
            ':wid' => $wid,
            ':legal' => trim($data['legal_name'] ?? 'Nutronyx Botanicals Private Limited'),
            ':gstin' => strtoupper(trim($data['gstin'] ?? '36AAACN1234F1Z8')),
            ':state' => trim($data['state_code'] ?? '36 - Telangana'),
            ':pan' => strtoupper(trim($data['pan_number'] ?? 'AAACN1234F')),
            ':pmode' => ($data['pricing_mode'] ?? 'INCLUSIVE') === 'EXCLUSIVE' ? 'EXCLUSIVE' : 'INCLUSIVE',
            ':shiprate' => (float)($data['shipping_gst_rate'] ?? 18.0),
            ':is_gst' => $isGst
        ]);

        AuditService::log('gst.settings.update', 'tax', $wid, "Updated statutory GST registration profile");
        return $res;
    }

    public static function getHsnMappings(): array
    {
        self::initTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("SELECT * FROM hsn_tax_mappings WHERE website_id = :wid ORDER BY gst_rate ASC, hsn_code ASC");
        $stmt->execute([':wid' => $wid]);
        return $stmt->fetchAll();
    }

    public static function addHsnMapping(array $data): string
    {
        self::initTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $id = 'hsn_' . bin2hex(random_bytes(6));
        $gstRate = (float)($data['gst_rate'] ?? 18.0);
        $halfRate = round($gstRate / 2, 2);

        $stmt = $db->prepare("
            INSERT INTO hsn_tax_mappings (id, website_id, hsn_code, commodity_title, gst_rate, cgst_rate, sgst_rate, igst_rate, description)
            VALUES (:id, :wid, :code, :title, :gst, :cgst, :sgst, :igst, :desc)
        ");
        $stmt->execute([
            ':id' => $id,
            ':wid' => $wid,
            ':code' => trim($data['hsn_code'] ?? '0000'),
            ':title' => trim($data['commodity_title'] ?? 'Custom Commodity'),
            ':gst' => $gstRate,
            ':cgst' => $halfRate,
            ':sgst' => $halfRate,
            ':igst' => $gstRate,
            ':desc' => trim($data['description'] ?? '')
        ]);

        AuditService::log('hsn.mapping.create', 'tax', $id, "Added HSN {$data['hsn_code']} @ {$gstRate}%");
        return $id;
    }

    public static function deleteHsnMapping(string $id): bool
    {
        self::initTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("DELETE FROM hsn_tax_mappings WHERE id = :id AND website_id = :wid");
        $res = $stmt->execute([':id' => $id, ':wid' => $wid]);
        AuditService::log('hsn.mapping.delete', 'tax', $id, "Deleted HSN code mapping");
        return $res;
    }

    public static function calculateGstBreakdown(float $grossAmount, float $gstRate, bool $isInterState, bool $isInclusive = true): array
    {
        if ($isInclusive) {
            $taxableAmount = round($grossAmount / (1 + ($gstRate / 100)), 2);
            $totalTax = round($grossAmount - $taxableAmount, 2);
        } else {
            $taxableAmount = round($grossAmount, 2);
            $totalTax = round($grossAmount * ($gstRate / 100), 2);
            $grossAmount = round($taxableAmount + $totalTax, 2);
        }

        if ($isInterState) {
            $cgst = 0.0;
            $sgst = 0.0;
            $igst = $totalTax;
        } else {
            $cgst = round($totalTax / 2, 2);
            $sgst = round($totalTax - $cgst, 2);
            $igst = 0.0;
        }

        return [
            'gross_amount' => $grossAmount,
            'taxable_amount' => $taxableAmount,
            'gst_rate' => $gstRate,
            'is_inter_state' => $isInterState,
            'is_inclusive' => $isInclusive,
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'igst_amount' => $igst,
            'total_tax' => $totalTax
        ];
    }

    public static function getRules(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM tax_rules WHERE website_id = :wid ORDER BY created_at ASC");
        $stmt->execute([':wid' => Context::getWebsiteId()]);
        return $stmt->fetchAll();
    }

    public static function updateRule(string $id, float $ratePercentage, int $isInclusive, int $appliesToShipping): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE tax_rules 
            SET rate_percentage = :rate, is_inclusive = :inc, applies_to_shipping = :ship
            WHERE id = :id AND website_id = :wid
        ");
        $res = $stmt->execute([
            ':rate' => $ratePercentage,
            ':inc' => $isInclusive,
            ':ship' => $appliesToShipping,
            ':id' => $id,
            ':wid' => Context::getWebsiteId()
        ]);

        AuditService::log('tax.update', 'tax_rule', $id, "Updated statutory tax rule rate to {$ratePercentage}%");
        return $res;
    }
}
