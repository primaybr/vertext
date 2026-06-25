<?php

declare(strict_types=1);

namespace App\Modules\Navigation;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS nav_menus (
            id         UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            name       VARCHAR(120) NOT NULL,
            slug       VARCHAR(120) UNIQUE NOT NULL,
            created_at TIMESTAMP    DEFAULT NOW(),
            updated_at TIMESTAMP    DEFAULT NOW()
        )");
        $db->execute();

        $db->query("CREATE TABLE IF NOT EXISTS nav_items (
            id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            menu_id     UUID         NOT NULL REFERENCES nav_menus(id) ON DELETE CASCADE,
            parent_id   UUID         REFERENCES nav_items(id) ON DELETE CASCADE,
            type        VARCHAR(20)  NOT NULL DEFAULT 'custom',
            label       VARCHAR(120) NOT NULL,
            url         VARCHAR(500),
            page_slug   VARCHAR(255),
            sort_order  INT          NOT NULL DEFAULT 0,
            open_in_new BOOLEAN      NOT NULL DEFAULT FALSE,
            created_at  TIMESTAMP    DEFAULT NOW(),
            updated_at  TIMESTAMP    DEFAULT NOW()
        )");
        $db->execute();

        // Seed primary menu
        $db->query("INSERT INTO nav_menus (name, slug) VALUES ('Primary Navigation', 'primary') ON CONFLICT (slug) DO NOTHING");
        $db->execute();

        // Permissions
        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'navigation')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['View Navigation', 'navigation.view',   'Access navigation menu management'],
            ['Manage Navigation', 'navigation.manage', 'Create, edit, and delete navigation menus and items'],
        ] as [$name, $slug, $desc]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $name, ':slug' => $slug, ':desc' => $desc]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'navigation'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DROP TABLE IF EXISTS nav_items CASCADE");
        $db->execute();

        $db->query("DROP TABLE IF EXISTS nav_menus CASCADE");
        $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'navigation')");
        $db->execute();

        $db->query("DELETE FROM permissions WHERE module = 'navigation'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $c = 'App\Modules\Navigation\Controllers\Admin\NavigationController';

        $router->get('/admin/navigation',                                                              $c, 'index');
        $router->post('/admin/navigation/store',                                                       $c, 'store');
        $router->get('/admin/navigation/([a-zA-Z0-9\-]+)',                                             $c, 'builder');
        $router->post('/admin/navigation/([a-zA-Z0-9\-]+)/delete',                                    $c, 'delete');
        $router->post('/admin/navigation/([a-zA-Z0-9\-]+)/items/store',                               $c, 'storeItem');
        $router->post('/admin/navigation/([a-zA-Z0-9\-]+)/items/reorder',                             $c, 'reorderItems');
        $router->post('/admin/navigation/([a-zA-Z0-9\-]+)/items/([a-zA-Z0-9\-]+)/update',             $c, 'updateItem');
        $router->post('/admin/navigation/([a-zA-Z0-9\-]+)/items/([a-zA-Z0-9\-]+)/delete',             $c, 'deleteItem');
    }
}
