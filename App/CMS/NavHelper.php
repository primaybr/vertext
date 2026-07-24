<?php

declare(strict_types=1);

namespace App\CMS;

/**
 * NavHelper - provides navigation menu data for use in theme layouts.
 *
 * Usage in a theme layout.php:
 *   $navItems = \App\CMS\NavHelper::getMenu('primary');
 *
 * Each item in the returned array:
 *   [
 *     'id'          => string,
 *     'label'       => string,
 *     'url'         => string (resolved),
 *     'open_in_new' => bool,
 *     'type'        => 'custom'|'page'|'module',
 *     'children'    => array (same shape, one level deep),
 *   ]
 *
 * If the Navigation module is not installed, falls back to auto-generating
 * items from every enabled module's nav_routes in module.json.
 */
class NavHelper
{
    /** @var array<string, array> Per-request menu cache (slug => items) */
    private static array $cache = [];

    /**
     * Return the nested menu items for a menu identified by its slug.
     * Falls back to module nav_routes if the Navigation module is not installed.
     */
    public static function getMenu(string $slug): array
    {
        if (isset(self::$cache[$slug])) {
            return self::$cache[$slug];
        }

        if (!ModuleLoader::isEnabled('navigation')) {
            return self::$cache[$slug] = self::buildFromModuleRoutes();
        }

        // Fragment-cached for 5 minutes; invalidated by NavigationController
        // on any menu save (PageCache::forgetFragment('nav_' . slug)). Keyed
        // by locale too - resolved URLs below carry a locale prefix, so the
        // id and en versions of a menu are genuinely different payloads, not
        // just different-language labels over the same URLs.
        return self::$cache[$slug] = PageCache::remember(
            'nav_' . $slug . '_' . I18n::getLocale(),
            static fn(): array => self::buildMenu($slug)
        );
    }

    /** Uncached menu builder (the body previously inlined in getMenu). */
    private static function buildMenu(string $slug): array
    {
        try {
            $menu = (new \Core\Model('nav_menus'))
                ->select('id')
                ->where('slug', $slug)
                ->get(1);

            if (!$menu) {
                return self::buildFromModuleRoutes();
            }

            $rows = (new \Core\Model('nav_items'))
                ->where('menu_id', $menu['id'])
                ->orderBy('sort_order', 'ASC')
                ->get() ?: [];

            $baseUrl = self::siteUrl();

            $parents  = [];
            $children = [];
            foreach ($rows as $row) {
                $row['url'] = self::resolveUrl($row, $baseUrl);
                if ($row['parent_id']) {
                    $children[$row['parent_id']][] = $row;
                } else {
                    $parents[] = $row;
                }
            }

            foreach ($parents as &$parent) {
                $parent['children'] = $children[$parent['id']] ?? [];
            }
            unset($parent);

            // Append module nav_routes whose URLs are not already in the DB menu.
            // This handles modules installed before Navigation (nav item was never seeded).
            $existingUrls = array_column($parents, 'url');
            foreach (self::buildFromModuleRoutes() as $auto) {
                if (!in_array($auto['url'], $existingUrls, true)) {
                    $parents[] = $auto;
                }
            }

        } catch (\Throwable) {
            return [];
        }

        return $parents;
    }

    /** Flush per-request cache (useful if menu data changes mid-request). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Build navigation items from enabled modules' nav_routes in module.json.
     * Used when the Navigation module is not installed or the primary menu is empty.
     */
    private static function buildFromModuleRoutes(): array
    {
        try {
            $baseUrl    = self::siteUrl();
            $modulesDir = dirname(__DIR__) . '/Modules';

            $enabled = (new \Core\Model('modules'))
                ->select('slug')
                ->where('status', 'enabled')
                ->orderBy('slug', 'ASC')
                ->get() ?: [];

            $items = [];
            foreach ($enabled as $mod) {
                $dirName  = str_replace(' ', '', ucwords(str_replace('-', ' ', $mod['slug'])));
                $manifestPath = $modulesDir . '/' . $dirName . '/module.json';
                if (!is_file($manifestPath)) {
                    continue;
                }
                $manifest = json_decode(file_get_contents($manifestPath) ?: '{}', true) ?: [];

                foreach ($manifest['nav_routes'] ?? [] as $route) {
                    if (empty($route['path'])) {
                        continue;
                    }

                    $path = $route['path'];

                    // Blog's base path is user-configurable at runtime, unlike every
                    // other module's static nav_routes declaration - resolve it live
                    // instead of trusting module.json's default "/blog", and skip
                    // entirely when Blog is at the site root (it's the homepage then,
                    // so a separate nav link would be redundant).
                    if ($mod['slug'] === 'blog' && class_exists(\App\Modules\Blog\Module::class)) {
                        $blogBase = \App\Modules\Blog\Module::basePath();
                        if ($blogBase === '') {
                            continue;
                        }
                        $path = '/' . $blogBase;
                    }

                    $items[] = [
                        'id'          => 'auto_' . md5($path),
                        'label'       => $route['label'] ?? $mod['slug'],
                        'url'         => I18n::path($baseUrl, $path),
                        'open_in_new' => false,
                        'type'        => 'module',
                        'children'    => [],
                        // Not part of the returned item shape - sort key only,
                        // stripped below. A module omitting "priority" sorts
                        // after every module that sets one, rather than landing
                        // wherever "modules.slug ASC" (this loop's own iteration
                        // order) happens to place it.
                        'priority'    => (int) ($route['priority'] ?? 1000),
                    ];
                }
            }

            // Auto-registered nav has no admin-configurable order (that's what
            // the Navigation module's DB-backed sort_order is for) - without
            // this, item order is purely "modules.slug ASC", alphabetical
            // accident rather than intent (e.g. Blog/Contact outranking Search).
            usort($items, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);
            foreach ($items as &$item) {
                unset($item['priority']);
            }
            unset($item);

            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    private static function resolveUrl(array $item, string $baseUrl): string
    {
        // "custom" links are admin-typed URLs (possibly external) - left
        // exactly as entered, never locale-prefixed. "page"/"module" links
        // are built from a known internal path, so they get the current
        // visitor's locale prefix like every other internal link.
        if ($item['type'] === 'page' && !empty($item['page_slug'])) {
            return I18n::path($baseUrl, '/' . ltrim($item['page_slug'], '/'));
        }
        if ($item['type'] === 'module' && !empty($item['url'])) {
            return I18n::path($baseUrl, '/' . ltrim($item['url'], '/'));
        }
        return $item['url'] ?? '#';
    }

    private static function siteUrl(): string
    {
        try {
            return rtrim(
                (new \Core\Model('settings'))->select('value')->where('key', 'site_url')->get(1)['value'] ?? '',
                '/'
            );
        } catch (\Throwable) {
            return '';
        }
    }
}
