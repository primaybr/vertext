# Vertext CMS

**Modular PHP CMS built for developers.**

Vertext is a lightweight, extensible content management system written in PHP 8.2+. It provides a professional admin panel, role-based access control, and a clean module system so you can ship exactly the features you need — nothing more.

---

## Features

- **Modular architecture** — install, enable, disable, and uninstall modules without touching core code
- **Role-based access control** — fine-grained permissions per resource and action; custom roles
- **Admin panel** — responsive sidebar UI with dark/light theme, flash messages, and audit trail
- **Blog module** — posts, categories, tags, comment moderation, Quill editor, SEO fields, public frontend
- **Media module** — file upload library with drag-and-drop, grid browser, and reusable picker modal
- **Setup wizard** — guided 5-step installation with DB connection testing
- **Security** — CSRF protection, bcrypt passwords, session hardening, login rate limiting, audit logs
- **PostgreSQL** — full native support via PDO with connection pooling and query caching
- **vtx-* component library** — chart, datatable, rich-text editor, media picker, tag input, upload, and more

---

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.2 or higher |
| PostgreSQL | 13 or higher |
| PHP extensions | PDO, pdo_pgsql, json, mbstring, fileinfo |
| Web server | Apache (mod_rewrite) or Nginx |

---

## Quick Start

```bash
# 1. Clone the repository
git clone https://github.com/your-org/vertext.git
cd vertext

# 2. Point your web server document root to: /path/to/vertext/Public/

# 3. Open the setup wizard in your browser
#    http://your-domain/setup
#    Follow the 5 steps to configure the database and create an admin user.

# 4. Log in
#    http://your-domain/admin/login
```

---

## Directory Structure

```
vertext/
├── App/                  # Application code (your work lives here)
│   ├── CMS/              # Auth, Installer, ModuleManager helpers
│   ├── Controllers/      # Admin, Setup, Web controllers
│   ├── Models/           # Database models
│   ├── Modules/          # Installable modules (Blog, Media, ...)
│   └── Views/            # Templates
├── Core/                 # Framework internals (do not modify)
├── Config/               # Routes, Database, Paths config
├── Public/               # Web root — only this is web-accessible
│   ├── assets/           # CSS, JS, images
│   └── index.php         # Front controller
├── Storage/              # Runtime config (gitignored: db.php, app.php)
├── Migrations/           # Database schema files
├── Cache/                # Query and template cache
└── Logs/                 # Application logs
```

---

## Module System

Vertext modules are self-contained units that own their database tables, routes, permissions, and views.

```
App/Modules/YourModule/
├── Module.php          # Implements ModuleInterface (install, uninstall, registerRoutes)
├── module.json         # Manifest: name, slug, version, nav links, permissions
├── Controllers/
└── Views/
```

Install a module: **Admin → Modules → Install**

See [docs/module-system.md](docs/module-system.md) and [docs/creating-a-module.md](docs/creating-a-module.md) for the full guide.

---

## Documentation

| Document | Description |
|----------|-------------|
| [Getting Started](docs/getting-started.md) | Installation, requirements, first steps |
| [Configuration](docs/configuration.md) | Config files, settings, trusted proxies |
| [Admin Guide](docs/admin-guide.md) | Users, roles, permissions, settings, modules |
| [Security](docs/security.md) | CSRF, RBAC, sessions, audit logs, uploads |
| [Module System](docs/module-system.md) | How modules work, lifecycle, ModuleLoader |
| [Creating a Module](docs/creating-a-module.md) | Step-by-step module scaffold guide |
| [ORM Guide](docs/orm-guide.md) | Query builder, CRUD, pagination, raw queries |
| [Template System](docs/template-system.md) | `{{var}}`, `{!! raw !!}`, adminRender, flash |
| [Blog Module](docs/blog-module.md) | Routes, permissions, posts, comments |
| [Media Module](docs/media-module.md) | Upload, grid, picker modal |
| [JS Components](docs/javascript-components.md) | vtx-chart, vtx-editor, vtx-media-picker, etc. |

---

## Examples

| File | Description |
|------|-------------|
| [examples/hello-module.php](examples/hello-module.php) | Minimal module scaffold |
| [examples/using-auth.php](examples/using-auth.php) | Auth::check, Auth::can, requirePermission |
| [examples/custom-settings.php](examples/custom-settings.php) | Reading/writing site settings |
| [examples/orm-queries.php](examples/orm-queries.php) | Common ORM query patterns |
| [examples/media-picker-integration.php](examples/media-picker-integration.php) | Adding a media picker to a module form |

---

## Security

Found a security issue? Please report it privately before disclosing publicly.

See [docs/security.md](docs/security.md) for a full description of Vertext's security model.

---

## License

MIT License — see `LICENSE` file for details.
