<?php

declare(strict_types=1);

namespace App\Modules\Pages;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS pages (
            id               UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            title            VARCHAR(255) NOT NULL,
            slug             VARCHAR(255) NOT NULL UNIQUE,
            content          TEXT,
            excerpt          TEXT,
            status           VARCHAR(20)  NOT NULL DEFAULT 'draft',
            template         VARCHAR(100) NOT NULL DEFAULT 'default',
            meta_title       VARCHAR(255),
            meta_description TEXT,
            sort_order       INT          NOT NULL DEFAULT 0,
            created_at       TIMESTAMP    DEFAULT NOW(),
            updated_at       TIMESTAMP    DEFAULT NOW(),
            created_by       UUID,
            updated_by       UUID,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        )");
        $db->execute();

        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'pages')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['View Pages',    'pages.view',    'Browse pages list'],
            ['Create Pages',  'pages.create',  'Create new pages'],
            ['Edit Pages',    'pages.edit',    'Edit page content and metadata'],
            ['Delete Pages',  'pages.delete',  'Delete pages'],
            ['Publish Pages', 'pages.publish', 'Change page status to published'],
        ] as [$name, $slug, $desc]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $name, ':slug' => $slug, ':desc' => $desc]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'pages'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DROP TABLE IF EXISTS pages CASCADE");
        $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'pages')");
        $db->execute();

        $db->query("DELETE FROM permissions WHERE module = 'pages'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $ca = 'App\Modules\Pages\Controllers\Admin\PagesController';
        $cf = 'App\Modules\Pages\Controllers\Front\PageController';

        // Admin CRUD (specific routes before wildcard)
        $router->get('/admin/pages',                              $ca, 'index');
        $router->get('/admin/pages/form',                         $ca, 'createForm');
        $router->post('/admin/pages/store',                       $ca, 'store');
        $router->get('/admin/pages/([a-zA-Z0-9\-]+)/form',        $ca, 'editForm');
        $router->post('/admin/pages/([a-zA-Z0-9\-]+)/update',     $ca, 'update');
        $router->post('/admin/pages/([a-zA-Z0-9\-]+)/delete',     $ca, 'delete');

        // Front-end — registered last so it doesn't shadow admin routes
        $router->get('/([a-z0-9][a-z0-9\-]*)',                   $cf, 'show');
    }
}
