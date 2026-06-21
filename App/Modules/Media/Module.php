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
            id            SERIAL PRIMARY KEY,
            filename      VARCHAR(260) NOT NULL,
            original_name VARCHAR(260) NOT NULL,
            mime_type     VARCHAR(100) NOT NULL,
            size          INT          NOT NULL DEFAULT 0,
            width         SMALLINT,
            height        SMALLINT,
            alt_text      VARCHAR(255),
            caption       TEXT,
            uploaded_by   INT,
            created_at    TIMESTAMP    DEFAULT NOW(),
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
        )");
        $db->execute();

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
                "Options -ExecCGI\n" .
                "AddHandler cgi-script .php .php3 .php4 .php5 .phtml .pl .py .jsp .asp .htm .shtml .sh .cgi\n" .
                "RemoveHandler .php .phtml\n" .
                "php_flag engine off\n"
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

        $router->get('/admin/media',                  $c,  'index');
        $router->post('/admin/media/upload',          $c,  'upload');
        $router->get('/admin/media/(\d+)/edit-form',  $c,  'editForm');
        $router->post('/admin/media/(\d+)/update',    $c,  'update');
        $router->post('/admin/media/(\d+)/delete',    $c,  'delete');

        $router->get('/admin/media/picker',           $cp, 'index');
    }
}
