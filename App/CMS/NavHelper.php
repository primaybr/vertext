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

        try {
            $menu = (new \Core\Model('nav_menus'))
                ->select('id')
                ->where('slug', $slug)
                ->get(1);

            if (!$menu) {
                return self::$cache[$slug] = self::buildFromModuleRoutes();
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
            return self::$cache[$slug] = [];
        }

        return self::$cache[$slug] = $parents;
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
                    $items[] = [
                        'id'          => 'auto_' . md5($route['path']),
                        'label'       => $route['label'] ?? $mod['slug'],
                        'url'         => $baseUrl . $route['path'],
                        'open_in_new' => false,
                        'type'        => 'module',
                        'children'    => [],
                    ];
                }
            }

            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    private static function resolveUrl(array $item, string $baseUrl): string
    {
        if ($item['type'] === 'page' && !empty($item['page_slug'])) {
            return $baseUrl . '/' . ltrim($item['page_slug'], '/');
        }
        if ($item['type'] === 'module' && !empty($item['url'])) {
            return rtrim($baseUrl, '/') . '/' . ltrim($item['url'], '/');
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
