<?php

declare(strict_types=1);

namespace App\Modules\Members;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS site_users (
            id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            name         VARCHAR(120) NOT NULL,
            email        VARCHAR(180) UNIQUE NOT NULL,
            password     VARCHAR(255) NOT NULL,
            status       VARCHAR(20)  NOT NULL DEFAULT 'pending',
            verify_token UUID         DEFAULT gen_random_uuid(),
            verified_at  TIMESTAMP,
            last_login   TIMESTAMP,
            created_at   TIMESTAMP    DEFAULT NOW(),
            updated_at   TIMESTAMP    DEFAULT NOW(),
            deleted_at   TIMESTAMP,
            created_by   UUID,
            updated_by   UUID,
            deleted_by   UUID
        )");
        $db->execute();

        // Default setting (may already exist via install_settings)
        $db->query("INSERT INTO settings (key, value, type, grp, label)
                    VALUES ('members_require_verification', '1', 'select', 'members', 'Require email verification')
                    ON CONFLICT (key) DO NOTHING");
        $db->execute();

        // Permissions
        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'members')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['Members - View',   'members.view',   'View site member accounts'],
            ['Members - Manage', 'members.manage', 'Suspend, activate, and delete site members'],
        ] as [$n, $s, $d]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $n, ':slug' => $s, ':desc' => $d]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'members'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DROP TABLE IF EXISTS site_users CASCADE"); $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'members')");
        $db->execute();
        $db->query("DELETE FROM permissions WHERE module = 'members'");
        $db->execute();
        $db->query("DELETE FROM settings WHERE grp = 'members'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $admin = 'App\\Modules\\Members\\Controllers\\Admin\\MembersController';
        $front = 'App\\Modules\\Members\\Controllers\\Front\\AccountController';

        $id = '([a-zA-Z0-9\-]+)';

        // Admin
        $router->get( '/admin/members',                $admin, 'index');
        $router->post("/admin/members/{$id}/status",   $admin, 'setStatus');
        $router->post("/admin/members/{$id}/delete",   $admin, 'delete');
        $router->post("/admin/members/{$id}/resend-verification", $admin, 'resendVerification');

        // Public account routes
        $router->get( '/account/register', $front, 'registerForm');
        $router->post('/account/register', $front, 'register');
        $router->get( '/account/login',    $front, 'loginForm');
        $router->post('/account/login',    $front, 'login');
        $router->get( '/account/logout',   $front, 'logout');
        $router->get( '/account/verify',   $front, 'verify');
        $router->get( '/account',          $front, 'profile');
        $router->post('/account/update',   $front, 'update');
    }
}
