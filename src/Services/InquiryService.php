<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;
use PDO;

class InquiryService
{
    /**
     * Target notification recipient email
     */
    public const NOTIFICATION_EMAIL = 'nutronyx.offical@gmail.com';

    /**
     * Ensure database schema exists
     */
    public static function ensureTables(): void
    {
        $db = Database::getConnection();

        $db->exec("
            CREATE TABLE IF NOT EXISTS customer_inquiries (
                id VARCHAR(40) PRIMARY KEY,
                website_id VARCHAR(36) NOT NULL,
                type VARCHAR(30) NOT NULL DEFAULT 'CONTACT', -- 'CONTACT' or 'PARTNERSHIP'
                name VARCHAR(150) NOT NULL,
                email VARCHAR(150) NOT NULL,
                phone VARCHAR(50),
                company VARCHAR(150),
                category VARCHAR(120),
                message TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'NEW', -- 'NEW', 'CONTACTED', 'RESOLVED', 'ARCHIVED'
                admin_notes TEXT,
                ip_address VARCHAR(45),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_inq_wid_type ON customer_inquiries(website_id, type);
            CREATE INDEX IF NOT EXISTS idx_inq_status ON customer_inquiries(status);
            CREATE INDEX IF NOT EXISTS idx_inq_created ON customer_inquiries(created_at);
        ");
    }

    /**
     * Save a new inquiry (Contact or Partnership) and dispatch email notification
     */
    public static function createInquiry(array $data): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId() ?: 'web_default_01';

        $id = 'inq_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $type = strtoupper(trim((string)($data['type'] ?? 'CONTACT')));
        if (!in_array($type, ['CONTACT', 'PARTNERSHIP'], true)) {
            $type = 'CONTACT';
        }

        $name = trim(strip_tags((string)($data['name'] ?? '')));
        $email = trim(strip_tags((string)($data['email'] ?? '')));
        $phone = trim(strip_tags((string)($data['phone'] ?? '')));
        $company = trim(strip_tags((string)($data['company'] ?? '')));
        $category = trim(strip_tags((string)($data['category'] ?? '')));
        $message = trim((string)($data['message'] ?? ''));
        $ipAddress = substr((string)($data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')), 0, 45);

        $stmt = $db->prepare("
            INSERT INTO customer_inquiries 
            (id, website_id, type, name, email, phone, company, category, message, status, admin_notes, ip_address, created_at, updated_at)
            VALUES 
            (:id, :website_id, :type, :name, :email, :phone, :company, :category, :message, 'NEW', NULL, :ip_address, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            ':id'         => $id,
            ':website_id' => $websiteId,
            ':type'       => $type,
            ':name'       => $name,
            ':email'      => $email,
            ':phone'      => $phone ?: null,
            ':company'    => $company ?: null,
            ':category'   => $category ?: null,
            ':message'    => $message,
            ':ip_address' => $ipAddress,
        ]);

        $record = [
            'id'         => $id,
            'website_id' => $websiteId,
            'type'       => $type,
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone,
            'company'    => $company,
            'category'   => $category,
            'message'    => $message,
            'status'     => 'NEW',
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Send email to nutronyx.offical@gmail.com
        self::sendNotificationEmail($record);

        return $record;
    }

    /**
     * Dispatch notification email to store owner
     */
    public static function sendNotificationEmail(array $inquiry): bool
    {
        $to = self::NOTIFICATION_EMAIL;
        $typeLabel = ($inquiry['type'] === 'PARTNERSHIP') ? 'New Partnership Application' : 'New Contact Inquiry';
        $subject = "🌱 [Nutronyx Alert] {$typeLabel} from {$inquiry['name']}";

        $name = htmlspecialchars($inquiry['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($inquiry['email'] ?? '', ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars($inquiry['phone'] ?? 'Not provided', ENT_QUOTES, 'UTF-8');
        $company = htmlspecialchars($inquiry['company'] ?? 'Individual / Not specified', ENT_QUOTES, 'UTF-8');
        $category = htmlspecialchars($inquiry['category'] ?? 'General', ENT_QUOTES, 'UTF-8');
        $messageHtml = nl2br(htmlspecialchars($inquiry['message'] ?? '', ENT_QUOTES, 'UTF-8'));
        $rawMessage = $inquiry['message'] ?? '';
        $id = htmlspecialchars($inquiry['id'] ?? '', ENT_QUOTES, 'UTF-8');
        $date = date('d M Y, h:i A T');

        $boundary = '==Part_' . md5((string)time()) . '_' . bin2hex(random_bytes(4));

        $headers = [
            'From: Nutronyx Botanical Concierge <no-reply@nutronyx.com>',
            'Reply-To: ' . ($inquiry['email'] ?? 'care@nutronyx.com'),
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            'X-Mailer: Nutronyx Engine/2.0'
        ];

        // Plaintext version
        $plainText = <<<TXT
==================================================
NUTRONYX BOTANICAL CONCIERGE — NEW LEAD ALERT
==================================================
Type:        {$typeLabel}
Lead ID:     {$inquiry['id']}
Date:        {$date}

SUBMITTER DETAILS:
Full Name:   {$inquiry['name']}
Email:       {$inquiry['email']}
Phone:       {$inquiry['phone']}
Organization/Brand: {$inquiry['company']}
Category/Subject:   {$inquiry['category']}

MESSAGE / PROPOSAL:
--------------------------------------------------
{$rawMessage}
--------------------------------------------------

Manage this lead in Nutronyx Admin:
https://nutronyx.in/admin/inquiries.php

Nutronyx Botanical Concierge System
TXT;

        // Rich HTML version
        $accentColor = ($inquiry['type'] === 'PARTNERSHIP') ? '#B8860B' : '#143D24';
        $typeBadgeBg = ($inquiry['type'] === 'PARTNERSHIP') ? '#FEF3C7' : '#E8F5E9';
        $typeBadgeColor = ($inquiry['type'] === 'PARTNERSHIP') ? '#92400E' : '#166534';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8F6F0; margin: 0; padding: 24px; color: #1F2937; }
.card { max-width: 620px; margin: 0 auto; background: #FFFFFF; border-radius: 14px; border: 1px solid #E5E7EB; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
.header { background: #143D24; padding: 28px 32px; color: #FFFFFF; }
.header h1 { margin: 0; font-size: 22px; font-weight: 700; letter-spacing: 0.02em; }
.header p { margin: 6px 0 0; font-size: 13px; color: #D1E7DD; letter-spacing: 0.05em; text-transform: uppercase; }
.content { padding: 32px; }
.badge { display: inline-block; padding: 6px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; border-radius: 20px; background: {$typeBadgeBg}; color: {$typeBadgeColor}; margin-bottom: 20px; }
.details-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
.details-table th, .details-table td { text-align: left; padding: 10px 12px; font-size: 14px; border-bottom: 1px solid #F3F4F6; }
.details-table th { width: 150px; color: #6B7280; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; }
.details-table td { color: #111827; font-weight: 500; }
.msg-box { background: #FAF9F6; border-left: 4px solid {$accentColor}; padding: 18px 20px; border-radius: 6px; font-size: 14px; line-height: 1.6; color: #1F2937; margin-bottom: 24px; white-space: pre-wrap; word-break: break-word; }
.cta-wrap { text-align: center; margin-top: 24px; }
.cta-btn { display: inline-block; background: #143D24; color: #FFFFFF !important; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 13.5px; }
.footer { background: #F9FAFB; padding: 18px 32px; font-size: 12px; color: #9CA3AF; text-align: center; border-top: 1px solid #E5E7EB; }
</style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>NUTRONYX</h1>
        <p>Botanical Concierge &bull; Lead Notification</p>
    </div>
    <div class="content">
        <span class="badge">{$typeLabel}</span>
        <h2 style="margin: 0 0 16px; font-size: 18px; color: #111827;">New Submission from {$name}</h2>
        <table class="details-table">
            <tr><th>Full Name</th><td>{$name}</td></tr>
            <tr><th>Email</th><td><a href="mailto:{$email}" style="color: #143D24; font-weight: 600; text-decoration: none;">{$email}</a></td></tr>
            <tr><th>Phone</th><td><a href="tel:{$phone}" style="color: #143D24; text-decoration: none;">{$phone}</a></td></tr>
            <tr><th>Company / Brand</th><td>{$company}</td></tr>
            <tr><th>Category / Subject</th><td><strong>{$category}</strong></td></tr>
            <tr><th>Received At</th><td>{$date}</td></tr>
            <tr><th>Inquiry ID</th><td><code>{$id}</code></td></tr>
        </table>

        <div style="font-size: 12px; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Message / Proposal:</div>
        <div class="msg-box">{$messageHtml}</div>

        <div class="cta-wrap">
            <a href="mailto:{$email}?subject=Re:%20Nutronyx%20Inquiry%20- {$category}" class="cta-btn">Reply to {$name}</a>
        </div>
    </div>
    <div class="footer">
        Sent automatically to nutronyx.offical@gmail.com &bull; Nutronyx Administrative Core
    </div>
</div>
</body>
</html>
HTML;

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $plainText . "\r\n\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $html . "\r\n\r\n";

        $body .= "--{$boundary}--";

        $mailSent = @mail($to, $subject, $body, implode("\r\n", $headers));
        return (bool)$mailSent;
    }

    /**
     * Retrieve inquiries with filtering, search, and pagination
     */
    public static function getInquiries(array $filters = []): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId() ?: 'web_default_01';

        $type = strtoupper((string)($filters['type'] ?? 'ALL'));
        $status = strtoupper((string)($filters['status'] ?? 'ALL'));
        $search = trim((string)($filters['search'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(5, min(100, (int)($filters['per_page'] ?? 15)));
        $offset = ($page - 1) * $perPage;

        $where = ['website_id = :wid'];
        $params = [':wid' => $websiteId];

        if ($type !== 'ALL') {
            $where[] = 'type = :type';
            $params[':type'] = $type;
        }

        if ($status !== 'ALL') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }

        if ($search !== '') {
            $where[] = '(name LIKE :q OR email LIKE :q OR phone LIKE :q OR company LIKE :q OR category LIKE :q OR message LIKE :q)';
            $params[':q'] = "%{$search}%";
        }

        $whereSql = implode(' AND ', $where);

        // Count total matching
        $countStmt = $db->prepare("SELECT COUNT(*) FROM customer_inquiries WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Fetch paginated results
        $selectStmt = $db->prepare("
            SELECT * FROM customer_inquiries 
            WHERE {$whereSql} 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $k => $v) {
            $selectStmt->bindValue($k, $v);
        }
        $selectStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $selectStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $selectStmt->execute();
        $inquiries = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

        // Analytics counters
        $statsStmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'NEW' THEN 1 ELSE 0 END) as new_count,
                SUM(CASE WHEN type = 'CONTACT' THEN 1 ELSE 0 END) as contact_count,
                SUM(CASE WHEN type = 'PARTNERSHIP' THEN 1 ELSE 0 END) as partnership_count,
                SUM(CASE WHEN type = 'WELCOME_OFFER' THEN 1 ELSE 0 END) as welcome_count
            FROM customer_inquiries 
            WHERE website_id = :wid
        ");
        $statsStmt->execute([':wid' => $websiteId]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'inquiries'   => $inquiries,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
            'stats'       => [
                'total'             => (int)($stats['total'] ?? 0),
                'new'               => (int)($stats['new_count'] ?? 0),
                'contact'           => (int)($stats['contact_count'] ?? 0),
                'partnership'       => (int)($stats['partnership_count'] ?? 0),
                'welcome'           => (int)($stats['welcome_count'] ?? 0),
            ]
        ];
    }

    /**
     * Get a single inquiry by ID
     */
    public static function getInquiryById(string $id): ?array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM customer_inquiries WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update inquiry status and admin notes
     */
    public static function updateStatus(string $id, string $status, ?string $adminNotes = null): bool
    {
        self::ensureTables();
        $db = Database::getConnection();
        $status = strtoupper(trim($status));
        $allowed = ['NEW', 'CONTACTED', 'RESOLVED', 'ARCHIVED'];
        if (!in_array($status, $allowed, true)) {
            $status = 'CONTACTED';
        }

        $sql = "UPDATE customer_inquiries SET status = :status, updated_at = CURRENT_TIMESTAMP";
        $params = [':id' => $id, ':status' => $status];

        if ($adminNotes !== null) {
            $sql .= ", admin_notes = :notes";
            $params[':notes'] = $adminNotes;
        }

        $sql .= " WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete an inquiry
     */
    public static function deleteInquiry(string $id): bool
    {
        self::ensureTables();
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM customer_inquiries WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get count of new unread inquiries
     */
    public static function getUnreadCount(): int
    {
        self::ensureTables();
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId() ?: 'web_default_01';
        $stmt = $db->prepare("SELECT COUNT(*) FROM customer_inquiries WHERE website_id = :wid AND status = 'NEW'");
        $stmt->execute([':wid' => $websiteId]);
        return (int)$stmt->fetchColumn();
    }
}
