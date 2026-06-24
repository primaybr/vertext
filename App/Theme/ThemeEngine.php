<?php

declare(strict_types=1);

namespace App\Theme;

/**
 * ThemeEngine - wraps public-facing module views in the active site theme.
 *
 * Usage from a front-end controller:
 *   ThemeEngine::render('modules/blog/front/index', $data);
 *
 * $data may include special keys consumed by the layout:
 *   page_title       - text for <title> and og:title
 *   page_description - meta description
 *   page_image       - og:image URL
 *
 * Theme assets live in App/Themes/{name}/ (source, git-tracked).
 * On first request they are deployed to Public/themes/{name}/ automatically.
 */
class ThemeEngine
{
    private static string $resolvedTheme = '';

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Render a front-end view wrapped in the active theme layout.
     *
     * The view file is resolved from App/Views/{view}.php (same convention
     * as the core template parser) so ModuleManager::deployViews() applies.
     */
    public static function render(string $view, array $data = []): void
    {
        $theme = self::activeTheme();
        self::deployIfNeeded($theme);

        $content  = self::renderView($view, $data);
        $baseUrl  = $data['baseUrl'] ?? '';
        $themeUrl = $baseUrl . '/themes/' . $theme;

        // Extract common page-meta keys so layout can reference them directly
        $pageTitle = $data['page_title']       ?? '';
        $pageDesc  = $data['page_description'] ?? '';
        $pageImage = $data['page_image']       ?? '';

        // Load general site settings for nav/footer
        $site     = self::siteSettings();
        $siteName = $site['site_name']        ?? 'Vertext';
        $siteDesc = $site['site_description'] ?? '';

        $layoutFile = ROOT . 'App' . DS . 'Themes' . DS . $theme . DS . 'layout.php';
        if (!file_exists($layoutFile)) {
            // Graceful degradation: output content without layout
            echo $content;
            return;
        }

        // Make everything available to the layout template
        ob_start();
        extract(compact(
            'content', 'pageTitle', 'pageDesc', 'pageImage',
            'baseUrl', 'themeUrl', 'siteName', 'siteDesc',
            'site', 'data'
        ));
        include $layoutFile;
        echo ob_get_clean();
    }

    /** Return the active theme slug (cached in static property). */
    public static function activeTheme(): string
    {
        if (self::$resolvedTheme !== '') {
            return self::$resolvedTheme;
        }
        try {
            $row = (new \Core\Model('settings'))
                ->select('value')
                ->where('key', 'active_theme')
                ->get(1);
            self::$resolvedTheme = ($row && !empty($row['value'])) ? $row['value'] : 'default';
        } catch (\Throwable) {
            self::$resolvedTheme = 'default';
        }
        return self::$resolvedTheme;
    }

    /** Return the public URL for a theme asset (e.g. 'css/theme.css'). */
    public static function assetUrl(string $path, string $baseUrl = '', string $theme = ''): string
    {
        $theme   = $theme   ?: self::activeTheme();
        $baseUrl = $baseUrl ?: '';
        return $baseUrl . '/themes/' . $theme . '/' . ltrim($path, '/');
    }

    /**
     * Manually deploy a theme's assets from App/Themes/{name}/ to Public/themes/{name}/.
     * Called automatically on first render; can also be triggered from an admin action.
     */
    public static function deploy(string $theme = ''): bool
    {
        $theme  = $theme ?: self::activeTheme();
        $srcDir = ROOT . 'App' . DS . 'Themes' . DS . $theme . DS;
        $dstDir = ROOT . 'Public' . DS . 'themes' . DS . $theme . DS;

        if (!is_dir($srcDir)) {
            return false;
        }

        return self::copyAssets($srcDir, $dstDir);
    }

    // ── Internal helpers ───────────────────────────────────────────────────────

    private static function renderView(string $view, array $data): string
    {
        $file = ROOT . 'App' . DS . 'Views' . DS . str_replace(['/', '\\'], DS, $view) . '.php';
        if (!file_exists($file)) {
            return '';
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    private static function deployIfNeeded(string $theme): void
    {
        $dstDir = ROOT . 'Public' . DS . 'themes' . DS . $theme . DS;
        if (is_dir($dstDir) && glob($dstDir . '*')) {
            return; // already deployed
        }
        self::deploy($theme);
    }

    private static function copyAssets(string $src, string $dst): bool
    {
        $ok = true;
        foreach (['css', 'js', 'fonts', 'images'] as $sub) {
            $srcSub = $src . $sub . DS;
            if (!is_dir($srcSub)) {
                continue;
            }
            $dstSub = $dst . $sub . DS;
            if (!is_dir($dstSub) && !mkdir($dstSub, 0755, true)) {
                $ok = false;
                continue;
            }
            foreach (glob($srcSub . '*') ?: [] as $f) {
                if (is_file($f) && !copy($f, $dstSub . basename($f))) {
                    $ok = false;
                }
            }
        }
        return $ok;
    }

    private static function siteSettings(): array
    {
        try {
            $rows = (new \Core\Model('settings'))->where('grp', 'general')->get() ?: [];
            $s    = [];
            foreach ($rows as $r) {
                $s[$r['key']] = $r['value'];
            }
            return $s;
        } catch (\Throwable) {
            return [];
        }
    }
}
