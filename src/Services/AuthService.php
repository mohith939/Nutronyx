<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use PDO;

class AuthService
{
    public static function check(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['nutronyx_admin_logged']);
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function user(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return [
            'id' => $_SESSION['nutronyx_admin_id'] ?? 'usr_mohith',
            'email' => $_SESSION['nutronyx_admin_email'] ?? 'mohith@nutronyx.com',
            'name' => $_SESSION['nutronyx_admin_name'] ?? 'Mohith',
            'role' => $_SESSION['nutronyx_admin_role'] ?? 'SUPER_ADMIN',
        ];
    }

    public static function hasPermission(string $action): bool
    {
        $user = self::user();
        if ($user['role'] === 'SUPER_ADMIN') {
            return true;
        }
        
        // Parse module.action format e.g. products.create
        if (str_contains($action, '.')) {
            [$module, $act] = explode('.', $action, 2);
            return RoleService::hasPermission($user['role'], $module, $act);
        }
        
        return RoleService::hasPermission($user['role'], $action, 'view');
    }

    public static function createAdminUser(string $name, string $email, string $password, string $role = 'OPERATOR'): string
    {
        $db = Database::getConnection();
        $id = 'usr_' . bin2hex(random_bytes(6));
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $db->prepare("
            INSERT INTO admin_users (id, name, email, password_hash, role, status)
            VALUES (:id, :name, :email, :hash, :role, 'ACTIVE')
        ");
        $stmt->execute([
            ':id' => $id,
            ':name' => trim($name),
            ':email' => strtolower(trim($email)),
            ':hash' => $hash,
            ':role' => strtoupper(trim($role))
        ]);

        AuditService::log('admin_user.create', 'admin_user', $id, "Created staff user {$email} with role {$role}");
        return $id;
    }
}
