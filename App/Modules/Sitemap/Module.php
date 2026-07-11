<?php

declare(strict_types=1);

namespace App\Modules\Sitemap;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        $this->seedDefaults($db);
    }

    /**
     * Duck-typed upgrade hook (see ModuleInterface docblock) - this module has no
     * schema to migrate, only new settings/permission rows added in 0.0.2. Reusing
     * the same ON CONFLICT DO NOTHING seeding as install() keeps this idempotent
     * and safe to run on a module that was installed before those keys existed.
     */
    public function upgrade(\Core\Database\Connection $db, string $fromVersion): void
    {
        $this->seedDefaults($db);
    }

    private function seedDefaults(\Core\Database\Connection $db): void
    {
        $sql = "INSERT INTO settings (key, value, type, grp, label)
                VALUES (:key, :val, :type, 'sitemap', :label)
                ON CONFLICT (key) DO NOTHING";

        foreach ([
            ['sitemap_include_pages',   '1', 'bool', 'Include Pages in sitemap'],
            ['sitemap_include_blog',    '1', 'bool', 'Include Blog posts in sitemap'],
            ['sitemap_include_events',  '1', 'bool', 'Include Events in sitemap'],
            ['sitemap_include_gallery', '1', 'bool', 'Include Gallery in sitemap'],
            ['sitemap_include_videos',  '1', 'bool', 'Include Videos in sitemap'],
            ['robots_extra_disallow',   '',  'text', 'Extra robots.txt Disallow paths (one per line)'],
        ] as [$key, $val, $type, $label]) {
            $db->query($sql);
            $db->arrayBind([':key' => $key, ':val' => $val, ':type' => $type, ':label' => $label]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO permissions (name, slug, description, module)
             VALUES ('Sitemap - Settings', 'sitemap.settings', 'Manage sitemap and robots.txt settings', 'sitemap')
             ON CONFLICT (slug) DO NOTHING"
        );
        $db->execute();

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'sitemap'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DELETE FROM settings WHERE grp = 'sitemap'");
        $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'sitemap')");
        $db->execute();
        $db->query("DELETE FROM permissions WHERE module = 'sitemap'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $c  = 'App\Modules\Sitemap\Controllers\SitemapController';
        $ca = 'App\Modules\Sitemap\Controllers\Admin\SitemapSettingsController';

        $router->get('/sitemap.xml', $c, 'index');
        $router->get('/robots.txt',  $c, 'robots');

        $router->get('/admin/sitemap/settings',       $ca, 'index');
        $router->post('/admin/sitemap/settings/save', $ca, 'save');
    }
}
