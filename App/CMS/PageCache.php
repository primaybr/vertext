<?php

declare(strict_types=1);

namespace App\CMS;

use Core\Model;

/**
 * Lightweight file-based caching for the public front-end (v0.0.8).
 *
 * Two layers:
 *  - Full-page output cache (serve()/capture()): public GET renders of Pages
 *    and Blog. Guarded aggressively - any personalized/stateful output is
 *    skipped (logged-in admin or member, flash messages pending, or the
 *    rendered HTML embeds a CSRF token, e.g. an embedded form).
 *  - Fragment cache (remember()): small computed structures like the nav menu.
 *
 * Files live in Cache/pages/ and Cache/fragments/; clearing Cache/ (Admin >
 * Settings > Clear Cache) wipes both.
 */
class PageCache
{
    public const DEFAULT_TTL   = 600; // full pages: 10 min
    public const FRAGMENT_TTL  = 300; // fragments: 5 min

    // ── Enable / bypass logic ─────────────────────────────────────────────────

    public static function enabled(): bool
    {
        static $enabled = null;
        if ($enabled !== null) return $enabled;

        try {
            $row = (new Model('settings'))->select('value')->where('key', 'cache_pages_enabled')->get(1);
            $enabled = ($row['value'] ?? '0') === '1';
        } catch (\Throwable $e) {
            $enabled = false;
        }
        return $enabled;
    }

    /** True when this request may be served from / stored to the page cache. */
    private static function cacheableRequest(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return false;

        // Only cache clean URLs (a lang switch is fine - it redirects/sets session)
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        if ($qs !== '' && !preg_match('/^lang=[a-z\-]{2,10}$/i', $qs)) return false;

        // Never cache personalized output
        if (Auth::check()) return false;
        if (ModuleLoader::isEnabled('members') && SiteAuth::check()) return false;

        // Pending flash messages make the next render one-off
        foreach (array_keys($_SESSION ?? []) as $key) {
            if (str_contains((string) $key, 'flash')) return false;
        }

        return true;
    }

    private static function key(): string
    {
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $uri    = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $locale = I18n::getLocale();
        return hash('sha256', $host . '|' . $uri . '|' . $locale);
    }

    private static function pageFile(string $key): string
    {
        return ROOT . 'Cache' . DS . 'pages' . DS . $key . '.html';
    }

    // ── Full-page cache ───────────────────────────────────────────────────────

    /** Serve the cached page and exit, when possible. Call at the top of a front action. */
    public static function serve(): void
    {
        if (!self::enabled() || !self::cacheableRequest()) return;

        $file = self::pageFile(self::key());
        if (!is_file($file)) return;
        if (time() - (int) filemtime($file) > self::DEFAULT_TTL) {
            @unlink($file);
            return;
        }

        $html = @file_get_contents($file);
        if ($html === false || $html === '') return;

        header('X-Vertext-Cache: hit');
        echo $html;
        exit;
    }

    /**
     * Run the renderer, capturing its output into the cache. The output still
     * reaches the browser unchanged. Skips storing when the HTML embeds a CSRF
     * token (per-visitor state must never be shared).
     */
    public static function capture(callable $render): void
    {
        if (!self::enabled() || !self::cacheableRequest()) {
            $render();
            return;
        }

        ob_start();
        $render();
        $html = (string) ob_get_flush();

        if ($html === '' || str_contains($html, 'name="csrf_token"')) {
            return;
        }

        $dir = ROOT . 'Cache' . DS . 'pages';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return;
        @file_put_contents(self::pageFile(self::key()), $html, LOCK_EX);
    }

    /** Remove every cached page (call on publish/save of any public content). */
    public static function flushPages(): void
    {
        foreach (glob(ROOT . 'Cache' . DS . 'pages' . DS . '*.html') ?: [] as $file) {
            @unlink($file);
        }
    }

    // ── Fragment cache ────────────────────────────────────────────────────────

    /** Read-through cache for small serializable values. */
    public static function remember(string $name, callable $produce, int $ttl = self::FRAGMENT_TTL): mixed
    {
        $file = ROOT . 'Cache' . DS . 'fragments' . DS . preg_replace('/[^a-z0-9_\-]/', '', strtolower($name)) . '.json';

        if (is_file($file) && time() - (int) filemtime($file) <= $ttl) {
            $raw = @file_get_contents($file);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if ($decoded !== null) return $decoded;
            }
        }

        $value = $produce();

        $dir = dirname($file);
        if (is_dir($dir) || @mkdir($dir, 0755, true)) {
            @file_put_contents($file, json_encode($value), LOCK_EX);
        }

        return $value;
    }

    /** Drop one fragment (or all fragments when $name is null). */
    public static function forgetFragment(?string $name = null): void
    {
        if ($name !== null) {
            @unlink(ROOT . 'Cache' . DS . 'fragments' . DS . preg_replace('/[^a-z0-9_\-]/', '', strtolower($name)) . '.json');
            return;
        }
        foreach (glob(ROOT . 'Cache' . DS . 'fragments' . DS . '*.json') ?: [] as $file) {
            @unlink($file);
        }
    }

    // ── Stats (Settings panel) ────────────────────────────────────────────────

    /** @return array{pages:int, fragments:int, other:int, bytes:int} */
    public static function stats(): array
    {
        $stats = ['pages' => 0, 'fragments' => 0, 'other' => 0, 'bytes' => 0];
        $root  = ROOT . 'Cache';
        if (!is_dir($root)) return $stats;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $stats['bytes'] += (int) $file->getSize();
            $path = (string) $file->getPathname();
            if (str_contains($path, DS . 'pages' . DS)) {
                $stats['pages']++;
            } elseif (str_contains($path, DS . 'fragments' . DS)) {
                $stats['fragments']++;
            } else {
                $stats['other']++;
            }
        }
        return $stats;
    }

    /** Delete expired page/fragment files (invoked from the admin flush). */
    public static function pruneExpired(): int
    {
        $pruned = 0;
        foreach (glob(ROOT . 'Cache' . DS . 'pages' . DS . '*.html') ?: [] as $file) {
            if (time() - (int) filemtime($file) > self::DEFAULT_TTL) {
                @unlink($file);
                $pruned++;
            }
        }
        foreach (glob(ROOT . 'Cache' . DS . 'fragments' . DS . '*.json') ?: [] as $file) {
            if (time() - (int) filemtime($file) > self::FRAGMENT_TTL) {
                @unlink($file);
                $pruned++;
            }
        }
        return $pruned;
    }
}
