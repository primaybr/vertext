<?php

declare(strict_types=1);

namespace App\Modules\Sitemap;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        $sql = "INSERT INTO settings (key, value, type, grp, label)
                VALUES (:key, :val, :type, 'sitemap', :label)
                ON CONFLICT (key) DO NOTHING";

        foreach ([
            ['sitemap_include_pages', '1', 'bool', 'Include Pages in sitemap'],
            ['sitemap_include_blog',  '1', 'bool', 'Include Blog posts in sitemap'],
        ] as [$key, $val, $type, $label]) {
            $db->query($sql);
            $db->arrayBind([':key' => $key, ':val' => $val, ':type' => $type, ':label' => $label]);
            $db->execute();
        }
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DELETE FROM settings WHERE grp = 'sitemap'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $c = 'App\Modules\Sitemap\Controllers\SitemapController';
        $router->get('/sitemap.xml', $c, 'index');
    }
}
