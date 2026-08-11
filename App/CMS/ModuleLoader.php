<?php

declare(strict_types=1);

namespace App\CMS;

/**
 * ModuleLoader - reads module status from the database and gates controller access.
 *
 * Uses a static per-request cache so the DB is queried at most once per request.
 * Call refresh() after toggling a module status to reset the cache.
 */
class ModuleLoader
{
    /**
     * Modules whose front assets apply site-wide regardless of which module
     * "owns" the current page, so they're always included alongside the
     * current page's own module in frontAssets():
     *   - theme-customizer: admin-configured accent color/font/custom-CSS
     *     overrides, which must apply on every page, not just its own.
     *   - forms, newsletter: App\CMS\Shortcodes::render() lets a [form
     *     slug="..."] or [newsletter_signup] shortcode embed either module's
     *     widget inline into ANY Pages/Blog body - the embedded partial
     *     renders only markup, no <link>/<script> tags of its own, so its
     *     CSS/JS has no other way to reach the page than being global here.
     */
    private const ALWAYS_GLOBAL_FRONT_MODULES = ['theme-customizer', 'forms', 'newsletter'];

    /** Per-request cache: null = not loaded yet */
    private static ?array $enabled            = null;
    private static ?array $navItems           = null;
    private static ?array $assets             = null;
    private static ?array $frontAssetsByModule = null;

    /** Load enabled module data from DB into the static cache */
    private static function load(): void
    {
        if (self::$enabled !== null) {
            return;
        }

        try {
            $rows = (new \Core\Model('modules'))
                ->select('slug, directory')
                ->where('status', 'enabled')
                ->get() ?: [];

            self::$enabled  = array_column($rows, 'slug');
            self::$navItems = [];

            $modulesDir = ROOT . 'App' . DS . 'Modules' . DS;
            foreach ($rows as $row) {
                $dir = $row['directory'] ?? '';
                if (!$dir || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $dir)) {
                    continue;
                }

                $manifestFile = $modulesDir . $dir . DS . 'module.json';
                if (!file_exists($manifestFile)) {
                    continue;
                }

                $manifest = json_decode(file_get_contents($manifestFile), true);
                if (!is_array($manifest) || empty($manifest['nav'])) {
                    continue;
                }

                $nav = $manifest['nav'];
                if (empty($nav['label']) || empty($nav['path'])) {
                    continue;
                }

                $subnav = [];
                if (!empty($nav['subnav']) && is_array($nav['subnav'])) {
                    foreach ($nav['subnav'] as $sub) {
                        if (empty($sub['label']) || empty($sub['path'])) {
                            continue;
                        }
                        $subnav[] = [
                            'label'      => (string) $sub['label'],
                            'icon'       => (string) ($sub['icon'] ?? 'pi-circle'),
                            'path'       => (string) $sub['path'],
                            'permission' => (string) ($sub['permission'] ?? ''),
                        ];
                    }
                }

                self::$navItems[] = [
                    'label'      => (string) $nav['label'],
                    'icon'       => (string) ($nav['icon'] ?? 'pi-circle'),
                    'path'       => (string) $nav['path'],
                    'active'     => (string) ($nav['active'] ?? $row['slug']),
                    'permission' => (string) ($nav['permission'] ?? ''),
                    'subnav'     => $subnav,
                ];
            }
            // Build module asset URL paths (css/js relative to assetsUrl)
            self::$assets              = ['css' => [], 'js' => []];
            self::$frontAssetsByModule = [];
            foreach ($rows as $assetRow) {
                $assetDir  = $assetRow['directory'] ?? '';
                $assetSlug = $assetRow['slug']      ?? '';
                if (!$assetDir || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $assetDir) || !$assetSlug) {
                    continue;
                }

                $assetManifestFile = $modulesDir . $assetDir . DS . 'module.json';
                if (!file_exists($assetManifestFile)) {
                    continue;
                }

                $assetManifest = json_decode(file_get_contents($assetManifestFile), true);
                $ver           = rawurlencode($assetManifest['version'] ?? '1');

                $adminAssets = $assetManifest['assets']['admin'] ?? [];
                foreach ((array) ($adminAssets['css'] ?? []) as $p) {
                    $p = ltrim((string) $p, '/');
                    if ($p) self::$assets['css'][] = "modules/{$assetSlug}/{$p}?v={$ver}";
                }
                foreach ((array) ($adminAssets['js'] ?? []) as $p) {
                    $p = ltrim((string) $p, '/');
                    if ($p) self::$assets['js'][] = "modules/{$assetSlug}/{$p}?v={$ver}";
                }

                // Front-end assets live at the top level of "assets" (sibling to "admin"),
                // deployed the same way, but injected into theme layouts instead of the
                // admin layout - kept per-module here so frontAssets() can scope to just
                // the module(s) a given page actually needs instead of unioning everything.
                $moduleFront = ['css' => [], 'js' => []];
                foreach ((array) ($assetManifest['assets']['css'] ?? []) as $p) {
                    $p = ltrim((string) $p, '/');
                    if ($p) $moduleFront['css'][] = "modules/{$assetSlug}/{$p}?v={$ver}";
                }
                foreach ((array) ($assetManifest['assets']['js'] ?? []) as $p) {
                    $p = ltrim((string) $p, '/');
                    if ($p) $moduleFront['js'][] = "modules/{$assetSlug}/{$p}?v={$ver}";
                }
                if ($moduleFront['css'] || $moduleFront['js']) {
                    self::$frontAssetsByModule[$assetSlug] = $moduleFront;
                }
            }

        } catch (\Exception) {
            // If DB is unavailable, allow everything (install/setup state)
            self::$enabled              = [];
            self::$navItems             = [];
            self::$assets               = ['css' => [], 'js' => []];
            self::$frontAssetsByModule  = [];
        }
    }

    /**
     * Check whether a module is currently enabled.
     * An empty slug always passes (no module declared = unrestricted).
     */
    public static function isEnabled(string $slug): bool
    {
        if ($slug === '') {
            return true;
        }

        self::load();
        return in_array($slug, self::$enabled, true);
    }

    /** Return all enabled module slugs */
    public static function getEnabled(): array
    {
        self::load();
        return self::$enabled ?? [];
    }

    /**
     * Return nav items declared in each enabled module's module.json.
     * Each entry: ['label', 'icon', 'path', 'active', 'permission']
     */
    public static function navItems(): array
    {
        self::load();
        return self::$navItems ?? [];
    }

    /**
     * Return admin asset URL paths (relative to assetsUrl) for all enabled modules.
     * Returns ['css' => [...], 'js' => [...]] - paths include ?v= cache-buster.
     * Prepend assetsUrl in your layout: $assetsUrl . $path
     */
    public static function assets(): array
    {
        self::load();
        return self::$assets ?? ['css' => [], 'js' => []];
    }

    /**
     * Return front-end asset URL paths (relative to assetsUrl) for the current page.
     * Reads the top-level "css"/"js" keys of module.json's "assets" (sibling to "admin"),
     * e.g. {"assets": {"css": [...], "js": [...], "admin": {"css": [...], "js": [...]}}}.
     * Injected into theme layouts (App/Themes/*\/layout.php), not the admin layout.
     *
     * $currentModule scopes the result to that module's own front assets, or
     * an ordered list of modules whose markup shares the current page,
     * plus ALWAYS_GLOBAL_FRONT_MODULES (ThemeEngine::render() derives it from
     * the view path, e.g. 'modules/blog/front/index' -> 'blog') - without it,
     * every enabled module's front assets are unioned together regardless of
     * whether the current page renders any of that module's markup at all
     * (the old behavior, kept as the default for error pages and anything
     * else that doesn't resolve to a single owning module).
     */
    public static function frontAssets(string|array|null $currentModule = null): array
    {
        self::load();
        $byModule = self::$frontAssetsByModule ?? [];

        if ($currentModule === null) {
            $wanted = array_keys($byModule);
        } else {
            $requested = is_array($currentModule) ? $currentModule : [$currentModule];
            $requested = array_values(array_filter(
                $requested,
                static fn (mixed $slug): bool => is_string($slug) && $slug !== ''
            ));
            $wanted = array_values(array_unique([...$requested, ...self::ALWAYS_GLOBAL_FRONT_MODULES]));
        }

        $css = [];
        $js  = [];
        foreach ($wanted as $slug) {
            $css = array_merge($css, $byModule[$slug]['css'] ?? []);
            $js  = array_merge($js, $byModule[$slug]['js'] ?? []);
        }

        return ['css' => $css, 'js' => $js];
    }

    /**
     * Reset the per-request cache.
     * Call this immediately after a module status is toggled so that
     * subsequent isEnabled() calls within the same request see the new state.
     */
    public static function refresh(): void
    {
        self::$enabled              = null;
        self::$navItems             = null;
        self::$assets               = null;
        self::$frontAssetsByModule  = null;
    }
}
