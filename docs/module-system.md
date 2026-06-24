# Module System

Vertext's module system lets you ship self-contained features (blog, media, e-commerce, etc.) as installable units. Each module owns its database tables, routes, permissions, views, and admin UI.

## Module Structure

Every module lives in `App/Modules/{ModuleName}/`:

```
App/Modules/Blog/
├── Module.php          # ModuleInterface implementation
├── module.json         # Manifest (name, nav, permissions)
├── Controllers/
│   ├── Admin/
│   │   ├── PostsController.php
│   │   └── ...
│   └── Front/
│       └── BlogController.php
└── Views/
    ├── admin/
    │   └── posts/
    │       ├── index.php
    │       └── _form.php
    └── front/
        ├── index.php
        └── post.php
```

## ModuleInterface Contract

Every module must implement `App\CMS\ModuleInterface`:

```php
interface ModuleInterface {
    public function install(Connection $db): void;
    public function uninstall(Connection $db): void;
    public function registerRoutes(Router $router): void;
}
```

### install(Connection $db)

Called when admin installs the module. Create tables, seed permissions. **Always wrap in a transaction** - the manager handles this automatically.

```php
public function install(Connection $db): void
{
    $db->statement("CREATE TABLE IF NOT EXISTS my_items (
        id BIGSERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT NOW()
    )");

    // Seed module permissions
    $db->table('permissions')->insertBatch([
        ['name' => 'View Items',   'slug' => 'items.view',   'module' => 'my-module'],
        ['name' => 'Create Items', 'slug' => 'items.create', 'module' => 'my-module'],
    ]);
}
```

### uninstall(Connection $db)

Called when admin uninstalls the module. Drop tables and remove permissions. Should be the reverse of `install()`.

```php
public function uninstall(Connection $db): void
{
    $db->statement("DROP TABLE IF EXISTS my_items");
    $db->table('permissions')->where('module', 'my-module')->delete()->run();
}
```

### registerRoutes(Router $router)

Called on every request (when the module is enabled). Register all admin and public routes the module needs.

```php
public function registerRoutes(Router $router): void
{
    // Admin routes
    $adm = 'App\Modules\MyModule\Controllers\Admin\ItemsController';
    $router->get( '/admin/my-module',      $adm, 'index');
    $router->get( '/admin/my-module/form', $adm, 'createForm');
    $router->post('/admin/my-module/store',$adm, 'store');

    // Public routes
    $pub = 'App\Modules\MyModule\Controllers\Front\ItemsController';
    $router->get('/items',               $pub, 'index');
    $router->get('/items/([a-z0-9\-]+)', $pub, 'show');
}
```

## module.json Manifest

```json
{
    "name": "My Module",
    "slug": "my-module",
    "version": "1.0.0",
    "description": "Does something useful.",
    "author": "Your Name",
    "requires": { "vertext": ">=0.0.1" },
    "nav": {
        "label": "My Module",
        "icon": "pi-grid",
        "path": "/admin/my-module",
        "active": "my-module",
        "permission": "items.view",
        "subnav": [
            { "label": "Items",    "path": "/admin/my-module",         "active": "my-module/items",    "permission": "items.view" },
            { "label": "Settings", "path": "/admin/my-module/settings", "active": "my-module/settings", "permission": "items.settings" }
        ]
    }
}
```

| Field | Description |
|-------|-------------|
| `slug` | Unique identifier used in routes, DB, and filesystem. Lowercase, hyphenated. |
| `nav.icon` | CSS class from the `pi-*` icon system (e.g. `pi-file-edit`, `pi-grid`) |
| `nav.active` | String matched against the current URL to highlight the nav item |
| `nav.permission` | If the user lacks this permission, the nav item is hidden |
| `nav.subnav` | Array of sub-navigation items with the same structure |

## Module Lifecycle

### Discovery

`ModuleManager::discover()` scans `App/Modules/*/module.json`. Found modules are listed in the Module Manager but are **not** installed yet.

### Installation

When admin clicks **Install**:
1. `Module.php` is loaded and `install($db)` is called inside a DB transaction.
2. A record is inserted into the `modules` table.
3. Views are deployed from `App/Modules/MyModule/Views/` to `App/Views/modules/my-module/`.
4. Route cache is cleared.

### Route Loading

On every request (after install), `ModuleManager::loadRoutes($router)`:
1. Queries `modules` table for enabled modules.
2. Instantiates each module's `Module.php`.
3. Calls `registerRoutes($router)`.

This is cached per-request via `ModuleLoader`.

### Enable / Disable

Toggles `status` in the `modules` table. Disabled modules have their routes and nav items hidden. No data is lost.

### View Deployment

Module views are copied to `App/Views/modules/{slug}/` during install. This is what templates render. To update views after editing source files, use **Sync Views** in the Module Manager. **Never edit `App/Views/modules/` directly** - those files are owned by the install lifecycle.

### Uninstallation

When admin clicks **Uninstall**:
1. `Module::uninstall($db)` is called (drop tables, remove permissions).
2. Module record is deleted from the `modules` table.
3. Deployed views in `App/Views/modules/{slug}/` are deleted.
4. Route cache is cleared.

## ModuleLoader Helper

`App\CMS\ModuleLoader` provides static access to runtime module state:

```php
use App\CMS\ModuleLoader;

// Check if a module is currently enabled
if (ModuleLoader::isEnabled('blog')) { ... }

// Get all enabled module slugs
$enabled = ModuleLoader::getEnabled(); // ['blog', 'media']

// Get nav items for the sidebar
$navItems = ModuleLoader::navItems();

// Refresh the per-request cache (call after toggle/install)
ModuleLoader::refresh();
```

## Using Module-Specific Views

From a module controller:

```php
// With base admin layout
return $this->adminRender('modules/my-module/admin/items/index', $data, 'Items', 'my-module');

// Partial (no layout - for AJAX modal responses)
return $this->renderPartial('modules/my-module/admin/items/_form', $data);
```
