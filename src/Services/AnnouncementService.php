<?php
declare(strict_types=1);

namespace Nutronyx\Services;

use Nutronyx\Database;
use Nutronyx\Context;

class AnnouncementService
{
    private static bool $tableEnsured = false;

    public static function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        $db = Database::getConnection();

        $db->exec("
            CREATE TABLE IF NOT EXISTS site_announcements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                website_id TEXT NOT NULL UNIQUE,
                is_enabled INTEGER NOT NULL DEFAULT 1,
                sentence_1 TEXT NOT NULL,
                icon_1 TEXT NOT NULL DEFAULT 'truck',
                sentence_2 TEXT NOT NULL,
                icon_2 TEXT NOT NULL DEFAULT 'shield',
                sentence_3 TEXT NOT NULL,
                icon_3 TEXT NOT NULL DEFAULT 'leaf',
                bg_color TEXT NOT NULL DEFAULT '#0D2F1F',
                text_color TEXT NOT NULL DEFAULT '#FAF6ED',
                speed_seconds INTEGER NOT NULL DEFAULT 28,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed default record if empty
        $stmt = $db->query("SELECT COUNT(*) FROM site_announcements");
        if ((int)$stmt->fetchColumn() === 0) {
            $insert = $db->prepare("
                INSERT INTO site_announcements (
                    website_id, is_enabled,
                    sentence_1, icon_1,
                    sentence_2, icon_2,
                    sentence_3, icon_3,
                    bg_color, text_color, speed_seconds
                ) VALUES (
                    'store_nutronyx_in', 1,
                    'GET 10% OFF YOUR FIRST ORDER | USE CODE: FIRST10', 'tag',
                    'No chemicals, no preservatives — freshness guaranteed', 'shield',
                    'Sourced directly from farmers — 100% whole-food ingredients', 'leaf',
                    '#0D2F1F', '#FAF6ED', 28
                )
            ");
            $insert->execute();
        } else {
            // Auto-migrate legacy sentence_1 text if it contains "75" or "Delivery from"
            $db->exec("
                UPDATE site_announcements 
                SET sentence_1 = 'GET 10% OFF YOUR FIRST ORDER | USE CODE: FIRST10', 
                    icon_1 = 'tag' 
                WHERE sentence_1 LIKE '%75%' OR sentence_1 LIKE '%Delivery from%'
            ");
        }

        self::$tableEnsured = true;
    }

    public static function getAnnouncement(?string $websiteId = null): array
    {
        self::ensureTable();
        $db = Database::getConnection();
        $siteId = $websiteId ?? Context::getWebsiteId();

        $stmt = $db->prepare("SELECT * FROM site_announcements WHERE website_id = ? LIMIT 1");
        $stmt->execute([$siteId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            // Fallback to first available or defaults
            $stmtFallback = $db->query("SELECT * FROM site_announcements LIMIT 1");
            $row = $stmtFallback->fetch(\PDO::FETCH_ASSOC);
        }

        if (!$row) {
            return [
                'website_id' => $siteId,
                'is_enabled' => 1,
                'sentence_1' => 'GET 10% OFF YOUR FIRST ORDER | USE CODE: FIRST10',
                'icon_1' => 'tag',
                'sentence_2' => 'No chemicals, no preservatives — freshness guaranteed',
                'icon_2' => 'shield',
                'sentence_3' => 'Sourced directly from farmers — 100% whole-food ingredients',
                'icon_3' => 'leaf',
                'bg_color' => '#0D2F1F',
                'text_color' => '#FAF6ED',
                'speed_seconds' => 28
            ];
        }

        $row['is_enabled'] = (int)($row['is_enabled'] ?? 1);
        $row['speed_seconds'] = (int)($row['speed_seconds'] ?? 28);
        return $row;
    }

    public static function updateAnnouncement(string $websiteId, array $data): bool
    {
        self::ensureTable();
        $db = Database::getConnection();

        $isEnabled = !empty($data['is_enabled']) ? 1 : 0;
        $sentence1 = trim((string)($data['sentence_1'] ?? ''));
        $icon1 = trim((string)($data['icon_1'] ?? 'truck'));
        $sentence2 = trim((string)($data['sentence_2'] ?? ''));
        $icon2 = trim((string)($data['icon_2'] ?? 'shield'));
        $sentence3 = trim((string)($data['sentence_3'] ?? ''));
        $icon3 = trim((string)($data['icon_3'] ?? 'leaf'));
        $bgColor = trim((string)($data['bg_color'] ?? '#0D2F1F'));
        $textColor = trim((string)($data['text_color'] ?? '#FAF6ED'));
        $speed = max(10, min(80, (int)($data['speed_seconds'] ?? 28)));

        // Check if record exists
        $stmtCheck = $db->prepare("SELECT id FROM site_announcements WHERE website_id = ? LIMIT 1");
        $stmtCheck->execute([$websiteId]);
        $exists = $stmtCheck->fetchColumn();

        if ($exists) {
            $stmt = $db->prepare("
                UPDATE site_announcements SET
                    is_enabled = ?,
                    sentence_1 = ?, icon_1 = ?,
                    sentence_2 = ?, icon_2 = ?,
                    sentence_3 = ?, icon_3 = ?,
                    bg_color = ?, text_color = ?,
                    speed_seconds = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE website_id = ?
            ");
            return $stmt->execute([
                $isEnabled,
                $sentence1, $icon1,
                $sentence2, $icon2,
                $sentence3, $icon3,
                $bgColor, $textColor,
                $speed,
                $websiteId
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO site_announcements (
                    website_id, is_enabled,
                    sentence_1, icon_1,
                    sentence_2, icon_2,
                    sentence_3, icon_3,
                    bg_color, text_color,
                    speed_seconds
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $websiteId, $isEnabled,
                $sentence1, $icon1,
                $sentence2, $icon2,
                $sentence3, $icon3,
                $bgColor, $textColor,
                $speed
            ]);
        }
    }

    public static function getIconSvg(string $iconName): string
    {
        return match ($iconName) {
            'truck' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>',
            'shield' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
            'leaf' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"></path><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"></path></svg>',
            'star' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>',
            'sparkles' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"></path></svg>',
            'tag' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>',
            'heart' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>',
            default => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle></svg>'
        };
    }
}
