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
 *     'id'          => string (UUID),
 *     'label'       => string,
 *     'url'         => string (resolved),
 *     'open_in_new' => bool,
 *     'type'        => 'custom'|'page',
 *     'children'    => array (same shape, one level deep),
 *   ]
 *
 * Returns empty array if the Navigation module is not installed.
 */
class NavHelper
{
    /** @var array<string, array> Per-request menu cache (slug => items) */
    private static array $cache = [];

    /**
     * Return the nested menu items for a menu identified by its slug.
     * Falls back to [] if the module is not installed or the menu does not exist.
     */
    public static function getMenu(string $slug): array
    {
        if (isset(self::$cache[$slug])) {
            return self::$cache[$slug];
        }

        if (!ModuleLoader::isEnabled('navigation')) {
            return self::$cache[$slug] = [];
        }

        try {
            $menu = (new \Core\Model('nav_menus'))
                ->select('id')
                ->where('slug', $slug)
                ->get(1);

            if (!$menu) {
                return self::$cache[$slug] = [];
            }

            $rows = (new \Core\Model('nav_items'))
                ->where('menu_id', $menu['id'])
                ->orderBy('sort_order', 'ASC')
                ->get() ?: [];

            if (empty($rows)) {
                return self::$cache[$slug] = [];
            }

            $baseUrl = rtrim(
                (new \Core\Model('settings'))->select('value')->where('key', 'site_url')->get(1)['value'] ?? '',
                '/'
            );

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

            // Attach children to parents
            foreach ($parents as &$parent) {
                $parent['children'] = $children[$parent['id']] ?? [];
            }
            unset($parent);

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

    private static function resolveUrl(array $item, string $baseUrl): string
    {
        if ($item['type'] === 'page' && !empty($item['page_slug'])) {
            return $baseUrl . '/' . ltrim($item['page_slug'], '/');
        }
        return $item['url'] ?? '#';
    }
}
