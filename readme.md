# Vertext CMS

![Version](https://img.shields.io/badge/version-0.0.9c--alpha-blue)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)
![License](https://img.shields.io/badge/license-MIT-green)

**Modular PHP CMS built for developers.**

Vertext is a lightweight, extensible content management system written in PHP 8.2+. It provides a professional admin panel, role-based access control, and a clean module system so you can ship exactly the features you need - nothing more.

> **Built on [Phuse 1.2.8b](https://github.com/primaybr/phuse)** - Vertext is powered by the Phuse framework, which provides the ORM, router, session manager, input helpers, validator, and core utilities.

---

## Features

- **Modular architecture** - install, enable, disable, and uninstall modules without touching core code; modules declare inter-dependencies enforced at install/uninstall time
- **Role-based access control** - fine-grained permissions per resource and action; custom roles
- **Admin panel** - responsive sidebar UI with dark/light theme, flash messages, and audit trail
- **Public theme system** - `ThemeEngine` wraps all front-end module views in a shared theme layout; 4 bundled themes (Default, Clean, Field, Frame) support dark/light mode with OS preference detection and a toggle button
- **Email notifications** - built-in Mailer (PHP mail + SMTP); contact notifications, comment approvals, auto-replies, welcome emails
- **Slug auto-generation** - `vtx-slug` component generates URL slugs from titles across all module forms
- **Navigation module** - build front-end menus with custom links, page slugs, module routes, and dropdown support; modules auto-register nav items on install; renders automatically in theme layouts via `NavHelper`
- **Admin profile page** - any logged-in user can update their own name, email, and password at `/admin/profile`
- **Blog module** - posts, categories, tags, comment moderation, Quill editor, SEO fields, dynamic URL routing, RSS feed, scheduled/expired publishing, content revisions
- **Analytics module** - privacy-friendly page-view tracking; unique visitors; device breakdown; date range filter; period comparison; CSV export
- **Search module** - full-text ILIKE search across indexed pages and posts; `GET /search`; admin reindex dashboard
- **Theme Customizer module** - accent color, font family, logo, and freeform custom CSS overrides injected into all theme layouts
- **Media module** - file upload with image resizing + thumbnail generation; grid browser; bulk delete; reusable picker modal
- **Pages module** - static page CRUD with front-end rendering via ThemeEngine; scheduled/expired publishing; content revisions
- **Sitemap module** - automatic `/sitemap.xml` from published pages and blog posts; extensible via `SitemapProvider` interface
- **Webhooks module** - outgoing webhooks with HMAC-SHA256 signed payloads; admin UI for endpoint management and delivery logs
- **Gallery module** - photo albums backed by Media library; drag-to-reorder; CSS lightbox
- **Contact Form module** - public contact form, admin inbox, email notifications, auto-reply
- **Videos module** - YouTube/Vimeo embed management; poster thumbnail caching; lazy iframe player
- **Theme Manager** - dedicated admin section (`/admin/themes`) to switch the active front-end theme; promoted from a settings tab to a first-class system module
- **Setup wizard** - guided 5-step installation with DB connection testing
- **Security** - CSRF protection, Argon2id passwords (auto-upgrade from bcrypt), password reset, active-session management, session hardening, login rate limiting, audit logs
- **PostgreSQL** - full native support via PDO with connection pooling and query caching
- **vtx-* component library** - chart, datatable, rich-text editor, media picker, tag input, upload, slug, and more
- **Two-factor authentication (2FA)** - TOTP (RFC 6238) via the TwoFactor module; QR code setup; backup codes; trusted-device cookie (30 days)
- **Forms Builder module** - drag-to-reorder field builder; conditional logic; multi-step forms; file uploads; email notifications; math challenge + optional reCAPTCHA v3; `[form]` shortcode; CSV export
- **Newsletter module** - subscribers with double opt-in; audience segments; scheduled campaigns; open/click tracking; welcome email; `[newsletter_signup]` shortcode; CSV import/export
- **Events module** - event listings with per-attendee RSVP, capacity + waiting list, ticket types, recurring events, iCal export, Canvas calendar sidebar, and attendee admin with CSV export
- **Bundle packages** - install groups of related modules in one click; custom bundle builder in the Module Manager
- **Module Marketplace** - install a module from any HTTPS URL via the Module Manager; SSRF-safe, SHA-256 verified
- **Module scaffold CLI** - `php vertext make:module Foo` and `php vertext make:bundle Foo` generate boilerplate files
- **i18n** - `__()` translation helper; locale path-prefix routing (`/id/...`); per-content Language field; admin Translations editor with locale scaffolding; `I18n::date()` with `IntlDateFormatter` support
- **Members module** - front-end visitor accounts with email verification, member profiles, and admin moderation
- **REST API** - read-only JSON API (`/api/v1/posts`, `/pages`, `/events`) with Bearer API keys and rate limiting
- **Performance** - opt-in full-page cache for public renders, nav fragment cache, version-fingerprinted assets, lazy-loaded images

---

## Requirements

| Requirement | Version |
| ----------- | ------- |
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

```text
vertext/
├── App/                  # Application code (your work lives here)
│   ├── CMS/              # Auth, Installer, ModuleManager, NavHelper, I18n
│   ├── Controllers/      # Admin, Setup, Web controllers
│   ├── Lang/             # Translation files (App/Lang/{locale}/{file}.php)
│   ├── Mail/             # Mailer, MailMessage, MailTemplate + Templates/
│   ├── Models/           # Database models
│   ├── Modules/          # Installable modules (Blog, Media, Pages, ...)
│   ├── Theme/            # ThemeEngine - wraps front-end views in a theme layout
│   ├── Themes/           # Theme source files (default/, clean/, field/, frame/)
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

```text
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
| Blog | 0.0.6 | Content | Posts, categories, tags, threaded comments, post series with prev/next nav, related posts, reading list (localStorage), dynamic URL routing, RSS feed, scheduled/expired publishing, content revisions |
| Pages | 0.0.3 | Content | Static page CRUD, page templates (default/full-width/sidebar/landing), custom fields, scheduled/expired publishing, content revisions |
| Media | 0.0.4 | Media | File uploads, folders, browser image editor (crop/rotate/flip), thumbnails, bulk move/delete |
| Gallery | 0.0.1 | Media | Photo albums with lightbox, backed by Media library |
| Videos | 0.0.1 | Media | YouTube/Vimeo embed management with poster thumbnails |
| Contact | 0.0.1 | Communication | Public contact form with admin inbox and email notifications |
| Navigation | 0.0.2 | Navigation | Front-end menu builder; Module Route type; auto-registration of module nav routes on install |
| Analytics | 0.0.4 | Analytics | Privacy-friendly page-view tracking; unique visitors; device breakdown; date range filter; search term tracking; CSV + JSON export |
| Search | 0.0.1 | Content | Full-text search across pages and posts; `GET /search`; admin reindex button |
| Theme Customizer | 0.0.1 | Design | Accent color, font, logo, and custom CSS overrides for the public theme |
| Sitemap | 0.0.1 | SEO | Automatic `/sitemap.xml` from published pages and blog posts |
| Webhooks | 0.0.1 | Integration | Outgoing webhooks with HMAC-SHA256 signing and delivery logs |
| Forms | 0.0.2 | Communication | Form builder with conditional logic, multi-step pages, file uploads, email notifications, anti-spam (honeypot/math/reCAPTCHA v3), shortcode embed, CSV export |
| Newsletter | 0.0.2 | Communication | Subscribers, segments, scheduled campaigns, open/click tracking, welcome email, signup shortcode, CSV import/export |
| Events | 0.0.2 | Community | Per-attendee RSVP with capacity + waiting list, ticket types, recurring events, iCal export, attendee admin |
| Members | 0.0.1 | Community | Front-end visitor accounts: registration, email verification, profiles, admin moderation |
| Two-Factor Auth | 0.0.1 | Security | TOTP authenticator app support; backup codes; trusted-device cookie |

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
| [Blog Module](docs/blog-module.md) | Posts, categories, tags, comments, RSS feed |
| [Media Module](docs/media-module.md) | Upload, image resizing, thumbnails, bulk delete, picker modal |
| [Analytics Module](docs/analytics-module.md) | Page-view tracking, date range filter, period comparison, CSV export |
| [Sitemap Module](docs/sitemap-module.md) | Auto XML sitemap, SitemapProvider interface |
| [Webhooks Module](docs/webhooks-module.md) | Outgoing webhooks, HMAC signing, delivery logs |
| [Pages Module](docs/pages-module.md) | Static page CRUD and public rendering |
| [Gallery Module](docs/gallery-module.md) | Photo albums, lightbox, drag-to-reorder |
| [Contact Module](docs/contact-module.md) | Public form, admin inbox, email notifications |
| [Videos Module](docs/videos-module.md) | YouTube/Vimeo embeds, poster thumbnails |
| [CLI](docs/cli.md) | Module scaffold and bundle creation commands |
| [2FA Module](docs/2fa.md) | TOTP setup, backup codes, trusted devices |
| [Forms Module](docs/forms.md) | Form builder, submissions, honeypot, CSV export |
| [Newsletter Module](docs/newsletter.md) | Subscriber management, double opt-in, HTML campaigns |
| [Events Module](docs/events.md) | Event listings, RSVP, Canvas calendar |
| [i18n Guide](docs/i18n.md) | Translation files, `__()` helper, locale routing, Translations admin |
| [Members Module](docs/members.md) | Front-end accounts, email verification, SiteAuth |
| [REST API](docs/api.md) | Endpoints, API keys, rate limits, response envelope |
| [Caching](docs/caching.md) | Full-page cache, fragment cache, asset fingerprinting |

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
| [examples/dispatching-webhooks.php](examples/dispatching-webhooks.php) | Dispatching webhook events from a module controller |
| [examples/sitemap-provider.php](examples/sitemap-provider.php) | Implementing SitemapProvider to contribute URLs to /sitemap.xml |

---

## Security

Found a security issue? Please report it privately before disclosing publicly.

See [docs/security.md](docs/security.md) for a full description of Vertext's security model.

---

## License

MIT License - see `LICENSE` file for details.
