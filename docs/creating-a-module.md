# Creating a Module

This guide walks through creating a minimal "Portfolio" module from scratch.

## 1. Scaffold the Directory

```
App/Modules/Portfolio/
├── Module.php
├── module.json
├── Controllers/
│   └── Admin/
│       └── ProjectsController.php
└── Views/
    └── admin/
        └── projects/
            ├── index.php
            └── _form.php
```

## 2. Write module.json

```json
{
    "name": "Portfolio",
    "slug": "portfolio",
    "version": "1.0.0",
    "description": "Manage portfolio projects.",
    "author": "Your Name",
    "requires": { "vertext": ">=0.0.1" },
    "nav": {
        "label": "Portfolio",
        "icon": "pi-image",
        "path": "/admin/portfolio",
        "active": "portfolio",
        "permission": "projects.view",
        "subnav": [
            { "label": "Projects", "path": "/admin/portfolio", "active": "portfolio/projects", "permission": "projects.view" }
        ]
    }
}
```

## 3. Write Module.php

```php
<?php
namespace App\Modules\Portfolio;

use App\CMS\ModuleInterface;
use Core\Database\Connection;
use Core\Router;

class Module implements ModuleInterface
{
    public function install(Connection $db): void
    {
        $db->statement("CREATE TABLE IF NOT EXISTS portfolio_projects (
            id BIGSERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            description TEXT,
            url VARCHAR(500),
            status VARCHAR(20) DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )");

        $db->table('permissions')->insertBatch([
            ['name' => 'View Projects',   'slug' => 'projects.view',   'description' => '', 'module' => 'portfolio'],
            ['name' => 'Create Projects', 'slug' => 'projects.create', 'description' => '', 'module' => 'portfolio'],
            ['name' => 'Edit Projects',   'slug' => 'projects.edit',   'description' => '', 'module' => 'portfolio'],
            ['name' => 'Delete Projects', 'slug' => 'projects.delete', 'description' => '', 'module' => 'portfolio'],
        ]);
    }

    public function uninstall(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS portfolio_projects");
        $db->table('permissions')->where('module', 'portfolio')->delete()->run();
    }

    public function registerRoutes(Router $router): void
    {
        $c = 'App\Modules\Portfolio\Controllers\Admin\ProjectsController';
        $router->get( '/admin/portfolio',                $c, 'index');
        $router->get( '/admin/portfolio/form',           $c, 'createForm');
        $router->post('/admin/portfolio/store',          $c, 'store');
        $router->get( '/admin/portfolio/(\d+)/form',     $c, 'editForm');
        $router->post('/admin/portfolio/(\d+)/update',   $c, 'update');
        $router->post('/admin/portfolio/(\d+)/delete',   $c, 'delete');
    }
}
```

## 4. Write the Controller

```php
<?php
namespace App\Modules\Portfolio\Controllers\Admin;

use App\Controllers\Admin\BaseController;

class ProjectsController extends BaseController
{
    protected string $module = 'portfolio'; // enables module access check

    public function index(): void
    {
        $this->requirePermission('projects.view');

        $projects = $this->db
            ->table('portfolio_projects')
            ->select('*')
            ->orderBy('created_at', 'DESC')
            ->get();

        $this->adminRender(
            'modules/portfolio/admin/projects/index',
            ['projects' => $projects],
            'Projects',
            'portfolio'
        );
    }

    public function createForm(): void
    {
        $this->requirePermission('projects.create');
        echo $this->renderPartial('modules/portfolio/admin/projects/_form', ['project' => null]);
    }

    public function store(): void
    {
        $this->requirePermission('projects.create');
        $this->validateCsrf();

        $title = $this->input->post('title');
        $slug  = $this->input->post('slug');

        $this->db->table('portfolio_projects')->insert([
            'title' => $title,
            'slug'  => $slug,
        ])->run();

        $this->audit('project.created', 'project', null, ['title' => $title]);
        $this->flash('success', 'Project created.');
        $this->redirect('/admin/portfolio');
    }

    // editForm(), update(), delete() follow the same pattern
}
```

## 5. Write the Views

**Views/admin/projects/index.php**:
```html
<div class="page-header">
    <h1>Projects</h1>
    <a href="/admin/portfolio/form" class="btn btn-primary vtx-modal-trigger">Add Project</a>
</div>

<table class="table">
    <thead>
        <tr><th>Title</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
        <?php foreach ($projects as $p): ?>
        <tr>
            <td>{{ $p->title }}</td>
            <td>{{ $p->status }}</td>
            <td>
                <a href="/admin/portfolio/{{ $p->id }}/form" class="btn btn-sm vtx-modal-trigger">Edit</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
```

**Views/admin/projects/_form.php** (rendered in modal):
```html
<form method="POST" action="{{ $project ? '/admin/portfolio/'.$project->id.'/update' : '/admin/portfolio/store' }}">
    <?= csrf_field() ?>
    <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" class="form-control" value="{{ $project->title ?? '' }}" required>
    </div>
    <div class="form-group">
        <label>Slug</label>
        <input type="text" name="slug" class="form-control" value="{{ $project->slug ?? '' }}" required>
    </div>
    <button type="submit" class="btn btn-primary">Save</button>
</form>
```

## 5b. Add CSS/JS Assets (optional)

Never write inline `<style>`/`<script>` blocks in a view file. Instead, put CSS/JS in the module's
`Assets/` folder and declare it in `module.json`:

```
App/Modules/Portfolio/
├── Assets/
│   ├── portfolio.css        # front-end (if the module has public views)
│   └── portfolio-admin.js   # admin-only
```

```json
{
    "assets": {
        "css": ["portfolio.css"],
        "admin": { "js": ["portfolio-admin.js"] }
    }
}
```

- Top-level `assets.css` / `assets.js` are injected into the active front-end theme's `layout.php`
  (all bundled themes already call `ModuleLoader::frontAssets()` for this).
- `assets.admin.css` / `assets.admin.js` are injected into the admin layout via `ModuleLoader::assets()`.
- Paths are relative to the `Assets/` folder itself - never prefix with `Assets/`. The whole folder
  is deployed verbatim to `Public/assets/modules/{slug}/` on install/redeploy.
- If a script needs a value only PHP knows (a CSRF token, an ID, JSON data), put it in a `data-*`
  attribute on an element in the view and read it from `dataset` in the external script - `{{ }}`
  template placeholders and raw `<?php ?>` don't get processed once the code lives in a static
  `.js` file.
- If a view can be loaded into the admin CRUD modal (an AJAX-fetched `_form.php` partial), bind
  behavior via the `vtx:modal:loaded` event (dispatched on `document`, with `event.detail.body`
  set to the freshly-injected content) rather than `getElementById(...).addEventListener(...)` at
  script-load time - the modal's content doesn't exist yet when a globally-loaded script first runs.

## 6. Install the Module

1. Go to **Admin → Modules**.
2. Your "Portfolio" module will appear in the discovered list.
3. Click **Install**.

Tables are created, permissions seeded, views deployed, and the Portfolio nav item appears in the sidebar (for users with `projects.view`).

## Checklist

- [ ] `module.json` has a unique `slug`
- [ ] `Module.php` namespace matches `App\Modules\{ModuleName}`
- [ ] `install()` uses `insertBatch` for permissions and includes `module` column
- [ ] `uninstall()` drops tables and removes permissions by `module` slug
- [ ] `registerRoutes()` uses fully-qualified controller class names
- [ ] Controller extends `BaseController` and calls `$this->requirePermission()`
- [ ] Controller calls `$this->validateCsrf()` on all POST handlers
- [ ] Controller calls `$this->audit()` for state-changing operations
- [ ] Views are in `Views/admin/` inside the module folder (not directly in `App/Views/`)
- [ ] No inline `<style>`/`<script>` in views - CSS/JS lives in `Assets/` and is declared in `module.json`

## BaseController Methods You'll Use

| Method | Description |
|--------|-------------|
| `$this->requirePermission('slug')` | Abort 403 if user lacks permission |
| `$this->validateCsrf()` | Abort 419 if CSRF token is missing/invalid |
| `$this->adminRender($view, $data, $title, $activeMenu)` | Render with the admin layout |
| `$this->renderPartial($view, $data)` | Render without layout (for AJAX/modal responses) |
| `$this->flash('type', 'message')` | Set a flash message |
| `$this->redirect('/path')` | Redirect and exit |
| `$this->audit($action, $resource, $id, $details)` | Write an audit log entry |
| `$this->db` | Database connection instance |
| `$this->input` | Input helper (sanitized GET/POST) |
