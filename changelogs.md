# Vertext CMS — Changelog

All notable changes to Vertext CMS are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [0.0.2-alpha] — 2026-06-23

Built on **Phuse 1.2.4** — all ORM, routing, session, and utility primitives come from the Phuse framework layer.

### App — Mailer (`App/Mail/`)

- **`Mailer`** — `Mailer::make()->send(MailMessage)`: PHP `mail()` driver and SMTP driver (native `fsockopen`, no external deps); reads config from `site_settings` at runtime
- **`MailMessage`** — fluent builder: `to()`, `subject()`, `htmlBody()`, `textBody()`, `from()`
- **`MailerConfig`** — maps `mail_driver`, `mail_host`, `mail_port`, `mail_username`, `mail_password`, `mail_encryption`, `mail_from_address`, `mail_from_name` settings
- **`MailTemplate`** — `render(string $name, array $vars)` renders HTML email templates from `App/Mail/Templates/`
- **Email templates** — `base.php` (shared layout), `comment_approved.php`, `comment_pending.php`, `welcome.php`, `contact_notification.php`, `contact_autoreply.php`
- **Admin Mail Settings** — new "Mail" tab in Admin → Site Settings to configure all mail options

### Core — Slug Component (`Public/assets/js/components/vtx-slug.js`)

- Watches `[data-vtx-slug-source]` → writes `[data-vtx-slug-target]` with debounced 300ms slug generation
- Mirrors `Str::slug()` logic: lowercase, non-alphanumeric → hyphen, collapse, trim
- Stops auto-updating once user manually edits the slug field; "Reset" link re-enables it
- Loaded on demand via `Vtx.load(['slug'], fn)`

### Media Module (v0.0.2)

- **Image resizing on upload** — originals wider than 1920 px are downscaled in-place; `resized` flag stored in DB
- **Thumbnail generation** — 400×400 cover-crop thumbnail (`thumb_` prefix) generated for every uploaded image via `Core\Utilities\Image\Image`; stored as `thumbnail_path`
- **Regenerate Thumbnails** — bulk action in the media grid processes up to 50 files per request; shows remaining count badge
- **Grid thumbnails** — media grid and picker modal now display the 400 px thumbnail instead of the full original (faster loads)
- Schema: `ALTER TABLE media_files ADD COLUMN IF NOT EXISTS thumbnail_path VARCHAR(500)` and `resized BOOLEAN DEFAULT FALSE` applied safely on existing installations

### App — Public Theme System (`App/Theme/ThemeEngine`, `App/Themes/default/`)

- **`ThemeEngine::render()`** — wraps any module front-end view in the active theme layout; captures view output with `ob_start`, injects as `$content` into `App/Themes/{theme}/layout.php`
- **`ThemeEngine::activeTheme()`** — reads `settings` key `active_theme` (default: `default`)
- **`ThemeEngine::deploy()`** — copies `App/Themes/{theme}/css|js|fonts|images/` to `Public/themes/{theme}/` on first render (auto-deploy, no manual step)
- **Default theme** — clean responsive layout: sticky header, mobile hamburger nav, dynamic nav links (blog, gallery, contact), footer; CSS custom properties for accent/text/muted/border/bg colors
- **`theme.json`** — declarative theme manifest: name, slug, version, description, author, assets
- Blog front-end refactored to render through ThemeEngine (content-only views, no `<html>/<head>/<body>` wrapper)

### Pages Module (v0.0.1)

- `pages` table: UUID PK, title, slug (unique), content, excerpt, status, template, meta_title, meta_description, sort_order
- 5 permissions: `pages.view/create/edit/delete/publish`
- Admin AJAX CRUD with Quill editor, slug auto-generation, SEO fields
- Front-end route `GET /([a-z0-9][a-z0-9\-]*)` renders published pages via ThemeEngine; returns 404 for unknown slugs

### Gallery Module (v0.0.1)

- `galleries` table: UUID PK, title, slug, description, cover_image_id (FK → media_files), status, meta fields
- `gallery_items` table: gallery_id, media_file_id, caption, sort_order; cascade delete
- 5 permissions: `gallery.view/create/edit/delete/publish`
- Admin: album CRUD; manage items page with iframe media picker, AJAX add/remove, HTML5 drag-to-reorder (saves via `POST /reorder` with `X-CSRF-Token` header)
- Front-end: album listing grid and single album view with pure CSS + vanilla JS lightbox (keyboard arrow/escape navigation)

### Contact Form Module (v0.0.1)

- `contact_submissions` table: UUID PK, name, email, subject, message, status (unread/read/spam), ip_address, submitted_at, read_at, replied_at
- 3 permissions: `contact.view/delete/settings`
- Admin inbox: filter by status (all/unread/read/spam), mark-read, mark-spam, delete; unread count badge in nav
- Settings: admin notification email, auto-reply toggle, customizable auto-reply message
- Front-end contact form with CSRF protection and rate limiting (1 submission per IP per 10 minutes)
- On submit: sends admin notification email via Mailer; sends auto-reply to visitor if enabled

### Videos Module (v0.0.1)

- `videos` table: UUID PK, title, slug, provider (youtube/vimeo/other), embed_url, video_id, thumbnail_path, description, status, meta fields, sort_order
- 5 permissions: `videos.view/create/edit/delete/publish`
- Admin grid with poster thumbnail previews; AJAX CRUD modal with `vtx-slug` support
- Poster thumbnail auto-fetch: YouTube `maxresdefault.jpg` cached locally; Vimeo poster via public API
- Front-end: responsive video grid listing; single video page with lazy iframe (poster click loads player)

---

## [Unreleased]

### Blog Module (v0.0.3) — 2026-06-21

- **Setup Wizard** — Fires immediately after install; 3-step wizard (URL path, blog identity, defaults) with a live URL preview and a root-mount warning for blank path. Skip link available for users who want to configure later.
- **Dynamic Front-End Routing** — Blog's public URL path is now stored as `blog_base_path` in the settings table (default: `blog`). Blank value mounts the blog at site root (`/`). Route registration reads this value at load time — no code changes needed to relocate the blog.
- **Path-Change SEO Prompt** — When `blog_base_path` is changed in Blog Settings, a warning panel appears with two options: add a 301 redirect from the old path (recommended; stacks across multiple changes) or change without redirect (for paths that had no real traffic).
- **301 Redirect Accumulation** — Old base paths are stored in `blog_redirect_paths` (JSON). `BlogRedirectController` registers redirect routes for each old path so index, post, and category URLs all redirect to their new equivalents automatically.
- **Route Cache Auto-Clear** — Any path change (via wizard or settings) clears the route cache immediately so the new URLs take effect on the next request without a manual cache flush.
- **Module Setup URL Convention** — `ModuleManager::install()` now checks the module manifest for a `"setup"` key and returns `setup_url` in the install response. The Module Manager JS redirects to the wizard URL instead of refreshing the panel. Any future module can declare its own post-install wizard with one manifest entry.
- **Settings cleanup on uninstall** — `Module::uninstall()` now deletes all `settings WHERE grp = 'blog'` so no orphaned rows remain after removal.

---

## [0.0.1-alpha] — 2026-06-21

Initial alpha release. The system is functional but not production-hardened.
APIs and database schema may change before the stable 1.0.0 release.

### Core System

- **Setup Wizard** — 5-step guided installation (requirements check, database setup, admin user creation, site configuration, completion)
- **Admin Panel** — Responsive sidebar layout with dark/light theme toggle (persisted via localStorage)
- **Role-Based Access Control (RBAC)** — Users → Roles → Permissions (many-to-many); fine-grained permission slugs per resource/action
- **Audit Logging** — All admin state-changing actions logged to `audit_logs` with user, action, resource, IP, and user agent
- **Login Rate Limiting** — Brute-force protection via `LoginRateLimiter`; configurable lock threshold
- **CSRF Protection** — Cryptographically secure tokens (32-byte random, hex-encoded); 1-hour expiry; timing-safe comparison
- **Session Security** — `HttpOnly`, `Secure`, `SameSite=Strict` cookies; session ID regeneration on login; user-agent hijacking detection
- **Security Headers** — `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff` on all admin responses
- **IP Spoofing Fix** — `Client::getIpAddress()` defaults to `REMOTE_ADDR`; proxy headers only trusted when `setTrustedProxies()` is configured

### Admin Panel Sections

- **Dashboard** — System overview: user count, role count, module status, recent audit log entries
- **User Management** — Create, edit, soft-delete users; assign roles; bcrypt passwords (cost 12); searchable paginated list
- **Role Management** — Create custom roles; assign permissions; system roles (Administrator) protected from deletion
- **Site Settings** — Edit site name, URL, description, admin email, timezone, date/time format, maintenance mode; key whitelist enforced
- **Module Manager** — Install, uninstall, enable/disable, sync views; auto-discovers modules from `App/Modules/`

### Module System

- **ModuleInterface** — `install()`, `uninstall()`, `registerRoutes()` contract for all modules
- **module.json manifest** — Declarative module metadata: name, slug, version, nav links with permission gates, subnav support
- **View deployment lifecycle** — Module views deployed to `App/Views/modules/{slug}/` on install; removed on uninstall
- **Route cache invalidation** — Route cache cleared automatically on module install/uninstall/toggle
- **ModuleLoader** — Per-request cache of enabled modules; `isEnabled()`, `getEnabled()`, `navItems()` helpers

### Blog Module (v0.0.2 → see [Unreleased] for v0.0.3)

- **Posts** — Create, edit, publish, archive, delete; draft/published/archived status workflow
- **Rich Text Editor** — Quill-based WYSIWYG editor (`vtx-editor` component)
- **SEO Fields** — Meta title, meta description, estimated reading time
- **Featured Images** — Optional featured image via Media picker
- **Categories** — Full CRUD with post relationship (many-to-many)
- **Tags** — Full CRUD with autocomplete tag input (`vtx-tags`); bulk operations
- **Comment Moderation** — Approve, mark as spam, delete; bulk moderation; pending/approved/spam statuses
- **Analytics Dashboard** — 30-day publication chart (`vtx-chart`); post/category/tag counts
- **Public Frontend** — Blog home, single post with comment form, category listing; all paginated
- **17 permissions** — Granular control over posts, categories, tags, comments, and blog settings

### Media Module (v0.0.1)

- **File Upload** — Drag-and-drop via `vtx-upload`; MIME type + extension validation; randomized filenames
- **Media Grid** — Paginated grid browser (24 files per page)
- **Metadata Editing** — Alt text and caption fields via AJAX modal
- **Media Picker Modal** — Reusable `vtx-media-picker` component for any module form
- **Upload Security** — Files stored in `Public/uploads/YYYY/MM/`; `.htaccess` blocks PHP execution in upload dir
- **4 permissions** — `media.view`, `media.upload`, `media.edit`, `media.delete`

### Database Layer Security Fixes

- **SQL Injection** — Fixed identifier injection in `month()`, `year()`, `day()`, `dateFormat()`, `fullTextSearch()`, `jsonExtract()`, `jsonContains()`, `caseWhen()`, `regexp()`, `ilike()`, `arrayContains()`, `stringAgg()` across `BuildersTrait`, `MySQL`, and `PgSQL` builders
- **`quoteIdentifier()`** — Added safe identifier quoting helper to both MySQL (backtick) and PgSQL (double-quote) builders
- **`bindValue()`** — Added parameterized binding helper for value arguments; all advanced query methods now use bound parameters

### JavaScript Component Library (vtx-*)

- **vtx-chart** — Bar/line/doughnut chart (Chart.js wrapper)
- **vtx-datatable** — Client-side sortable, filterable table with pagination
- **vtx-editor** — Quill rich text editor with hidden textarea sync
- **vtx-media-picker** — Media library picker modal with preview
- **vtx-search** — Debounced live AJAX search
- **vtx-select** — Enhanced select with keyboard navigation
- **vtx-tags** — Tag chip input with AJAX autocomplete
- **vtx-upload** — Drag-and-drop file uploader with progress

### CSS Framework

- Custom flat design CSS framework (Bootstrap 5.3.8 compatible class names)
- Dark/light theme via `[data-theme=dark]` attribute
- CSS custom properties (`--ps-*`) for theming
- 50+ SVG icon system via `.pi .pi-{name}` classes
- Responsive grid, utility classes, form styles, badges, cards, tables, alerts

---

## Upcoming

### [0.0.3-alpha] — planned

- Comment email notifications (approval, new comment alert to post author)
- New user welcome emails
- Admin theme selector (switch active front-end theme from Site Settings)
- Additional front-end theme options
