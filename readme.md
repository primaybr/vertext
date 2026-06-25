# Vertext CMS

![Version](https://img.shields.io/badge/version-0.0.3--alpha-blue)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)
![License](https://img.shields.io/badge/license-MIT-green)

**Modular PHP CMS built for developers.**

Vertext is a lightweight, extensible content management system written in PHP 8.2+. It provides a professional admin panel, role-based access control, and a clean module system so you can ship exactly the features you need - nothing more.

> **Built on [Phuse 1.2.4](https://github.com/primaybr/phuse)** - Vertext is powered by the Phuse framework, which provides the ORM, router, session manager, input helpers, validator, and core utilities.

---

## Features

- **Modular architecture** - install, enable, disable, and uninstall modules without touching core code; modules declare inter-dependencies enforced at install/uninstall time
- **Role-based access control** - fine-grained permissions per resource and action; custom roles
- **Admin panel** - responsive sidebar UI with dark/light theme, flash messages, and audit trail
- **Public theme system** - `ThemeEngine` wraps all front-end module views in a shared theme layout; both bundled themes (Default, Clean) support dark/light mode with OS preference detection and a toggle button
- **Email notifications** - built-in Mailer (PHP mail + SMTP); contact notifications, comment approvals, auto-replies, welcome emails
- **Slug auto-generation** - `vtx-slug` component generates URL slugs from titles across all module forms
- **Navigation module** - build front-end menus with custom links, page slugs, and dropdown support; renders automatically in theme layouts via `NavHelper`
- **Blog module** - posts, categories, tags, comment moderation, Quill editor, SEO fields, dynamic URL routing, public frontend
- **Analytics module** - privacy-friendly page-view tracking with bot filter, IP hashing, and admin dashboard (views, top pages, referrers, 30-day chart)
- **Media module** - file upload with image resizing + 400x400 thumbnail generation; grid browser; reusable picker modal
- **Pages module** - static page CRUD with front-end rendering via ThemeEngine
- **Gallery module** - photo albums backed by Media library; drag-to-reorder; CSS lightbox
- **Contact Form module** - public contact form, admin inbox, email notifications, auto-reply
- **Videos module** - YouTube/Vimeo embed management; poster thumbnail caching; lazy iframe player
- **Theme Manager** - dedicated admin section (`/admin/themes`) to switch the active front-end theme; promoted from a settings tab to a first-class system module
- **Setup wizard** - guided 5-step installation with DB connection testing
- **Security** - CSRF protection, bcrypt passwords, session hardening, login rate limiting, audit logs
- **PostgreSQL** - full native support via PDO with connection pooling and query caching
- **vtx-* component library** - chart, datatable, rich-text editor, media picker, tag input, upload, slug, and more

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
git clone https://github.com/primaybr/vertext.git
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
│   ├── CMS/              # Auth, Installer, ModuleManager, NavHelper
│   ├── Controllers/      # Admin, Setup, Web controllers
│   ├── Mail/             # Mailer, MailMessage, MailTemplate + Templates/
│   ├── Models/           # Database models
│   ├── Modules/          # Installable modules (Blog, Media, Pages, ...)
│   ├── Theme/            # ThemeEngine - wraps front-end views in a theme layout
│   ├── Themes/           # Theme source files (default/, clean/)
│   └── Views/            # Templates (admin + deployed module views)
├── Core/                 # Framework internals - do not modify
├── Config/               # Routes, Database, Paths config
├── Public/               # Web root - only this is web-accessible
│   ├── assets/           # CSS, JS, images (styles.css = Phuse framework base)
│   ├── themes/           # Deployed theme assets (auto-generated)
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
├── module.json         # Manifest: name, slug, version, category, nav links, permissions
├── Controllers/
├── Views/
└── Assets/             # Per-module CSS/JS, deployed to Public/assets/modules/{slug}/
```

Install a module: **Admin → Module Manager → Install**

### Bundled Modules

| Module | Version | Category | Description |
| ------ | ------- | -------- | ----------- |
| Blog | 0.0.3 | Content | Posts, categories, tags, comments, dynamic URL routing |
| Pages | 0.0.1 | Content | Static page CRUD with public rendering |
| Media | 0.0.2 | Media | File uploads with image resizing and thumbnail generation |
| Gallery | 0.0.1 | Media | Photo albums with lightbox, backed by Media library |
| Videos | 0.0.1 | Media | YouTube/Vimeo embed management with poster thumbnails |
| Contact | 0.0.1 | Communication | Public contact form with admin inbox and email notifications |
| Navigation | 0.0.1 | Navigation | Front-end menu builder with NavHelper theme integration |
| Analytics | 0.0.1 | Analytics | Privacy-friendly page-view tracking with admin dashboard |

See [docs/module-system.md](docs/module-system.md) and [docs/creating-a-module.md](docs/creating-a-module.md) for the full guide.

---

## Documentation

| Document | Description |
| -------- | ----------- |
| [Getting Started](docs/getting-started.md) | Installation, requirements, first steps |
| [Configuration](docs/configuration.md) | Config files, settings, mail keys, trusted proxies |
| [Admin Guide](docs/admin-guide.md) | Users, roles, permissions, settings, modules, themes |
| [Security](docs/security.md) | CSRF, RBAC, sessions, audit logs, uploads |
| [Module System](docs/module-system.md) | How modules work, lifecycle, ModuleLoader |
| [Creating a Module](docs/creating-a-module.md) | Step-by-step module scaffold guide |
| [ORM Guide](docs/orm-guide.md) | Query builder, CRUD, pagination, raw queries |
| [Template System](docs/template-system.md) | `{{var}}`, `{!! raw !!}`, adminRender, flash |
| [Theme System](docs/theme-system.md) | ThemeEngine, dark/light mode, custom themes, asset deployment |
| [Mail System](docs/mail-system.md) | Mailer, MailMessage, templates, SMTP config |
| [JS Components](docs/javascript-components.md) | vtx-chart, vtx-editor, vtx-slug, vtx-media-picker, etc. |
| [Blog Module](docs/blog-module.md) | Routes, permissions, posts, comments |
| [Media Module](docs/media-module.md) | Upload, image resizing, thumbnails, picker modal |
| [Pages Module](docs/pages-module.md) | Static page CRUD and public rendering |
| [Gallery Module](docs/gallery-module.md) | Photo albums, lightbox, drag-to-reorder |
| [Contact Module](docs/contact-module.md) | Public form, admin inbox, email notifications |
| [Videos Module](docs/videos-module.md) | YouTube/Vimeo embeds, poster thumbnails |

---

## Examples

| File | Description |
| ---- | ----------- |
| [examples/hello-module.php](examples/hello-module.php) | Minimal module scaffold with module.json and Module.php |
| [examples/using-auth.php](examples/using-auth.php) | Auth::check, Auth::can, requirePermission |
| [examples/custom-settings.php](examples/custom-settings.php) | Reading/writing site settings |
| [examples/orm-queries.php](examples/orm-queries.php) | Common ORM query patterns |
| [examples/media-picker-integration.php](examples/media-picker-integration.php) | Adding a media picker to a module form |
| [examples/sending-mail.php](examples/sending-mail.php) | Sending email with Mailer and MailTemplate |
| [examples/using-themes.php](examples/using-themes.php) | ThemeEngine, dark/light mode, custom theme layout |

---

## Security

Found a security issue? Please report it privately before disclosing publicly.

See [docs/security.md](docs/security.md) for a full description of Vertext's security model.

---

## License

MIT License - see `LICENSE` file for details.
