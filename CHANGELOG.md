# Vertext CMS - Changelog

All notable changes to Vertext CMS are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [0.0.5-alpha] - 2026-06-26

### Core

- **Content Revisions** - snapshot before every update on Posts and Pages; `content_revisions` table shared between both modules; revision list per content item; restore from any revision with a single click; snapshot captures title, body/content, and status
- **Scheduled/Expired Publishing** - `published_at` and `expire_at` columns on both Posts and Pages; public queries filter at render time (`(status='published') OR (status='scheduled' AND published_at <= NOW()) AND (expire_at IS NULL OR expire_at > NOW())`) with no cron required; "Scheduled" tab in Posts admin; "Live (scheduled)" badge when a scheduled post has passed its `published_at`; `expire_at` field in both post and page forms
- **vtx-select Standardization** - all admin `<select>` elements gain `data-vtx-select` for consistent searchable dropdown UX; class normalized to `form-select` across all module forms

### Navigation Module (v0.0.1 → v0.0.2)

- **Module Route type** - Navigation builder gains "Module Route" as a third item type alongside Custom URL and Page; lists all front-end routes declared by installed modules via `nav_routes` in `module.json`
- **Auto-registration on install** - modules with front-end routes (Contact, Gallery, Videos, Blog, Search) declare `nav_routes` in `module.json`; on install, a nav item is auto-inserted into the primary navigation menu

### Search Module (v0.0.1)

- `search_index` table with `UNIQUE(content_type, content_id)`; `ON CONFLICT DO UPDATE` upsert for idempotent reindex; admin dashboard shows total count, per-type breakdown, and last indexed timestamp; Reindex button for users with `search.manage`
- `GET /search?q=...` - ILIKE search across `title` and `body` of indexed Pages and Blog posts; up to 30 results; contextual excerpt with match-position snippet; type badge per result
- Permission: `search.manage`; auto-granted to Administrator on install

### Theme Customizer (v0.0.1)

- Accent color picker with hex input and live JS preview; font family selector (system, Inter, Georgia, Helvetica, Courier); logo URL field (replaces site name text with `<img>` in both default and clean themes); freeform custom CSS textarea
- Settings stored in `settings` table (grp: `theme-customizer`); `ThemeCustomizerHelper::getCss()` injects a `<style>` block with `--ps-primary`, `--ps-primary-hover`, `--ps-primary-light`, `--ps-font-sans` CSS variable overrides and custom CSS after `theme.css` in all theme layouts
- Permission: `theme-customizer.manage`; auto-granted to Administrator on install

### Analytics (v0.0.2 → v0.0.3)

- **Unique visitors** - count distinct `ip_hash` per period; KPI card in the analytics dashboard
- **Device breakdown** - mobile vs desktop split from User-Agent string; displayed as percentage in the dashboard

### Docs

- **Module dependency system** - `docs/module-system.md` updated to document `requires.modules` in `module.json`, install guards (`checkModuleDeps()`), and uninstall protection (`checkDependents()`) - fully implemented since v0.0.3 but previously undocumented

---

## Upcoming

### [0.0.6-alpha]

#### Core

- **Multi-language / i18n** - language switcher, translatable content fields, locale-aware date/number formatting
- **Role/permission builder UI** - create custom permissions in admin without hardcoding in module code
- **Two-factor authentication (2FA)** - TOTP authenticator app support for admin users
- **Admin audit log UI** - searchable, filterable view of `audit_logs` table (data collected since v0.0.1)

#### Blog (v0.0.6)

- **Related posts** - N related posts below single post view based on shared tags/categories
- **Reading list** - visitor-side "save for later" to localStorage; no account required
- **Comments** - threaded comments with moderation; Disqus embed as alternative
- **Post series** - ordered multi-part posts with prev/next navigation

#### New Modules

- **Forms Builder** - drag-and-drop custom form creation; extends Contact module patterns; stores submissions in DB
- **Newsletter** - subscriber list management + email blast; integrates with Webhooks for delivery events
- **Events** - event listings with date, location, RSVP; front-end calendar view

#### Analytics (v0.0.4)

- **Referrer breakdown** - top traffic sources with domain grouping
- **Search term tracking** - captures queries from the Search module
- **Export** - structured CSV/JSON export for import into external analytics tools

#### DX / Infrastructure

- **Module scaffold CLI** - `php vertext make:module Foo` generates boilerplate module files
- **Module marketplace** - install a module directly from a URL via the Module Manager UI

---

## [0.0.4-alpha] - 2026-06-25

### Core

- **Admin profile page** - `GET /admin/profile` + `POST /admin/profile/update`: any logged-in user can update their own display name, email address, and password without needing the Users management permission; email uniqueness validated against other users; passwords hashed with bcrypt cost 12; change logged to audit trail

### Blog (v0.0.3 → v0.0.4)

- **RSS feed** - `GET /{blog_base}/feed.rss`: RSS 2.0 feed of the 20 most recent published posts; includes `atom:link`, `content:encoded` (full post body via CDATA), and `<enclosure>` for featured images; auto-linked via `<link rel="alternate" type="application/rss+xml">` in both theme `<head>` elements when Blog is enabled; `feedUrl` computed centrally in `ThemeEngine` to avoid modifying every render call

### Media (v0.0.2 → v0.0.3)

- **Bulk actions** - checkbox overlay on every media card (shown to users with `media.delete`); select-all toggle in the bulk action bar; bulk delete sends `POST /admin/media/bulk` with CSRF; physical files and thumbnails deleted from disk before DB rows removed; batch uses `whereRaw("id IN (...)")` for efficient single-query deletion; `Auth::audit('media.bulk_delete')` records count
- **Bulk action bar** - slides in when ≥1 card is selected; shows selection count; `vtxConfirmModal` confirms before delete; `VtxAjax.postForm` submits and reloads grid on success

### Analytics (v0.0.1 → v0.0.2)

- **Date range filter** - from/to date pickers in the dashboard header; quick presets (Today, 7 Days, 30 Days, 90 Days); all stats, chart, top pages, and top referrers now reflect the selected period instead of being hardcoded to 30 days
- **Period comparison** - "Selected Period" KPI card shows delta (`▲`/`▼`) vs the immediately preceding equivalent period; "Today" card shows delta vs yesterday; computed as `round((current - prev) / prev * 100, 1)%`; "no prior data" shown when previous period is zero
- **Daily average** - third KPI card shows `total / days` for the selected range
- **CSV export** - `GET /admin/analytics/export?from=...&to=...` streams a CSV of all `url_path`, `page_title`, `referrer_host`, `viewed_at` rows for the selected period with proper `Content-Disposition: attachment` header

### New Modules

#### Sitemap (v0.0.1)

- `GET /sitemap.xml` - generates an XML sitemap from published pages (priority 0.8, changefreq monthly) and Blog posts (priority 0.7, changefreq weekly) when Blog is enabled; home page included at priority 1.0; blog index at priority 0.9
- `SitemapProvider` interface - any module can implement `getSitemapUrls(string $siteUrl): array` to contribute URLs in future releases
- Site URL resolved from `settings.site_url`, falls back to request `HTTP_HOST` + `baseUrl`
- Settings: `sitemap_include_pages` and `sitemap_include_blog` (both default enabled)
- 1-hour `Cache-Control` header; wraps all DB calls in try-catch so a missing Pages or Blog table never returns a 500

#### Webhooks (v0.0.1)

- `webhook_endpoints` table: name, payload URL, HMAC secret, JSON events array, enabled flag; `webhook_logs` table: endpoint_id FK (cascade delete), event, payload, HTTP response code, response body (truncated 500 chars), duration ms, success flag
- `WebhookDispatcher::dispatch(event, payload)` - static method called by any module; finds enabled endpoints subscribed to the event; fires signed HTTP POST via cURL (10s timeout); logs result; fails silently so delivery never breaks a request
- `WebhookDispatcher::dispatchToEndpoint(id, event, payload)` - dispatches to a specific endpoint regardless of subscription (used for test ping)
- Payload signing: `X-Vertext-Signature: sha256={HMAC-SHA256}`, `X-Vertext-Event`, `X-Vertext-Delivery` headers on every request
- Admin UI: endpoint list with last-delivery status badge; create/edit form with event checkboxes and secret regeneration; delivery log table (last 50 per endpoint: event, HTTP status, duration, response preview); test ping button fires a `ping` event immediately
- Available events: `post.published`, `post.deleted`, `page.published`, `page.deleted`, `media.uploaded`, `media.deleted`, `ping`
- Permissions: `webhooks.view`, `webhooks.manage`; both auto-granted to Administrator on install

### JavaScript Component UI/UX Overhaul

All `vtx-*` components that used inline `cssText` or inline `style` attributes injected from JavaScript have been refactored to use CSS classes only.

- **`vtx-slug`** - removed hardcoded `style="color:..."` from reset link; added `.vtx-slug-hint` and `.vtx-slug-reset` classes to `admin.css` with hover/active states via CSS transitions
- **`vtx-upload`** - removed `element.style.cssText` from overlay and progress bar; `.vtx-upload-overlay`, `.vtx-upload-overlay-text`, `.vtx-upload-bar` added to `admin.css`; dark mode override included
- **`vtx-tags`** - removed all `this.wrap.style.cssText`, `this.inputEl.style.cssText`, `this.dropdown.style.cssText`, `chip.style.cssText`, and mouseover/mouseout inline background handlers; `.vtx-tags-wrap`, `.vtx-tags-dropdown`, `.vtx-tags-option`, `.vtx-tags-option--added`, `.vtx-tags-option-badge`, `.vtx-tag-chip-remove` moved to `blog.css`; hover handled via CSS `:hover`
- **`vtx-media-picker`** - removed entire modal CSS injected via `panel.style.cssText`; `.vtx-media-picker-panel`, `.vtx-media-picker-header`, `.vtx-media-picker-title`, `.vtx-media-picker-close`, `.vtx-picker-loading`, `.vtx-picker-error`, `#vtx-picker-panel-body` moved to `media.css` with dark mode overrides

### Added

- **Maintenance Mode** - working implementation: `App\CMS\Maintenance::check()` called from `Config/Routes.php` before routing; reads `maintenance_mode` setting from DB; bypasses `/admin`, `/setup`, and logged-in admin sessions automatically; serves `App/Views/maintenance.php` - a standalone self-contained 503 page with inline CSS, dark mode via `prefers-color-scheme`, and animated amber status dot (no theme engine dependency); settings panel shows a proper pill toggle switch (`vtx-pill-toggle`) wired to a dedicated `POST /admin/settings/toggle-maintenance` endpoint that toggles the DB value and clears the page cache; warning banner appears in the system panel while mode is active; note that logged-in admins always see the live site - test in a private window
- **`App\CMS\Version`** - canonical version constant (`Version::APP`); admin sidebar reads from it instead of a hardcoded string; update this single constant when cutting a new release

### Fixed

- **Clean theme visual overhaul** - `--clr-border` changed from near-black (`#0f0f0f`) to soft gray (`#d4d4d4` light / `#2e2e2e` dark); all `2px solid` borders reduced to `1px solid`; `--radius-sm: 4px` and `--radius-md: 8px` CSS variables added; border-radius applied to dropdown menus, theme toggle, nav toggle, `code`, `pre` elements
- **Media thumbnail generation - Phuse Image class replaced with raw GD** - the Phuse `Image` wrapper silently short-circuits all operations (resize, crop, save) when its internal `$errors` array is non-empty; dimension validation errors are added to `$errors` without halting execution, so every subsequent GD operation returned false and `processUploadedImage()` returned `null` for all inputs; rewrote thumbnail generation using raw GD functions (`imagecreatefromjpeg`, `imagecreatefrompng`, `imagecreatefromwebp`, `imagecopyresampled`) which fail loudly and predictably; added `loadGdImage()` and `saveGdImage()` helpers; cover-crop math and 400 px target size unchanged
- **Media thumbnail regeneration - failure sentinel included in retry queries** - files that fail thumbnail generation are marked `thumbnail_path = filename` as a sentinel; the three regen queries (`missingThumbCount`, batch fetch, remaining count) only checked `IS NULL OR = ''`, permanently hiding sentinelled files from future regen attempts; all three queries updated to `IS NULL OR = :emp OR = filename` so failed files can be retried
- **Media thumbnail regeneration - SQL syntax error** - an empty string literal `''` inside `whereRaw("thumbnail_path = '' ...")` was mangled by the Phuse ORM query builder before reaching PostgreSQL, producing an unterminated string error; replaced with a bound parameter `[':emp' => '']`
- **UUID router - integer cast removed** - `Core/Router.php` cast numeric-looking route captures to `int` via `ctype_digit`; tables with UUID primary keys can produce IDs whose leading segment is all digits (e.g. role ID `"1"` from a legacy seed row), triggering a `TypeError: string expected, int given` when the capture reached a `string $id` parameter; fixed by replacing the cast with `array_map('strval', $matches)` so all captures are always strings regardless of content
- **`alert()` replaced with `Phuse.toast()`** - all module views used `if (window.vtxToast) vtxToast(...)` which always evaluated false (`vtxToast` does not exist); the real API is `Phuse.toast(message, type)`; replaced in Media, Contact, Webhooks, and Gallery module views (source + deployed copies)
- **Blog featured image UUID cast** - updating a post with a featured image failed with PostgreSQL `invalid input syntax for type uuid`; `(int)` cast on a UUID string (e.g. `"647bbb85-..."`) produced a leading-digit integer (`647`) which failed the column type check; fixed with `htmlspecialchars($p['featured_image_id'] ?? '')` in the post form hidden input
- **Version strings centralized** - `App\CMS\Version::PHUSE` added alongside `Version::APP`; admin dashboard System Info panel reads both constants instead of hardcoded strings; Phuse version updated to `1.2.5` across all files (`Core/Template/Parser.php`, `Core/Template/ParserTrait.php`, `Public/assets/js/scripts.js`, `Public/assets/css/styles.css`)

---

## [0.0.3-alpha] - 2026-06-24

### New Modules

#### Navigation (v0.0.1)

- Admin menu builder: create named menus (e.g. "Primary Navigation"), add items (custom URL, page slug, or module link), single-level parent/child nesting, drag-to-reorder
- Tables: `nav_menus` (UUID, name, slug) and `nav_items` (UUID, menu_id, parent_id, type, label, url, page_slug, sort_order, open_in_new)
- Seeds "Primary Navigation" (slug: `primary`) on install
- `NavHelper::getMenu(string $slug)` - static helper called from theme layouts; returns nested items array with per-request caching; returns empty array gracefully when module is not installed
- Both default and clean themes automatically render the primary menu via NavHelper, with dropdown support for nested items
- Permissions: `navigation.view`, `navigation.manage`

#### Analytics (v0.0.1)

- Privacy-friendly page-view tracking: `url_path`, `page_title`, `referrer_host` (hostname only), `ip_hash` (SHA-256 with daily salt - not reversible), `viewed_at`
- Bot filter: 18 user-agent patterns (Googlebot, Bingbot, Slurp, DuckDuckBot, Baidu, Yandex, Sogou, facebot, ia_archiver, SemRush, Ahrefs, Screaming Frog, Chrome Lighthouse, headless patterns)
- `Tracker::record()` called automatically from `ThemeEngine::render()` when module is enabled; wrapped in try-catch so analytics never breaks a page
- Admin dashboard: today/week/month view counts, top 10 pages, top 10 referrers, 30-day daily chart (pure Canvas, no external library)
- JSON data endpoint (`GET /admin/analytics/data`) for chart refresh
- Permissions: `analytics.view`, `analytics.manage`

### New Theme

#### Clean (v0.0.1)

- Typographic, editorial theme: Georgia/serif body font, black borders, uppercase nav, 2-px thick accents
- Full dark/light mode support (same CSS layer pattern as default theme)
- NavHelper integration - renders Primary Navigation menu same as default theme

### Front-End Theme Improvements

- **Dark/light mode** on both `default` and `clean` themes
  - Three CSS layers: `:root` (light defaults), `@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) }` (OS preference), `[data-theme="dark"]` (explicit override)
  - FOUC prevention: inline `<script>` in `<head>` reads `localStorage.getItem('vtx-theme')` and sets `data-theme` before CSS renders
  - Theme toggle button (sun/moon SVG) in every layout header
  - Theme preference persisted to `localStorage` under key `vtx-theme`
- **styles.css as base layer** - both theme layouts now load `Public/assets/css/styles.css` before `theme.css`; gives front-end views access to the full Phuse CSS framework (grid, utilities, custom properties)
- **Default theme colors** - light: `#ffffff` bg, `#4f46e5` accent; dark: `#0f172a` bg, `#818cf8` accent
- **Clean theme colors** - light: `#ffffff` bg, `#111111` accent; dark: `#0c0c0c` bg, `#f0f0f0` border-inverted

### Admin - Theme Manager

- **Promoted to a system module** - Theme Manager is now a core admin section at `GET /admin/themes` with its own sidebar nav entry (`pi-sliders` icon), between Module Manager and Settings
- **`ThemesController`** - new controller with `index()` (theme card grid) and `setTheme()` (POST handler)
- **Removed from Settings** - the Themes tab is gone from Admin → Settings; the route `/admin/settings/set-theme` is removed
- **Migration + DB seed** - `theme-manager` added to the core modules seed in `Migrations/001_core_tables.php`; existing installations get the row on next DB sync

### Admin - Module Manager Overhaul

- **Categorized card layout** replaces the previous two-table layout
- **System section** (collapsible) - core modules shown as compact read-only rows with an "Always On" indicator; collapsed by default
- **Category sections** - add-on modules grouped by category (Content, Media, Communication, Analytics, Navigation) with card grids; each card shows the module icon, name, version badge, description, status badge, and action buttons
- **`category` field** added to all `module.json` files
- Installed card: Disable/Enable toggle, Sync Views, Uninstall buttons
- Available card (dashed border, slightly dimmed): Install button; disabled with tooltip when module dependencies are missing

### Admin - Navigation Module Bug Fixes

- `navigation/index.php` admin view: replaced `new bootstrap.Modal()` with `Phuse.modal()` lazy getter; replaced `data-bs-dismiss` with `data-dismiss`
- `navigation/builder.php` admin view: modal init moved inside `DOMContentLoaded` to fix `Phuse is not defined` timing error
- `NavigationController::storeItem()` and `updateItem()`: `open_in_new` cast changed from `(bool)` to `? 1 : 0` to avoid boolean empty-string binding error in PostgreSQL
- `NavigationController`: `->where('parent_id IS NULL', '')` changed to `->whereRaw('parent_id IS NULL', [])` to avoid `SQLSTATE[22P02]` (Phuse ORM `where()` always appends `= :bind`; IS NULL conditions require `whereRaw()`)

### Icons

- `pi-bars` - three-line hamburger/menu icon
- `pi-chart-bar` - vertical bar chart icon
- Both added to `Public/assets/css/styles.css` following the existing `mask-image` + URL-encoded SVG data URI pattern

### Mail

- **Comment notification to post author** - when a visitor submits a comment and `comments_require_approval` is enabled, the post author receives an email notification via `comment_pending` template
- **New user welcome email** - welcome email sent automatically on user creation via `welcome` template

### Module Dependency System

- `module.json` `requires.modules` array: declare which other modules must be installed first
- `ModuleManager::checkModuleDeps()` - blocks install if required modules are missing; returns list of missing slugs
- `ModuleManager::checkDependents()` - blocks uninstall if other installed modules depend on this one
- `ModuleManager::getDependencyInfo()` - public method returning per-slug install status for Module Manager UI
- Install button disabled with tooltip in Module Manager when dependencies are not met

---

## [0.0.2b-alpha] - 2026-06-24

Patch release. Bug fixes only - no new features or schema changes.

### Bug Fixes

#### Admin UI

- **Modal close button invisible** - `btn-close` buttons in the form and confirm modals had no visible content; added `<i class="pi pi-x">` icon inside both buttons in `base.php`
- **Missing nav icons** - Added `pi-video` (video camera SVG) and `pi-images` (stacked frames SVG) to `styles.css`; added `pi-inbox` (tray SVG) and `pi-cog` (gear SVG) to `styles.css`; fixed Contact module nav icon `pi-envelope` → `pi-mail` (the `pi-envelope` class does not exist)

#### CRUD - First-Save Empty-State Bug

- **`admin.js` fallback** - When saving the first item into an empty list, the tbody-swap fallback failed silently (`curBody` was null); now falls back to replacing all `.vtx-panel[id]` elements from the fresh response HTML
- **Pages module** - Added `id="pages-table-panel"` to the main panel so the fallback can locate it (source + deployed)
- **Gallery module** - Added `id="galleries-table-panel"` to the main panel (source + deployed)

#### `vtx-slug` Component - Double "Reset to auto"

- **Duplicate listeners** - `vtxSlug.init()` called on every modal open attached new listeners to the same elements without a guard, causing two "• Reset to auto" links to appear; added `targetEl._vtxSlugInit` marker so `initSlugPair()` is idempotent

#### Contact Module

- **Fatal error on admin pages** - Views called `$this->extend()`, `$this->section()`, `$this->endSection()` inside Phuse Parser templates where `$this` is the Parser instance (these methods do not exist); removed all three calls from `index.php`, `view.php`, and `settings.php` (source + deployed)

#### Pages Module

- **List always showed empty** - `PagesController::index()` passed `'pages'` as a duplicate key in the data array; PHP keeps the last value (the integer page count), so `$pages` in the view was an integer and `foreach` silently skipped it; renamed second key to `'totalPages'` and updated both views

#### Videos Module

- **`Uncaught ReferenceError: bootstrap is not defined`** - The Videos admin index used Bootstrap JS modal API (`bootstrap.Modal.getOrCreateInstance`) which is not loaded in this CMS; rewrote the view to use the CMS `vtx-form-modal` system (`data-form-url`, `data-crud-form`, `data-confirm-form`), removed the embedded `#videoModal` markup and 48-line custom JS; added `vtx:crud:success` reload listener
- **`_form.php`** - Changed `data-vtx-ajax` → `data-crud-form`; fixed Cancel button from `data-bs-dismiss="modal"` → `vtxFormModalClose()`; fixed `Vtx.slug.init(el, el)` call to standard `window.vtxSlug.init()`

---

## [0.0.2-alpha] - 2026-06-23

Built on **Phuse 1.2.5** - all ORM, routing, session, and utility primitives come from the Phuse framework layer.

### Blog Module (v0.0.3)

- **Setup Wizard** - Fires immediately after install; 3-step wizard (URL path, blog identity, defaults) with a live URL preview and a root-mount warning for blank path. Skip link available for users who want to configure later.
- **Dynamic Front-End Routing** - Blog's public URL path is now stored as `blog_base_path` in the settings table (default: `blog`). Blank value mounts the blog at site root (`/`). Route registration reads this value at load time - no code changes needed to relocate the blog.
- **Path-Change SEO Prompt** - When `blog_base_path` is changed in Blog Settings, a warning panel appears with two options: add a 301 redirect from the old path (recommended; stacks across multiple changes) or change without redirect (for paths that had no real traffic).
- **301 Redirect Accumulation** - Old base paths are stored in `blog_redirect_paths` (JSON). `BlogRedirectController` registers redirect routes for each old path so index, post, and category URLs all redirect to their new equivalents automatically.
- **Route Cache Auto-Clear** - Any path change (via wizard or settings) clears the route cache immediately so the new URLs take effect on the next request without a manual cache flush.
- **Module Setup URL Convention** - `ModuleManager::install()` now checks the module manifest for a `"setup"` key and returns `setup_url` in the install response. The Module Manager JS redirects to the wizard URL instead of refreshing the panel. Any future module can declare its own post-install wizard with one manifest entry.
- **Settings cleanup on uninstall** - `Module::uninstall()` now deletes all `settings WHERE grp = 'blog'` so no orphaned rows remain after removal.

### App - Mailer (`App/Mail/`)

- **`Mailer`** - `Mailer::make()->send(MailMessage)`: PHP `mail()` driver and SMTP driver (native `fsockopen`, no external deps); reads config from `site_settings` at runtime
- **`MailMessage`** - fluent builder: `to()`, `subject()`, `htmlBody()`, `textBody()`, `from()`
- **`MailerConfig`** - maps `mail_driver`, `mail_host`, `mail_port`, `mail_username`, `mail_password`, `mail_encryption`, `mail_from_address`, `mail_from_name` settings
- **`MailTemplate`** - `render(string $name, array $vars)` renders HTML email templates from `App/Mail/Templates/`
- **Email templates** - `base.php` (shared layout), `comment_approved.php`, `comment_pending.php`, `welcome.php`, `contact_notification.php`, `contact_autoreply.php`
- **Admin Mail Settings** - new "Mail" tab in Admin → Site Settings to configure all mail options

### Core - Slug Component (`Public/assets/js/components/vtx-slug.js`)

- Watches `[data-vtx-slug-source]` → writes `[data-vtx-slug-target]` with debounced 300ms slug generation
- Mirrors `Str::slug()` logic: lowercase, non-alphanumeric → hyphen, collapse, trim
- Stops auto-updating once user manually edits the slug field; "Reset" link re-enables it
- Loaded on demand via `Vtx.load(['slug'], fn)`

### Media Module (v0.0.2)

- **Image resizing on upload** - originals wider than 1920 px are downscaled in-place; `resized` flag stored in DB
- **Thumbnail generation** - 400x400 cover-crop thumbnail (`thumb_` prefix) generated for every uploaded image via `Core\Utilities\Image\Image`; stored as `thumbnail_path`
- **Regenerate Thumbnails** - bulk action in the media grid processes up to 50 files per request; shows remaining count badge
- **Grid thumbnails** - media grid and picker modal now display the 400 px thumbnail instead of the full original (faster loads)
- Schema: `ALTER TABLE media_files ADD COLUMN IF NOT EXISTS thumbnail_path VARCHAR(500)` and `resized BOOLEAN DEFAULT FALSE` applied safely on existing installations

### App - Public Theme System (`App/Theme/ThemeEngine`, `App/Themes/default/`)

- **`ThemeEngine::render()`** - wraps any module front-end view in the active theme layout; captures view output with `ob_start`, injects as `$content` into `App/Themes/{theme}/layout.php`
- **`ThemeEngine::activeTheme()`** - reads `settings` key `active_theme` (default: `default`)
- **`ThemeEngine::deploy()`** - copies `App/Themes/{theme}/css|js|fonts|images/` to `Public/themes/{theme}/` on first render (auto-deploy, no manual step)
- **Default theme** - clean responsive layout: sticky header, mobile hamburger nav, dynamic nav links (blog, gallery, contact), footer; CSS custom properties for accent/text/muted/border/bg colors
- **`theme.json`** - declarative theme manifest: name, slug, version, description, author, assets
- Blog front-end refactored to render through ThemeEngine (content-only views, no `<html>/<head>/<body>` wrapper)

### Pages Module (v0.0.1)

- `pages` table: UUID PK, title, slug (unique), content, excerpt, status, template, meta_title, meta_description, sort_order
- 5 permissions: `pages.view/create/edit/delete/publish`
- Admin AJAX CRUD with Quill editor, slug auto-generation, SEO fields
- Front-end route `GET /([a-z0-9][a-z0-9\-]*)` renders published pages via ThemeEngine; returns 404 for unknown slugs

### Gallery Module (v0.0.1)

- `galleries` table: UUID PK, title, slug, description, cover_image_id (FK to media_files), status, meta fields
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

## [0.0.1-alpha] - 2026-06-21

Initial alpha release. The system is functional but not production-hardened.
APIs and database schema may change before the stable 1.0.0 release.

### Core System

- **Setup Wizard** - 5-step guided installation (requirements check, database setup, admin user creation, site configuration, completion)
- **Admin Panel** - Responsive sidebar layout with dark/light theme toggle (persisted via localStorage)
- **Role-Based Access Control (RBAC)** - Users → Roles → Permissions (many-to-many); fine-grained permission slugs per resource/action
- **Audit Logging** - All admin state-changing actions logged to `audit_logs` with user, action, resource, IP, and user agent
- **Login Rate Limiting** - Brute-force protection via `LoginRateLimiter`; configurable lock threshold
- **CSRF Protection** - Cryptographically secure tokens (32-byte random, hex-encoded); 1-hour expiry; timing-safe comparison
- **Session Security** - `HttpOnly`, `Secure`, `SameSite=Strict` cookies; session ID regeneration on login; user-agent hijacking detection
- **Security Headers** - `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff` on all admin responses
- **IP Spoofing Fix** - `Client::getIpAddress()` defaults to `REMOTE_ADDR`; proxy headers only trusted when `setTrustedProxies()` is configured

### Admin Panel Sections

- **Dashboard** - System overview: user count, role count, module status, recent audit log entries
- **User Management** - Create, edit, soft-delete users; assign roles; bcrypt passwords (cost 12); searchable paginated list
- **Role Management** - Create custom roles; assign permissions; system roles (Administrator) protected from deletion
- **Site Settings** - Edit site name, URL, description, admin email, timezone, date/time format, maintenance mode; key whitelist enforced
- **Module Manager** - Install, uninstall, enable/disable, sync views; auto-discovers modules from `App/Modules/`

### Module System

- **ModuleInterface** - `install()`, `uninstall()`, `registerRoutes()` contract for all modules
- **module.json manifest** - Declarative module metadata: name, slug, version, nav links with permission gates, subnav support
- **View deployment lifecycle** - Module views deployed to `App/Views/modules/{slug}/` on install; removed on uninstall
- **Route cache invalidation** - Route cache cleared automatically on module install/uninstall/toggle
- **ModuleLoader** - Per-request cache of enabled modules; `isEnabled()`, `getEnabled()`, `navItems()` helpers

### Blog Module (v0.0.2 - see [0.0.2-alpha] for v0.0.3)

- **Posts** - Create, edit, publish, archive, delete; draft/published/archived status workflow
- **Rich Text Editor** - Quill-based WYSIWYG editor (`vtx-editor` component)
- **SEO Fields** - Meta title, meta description, estimated reading time
- **Featured Images** - Optional featured image via Media picker
- **Categories** - Full CRUD with post relationship (many-to-many)
- **Tags** - Full CRUD with autocomplete tag input (`vtx-tags`); bulk operations
- **Comment Moderation** - Approve, mark as spam, delete; bulk moderation; pending/approved/spam statuses
- **Analytics Dashboard** - 30-day publication chart (`vtx-chart`); post/category/tag counts
- **Public Frontend** - Blog home, single post with comment form, category listing; all paginated
- **17 permissions** - Granular control over posts, categories, tags, comments, and blog settings

### Media Module (v0.0.1)

- **File Upload** - Drag-and-drop via `vtx-upload`; MIME type + extension validation; randomized filenames
- **Media Grid** - Paginated grid browser (24 files per page)
- **Metadata Editing** - Alt text and caption fields via AJAX modal
- **Media Picker Modal** - Reusable `vtx-media-picker` component for any module form
- **Upload Security** - Files stored in `Public/uploads/YYYY/MM/`; `.htaccess` blocks PHP execution in upload dir
- **4 permissions** - `media.view`, `media.upload`, `media.edit`, `media.delete`

### Database Layer Security Fixes

- **SQL Injection** - Fixed identifier injection in `month()`, `year()`, `day()`, `dateFormat()`, `fullTextSearch()`, `jsonExtract()`, `jsonContains()`, `caseWhen()`, `regexp()`, `ilike()`, `arrayContains()`, `stringAgg()` across `BuildersTrait`, `MySQL`, and `PgSQL` builders
- **`quoteIdentifier()`** - Added safe identifier quoting helper to both MySQL (backtick) and PgSQL (double-quote) builders
- **`bindValue()`** - Added parameterized binding helper for value arguments; all advanced query methods now use bound parameters

### JavaScript Component Library (vtx-*)

- **vtx-chart** - Bar/line/doughnut chart (Chart.js wrapper)
- **vtx-datatable** - Client-side sortable, filterable table with pagination
- **vtx-editor** - Quill rich text editor with hidden textarea sync
- **vtx-media-picker** - Media library picker modal with preview
- **vtx-search** - Debounced live AJAX search
- **vtx-select** - Enhanced select with keyboard navigation
- **vtx-tags** - Tag chip input with AJAX autocomplete
- **vtx-upload** - Drag-and-drop file uploader with progress

### CSS Framework

- Custom flat design CSS framework (Bootstrap 5.3.8 compatible class names)
- Dark/light theme via `[data-theme=dark]` attribute
- CSS custom properties (`--ps-*`) for theming
- 50+ SVG icon system via `.pi .pi-{name}` classes
- Responsive grid, utility classes, form styles, badges, cards, tables, alerts
