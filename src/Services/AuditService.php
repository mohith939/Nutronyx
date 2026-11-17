<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;

class AuditService
{
    public static function log(string $actionType, string $targetResource, string $targetId, string $details = ''): void
    {
        try {
            $user = AuthService::user();
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO admin_audit_logs (id, website_id, actor_id, actor_email, ip_address, action_type, target_resource, target_id, details)
                VALUES (:id, :website_id, :actor_id, :actor_email, :ip, :action, :res, :target_id, :details)
            ");
            $stmt->execute([
                ':id' => 'log_' . bin2hex(random_bytes(8)),
                ':website_id' => Context::getWebsiteId(),
                ':actor_id' => $user['id'],
                ':actor_email' => $user['email'],
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ':action' => $actionType,
                ':res' => $targetResource,
                ':target_id' => $targetId,
                ':details' => $details
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to write audit log: " . $e->getMessage());
        }
    }

    public static function getRecentLogs(int $limit = 50): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM admin_audit_logs 
            WHERE website_id = :wid 
            ORDER BY created_at DESC 
            LIMIT :lim
        ");
        $stmt->bindValue(':wid', Context::getWebsiteId());
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
