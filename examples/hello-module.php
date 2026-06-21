<?php
/**
 * Example: Minimal "Hello" Module
 *
 * This file shows the minimum viable structure for a Vertext module.
 * Copy this pattern into App/Modules/Hello/ and adapt as needed.
 *
 * Files needed:
 *   App/Modules/Hello/Module.php       ← this file's contents
 *   App/Modules/Hello/module.json      ← manifest shown below
 *   App/Modules/Hello/Controllers/Admin/HelloController.php
 *   App/Modules/Hello/Views/admin/index.php
 */

// ── module.json ──────────────────────────────────────────────────────────────
$moduleJson = <<<'JSON'
{
    "name": "Hello",
    "slug": "hello",
    "version": "1.0.0",
    "description": "A minimal hello-world module.",
    "author": "Your Name",
    "requires": { "vertext": ">=0.0.1" },
    "nav": {
        "label": "Hello",
        "icon": "pi-star",
        "path": "/admin/hello",
        "active": "hello",
        "permission": "hello.view"
    }
}
JSON;

// ── Module.php ────────────────────────────────────────────────────────────────
/*
namespace App\Modules\Hello;

use App\CMS\ModuleInterface;
use Core\Database\Connection;
use Core\Router;

class Module implements ModuleInterface
{
    public function install(Connection $db): void
    {
        // Seed the single permission this module needs
        $db->table('permissions')->insertBatch([
            [
                'name'        => 'View Hello',
                'slug'        => 'hello.view',
                'description' => 'Access the Hello admin page',
                'module'      => 'hello',
            ],
        ]);
    }

    public function uninstall(Connection $db): void
    {
        $db->table('permissions')->where('module', 'hello')->delete()->run();
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/hello', 'App\Modules\Hello\Controllers\Admin\HelloController', 'index');
    }
}
*/

// ── HelloController.php ───────────────────────────────────────────────────────
/*
namespace App\Modules\Hello\Controllers\Admin;

use App\Controllers\Admin\BaseController;

class HelloController extends BaseController
{
    protected string $module = 'hello';

    public function index(): void
    {
        $this->requirePermission('hello.view');

        $this->adminRender(
            'modules/hello/admin/index',
            ['message' => 'Hello from Vertext!'],
            'Hello Module',
            'hello'
        );
    }
}
*/

// ── Views/admin/index.php ─────────────────────────────────────────────────────
/*
<div class="page-header">
    <h1>Hello Module</h1>
</div>
<div class="card">
    <div class="card-body">
        <p>{{ $message }}</p>
    </div>
</div>
*/
