<?php

declare(strict_types=1);

namespace App\Modules\Media;

use App\CMS\ModuleInterface;

/**
 * Media Module — lifecycle class
 *
 * Provides a general-purpose media library.
 * Other modules can open /admin/media/picker in the CRUD modal and receive
 * a selected file via window.__vtxMediaPickerCallback(url, id).
 */
class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS media_files (
            id             UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            filename       VARCHAR(260) NOT NULL,
            original_name  VARCHAR(260) NOT NULL,
            mime_type      VARCHAR(100) NOT NULL,
            size           INT          NOT NULL DEFAULT 0,
            width          SMALLINT,
            height         SMALLINT,
            alt_text       VARCHAR(255),
            caption        TEXT,
            thumbnail_path VARCHAR(500),
            resized        BOOLEAN      DEFAULT FALSE,
            uploaded_by    UUID,
            created_at     TIMESTAMP    DEFAULT NOW(),
            updated_at     TIMESTAMP    DEFAULT NOW(),
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
        )");
        $db->execute();

        // Upgrade columns for existing installations
        foreach ([
            "ALTER TABLE media_files ADD COLUMN IF NOT EXISTS thumbnail_path VARCHAR(500)",
            "ALTER TABLE media_files ADD COLUMN IF NOT EXISTS resized BOOLEAN DEFAULT FALSE",
        ] as $alterSql) {
            try {
                $db->query($alterSql);
                $db->execute();
            } catch (\Exception) {
                // Column already exists — safe to ignore
            }
        }

        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'media')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['View Media',   'media.view',   'Browse and view media files'],
            ['Upload Media', 'media.upload', 'Upload new media files'],
            ['Edit Media',   'media.edit',   'Edit media file metadata (alt text, caption)'],
            ['Delete Media', 'media.delete', 'Delete media files'],
        ] as [$name, $slug, $desc]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $name, ':slug' => $slug, ':desc' => $desc]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'media'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();

        // Create uploads directory and protect against PHP execution
        $uploadsDir = ROOT . 'Public' . DS . 'uploads' . DS . 'media';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $htaccess = $uploadsDir . DS . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess,
                "Options -ExecCGI -Indexes\n" .
                "RemoveHandler .php .php3 .php4 .php5 .phtml .pl .py .jsp .asp .sh .cgi\n" .
                "RemoveType .php .phtml\n\n" .
                "<FilesMatch \"\\.(php[0-9]?|phtml|pl|py|jsp|asp|sh|cgi)$\">\n" .
                "    Require all denied\n" .
                "</FilesMatch>\n"
            );
        }
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DROP TABLE IF EXISTS media_files CASCADE");
        $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'media')");
        $db->execute();

        $db->query("DELETE FROM permissions WHERE module = 'media'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $c  = 'App\Modules\Media\Controllers\Admin\MediaController';
        $cp = 'App\Modules\Media\Controllers\Admin\MediaPickerController';

        $router->get('/admin/media',                               $c,  'index');
        $router->post('/admin/media/upload',                       $c,  'upload');
        $router->post('/admin/media/regen-thumbnails',             $c,  'regenThumbnails');
        $router->get('/admin/media/([a-zA-Z0-9\-]+)/edit-form',   $c,  'editForm');
        $router->post('/admin/media/([a-zA-Z0-9\-]+)/update',     $c,  'update');
        $router->post('/admin/media/([a-zA-Z0-9\-]+)/delete',     $c,  'delete');

        $router->get('/admin/media/picker',           $cp, 'index');
    }
}
