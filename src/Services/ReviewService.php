<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;
use PDO;

class ReviewService
{
    /**
     * Ensure database schema has all required columns and initial seed data
     */
    public static function ensureTables(): void
    {
        $db = Database::getConnection();

        // 1. Check existing columns in reviews table
        $cols = [];
        $res = $db->query("PRAGMA table_info(reviews)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res as $r) {
            $cols[] = strtolower($r['name']);
        }

        $neededCols = [
            'variant_id' => 'VARCHAR(36)',
            'customer_id' => 'VARCHAR(36)',
            'review_source' => "VARCHAR(30) DEFAULT 'CUSTOMER_SUBMITTED'",
            'order_id' => 'VARCHAR(36)',
            'admin_reply_author' => 'VARCHAR(120)',
            'admin_reply_at' => 'DATETIME',
            'internal_notes' => 'TEXT',
            'moderated_by' => 'VARCHAR(120)',
            'moderated_at' => 'DATETIME',
            'created_by_admin' => 'VARCHAR(120)',
            'updated_at' => 'DATETIME'
        ];

        foreach ($neededCols as $col => $definition) {
            if (!in_array(strtolower($col), $cols, true)) {
                try {
                    $db->exec("ALTER TABLE reviews ADD COLUMN {$col} {$definition}");
                } catch (\Throwable $e) {
                    // Column might already exist or table locked
                }
            }
        }
    }

    /**
     * Retrieve reviews with multi-field search, filtering, sorting, and pagination
     */
    public static function getReviews($filters = null): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        if (is_string($filters) || $filters === null) {
            $filters = ['status' => $filters];
        }

        $status = !empty($filters['status']) && strtoupper($filters['status']) !== 'ALL' ? strtoupper($filters['status']) : null;
        $rating = !empty($filters['rating']) ? (int)$filters['rating'] : null;
        $productId = !empty($filters['product_id']) && $filters['product_id'] !== 'ALL' ? $filters['product_id'] : null;
        $customerId = !empty($filters['customer_id']) ? $filters['customer_id'] : null;
        $search = !empty($filters['search']) ? trim($filters['search']) : null;
        $source = !empty($filters['source']) ? strtoupper($filters['source']) : null;
        $sortBy = $filters['sort_by'] ?? 'newest';
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int)($filters['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;

        $where = ['r.website_id = :wid'];
        $params = [':wid' => $websiteId];

        if ($status) {
            $where[] = 'r.status = :status';
            $params[':status'] = $status;
        }

        if ($rating) {
            $where[] = 'r.rating = :rating';
            $params[':rating'] = $rating;
        }

        if ($productId) {
            $where[] = 'r.product_id = :product_id';
            $params[':product_id'] = $productId;
        }

        if ($customerId) {
            $where[] = 'r.customer_id = :customer_id';
            $params[':customer_id'] = $customerId;
        }

        if ($source) {
            $where[] = 'r.review_source = :source';
            $params[':source'] = $source;
        }

        if ($search) {
            $where[] = '(r.title LIKE :search OR r.comment LIKE :search OR r.customer_name LIKE :search OR p.title LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);

        $orderSql = match ($sortBy) {
            'oldest' => 'r.created_at ASC',
            'rating_high' => 'r.rating DESC, r.created_at DESC',
            'rating_low' => 'r.rating ASC, r.created_at DESC',
            default => 'r.created_at DESC'
        };

        // Total count query
        $countSql = "
            SELECT COUNT(*) 
            FROM reviews r
            LEFT JOIN products p ON r.product_id = p.id
            WHERE {$whereSql}
        ";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Data query with joins
        $dataSql = "
            SELECT r.*, 
                   p.title as product_title, 
                   p.image_url as product_image, 
                   p.slug as product_slug,
                   pv.title as variant_title, 
                   pv.weight_grams as variant_weight,
                   c.email as customer_email, 
                   c.phone as customer_phone,
                   o.order_number, 
                   o.order_status, 
                   o.payment_status
            FROM reviews r
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN product_variants pv ON r.variant_id = pv.id
            LEFT JOIN customers c ON r.customer_id = c.id
            LEFT JOIN orders o ON r.order_id = o.id
            WHERE {$whereSql}
            ORDER BY {$orderSql}
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($dataSql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (isset($filters['return_envelope']) && !$filters['return_envelope']) {
            return $reviews;
        }

        return [
            'reviews' => $reviews,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
            'offset' => $offset
        ];
    }

    /**
     * Get a single review by ID with complete joined relationships
     */
    public static function getReviewById(string $id): ?array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT r.*, 
                   p.title as product_title, 
                   p.image_url as product_image, 
                   p.slug as product_slug,
                   pv.title as variant_title, 
                   pv.weight_grams as variant_weight,
                   c.name as customer_account_name, 
                   c.email as customer_email, 
                   c.phone as customer_phone,
                   o.order_number, 
                   o.order_status, 
                   o.payment_status, 
                   o.grand_total_amount, 
                   o.created_at as order_created_at
            FROM reviews r
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN product_variants pv ON r.variant_id = pv.id
            LEFT JOIN customers c ON r.customer_id = c.id
            LEFT JOIN orders o ON r.order_id = o.id
            WHERE r.id = :id AND r.website_id = :wid
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':wid' => Context::getWebsiteId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get recent audit logs for a specific review
     */
    public static function getAuditLogsForReview(string $reviewId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM admin_audit_logs 
            WHERE target_resource = 'review' AND target_id = :id 
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $stmt->execute([':id' => $reviewId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate summary KPIs and rating distribution dynamically from real data
     */
    public static function getReviewAnalytics(): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_count,
                COUNT(CASE WHEN status = 'PENDING' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'APPROVED' THEN 1 END) as approved_count,
                COUNT(CASE WHEN status = 'REJECTED' THEN 1 END) as rejected_count,
                COUNT(CASE WHEN status = 'HIDDEN' THEN 1 END) as hidden_count,
                COUNT(CASE WHEN is_verified_buyer = 1 AND status = 'APPROVED' THEN 1 END) as verified_count,
                AVG(CASE WHEN status = 'APPROVED' THEN rating END) as avg_rating
            FROM reviews
            WHERE website_id = :wid
        ");
        $stmt->execute([':wid' => $wid]);
        $core = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalCount = (int)($core['total_count'] ?? 0);
        $pendingCount = (int)($core['pending_count'] ?? 0);
        $approvedCount = (int)($core['approved_count'] ?? 0);
        $rejectedCount = (int)($core['rejected_count'] ?? 0);
        $hiddenCount = (int)($core['hidden_count'] ?? 0);
        $verifiedCount = (int)($core['verified_count'] ?? 0);
        $avgRating = $core['avg_rating'] !== null ? round((float)$core['avg_rating'], 1) : 0.0;

        $distStmt = $db->prepare("
            SELECT rating, COUNT(*) as count 
            FROM reviews 
            WHERE website_id = :wid AND status = 'APPROVED'
            GROUP BY rating
        ");
        $distStmt->execute([':wid' => $wid]);
        $rows = $distStmt->fetchAll(PDO::FETCH_ASSOC);

        $ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $ratingPercentages = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($rows as $r) {
            $star = (int)$r['rating'];
            if ($star >= 1 && $star <= 5) {
                $ratingCounts[$star] = (int)$r['count'];
            }
        }

        $baseCount = max(1, $approvedCount);
        foreach ($ratingCounts as $star => $count) {
            $ratingPercentages[$star] = round(($count / $baseCount) * 100, 1);
        }

        return [
            'total_reviews' => $totalCount,
            'average_rating' => $avgRating,
            'pending_reviews' => $pendingCount,
            'approved_reviews' => $approvedCount,
            'rejected_reviews' => $rejectedCount,
            'hidden_reviews' => $hiddenCount,
            'verified_reviews' => $verifiedCount,
            'rating_counts' => $ratingCounts,
            'rating_percentages' => $ratingPercentages
        ];
    }

    /**
     * Verify whether a customer genuinely purchased a product or variant
     * Checks real orders and order_items records in the database
     */
    public static function verifyPurchase(
        string $websiteId,
        ?string $customerId,
        ?string $customerName,
        ?string $customerEmail,
        ?string $customerPhone,
        string $productId,
        ?string $variantId = null
    ): ?string {
        $db = Database::getConnection();

        $sql = "
            SELECT o.id 
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN product_variants pv ON oi.variant_id = pv.id
            WHERE o.website_id = :wid
              AND o.order_status NOT IN ('CANCELLED', 'REFUNDED')
              AND (oi.variant_id = :vid OR pv.product_id = :pid)
              AND (
                  (:cid IS NOT NULL AND o.customer_id = :cid)
                  OR (:cphone IS NOT NULL AND (o.shipping_phone LIKE :cphone_like OR o.customer_id IN (SELECT id FROM customers WHERE phone LIKE :cphone_like)))
                  OR (:cemail IS NOT NULL AND o.customer_id IN (SELECT id FROM customers WHERE email = :cemail))
                  OR (:cname IS NOT NULL AND LOWER(TRIM(o.shipping_name)) = LOWER(TRIM(:cname)))
              )
            ORDER BY o.created_at DESC
            LIMIT 1
        ";

        $cleanPhone = $customerPhone ? preg_replace('/[^0-9]/', '', $customerPhone) : '';
        $phoneLike = strlen($cleanPhone) >= 7 ? '%' . substr($cleanPhone, -7) . '%' : 'NONE';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':wid' => $websiteId,
            ':vid' => $variantId ?: 'none',
            ':pid' => $productId,
            ':cid' => $customerId ?: null,
            ':cphone' => $customerPhone ?: null,
            ':cphone_like' => $phoneLike,
            ':cemail' => $customerEmail ?: null,
            ':cname' => $customerName ?: null
        ]);

        $orderId = $stmt->fetchColumn();
        return $orderId ? (string)$orderId : null;
    }

    /**
     * Manual admin review creation with full verification checks and audit trail
     */
    public static function createAdminReview(array $data): string
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $currentUser = AuthService::user();

        $productId = trim($data['product_id'] ?? '');
        if (empty($productId)) {
            throw new \InvalidArgumentException('Please select a valid product.');
        }

        $variantId = !empty($data['variant_id']) ? trim($data['variant_id']) : null;
        $customerId = !empty($data['customer_id']) ? trim($data['customer_id']) : null;
        $customerName = trim($data['customer_name'] ?? '');
        if (empty($customerName)) {
            if ($customerId) {
                $cStmt = $db->prepare("SELECT name, email, phone FROM customers WHERE id = :id LIMIT 1");
                $cStmt->execute([':id' => $customerId]);
                $cust = $cStmt->fetch(PDO::FETCH_ASSOC);
                if ($cust) {
                    $customerName = $cust['name'];
                }
            }
            if (empty($customerName)) {
                $customerName = 'Verified Customer';
            }
        }

        $customerEmail = null;
        $customerPhone = null;
        if ($customerId) {
            $cStmt = $db->prepare("SELECT email, phone FROM customers WHERE id = :id LIMIT 1");
            $cStmt->execute([':id' => $customerId]);
            $cRow = $cStmt->fetch(PDO::FETCH_ASSOC);
            if ($cRow) {
                $customerEmail = $cRow['email'] ?? null;
                $customerPhone = $cRow['phone'] ?? null;
            }
        }

        $verifiedOrderId = self::verifyPurchase(
            $wid,
            $customerId,
            $customerName,
            $customerEmail,
            $customerPhone,
            $productId,
            $variantId
        );

        $isVerified = $verifiedOrderId ? 1 : 0;
        $orderId = $verifiedOrderId ?: (!empty($data['order_id']) ? trim($data['order_id']) : null);

        $rating = max(1, min(5, (int)($data['rating'] ?? 5)));
        $title = trim($data['title'] ?? '');
        $comment = trim($data['comment'] ?? '');
        if (empty($comment)) {
            throw new \InvalidArgumentException('Review content is required.');
        }

        $status = in_array(strtoupper($data['status'] ?? ''), ['APPROVED', 'PENDING', 'REJECTED', 'HIDDEN'], true)
            ? strtoupper($data['status'])
            : 'APPROVED';

        $createdAt = !empty($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s');
        if (strlen($createdAt) === 10) {
            $createdAt .= ' ' . date('H:i:s');
        }

        $adminReply = trim($data['admin_reply'] ?? '') ?: null;
        $adminReplyAuthor = $adminReply ? ($currentUser['name'] ?? 'Admin') : null;
        $adminReplyAt = $adminReply ? date('Y-m-d H:i:s') : null;

        $internalNotes = trim($data['internal_notes'] ?? '') ?: null;
        $creatorName = $currentUser['name'] ?? 'Mohith (Admin)';

        $id = 'rev_' . bin2hex(random_bytes(6));

        $stmt = $db->prepare("
            INSERT INTO reviews (
                id, website_id, product_id, variant_id, customer_id, customer_name, rating, title, comment,
                status, is_verified_buyer, review_source, order_id, admin_reply, admin_reply_author, admin_reply_at,
                internal_notes, moderated_by, moderated_at, created_by_admin, created_at, updated_at
            ) VALUES (
                :id, :wid, :pid, :vid, :cid, :cname, :rating, :title, :comment,
                :status, :is_verified, 'ADMIN_ADDED', :oid, :reply, :reply_author, :reply_at,
                :notes, :mod_by, :mod_at, :creator, :created_at, CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':id' => $id,
            ':wid' => $wid,
            ':pid' => $productId,
            ':vid' => $variantId,
            ':cid' => $customerId,
            ':cname' => $customerName,
            ':rating' => $rating,
            ':title' => $title,
            ':comment' => $comment,
            ':status' => $status,
            ':is_verified' => $isVerified,
            ':oid' => $orderId,
            ':reply' => $adminReply,
            ':reply_author' => $adminReplyAuthor,
            ':reply_at' => $adminReplyAt,
            ':notes' => $internalNotes,
            ':mod_by' => $status === 'APPROVED' ? $creatorName : null,
            ':mod_at' => $status === 'APPROVED' ? date('Y-m-d H:i:s') : null,
            ':creator' => $creatorName,
            ':created_at' => $createdAt
        ]);

        AuditService::log(
            'review.create_manual',
            'review',
            $id,
            "Admin created manual review for product {$productId} ({$rating}★) with status {$status}. Verified: " . ($isVerified ? 'Yes' : 'No')
        );

        return $id;
    }

    public static function createReview(array $data): string
    {
        return self::createAdminReview($data);
    }

    /**
     * Public customer review submission from storefront (sets status to PENDING)
     */
    public static function submitCustomerReview(array $data): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $productId = trim($data['product_id'] ?? '');
        if (empty($productId)) {
            return ['success' => false, 'error' => 'Product is required.'];
        }

        $customerName = trim($data['customer_name'] ?? '');
        if (empty($customerName)) {
            return ['success' => false, 'error' => 'Your name is required.'];
        }

        $customerEmail = trim($data['customer_email'] ?? '') ?: null;
        $customerPhone = trim($data['customer_phone'] ?? '') ?: null;
        $customerId = trim($data['customer_id'] ?? '') ?: null;
        $variantId = trim($data['variant_id'] ?? '') ?: null;

        $rating = max(1, min(5, (int)($data['rating'] ?? 5)));
        $title = trim($data['title'] ?? '');
        $comment = trim($data['comment'] ?? '');

        if (strlen($comment) < 10) {
            return ['success' => false, 'error' => 'Please provide at least 10 characters for your review feedback.'];
        }

        $dupStmt = $db->prepare("
            SELECT id FROM reviews 
            WHERE website_id = :wid 
              AND product_id = :pid 
              AND (customer_name = :cname OR (customer_id IS NOT NULL AND customer_id = :cid))
              AND created_at >= datetime('now', '-30 days')
            LIMIT 1
        ");
        $dupStmt->execute([
            ':wid' => $wid,
            ':pid' => $productId,
            ':cname' => $customerName,
            ':cid' => $customerId
        ]);
        if ($dupStmt->fetchColumn()) {
            return ['success' => false, 'error' => 'You have already submitted a review for this product recently.'];
        }

        $verifiedOrderId = self::verifyPurchase(
            $wid,
            $customerId,
            $customerName,
            $customerEmail,
            $customerPhone,
            $productId,
            $variantId
        );

        $isVerified = $verifiedOrderId ? 1 : 0;
        $id = 'rev_' . bin2hex(random_bytes(6));

        $stmt = $db->prepare("
            INSERT INTO reviews (
                id, website_id, product_id, variant_id, customer_id, customer_name, rating, title, comment,
                status, is_verified_buyer, review_source, order_id, created_at, updated_at
            ) VALUES (
                :id, :wid, :pid, :vid, :cid, :cname, :rating, :title, :comment,
                'PENDING', :is_verified, 'CUSTOMER_SUBMITTED', :oid, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':id' => $id,
            ':wid' => $wid,
            ':pid' => $productId,
            ':vid' => $variantId,
            ':cid' => $customerId,
            ':cname' => $customerName,
            ':rating' => $rating,
            ':title' => $title,
            ':comment' => $comment,
            ':is_verified' => $isVerified,
            ':oid' => $verifiedOrderId
        ]);

        return [
            'success' => true,
            'id' => $id,
            'is_verified' => (bool)$isVerified,
            'message' => 'Thank you for your valuable feedback! Your review has been submitted and is awaiting moderation.'
        ];
    }

    /**
     * Moderate a review: approve, reject, hide, or restore with optional notes and reply
     */
    public static function moderate(string $reviewId, string $status, ?string $adminReply = null, ?string $internalNotes = null): bool
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $user = AuthService::user();
        $moderatorName = $user['name'] ?? 'Admin';

        $status = strtoupper($status);
        if (!in_array($status, ['APPROVED', 'PENDING', 'REJECTED', 'HIDDEN'], true)) {
            $status = 'APPROVED';
        }

        $orig = self::getReviewById($reviewId);
        if (!$orig) return false;

        $sql = "
            UPDATE reviews 
            SET status = :status,
                moderated_by = :mod_by,
                moderated_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
        ";
        $params = [
            ':status' => $status,
            ':mod_by' => $moderatorName,
            ':id' => $reviewId,
            ':wid' => $wid
        ];

        if ($internalNotes !== null) {
            $sql .= ", internal_notes = :notes";
            $params[':notes'] = trim($internalNotes);
        }

        if ($adminReply !== null) {
            $cleanedReply = trim($adminReply) ?: null;
            $sql .= ", admin_reply = :reply";
            $params[':reply'] = $cleanedReply;

            if ($cleanedReply) {
                $sql .= ", admin_reply_author = :reply_author, admin_reply_at = CURRENT_TIMESTAMP";
                $params[':reply_author'] = $moderatorName;
            } else {
                $sql .= ", admin_reply_author = NULL, admin_reply_at = NULL";
            }
        }

        $sql .= " WHERE id = :id AND website_id = :wid";

        $stmt = $db->prepare($sql);
        $res = $stmt->execute($params);

        AuditService::log(
            'review.moderate',
            'review',
            $reviewId,
            "Moderated review {$reviewId} to status {$status} by {$moderatorName}."
        );

        return $res;
    }

    /**
     * Update review details
     */
    public static function updateReview(string $reviewId, array $data): bool
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $user = AuthService::user();

        $orig = self::getReviewById($reviewId);
        if (!$orig) return false;

        $rating = isset($data['rating']) ? max(1, min(5, (int)$data['rating'])) : (int)$orig['rating'];
        $title = isset($data['title']) ? trim($data['title']) : $orig['title'];
        $comment = isset($data['comment']) ? trim($data['comment']) : $orig['comment'];
        $status = isset($data['status']) && in_array(strtoupper($data['status']), ['APPROVED', 'PENDING', 'REJECTED', 'HIDDEN'], true)
            ? strtoupper($data['status'])
            : $orig['status'];
        $customerName = isset($data['customer_name']) ? trim($data['customer_name']) : $orig['customer_name'];
        $productId = isset($data['product_id']) ? trim($data['product_id']) : $orig['product_id'];
        $variantId = isset($data['variant_id']) ? trim($data['variant_id']) : $orig['variant_id'];
        $internalNotes = isset($data['internal_notes']) ? trim($data['internal_notes']) : $orig['internal_notes'];

        $adminReply = isset($data['admin_reply']) ? (trim($data['admin_reply']) ?: null) : $orig['admin_reply'];
        $replyAuthor = $adminReply ? ($user['name'] ?? 'Admin') : null;
        $replyAt = $adminReply ? date('Y-m-d H:i:s') : null;

        $verifiedOrderId = self::verifyPurchase(
            $wid,
            $orig['customer_id'] ?? null,
            $customerName,
            $orig['customer_email'] ?? null,
            $orig['customer_phone'] ?? null,
            $productId,
            $variantId
        );
        $isVerified = $verifiedOrderId ? 1 : ($orig['is_verified_buyer'] && $orig['order_id'] ? 1 : 0);
        $orderId = $verifiedOrderId ?: $orig['order_id'];

        $stmt = $db->prepare("
            UPDATE reviews 
            SET rating = :rating,
                title = :title,
                comment = :comment,
                status = :status,
                customer_name = :cname,
                product_id = :pid,
                variant_id = :vid,
                is_verified_buyer = :is_verified,
                order_id = :oid,
                internal_notes = :notes,
                admin_reply = :reply,
                admin_reply_author = :reply_author,
                admin_reply_at = :reply_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND website_id = :wid
        ");

        $res = $stmt->execute([
            ':rating' => $rating,
            ':title' => $title,
            ':comment' => $comment,
            ':status' => $status,
            ':cname' => $customerName,
            ':pid' => $productId,
            ':vid' => $variantId,
            ':is_verified' => $isVerified,
            ':oid' => $orderId,
            ':notes' => $internalNotes,
            ':reply' => $adminReply,
            ':reply_author' => $replyAuthor,
            ':reply_at' => $replyAt,
            ':id' => $reviewId,
            ':wid' => $wid
        ]);

        AuditService::log(
            'review.edit',
            'review',
            $reviewId,
            "Updated review content/rating ({$rating}★) and status {$status}."
        );

        return $res;
    }

    /**
     * Add or update official staff response
     */
    public static function saveStaffReply(string $reviewId, string $reply): bool
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $user = AuthService::user();
        $author = $user['name'] ?? 'Nutronyx Care';

        $stmt = $db->prepare("
            UPDATE reviews 
            SET admin_reply = :reply,
                admin_reply_author = :author,
                admin_reply_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND website_id = :wid
        ");

        $res = $stmt->execute([
            ':reply' => trim($reply),
            ':author' => $author,
            ':id' => $reviewId,
            ':wid' => $wid
        ]);

        AuditService::log('review.staff_reply', 'review', $reviewId, "Staff response authored by {$author}");
        return $res;
    }

    /**
     * Delete official staff response
     */
    public static function deleteStaffReply(string $reviewId): bool
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("
            UPDATE reviews 
            SET admin_reply = NULL,
                admin_reply_author = NULL,
                admin_reply_at = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND website_id = :wid
        ");

        $res = $stmt->execute([':id' => $reviewId, ':wid' => $wid]);
        AuditService::log('review.delete_reply', 'review', $reviewId, "Deleted staff response");
        return $res;
    }

    /**
     * Delete review permanently
     */
    public static function deleteReview(string $reviewId): bool
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("DELETE FROM reviews WHERE id = :id AND website_id = :wid");
        $res = $stmt->execute([':id' => $reviewId, ':wid' => $wid]);

        AuditService::log('review.delete', 'review', $reviewId, "Permanently deleted review {$reviewId}");
        return $res;
    }

    /**
     * Perform bulk moderation actions (APPROVE, REJECT, HIDE, DELETE)
     */
    public static function bulkModerate(array $reviewIds, string $action): int
    {
        if (empty($reviewIds)) return 0;
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();
        $user = AuthService::user();
        $modBy = $user['name'] ?? 'Admin';

        $action = strtoupper($action);
        $count = 0;

        if ($action === 'DELETE') {
            $in = implode(',', array_fill(0, count($reviewIds), '?'));
            $stmt = $db->prepare("DELETE FROM reviews WHERE website_id = ? AND id IN ($in)");
            $params = array_merge([$wid], $reviewIds);
            $stmt->execute($params);
            $count = $stmt->rowCount();
            AuditService::log('review.bulk_delete', 'review', implode(',', $reviewIds), "Bulk deleted {$count} reviews");
        } elseif (in_array($action, ['APPROVE', 'REJECT', 'HIDE'], true)) {
            $statusMap = [
                'APPROVE' => 'APPROVED',
                'REJECT' => 'REJECTED',
                'HIDE' => 'HIDDEN'
            ];
            $newStatus = $statusMap[$action];
            $in = implode(',', array_fill(0, count($reviewIds), '?'));
            $stmt = $db->prepare("
                UPDATE reviews 
                SET status = ?, moderated_by = ?, moderated_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE website_id = ? AND id IN ($in)
            ");
            $params = array_merge([$newStatus, $modBy, $wid], $reviewIds);
            $stmt->execute($params);
            $count = $stmt->rowCount();
            AuditService::log('review.bulk_moderate', 'review', implode(',', $reviewIds), "Bulk set status {$newStatus} for {$count} reviews");
        }

        return $count;
    }

    /**
     * Retrieve approved reviews for product page storefront integration
     */
    public static function getApprovedReviewsForProduct(string $productId, int $limit = 10, int $offset = 0): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $sql = "
            SELECT r.*, 
                   p.title as product_title, 
                   p.image_url as product_image,
                   pv.title as variant_title
            FROM reviews r
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN product_variants pv ON r.variant_id = pv.id
            WHERE r.website_id = :wid 
              AND r.status = 'APPROVED'
        ";
        $params = [':wid' => $wid];

        if ($productId !== 'all' && !empty($productId)) {
            $sql .= " AND r.product_id = :pid";
            $params[':pid'] = $productId;
        }

        $sql .= " ORDER BY r.is_verified_buyer DESC, r.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate aggregate rating metrics for a specific product
     */
    public static function getProductRatingStats(string $productId): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $wid = Context::getWebsiteId();

        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as count,
                AVG(rating) as avg_rating
            FROM reviews
            WHERE website_id = :wid AND product_id = :pid AND status = 'APPROVED'
        ");
        $stmt->execute([':wid' => $wid, ':pid' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $count = (int)($row['count'] ?? 0);
        $avg = $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 1) : 4.8;

        $distStmt = $db->prepare("
            SELECT rating, COUNT(*) as cnt 
            FROM reviews 
            WHERE website_id = :wid AND product_id = :pid AND status = 'APPROVED'
            GROUP BY rating
        ");
        $distStmt->execute([':wid' => $wid, ':pid' => $productId]);
        $distRows = $distStmt->fetchAll(PDO::FETCH_ASSOC);

        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($distRows as $dr) {
            $dist[(int)$dr['rating']] = (int)$dr['cnt'];
        }

        return [
            'count' => $count,
            'average' => $avg,
            'distribution' => $dist
        ];
    }
}
