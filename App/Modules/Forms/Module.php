<?php

declare(strict_types=1);

namespace App\Modules\Forms;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        // form_definitions: stores form schema (fields JSON) and settings
        $db->query("CREATE TABLE IF NOT EXISTS form_definitions (
            id          UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
            name        VARCHAR(255)  NOT NULL,
            slug        VARCHAR(255)  UNIQUE NOT NULL,
            description TEXT,
            fields      TEXT          NOT NULL DEFAULT '[]',
            settings    TEXT          NOT NULL DEFAULT '{}',
            status      VARCHAR(20)   NOT NULL DEFAULT 'active',
            created_at  TIMESTAMP     DEFAULT NOW(),
            updated_at  TIMESTAMP     DEFAULT NOW(),
            deleted_at  TIMESTAMP,
            created_by  UUID,
            updated_by  UUID,
            deleted_by  UUID
        )");
        $db->execute();

        // form_submissions: one row per frontend submission
        $db->query("CREATE TABLE IF NOT EXISTS form_submissions (
            id           UUID      PRIMARY KEY DEFAULT gen_random_uuid(),
            form_id      UUID      NOT NULL REFERENCES form_definitions(id) ON DELETE CASCADE,
            data         TEXT      NOT NULL DEFAULT '{}',
            ip_hash      VARCHAR(64),
            status       VARCHAR(20) NOT NULL DEFAULT 'unread',
            submitted_at TIMESTAMP  DEFAULT NOW(),
            created_at   TIMESTAMP  DEFAULT NOW(),
            updated_at   TIMESTAMP  DEFAULT NOW(),
            deleted_at   TIMESTAMP,
            created_by   UUID,
            updated_by   UUID,
            deleted_by   UUID
        )");
        $db->execute();

        // Permissions
        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'forms')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['Forms - View',            'forms.view',        'View forms and submissions'],
            ['Forms - Manage',          'forms.manage',      'Create, edit, and delete forms'],
            ['Forms - Export',          'forms.export',      'Export submissions to CSV'],
            ['Forms - Delete Submission','forms.delete_submission', 'Delete individual submissions'],
        ] as [$n, $s, $d]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $n, ':slug' => $s, ':desc' => $d]);
            $db->execute();
        }

        // Grant all to administrator
        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'forms'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DROP TABLE IF EXISTS form_submissions CASCADE"); $db->execute();
        $db->query("DROP TABLE IF EXISTS form_definitions CASCADE");  $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'forms')");
        $db->execute();
        $db->query("DELETE FROM permissions WHERE module = 'forms'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $admin = 'App\\Modules\\Forms\\Controllers\\Admin\\FormsController';
        $subs  = 'App\\Modules\\Forms\\Controllers\\Admin\\SubmissionsController';
        $front = 'App\\Modules\\Forms\\Controllers\\Front\\FormFrontController';

        $id   = '([a-zA-Z0-9\-]+)';
        $slug = '([a-z0-9\-]+)';

        // Admin - form CRUD (static routes first to avoid wildcard shadowing)
        $router->get( '/admin/forms',                              $admin, 'index');
        $router->get( '/admin/forms/create',                       $admin, 'createForm');
        $router->post('/admin/forms/store',                        $admin, 'store');
        $router->get( "/admin/forms/{$id}/edit",                   $admin, 'editForm');
        $router->post("/admin/forms/{$id}/update",                 $admin, 'update');
        $router->post("/admin/forms/{$id}/delete",                 $admin, 'delete');
        $router->get( "/admin/forms/{$id}/builder",                $admin, 'builder');
        $router->post("/admin/forms/{$id}/save-fields",            $admin, 'saveFields');

        // Admin - submissions (static route before wildcard)
        $router->get( '/admin/forms/all-submissions',              $subs, 'allSubmissions');
        $router->get( "/admin/forms/{$id}/submissions/export",     $subs, 'export');
        $router->get( "/admin/forms/{$id}/submissions/{$id}",      $subs, 'detail');
        $router->post("/admin/forms/{$id}/submissions/{$id}/delete", $subs, 'delete');
        $router->get( "/admin/forms/{$id}/submissions",            $subs, 'index');

        // Public front-end
        $router->get( "/forms/{$slug}",                            $front, 'show');
        $router->post("/forms/{$slug}/submit",                     $front, 'submit');
    }
}
