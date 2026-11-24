<?php
/**
 * NUTRONYX — Enterprise Multi-Website Database Layer
 * SQLite PDO Engine with Foreign Keys, WAL mode, and Auto-Seeding
 */

declare(strict_types=1);

namespace Nutronyx;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private static string $dbFile = __DIR__ . '/../storage/nutronyx_commerce.sqlite';

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $storageDir = dirname(self::$dbFile);
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            $firstRun = !file_exists(self::$dbFile);

            self::$instance = new PDO('sqlite:' . self::$dbFile, '', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // High performance Pragmas
            self::$instance->exec('PRAGMA foreign_keys = ON;');
            self::$instance->exec('PRAGMA journal_mode = WAL;');
            self::$instance->exec('PRAGMA synchronous = NORMAL;');

            if ($firstRun || self::isSchemaUninitialized()) {
                self::initializeSchema();
                self::seedInitialData();
            } else {
                self::migrateExtendedColumns(self::$instance);
                self::seedInitialData();
            }
        }

        return self::$instance;
    }

    private static function isSchemaUninitialized(): bool
    {
        $stmt = self::$instance->query("SELECT name FROM sqlite_master WHERE type='table' AND name='websites'");
        return $stmt->fetchColumn() === false;
    }

    public static function initializeSchema(): void
    {
        $db = self::$instance;

        // 1. Websites (Tenants)
        $db->exec("
            CREATE TABLE IF NOT EXISTS websites (
                id VARCHAR(36) PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                domain VARCHAR(255) NOT NULL UNIQUE,
                is_default INTEGER NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 2. Categories
        $db->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (website_id, slug)
            );
        ");

        // 3. Products
        $db->exec("
            CREATE TABLE IF NOT EXISTS products (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                category_id VARCHAR(36) REFERENCES categories(id) ON DELETE SET NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT,
                status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', -- ACTIVE, DRAFT, ARCHIVED
                image_url VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (website_id, slug)
            );
        ");

        // 4. Product Variants (SKUs & Integer Pricing)
        $db->exec("
            CREATE TABLE IF NOT EXISTS product_variants (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                product_id VARCHAR(36) NOT NULL REFERENCES products(id) ON DELETE CASCADE,
                sku VARCHAR(64) NOT NULL,
                title VARCHAR(100) NOT NULL,
                price_amount INTEGER NOT NULL, -- Integer Paise / Cents (e.g. 149900 = ₹1,499.00)
                compare_at_amount INTEGER,
                weight_grams INTEGER DEFAULT 300,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (website_id, sku)
            );
        ");

        // 5. Inventory Stocks (Available vs Reserved)
        $db->exec("
            CREATE TABLE IF NOT EXISTS inventory_stocks (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                variant_id VARCHAR(36) NOT NULL UNIQUE REFERENCES product_variants(id) ON DELETE CASCADE,
                quantity_on_hand INTEGER NOT NULL DEFAULT 0,
                quantity_reserved INTEGER NOT NULL DEFAULT 0,
                low_stock_threshold INTEGER NOT NULL DEFAULT 15,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 6. Inventory Movements
        $db->exec("
            CREATE TABLE IF NOT EXISTS inventory_movements (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                variant_id VARCHAR(36) NOT NULL REFERENCES product_variants(id) ON DELETE CASCADE,
                movement_type VARCHAR(32) NOT NULL, -- INBOUND, RESERVED, FULFILLED, ADJUSTMENT
                quantity_delta INTEGER NOT NULL,
                balance_after INTEGER NOT NULL,
                reason TEXT,
                actor_email VARCHAR(100) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 7. Customers (Order-Derived, Minimal Support Ledger)
        $db->exec("
            CREATE TABLE IF NOT EXISTS customers (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(120) NOT NULL,
                phone VARCHAR(30) NOT NULL,
                total_orders INTEGER NOT NULL DEFAULT 1,
                total_spent_amount INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (website_id, email)
            );
        ");

        // 8. Orders (Controlled State Machine)
        $db->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                order_number VARCHAR(32) NOT NULL,
                customer_id VARCHAR(36) NOT NULL REFERENCES customers(id),
                order_status VARCHAR(30) NOT NULL DEFAULT 'PROCESSING', -- PENDING_PAYMENT, PROCESSING, FULFILLED, CANCELLED
                payment_status VARCHAR(30) NOT NULL DEFAULT 'PAID', -- UNPAID, AUTHORIZED, PAID, PARTIALLY_REFUNDED, REFUNDED
                fulfillment_status VARCHAR(30) NOT NULL DEFAULT 'UNFULFILLED', -- UNFULFILLED, PARTIALLY_FULFILLED, FULFILLED
                subtotal_amount INTEGER NOT NULL,
                discount_amount INTEGER NOT NULL DEFAULT 0,
                tax_amount INTEGER NOT NULL DEFAULT 0,
                shipping_amount INTEGER NOT NULL DEFAULT 0,
                grand_total_amount INTEGER NOT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'INR',
                shipping_name VARCHAR(120) NOT NULL,
                shipping_phone VARCHAR(30) NOT NULL,
                shipping_address_line1 VARCHAR(255) NOT NULL,
                shipping_address_line2 VARCHAR(255),
                shipping_city VARCHAR(100) NOT NULL,
                shipping_state VARCHAR(100) NOT NULL,
                shipping_postal_code VARCHAR(20) NOT NULL,
                customer_notes TEXT,
                internal_notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (website_id, order_number)
            );
        ");

        // 9. Order Items (Immutable)
        $db->exec("
            CREATE TABLE IF NOT EXISTS order_items (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                order_id VARCHAR(36) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
                variant_id VARCHAR(36) NOT NULL REFERENCES product_variants(id),
                sku VARCHAR(64) NOT NULL,
                title VARCHAR(255) NOT NULL,
                unit_price_amount INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                tax_amount INTEGER NOT NULL DEFAULT 0,
                discount_amount INTEGER NOT NULL DEFAULT 0,
                total_amount INTEGER NOT NULL
            );
        ");

        // 10. Order Timeline
        $db->exec("
            CREATE TABLE IF NOT EXISTS order_timeline (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                order_id VARCHAR(36) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
                event_type VARCHAR(50) NOT NULL, -- CREATED, PAID, ADDRESS_MODIFIED, SHIPPED, CANCELLED
                message TEXT NOT NULL,
                actor VARCHAR(100) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 11. Payment Transactions & Multi-Gateway Ledger
        $db->exec("
            CREATE TABLE IF NOT EXISTS payment_transactions (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                order_id VARCHAR(36) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
                gateway VARCHAR(50) NOT NULL, -- razorpay, stripe, cashfree
                gateway_transaction_id VARCHAR(100),
                transaction_type VARCHAR(20) NOT NULL, -- CHARGE, REFUND, VOID
                amount INTEGER NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'SUCCESS', -- SUCCESS, FAILED, PENDING
                idempotency_key VARCHAR(64) UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 12. Shipments & Tracking
        $db->exec("
            CREATE TABLE IF NOT EXISTS shipments (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                order_id VARCHAR(36) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
                carrier VARCHAR(60) NOT NULL, -- BlueDart, Delhivery, DTDC
                tracking_number VARCHAR(100) NOT NULL,
                tracking_url VARCHAR(255),
                shipped_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                delivered_at DATETIME,
                status VARCHAR(30) NOT NULL DEFAULT 'DISPATCHED' -- DISPATCHED, IN_TRANSIT, DELIVERED
            );
        ");

        // 13. Returns & Refunds
        $db->exec("
            CREATE TABLE IF NOT EXISTS order_returns (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                order_id VARCHAR(36) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
                reason_code VARCHAR(50) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'PENDING_INSPECTION', -- PENDING_INSPECTION, APPROVED, REJECTED, REFUNDED
                refund_amount INTEGER NOT NULL DEFAULT 0,
                restock_approved INTEGER NOT NULL DEFAULT 0,
                internal_notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 14. Promotions & Coupons
        $db->exec("
            CREATE TABLE IF NOT EXISTS promotions (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                code VARCHAR(50) NOT NULL,
                discount_type VARCHAR(20) NOT NULL, -- PERCENTAGE, FIXED, FREE_SHIPPING
                discount_value INTEGER NOT NULL, -- 10 for 10%, or 20000 for ₹200.00
                minimum_order_amount INTEGER NOT NULL DEFAULT 0,
                usage_limit INTEGER DEFAULT 1000,
                usage_count INTEGER NOT NULL DEFAULT 0,
                starts_at DATETIME,
                expires_at DATETIME,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (website_id, code)
            );
        ");

        // 15. Reviews & Ratings Moderation
        $db->exec("
            CREATE TABLE IF NOT EXISTS reviews (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                product_id VARCHAR(36) NOT NULL REFERENCES products(id) ON DELETE CASCADE,
                customer_name VARCHAR(120) NOT NULL,
                rating INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
                title VARCHAR(150),
                comment TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'PENDING', -- PENDING, APPROVED, REJECTED
                is_verified_buyer INTEGER NOT NULL DEFAULT 1,
                admin_reply TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 16. Tax Rules per Website
        $db->exec("
            CREATE TABLE IF NOT EXISTS tax_rules (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                name VARCHAR(100) NOT NULL,
                rate_percentage REAL NOT NULL, -- e.g. 18.00 for 18% GST
                is_inclusive INTEGER NOT NULL DEFAULT 1,
                applies_to_shipping INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 17. Shipping Methods
        $db->exec("
            CREATE TABLE IF NOT EXISTS shipping_methods (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
                name VARCHAR(100) NOT NULL,
                charge_amount INTEGER NOT NULL DEFAULT 0, -- e.g. 9900 = ₹99
                min_order_free_amount INTEGER DEFAULT 99900, -- Free over ₹999
                estimated_days VARCHAR(50) DEFAULT '3-5 business days',
                is_active INTEGER NOT NULL DEFAULT 1
            );
        ");

        // 18. Admin Users & Roles
        $db->exec("
            CREATE TABLE IF NOT EXISTS admin_users (
                id VARCHAR(36) PRIMARY KEY,
                email VARCHAR(120) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                name VARCHAR(100) NOT NULL,
                role VARCHAR(30) NOT NULL DEFAULT 'OPERATOR', -- SUPER_ADMIN, STORE_MANAGER, OPERATOR, AUDITOR
                status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
                last_login_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 19. Admin Audit Logs (Immutable)
        $db->exec("
            CREATE TABLE IF NOT EXISTS admin_audit_logs (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL,
                actor_id VARCHAR(36) NOT NULL,
                actor_email VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                action_type VARCHAR(64) NOT NULL,
                target_resource VARCHAR(64) NOT NULL,
                target_id VARCHAR(36) NOT NULL,
                details TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 20. Developer SDK Installed Modules
        $db->exec("
            CREATE TABLE IF NOT EXISTS installed_modules (
                id VARCHAR(64) PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                version VARCHAR(32) NOT NULL,
                description TEXT,
                author VARCHAR(100),
                status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', -- ACTIVE, DISABLED
                settings_json TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        self::migrateExtendedColumns($db);
    }

    private static function migrateExtendedColumns(\PDO $db): void
    {
        // 1. Products extended columns
        $productCols = [
            'short_description' => 'TEXT',
            'sale_price_amount' => 'INTEGER',
            'cost_price_amount' => 'INTEGER',
            'tax_rate' => 'REAL DEFAULT 0',
            'currency' => "VARCHAR(10) DEFAULT 'INR'",
            'barcode' => 'VARCHAR(100)',
            'brand' => 'VARCHAR(100)',
            'track_inventory' => 'INTEGER DEFAULT 1',
            'allow_backorders' => 'INTEGER DEFAULT 0',
            'stock_status' => "VARCHAR(20) DEFAULT 'in_stock'",
            'subcategory' => 'VARCHAR(100)',
            'tags' => 'TEXT',
            'collections' => 'TEXT',
            'options_json' => 'TEXT',
            'video_url' => 'VARCHAR(255)',
            'shipping_class' => 'VARCHAR(50)',
            'dimensions_cm' => 'VARCHAR(50)',
            'position' => 'INTEGER DEFAULT 0',
        ];

        $stmt = $db->query("PRAGMA table_info(products)");
        $existing = [];
        while ($row = $stmt->fetch()) {
            $existing[$row['name']] = true;
        }
        foreach ($productCols as $col => $type) {
            if (!isset($existing[$col])) {
                $db->exec("ALTER TABLE products ADD COLUMN {$col} {$type}");
            }
        }

        // 2. Categories extended columns
        $catCols = [
            'image_url' => 'VARCHAR(255)',
            'subcategories_json' => 'TEXT',
        ];
        $cStmt = $db->query("PRAGMA table_info(categories)");
        $catExisting = [];
        while ($row = $cStmt->fetch()) {
            $catExisting[$row['name']] = true;
        }
        foreach ($catCols as $col => $type) {
            if (!isset($catExisting[$col])) {
                $db->exec("ALTER TABLE categories ADD COLUMN {$col} {$type}");
            }
        }

        // 3. Orders extended columns
        $orderCols = [
            'delivery_person_name' => "VARCHAR(120) DEFAULT 'Not assigned'",
            'delivery_person_phone' => 'VARCHAR(50)',
            'invoice_number' => 'VARCHAR(60)',
            'coupon_code' => 'VARCHAR(64)',
        ];
        $oStmt = $db->query("PRAGMA table_info(orders)");
        $orderExisting = [];
        while ($row = $oStmt->fetch()) {
            $orderExisting[$row['name']] = true;
        }
        foreach ($orderCols as $col => $type) {
            if (!isset($orderExisting[$col])) {
                $db->exec("ALTER TABLE orders ADD COLUMN {$col} {$type}");
            }
        }

        // 4. Customers extended columns
        $custCols = [
            'status' => "VARCHAR(20) DEFAULT 'Active'",
            'last_order_at' => 'DATETIME',
            'notes' => 'TEXT',
        ];
        $custStmt = $db->query("PRAGMA table_info(customers)");
        $custExisting = [];
        while ($row = $custStmt->fetch()) {
            $custExisting[$row['name']] = true;
        }
        foreach ($custCols as $col => $type) {
            if (!isset($custExisting[$col])) {
                $db->exec("ALTER TABLE customers ADD COLUMN {$col} {$type}");
            }
        }

        // 5. Shipping Settings table
        $db->exec("
            CREATE TABLE IF NOT EXISTS shipping_settings (
                website_id VARCHAR(36) PRIMARY KEY,
                min_order_amount INTEGER DEFAULT 0,
                max_weight_grams INTEGER DEFAULT 50000,
                default_rate_amount INTEGER DEFAULT 0,
                free_shipping_threshold INTEGER DEFAULT 0,
                cod_charge_amount INTEGER DEFAULT 0,
                gst_percentage REAL DEFAULT 0,
                min_delivery_days INTEGER DEFAULT 3,
                max_delivery_days INTEGER DEFAULT 7,
                shipping_enabled INTEGER DEFAULT 1,
                cod_enabled INTEGER DEFAULT 1,
                charge_gst INTEGER DEFAULT 0,
                delivery_method VARCHAR(50) DEFAULT 'courier',
                shopper_delivery_name VARCHAR(120) DEFAULT 'Standard Delivery',
                shopper_delivery_note TEXT DEFAULT 'Delivered safely to your doorstep.',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 6. Shipping Priority Rules table
        $db->exec("
            CREATE TABLE IF NOT EXISTS shipping_priority_rules (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL,
                priority INTEGER NOT NULL DEFAULT 1,
                name VARCHAR(120) NOT NULL,
                condition_type VARCHAR(64) NOT NULL,
                condition_value TEXT NOT NULL,
                rate_amount INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER DEFAULT 1
            );
        ");

        // 7. Cross Selling & Recommendation Rules table
        $db->exec("
            CREATE TABLE IF NOT EXISTS cross_selling_rules (
                id VARCHAR(36) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL,
                rule_type VARCHAR(64) NOT NULL,
                priority_order INTEGER NOT NULL DEFAULT 1,
                is_active INTEGER DEFAULT 1,
                pinned_product_ids TEXT,
                products_per_carousel INTEGER DEFAULT 12,
                hide_out_of_stock INTEGER DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    public static function seedInitialData(): void
    {
        $db = self::$instance;

        // 1. Websites (Multi-Tenant Contexts)
        $db->exec("
            INSERT OR IGNORE INTO websites (id, name, domain, is_default, status) VALUES
            ('web_nutronyx_d2c', 'Nutronyx D2C Storefront', 'nutronyx.com', 1, 'ACTIVE'),
            ('web_partner_portal', 'Nutronyx Partner & Retail', 'partners.nutronyx.com', 0, 'ACTIVE');
        ");

        // 2. Admin User (Requested credentials: nutronyx@gmail.com / svgdryfruits@gmail.com / Mohith@123)
        $passwordHash = password_hash('Mohith@123', PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            INSERT OR IGNORE INTO admin_users (id, email, password_hash, name, role, status) VALUES
            ('usr_mohith', 'mohith@nutronyx.com', :pwd, 'Mohith', 'SUPER_ADMIN', 'ACTIVE'),
            ('usr_svgdryfruits', 'svgdryfruits@gmail.com', :pwd, 'SVG Dry Fruits Admin', 'SUPER_ADMIN', 'ACTIVE')
        ");
        $stmt->execute([':pwd' => $passwordHash]);

        // 3. Tax Rules
        $db->exec("
            INSERT OR IGNORE INTO tax_rules (id, website_id, name, rate_percentage, is_inclusive, applies_to_shipping) VALUES
            ('tax_d2c_gst', 'web_nutronyx_d2c', 'Standard GST (Inclusive)', 18.0, 1, 1),
            ('tax_partner_gst', 'web_partner_portal', 'Wholesale GST (Inclusive)', 18.0, 1, 1);
        ");

        // 4. Shipping Methods
        $db->exec("
            INSERT OR IGNORE INTO shipping_methods (id, website_id, name, charge_amount, min_order_free_amount, estimated_days, is_active) VALUES
            ('ship_std', 'web_nutronyx_d2c', 'Standard Express Ground', 9900, 99900, '3-5 Business Days', 1),
            ('ship_prio', 'web_nutronyx_d2c', 'Priority Courier', 19900, 199900, '1-2 Business Days', 1),
            ('ship_bulk', 'web_partner_portal', 'Wholesale Freight Dispatch', 49900, 499900, '4-7 Business Days', 1);
        ");

        // 5. Shipping Settings
        $db->exec("
            INSERT OR IGNORE INTO shipping_settings (website_id, min_order_amount, max_weight_grams, default_rate_amount, free_shipping_threshold, cod_charge_amount, gst_percentage, min_delivery_days, max_delivery_days, shipping_enabled, cod_enabled, charge_gst, delivery_method) VALUES
            ('web_nutronyx_d2c', 0, 50000, 0, 0, 0, 0, 3, 7, 1, 1, 0, 'courier');
        ");

        // 6. Cross Selling & Recommendation Rules Structure
        $db->exec("
            INSERT OR IGNORE INTO cross_selling_rules (id, website_id, rule_type, priority_order, is_active, products_per_carousel, hide_out_of_stock) VALUES
            ('cs_1', 'web_nutronyx_d2c', 'manual', 1, 1, 12, 0),
            ('cs_2', 'web_nutronyx_d2c', 'frequently_bought', 2, 1, 12, 0),
            ('cs_3', 'web_nutronyx_d2c', 'customers_also_bought', 3, 1, 12, 0),
            ('cs_4', 'web_nutronyx_d2c', 'automatic_category', 4, 1, 12, 0),
            ('cs_5', 'web_nutronyx_d2c', 'automatic_tags', 5, 1, 12, 0),
            ('cs_6', 'web_nutronyx_d2c', 'automatic_brand', 6, 1, 12, 0),
            ('cs_7', 'web_nutronyx_d2c', 'recently_viewed', 7, 1, 12, 0);
        ");

        // System seeds complete (no mock business data)
    }
}
