<?php

declare(strict_types=1);

namespace App\Modules\Pages;

use Core\Model;

/**
 * Pages v0.0.2 helpers: template column, custom fields (page_meta).
 */
class PageHelper
{
    public const TEMPLATES = ['default', 'full-width', 'sidebar', 'landing'];

    /** Idempotent schema upgrades for existing installs. */
    public static function ensureSchema(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            $db = (new Model('pages'))->db;
            $db->query("ALTER TABLE pages ADD COLUMN IF NOT EXISTS template VARCHAR(30) NOT NULL DEFAULT 'default'");
            $db->execute();
            $db->query("CREATE TABLE IF NOT EXISTS page_meta (
                id         UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
                page_id    UUID         NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
                meta_key   VARCHAR(100) NOT NULL,
                meta_value TEXT,
                created_at TIMESTAMP    DEFAULT NOW(),
                updated_at TIMESTAMP    DEFAULT NOW(),
                UNIQUE (page_id, meta_key)
            )");
            $db->execute();
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }

    /** One custom field value (or default). */
    public static function getMeta(string $pageId, string $key, ?string $default = null): ?string
    {
        try {
            $row = (new Model('page_meta'))->withoutTimestamps()
                ->select('meta_value')
                ->where('page_id', $pageId)
                ->where('meta_key', $key)
                ->get(1);
            return $row ? (string) $row['meta_value'] : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /** All custom fields for a page as key => value. */
    public static function getAllMeta(string $pageId): array
    {
        try {
            $rows = (new Model('page_meta'))->withoutTimestamps()
                ->select('meta_key, meta_value')
                ->where('page_id', $pageId)
                ->orderBy('meta_key', 'ASC')
                ->get() ?: [];
            return array_column($rows, 'meta_value', 'meta_key');
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Replace a page's custom fields with the given key => value map.
     * Keys are normalized to [a-z0-9_-]; empty keys are dropped.
     */
    public static function syncMeta(string $pageId, array $meta): void
    {
        self::ensureSchema();

        try {
            (new Model('page_meta'))->withoutTimestamps()->where('page_id', $pageId)->delete();

            foreach ($meta as $key => $value) {
                $key = substr(preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim((string) $key))), 0, 100);
                if ($key === '') continue;
                (new Model('page_meta'))->withoutTimestamps()->save([
                    'page_id'    => $pageId,
                    'meta_key'   => $key,
                    'meta_value' => (string) $value,
                ]);
            }
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }

    /** Validated template slug ('default' fallback). */
    public static function normalizeTemplate(?string $template): string
    {
        return in_array($template, self::TEMPLATES, true) ? $template : 'default';
    }
}
