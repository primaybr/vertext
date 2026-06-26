<?php

declare(strict_types=1);

namespace App\Modules\Webhooks;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        $userIdType = 'UUID';
        try {
            $r = \Core\Model::on($db, 'information_schema.columns')
                ->select('data_type')->where('table_name', 'users')
                ->where('column_name', 'id')->where('table_schema', 'public')->get(1);
            if ($r && stripos($r['data_type'] ?? '', 'int') !== false) {
                $userIdType = 'BIGINT';
            }
        } catch (\Exception) {}

        $db->query("CREATE TABLE IF NOT EXISTS webhook_endpoints (
            id         UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            name       VARCHAR(255) NOT NULL,
            url        VARCHAR(2000) NOT NULL,
            secret     VARCHAR(255) NOT NULL DEFAULT '',
            events     TEXT         NOT NULL DEFAULT '[]',
            enabled    BOOLEAN      NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP    DEFAULT NOW(),
            updated_at TIMESTAMP    DEFAULT NOW(),
            created_by {$userIdType},
            updated_by {$userIdType}
        )");
        $db->execute();

        $db->query("CREATE TABLE IF NOT EXISTS webhook_logs (
            id            UUID      PRIMARY KEY DEFAULT gen_random_uuid(),
            endpoint_id   UUID      NOT NULL REFERENCES webhook_endpoints(id) ON DELETE CASCADE,
            event         VARCHAR(100) NOT NULL,
            payload       TEXT,
            response_code SMALLINT  NOT NULL DEFAULT 0,
            response_body TEXT,
            duration_ms   INT       NOT NULL DEFAULT 0,
            success       BOOLEAN   NOT NULL DEFAULT FALSE,
            created_at    TIMESTAMP DEFAULT NOW()
        )");
        $db->execute();

        $db->query("CREATE INDEX IF NOT EXISTS idx_webhook_logs_endpoint ON webhook_logs(endpoint_id, created_at DESC)");
        $db->execute();

        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'webhooks')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['View Webhooks',   'webhooks.view',   'View webhook endpoints and logs'],
            ['Manage Webhooks', 'webhooks.manage', 'Create, edit, and delete webhook endpoints'],
        ] as [$name, $slug, $desc]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $name, ':slug' => $slug, ':desc' => $desc]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'webhooks'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DROP TABLE IF EXISTS webhook_logs CASCADE");
        $db->execute();

        $db->query("DROP TABLE IF EXISTS webhook_endpoints CASCADE");
        $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'webhooks')");
        $db->execute();

        $db->query("DELETE FROM permissions WHERE module = 'webhooks'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $c = 'App\Modules\Webhooks\Controllers\Admin\WebhooksController';

        $router->get('/admin/webhooks',                                 $c, 'index');
        $router->get('/admin/webhooks/create',                          $c, 'create');
        $router->post('/admin/webhooks/store',                          $c, 'store');
        $router->get('/admin/webhooks/([a-zA-Z0-9\-]+)/edit',          $c, 'edit');
        $router->post('/admin/webhooks/([a-zA-Z0-9\-]+)/update',       $c, 'update');
        $router->post('/admin/webhooks/([a-zA-Z0-9\-]+)/delete',       $c, 'delete');
        $router->get('/admin/webhooks/([a-zA-Z0-9\-]+)/logs',          $c, 'logs');
        $router->post('/admin/webhooks/([a-zA-Z0-9\-]+)/test',         $c, 'test');
    }
}
