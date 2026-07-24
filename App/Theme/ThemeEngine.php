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
 * Canonical URL and twitter:card tags are computed automatically for every
 * render() call (see App/Views/_shared/seo-meta.php) - no per-controller
 * opt-in needed beyond the page_title/page_description/page_image keys above.
 *
 * Theme assets live in App/Themes/{name}/ (source, git-tracked).
 * On first request they are deployed to Public/themes/{name}/ automatically.
 */
class ThemeEngine
{
    private static string $resolvedTheme = '';

    // -- Public API -------------------------------------------------------------

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

        // Canonical URL: prefer the admin-configured site_url (agrees with the
        // sitemap's own <loc> host resolution) over the detected request host,
        // and always strip the query string - canonical should point at the
        // clean content URL, not a paginated/tracked variant.
        $uri           = new \Core\Http\URI();
        $requestPath   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $canonicalHost = !empty($site['site_url']) ? rtrim($site['site_url'], '/') : rtrim($uri->getProtocol() . $uri->getHost(), '/');
        $canonicalUrl  = $canonicalHost . $requestPath;

        // Inject RSS feed autodiscovery link when Blog module is active
        $feedUrl = '';
        if (\App\CMS\ModuleLoader::isEnabled('blog')) {
            try {
                $bpRow = (new \Core\Model('settings'))
                    ->select('value')
                    ->where('key', 'blog_base_path')
                    ->where('grp', 'blog')
                    ->get(1);
                $rawBase  = trim($bpRow['value'] ?? 'blog', '/');
                $blogBase = $rawBase === '' ? '' : '/' . $rawBase;
                $feedUrl  = rtrim($baseUrl, '/') . $blogBase . '/feed.rss';
            } catch (\Throwable) {}
        }

        $layoutFile = ROOT . 'App' . DS . 'Themes' . DS . $theme . DS . 'layout.php';
        if (!file_exists($layoutFile)) {
            // Graceful degradation: output content without layout
            echo $content;
            return;
        }

        // Make everything available to the layout template
        ob_start();
        extract(compact(
            'content', 'pageTitle', 'pageDesc', 'pageImage', 'canonicalUrl',
            'baseUrl', 'themeUrl', 'siteName', 'siteDesc',
            'site', 'data', 'feedUrl'
        ));
        include $layoutFile;
        $finalHtml = ob_get_clean();
        echo (new \Core\Utilities\Text\HTML())->minify($finalHtml);

        // Track page view (silent - analytics must never break a page)
        if (\App\CMS\ModuleLoader::isEnabled('analytics')) {
            $urlPath  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $refHost  = \App\Modules\Analytics\Tracker::referrerHost();
            \App\Modules\Analytics\Tracker::record($urlPath, $pageTitle, $refHost);
        }
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

    /**
     * Scan App/Themes/ for directories containing a valid theme.json.
     * Returns array of theme manifests with an extra 'active' boolean.
     */
    public static function discover(): array
    {
        $themesDir   = ROOT . 'App' . DS . 'Themes' . DS;
        $activeTheme = self::activeTheme();
        $themes      = [];

        if (!is_dir($themesDir)) {
            return $themes;
        }

        foreach (glob($themesDir . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            $jsonFile = $dir . DS . 'theme.json';
            if (!file_exists($jsonFile)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($jsonFile), true);
            if (!is_array($manifest) || empty($manifest['slug'])) {
                continue;
            }

            $manifest['active'] = ($manifest['slug'] === $activeTheme);
            $themes[] = $manifest;
        }

        return $themes;
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

    // -- Internal helpers -------------------------------------------------------

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
            $rows = (new \Core\Model('settings'))->whereIn(['grp' => ['general', 'analytics']])->get() ?: [];
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
