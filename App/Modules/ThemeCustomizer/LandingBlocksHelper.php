<?php

declare(strict_types=1);

namespace App\Modules\ThemeCustomizer;

use Core\Model;

/**
 * Per-theme landing page content blocks (v0.0.4).
 *
 * Storage mirrors the Forms module's `fields TEXT DEFAULT '[]'` pattern - one
 * row per theme, holding an ordered JSON array of typed block objects. The
 * table is added via ensureSchema() (idempotent ALTER/CREATE) rather than a
 * one-time Module::install() edit, since ThemeCustomizer is already installed
 * on existing sites and install() never re-runs.
 */
class LandingBlocksHelper
{
    public const TYPES = ['hero', 'feature-grid', 'testimonials', 'gallery', 'cta-banner', 'rich-text', 'stats'];

    private const MAX_ITEMS  = 24;
    private const MAX_BLOCKS = 40;

    /** Pending, unsaved blocks for the live-preview iframe (see
     *  ThemeCustomizerController::preview()) - picked up automatically by
     *  getBlocks() so callers (Welcome.php, index()) don't need to know about
     *  preview mode. Only ever set by preview() itself, in the same request
     *  that renders the preview - Welcome.php's real, public render never
     *  touches this, so live visitors always see the saved (DB) blocks. */
    private static ?array $previewOverride = null;

    public static function setPreviewOverride(array $blocks): void
    {
        self::$previewOverride = $blocks;
    }

    /** Idempotent schema creation for existing installs. */
    public static function ensureSchema(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            $db = (new Model('theme_landing_blocks'))->db;
            $db->query("CREATE TABLE IF NOT EXISTS theme_landing_blocks (
                id         UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
                theme_slug VARCHAR(50)  NOT NULL UNIQUE,
                blocks     TEXT         NOT NULL DEFAULT '[]',
                created_at TIMESTAMP    DEFAULT NOW(),
                updated_at TIMESTAMP    DEFAULT NOW(),
                deleted_at TIMESTAMP,
                created_by UUID,
                updated_by UUID,
                deleted_by UUID
            )");
            $db->execute();
        } catch (\Throwable) {
            // Non-fatal - getBlocks()/saveBlocks() below will simply fail closed.
        }
    }

    /**
     * Return this theme's block list, seeding it from the theme's archetype
     * fixture on first access if no row exists yet.
     */
    public static function getBlocks(string $themeSlug): array
    {
        if (self::$previewOverride !== null) {
            return self::$previewOverride;
        }

        self::ensureSchema();

        try {
            $row = (new Model('theme_landing_blocks'))
                ->select('blocks')
                ->where('theme_slug', $themeSlug)
                ->whereNull('deleted_at')
                ->get(1);

            if ($row) {
                $blocks = json_decode($row['blocks'] ?? '[]', true);
                return is_array($blocks) ? $blocks : [];
            }
        } catch (\Throwable) {
            return [];
        }

        $seed = LandingBlocksSeeder::forTheme($themeSlug);

        try {
            (new Model('theme_landing_blocks'))->save([
                'theme_slug' => $themeSlug,
                'blocks'     => json_encode($seed),
            ]);
        } catch (\Throwable) {
            // Non-fatal - the seed still renders this request even if the insert failed.
        }

        return $seed;
    }

    /** Whitelist + sanitize a raw (client-submitted) blocks array before persisting. */
    public static function sanitizeBlocks(array $blocks): array
    {
        $clean = [];

        foreach (array_slice($blocks, 0, self::MAX_BLOCKS) as $block) {
            if (!is_array($block) || !in_array($block['type'] ?? '', self::TYPES, true)) {
                continue;
            }
            $type = $block['type'];

            $sanitized = ['type' => $type];

            switch ($type) {
                case 'hero':
                    $sanitized['headline']    = self::text($block['headline']    ?? '');
                    $sanitized['subheadline'] = self::text($block['subheadline'] ?? '');
                    $sanitized['cta_text']    = self::text($block['cta_text']    ?? '');
                    $sanitized['cta_link']    = self::url($block['cta_link']     ?? '');
                    $sanitized['image']       = self::url($block['image']        ?? '');
                    break;

                case 'feature-grid':
                    $sanitized['headline'] = self::text($block['headline'] ?? '');
                    $columns = (int) ($block['columns'] ?? 3);
                    $sanitized['columns'] = in_array($columns, [2, 3, 4], true) ? $columns : 3;
                    $sanitized['items'] = array_map(fn($i) => [
                        'icon'  => self::text($i['icon']  ?? '', 60),
                        'title' => self::text($i['title'] ?? ''),
                        'text'  => self::text($i['text']  ?? '', 500),
                    ], self::items($block['items'] ?? []));
                    break;

                case 'testimonials':
                    $sanitized['headline'] = self::text($block['headline'] ?? '');
                    $sanitized['items'] = array_map(fn($i) => [
                        'quote'  => self::text($i['quote']  ?? '', 500),
                        'author' => self::text($i['author'] ?? ''),
                        'role'   => self::text($i['role']   ?? ''),
                        'avatar' => self::url($i['avatar']  ?? ''),
                    ], self::items($block['items'] ?? []));
                    break;

                case 'gallery':
                    $sanitized['headline'] = self::text($block['headline'] ?? '');
                    $sanitized['items'] = array_map(fn($i) => [
                        'image' => self::url($i['image'] ?? ''),
                        'alt'   => self::text($i['alt'] ?? '', 160),
                    ], self::items($block['items'] ?? []));
                    break;

                case 'cta-banner':
                    $sanitized['headline']    = self::text($block['headline'] ?? '');
                    $sanitized['text']        = self::text($block['text'] ?? '', 500);
                    $sanitized['button_text'] = self::text($block['button_text'] ?? '');
                    $sanitized['button_link'] = self::url($block['button_link'] ?? '');
                    break;

                case 'rich-text':
                    $sanitized['html'] = self::sanitizeHtml((string) ($block['html'] ?? ''));
                    break;

                case 'stats':
                    $sanitized['items'] = array_map(fn($i) => [
                        'number' => self::text($i['number'] ?? '', 40),
                        'label'  => self::text($i['label']  ?? '', 100),
                    ], self::items($block['items'] ?? []));
                    break;
            }

            $clean[] = $sanitized;
        }

        return $clean;
    }

    /** Persist a (already-sanitized) blocks array for one theme. */
    public static function saveBlocks(string $themeSlug, array $blocks): void
    {
        self::ensureSchema();

        $existing = (new Model('theme_landing_blocks'))
            ->select('id')
            ->where('theme_slug', $themeSlug)
            ->get(1);

        if ($existing) {
            (new Model('theme_landing_blocks'))
                ->where('theme_slug', $themeSlug)
                ->update(['blocks' => json_encode($blocks), 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            (new Model('theme_landing_blocks'))->save([
                'theme_slug' => $themeSlug,
                'blocks'     => json_encode($blocks),
            ]);
        }
    }

    public static function isEnabled(): bool
    {
        try {
            $row = (new Model('settings'))
                ->select('value')
                ->where('key', 'landing_blocks_enabled')
                ->where('grp', 'theme-customizer')
                ->get(1);
            return ($row['value'] ?? '0') === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    // -- Sanitization primitives -------------------------------------------------

    private static function items(mixed $items): array
    {
        return is_array($items) ? array_slice($items, 0, self::MAX_ITEMS) : [];
    }

    private static function text(mixed $val, int $maxLen = 200): string
    {
        $val = trim(strip_tags((string) $val));
        return mb_substr($val, 0, $maxLen);
    }

    /** Relative paths or http(s) URLs only - delegates to the shared sanitizer so
     *  "what counts as a safe URL" has one definition across the whole CMS. */
    private static function url(mixed $val): string
    {
        return \App\CMS\HtmlSanitizer::isSafeUrl($val, 500);
    }

    /** Rich Text block HTML - see App\CMS\HtmlSanitizer for the DOM-based allowlist
     *  sanitizer this delegates to (shared with Blog\Controllers\Admin\PostsController). */
    private static function sanitizeHtml(string $html): string
    {
        return \App\CMS\HtmlSanitizer::clean($html);
    }
}
