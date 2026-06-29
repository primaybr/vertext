<?php

declare(strict_types=1);

namespace App\Modules\Analytics;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS analytics_pageviews (
            id            UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            url_path      VARCHAR(500) NOT NULL,
            page_title    VARCHAR(255),
            referrer_host VARCHAR(255),
            ip_hash       VARCHAR(64),
            device_type   VARCHAR(10),
            viewed_at     TIMESTAMP    DEFAULT NOW()
        )");
        $db->execute();

        // Migration for existing installations
        $db->query("ALTER TABLE analytics_pageviews ADD COLUMN IF NOT EXISTS device_type VARCHAR(10)");
        $db->execute();

        $db->query("CREATE INDEX IF NOT EXISTS idx_analytics_viewed_at ON analytics_pageviews(viewed_at)");
        $db->execute();

        $db->query("CREATE INDEX IF NOT EXISTS idx_analytics_url_path ON analytics_pageviews(url_path)");
        $db->execute();

        // v0.0.4: search query tracking table
        $db->query("CREATE TABLE IF NOT EXISTS analytics_search_queries (
            id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            query        VARCHAR(500) NOT NULL,
            result_count SMALLINT     DEFAULT 0,
            ip_hash      VARCHAR(64),
            searched_at  TIMESTAMP    DEFAULT NOW()
        )");
        $db->execute();

        $db->query("CREATE INDEX IF NOT EXISTS idx_analytics_search_at ON analytics_search_queries(searched_at)");
        $db->execute();

        // analytics_enabled setting (grp: analytics)
        $db->query("INSERT INTO settings (key, value, type, grp, label)
                    VALUES ('analytics_enabled', '1', 'bool', 'analytics', 'Enable Analytics Tracking')
                    ON CONFLICT (key) DO NOTHING");
        $db->execute();

        // Permissions
        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'analytics')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['View Analytics',   'analytics.view',   'View analytics dashboard and reports'],
            ['Manage Analytics', 'analytics.manage', 'Configure analytics settings and clear data'],
        ] as [$name, $slug, $desc]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $name, ':slug' => $slug, ':desc' => $desc]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'analytics'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DROP TABLE IF EXISTS analytics_search_queries CASCADE");
        $db->execute();

        $db->query("DROP TABLE IF EXISTS analytics_pageviews CASCADE");
        $db->execute();

        $db->query("DELETE FROM settings WHERE grp = 'analytics'");
        $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'analytics')");
        $db->execute();

        $db->query("DELETE FROM permissions WHERE module = 'analytics'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $c = 'App\Modules\Analytics\Controllers\Admin\AnalyticsDashboardController';

        $router->get('/admin/analytics',        $c, 'index');
        $router->get('/admin/analytics/data',         $c, 'data');
        $router->get('/admin/analytics/export',       $c, 'export');
        $router->get('/admin/analytics/search-terms', $c, 'searchTerms');
    }
}
