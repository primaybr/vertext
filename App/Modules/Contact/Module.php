<?php

declare(strict_types=1);

namespace App\Modules\Contact;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS contact_submissions (
            id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            name         VARCHAR(255) NOT NULL,
            email        VARCHAR(255) NOT NULL,
            subject      VARCHAR(255),
            message      TEXT         NOT NULL,
            status       VARCHAR(20)  NOT NULL DEFAULT 'unread',
            ip_address   VARCHAR(45),
            submitted_at TIMESTAMP    DEFAULT NOW(),
            read_at      TIMESTAMP,
            replied_at   TIMESTAMP
        )");
        $db->execute();

        // Contact settings (lazy-seeded by ContactSettingsController)
        $settingsSql = "INSERT INTO settings (key, value, type, grp, label)
                        VALUES (:k, :v, :t, 'contact', :l)
                        ON CONFLICT (key) DO NOTHING";
        foreach ([
            ['contact_path',         'contact',  'text',    'Contact page path'],
            ['contact_admin_email',  '',          'text',    'Notification email address'],
            ['contact_auto_reply',   '0',         'boolean', 'Send auto-reply to visitors'],
            ['contact_auto_reply_msg', '',         'text',    'Auto-reply message body'],
        ] as [$k, $v, $t, $l]) {
            $db->query($settingsSql);
            $db->arrayBind([':k' => $k, ':v' => $v, ':t' => $t, ':l' => $l]);
            $db->execute();
        }

        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'contact')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['View Contact Inbox',     'contact.view',     'Read contact submissions'],
            ['Delete Submissions',     'contact.delete',   'Delete contact submissions'],
            ['Contact Settings',       'contact.settings', 'Configure contact form settings'],
        ] as [$name, $slug, $desc]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $name, ':slug' => $slug, ':desc' => $desc]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'contact'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DROP TABLE IF EXISTS contact_submissions CASCADE");
        $db->execute();

        $db->query("DELETE FROM settings WHERE grp = 'contact'");
        $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'contact')");
        $db->execute();

        $db->query("DELETE FROM permissions WHERE module = 'contact'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $ci = 'App\Modules\Contact\Controllers\Admin\ContactController';
        $cs = 'App\Modules\Contact\Controllers\Admin\ContactSettingsController';
        $cf = 'App\Modules\Contact\Controllers\Front\ContactFormController';

        // Admin — inbox
        $router->get('/admin/contact',                                     $ci, 'index');
        $router->get('/admin/contact/settings',                            $cs, 'index');
        $router->post('/admin/contact/settings/save',                      $cs, 'save');
        $router->get('/admin/contact/([a-zA-Z0-9\-]+)',                    $ci, 'view');
        $router->post('/admin/contact/([a-zA-Z0-9\-]+)/mark-read',        $ci, 'markRead');
        $router->post('/admin/contact/([a-zA-Z0-9\-]+)/mark-spam',        $ci, 'markSpam');
        $router->post('/admin/contact/([a-zA-Z0-9\-]+)/delete',           $ci, 'delete');

        // Front-end — the actual contact path is read from settings at runtime
        // We register a fixed route; ContactFormController reads the setting to validate
        $router->get('/contact',           $cf, 'show');
        $router->post('/contact',          $cf, 'submit');
    }
}
