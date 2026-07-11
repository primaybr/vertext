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

        $this->seedSettings($db);
        LandingBlocksHelper::ensureSchema();
    }

    /**
     * Duck-typed upgrade hook (see ModuleInterface docblock) - v0.0.4 added the
     * landing_blocks_enabled toggle and the theme_landing_blocks table, neither
     * of which existed when this module was first installed on existing sites.
     */
    public function upgrade(\Core\Database\Connection $db, string $fromVersion): void
    {
        $this->seedSettings($db);
        LandingBlocksHelper::ensureSchema();
    }

    private function seedSettings(\Core\Database\Connection $db): void
    {
        $defaults = [
            ['primary_color',          '#1E3A5F', 'text',     'Primary Accent Color'],
            ['font_family',            'system',  'text',     'Font Family'],
            ['corner_style',           'subtle',  'text',     'Corner Style'],
            ['logo_url',               '',        'text',     'Logo URL'],
            ['custom_css',             '',        'textarea', 'Custom CSS'],
            ['landing_blocks_enabled', '0',       'bool',     'Use theme block-based landing page'],
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
        $router->get('/admin/theme-customizer',         $c, 'index');
        $router->post('/admin/theme-customizer/save',   $c, 'save');
        $router->get('/admin/theme-customizer/preview', $c, 'preview');
        $router->post('/admin/theme-customizer/landing-blocks/([a-z0-9\-]+)/save', $c, 'saveLandingBlocks');
        $router->post('/admin/theme-customizer/landing-blocks/([a-z0-9\-]+)/preview-stage', $c, 'previewStageLandingBlocks');
    }
}
