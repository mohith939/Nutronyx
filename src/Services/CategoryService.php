<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;

class CategoryService
{
    /**
     * Get paginated and filtered categories for a website
     */
    public static function getCategories(
        string $websiteId,
        ?string $status = null,
        ?int $isFeatured = null,
        ?string $search = null,
        string $sort = 'newest',
        int $page = 1,
        int $perPage = 10
    ): array {
        $db = Database::getConnection();

        $sql = "
            SELECT c.*, 
                   (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.website_id = c.website_id) as product_count
            FROM categories c
            WHERE c.website_id = :wid
        ";
        $params = [':wid' => $websiteId];

        if ($status !== null && $status !== '' && $status !== 'all') {
            $sql .= " AND c.status = :status";
            $params[':status'] = strtoupper($status);
        }

        if ($isFeatured !== null && $isFeatured !== '') {
            $sql .= " AND c.is_featured = :is_featured";
            $params[':is_featured'] = (int)$isFeatured;
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (c.name LIKE :search OR c.description LIKE :search OR c.slug LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        // Count total matching
        $countSql = "SELECT COUNT(*) FROM (" . $sql . ") as t";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Sort
        switch ($sort) {
            case 'oldest':
                $sql .= " ORDER BY c.created_at ASC";
                break;
            case 'name_asc':
                $sql .= " ORDER BY c.name ASC";
                break;
            case 'name_desc':
                $sql .= " ORDER BY c.name DESC";
                break;
            case 'products_desc':
                $sql .= " ORDER BY product_count DESC, c.name ASC";
                break;
            case 'newest':
            default:
                $sql .= " ORDER BY c.created_at DESC";
                break;
        }

        // Pagination
        $offset = max(0, ($page - 1) * $perPage);
        $sql .= " LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $categories = $stmt->fetchAll();

        return [
            'categories' => $categories,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage))
        ];
    }

    /**
     * Get a single category by ID
     */
    public static function getCategory(string $id, string $websiteId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.website_id = c.website_id) as product_count
            FROM categories c
            WHERE c.id = :id AND c.website_id = :wid
        ");
        $stmt->execute([':id' => $id, ':wid' => $websiteId]);
        $cat = $stmt->fetch();
        return $cat ?: null;
    }

    /**
     * Create a new category
     */
    public static function createCategory(array $data): string
    {
        $db = Database::getConnection();
        $id = $data['id'] ?? ('cat_' . bin2hex(random_bytes(6)));
        $websiteId = $data['website_id'] ?? Context::getWebsiteId();
        $name = trim($data['name'] ?? '');
        $slug = trim($data['slug'] ?? '');
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        }
        $description = trim($data['description'] ?? '');
        $imageUrl = trim($data['image_url'] ?? 'assets/images/about-commitment-still-life.jpg');
        $status = strtoupper(trim($data['status'] ?? 'ACTIVE'));
        if (!in_array($status, ['ACTIVE', 'DRAFT', 'ARCHIVED'])) {
            $status = 'ACTIVE';
        }
        $isFeatured = !empty($data['is_featured']) ? 1 : 0;

        $subcats = $data['subcategories'] ?? [];
        if (is_string($subcats)) {
            $parts = explode(',', $subcats);
            $subcats = array_values(array_filter(array_map('trim', $parts)));
        }
        $subcatsJson = !empty($subcats) ? json_encode($subcats) : null;

        $stmt = $db->prepare("
            INSERT INTO categories (id, website_id, name, slug, description, image_url, status, is_featured, subcategories_json)
            VALUES (:id, :wid, :name, :slug, :desc, :img, :status, :feat, :sub)
        ");
        $stmt->execute([
            ':id' => $id,
            ':wid' => $websiteId,
            ':name' => $name,
            ':slug' => $slug,
            ':desc' => $description,
            ':img' => $imageUrl,
            ':status' => $status,
            ':feat' => $isFeatured,
            ':sub' => $subcatsJson
        ]);

        AuditService::log('category.create', 'category', $id, "Created category: {$name}");

        return $id;
    }

    /**
     * Update an existing category
     */
    public static function updateCategory(string $id, array $data, string $websiteId): bool
    {
        $db = Database::getConnection();
        $existing = self::getCategory($id, $websiteId);
        if (!$existing) {
            return false;
        }

        $name = trim($data['name'] ?? $existing['name']);
        $slug = trim($data['slug'] ?? $existing['slug']);
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        }
        $description = trim($data['description'] ?? ($existing['description'] ?? ''));
        $imageUrl = trim($data['image_url'] ?? ($existing['image_url'] ?? ''));
        $status = strtoupper(trim($data['status'] ?? ($existing['status'] ?? 'ACTIVE')));
        if (!in_array($status, ['ACTIVE', 'DRAFT', 'ARCHIVED'])) {
            $status = 'ACTIVE';
        }
        $isFeatured = isset($data['is_featured']) ? (!empty($data['is_featured']) ? 1 : 0) : (int)($existing['is_featured'] ?? 0);

        $subcatsJson = $existing['subcategories_json'];
        if (isset($data['subcategories'])) {
            $subcats = $data['subcategories'];
            if (is_string($subcats)) {
                $parts = explode(',', $subcats);
                $subcats = array_values(array_filter(array_map('trim', $parts)));
            }
            $subcatsJson = !empty($subcats) ? json_encode($subcats) : null;
        }

        $stmt = $db->prepare("
            UPDATE categories 
            SET name = :name, slug = :slug, description = :desc, image_url = :img,
                status = :status, is_featured = :feat, subcategories_json = :sub
            WHERE id = :id AND website_id = :wid
        ");
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':desc' => $description,
            ':img' => $imageUrl,
            ':status' => $status,
            ':feat' => $isFeatured,
            ':sub' => $subcatsJson,
            ':id' => $id,
            ':wid' => $websiteId
        ]);

        AuditService::log('category.update', 'category', $id, "Updated category: {$name}");

        return true;
    }

    /**
     * Toggle featured status
     */
    public static function toggleFeatured(string $id, string $websiteId): int
    {
        $db = Database::getConnection();
        $cat = self::getCategory($id, $websiteId);
        if (!$cat) {
            return 0;
        }

        $newVal = ((int)($cat['is_featured'] ?? 0) === 1) ? 0 : 1;
        $stmt = $db->prepare("UPDATE categories SET is_featured = :feat WHERE id = :id AND website_id = :wid");
        $stmt->execute([':feat' => $newVal, ':id' => $id, ':wid' => $websiteId]);

        AuditService::log('category.toggle_featured', 'category', $id, "Toggled featured to {$newVal}");

        return $newVal;
    }

    /**
     * Toggle active/draft status
     */
    public static function toggleStatus(string $id, string $websiteId): string
    {
        $db = Database::getConnection();
        $cat = self::getCategory($id, $websiteId);
        if (!$cat) {
            return 'ACTIVE';
        }

        $newStatus = (strtoupper($cat['status'] ?? 'ACTIVE') === 'ACTIVE') ? 'DRAFT' : 'ACTIVE';
        $stmt = $db->prepare("UPDATE categories SET status = :st WHERE id = :id AND website_id = :wid");
        $stmt->execute([':st' => $newStatus, ':id' => $id, ':wid' => $websiteId]);

        AuditService::log('category.toggle_status', 'category', $id, "Toggled status to {$newStatus}");

        return $newStatus;
    }

    /**
     * Delete a category
     */
    public static function deleteCategory(string $id, string $websiteId): bool
    {
        $db = Database::getConnection();
        $cat = self::getCategory($id, $websiteId);
        if (!$cat) {
            return false;
        }

        // Unlink products
        $db->prepare("UPDATE products SET category_id = NULL WHERE category_id = :id AND website_id = :wid")
           ->execute([':id' => $id, ':wid' => $websiteId]);

        // Delete category
        $stmt = $db->prepare("DELETE FROM categories WHERE id = :id AND website_id = :wid");
        $stmt->execute([':id' => $id, ':wid' => $websiteId]);

        AuditService::log('category.delete', 'category', $id, "Deleted category {$cat['name']}");

        return true;
    }

    /**
     * Bulk update status
     */
    public static function bulkUpdateStatus(array $ids, string $status, string $websiteId): int
    {
        if (empty($ids)) return 0;
        $db = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("UPDATE categories SET status = ? WHERE website_id = ? AND id IN ($placeholders)");
        $params = array_merge([strtoupper($status), $websiteId], $ids);
        $stmt->execute($params);
        $count = $stmt->rowCount();

        AuditService::log('category.bulk_status', 'category', 'bulk', "Bulk updated {$count} categories to {$status}");
        return $count;
    }

    /**
     * Bulk update featured
     */
    public static function bulkUpdateFeatured(array $ids, int $isFeatured, string $websiteId): int
    {
        if (empty($ids)) return 0;
        $db = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("UPDATE categories SET is_featured = ? WHERE website_id = ? AND id IN ($placeholders)");
        $params = array_merge([$isFeatured, $websiteId], $ids);
        $stmt->execute($params);
        $count = $stmt->rowCount();

        AuditService::log('category.bulk_featured', 'category', 'bulk', "Bulk set featured={$isFeatured} for {$count} categories");
        return $count;
    }

    /**
     * Bulk delete
     */
    public static function bulkDelete(array $ids, string $websiteId): int
    {
        if (empty($ids)) return 0;
        $db = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Unlink products
        $unlinkStmt = $db->prepare("UPDATE products SET category_id = NULL WHERE website_id = ? AND category_id IN ($placeholders)");
        $unlinkStmt->execute(array_merge([$websiteId], $ids));

        // Delete
        $stmt = $db->prepare("DELETE FROM categories WHERE website_id = ? AND id IN ($placeholders)");
        $stmt->execute(array_merge([$websiteId], $ids));
        $count = $stmt->rowCount();

        AuditService::log('category.bulk_delete', 'category', 'bulk', "Bulk deleted {$count} categories");
        return $count;
    }
}
