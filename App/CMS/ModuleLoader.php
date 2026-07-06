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
    /** Per-request cache: null = not loaded yet */
    private static ?array $enabled     = null;
    private static ?array $navItems    = null;
    private static ?array $assets      = null;
    private static ?array $frontAssets = null;

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
            self::$assets      = ['css' => [], 'js' => []];
            self::$frontAssets = ['css' => [], 'js' => []];
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
                // deployed the same way, but injected into theme layouts instead of the admin layout.
                foreach ((array) ($assetManifest['assets']['css'] ?? []) as $p) {
                    $p = ltrim((string) $p, '/');
                    if ($p) self::$frontAssets['css'][] = "modules/{$assetSlug}/{$p}?v={$ver}";
                }
                foreach ((array) ($assetManifest['assets']['js'] ?? []) as $p) {
                    $p = ltrim((string) $p, '/');
                    if ($p) self::$frontAssets['js'][] = "modules/{$assetSlug}/{$p}?v={$ver}";
                }
            }

        } catch (\Exception) {
            // If DB is unavailable, allow everything (install/setup state)
            self::$enabled      = [];
            self::$navItems     = [];
            self::$assets       = ['css' => [], 'js' => []];
            self::$frontAssets  = ['css' => [], 'js' => []];
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
     * Return front-end asset URL paths (relative to assetsUrl) for all enabled modules.
     * Reads the top-level "css"/"js" keys of module.json's "assets" (sibling to "admin"),
     * e.g. {"assets": {"css": [...], "js": [...], "admin": {"css": [...], "js": [...]}}}.
     * Injected into theme layouts (App/Themes/*\/layout.php), not the admin layout.
     */
    public static function frontAssets(): array
    {
        self::load();
        return self::$frontAssets ?? ['css' => [], 'js' => []];
    }

    /**
     * Reset the per-request cache.
     * Call this immediately after a module status is toggled so that
     * subsequent isEnabled() calls within the same request see the new state.
     */
    public static function refresh(): void
    {
        self::$enabled      = null;
        self::$navItems     = null;
        self::$assets       = null;
        self::$frontAssets  = null;
    }
}
