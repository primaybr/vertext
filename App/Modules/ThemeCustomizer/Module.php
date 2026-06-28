<?php

declare(strict_types=1);

namespace App\Modules\ThemeCustomizer;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    private const GRP = 'theme-customizer';

    public function install(\Core\Database\Connection $db): void
    {
        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'theme-customizer')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['Manage Theme Customizer', 'theme-customizer.manage', 'Edit site appearance settings'],
        ] as [$name, $slug, $desc]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $name, ':slug' => $slug, ':desc' => $desc]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'theme-customizer'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();

        // Seed default settings (idempotent)
        $defaults = [
            ['primary_color', '#2563EB', 'text',     'Primary Accent Color'],
            ['font_family',   'system',  'text',     'Font Family'],
            ['logo_url',      '',        'text',     'Logo URL'],
            ['custom_css',    '',        'textarea', 'Custom CSS'],
        ];
        foreach ($defaults as [$key, $val, $type, $label]) {
            $exists = \Core\Model::on($db, 'settings')
                ->select('id')->where('key', $key)->where('grp', self::GRP)->get(1);
            if (!$exists) {
                \Core\Model::on($db, 'settings')->save([
                    'key'   => $key,
                    'value' => $val,
                    'grp'   => self::GRP,
                    'type'  => $type,
                    'label' => $label,
                ]);
            }
        }
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'theme-customizer')");
        $db->execute();

        $db->query("DELETE FROM permissions WHERE module = 'theme-customizer'");
        $db->execute();

        // Settings kept intentionally for re-install context preservation
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $c = 'App\Modules\ThemeCustomizer\Controllers\Admin\ThemeCustomizerController';
        $router->get('/admin/theme-customizer',       $c, 'index');
        $router->post('/admin/theme-customizer/save', $c, 'save');
    }
}
