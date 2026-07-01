<?php

declare(strict_types=1);

namespace App\Modules\Newsletter;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        // Subscribers
        $db->query("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id         UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            email      VARCHAR(255) UNIQUE NOT NULL,
            name       VARCHAR(120),
            status     VARCHAR(20)  NOT NULL DEFAULT 'active',
            token      UUID         NOT NULL DEFAULT gen_random_uuid(),
            source     VARCHAR(100) DEFAULT 'direct',
            created_at TIMESTAMP    DEFAULT NOW(),
            updated_at TIMESTAMP    DEFAULT NOW(),
            deleted_at TIMESTAMP,
            created_by UUID,
            updated_by UUID,
            deleted_by UUID
        )");
        $db->execute();

        // Campaigns
        $db->query("CREATE TABLE IF NOT EXISTS newsletter_campaigns (
            id            UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            subject       VARCHAR(255) NOT NULL,
            preview_text  VARCHAR(255),
            body_html     TEXT,
            body_text     TEXT,
            status        VARCHAR(20)  NOT NULL DEFAULT 'draft',
            sent_count    INT          NOT NULL DEFAULT 0,
            scheduled_at  TIMESTAMP,
            sent_at       TIMESTAMP,
            created_at    TIMESTAMP    DEFAULT NOW(),
            updated_at    TIMESTAMP    DEFAULT NOW(),
            deleted_at    TIMESTAMP,
            created_by    UUID,
            updated_by    UUID,
            deleted_by    UUID
        )");
        $db->execute();

        // Settings seed (double opt-in off by default)
        $db->query("INSERT INTO settings (key, value, grp) VALUES ('newsletter_double_optin', '0', 'newsletter') ON CONFLICT (key) DO NOTHING");
        $db->execute();
        $db->query("INSERT INTO settings (key, value, grp) VALUES ('newsletter_from_name', '', 'newsletter') ON CONFLICT (key) DO NOTHING");
        $db->execute();
        $db->query("INSERT INTO settings (key, value, grp) VALUES ('newsletter_from_email', '', 'newsletter') ON CONFLICT (key) DO NOTHING");
        $db->execute();
        $db->query("INSERT INTO settings (key, value, grp) VALUES ('newsletter_confirm_subject', 'Please confirm your subscription', 'newsletter') ON CONFLICT (key) DO NOTHING");
        $db->execute();

        // Permissions
        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'newsletter')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['Newsletter - View',    'newsletter.view',    'View subscribers and campaigns'],
            ['Newsletter - Manage',  'newsletter.manage',  'Create and send campaigns'],
            ['Newsletter - Export',  'newsletter.export',  'Export subscriber list to CSV'],
        ] as [$n, $s, $d]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $n, ':slug' => $s, ':desc' => $d]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'newsletter'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DROP TABLE IF EXISTS newsletter_campaigns CASCADE");   $db->execute();
        $db->query("DROP TABLE IF EXISTS newsletter_subscribers CASCADE"); $db->execute();
        $db->query("DELETE FROM settings WHERE grp = 'newsletter'");      $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'newsletter')");
        $db->execute();
        $db->query("DELETE FROM permissions WHERE module = 'newsletter'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $sub  = 'App\\Modules\\Newsletter\\Controllers\\Admin\\SubscribersController';
        $camp = 'App\\Modules\\Newsletter\\Controllers\\Admin\\CampaignsController';
        $set  = 'App\\Modules\\Newsletter\\Controllers\\Admin\\NewsletterSettingsController';
        $pub  = 'App\\Modules\\Newsletter\\Controllers\\Front\\NewsletterPublicController';

        $id   = '([a-zA-Z0-9\-]+)';

        // Subscribers
        $router->get( '/admin/newsletter',                        $sub, 'index');
        $router->get( '/admin/newsletter/subscribers',            $sub, 'index');
        $router->post('/admin/newsletter/subscribers/store',      $sub, 'store');
        $router->post("/admin/newsletter/subscribers/{$id}/delete", $sub, 'delete');
        $router->post('/admin/newsletter/subscribers/import',     $sub, 'import');
        $router->get( '/admin/newsletter/subscribers/export',     $sub, 'export');

        // Campaigns
        $router->get( '/admin/newsletter/campaigns',                  $camp, 'index');
        $router->get( '/admin/newsletter/campaigns/create',           $camp, 'createForm');
        $router->post('/admin/newsletter/campaigns/store',            $camp, 'store');
        $router->get( "/admin/newsletter/campaigns/{$id}/edit",       $camp, 'editForm');
        $router->post("/admin/newsletter/campaigns/{$id}/update",     $camp, 'update');
        $router->post("/admin/newsletter/campaigns/{$id}/delete",     $camp, 'delete');
        $router->post("/admin/newsletter/campaigns/{$id}/send",       $camp, 'send');
        $router->post("/admin/newsletter/campaigns/{$id}/test-send",  $camp, 'testSend');

        // Settings
        $router->get( '/admin/newsletter/settings',              $set, 'index');
        $router->post('/admin/newsletter/settings/save',         $set, 'save');

        // Public
        $router->get( '/newsletter/unsubscribe',  $pub, 'unsubscribe');
        $router->get( '/newsletter/confirm',       $pub, 'confirm');
        $router->post('/newsletter/subscribe',     $pub, 'subscribe');
    }
}
