<?php

declare(strict_types=1);

namespace App\Modules\Videos;

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

        $db->query("CREATE TABLE IF NOT EXISTS videos (
            id               UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            title            VARCHAR(255) NOT NULL,
            slug             VARCHAR(255) NOT NULL UNIQUE,
            provider         VARCHAR(20)  NOT NULL DEFAULT 'youtube',
            embed_url        VARCHAR(500) NOT NULL,
            video_id         VARCHAR(100),
            thumbnail_path   VARCHAR(500),
            description      TEXT,
            status           VARCHAR(20)  NOT NULL DEFAULT 'draft',
            meta_title       VARCHAR(255),
            meta_description TEXT,
            sort_order       INT          NOT NULL DEFAULT 0,
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
                    $db->query("SAVEPOINT sp_fix_vid_{$col}"); $db->execute();
                    $cr = \Core\Model::on($db, 'information_schema.columns')
                        ->select('data_type')->where('table_name', 'videos')
                        ->where('column_name', $col)->where('table_schema', 'public')->get(1);
                    if ($cr && strtolower($cr['data_type'] ?? '') === 'uuid') {
                        $db->query("ALTER TABLE videos DROP COLUMN IF EXISTS {$col}"); $db->execute();
                        $db->query("ALTER TABLE videos ADD COLUMN {$col} BIGINT"); $db->execute();
                    }
                    $db->query("RELEASE SAVEPOINT sp_fix_vid_{$col}"); $db->execute();
                } catch (\Exception) {
                    try { $db->query("ROLLBACK TO SAVEPOINT sp_fix_vid_{$col}"); $db->execute(); } catch (\Exception) {}
                }
            }
        }

        // FKs added separately - survives when users.id type doesn't match UUID yet.
        // SAVEPOINT/ROLLBACK TO clears the aborted-transaction state on failure.
        try {
            $db->query("SAVEPOINT sp_videos_users_fk"); $db->execute();
            $db->query("ALTER TABLE videos DROP CONSTRAINT IF EXISTS videos_created_by_fkey"); $db->execute();
            $db->query("ALTER TABLE videos ADD CONSTRAINT videos_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE videos DROP CONSTRAINT IF EXISTS videos_updated_by_fkey"); $db->execute();
            $db->query("ALTER TABLE videos ADD CONSTRAINT videos_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE videos DROP CONSTRAINT IF EXISTS videos_deleted_by_fkey"); $db->execute();
            $db->query("ALTER TABLE videos ADD CONSTRAINT videos_deleted_by_fkey FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("RELEASE SAVEPOINT sp_videos_users_fk"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_videos_users_fk"); $db->execute(); } catch (\Exception) {}
        }

        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'videos')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['View Videos',   'videos.view',    'Browse video library'],
            ['Create Videos', 'videos.create',  'Add new videos'],
            ['Edit Videos',   'videos.edit',    'Edit existing videos'],
            ['Delete Videos', 'videos.delete',  'Delete videos'],
            ['Publish Videos','videos.publish', 'Change video status'],
        ] as [$name, $slug, $desc]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $name, ':slug' => $slug, ':desc' => $desc]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id FROM roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'videos'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();

        // Auto-insert into primary navigation if Navigation module is installed
        try {
            $db->query("SAVEPOINT sp_videos_nav"); $db->execute();
            $pm = \Core\Model::on($db, 'nav_menus')->select('id')->where('slug', 'primary')->get(1);
            if ($pm) {
                $exists = \Core\Model::on($db, 'nav_items')
                    ->where('menu_id', $pm['id'])->where('url', '/videos')->get(1);
                if (!$exists) {
                    $order = (int) (\Core\Model::on($db, 'nav_items')
                        ->where('menu_id', $pm['id'])->whereRaw('parent_id IS NULL', [])->totalRows() ?: 0);
                    \Core\Model::on($db, 'nav_items')->save([
                        'menu_id'     => $pm['id'],
                        'type'        => 'module',
                        'label'       => 'Videos',
                        'url'         => '/videos',
                        'sort_order'  => $order,
                        'open_in_new' => false,
                    ]);
                }
            }
            $db->query("RELEASE SAVEPOINT sp_videos_nav"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_videos_nav"); $db->execute(); } catch (\Exception) {}
        }
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $thumbDir = ROOT . 'Public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'video-thumbs';
        if (is_dir($thumbDir)) {
            foreach (glob($thumbDir . '/*') as $f) {
                @unlink($f);
            }
            @rmdir($thumbDir);
        }

        $db->query("DROP TABLE IF EXISTS videos CASCADE");
        $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'videos')");
        $db->execute();

        $db->query("DELETE FROM permissions WHERE module = 'videos'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $ca = 'App\Modules\Videos\Controllers\Admin\VideosController';
        $cf = 'App\Modules\Videos\Controllers\Front\VideoController';

        // Admin
        $router->get('/admin/videos',                           $ca, 'index');
        $router->get('/admin/videos/form',                      $ca, 'form');
        $router->post('/admin/videos/store',                    $ca, 'store');
        $router->get('/admin/videos/([a-zA-Z0-9\-]+)/form',    $ca, 'editForm');
        $router->post('/admin/videos/([a-zA-Z0-9\-]+)/update', $ca, 'update');
        $router->post('/admin/videos/([a-zA-Z0-9\-]+)/delete', $ca, 'delete');

        // Front
        $router->get('/videos',                         $cf, 'index');
        $router->get('/videos/([a-z0-9][a-z0-9\-]*)', $cf, 'single');
    }
}
