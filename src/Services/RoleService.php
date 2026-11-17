<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;
use PDO;

class RoleService
{
    /**
     * Standard module permissions definition
     */
    public const MODULE_PERMISSIONS = [
        'dashboard' => [
            'name' => 'Dashboard & KPIs',
            'actions' => ['view' => 'View performance metrics and summary charts', 'export' => 'Export dashboard analytics reports']
        ],
        'products' => [
            'name' => 'Products & Catalog',
            'actions' => ['view' => 'View products and catalog list', 'create' => 'Add new products and SKUs', 'edit' => 'Update pricing and descriptions', 'delete' => 'Delete catalog items']
        ],
        'categories' => [
            'name' => 'Categories & Taxonomies',
            'actions' => ['view' => 'View categories', 'create' => 'Add categories', 'edit' => 'Update categories', 'delete' => 'Delete categories']
        ],
        'orders' => [
            'name' => 'Orders & Fulfillments',
            'actions' => ['view' => 'View orders and transactions', 'create' => 'Draft manual orders', 'edit' => 'Update order status and addresses', 'fulfill' => 'Process dispatch and packaging', 'delete' => 'Cancel or delete orders']
        ],
        'customers' => [
            'name' => 'Customers & CRM',
            'actions' => ['view' => 'View customer profiles and history', 'create' => 'Add customers', 'edit' => 'Update customer information', 'delete' => 'Delete customer records']
        ],
        'inventory' => [
            'name' => 'Inventory & Stocks',
            'actions' => ['view' => 'View stock on hand and thresholds', 'adjust' => 'Perform stock in/out adjustments', 'reconcile' => 'Execute inventory audits']
        ],
        'promotions' => [
            'name' => 'Discounts & Coupons',
            'actions' => ['view' => 'View promotional codes and rules', 'create' => 'Create coupon codes', 'edit' => 'Modify discounts and campaigns', 'delete' => 'Delete promotional rules']
        ],
        'reviews' => [
            'name' => 'Reviews & Moderation',
            'actions' => ['view' => 'View customer feedback', 'create' => 'Create manual reviews', 'moderate' => 'Approve or reject reviews', 'reply' => 'Author staff responses', 'delete' => 'Delete reviews']
        ],
        'shipping' => [
            'name' => 'Shipping & Ekart Logistics',
            'actions' => ['view' => 'View shipping rates and orders', 'edit' => 'Configure rates and courier accounts', 'sync' => 'Generate waybills and track shipments']
        ],
        'templates' => [
            'name' => 'Invoices & Receipts',
            'actions' => ['view' => 'View template previews and company info', 'edit' => 'Customize layouts and digital signatures']
        ],
        'reports' => [
            'name' => 'Reports & Intelligence',
            'actions' => ['view' => 'View revenue and sales intelligence', 'export' => 'Export CSV and accounting data']
        ],
        'governance' => [
            'name' => 'Roles & Security Governance',
            'actions' => ['view' => 'View users, roles, and security audit trail', 'users_manage' => 'Create, invite, suspend, and edit staff users', 'roles_manage' => 'Configure custom roles and permissions', 'audit_view' => 'Inspect immutable security logs']
        ],
        'settings' => [
            'name' => 'System & Store Settings',
            'actions' => ['view' => 'View store settings and tax rules', 'edit' => 'Modify store settings, GST and API keys']
        ]
    ];

    /**
     * Ensure database tables and initial roles/users exist
     */
    public static function ensureTables(): void
    {
        $db = Database::getConnection();

        // 1. Roles table
        $db->exec("
            CREATE TABLE IF NOT EXISTS admin_roles (
                id VARCHAR(36) PRIMARY KEY,
                code VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                is_system INTEGER DEFAULT 0,
                badge_color VARCHAR(30) DEFAULT 'green',
                permissions TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 2. Add extra columns to admin_users if missing
        $cols = [];
        $res = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res as $r) {
            $cols[] = strtolower($r['name']);
        }

        $neededCols = [
            'phone' => 'VARCHAR(30)',
            'store_access_type' => "VARCHAR(20) DEFAULT 'ALL'",
            'allowed_stores' => 'TEXT DEFAULT \'["web_nutronyx_d2c","web_partner_portal"]\'',
            'default_website_id' => "VARCHAR(36) DEFAULT 'web_nutronyx_d2c'",
            'invitation_token' => 'VARCHAR(64)',
            'invitation_expires_at' => 'DATETIME',
            'password_reset_token' => 'VARCHAR(64)',
            'password_reset_expires_at' => 'DATETIME',
            'last_active_at' => 'DATETIME',
            'avatar_color' => "VARCHAR(10) DEFAULT '#166534'"
        ];

        foreach ($neededCols as $col => $definition) {
            if (!in_array(strtolower($col), $cols, true)) {
                try {
                    $db->exec("ALTER TABLE admin_users ADD COLUMN {$col} {$definition}");
                } catch (\Throwable $e) {
                    // Ignored if column already exists
                }
            }
        }

        // 3. Add status column to admin_audit_logs if missing
        $logCols = [];
        $logRes = $db->query("PRAGMA table_info(admin_audit_logs)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($logRes as $lr) {
            $logCols[] = strtolower($lr['name']);
        }
        if (!in_array('status', $logCols, true)) {
            try {
                $db->exec("ALTER TABLE admin_audit_logs ADD COLUMN status VARCHAR(20) DEFAULT 'SUCCESS'");
            } catch (\Throwable $e) {}
        }

        // 4. Seed Standard Roles
        $roleCount = (int)$db->query("SELECT COUNT(*) FROM admin_roles")->fetchColumn();
        if ($roleCount === 0) {
            $allPerms = [];
            foreach (self::MODULE_PERMISSIONS as $mod => $info) {
                foreach ($info['actions'] as $act => $desc) {
                    $allPerms[] = "{$mod}.{$act}";
                }
            }

            $defaultRoles = [
                [
                    'id' => 'role_super_admin',
                    'code' => 'SUPER_ADMIN',
                    'name' => 'Super Admin',
                    'description' => 'Unrestricted global root administrator with full access across all modules, system configuration, team governance, and security audit logs.',
                    'is_system' => 1,
                    'badge_color' => 'green',
                    'permissions' => json_encode(['*'])
                ],
                [
                    'id' => 'role_store_manager',
                    'code' => 'STORE_MANAGER',
                    'name' => 'Store Manager',
                    'description' => 'Comprehensive daily store operations: product catalog, orders & fulfillments, inventory adjustments, customers CRM, marketing discounts, and customer reviews.',
                    'is_system' => 1,
                    'badge_color' => 'light_green',
                    'permissions' => json_encode([
                        'dashboard.view', 'dashboard.export',
                        'products.view', 'products.create', 'products.edit', 'products.delete',
                        'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
                        'orders.view', 'orders.create', 'orders.edit', 'orders.fulfill', 'orders.delete',
                        'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
                        'inventory.view', 'inventory.adjust',
                        'promotions.view', 'promotions.create', 'promotions.edit', 'promotions.delete',
                        'reviews.view', 'reviews.create', 'reviews.moderate', 'reviews.reply', 'reviews.delete',
                        'shipping.view', 'shipping.edit', 'shipping.sync',
                        'templates.view', 'templates.edit',
                        'reports.view', 'reports.export',
                        'settings.view'
                    ])
                ]
            ];

            $insRole = $db->prepare("
                INSERT INTO admin_roles (id, code, name, description, is_system, badge_color, permissions)
                VALUES (:id, :code, :name, :desc, :is_sys, :color, :perms)
            ");
            foreach ($defaultRoles as $dr) {
                $insRole->execute([
                    ':id' => $dr['id'],
                    ':code' => $dr['code'],
                    ':name' => $dr['name'],
                    ':desc' => $dr['description'],
                    ':is_sys' => $dr['is_system'],
                    ':color' => $dr['badge_color'],
                    ':perms' => $dr['permissions']
                ]);
            }
        }

        // Always enforce consolidation to the 2 official roles
        self::migrateToTwoRoles();

        // 5. Seed Primary Super Admin Accounts
        $defaultPasswordHash = password_hash('Mohith@123', PASSWORD_BCRYPT);
        $primaryAdmins = [
            ['usr_svgdryfruits', 'SVG Dry Fruits Admin', 'svgdryfruits@gmail.com', 'SUPER_ADMIN', 'ALL', '["web_nutronyx_d2c"]', 'ACTIVE', date('Y-m-d H:i:s'), '2025-01-12 10:00:00', '#143D24'],
            ['usr_mohith', 'Mohith', 'mohith@nutronyx.com', 'SUPER_ADMIN', 'ALL', '["web_nutronyx_d2c","web_partner_portal"]', 'ACTIVE', date('Y-m-d H:i:s'), '2025-01-12 10:00:00', '#166534'],
        ];

        $insUser = $db->prepare("
            INSERT OR IGNORE INTO admin_users (
                id, name, email, password_hash, role, store_access_type, allowed_stores, default_website_id, status, last_login_at, created_at, avatar_color
            ) VALUES (
                :id, :name, :email, :pwd, :role, :st_type, :allowed, 'web_nutronyx_d2c', :status, :last_log, :created_at, :color
            )
        ");

        foreach ($primaryAdmins as $s) {
            $insUser->execute([
                ':id' => $s[0],
                ':name' => $s[1],
                ':email' => strtolower($s[2]),
                ':pwd' => $defaultPasswordHash,
                ':role' => $s[3],
                ':st_type' => $s[4],
                ':allowed' => $s[5],
                ':status' => $s[6],
                ':last_log' => $s[7],
                ':created_at' => $s[8],
                ':color' => $s[9]
            ]);
        }
    }

    /**
     * Enforce consolidation to exactly two roles: Super Admin and Store Manager
     */
    public static function migrateToTwoRoles(): void
    {
        $db = Database::getConnection();

        $roles = $db->query("SELECT code FROM admin_roles")->fetchAll(PDO::FETCH_COLUMN);
        if (count($roles) === 2 && in_array('SUPER_ADMIN', $roles) && in_array('STORE_MANAGER', $roles)) {
            return;
        }

        $storeManagerPerms = [
            'dashboard.view', 'dashboard.export',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'orders.view', 'orders.create', 'orders.edit', 'orders.fulfill', 'orders.delete',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'inventory.view', 'inventory.adjust',
            'promotions.view', 'promotions.create', 'promotions.edit', 'promotions.delete',
            'reviews.view', 'reviews.create', 'reviews.moderate', 'reviews.reply', 'reviews.delete',
            'shipping.view', 'shipping.edit', 'shipping.sync',
            'templates.view', 'templates.edit',
            'reports.view', 'reports.export',
            'settings.view'
        ];

        // Delete old roles not in SUPER_ADMIN or STORE_MANAGER
        $db->exec("DELETE FROM admin_roles WHERE code NOT IN ('SUPER_ADMIN', 'STORE_MANAGER')");

        // Ensure SUPER_ADMIN exists
        $hasSuperAdmin = $db->query("SELECT COUNT(*) FROM admin_roles WHERE code = 'SUPER_ADMIN'")->fetchColumn();
        if (!$hasSuperAdmin) {
            $db->exec("INSERT INTO admin_roles (id, code, name, description, is_system, badge_color, permissions)
                VALUES ('role_super_admin', 'SUPER_ADMIN', 'Super Admin', 'Unrestricted global root administrator with full access across all modules, system configuration, team governance, and security audit logs.', 1, 'green', '[\"*\"]')");
        } else {
            $db->exec("UPDATE admin_roles SET 
                name = 'Super Admin',
                description = 'Unrestricted global root administrator with full access across all modules, system configuration, team governance, and security audit logs.',
                badge_color = 'green',
                permissions = '[\"*\"]'
                WHERE code = 'SUPER_ADMIN'");
        }

        // Ensure STORE_MANAGER exists
        $hasStoreManager = $db->query("SELECT COUNT(*) FROM admin_roles WHERE code = 'STORE_MANAGER'")->fetchColumn();
        if (!$hasStoreManager) {
            $ins = $db->prepare("INSERT INTO admin_roles (id, code, name, description, is_system, badge_color, permissions)
                VALUES ('role_store_manager', 'STORE_MANAGER', 'Store Manager', 'Comprehensive daily store operations: product catalog, orders & fulfillments, inventory adjustments, customers CRM, marketing discounts, and customer reviews.', 1, 'light_green', :perms)");
            $ins->execute([':perms' => json_encode($storeManagerPerms)]);
        } else {
            $upd = $db->prepare("UPDATE admin_roles SET
                name = 'Store Manager',
                description = 'Comprehensive daily store operations: product catalog, orders & fulfillments, inventory adjustments, customers CRM, marketing discounts, and customer reviews.',
                badge_color = 'light_green',
                permissions = :perms
                WHERE code = 'STORE_MANAGER'");
            $upd->execute([':perms' => json_encode($storeManagerPerms)]);
        }

        // Reassign any non-super admin users to STORE_MANAGER
        $db->exec("UPDATE admin_users SET role = 'STORE_MANAGER' WHERE role != 'SUPER_ADMIN'");
        $db->exec("UPDATE admin_users SET store_access_type = 'SPECIFIC', allowed_stores = '[\"web_nutronyx_d2c\"]'");
    }

    /**
     * Get KPI Summary Statistics for Roles & Governance
     */
    public static function getGovernanceKpis(): array
    {
        self::ensureTables();
        $db = Database::getConnection();

        $totalUsers = (int)$db->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
        $activeUsers = (int)$db->query("SELECT COUNT(*) FROM admin_users WHERE status = 'ACTIVE'")->fetchColumn();
        $rolesCount = (int)$db->query("SELECT COUNT(*) FROM admin_roles")->fetchColumn();
        $suspendedUsers = (int)$db->query("SELECT COUNT(*) FROM admin_users WHERE status = 'SUSPENDED'")->fetchColumn();
        $invitedUsers = (int)$db->query("SELECT COUNT(*) FROM admin_users WHERE status = 'INVITED'")->fetchColumn();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'roles_count' => $rolesCount,
            'suspended_users' => $suspendedUsers,
            'invited_users' => $invitedUsers
        ];
    }

    /**
     * Get list of admin users with filters, search, and pagination
     */
    public static function getUsers(array $filters = []): array
    {
        self::ensureTables();
        $db = Database::getConnection();

        $search = !empty($filters['search']) ? trim($filters['search']) : null;
        $role = !empty($filters['role']) && $filters['role'] !== 'ALL' ? strtoupper($filters['role']) : null;
        $status = !empty($filters['status']) && $filters['status'] !== 'ALL' ? strtoupper($filters['status']) : null;
        $store = !empty($filters['store']) && $filters['store'] !== 'ALL' ? $filters['store'] : null;
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int)($filters['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        if ($search) {
            $where[] = "(u.name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($role) {
            $where[] = "u.role = :role";
            $params[':role'] = $role;
        }

        if ($status) {
            $where[] = "u.status = :status";
            $params[':status'] = $status;
        }

        if ($store) {
            if ($store === 'ALL_STORES') {
                $where[] = "u.store_access_type = 'ALL'";
            } else {
                $where[] = "(u.store_access_type = 'ALL' OR u.allowed_stores LIKE :store_like)";
                $params[':store_like'] = '%' . $store . '%';
            }
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM admin_users u WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT u.*, r.name as role_name, r.badge_color as role_color
            FROM admin_users u
            LEFT JOIN admin_roles r ON u.role = r.code
            WHERE {$whereSql}
            ORDER BY u.created_at ASC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'users' => $users,
            'items' => $users,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
            'offset' => $offset
        ];
    }

    /**
     * Get single user by ID
     */
    public static function getUserById(string $id): ?array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.*, r.name as role_name, r.description as role_desc, r.permissions as role_permissions
            FROM admin_users u
            LEFT JOIN admin_roles r ON u.role = r.code
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create new admin user
     */
    public static function createUser(array $data): string
    {
        self::ensureTables();
        $db = Database::getConnection();

        $name = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? 'Mohith@123';
        $role = strtoupper(trim($data['role'] ?? 'OPERATOR'));
        $status = strtoupper(trim($data['status'] ?? 'ACTIVE'));
        $storeType = strtoupper(trim($data['store_access_type'] ?? 'ALL'));
        $allowedStores = !empty($data['allowed_stores']) ? (is_array($data['allowed_stores']) ? json_encode($data['allowed_stores']) : $data['allowed_stores']) : '["web_nutronyx_d2c","web_partner_portal"]';
        $phone = trim($data['phone'] ?? '') ?: null;

        if (empty($name) || empty($email)) {
            throw new \InvalidArgumentException('Staff member name and email address are required.');
        }

        // Check if email already exists
        $check = $db->prepare("SELECT id FROM admin_users WHERE email = :email LIMIT 1");
        $check->execute([':email' => $email]);
        if ($check->fetchColumn()) {
            throw new \InvalidArgumentException("An administrator account with email '{$email}' already exists.");
        }

        $id = 'usr_' . bin2hex(random_bytes(6));
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $avatarColors = ['#166534', '#7E22CE', '#0369A1', '#B45309', '#0D9488'];
        $color = $avatarColors[crc32($email) % count($avatarColors)];

        $stmt = $db->prepare("
            INSERT INTO admin_users (
                id, name, email, password_hash, role, status, phone, store_access_type, allowed_stores, default_website_id, avatar_color, created_at
            ) VALUES (
                :id, :name, :email, :hash, :role, :status, :phone, :store_type, :allowed, 'web_nutronyx_d2c', :color, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':email' => $email,
            ':hash' => $hash,
            ':role' => $role,
            ':status' => $status,
            ':phone' => $phone,
            ':store_type' => $storeType,
            ':allowed' => $allowedStores,
            ':color' => $color
        ]);

        AuditService::log('admin_user.create', 'admin_user', $id, "Created administrator '{$name}' ({$email}) with role {$role}.");
        return $id;
    }

    /**
     * Invite staff member via email
     */
    public static function inviteUser(array $data): string
    {
        self::ensureTables();
        $db = Database::getConnection();

        $name = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $role = strtoupper(trim($data['role'] ?? 'OPERATOR'));
        $storeType = strtoupper(trim($data['store_access_type'] ?? 'ALL'));
        $allowedStores = !empty($data['allowed_stores']) ? (is_array($data['allowed_stores']) ? json_encode($data['allowed_stores']) : $data['allowed_stores']) : '["web_nutronyx_d2c","web_partner_portal"]';

        if (empty($name) || empty($email)) {
            throw new \InvalidArgumentException('Name and email are required for staff invitation.');
        }

        $check = $db->prepare("SELECT id FROM admin_users WHERE email = :email LIMIT 1");
        $check->execute([':email' => $email]);
        if ($check->fetchColumn()) {
            throw new \InvalidArgumentException("A user with email '{$email}' already exists.");
        }

        $id = 'usr_' . bin2hex(random_bytes(6));
        $token = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $tempPassword = password_hash(bin2hex(random_bytes(10)), PASSWORD_BCRYPT);
        $avatarColors = ['#166534', '#7E22CE', '#0369A1', '#B45309', '#0D9488'];
        $color = $avatarColors[crc32($email) % count($avatarColors)];

        $stmt = $db->prepare("
            INSERT INTO admin_users (
                id, name, email, password_hash, role, status, store_access_type, allowed_stores, default_website_id, invitation_token, invitation_expires_at, avatar_color, created_at
            ) VALUES (
                :id, :name, :email, :pwd, :role, 'INVITED', :st_type, :allowed, 'web_nutronyx_d2c', :tok, :exp, :color, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':email' => $email,
            ':pwd' => $tempPassword,
            ':role' => $role,
            ':st_type' => $storeType,
            ':allowed' => $allowedStores,
            ':tok' => $token,
            ':exp' => $expires,
            ':color' => $color
        ]);

        AuditService::log('admin_user.invite', 'admin_user', $id, "Dispatched staff invitation to {$email} for role {$role}.");
        return $id;
    }

    /**
     * Update user details
     */
    public static function updateUser(string $id, array $data): bool
    {
        self::ensureTables();
        $db = Database::getConnection();

        $user = self::getUserById($id);
        if (!$user) return false;

        $name = trim($data['name'] ?? $user['name']);
        $email = strtolower(trim($data['email'] ?? $user['email']));
        $role = strtoupper(trim($data['role'] ?? $user['role']));
        $status = strtoupper(trim($data['status'] ?? $user['status']));
        $storeType = strtoupper(trim($data['store_access_type'] ?? ($user['store_access_type'] ?? 'ALL')));
        $allowedStores = isset($data['allowed_stores']) ? (is_array($data['allowed_stores']) ? json_encode($data['allowed_stores']) : $data['allowed_stores']) : $user['allowed_stores'];
        $phone = isset($data['phone']) ? trim($data['phone']) : $user['phone'];

        // Protection: Ensure at least one active Super Admin remains
        if ($user['role'] === 'SUPER_ADMIN' && ($role !== 'SUPER_ADMIN' || $status !== 'ACTIVE')) {
            $superCount = (int)$db->query("SELECT COUNT(*) FROM admin_users WHERE role = 'SUPER_ADMIN' AND status = 'ACTIVE' AND id != '{$id}'")->fetchColumn();
            if ($superCount === 0) {
                throw new \RuntimeException('Cannot demote or deactivate the last remaining active Super Admin.');
            }
        }

        $stmt = $db->prepare("
            UPDATE admin_users 
            SET name = :name, email = :email, role = :role, status = :status,
                phone = :phone, store_access_type = :st_type, allowed_stores = :allowed
            WHERE id = :id
        ");
        $res = $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':role' => $role,
            ':status' => $status,
            ':phone' => $phone,
            ':st_type' => $storeType,
            ':allowed' => $allowedStores,
            ':id' => $id
        ]);

        AuditService::log('admin_user.update', 'admin_user', $id, "Updated staff profile for {$name} ({$email}) - Role: {$role}, Status: {$status}.");
        return $res;
    }

    /**
     * Toggle user status (Activate / Suspend)
     */
    public static function setUserStatus(string $id, string $status): bool
    {
        self::ensureTables();
        $db = Database::getConnection();

        $user = self::getUserById($id);
        if (!$user) return false;

        $status = strtoupper($status);
        if ($user['role'] === 'SUPER_ADMIN' && $status === 'SUSPENDED') {
            $superCount = (int)$db->query("SELECT COUNT(*) FROM admin_users WHERE role = 'SUPER_ADMIN' AND status = 'ACTIVE' AND id != '{$id}'")->fetchColumn();
            if ($superCount === 0) {
                throw new \RuntimeException('Security Violation: You cannot suspend the sole active Super Admin account.');
            }
        }

        $stmt = $db->prepare("UPDATE admin_users SET status = :status WHERE id = :id");
        $res = $stmt->execute([':status' => $status, ':id' => $id]);

        AuditService::log('admin_user.status', 'admin_user', $id, "Account status changed to {$status} for {$user['name']} ({$user['email']}).");
        return $res;
    }

    /**
     * Secure password reset for an admin user
     */
    public static function resetPassword(string $id, ?string $newPassword = null): string
    {
        self::ensureTables();
        $db = Database::getConnection();

        $user = self::getUserById($id);
        if (!$user) {
            throw new \InvalidArgumentException('User not found.');
        }

        $pwd = $newPassword ?: 'Nutronyx@' . rand(1000, 9999);
        $hash = password_hash($pwd, PASSWORD_BCRYPT);

        $stmt = $db->prepare("
            UPDATE admin_users 
            SET password_hash = :hash, password_reset_token = NULL, password_reset_expires_at = NULL 
            WHERE id = :id
        ");
        $stmt->execute([':hash' => $hash, ':id' => $id]);

        AuditService::log('admin_user.password_reset', 'admin_user', $id, "Password securely reset for {$user['email']}.");
        return $pwd;
    }

    /**
     * Delete an admin user (with super admin protection)
     */
    public static function deleteUser(string $id): bool
    {
        self::ensureTables();
        $db = Database::getConnection();

        $user = self::getUserById($id);
        if (!$user) return false;

        if ($user['role'] === 'SUPER_ADMIN') {
            $superCount = (int)$db->query("SELECT COUNT(*) FROM admin_users WHERE role = 'SUPER_ADMIN' AND id != '{$id}'")->fetchColumn();
            if ($superCount === 0) {
                throw new \RuntimeException('Cannot delete the last remaining Super Admin.');
            }
        }

        $stmt = $db->prepare("DELETE FROM admin_users WHERE id = :id");
        $res = $stmt->execute([':id' => $id]);

        AuditService::log('admin_user.delete', 'admin_user', $id, "Deleted administrator {$user['name']} ({$user['email']}).");
        return $res;
    }

    /**
     * Get all roles
     */
    public static function getRoles(): array
    {
        self::ensureTables();
        $db = Database::getConnection();

        $stmt = $db->query("
            SELECT r.*,
                   (SELECT COUNT(*) FROM admin_users u WHERE u.role = r.code) as users_count
            FROM admin_roles r
            ORDER BY r.is_system DESC, r.created_at ASC
        ");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($roles as &$r) {
            $r['permissions_array'] = json_decode($r['permissions'] ?: '[]', true) ?: [];
        }

        return $roles;
    }

    /**
     * Get single role by code or ID
     */
    public static function getRole(string $codeOrId): ?array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM admin_roles WHERE code = :c OR id = :c LIMIT 1");
        $stmt->execute([':c' => $codeOrId]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($role) {
            $role['permissions_array'] = json_decode($role['permissions'] ?: '[]', true) ?: [];
        }
        return $role ?: null;
    }

    /**
     * Create custom role
     */
    public static function createRole($data, ?string $name = null, ?string $desc = null, $perms = []): string
    {
        self::ensureTables();
        $db = Database::getConnection();

        if (!is_array($data)) {
            $data = [
                'code' => (string)$data,
                'name' => (string)($name ?? $data),
                'description' => (string)($desc ?? ''),
                'permissions' => $perms
            ];
        }

        $name = trim($data['name'] ?? '');
        $code = strtoupper(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $data['code'] ?? $name)));
        $desc = trim($data['description'] ?? '');
        $color = $data['badge_color'] ?? 'green';
        $perms = !empty($data['permissions']) ? (is_array($data['permissions']) ? json_encode($data['permissions']) : $data['permissions']) : '[]';

        if (empty($name) || empty($code)) {
            throw new \InvalidArgumentException('Role name and code are required.');
        }

        $check = $db->prepare("SELECT id FROM admin_roles WHERE code = :code LIMIT 1");
        $check->execute([':code' => $code]);
        if ($check->fetchColumn()) {
            throw new \InvalidArgumentException("Role code '{$code}' already exists.");
        }

        $id = 'role_' . bin2hex(random_bytes(6));
        $stmt = $db->prepare("
            INSERT INTO admin_roles (id, code, name, description, is_system, badge_color, permissions, created_at, updated_at)
            VALUES (:id, :code, :name, :desc, 0, :color, :perms, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':id' => $id,
            ':code' => $code,
            ':name' => $name,
            ':desc' => $desc,
            ':color' => $color,
            ':perms' => $perms
        ]);

        AuditService::log('role.create', 'admin_role', $id, "Created custom role '{$name}' ({$code}).");
        return $id;
    }

    /**
     * Update role details and permissions
     */
    public static function updateRole(string $id, array $data): bool
    {
        self::ensureTables();
        $db = Database::getConnection();

        $role = self::getRole($id);
        if (!$role) return false;

        $name = trim($data['name'] ?? $role['name']);
        $desc = trim($data['description'] ?? $role['description']);
        $color = $data['badge_color'] ?? $role['badge_color'];
        $perms = isset($data['permissions']) ? (is_array($data['permissions']) ? json_encode($data['permissions']) : $data['permissions']) : $role['permissions'];

        $stmt = $db->prepare("
            UPDATE admin_roles 
            SET name = :name, description = :desc, badge_color = :color, permissions = :perms, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id OR code = :id
        ");
        $res = $stmt->execute([
            ':name' => $name,
            ':desc' => $desc,
            ':color' => $color,
            ':perms' => $perms,
            ':id' => $id
        ]);

        AuditService::log('role.update', 'admin_role', $id, "Updated role '{$name}' permissions and configuration.");
        return $res;
    }

    /**
     * Duplicate role
     */
    public static function duplicateRole(string $id, ?string $newCode = null, ?string $newName = null): string
    {
        $role = self::getRole($id);
        if (!$role) {
            throw new \InvalidArgumentException('Role to duplicate not found.');
        }

        $newName = $newName ?: ($role['name'] . ' (Copy)');
        $newCode = $newCode ?: (substr($role['code'], 0, 20) . '_COPY_' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)));

        return self::createRole([
            'name' => $newName,
            'code' => $newCode,
            'description' => 'Cloned from ' . $role['name'] . '. ' . ($role['description'] ?? ''),
            'badge_color' => $role['badge_color'] ?? 'green',
            'permissions' => $role['permissions_array'] ?? []
        ]);
    }

    /**
     * Delete custom role
     */
    public static function deleteRole(string $id): bool
    {
        self::ensureTables();
        $db = Database::getConnection();

        $role = self::getRole($id);
        if (!$role) return false;

        if (!empty($role['is_system'])) {
            throw new \RuntimeException("System role '{$role['name']}' is protected and cannot be deleted.");
        }

        // Check if users are assigned
        $userCount = (int)$db->query("SELECT COUNT(*) FROM admin_users WHERE role = '{$role['code']}'")->fetchColumn();
        if ($userCount > 0) {
            throw new \RuntimeException("Cannot delete role '{$role['name']}' because {$userCount} users are currently assigned to it. Please reassign them first.");
        }

        $stmt = $db->prepare("DELETE FROM admin_roles WHERE id = :id");
        $res = $stmt->execute([':id' => $role['id']]);

        AuditService::log('role.delete', 'admin_role', $role['id'], "Deleted role '{$role['name']}' ({$role['code']}).");
        return $res;
    }

    /**
     * Check if a user has a specific permission
     */
    public static function hasPermission(string $action): bool
    {
        $user = AuthService::user();
        if (($user['role'] ?? '') === 'SUPER_ADMIN') {
            return true;
        }

        $role = self::getRole($user['role'] ?? '');
        if (!$role) return false;

        $perms = $role['permissions_array'] ?? [];
        if (in_array('*', $perms, true)) return true;

        if (in_array($action, $perms, true)) return true;

        // Check wildcard prefix (e.g. orders.* matches orders.view)
        $parts = explode('.', $action);
        if (count($parts) === 2) {
            if (in_array($parts[0] . '.*', $perms, true)) return true;
        }

        return false;
    }

    /**
     * Verify whether a user is authorized to access a specific website/store
     */
    public static function canUserAccessStore(string $userId, string $websiteId): bool
    {
        $user = self::getUserById($userId);
        if (!$user) return false;

        if ($user['role'] === 'SUPER_ADMIN') return true;

        if (($user['store_access_type'] ?? 'ALL') === 'ALL') return true;

        $allowed = json_decode($user['allowed_stores'] ?: '[]', true) ?: [];
        return in_array($websiteId, $allowed, true);
    }

    /**
     * Update user store access
     */
    public static function updateUserStoreAccess(string $id, string $storeType, array $allowedStoreIds): bool
    {
        self::ensureTables();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            UPDATE admin_users 
            SET store_access_type = :st_type, allowed_stores = :stores
            WHERE id = :id
        ");
        $res = $stmt->execute([
            ':st_type' => $storeType === 'ALL' ? 'ALL' : 'SPECIFIC',
            ':stores' => json_encode(array_values($allowedStoreIds)),
            ':id' => $id
        ]);

        AuditService::log('store_access.update', 'admin_user', $id, "Updated store access scope to {$storeType}.");
        return $res;
    }

    /**
     * Query security audit logs with multi-field search and filters
     */
    public static function getAuditLogs($limitOrFilters = 50, ?string $search = null, ?string $action = null): array
    {
        self::ensureTables();
        $db = Database::getConnection();

        $limit = 50;
        if (is_array($limitOrFilters)) {
            $search = !empty($limitOrFilters['search']) ? trim($limitOrFilters['search']) : null;
            $action = !empty($limitOrFilters['action']) && $limitOrFilters['action'] !== 'ALL' ? $limitOrFilters['action'] : null;
            $limit = max(1, min(1000, (int)($limitOrFilters['limit'] ?? $limitOrFilters['per_page'] ?? 50)));
        } else {
            $limit = max(1, min(1000, (int)$limitOrFilters));
        }

        $where = ['1=1'];
        $params = [];

        if (!empty($search)) {
            $where[] = "(actor_email LIKE :search OR action_type LIKE :search OR details LIKE :search OR target_resource LIKE :search OR ip_address LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if (!empty($action) && $action !== 'ALL') {
            $where[] = "action_type LIKE :action";
            $params[':action'] = '%' . $action . '%';
        }

        $whereSql = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT * FROM admin_audit_logs 
            WHERE {$whereSql}
            ORDER BY created_at DESC 
            LIMIT :lim
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Safely write an administrative audit event
     */
    public static function logAudit(
        string $actionType,
        string $targetResource,
        string $targetId,
        array $details = [],
        string $status = 'SUCCESS'
    ): void {
        try {
            self::ensureTables();
            $db = Database::getConnection();
            $actorId = $_SESSION['nutronyx_admin_id'] ?? $targetId;
            $actorEmail = $_SESSION['nutronyx_admin_email'] ?? ($details['email'] ?? 'system@nutronyx.com');
            $websiteId = Context::getWebsiteId() ?: 'web_nutronyx_d2c';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $stmt = $db->prepare("
                INSERT INTO admin_audit_logs 
                (id, website_id, actor_id, actor_email, ip_address, action_type, target_resource, target_id, details, status, created_at)
                VALUES 
                (:id, :website_id, :actor_id, :actor_email, :ip_address, :action_type, :target_resource, :target_id, :details, :status, :created_at)
            ");
            $stmt->execute([
                ':id' => 'aud_' . bin2hex(random_bytes(8)),
                ':website_id' => $websiteId,
                ':actor_id' => $actorId,
                ':actor_email' => $actorEmail,
                ':ip_address' => $ip,
                ':action_type' => $actionType,
                ':target_resource' => $targetResource,
                ':target_id' => $targetId,
                ':details' => json_encode($details, JSON_UNESCAPED_SLASHES),
                ':status' => $status,
                ':created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            // Silently swallow so audit logging never crashes authentication or user operations
            error_log('Audit log error in RoleService: ' . $e->getMessage());
        }
    }
}

