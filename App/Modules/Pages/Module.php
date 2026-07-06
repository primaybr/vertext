<?php

declare(strict_types=1);

namespace App\Modules\Pages;

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
            lang             VARCHAR(10)  NOT NULL DEFAULT 'en',
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
                    $db->query("SAVEPOINT sp_fix_pages_{$col}"); $db->execute();
                    $cr = \Core\Model::on($db, 'information_schema.columns')
                        ->select('data_type')->where('table_name', 'pages')
                        ->where('column_name', $col)->where('table_schema', 'public')->get(1);
                    if ($cr && strtolower($cr['data_type'] ?? '') === 'uuid') {
                        $db->query("ALTER TABLE pages DROP COLUMN IF EXISTS {$col}"); $db->execute();
                        $db->query("ALTER TABLE pages ADD COLUMN {$col} BIGINT"); $db->execute();
                    }
                    $db->query("RELEASE SAVEPOINT sp_fix_pages_{$col}"); $db->execute();
                } catch (\Exception) {
                    try { $db->query("ROLLBACK TO SAVEPOINT sp_fix_pages_{$col}"); $db->execute(); } catch (\Exception) {}
                }
            }
        }

        // FKs added separately - survives when users.id type doesn't match UUID yet.
        // SAVEPOINT/ROLLBACK TO clears the aborted-transaction state on failure.
        try {
            $db->query("SAVEPOINT sp_pages_users_fk"); $db->execute();
            $db->query("ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_created_by_fkey"); $db->execute();
            $db->query("ALTER TABLE pages ADD CONSTRAINT pages_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_updated_by_fkey"); $db->execute();
            $db->query("ALTER TABLE pages ADD CONSTRAINT pages_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_deleted_by_fkey"); $db->execute();
            $db->query("ALTER TABLE pages ADD CONSTRAINT pages_deleted_by_fkey FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("RELEASE SAVEPOINT sp_pages_users_fk"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_pages_users_fk"); $db->execute(); } catch (\Exception) {}
        }

        // -- Content revisions (shared with Blog module) -----------------------
        $db->query("CREATE TABLE IF NOT EXISTS content_revisions (
            id               UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            content_type     VARCHAR(20)  NOT NULL,
            content_id       UUID         NOT NULL,
            revision_number  INT          NOT NULL DEFAULT 1,
            title            VARCHAR(255),
            body             TEXT,
            status           VARCHAR(20),
            slug             VARCHAR(255),
            excerpt          TEXT,
            meta_title       VARCHAR(255),
            meta_description TEXT,
            created_at       TIMESTAMP    DEFAULT NOW(),
            updated_at       TIMESTAMP    DEFAULT NOW(),
            created_by       UUID,
            updated_by       UUID
        )");
        $db->execute();

        $db->query("CREATE INDEX IF NOT EXISTS idx_content_revisions_content ON content_revisions (content_type, content_id)");
        $db->execute();

        // v0.0.2: per-page custom fields
        $db->query("CREATE TABLE IF NOT EXISTS page_meta (
            id         UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            page_id    UUID         NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
            meta_key   VARCHAR(100) NOT NULL,
            meta_value TEXT,
            created_at TIMESTAMP    DEFAULT NOW(),
            updated_at TIMESTAMP    DEFAULT NOW(),
            UNIQUE (page_id, meta_key)
        )");
        $db->execute();

        // Safely add new columns to existing tables (re-install safe)
        foreach ([
            "ALTER TABLE pages ADD COLUMN IF NOT EXISTS published_at TIMESTAMP",
            "ALTER TABLE pages ADD COLUMN IF NOT EXISTS expire_at    TIMESTAMP",
            "ALTER TABLE pages ADD COLUMN IF NOT EXISTS template VARCHAR(30) NOT NULL DEFAULT 'default'",
            "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NOW()",
            "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS updated_by UUID",
            "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS slug VARCHAR(255)",
            "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS excerpt TEXT",
            "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255)",
            "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS meta_description TEXT",
        ] as $ddl) {
            $db->query($ddl);
            $db->execute();
        }

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
        $router->get('/admin/pages/([a-zA-Z0-9\-]+)/revisions',  $ca, 'revisions');
        $router->post('/admin/pages/([a-zA-Z0-9\-]+)/revisions/([a-zA-Z0-9\-]+)/restore', $ca, 'restoreRevision');
        $router->get('/admin/pages/([a-zA-Z0-9\-]+)/revisions/([a-zA-Z0-9\-]+)/diff',    $ca, 'viewRevision');

        // Front-end catch-all is intentionally NOT registered here.
        // It is registered in Config/Routes.php AFTER all module routes so that
        // specific routes like /search and /videos are matched first.
    }
}
