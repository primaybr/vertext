<?php

declare(strict_types=1);

namespace App\Modules\Videos;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
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
            created_by       UUID REFERENCES users(id) ON DELETE SET NULL,
            updated_by       UUID REFERENCES users(id) ON DELETE SET NULL
        )");
        $db->execute();

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
