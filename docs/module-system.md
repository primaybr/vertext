# Module System

Vertext's module system lets you ship self-contained features (blog, media, e-commerce, etc.) as installable units. Each module owns its database tables, routes, permissions, views, and admin UI.

## Module Structure

Every module lives in `App/Modules/{ModuleName}/`:

```text
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

### upgrade(Connection $db, string $fromVersion) - optional (0.1.1)

Not part of `ModuleInterface` - this is a duck-typed convention, checked with `method_exists()`, so existing modules need no changes. Add it only if a version bump needs to actually migrate something (new column, backfill, new table); if you don't define it, Module Manager still detects and offers the version bump, it just has nothing to run beyond updating the recorded version.

Module Manager compares the installed DB version against `module.json`'s `version` on every **Admin → Modules** load. When the manifest is newer, an "Update available" badge and button appear next to that module; clicking it runs `upgrade()` (if present) inside a transaction, then updates the stored version - same idempotency contract as `uninstall()`: safe to re-run if a previous attempt failed partway.

```php
public function upgrade(Connection $db, string $fromVersion): void
{
    // Only need to handle what actually changed since $fromVersion - keep this
    // idempotent (IF NOT EXISTS / ON CONFLICT) since retries call it again from scratch.
    $db->statement("ALTER TABLE my_items ADD COLUMN IF NOT EXISTS archived BOOLEAN DEFAULT FALSE");
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
    "category": "Content",
    "requires": { "vertext": ">=0.0.1" },
    "nav": {
        "label": "My Module",
        "icon": "pi-grid",
        "path": "/admin/my-module",
        "active": "my-module",
        "permission": "items.view",
        "subnav": [
            { "label": "Items",    "path": "/admin/my-module",          "active": "my-module/items",    "permission": "items.view" },
            { "label": "Settings", "path": "/admin/my-module/settings", "active": "my-module/settings", "permission": "items.settings" }
        ]
    }
}
```

| Field | Description |
| --- | --- |
| `slug` | Unique identifier used in routes, DB, and filesystem. Lowercase, hyphenated. |
| `category` | Groups the module in the Module Manager UI (e.g. Content, Media, Communication, Analytics, Navigation). Defaults to "Other" if omitted. |
| `nav.icon` | CSS class from the `pi-*` icon system. Always check existing icons in `styles.css` before choosing; add missing ones to grow the icon library. |
| `nav.active` | String matched against the current URL to highlight the nav item |
| `nav.permission` | If the user lacks this permission, the nav item is hidden |
| `nav.subnav` | Array of sub-navigation items with the same structure |

## Public Navigation Auto-Registration

Modules that expose public front-end routes can declare them via `nav_routes` in `module.json`. Two things happen automatically:

1. **On install** - the module's `install()` method reads the primary navigation menu and inserts a nav item for each declared route (if one with that URL does not already exist).
2. **Navigation builder** - the builder page lists all `nav_routes` from enabled modules as "Module Route" type items, so editors can add them to any menu without typing the path manually.

```json
{
    "name": "My Module",
    "slug": "my-module",
    "nav_routes": [
        { "label": "My Module", "path": "/my-module" }
    ]
}
```

The `nav_routes` array supports multiple entries (for modules with more than one public page). Each entry needs `path` (required) and `label` (used as the default menu label). Nav items inserted this way have `type = 'module'` in the `nav_items` table.

**If your module's path is user-configurable at runtime** (like Blog's base path setting), the static `path` in `module.json` is only a *default* - it is not re-read automatically. Both auto-registration paths (`NavHelper::buildFromModuleRoutes()`'s fallback and `NavigationController::syncModules()`/`builder()`) must resolve the live path instead of trusting the manifest; see `resolveModuleRoutePath()` in `NavigationController` and the `blog` special-case in `NavHelper` for the pattern to follow. Your module's settings-save handler is also responsible for calling something equivalent to `Blog\Module::syncNavItem()` to keep the existing nav item's URL (and visibility) in sync when the path changes.

**Install snippet** - add this at the end of `Module::install()`:

```php
try {
    $pm = \Core\Model::on($db, 'nav_menus')->select('id')->where('slug', 'primary')->get(1);
    if ($pm) {
        $exists = \Core\Model::on($db, 'nav_items')
            ->where('menu_id', $pm['id'])->where('url', '/my-module')->get(1);
        if (!$exists) {
            $order = (int) (\Core\Model::on($db, 'nav_items')
                ->where('menu_id', $pm['id'])->whereRaw('parent_id IS NULL', [])->totalRows() ?: 0);
            \Core\Model::on($db, 'nav_items')->save([
                'menu_id'     => $pm['id'],
                'type'        => 'module',
                'label'       => 'My Module',
                'url'         => '/my-module',
                'sort_order'  => $order,
                'open_in_new' => false,
            ]);
        }
    }
} catch (\Exception) {}
```

The entire block is wrapped in `try/catch` so it is safe when the Navigation module is not installed.

## Module Dependencies

Modules can declare dependencies on other modules via `requires.modules` in `module.json`. The install/uninstall lifecycle enforces these automatically.

```json
{
    "name": "My Newsletter",
    "slug": "newsletter",
    "requires": {
        "vertext": ">=0.0.5",
        "modules": ["media"]
    }
}
```

### Install-time enforcement

`ModuleManager::checkModuleDeps()` runs before `Module::install()`. If a required module is not installed **and enabled**, installation is blocked with a clear error:

> "Cannot install: required module(s) are not installed: \"media\""

The Module Manager UI shows a disabled Install button with a tooltip listing missing dependencies.

### Uninstall-time protection

`ModuleManager::checkDependents()` runs before `Module::uninstall()`. If any installed module declares a dependency on the module being removed, uninstallation is blocked:

> "Cannot uninstall: \"newsletter\" depends on this module. Uninstall it first."

### Dependency status in the UI

`ModuleManager::getDependencyInfo(array $slugs): array` returns per-slug install+enabled status. The Module Manager passes this to the card view to render green/red dependency badges on module cards.

### No circular dependency detection

The system does not check for circular dependencies (A → B → A). Module authors are responsible for avoiding cycles.

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

---

## Bundle Packages

Bundles let you install a curated set of modules in one click from the Module Manager's **Packages** tab.

### Bundle Directory

```text
App/Bundles/
    content-portal/
        bundle.json
    media-showcase/
        bundle.json
    business-site/
        bundle.json
    full-stack/
        bundle.json
```

### bundle.json Schema

```json
{
  "name":        "Content Portal",
  "slug":        "content-portal",
  "version":     "1.0.0",
  "description": "Full content publishing: blog, search, analytics, contact, navigation.",
  "icon":        "pi-globe",
  "category":    "Publishing",
  "modules": [
    { "slug": "blog",       "required": true  },
    { "slug": "search",     "required": true  },
    { "slug": "navigation", "required": true  },
    { "slug": "analytics",  "required": false },
    { "slug": "contact",    "required": false },
    { "slug": "sitemap",    "required": false }
  ]
}
```

`required: true` - module is pre-checked and locked in the install modal.
`required: false` - module is pre-checked but can be unchecked before installing.

### Install API

**`ModuleManager::getBundles(): array`**

Scans `App/Bundles/*/bundle.json`. Annotates each module entry with `installed` (bool) and `enabled` (bool). Computes `installed_count`, `total_count`, and `status` (`installed` / `partial` / `none`) per bundle.

**`ModuleManager::installBatch(array $slugs): array`**

1. Resolves install order using Kahn's topological sort over `requires.modules` from each manifest.
2. Installs each module in order; already-installed modules are skipped.
3. Returns a per-slug map: `['slug' => ['success' => bool, 'name' => str, 'message' => str, 'skipped' => bool]]`.

### Built-in Bundles

| Bundle | Slug | Modules |
| ------ | ---- | ------- |
| Content Portal | `content-portal` | Blog, Search, Navigation (req); Analytics, Contact, Sitemap (opt) |
| Media Showcase | `media-showcase` | Media, Gallery, Videos, Navigation (req); Analytics (opt) |
| Business Site | `business-site` | Pages, Contact, Navigation (req); Analytics, Sitemap (opt) |
| Full Stack | `full-stack` | All available add-on modules (all req) |

### Creating a Custom Bundle

1. Create `App/Bundles/my-bundle/bundle.json` following the schema above.
2. The bundle appears automatically in the Packages tab on next page load (no cache clear needed - bundles are scanned at runtime).
3. There is no CLI or API required to register a bundle.

---

## Module author notes added in 0.0.8

- **Database writes must go through `Core\Model`** (`save()` / `update()` / `delete()`), never
  raw `$connection->query()` + `execute()` for INSERT/UPDATE/DELETE. The PDO layer runs without
  autocommit and only `Model` wraps writes in a transaction commit - raw DML executes but is
  silently rolled back at the end of the request. Raw statements are fine for DDL
  (`CREATE TABLE IF NOT EXISTS`, `ALTER TABLE`).
- **Shortcodes** - `App\CMS\Shortcodes::render($html, $baseUrl)` resolves `[form slug="..."]`
  and `[newsletter_signup]` inside trusted content bodies. Front controllers that render
  admin-authored HTML can call it before passing the body to the view.
- **Page cache invalidation** - if your module renders public content, call
  `\App\CMS\PageCache::flushPages()` after any change that affects the public output.
- **Webhook registry** - add new event keys to `WebhookDispatcher::EVENTS` so they appear in the
  endpoint subscription UI.
