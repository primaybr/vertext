# Vertext CLI

The `vertext` script provides developer scaffolding commands run from the project root.

```
php vertext <command> <name>
```

---

## make:module

Scaffold a new module with all required boilerplate files.

```
php vertext make:module <ModuleName>
```

**Rules for `<ModuleName>`:**
- Must be PascalCase (e.g. `Blog`, `ThemeCustomizer`, `MyPlugin`)
- At least 2 characters
- Must not already exist under `App/Modules/`

**Slug derivation:** PascalCase is converted to kebab-case automatically.
`ThemeCustomizer` → `theme-customizer`, `Blog` → `blog`

**Files generated:**

| File | Purpose |
|------|---------|
| `App/Modules/{Name}/module.json` | Module manifest with name, slug, nav, assets |
| `App/Modules/{Name}/Module.php` | Lifecycle class: `install()`, `uninstall()`, `registerRoutes()` |
| `App/Modules/{Name}/Controllers/Admin/{Name}Controller.php` | Admin controller stub extending `BaseController` |
| `App/Modules/{Name}/Views/admin/index.php` | Admin list view stub |
| `App/Modules/{Name}/Assets/{slug}.css` | Empty per-module CSS file |

**Example:**

```
$ php vertext make:module Faq

Module scaffolded: Faq (faq)
  created  App/Modules/Faq/module.json
  created  App/Modules/Faq/Module.php
  created  App/Modules/Faq/Controllers/Admin/FaqController.php
  created  App/Modules/Faq/Views/admin/index.php
  created  App/Modules/Faq/Assets/faq.css

Next steps:
  1. Edit module.json - fill in description, category, permissions
  2. Edit Module.php  - add install() tables and permissions
  3. Add routes to registerRoutes() in Module.php
  4. Install the module at /admin/modules
```

**After scaffolding**, the typical workflow is:

1. **`module.json`** - Fill in `description`, `category`, and any `nav_routes` or `requires.modules` entries.
2. **`Module.php`** - Uncomment and customise the `CREATE TABLE` block; add any settings seeds and permission grants.
3. **`Controllers/Admin/{Name}Controller.php`** - Replace the `TODO` view path with the actual module view; add action methods.
4. **`Views/admin/index.php`** - Build the real admin UI using `vtx-*` components and the existing panel/table patterns.
5. Install via the Module Manager at `/admin/modules` - this runs `Module::install()`.

---

## make:bundle

Create a bundle manifest skeleton.

```
php vertext make:bundle <bundle-slug>
```

**Rules for `<bundle-slug>`:**
- Must be lowercase kebab-case (e.g. `marketing-suite`, `events-portal`)
- At least 2 characters, starting with a letter
- Must not already exist under `App/Bundles/`

**Files generated:**

| File | Purpose |
|------|---------|
| `App/Bundles/{slug}/bundle.json` | Bundle manifest with name, slug, icon, category, modules array |

**Example:**

```
$ php vertext make:bundle marketing-suite

Bundle scaffolded: marketing-suite
  created  App/Bundles/marketing-suite/bundle.json

Next steps:
  1. Edit bundle.json - fill in name, description, and modules array
  2. Each module entry needs: { "slug": "blog", "required": true }
  3. The bundle will appear in the Module Manager Packages tab automatically
```

**Generated `bundle.json` structure:**

```json
{
    "name": "Marketing Suite",
    "slug": "marketing-suite",
    "version": "1.0.0",
    "description": "",
    "icon": "pi-grid",
    "category": "General",
    "modules": []
}
```

Fill in the `modules` array. Each entry has:
- `"slug"` - the module's slug (must match an existing module under `App/Modules/`)
- `"required"` - `true` locks the checkbox in the install modal; `false` allows the user to deselect it

**Example filled modules array:**

```json
"modules": [
    { "slug": "newsletter",   "required": true  },
    { "slug": "forms",        "required": true  },
    { "slug": "analytics",    "required": false },
    { "slug": "webhooks",     "required": false },
    { "slug": "contact",      "required": false }
]
```

---

## Bundle status logic

The Module Manager derives each bundle's status from the installed state of its listed modules:

| Status | Condition |
|--------|-----------|
| **Installed** | All modules in the bundle are installed |
| **Partial** | At least one module is installed, but at least one required module is missing |
| **Not Installed** | No modules in the bundle are installed |

---

## Built-in bundles

| Bundle | Slug | Required modules |
|--------|------|-----------------|
| Content Portal | `content-portal` | Blog, Search, Navigation |
| Media Showcase | `media-showcase` | Media, Gallery, Videos, Navigation |
| Business Site | `business-site` | Pages, Contact, Navigation |
| Full Stack | `full-stack` | All available add-on modules |
| Marketing Suite | `marketing-suite` | Newsletter, Forms Builder |
| Events Portal | `events-portal` | Events, Contact, Navigation |
