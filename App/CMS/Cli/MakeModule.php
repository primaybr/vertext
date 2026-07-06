<?php

declare(strict_types=1);

namespace App\CMS\Cli;

/**
 * Scaffold a new Vertext module.
 *
 * Usage: php vertext make:module <ModuleName>
 *
 * Generates:
 *   App/Modules/{Name}/Module.php
 *   App/Modules/{Name}/module.json
 *   App/Modules/{Name}/Controllers/Admin/{Name}Controller.php
 *   App/Modules/{Name}/Views/admin/index.php
 *   App/Modules/{Name}/Assets/{slug}.css
 */
final class MakeModule
{
    public static function run(string $name): never
    {
        // -- Validate ----------------------------------------------------------
        if (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $name)) {
            self::error(
                "Module name must be PascalCase and at least 2 characters.\n" .
                "  Valid:   Blog, ThemeCustomizer, MyPlugin\n" .
                "  Invalid: blog, my-plugin, _Foo"
            );
        }

        $slug    = self::toSlug($name);
        $destDir = BASE_PATH . "/App/Modules/{$name}";

        if (is_dir($destDir)) {
            self::error("Module already exists at App/Modules/{$name}/");
        }

        // -- Create directories ------------------------------------------------
        $dirs = [
            $destDir,
            "{$destDir}/Controllers/Admin",
            "{$destDir}/Controllers/Front",
            "{$destDir}/Views/admin",
            "{$destDir}/Views/front",
            "{$destDir}/Assets",
        ];
        foreach ($dirs as $dir) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                self::error("Failed to create directory: {$dir}");
            }
        }

        // -- Write files -------------------------------------------------------
        self::write("{$destDir}/module.json",           self::moduleJson($name, $slug));
        self::write("{$destDir}/Module.php",            self::modulePHP($name, $slug));
        self::write("{$destDir}/Controllers/Admin/{$name}Controller.php", self::controller($name, $slug));
        self::write("{$destDir}/Views/admin/index.php", self::adminIndexView($name, $slug));
        self::write("{$destDir}/Assets/{$slug}.css",    self::css($slug));

        // -- Report ------------------------------------------------------------
        self::out("\033[32mModule scaffolded:\033[0m {$name} ({$slug})\n");
        $files = [
            "App/Modules/{$name}/module.json",
            "App/Modules/{$name}/Module.php",
            "App/Modules/{$name}/Controllers/Admin/{$name}Controller.php",
            "App/Modules/{$name}/Views/admin/index.php",
            "App/Modules/{$name}/Assets/{$slug}.css",
        ];
        foreach ($files as $f) {
            self::out("  \033[36mcreated\033[0m  {$f}");
        }
        self::out('');
        self::out("Next steps:");
        self::out("  1. Edit \033[33mmodule.json\033[0m - fill in description, category, permissions");
        self::out("  2. Edit \033[33mModule.php\033[0m  - add install() tables and permissions");
        self::out("  3. Add routes to \033[33mregisterRoutes()\033[0m in Module.php");
        self::out("  4. Install the module at \033[33m/admin/modules\033[0m");
        self::out('');
        exit(0);
    }

    // -- Templates -------------------------------------------------------------

    private static function moduleJson(string $name, string $slug): string
    {
        $label = self::titleCase($name);
        return json_encode([
            'name'        => $label,
            'slug'        => $slug,
            'version'     => '0.0.1',
            'description' => '',
            'author'      => 'Vertext',
            'category'    => 'General',
            'requires'    => ['vertext' => '>=0.0.7'],
            'nav_routes'  => [],
            'nav'         => [
                'label'      => $label,
                'icon'       => 'pi-grid',
                'path'       => "/admin/{$slug}",
                'active'     => $slug,
                'permission' => "{$slug}.view",
            ],
            'assets'      => [
                'css' => ["Assets/{$slug}.css"],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private static function modulePHP(string $name, string $slug): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Modules\\{$name};

use App\\CMS\\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\\Core\\Database\\Connection \$db): void
    {
        // TODO: create tables
        // \$db->query("CREATE TABLE IF NOT EXISTS {$slug}_items (
        //     id         UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
        //     name       VARCHAR(255) NOT NULL,
        //     created_at TIMESTAMP   DEFAULT NOW(),
        //     updated_at TIMESTAMP   DEFAULT NOW(),
        //     deleted_at TIMESTAMP,
        //     created_by UUID,
        //     updated_by UUID,
        //     deleted_by UUID
        // )");
        // \$db->execute();

        // Insert permissions
        \$permSql = "INSERT INTO permissions (name, slug, description, module)
                     VALUES (:name, :slug, :desc, '{$slug}')
                     ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['{$name} - View', '{$slug}.view',   'View {$slug} content'],
            ['{$name} - Manage', '{$slug}.manage', 'Create and edit {$slug} content'],
        ] as [\$n, \$s, \$d]) {
            \$db->query(\$permSql);
            \$db->arrayBind([':name' => \$n, ':slug' => \$s, ':desc' => \$d]);
            \$db->execute();
        }

        // Grant all permissions to administrator
        \$db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = '{$slug}'
             ON CONFLICT DO NOTHING"
        );
        \$db->execute();
    }

    public function uninstall(\\Core\\Database\\Connection \$db): void
    {
        // TODO: drop tables
        // \$db->query("DROP TABLE IF EXISTS {$slug}_items CASCADE"); \$db->execute();

        \$db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = '{$slug}')");
        \$db->execute();
        \$db->query("DELETE FROM permissions WHERE module = '{$slug}'");
        \$db->execute();
    }

    public function registerRoutes(\\Core\\Router \$router): void
    {
        \$ctrl = 'App\\\\Modules\\\\{$name}\\\\Controllers\\\\Admin\\\\{$name}Controller';

        \$router->get('/admin/{$slug}', \$ctrl, 'index');
        // TODO: add more routes
    }
}
PHP;
    }

    private static function controller(string $name, string $slug): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Modules\\{$name}\\Controllers\\Admin;

use App\\Controllers\\Admin\\BaseController;
use App\\CMS\\Auth;

/**
 * {$name} admin controller.
 *
 * GET /admin/{$slug} → index()
 */
class {$name}Controller extends BaseController
{
    protected string \$module = '{$slug}';

    public function __construct()
    {
        parent::__construct();
    }

    /** GET /admin/{$slug} */
    public function index(): void
    {
        \$this->requirePermission('{$slug}.view');

        \$this->adminRender('admin/profile/index', [
            // TODO: pass data from DB
        ], '{$name}', '{$slug}');
    }
}
PHP;
    }

    private static function adminIndexView(string $name, string $slug): string
    {
        $label = self::titleCase($name);
        return <<<HTML
<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-grid me-2 text-primary"></i>{$label}</h1>
    <p class="vtx-page-desc">Manage {$label} content.</p>
  </div>
</div>

<div class="vtx-panel">
  <div class="vtx-panel-body">
    <p style="color:var(--ps-text-muted);">No content yet. Edit this view at <code>App/Modules/{$name}/Views/admin/index.php</code>.</p>
  </div>
</div>
HTML;
    }

    private static function css(string $slug): string
    {
        return "/* {$slug} module styles */\n";
    }

    // -- Helpers ---------------------------------------------------------------

    /** PascalCase → kebab-case: ThemeCustomizer → theme-customizer */
    private static function toSlug(string $name): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));
    }

    /** PascalCase → spaced Title Case: ThemeCustomizer → Theme Customizer */
    private static function titleCase(string $name): string
    {
        return trim(preg_replace('/([A-Z])/', ' $1', $name));
    }

    private static function write(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            self::error("Failed to write file: {$path}");
        }
    }

    private static function out(string $msg): void
    {
        echo $msg . "\n";
    }

    private static function error(string $msg): never
    {
        echo "\033[31mError:\033[0m {$msg}\n";
        exit(1);
    }
}
