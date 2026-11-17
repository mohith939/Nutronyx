<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;
use PDO;

class TemplateService
{
    private static function ensureTable(): void
    {
        $db = Database::getConnection();
        $db->exec("
            CREATE TABLE IF NOT EXISTS document_templates (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL,
                doc_type VARCHAR(20) NOT NULL,
                theme_style VARCHAR(30) DEFAULT 'classic_executive',
                show_logo INTEGER DEFAULT 1,
                show_company_address INTEGER DEFAULT 1,
                show_contact_phone INTEGER DEFAULT 1,
                show_contact_email INTEGER DEFAULT 1,
                show_carrier_info INTEGER DEFAULT 1,
                show_payment_method INTEGER DEFAULT 1,
                show_terms_and_conditions INTEGER DEFAULT 1,
                custom_header_title TEXT,
                custom_footer_note TEXT,
                terms_text TEXT,
                receipt_width VARCHAR(20) DEFAULT '80mm',
                logo_url TEXT DEFAULT 'assets/images/nutronyx-full-brand-logo.png',
                logo_position VARCHAR(20) DEFAULT 'left',
                logo_height INTEGER DEFAULT 44,
                primary_color VARCHAR(20) DEFAULT '#143D24',
                accent_color VARCHAR(20) DEFAULT '#2B6E4E',
                company_name VARCHAR(150) DEFAULT 'Nutronyx Botanicals Pvt. Ltd.',
                brand_name VARCHAR(100) DEFAULT 'NUTRONYX',
                company_address TEXT DEFAULT 'Plot 42, Botanical Corridor, Whitefield, Bengaluru, KA 560066',
                contact_phone VARCHAR(50) DEFAULT '+91 800 234 5678',
                contact_email VARCHAR(100) DEFAULT 'care@nutronyx.com',
                website_url VARCHAR(100) DEFAULT 'https://nutronyx.in',
                tax_id VARCHAR(50) DEFAULT 'GSTIN: 29ABCDE1234F1Z5',
                show_tax_id INTEGER DEFAULT 1,
                invoice_prefix VARCHAR(20) DEFAULT 'INV',
                signatory_name VARCHAR(100) DEFAULT 'Authorized Signatory',
                signatory_title VARCHAR(100) DEFAULT 'Compliance & Accounts Officer',
                show_signatory INTEGER DEFAULT 1,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(website_id, doc_type)
            )
        ");

        // Schema migration check for existing tables
        $existingCols = [];
        $res = $db->query("PRAGMA table_info(document_templates)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res as $r) {
            $existingCols[$r['name']] = true;
        }

        $newCols = [
            'logo_url' => "TEXT DEFAULT 'assets/images/nutronyx-full-brand-logo.png'",
            'logo_position' => "VARCHAR(20) DEFAULT 'left'",
            'logo_height' => "INTEGER DEFAULT 44",
            'primary_color' => "VARCHAR(20) DEFAULT '#143D24'",
            'accent_color' => "VARCHAR(20) DEFAULT '#2B6E4E'",
            'company_name' => "VARCHAR(150) DEFAULT 'Nutronyx Botanicals Pvt. Ltd.'",
            'brand_name' => "VARCHAR(100) DEFAULT 'NUTRONYX'",
            'company_address' => "TEXT DEFAULT 'Plot 42, Botanical Corridor, Whitefield, Bengaluru, KA 560066'",
            'contact_phone' => "VARCHAR(50) DEFAULT '+91 800 234 5678'",
            'contact_email' => "VARCHAR(100) DEFAULT 'care@nutronyx.com'",
            'website_url' => "VARCHAR(100) DEFAULT 'https://nutronyx.in'",
            'tax_id' => "VARCHAR(50) DEFAULT 'GSTIN: 29ABCDE1234F1Z5'",
            'show_tax_id' => "INTEGER DEFAULT 1",
            'invoice_prefix' => "VARCHAR(20) DEFAULT 'INV'",
            'signatory_name' => "VARCHAR(100) DEFAULT 'Authorized Signatory'",
            'signatory_title' => "VARCHAR(100) DEFAULT 'Compliance & Accounts Officer'",
            'show_signatory' => "INTEGER DEFAULT 1",
            'signature_url' => "TEXT DEFAULT 'assets/images/sample-signature.png'",
            'has_gst' => "INTEGER DEFAULT 1",
            'disabled_themes' => "TEXT DEFAULT '[]'"
        ];

        foreach ($newCols as $col => $def) {
            if (!isset($existingCols[$col])) {
                $db->exec("ALTER TABLE document_templates ADD COLUMN {$col} {$def}");
            }
        }
    }

    public static function getTemplate(string $docType): array
    {
        self::ensureTable();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("SELECT * FROM document_templates WHERE website_id = :wid AND doc_type = :dt LIMIT 1");
        $stmt->execute([':wid' => $wid, ':dt' => $docType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $defaults = self::getDefaults($docType);

        if ($row) {
            foreach ($row as $k => $v) {
                if ($v !== null) {
                    $defaults[$k] = $v;
                }
            }
            // If business does not have GST, ensure tax_id is empty if not set
            if (isset($defaults['has_gst']) && empty($defaults['has_gst'])) {
                if (empty($row['tax_id'])) {
                    $defaults['tax_id'] = '';
                }
            }
            return $defaults;
        }

        return $defaults;
    }

    public static function getDefaults(string $docType): array
    {
        if ($docType === 'invoice') {
            return [
                'doc_type' => 'invoice',
                'theme_style' => 'classic_executive',
                'show_logo' => 1,
                'logo_url' => 'assets/images/nutronyx-full-brand-logo.png',
                'logo_position' => 'left',
                'logo_height' => 44,
                'primary_color' => '#143D24',
                'accent_color' => '#2B6E4E',
                'company_name' => 'Nutronyx Botanicals Pvt. Ltd.',
                'brand_name' => 'NUTRONYX',
                'company_address' => 'Plot 42, Botanical Corridor, Whitefield, Bengaluru, KA 560066',
                'contact_phone' => '+91 800 234 5678',
                'contact_email' => 'care@nutronyx.com',
                'website_url' => 'https://nutronyx.in',
                'tax_id' => 'GSTIN: 29ABCDE1234F1Z5',
                'show_tax_id' => 1,
                'invoice_prefix' => 'INV',
                'signatory_name' => 'Authorized Signatory',
                'signatory_title' => 'Compliance & Accounts Officer',
                'show_signatory' => 1,
                'show_company_address' => 1,
                'show_contact_phone' => 1,
                'show_contact_email' => 1,
                'show_carrier_info' => 1,
                'show_payment_method' => 1,
                'show_terms_and_conditions' => 1,
                'custom_header_title' => 'TAX INVOICE / BILL OF SUPPLY',
                'custom_footer_note' => 'Thank you for choosing Nutronyx. All botanical extracts are certified for purity and freshness.',
                'terms_text' => "1. Goods once sold may be returned within 7 days in sealed condition.\n2. Invoices are subject to state jurisdiction.\n3. Digital computer-generated invoice &mdash; requires no physical signature.",
                'receipt_width' => '80mm',
                'signature_url' => 'assets/images/sample-signature.png',
                'has_gst' => 1,
                'disabled_themes' => '[]'
            ];
        }

        return [
            'doc_type' => 'receipt',
            'theme_style' => 'thermal_compact',
            'show_logo' => 1,
            'logo_url' => 'assets/images/nutronyx-full-brand-logo.png',
            'logo_position' => 'center',
            'logo_height' => 38,
            'primary_color' => '#143D24',
            'accent_color' => '#2B6E4E',
            'company_name' => 'Nutronyx Botanicals Pvt. Ltd.',
            'brand_name' => 'NUTRONYX',
            'company_address' => 'Whitefield Store #4, Bengaluru',
            'contact_phone' => '+91 800 234 5678',
            'contact_email' => 'care@nutronyx.com',
            'website_url' => 'https://nutronyx.in',
            'tax_id' => 'GSTIN: 29ABCDE1234F1Z5',
            'show_tax_id' => 1,
            'invoice_prefix' => 'REC',
            'signatory_name' => 'Store POS Terminal 01',
            'signatory_title' => 'Cashier / Dispatch Lead',
            'show_signatory' => 0,
            'show_company_address' => 1,
            'show_contact_phone' => 1,
            'show_contact_email' => 1,
            'show_carrier_info' => 1,
            'show_payment_method' => 1,
            'show_terms_and_conditions' => 1,
            'custom_header_title' => 'PAYMENT & FULFILLMENT RECEIPT',
            'custom_footer_note' => 'Thank you for your visit! Keep this slip for pickup or returns.',
            'terms_text' => "Support: support@nutronyx.com | Mon-Sat 9AM-8PM",
            'receipt_width' => '80mm',
            'signature_url' => null,
            'has_gst' => 1,
            'disabled_themes' => '[]'
        ];
    }

    public static function saveTemplate(string $docType, array $data): bool
    {
        self::ensureTable();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $id = 'tpl_' . bin2hex(random_bytes(6));

        $stmt = $db->prepare("
            INSERT INTO document_templates (
                id, website_id, doc_type, theme_style, show_logo, show_company_address,
                show_contact_phone, show_contact_email, show_carrier_info, show_payment_method,
                show_terms_and_conditions, custom_header_title, custom_footer_note, terms_text, receipt_width,
                logo_url, logo_position, logo_height, primary_color, accent_color,
                company_name, brand_name, company_address, contact_phone, contact_email,
                website_url, tax_id, show_tax_id, invoice_prefix, signatory_name, signatory_title, show_signatory,
                signature_url, has_gst, disabled_themes,
                updated_at
            ) VALUES (
                :id, :wid, :dt, :theme, :logo, :addr, :phone, :email, :carrier, :pm, :terms_flag, :title, :footer, :terms, :width,
                :l_url, :l_pos, :l_h, :p_col, :a_col, :c_name, :b_name, :c_addr, :c_phone, :c_email,
                :w_url, :t_id, :st_id, :inv_pref, :sig_name, :sig_title, :show_sig,
                :sig_url, :has_gst, :dis_thm,
                CURRENT_TIMESTAMP
            ) ON CONFLICT(website_id, doc_type) DO UPDATE SET
                theme_style = excluded.theme_style,
                show_logo = excluded.show_logo,
                show_company_address = excluded.show_company_address,
                show_contact_phone = excluded.show_contact_phone,
                show_contact_email = excluded.show_contact_email,
                show_carrier_info = excluded.show_carrier_info,
                show_payment_method = excluded.show_payment_method,
                show_terms_and_conditions = excluded.show_terms_and_conditions,
                custom_header_title = excluded.custom_header_title,
                custom_footer_note = excluded.custom_footer_note,
                terms_text = excluded.terms_text,
                receipt_width = excluded.receipt_width,
                logo_url = excluded.logo_url,
                logo_position = excluded.logo_position,
                logo_height = excluded.logo_height,
                primary_color = excluded.primary_color,
                accent_color = excluded.accent_color,
                company_name = excluded.company_name,
                brand_name = excluded.brand_name,
                company_address = excluded.company_address,
                contact_phone = excluded.contact_phone,
                contact_email = excluded.contact_email,
                website_url = excluded.website_url,
                tax_id = excluded.tax_id,
                show_tax_id = excluded.show_tax_id,
                invoice_prefix = excluded.invoice_prefix,
                signatory_name = excluded.signatory_name,
                signatory_title = excluded.signatory_title,
                show_signatory = excluded.show_signatory,
                signature_url = excluded.signature_url,
                has_gst = excluded.has_gst,
                disabled_themes = excluded.disabled_themes,
                updated_at = CURRENT_TIMESTAMP
        ");

        return $stmt->execute([
            ':id' => $id,
            ':wid' => $wid,
            ':dt' => $docType,
            ':theme' => $data['theme_style'] ?? 'classic_executive',
            ':logo' => !empty($data['show_logo']) ? 1 : 0,
            ':addr' => !empty($data['show_company_address']) ? 1 : 0,
            ':phone' => !empty($data['show_contact_phone']) ? 1 : 0,
            ':email' => !empty($data['show_contact_email']) ? 1 : 0,
            ':carrier' => !empty($data['show_carrier_info']) ? 1 : 0,
            ':pm' => !empty($data['show_payment_method']) ? 1 : 0,
            ':terms_flag' => !empty($data['show_terms_and_conditions']) ? 1 : 0,
            ':title' => trim($data['custom_header_title'] ?? ''),
            ':footer' => trim($data['custom_footer_note'] ?? ''),
            ':terms' => trim($data['terms_text'] ?? ''),
            ':width' => $data['receipt_width'] ?? '80mm',
            ':l_url' => trim($data['logo_url'] ?? 'assets/images/nutronyx-full-brand-logo.png'),
            ':l_pos' => $data['logo_position'] ?? 'left',
            ':l_h' => (int)($data['logo_height'] ?? 44),
            ':p_col' => trim($data['primary_color'] ?? '#143D24'),
            ':a_col' => trim($data['accent_color'] ?? '#2B6E4E'),
            ':c_name' => trim($data['company_name'] ?? 'Nutronyx Botanicals Pvt. Ltd.'),
            ':b_name' => trim($data['brand_name'] ?? 'NUTRONYX'),
            ':c_addr' => trim($data['company_address'] ?? 'Plot 42, Botanical Corridor, Whitefield, Bengaluru, KA 560066'),
            ':c_phone' => trim($data['contact_phone'] ?? '+91 800 234 5678'),
            ':c_email' => trim($data['contact_email'] ?? 'care@nutronyx.com'),
            ':w_url' => trim($data['website_url'] ?? 'https://nutronyx.in'),
            ':t_id' => trim($data['tax_id'] ?? ''),
            ':st_id' => !empty($data['show_tax_id']) ? 1 : 0,
            ':inv_pref' => trim($data['invoice_prefix'] ?? ($docType === 'invoice' ? 'INV' : 'REC')),
            ':sig_name' => trim($data['signatory_name'] ?? 'Authorized Signatory'),
            ':sig_title' => trim($data['signatory_title'] ?? 'Compliance & Accounts Officer'),
            ':show_sig' => !empty($data['show_signatory']) ? 1 : 0,
            ':sig_url' => !empty($data['signature_url']) ? trim($data['signature_url']) : null,
            ':has_gst' => isset($data['has_gst']) ? (!empty($data['has_gst']) ? 1 : 0) : (!empty($data['tax_id']) ? 1 : 0),
            ':dis_thm' => is_array($data['disabled_themes'] ?? null) ? json_encode($data['disabled_themes']) : (is_string($data['disabled_themes'] ?? null) ? $data['disabled_themes'] : '[]')
        ]);
    }
}
