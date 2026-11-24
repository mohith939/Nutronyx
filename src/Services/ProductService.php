<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;
use PDO;

class ProductService
{
    /**
     * Backwards-compatible getProducts method
     */
    public static function getProducts(?string $categoryId = null, ?string $status = null, ?string $search = null, bool $visibleStorefrontOnly = false): array
    {
        $res = self::getProductsAdvanced([
            'category_id' => $categoryId,
            'status' => $status,
            'search' => $search,
            'visible_storefront_only' => $visibleStorefrontOnly,
            'per_page' => 1000
        ]);
        return $res['products'];
    }

    /**
     * Advanced product listing with multi-field search, filters, sorting, pagination, and eager variants
     */
    public static function getProductsAdvanced(array $filters = []): array
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $search = !empty($filters['search']) ? trim($filters['search']) : null;
        $categoryId = !empty($filters['category_id']) ? $filters['category_id'] : null;
        $status = !empty($filters['status']) && $filters['status'] !== 'all' ? strtoupper($filters['status']) : null;
        $stockFilter = !empty($filters['stock_filter']) && $filters['stock_filter'] !== 'all' ? $filters['stock_filter'] : null;
        $minPrice = isset($filters['min_price']) && is_numeric($filters['min_price']) ? (float)$filters['min_price'] : null;
        $maxPrice = isset($filters['max_price']) && is_numeric($filters['max_price']) ? (float)$filters['max_price'] : null;
        $hasVariants = !empty($filters['has_variants']) && $filters['has_variants'] !== 'all' ? $filters['has_variants'] : null;
        $sortBy = $filters['sort_by'] ?? 'newest';
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int)($filters['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;

        // Base query with computed metrics
        $sql = "
            SELECT p.*, c.name as category_name,
                   (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) as variant_count,
                   (SELECT MIN(pv.price_amount) FROM product_variants pv WHERE pv.product_id = p.id) as min_price,
                   (SELECT MAX(pv.price_amount) FROM product_variants pv WHERE pv.product_id = p.id) as max_price,
                   COALESCE((SELECT SUM(inv.quantity_on_hand) FROM product_variants pv JOIN inventory_stocks inv ON inv.variant_id = pv.id WHERE pv.product_id = p.id), 0) as total_stock,
                   (SELECT pv.sku FROM product_variants pv WHERE pv.product_id = p.id ORDER BY pv.created_at ASC LIMIT 1) as primary_sku,
                   (SELECT pv.id FROM product_variants pv WHERE pv.product_id = p.id ORDER BY pv.created_at ASC LIMIT 1) as primary_variant_id,
                   (SELECT inv.low_stock_threshold FROM product_variants pv JOIN inventory_stocks inv ON inv.variant_id = pv.id WHERE pv.product_id = p.id ORDER BY pv.created_at ASC LIMIT 1) as low_stock_threshold
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.website_id = :wid
        ";
        $params = [':wid' => $websiteId];

        if ($categoryId) {
            $sql .= " AND p.category_id = :cid";
            $params[':cid'] = $categoryId;
        }

        if ($status) {
            $sql .= " AND p.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($filters['visible_storefront_only'])) {
            $sql .= " AND p.is_visible_storefront = 1";
        }

        if ($search) {
            $sql .= " AND (
                p.title LIKE :s 
                OR p.description LIKE :s 
                OR p.slug LIKE :s 
                OR p.barcode LIKE :s
                OR EXISTS (SELECT 1 FROM product_variants pv2 WHERE pv2.product_id = p.id AND (pv2.sku LIKE :s OR pv2.title LIKE :s))
            )";
            $params[':s'] = '%' . $search . '%';
        }

        if ($minPrice !== null) {
            $sql .= " AND (SELECT MIN(pv3.price_amount) FROM product_variants pv3 WHERE pv3.product_id = p.id) >= :min_price";
            $params[':min_price'] = (int)round($minPrice * 100);
        }

        if ($maxPrice !== null) {
            $sql .= " AND (SELECT MIN(pv4.price_amount) FROM product_variants pv4 WHERE pv4.product_id = p.id) <= :max_price";
            $params[':max_price'] = (int)round($maxPrice * 100);
        }

        if ($stockFilter === 'in_stock') {
            $sql .= " AND COALESCE((SELECT SUM(inv.quantity_on_hand) FROM product_variants pv JOIN inventory_stocks inv ON inv.variant_id = pv.id WHERE pv.product_id = p.id), 0) > 0";
        } elseif ($stockFilter === 'low_stock') {
            $sql .= " AND COALESCE((SELECT SUM(inv.quantity_on_hand) FROM product_variants pv JOIN inventory_stocks inv ON inv.variant_id = pv.id WHERE pv.product_id = p.id), 0) > 0 
                      AND COALESCE((SELECT SUM(inv.quantity_on_hand) FROM product_variants pv JOIN inventory_stocks inv ON inv.variant_id = pv.id WHERE pv.product_id = p.id), 0) <= 20";
        } elseif ($stockFilter === 'out_of_stock') {
            $sql .= " AND COALESCE((SELECT SUM(inv.quantity_on_hand) FROM product_variants pv JOIN inventory_stocks inv ON inv.variant_id = pv.id WHERE pv.product_id = p.id), 0) <= 0";
        }

        if ($hasVariants === 'yes') {
            $sql .= " AND (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) > 1";
        } elseif ($hasVariants === 'no') {
            $sql .= " AND (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) <= 1";
        }

        // Sorting
        switch ($sortBy) {
            case 'oldest':
                $sql .= " ORDER BY p.created_at ASC";
                break;
            case 'title_asc':
                $sql .= " ORDER BY p.title ASC";
                break;
            case 'title_desc':
                $sql .= " ORDER BY p.title DESC";
                break;
            case 'price_asc':
                $sql .= " ORDER BY min_price ASC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY min_price DESC";
                break;
            case 'stock_asc':
                $sql .= " ORDER BY total_stock ASC";
                break;
            case 'stock_desc':
                $sql .= " ORDER BY total_stock DESC";
                break;
            case 'newest':
            default:
                $sql .= " ORDER BY p.created_at DESC";
                break;
        }

        // Total count matching filtered query
        $countSql = "SELECT COUNT(*) FROM (" . $sql . ") as subq";
        $cStmt = $db->prepare($countSql);
        $cStmt->execute($params);
        $filteredTotal = (int)$cStmt->fetchColumn();

        // Pagination slice
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll();

        // Eagerly attach variants to each product
        if (!empty($products)) {
            $productIds = array_column($products, 'id');
            $inClause = implode(',', array_fill(0, count($productIds), '?'));
            
            $varSql = "
                SELECT pv.*, 
                       COALESCE(inv.quantity_on_hand, 0) as stock_quantity,
                       COALESCE(inv.quantity_reserved, 0) as quantity_reserved,
                       COALESCE(inv.low_stock_threshold, 10) as low_stock_threshold
                FROM product_variants pv
                LEFT JOIN inventory_stocks inv ON inv.variant_id = pv.id
                WHERE pv.product_id IN ({$inClause}) AND pv.website_id = ?
            ";
            if (!empty($filters['visible_storefront_only'])) {
                $varSql .= " AND pv.is_visible = 1";
            }
            $varSql .= " ORDER BY pv.price_amount ASC";
            $varStmt = $db->prepare($varSql);
            $varParams = array_merge($productIds, [$websiteId]);
            $varStmt->execute($varParams);
            $allVariants = $varStmt->fetchAll();

            $variantMap = [];
            foreach ($allVariants as $var) {
                $pid = $var['product_id'];
                if (!isset($variantMap[$pid])) {
                    $variantMap[$pid] = [];
                }
                $variantMap[$pid][] = $var;
            }

            foreach ($products as &$p) {
                $p['variants'] = $variantMap[$p['id']] ?? [];
            }
            unset($p);
        }

        // Aggregate overall KPIs for top metric cards
        $totalAll = (int)$db->query("SELECT COUNT(*) FROM products WHERE website_id = '{$websiteId}'")->fetchColumn();
        $activeAll = (int)$db->query("SELECT COUNT(*) FROM products WHERE website_id = '{$websiteId}' AND status = 'ACTIVE'")->fetchColumn();
        $draftAll = (int)$db->query("SELECT COUNT(*) FROM products WHERE website_id = '{$websiteId}' AND status = 'DRAFT'")->fetchColumn();
        $outOfStockAll = (int)$db->query("
            SELECT COUNT(*) FROM products p 
            WHERE p.website_id = '{$websiteId}' 
            AND COALESCE((SELECT SUM(inv.quantity_on_hand) FROM product_variants pv JOIN inventory_stocks inv ON inv.variant_id = pv.id WHERE pv.product_id = p.id), 0) <= 0
        ")->fetchColumn();

        return [
            'products' => $products,
            'total' => $filteredTotal,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($filteredTotal / $perPage),
            'counts' => [
                'total' => $totalAll,
                'active' => $activeAll,
                'draft' => $draftAll,
                'out_of_stock' => $outOfStockAll
            ]
        ];
    }

    /**
     * Get single product with variants and primary attributes
     */
    public static function getProduct(string $id, bool $visibleStorefrontOnly = false): ?array
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $sql = "
            SELECT p.*, c.name as category_name,
                   (SELECT pv.sku FROM product_variants pv WHERE pv.product_id = p.id ORDER BY pv.created_at ASC LIMIT 1) as primary_sku,
                   (SELECT pv.price_amount FROM product_variants pv WHERE pv.product_id = p.id ORDER BY pv.created_at ASC LIMIT 1) as primary_price_amount,
                   (SELECT pv.compare_at_amount FROM product_variants pv WHERE pv.product_id = p.id ORDER BY pv.created_at ASC LIMIT 1) as primary_compare_at_amount,
                   (SELECT MIN(pv.price_amount) FROM product_variants pv WHERE pv.product_id = p.id) as min_price,
                   (SELECT MAX(pv.price_amount) FROM product_variants pv WHERE pv.product_id = p.id) as max_price,
                   COALESCE((SELECT SUM(inv.quantity_on_hand) FROM product_variants pv JOIN inventory_stocks inv ON inv.variant_id = pv.id WHERE pv.product_id = p.id), 0) as total_stock,
                   (SELECT inv.quantity_on_hand FROM product_variants pv JOIN inventory_stocks inv ON inv.variant_id = pv.id WHERE pv.product_id = p.id ORDER BY pv.created_at ASC LIMIT 1) as primary_stock,
                   (SELECT inv.low_stock_threshold FROM product_variants pv JOIN inventory_stocks inv ON inv.variant_id = pv.id WHERE pv.product_id = p.id ORDER BY pv.created_at ASC LIMIT 1) as primary_low_stock_threshold
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = :id AND p.website_id = :wid
        ";
        if ($visibleStorefrontOnly) {
            $sql .= " AND p.is_visible_storefront = 1 AND p.status = 'ACTIVE'";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id, ':wid' => $websiteId]);
        $prod = $stmt->fetch();
        if (!$prod) return null;

        // Fetch all variants
        $vSql = "
            SELECT pv.*, 
                   COALESCE(inv.quantity_on_hand, 0) as quantity_on_hand, 
                   COALESCE(inv.quantity_reserved, 0) as quantity_reserved, 
                   COALESCE(inv.low_stock_threshold, 10) as low_stock_threshold
            FROM product_variants pv
            LEFT JOIN inventory_stocks inv ON inv.variant_id = pv.id
            WHERE pv.product_id = :pid AND pv.website_id = :wid
        ";
        if ($visibleStorefrontOnly) {
            $vSql .= " AND pv.is_visible = 1";
        }
        $vSql .= " ORDER BY pv.price_amount ASC";
        $vStmt = $db->prepare($vSql);
        $vStmt->execute([':pid' => $id, ':wid' => $websiteId]);
        $prod['variants'] = $vStmt->fetchAll();

        return $prod;
    }

    /**
     * Get comprehensive product details drawer payload (including relations & audit logs)
     */
    public static function getProductDetails(string $id, bool $visibleStorefrontOnly = false): ?array
    {
        $prod = self::getProduct($id, $visibleStorefrontOnly);
        if (!$prod) return null;

        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        // 1. Fetch relationships
        $prod['relations'] = self::getRelations($id, $visibleStorefrontOnly);

        // 2. Fetch product activity / audit logs
        $stmt = $db->prepare("
            SELECT * FROM admin_audit_logs 
            WHERE (target_resource = 'product' AND target_id = :id)
               OR (target_resource = 'product_variant' AND target_id IN (SELECT id FROM product_variants WHERE product_id = :id2))
            ORDER BY created_at DESC 
            LIMIT 25
        ");
        $stmt->execute([':id' => $id, ':id2' => $id]);
        $prod['activity_logs'] = $stmt->fetchAll();

        return $prod;
    }

    /**
     * Create product with primary variant and optional extra variants
     */
    public static function createProduct(array $data): string
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();
        $id = !empty($data['id']) ? $data['id'] : ('prod_' . bin2hex(random_bytes(6)));

        $title = trim($data['title'] ?? 'Untitled Product');
        $slug = !empty($data['slug']) 
            ? preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($data['slug'])))
            : preg_replace('/[^a-z0-9]+/i', '-', strtolower($title)) . '-' . rand(100, 999);

        $priceRupees = (float)($data['price_rupees'] ?? 499);
        $salePriceRupees = !empty($data['sale_price']) ? (float)$data['sale_price'] : null;
        $costPriceRupees = !empty($data['cost_price']) ? (float)$data['cost_price'] : null;

        $priceAmount = (int)round($priceRupees * 100);
        $salePriceAmount = $salePriceRupees !== null ? (int)round($salePriceRupees * 100) : null;
        $costPriceAmount = $costPriceRupees !== null ? (int)round($costPriceRupees * 100) : null;

        $stmt = $db->prepare("
            INSERT INTO products (
                id, website_id, category_id, title, slug, description, short_description, status, image_url,
                sale_price_amount, cost_price_amount, tax_rate, currency, barcode, brand,
                track_inventory, allow_backorders, stock_status, subcategory, tags, collections,
                options_json, video_url, shipping_class, dimensions_cm,
                is_visible_storefront, is_visible_catalog, is_visible_search, is_featured,
                created_at, updated_at
            ) VALUES (
                :id, :wid, :cid, :title, :slug, :desc, :short_desc, :status, :img,
                :sale_price, :cost_price, :tax_rate, :currency, :barcode, :brand,
                :track_inv, :backorders, :stock_status, :subcat, :tags, :collections,
                :options_json, :video_url, :shipping_class, :dimensions_cm,
                :vis_store, :vis_cat, :vis_search, :featured,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':id' => $id,
            ':wid' => $websiteId,
            ':cid' => !empty($data['category_id']) ? $data['category_id'] : null,
            ':title' => $title,
            ':slug' => $slug,
            ':desc' => $data['description'] ?? '',
            ':short_desc' => $data['short_description'] ?? '',
            ':status' => strtoupper($data['status'] ?? 'ACTIVE'),
            ':img' => !empty($data['image_url']) ? $data['image_url'] : 'assets/images/about-commitment-still-life.jpg',
            ':sale_price' => $salePriceAmount,
            ':cost_price' => $costPriceAmount,
            ':tax_rate' => (float)($data['tax_rate'] ?? 0),
            ':currency' => $data['currency'] ?? 'INR',
            ':barcode' => $data['barcode'] ?? null,
            ':brand' => $data['brand'] ?? 'Nutronyx',
            ':track_inv' => isset($data['track_inventory']) ? (int)$data['track_inventory'] : 1,
            ':backorders' => isset($data['allow_backorders']) ? (int)$data['allow_backorders'] : 0,
            ':stock_status' => $data['stock_status'] ?? 'in_stock',
            ':subcat' => $data['subcategory'] ?? null,
            ':tags' => $data['tags'] ?? null,
            ':collections' => $data['collections'] ?? null,
            ':options_json' => !empty($data['options_json']) ? (is_string($data['options_json']) ? $data['options_json'] : json_encode($data['options_json'])) : null,
            ':video_url' => $data['video_url'] ?? null,
            ':shipping_class' => $data['shipping_class'] ?? 'Standard Ground',
            ':dimensions_cm' => $data['dimensions_cm'] ?? null,
            ':vis_store' => isset($data['is_visible_storefront']) ? (int)$data['is_visible_storefront'] : 1,
            ':vis_cat' => isset($data['is_visible_catalog']) ? (int)$data['is_visible_catalog'] : 1,
            ':vis_search' => isset($data['is_visible_search']) ? (int)$data['is_visible_search'] : 1,
            ':featured' => isset($data['is_featured']) ? (int)$data['is_featured'] : 0,
        ]);

        // Create initial or custom variants
        $createdCustom = false;
        if (!empty($data['variants']) && is_array($data['variants'])) {
            foreach ($data['variants'] as $v) {
                if (empty(trim($v['title'] ?? ''))) continue;
                try {
                    self::createVariant($id, $v);
                    $createdCustom = true;
                } catch (\Throwable $ex) {}
            }
        }
        if (!$createdCustom) {
            // Default primary variant
            $sku = !empty($data['sku']) ? strtoupper(trim($data['sku'])) : ('NX-' . strtoupper(substr(str_replace('-', '', $slug), 0, 8)));
            self::createVariant($id, [
                'sku' => $sku,
                'title' => $data['variant_title'] ?? 'Standard',
                'price_amount' => $priceAmount,
                'compare_at_amount' => $salePriceAmount,
                'cost_price_amount' => $costPriceAmount,
                'weight_grams' => (int)($data['weight_grams'] ?? 350),
                'stock_quantity' => (int)($data['stock_quantity'] ?? $data['initial_stock'] ?? 100),
                'low_stock_threshold' => (int)($data['low_stock_threshold'] ?? 10),
                'status' => 'ACTIVE',
                'is_visible' => 1
            ]);
        }

        AuditService::log('product.create', 'product', $id, "Created product '{$title}'");

        return $id;
    }

    /**
     * Update existing product
     */
    public static function updateProduct(string $id, array $data): bool
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $title = trim($data['title'] ?? '');
        $slug = !empty($data['slug']) 
            ? preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($data['slug'])))
            : preg_replace('/[^a-z0-9]+/i', '-', strtolower($title));

        $priceRupees = isset($data['price_rupees']) ? (float)$data['price_rupees'] : null;
        $salePriceRupees = isset($data['sale_price']) && $data['sale_price'] !== '' ? (float)$data['sale_price'] : null;
        $costPriceRupees = isset($data['cost_price']) && $data['cost_price'] !== '' ? (float)$data['cost_price'] : null;

        $salePriceAmount = $salePriceRupees !== null ? (int)round($salePriceRupees * 100) : null;
        $costPriceAmount = $costPriceRupees !== null ? (int)round($costPriceRupees * 100) : null;

        $stmt = $db->prepare("
            UPDATE products SET
                category_id = :cid,
                title = :title,
                slug = :slug,
                description = :desc,
                short_description = :short_desc,
                status = :status,
                image_url = CASE WHEN :img != '' THEN :img ELSE image_url END,
                sale_price_amount = :sale_price,
                cost_price_amount = :cost_price,
                tax_rate = :tax_rate,
                currency = :currency,
                barcode = :barcode,
                brand = :brand,
                track_inventory = :track_inv,
                allow_backorders = :backorders,
                stock_status = :stock_status,
                subcategory = :subcat,
                tags = :tags,
                collections = :collections,
                options_json = :options_json,
                video_url = :video_url,
                shipping_class = :shipping_class,
                dimensions_cm = :dimensions_cm,
                is_visible_storefront = :vis_store,
                is_visible_catalog = :vis_cat,
                is_visible_search = :vis_search,
                is_featured = :featured,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND website_id = :wid
        ");
        $stmt->execute([
            ':id' => $id,
            ':wid' => $websiteId,
            ':cid' => !empty($data['category_id']) ? $data['category_id'] : null,
            ':title' => $title,
            ':slug' => $slug,
            ':desc' => $data['description'] ?? '',
            ':short_desc' => $data['short_description'] ?? '',
            ':status' => strtoupper($data['status'] ?? 'ACTIVE'),
            ':img' => !empty($data['image_url']) ? $data['image_url'] : '',
            ':sale_price' => $salePriceAmount,
            ':cost_price' => $costPriceAmount,
            ':tax_rate' => (float)($data['tax_rate'] ?? 0),
            ':currency' => $data['currency'] ?? 'INR',
            ':barcode' => $data['barcode'] ?? null,
            ':brand' => $data['brand'] ?? 'Nutronyx',
            ':track_inv' => isset($data['track_inventory']) ? (int)$data['track_inventory'] : 1,
            ':backorders' => isset($data['allow_backorders']) ? (int)$data['allow_backorders'] : 0,
            ':stock_status' => $data['stock_status'] ?? 'in_stock',
            ':subcat' => $data['subcategory'] ?? null,
            ':tags' => $data['tags'] ?? null,
            ':collections' => $data['collections'] ?? null,
            ':options_json' => !empty($data['options_json']) ? (is_string($data['options_json']) ? $data['options_json'] : json_encode($data['options_json'])) : null,
            ':video_url' => $data['video_url'] ?? null,
            ':shipping_class' => $data['shipping_class'] ?? 'Standard Ground',
            ':dimensions_cm' => $data['dimensions_cm'] ?? null,
            ':vis_store' => isset($data['is_visible_storefront']) ? (int)$data['is_visible_storefront'] : 1,
            ':vis_cat' => isset($data['is_visible_catalog']) ? (int)$data['is_visible_catalog'] : 1,
            ':vis_search' => isset($data['is_visible_search']) ? (int)$data['is_visible_search'] : 1,
            ':featured' => isset($data['is_featured']) ? (int)$data['is_featured'] : 0,
        ]);

        // Synchronize product variants if matrix provided
        if (!empty($data['variants']) && is_array($data['variants'])) {
            $existingVariantsStmt = $db->prepare("SELECT id, sku FROM product_variants WHERE product_id = :pid AND website_id = :wid");
            $existingVariantsStmt->execute([':pid' => $id, ':wid' => $websiteId]);
            $existingVariants = $existingVariantsStmt->fetchAll(PDO::FETCH_ASSOC);
            $existingVarMap = [];
            foreach ($existingVariants as $ev) {
                $existingVarMap[$ev['id']] = $ev;
            }

            $submittedVarIds = [];
            foreach ($data['variants'] as $v) {
                $vTitle = trim($v['title'] ?? '');
                if (empty($vTitle)) continue;

                $vSku = strtoupper(trim($v['sku'] ?? ''));
                if (empty($vSku)) {
                    $vSku = 'NX-' . strtoupper(substr(str_replace('-', '', $slug), 0, 8)) . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                }

                $vPrice = isset($v['price_rupees']) ? (int)round(((float)$v['price_rupees']) * 100) : ($priceAmount ?: 49900);
                $vStock = isset($v['stock_quantity']) ? (int)$v['stock_quantity'] : 100;
                $vId = !empty($v['id']) ? trim($v['id']) : null;

                if ($vId && isset($existingVarMap[$vId])) {
                    // Update existing variant
                    $uStmt = $db->prepare("
                        UPDATE product_variants SET
                            title = :title,
                            sku = :sku,
                            price_amount = :price,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id AND website_id = :wid
                    ");
                    $uStmt->execute([
                        ':title' => $vTitle,
                        ':sku' => $vSku,
                        ':price' => $vPrice,
                        ':id' => $vId,
                        ':wid' => $websiteId
                    ]);

                    // Update stock
                    $sStmt = $db->prepare("
                        UPDATE inventory_stocks SET
                            quantity_on_hand = :qty,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE variant_id = :vid AND website_id = :wid
                    ");
                    $sStmt->execute([
                        ':qty' => $vStock,
                        ':vid' => $vId,
                        ':wid' => $websiteId
                    ]);

                    $submittedVarIds[] = $vId;
                } else {
                    // Insert new variant
                    try {
                        $newVid = self::createVariant($id, [
                            'title' => $vTitle,
                            'sku' => $vSku,
                            'price_amount' => $vPrice,
                            'stock_quantity' => $vStock,
                            'is_visible' => 1
                        ]);
                        $submittedVarIds[] = $newVid;
                    } catch (\Throwable $e) {}
                }
            }

            // Remove any variants that were deleted from the matrix (if more than 1 existed originally)
            if (!empty($submittedVarIds)) {
                foreach ($existingVarMap as $evId => $evData) {
                    if (!in_array($evId, $submittedVarIds, true)) {
                        try {
                            $db->prepare("DELETE FROM inventory_stocks WHERE variant_id = :vid AND website_id = :wid")->execute([':vid' => $evId, ':wid' => $websiteId]);
                            $db->prepare("DELETE FROM product_variants WHERE id = :vid AND website_id = :wid")->execute([':vid' => $evId, ':wid' => $websiteId]);
                        } catch (\Throwable $ex) {}
                    }
                }
            }
        } elseif ($priceRupees !== null || !empty($data['sku']) || isset($data['stock_quantity'])) {
            $vStmt = $db->prepare("SELECT id FROM product_variants WHERE product_id = :pid AND website_id = :wid ORDER BY created_at ASC LIMIT 1");
            $vStmt->execute([':pid' => $id, ':wid' => $websiteId]);
            $primaryVar = $vStmt->fetch();

            if ($primaryVar) {
                $varId = $primaryVar['id'];
                $varUpdates = [];
                $varParams = [':vid' => $varId, ':wid' => $websiteId];

                if ($priceRupees !== null) {
                    $varUpdates[] = "price_amount = :price";
                    $varParams[':price'] = (int)round($priceRupees * 100);
                }
                if ($salePriceAmount !== null) {
                    $varUpdates[] = "compare_at_amount = :comp_price";
                    $varParams[':comp_price'] = $salePriceAmount;
                }
                if (!empty($data['sku'])) {
                    $varUpdates[] = "sku = :sku";
                    $varParams[':sku'] = strtoupper(trim($data['sku']));
                }
                if (!empty($varUpdates)) {
                    $db->prepare("UPDATE product_variants SET " . implode(', ', $varUpdates) . " WHERE id = :vid AND website_id = :wid")->execute($varParams);
                }

                if (isset($data['stock_quantity']) || isset($data['low_stock_threshold']) || isset($data['low_stock_alert'])) {
                    $invUpdates = [];
                    $invParams = [':vid' => $varId, ':wid' => $websiteId];
                    if (isset($data['stock_quantity'])) {
                        $invUpdates[] = "quantity_on_hand = :qty";
                        $invParams[':qty'] = (int)$data['stock_quantity'];
                    }
                    if (isset($data['low_stock_threshold']) || isset($data['low_stock_alert'])) {
                        $invUpdates[] = "low_stock_threshold = :threshold";
                        $invParams[':threshold'] = (int)($data['low_stock_threshold'] ?? $data['low_stock_alert']);
                    }
                    if (!empty($invUpdates)) {
                        $invUpdates[] = "updated_at = CURRENT_TIMESTAMP";
                        $db->prepare("UPDATE inventory_stocks SET " . implode(', ', $invUpdates) . " WHERE variant_id = :vid AND website_id = :wid")->execute($invParams);
                    }
                }
            }
        }

        AuditService::log('product.update', 'product', $id, "Updated product '{$title}'");
        return true;
    }

    /**
     * Delete product safely (removes relations, variants, and stock, but does not break historical orders)
     */
    public static function deleteProduct(string $id): bool
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        // 1. Delete relations
        $db->prepare("DELETE FROM product_relations WHERE (product_id = :id OR related_product_id = :id) AND website_id = :wid")->execute([':id' => $id, ':wid' => $websiteId]);

        // 2. Fetch variants to clean inventory
        $vStmt = $db->prepare("SELECT id FROM product_variants WHERE product_id = :pid AND website_id = :wid");
        $vStmt->execute([':pid' => $id, ':wid' => $websiteId]);
        $variantIds = $vStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($variantIds as $vid) {
            $db->prepare("DELETE FROM inventory_stocks WHERE variant_id = :vid AND website_id = :wid")->execute([':vid' => $vid, ':wid' => $websiteId]);
        }

        // 3. Delete variants
        $db->prepare("DELETE FROM product_variants WHERE product_id = :id AND website_id = :wid")->execute([':id' => $id, ':wid' => $websiteId]);

        // 4. Delete product
        $stmt = $db->prepare("DELETE FROM products WHERE id = :id AND website_id = :wid");
        $res = $stmt->execute([':id' => $id, ':wid' => $websiteId]);

        AuditService::log('product.delete', 'product', $id, "Deleted product #{$id}");
        return $res;
    }

    /**
     * Update product status (ACTIVE, DRAFT, ARCHIVED)
     */
    public static function updateStatus(string $productId, string $newStatus): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE products SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND website_id = :wid");
        $res = $stmt->execute([':status' => strtoupper($newStatus), ':id' => $productId, ':wid' => Context::getWebsiteId()]);
        AuditService::log('product.status_change', 'product', $productId, "Status changed to {$newStatus}");
        return $res;
    }

    public static function toggleStorefrontVisibility(string $productId): array
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $curr = $db->prepare("SELECT is_visible_storefront FROM products WHERE id = :id AND website_id = :wid");
        $curr->execute([':id' => $productId, ':wid' => $websiteId]);
        $val = (int)$curr->fetchColumn();
        $newState = ($val === 1) ? 0 : 1;

        $stmt = $db->prepare("UPDATE products SET is_visible_storefront = :vis, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND website_id = :wid");
        $stmt->execute([':vis' => $newState, ':id' => $productId, ':wid' => $websiteId]);

        // When parent is toggled off, sync all sub-variants so they are also turned off
        if ($newState === 0) {
            $db->prepare("UPDATE product_variants SET is_visible = 0 WHERE product_id = :pid AND website_id = :wid")->execute([':pid' => $productId, ':wid' => $websiteId]);
        } else {
            $db->prepare("UPDATE product_variants SET is_visible = 1 WHERE product_id = :pid AND website_id = :wid")->execute([':pid' => $productId, ':wid' => $websiteId]);
        }

        AuditService::log('product.visibility_toggle', 'product', $productId, "Toggled storefront visibility to " . ($newState ? 'ON' : 'OFF'));
        return ['success' => true, 'is_visible' => $newState];
    }

    /**
     * Create a new variant for a product
     */
    public static function createVariant(string $productId, array $data): string
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $sku = strtoupper(trim($data['sku'] ?? ''));
        if (empty($sku)) {
            $sku = 'NX-VAR-' . strtoupper(bin2hex(random_bytes(4)));
        }

        // Check SKU uniqueness
        $chk = $db->prepare("SELECT id FROM product_variants WHERE sku = :sku AND website_id = :wid");
        $chk->execute([':sku' => $sku, ':wid' => $websiteId]);
        if ($chk->fetch()) {
            throw new \InvalidArgumentException("SKU '{$sku}' is already in use by another variant.");
        }

        $variantId = !empty($data['id']) ? $data['id'] : ('var_' . bin2hex(random_bytes(6)));
        $title = trim($data['title'] ?? 'Variant');
        $priceAmount = isset($data['price_amount']) ? (int)$data['price_amount'] : (int)round(((float)($data['price_rupees'] ?? 499)) * 100);
        $compareAtAmount = !empty($data['compare_at_amount']) ? (int)$data['compare_at_amount'] : (!empty($data['sale_price']) ? (int)round(((float)$data['sale_price']) * 100) : null);
        $costPriceAmount = !empty($data['cost_price_amount']) ? (int)$data['cost_price_amount'] : (!empty($data['cost_price']) ? (int)round(((float)$data['cost_price']) * 100) : null);
        $weightGrams = (int)($data['weight_grams'] ?? 250);
        $img = $data['image_url'] ?? null;
        $status = strtoupper($data['status'] ?? 'ACTIVE');
        $isVisible = isset($data['is_visible']) ? (int)$data['is_visible'] : 1;
        $attrsJson = !empty($data['attributes_json']) ? (is_string($data['attributes_json']) ? $data['attributes_json'] : json_encode($data['attributes_json'])) : null;

        $vStmt = $db->prepare("
            INSERT INTO product_variants (
                id, website_id, product_id, sku, title, price_amount, compare_at_amount, cost_price_amount,
                weight_grams, image_url, status, is_visible, attributes_json, created_at
            ) VALUES (
                :id, :wid, :pid, :sku, :title, :price, :comp, :cost,
                :weight, :img, :status, :vis, :attrs, CURRENT_TIMESTAMP
            )
        ");
        $vStmt->execute([
            ':id' => $variantId,
            ':wid' => $websiteId,
            ':pid' => $productId,
            ':sku' => $sku,
            ':title' => $title,
            ':price' => $priceAmount,
            ':comp' => $compareAtAmount,
            ':cost' => $costPriceAmount,
            ':weight' => $weightGrams,
            ':img' => $img,
            ':status' => $status,
            ':vis' => $isVisible,
            ':attrs' => $attrsJson
        ]);

        // Initialize inventory stock
        $stockQty = (int)($data['stock_quantity'] ?? $data['quantity_on_hand'] ?? 100);
        $threshold = (int)($data['low_stock_threshold'] ?? 10);
        $iStmt = $db->prepare("
            INSERT INTO inventory_stocks (id, website_id, variant_id, quantity_on_hand, quantity_reserved, low_stock_threshold, updated_at)
            VALUES (:id, :wid, :vid, :qty, 0, :threshold, CURRENT_TIMESTAMP)
        ");
        $iStmt->execute([
            ':id' => 'inv_' . bin2hex(random_bytes(6)),
            ':wid' => $websiteId,
            ':vid' => $variantId,
            ':qty' => $stockQty,
            ':threshold' => $threshold
        ]);

        AuditService::log('product.variant_create', 'product_variant', $variantId, "Created variant '{$title}' ({$sku}) for product #{$productId}");
        return $variantId;
    }

    /**
     * Update an existing variant
     */
    public static function updateVariant(string $variantId, array $data): bool
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $sku = !empty($data['sku']) ? strtoupper(trim($data['sku'])) : null;
        if ($sku !== null) {
            $chk = $db->prepare("SELECT id FROM product_variants WHERE sku = :sku AND id != :vid AND website_id = :wid");
            $chk->execute([':sku' => $sku, ':vid' => $variantId, ':wid' => $websiteId]);
            if ($chk->fetch()) {
                throw new \InvalidArgumentException("SKU '{$sku}' is already in use by another variant.");
            }
        }

        $priceAmount = isset($data['price_rupees']) ? (int)round(((float)$data['price_rupees']) * 100) : (isset($data['price_amount']) ? (int)$data['price_amount'] : null);
        $compareAtAmount = isset($data['sale_price']) && $data['sale_price'] !== '' ? (int)round(((float)$data['sale_price']) * 100) : (isset($data['compare_at_amount']) ? (int)$data['compare_at_amount'] : null);
        $weightGrams = isset($data['weight_grams']) ? (int)$data['weight_grams'] : null;

        $updates = [];
        $params = [':vid' => $variantId, ':wid' => $websiteId];

        if (!empty($data['title'])) {
            $updates[] = "title = :title";
            $params[':title'] = trim($data['title']);
        }
        if ($sku !== null) {
            $updates[] = "sku = :sku";
            $params[':sku'] = $sku;
        }
        if ($priceAmount !== null) {
            $updates[] = "price_amount = :price";
            $params[':price'] = $priceAmount;
        }
        if ($compareAtAmount !== null) {
            $updates[] = "compare_at_amount = :comp";
            $params[':comp'] = $compareAtAmount;
        }
        if ($weightGrams !== null) {
            $updates[] = "weight_grams = :weight";
            $params[':weight'] = $weightGrams;
        }
        if (isset($data['is_visible'])) {
            $updates[] = "is_visible = :vis";
            $params[':vis'] = (int)$data['is_visible'];
        }
        if (isset($data['status'])) {
            $updates[] = "status = :status";
            $params[':status'] = strtoupper($data['status']);
        }
        if (!empty($data['image_url'])) {
            $updates[] = "image_url = :img";
            $params[':img'] = $data['image_url'];
        }

        if (!empty($updates)) {
            $db->prepare("UPDATE product_variants SET " . implode(', ', $updates) . " WHERE id = :vid AND website_id = :wid")->execute($params);
        }

        // Update inventory stock if supplied
        if (isset($data['stock_quantity']) || isset($data['low_stock_threshold'])) {
            $invUpdates = [];
            $invParams = [':vid' => $variantId, ':wid' => $websiteId];
            if (isset($data['stock_quantity'])) {
                $invUpdates[] = "quantity_on_hand = :qty";
                $invParams[':qty'] = (int)$data['stock_quantity'];
            }
            if (isset($data['low_stock_threshold'])) {
                $invUpdates[] = "low_stock_threshold = :threshold";
                $invParams[':threshold'] = (int)$data['low_stock_threshold'];
            }
            if (!empty($invUpdates)) {
                $invUpdates[] = "updated_at = CURRENT_TIMESTAMP";
                $db->prepare("UPDATE inventory_stocks SET " . implode(', ', $invUpdates) . " WHERE variant_id = :vid AND website_id = :wid")->execute($invParams);
            }
        }

        AuditService::log('product.variant_update', 'product_variant', $variantId, "Updated variant #{$variantId}");
        return true;
    }

    /**
     * Delete variant safely
     */
    public static function deleteVariant(string $variantId): bool
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        // Check if there are other variants for this product
        $vStmt = $db->prepare("SELECT product_id FROM product_variants WHERE id = :vid AND website_id = :wid");
        $vStmt->execute([':vid' => $variantId, ':wid' => $websiteId]);
        $var = $vStmt->fetch();
        if (!$var) return false;

        $countStmt = $db->prepare("SELECT COUNT(*) FROM product_variants WHERE product_id = :pid AND website_id = :wid");
        $countStmt->execute([':pid' => $var['product_id'], ':wid' => $websiteId]);
        $remaining = (int)$countStmt->fetchColumn();

        if ($remaining <= 1) {
            throw new \RuntimeException("Cannot delete the only remaining variant of a product. Products must maintain at least one variant.");
        }

        // Remove inventory stock
        $db->prepare("DELETE FROM inventory_stocks WHERE variant_id = :vid AND website_id = :wid")->execute([':vid' => $variantId, ':wid' => $websiteId]);
        
        // Remove variant
        $res = $db->prepare("DELETE FROM product_variants WHERE id = :vid AND website_id = :wid")->execute([':vid' => $variantId, ':wid' => $websiteId]);

        AuditService::log('product.variant_delete', 'product_variant', $variantId, "Deleted variant #{$variantId}");
        return $res;
    }

    /**
     * Toggle variant visibility switch
     */
    public static function toggleVariantVisibility(string $variantId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE product_variants SET is_visible = CASE WHEN is_visible = 1 THEN 0 ELSE 1 END WHERE id = :id AND website_id = :wid");
        $res = $stmt->execute([':id' => $variantId, ':wid' => Context::getWebsiteId()]);
        AuditService::log('product.variant_visibility_toggle', 'product_variant', $variantId, "Toggled variant visibility");
        return $res;
    }

    /**
     * Duplicate an existing product with its variants and inventory
     */
    public static function duplicateProduct(string $productId): string
    {
        $orig = self::getProduct($productId);
        if (!$orig) {
            throw new \InvalidArgumentException("Product not found: {$productId}");
        }

        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $newProductId = 'prod_' . bin2hex(random_bytes(6));
        $newTitle = $orig['title'] . ' (Copy)';
        $newSlug = $orig['slug'] . '-copy-' . rand(100, 999);

        // Insert new product
        $pStmt = $db->prepare("
            INSERT INTO products (
                id, website_id, category_id, title, slug, description, short_description, status, image_url,
                sale_price_amount, cost_price_amount, tax_rate, currency, barcode, brand,
                track_inventory, allow_backorders, stock_status, subcategory, tags, collections,
                options_json, video_url, shipping_class, dimensions_cm,
                is_visible_storefront, is_visible_catalog, is_visible_search, is_featured,
                created_at, updated_at
            ) VALUES (
                :id, :wid, :cid, :title, :slug, :desc, :short_desc, 'DRAFT', :img,
                :sale_price, :cost_price, :tax_rate, :currency, NULL, :brand,
                :track_inv, :backorders, :stock_status, :subcat, :tags, :collections,
                :options_json, :video_url, :shipping_class, :dimensions_cm,
                0, 0, 0, 0,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $pStmt->execute([
            ':id' => $newProductId,
            ':wid' => $websiteId,
            ':cid' => $orig['category_id'],
            ':title' => $newTitle,
            ':slug' => $newSlug,
            ':desc' => $orig['description'],
            ':short_desc' => $orig['short_description'],
            ':img' => $orig['image_url'],
            ':sale_price' => $orig['sale_price_amount'],
            ':cost_price' => $orig['cost_price_amount'],
            ':tax_rate' => $orig['tax_rate'],
            ':currency' => $orig['currency'],
            ':brand' => $orig['brand'],
            ':track_inv' => $orig['track_inventory'],
            ':backorders' => $orig['allow_backorders'],
            ':stock_status' => $orig['stock_status'],
            ':subcat' => $orig['subcategory'],
            ':tags' => $orig['tags'],
            ':collections' => $orig['collections'],
            ':options_json' => $orig['options_json'],
            ':video_url' => $orig['video_url'],
            ':shipping_class' => $orig['shipping_class'],
            ':dimensions_cm' => $orig['dimensions_cm']
        ]);

        // Duplicate variants with unique SKUs
        foreach ($orig['variants'] as $idx => $v) {
            $newSku = $v['sku'] . '-COPY-' . strtoupper(bin2hex(random_bytes(2)));
            self::createVariant($newProductId, [
                'sku' => $newSku,
                'title' => $v['title'],
                'price_amount' => $v['price_amount'],
                'compare_at_amount' => $v['compare_at_amount'],
                'cost_price_amount' => $v['cost_price_amount'] ?? 0,
                'weight_grams' => $v['weight_grams'],
                'image_url' => $v['image_url'] ?? null,
                'status' => 'ACTIVE',
                'is_visible' => 1,
                'attributes_json' => $v['attributes_json'] ?? null,
                'stock_quantity' => $v['quantity_on_hand'] ?? 100,
                'low_stock_threshold' => $v['low_stock_threshold'] ?? 10
            ]);
        }

        AuditService::log('product.duplicate', 'product', $newProductId, "Duplicated product #{$productId} into #{$newProductId}");
        return $newProductId;
    }

    /**
     * Get related products categorized into related, upsell, and cross_sell
     */
    public static function getRelations(string $productId, bool $onlyVisible = false): array
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $visFilter = $onlyVisible ? " AND p.is_visible_storefront = 1 AND p.status = 'ACTIVE'" : "";
        $stmt = $db->prepare("
            SELECT pr.id as relation_id, pr.relation_type, pr.position,
                   p.id as product_id, p.title, p.slug, p.image_url, p.status,
                   c.name as category_name,
                   (SELECT pv.sku FROM product_variants pv WHERE pv.product_id = p.id ORDER BY pv.created_at ASC LIMIT 1) as primary_sku,
                   (SELECT MIN(pv.price_amount) FROM product_variants pv WHERE pv.product_id = p.id) as min_price
            FROM product_relations pr
            JOIN products p ON p.id = pr.related_product_id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE pr.product_id = :pid AND pr.website_id = :wid {$visFilter}
            ORDER BY pr.position ASC, pr.created_at ASC
        ");
        $stmt->execute([':pid' => $productId, ':wid' => $websiteId]);
        $rows = $stmt->fetchAll();

        $grouped = [
            'related' => [],
            'upsell' => [],
            'cross_sell' => []
        ];

        foreach ($rows as $r) {
            $type = $r['relation_type'] ?? 'related';
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $r;
        }

        return $grouped;
    }

    /**
     * Save relationship list for a product and relation type
     */
    public static function saveRelations(string $productId, string $relationType, array $relatedProductIds): bool
    {
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        // Prevent self-reference and remove duplicates
        $cleanIds = array_values(array_unique(array_filter($relatedProductIds, fn($id) => !empty($id) && $id !== $productId)));

        // Remove existing relations of this type
        $del = $db->prepare("DELETE FROM product_relations WHERE product_id = :pid AND relation_type = :type AND website_id = :wid");
        $del->execute([':pid' => $productId, ':type' => $relationType, ':wid' => $websiteId]);

        // Insert new relations
        $ins = $db->prepare("
            INSERT OR IGNORE INTO product_relations (id, website_id, product_id, related_product_id, relation_type, position, created_at)
            VALUES (:id, :wid, :pid, :rid, :type, :pos, CURRENT_TIMESTAMP)
        ");

        foreach ($cleanIds as $idx => $rid) {
            $ins->execute([
                ':id' => 'rel_' . bin2hex(random_bytes(6)),
                ':wid' => $websiteId,
                ':pid' => $productId,
                ':rid' => $rid,
                ':type' => $relationType,
                ':pos' => $idx + 1
            ]);
        }

        AuditService::log('product.relations_update', 'product', $productId, "Updated {$relationType} relationships (" . count($cleanIds) . " items)");
        return true;
    }

    /**
     * Bulk update status (ACTIVE, DRAFT, ARCHIVED)
     */
    public static function bulkUpdateStatus(array $productIds, string $newStatus): int
    {
        if (empty($productIds)) return 0;
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $in = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $db->prepare("UPDATE products SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id IN ({$in}) AND website_id = ?");
        $params = array_merge([strtoupper($newStatus)], $productIds, [$websiteId]);
        $stmt->execute($params);
        $count = $stmt->rowCount();

        AuditService::log('product.bulk_status', 'product', 'batch', "Bulk updated status to {$newStatus} for {$count} products");
        return $count;
    }

    /**
     * Bulk update category
     */
    public static function bulkChangeCategory(array $productIds, string $categoryId): int
    {
        if (empty($productIds)) return 0;
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $in = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $db->prepare("UPDATE products SET category_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id IN ({$in}) AND website_id = ?");
        $params = array_merge([$categoryId], $productIds, [$websiteId]);
        $stmt->execute($params);
        $count = $stmt->rowCount();

        AuditService::log('product.bulk_category', 'product', 'batch', "Bulk assigned category {$categoryId} to {$count} products");
        return $count;
    }

    /**
     * Bulk update stock quantity
     */
    public static function bulkUpdateStock(array $productIds, int $quantity, string $mode = 'set'): int
    {
        if (empty($productIds)) return 0;
        $db = Database::getConnection();
        $websiteId = Context::getWebsiteId();

        $in = implode(',', array_fill(0, count($productIds), '?'));
        $vStmt = $db->prepare("SELECT id FROM product_variants WHERE product_id IN ({$in}) AND website_id = ?");
        $vParams = array_merge($productIds, [$websiteId]);
        $vStmt->execute($vParams);
        $variantIds = $vStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($variantIds)) return 0;

        $vIn = implode(',', array_fill(0, count($variantIds), '?'));
        if ($mode === 'add') {
            $sql = "UPDATE inventory_stocks SET quantity_on_hand = quantity_on_hand + ?, updated_at = CURRENT_TIMESTAMP WHERE variant_id IN ({$vIn}) AND website_id = ?";
        } elseif ($mode === 'subtract') {
            $sql = "UPDATE inventory_stocks SET quantity_on_hand = MAX(0, quantity_on_hand - ?), updated_at = CURRENT_TIMESTAMP WHERE variant_id IN ({$vIn}) AND website_id = ?";
        } else {
            $sql = "UPDATE inventory_stocks SET quantity_on_hand = ?, updated_at = CURRENT_TIMESTAMP WHERE variant_id IN ({$vIn}) AND website_id = ?";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$quantity], $variantIds, [$websiteId]));
        $count = $stmt->rowCount();

        AuditService::log('product.bulk_stock', 'inventory', 'batch', "Bulk updated stock ({$mode} {$quantity}) across {$count} variant inventories");
        return $count;
    }

    /**
     * Bulk delete products safely
     */
    public static function bulkDelete(array $productIds): int
    {
        $deleted = 0;
        foreach ($productIds as $pid) {
            if (self::deleteProduct($pid)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Catalog categories
     */
    public static function getCategories(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM categories WHERE website_id = :wid ORDER BY name ASC");
        $stmt->execute([':wid' => Context::getWebsiteId()]);
        return $stmt->fetchAll();
    }
}
