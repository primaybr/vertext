<?php

declare(strict_types=1);

namespace App\Modules\Gallery;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        // Detect users.id type so created_by/updated_by use a compatible type for JOINs
        $userIdType = 'UUID';
        try {
            $r = \Core\Model::on($db, 'information_schema.columns')
                ->select('data_type')->where('table_name', 'users')
                ->where('column_name', 'id')->where('table_schema', 'public')->get(1);
            if ($r && stripos($r['data_type'] ?? '', 'int') !== false) {
                $userIdType = 'BIGINT';
            }
        } catch (\Exception) {}

        $db->query("CREATE TABLE IF NOT EXISTS galleries (
            id               UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            title            VARCHAR(255) NOT NULL,
            slug             VARCHAR(255) NOT NULL UNIQUE,
            description      TEXT,
            cover_image_id   UUID,
            status           VARCHAR(20)  NOT NULL DEFAULT 'draft',
            meta_title       VARCHAR(255),
            meta_description TEXT,
            created_at       TIMESTAMP    DEFAULT NOW(),
            updated_at       TIMESTAMP    DEFAULT NOW(),
            deleted_at       TIMESTAMP,
            created_by       {$userIdType},
            updated_by       {$userIdType},
            deleted_by       {$userIdType}
        )");
        $db->execute();

        // Correct column types if table was created before this detection was added
        if ($userIdType === 'BIGINT') {
            foreach (['created_by', 'updated_by'] as $col) {
                try {
                    $db->query("SAVEPOINT sp_fix_gal_{$col}"); $db->execute();
                    $cr = \Core\Model::on($db, 'information_schema.columns')
                        ->select('data_type')->where('table_name', 'galleries')
                        ->where('column_name', $col)->where('table_schema', 'public')->get(1);
                    if ($cr && strtolower($cr['data_type'] ?? '') === 'uuid') {
                        $db->query("ALTER TABLE galleries DROP COLUMN IF EXISTS {$col}"); $db->execute();
                        $db->query("ALTER TABLE galleries ADD COLUMN {$col} BIGINT"); $db->execute();
                    }
                    $db->query("RELEASE SAVEPOINT sp_fix_gal_{$col}"); $db->execute();
                } catch (\Exception) {
                    try { $db->query("ROLLBACK TO SAVEPOINT sp_fix_gal_{$col}"); $db->execute(); } catch (\Exception) {}
                }
            }
        }

        // FKs added separately - cover_image_id always safe (media_files.id is UUID),
        // created_by/updated_by guarded in case users.id type doesn't match UUID yet.
        // SAVEPOINT/ROLLBACK TO clears the aborted-transaction state on failure.
        try {
            $db->query("SAVEPOINT sp_galleries_cover_fk"); $db->execute();
            $db->query("ALTER TABLE galleries DROP CONSTRAINT IF EXISTS galleries_cover_image_id_fkey"); $db->execute();
            $db->query("ALTER TABLE galleries ADD CONSTRAINT galleries_cover_image_id_fkey FOREIGN KEY (cover_image_id) REFERENCES media_files(id) ON DELETE SET NULL"); $db->execute();
            $db->query("RELEASE SAVEPOINT sp_galleries_cover_fk"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_galleries_cover_fk"); $db->execute(); } catch (\Exception) {}
        }

        try {
            $db->query("SAVEPOINT sp_galleries_users_fk"); $db->execute();
            $db->query("ALTER TABLE galleries DROP CONSTRAINT IF EXISTS galleries_created_by_fkey"); $db->execute();
            $db->query("ALTER TABLE galleries ADD CONSTRAINT galleries_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE galleries DROP CONSTRAINT IF EXISTS galleries_updated_by_fkey"); $db->execute();
            $db->query("ALTER TABLE galleries ADD CONSTRAINT galleries_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE galleries DROP CONSTRAINT IF EXISTS galleries_deleted_by_fkey"); $db->execute();
            $db->query("ALTER TABLE galleries ADD CONSTRAINT galleries_deleted_by_fkey FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("RELEASE SAVEPOINT sp_galleries_users_fk"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_galleries_users_fk"); $db->execute(); } catch (\Exception) {}
        }

        $db->query("CREATE TABLE IF NOT EXISTS gallery_items (
            id            UUID      PRIMARY KEY DEFAULT gen_random_uuid(),
            gallery_id    UUID      NOT NULL,
            media_file_id UUID      NOT NULL,
            caption       TEXT,
            sort_order    INT       NOT NULL DEFAULT 0,
            created_at    TIMESTAMP DEFAULT NOW(),
            updated_at    TIMESTAMP DEFAULT NOW(),
            deleted_at    TIMESTAMP,
            created_by    {$userIdType},
            updated_by    {$userIdType},
            deleted_by    {$userIdType},
            FOREIGN KEY (gallery_id)    REFERENCES galleries(id)    ON DELETE CASCADE,
            FOREIGN KEY (media_file_id) REFERENCES media_files(id) ON DELETE CASCADE
        )");
        $db->execute();

        try {
            $db->query("SAVEPOINT sp_gallery_items_users_fk"); $db->execute();
            $db->query("ALTER TABLE gallery_items DROP CONSTRAINT IF EXISTS gallery_items_created_by_fkey"); $db->execute();
            $db->query("ALTER TABLE gallery_items ADD CONSTRAINT gallery_items_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE gallery_items DROP CONSTRAINT IF EXISTS gallery_items_updated_by_fkey"); $db->execute();
            $db->query("ALTER TABLE gallery_items ADD CONSTRAINT gallery_items_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE gallery_items DROP CONSTRAINT IF EXISTS gallery_items_deleted_by_fkey"); $db->execute();
            $db->query("ALTER TABLE gallery_items ADD CONSTRAINT gallery_items_deleted_by_fkey FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("RELEASE SAVEPOINT sp_gallery_items_users_fk"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_gallery_items_users_fk"); $db->execute(); } catch (\Exception) {}
        }

        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'gallery')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['View Gallery',    'gallery.view',    'Browse gallery albums'],
            ['Create Galleries','gallery.create',  'Create new albums'],
            ['Edit Galleries',  'gallery.edit',    'Edit album content and images'],
            ['Delete Galleries','gallery.delete',  'Delete albums'],
            ['Publish Galleries','gallery.publish','Change album status to published'],
        ] as [$name, $slug, $desc]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $name, ':slug' => $slug, ':desc' => $desc]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'gallery'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();

        // Auto-insert into primary navigation if Navigation module is installed
        try {
            $db->query("SAVEPOINT sp_gallery_nav"); $db->execute();
            $pm = \Core\Model::on($db, 'nav_menus')->select('id')->where('slug', 'primary')->get(1);
            if ($pm) {
                $exists = \Core\Model::on($db, 'nav_items')
                    ->where('menu_id', $pm['id'])->where('url', '/gallery')->get(1);
                if (!$exists) {
                    $order = (int) (\Core\Model::on($db, 'nav_items')
                        ->where('menu_id', $pm['id'])->whereRaw('parent_id IS NULL', [])->totalRows() ?: 0);
                    \Core\Model::on($db, 'nav_items')->save([
                        'menu_id'     => $pm['id'],
                        'type'        => 'module',
                        'label'       => 'Gallery',
                        'url'         => '/gallery',
                        'sort_order'  => $order,
                        'open_in_new' => false,
                    ]);
                }
            }
            $db->query("RELEASE SAVEPOINT sp_gallery_nav"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_gallery_nav"); $db->execute(); } catch (\Exception) {}
        }
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DROP TABLE IF EXISTS gallery_items CASCADE");
        $db->execute();
        $db->query("DROP TABLE IF EXISTS galleries CASCADE");
        $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'gallery')");
        $db->execute();
        $db->query("DELETE FROM permissions WHERE module = 'gallery'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $ca = 'App\Modules\Gallery\Controllers\Admin\GalleriesController';
        $ci = 'App\Modules\Gallery\Controllers\Admin\GalleryItemsController';
        $cf = 'App\Modules\Gallery\Controllers\Front\GalleryController';

        // Admin - album CRUD
        $router->get('/admin/gallery',                                  $ca, 'index');
        $router->get('/admin/gallery/form',                             $ca, 'createForm');
        $router->post('/admin/gallery/store',                           $ca, 'store');
        $router->get('/admin/gallery/([a-zA-Z0-9\-]+)/form',            $ca, 'editForm');
        $router->post('/admin/gallery/([a-zA-Z0-9\-]+)/update',         $ca, 'update');
        $router->post('/admin/gallery/([a-zA-Z0-9\-]+)/delete',         $ca, 'delete');

        // Admin - album items
        $router->get('/admin/gallery/([a-zA-Z0-9\-]+)/items',           $ci, 'index');
        $router->post('/admin/gallery/([a-zA-Z0-9\-]+)/items/add',      $ci, 'add');
        $router->post('/admin/gallery/([a-zA-Z0-9\-]+)/items/reorder',  $ci, 'reorder');
        $router->post('/admin/gallery/([a-zA-Z0-9\-]+)/items/([a-zA-Z0-9\-]+)/remove', $ci, 'remove');

        // Front-end
        $router->get('/gallery',                   $cf, 'index');
        $router->get('/gallery/([a-z0-9\-]+)',      $cf, 'album');
    }
}
