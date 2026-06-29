# Vertext CMS - Changelog

All notable changes to Vertext CMS are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [0.0.6-alpha] - 2026-06-29

### Core

- **Role/permission builder UI** - `GET /admin/roles/permissions`: all permissions grouped by module; inline "New Permission" form auto-generates slug from name; create custom permissions at runtime (module = `custom`); delete custom permissions only; audited; no code changes required
- **Admin audit log UI** - `GET /admin/audit-log`: paginated (50/page) filterable view of `audit_logs` table; filter by free-text search (action, resource type, resource ID, user name), action type dropdown, and from/to date range; LEFT JOIN on users for display name; System shown for background actions; action badges color-coded by type (create/update/delete/login/other)

### Blog (v0.0.4 -> v0.0.6)

- **Related posts** - up to 4 posts sharing tags or categories shown below each post view; raw SQL with EXISTS subqueries for tag and category overlap; gracefully empty when no shared content exists
- **Reading list** - "Save to Reading List" / "Saved" toggle button in the post header; stores `{id, title, slug, url}` entries in `localStorage['vtx_reading_list']`; no account required; persists across sessions; button state reflects current saved status on page load
- **Threaded comments** - `parent_comment_id UUID` added to `blog_comments` (FK to self, ON DELETE CASCADE); `BlogController::threadComments()` builds tree from flat list; top-level comments show inline "Reply" form with collapsible toggle; replies indented under parent with left border; `submitComment()` validates parent belongs to same post and is approved before accepting `parent_comment_id`
- **Post series** - `post_series` table (id, title, slug, description + audit cols); `post_series_posts` pivot (series_id, post_id, sort_order); admin CRUD at `GET /admin/blog/series`; form shows all blog posts with checkbox + sort_order input; front-end: purple series box above post body shows series title, all parts as ordered list (current highlighted), prev/next navigation links
- **Series admin** - new `SeriesController` with index, createForm, store, editForm, update, delete; `syncSeriesPosts()` replaces post assignments atomically; "Series" subnav entry added to Blog module nav

### Analytics (v0.0.3 -> v0.0.4)

- **Search term tracking** - `analytics_search_queries` table (query, result_count, ip_hash daily-salted SHA-256, searched_at); `Tracker::recordSearch()` called by `SearchController` after result count is known; dashboard shows top 10 terms + zero-result terms panel; graceful on existing installs missing the table
- **JSON export** - `GET /admin/analytics/export?format=json` streams `application/json` with full rows array; existing CSV export unchanged
- **Export buttons** updated to show separate CSV and JSON download links

### Module Manager - Bundle Packages

- **Packages tab** - Module Manager gains a second tab ("Packages") alongside the existing a la carte "Modules" tab; Packages is the default view; tab state persisted in `localStorage`
- **Bundle manifests** - `App/Bundles/{slug}/bundle.json` schema: `name`, `slug`, `icon`, `category`, `description`, and `modules[]` with per-entry `required` flag; required modules cannot be deselected during install
- **Bundle status** - each bundle card shows `Not Installed`, `Partial (n/total)`, or `Installed` based on which of its modules are currently enabled
- **Bundle install modal** - click "Install Bundle" to open a checklist; required modules are pre-checked and locked; optional modules are pre-checked but togglable; progress panel streams per-module install status before reloading
- **`ModuleManager::getBundles()`** - scans `App/Bundles/*/bundle.json`, annotates each module entry with install status, returns bundle list for the UI
- **`ModuleManager::installBatch(array $slugs)`** - topological sort (Kahn's algorithm) then sequential install; skips already-installed modules; returns per-slug `{success, name, message, skipped}` result map
- **Four built-in bundles**: Content Portal (Blog + Search + Navigation + Analytics + Contact + Sitemap), Media Showcase (Media + Gallery + Videos + Navigation + Analytics), Business Site (Pages + Contact + Navigation + Analytics + Sitemap), Full Stack (all available add-on modules)
- **`pi-briefcase`** icon added to `styles.css` for the Business Site bundle
- **`POST /admin/modules/install-bundle`** - new AJAX endpoint consumed by the bundle install modal

---

## Upcoming

### [0.0.7-alpha]

#### Core

- **Multi-language / i18n** - language switcher, translatable content fields, locale-aware date/number formatting
- **Two-factor authentication (2FA)** - TOTP (RFC 6238) authenticator app support for admin users; no external dependencies; hook in `AuthController::processLogin()` after `Auth::attempt()` succeeds

#### New Modules

- **Forms Builder** - drag-and-drop custom form creation; extends Contact module patterns; stores submissions in DB; email notification on submission
- **Newsletter** - subscriber list management + email blast; integrates with Webhooks for delivery events; unsubscribe link in every email
- **Events** - event listings with date, location, RSVP count; front-end calendar view

#### Extended Bundle System

- **Bundle customizer** - configure module settings (e.g. `blog_base_path`) inline during bundle install before committing
- **Custom bundle builder** - admin UI to compose and save a named bundle from installed modules
- **New bundles**: Marketing Suite (Newsletter + Forms Builder + Analytics + Webhooks + Contact), Events Portal (Events + Contact + Navigation + Analytics + Sitemap)

#### DX / Infrastructure

- **Module scaffold CLI** - `php vertext make:module Foo` generates boilerplate module files (Module.php, module.json, controller, views)
- **Module marketplace** - install a module directly from a URL via the Module Manager UI
- **`php vertext make:bundle Foo`** - generates `App/Bundles/foo/bundle.json` skeleton

---

## [0.0.5-alpha] - 2026-06-28

### Core

- **Content Revisions** - snapshot before every update on Posts and Pages; `content_revisions` table shared between both modules; revision list per content item; restore from any revision with a single click; snapshot captures title, body/content, and status
- **Scheduled/Expired Publishing** - `published_at` and `expire_at` columns on both Posts and Pages; public queries filter at render time (`(status='published') OR (status='scheduled' AND published_at <= NOW()) AND (expire_at IS NULL OR expire_at > NOW())`) with no cron required; "Scheduled" tab in Posts admin; "Live (scheduled)" badge when a scheduled post has passed its `published_at`; `expire_at` field in both post and page forms
- **vtx-select Standardization** - all admin `<select>` elements gain `data-vtx-select` for consistent searchable dropdown UX; class normalized to `form-select` across all module forms

### Navigation Module (v0.0.1 -> v0.0.2)

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

### Analytics (v0.0.2 -> v0.0.3)

- **Unique visitors** - count distinct `ip_hash` per period; KPI card in the analytics dashboard
- **Device breakdown** - mobile vs desktop split from User-Agent string; displayed as percentage in the dashboard

### Docs

- **Module dependency system** - `docs/module-system.md` updated to document `requires.modules` in `module.json`, install guards (`checkModuleDeps()`), and uninstall protection (`checkDependents()`) - fully implemented since v0.0.3 but previously undocumented

---

## [0.0.4-alpha] - 2026-06-25

### Core

- **Admin profile page** - `GET /admin/profile` + `POST /admin/profile/update`: any logged-in user can update their own display name, email address, and password without needing the Users management permission; email uniqueness validated against other users; passwords hashed with bcrypt cost 12; change logged to audit trail

### Blog (v0.0.3 -> v0.0.4)

- **RSS feed** - `GET /{blog_base}/feed.rss`: RSS 2.0 feed of the 20 most recent published posts; includes `atom:link`, `content:encoded` (full post body via CDATA), and `<enclosure>` for featured images; auto-linked via `<link rel="alternate" type="application/rss+xml">` in both theme `<head>` elements when Blog is enabled; `feedUrl` computed centrally in `ThemeEngine` to avoid modifying every render call

### Media (v0.0.2 -> v0.0.3)

- **Bulk actions** - checkbox overlay on every media card (shown to users with `media.delete`); select-all toggle in the bulk action bar; bulk delete sends `POST /admin/media/bulk` with CSRF; physical files and thumbnails deleted from disk before DB rows removed; batch uses `whereRaw("id IN (...)")` for efficient single-query deletion; `Auth::audit('media.bulk_delete')` records count
- **Bulk action bar** - slides in when >=1 card is selected; shows selection count; `vtxConfirmModal` confirms before delete; `VtxAjax.postForm` submits and reloads grid on success

### Analytics (v0.0.1 -> v0.0.2)

- **Date range filter** - from/to date pickers in the dashboard header; quick presets (Today, 7 Days, 30 Days, 90 Days); all stats, chart, top pages, and top referrers now reflect the selected period instead of being hardcoded to 30 days
- **Period comparison** - "Selected Period" KPI card shows delta vs the immediately preceding equivalent period; "Today" card shows delta vs yesterday; computed as `round((current - prev) / prev * 100, 1)%`; "no prior data" shown when previous period is zero
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
- Payload signing: `X-Vertext-Signature: sha256={HMAC-SHA256}`, `X-Vertext-Event`, `X-Vertext-Delivery` headers on every request
- Admin UI: endpoint list with last-delivery status badge; create/edit form with event checkboxes and secret regeneration; delivery log table (last 50 per endpoint); test ping button fires a `ping` event immediately
- Available events: `post.published`, `post.deleted`, `page.published`, `page.deleted`, `media.uploaded`, `media.deleted`, `ping`
- Permissions: `webhooks.view`, `webhooks.manage`; both auto-granted to Administrator on install

### JavaScript Component UI/UX Overhaul

All `vtx-*` components that used inline `cssText` or inline `style` attributes injected from JavaScript have been refactored to use CSS classes only.

- **`vtx-slug`** - removed hardcoded `style="color:..."` from reset link; added `.vtx-slug-hint` and `.vtx-slug-reset` classes to `admin.css`
- **`vtx-upload`** - removed `element.style.cssText` from overlay and progress bar; `.vtx-upload-overlay`, `.vtx-upload-bar` moved to `admin.css`
- **`vtx-tags`** - removed all inline style assignments; `.vtx-tags-wrap`, `.vtx-tags-dropdown`, `.vtx-tag-chip-remove` moved to `blog.css`
- **`vtx-media-picker`** - removed modal CSS injected via `panel.style.cssText`; all classes moved to `media.css`

### Added

- **Maintenance Mode** - `App\CMS\Maintenance::check()` called from `Config/Routes.php` before routing; bypasses `/admin`, `/setup`, and logged-in admin sessions automatically; serves `App/Views/maintenance.php` - standalone 503 page with inline CSS and dark mode support; settings panel shows a pill toggle wired to `POST /admin/settings/toggle-maintenance`
- **`App\CMS\Version`** - canonical version constant (`Version::APP`); admin sidebar reads from it instead of a hardcoded string

### Fixed

- **Clean theme visual overhaul** - `--clr-border` changed from near-black to soft gray; all `2px solid` borders reduced to `1px solid`; `--radius-sm` and `--radius-md` CSS variables added
- **Media thumbnail generation** - replaced Phuse `Image` wrapper (silently short-circuits on validation errors) with raw GD functions; `loadGdImage()` and `saveGdImage()` helpers; cover-crop math unchanged
- **UUID router** - `Core/Router.php` was casting numeric-looking route captures to `int`; caused `TypeError` when UUID segments (e.g. `"1"`) reached `string $id` parameters; fixed with `array_map('strval', $matches)`
- **`alert()` replaced with `Phuse.toast()`** - all module views used `vtxToast()` which does not exist; replaced with `Phuse.toast(message, type)` in Media, Contact, Webhooks, and Gallery
- **Blog featured image UUID cast** - `(int)` cast on a UUID string produced a leading-digit integer which failed PostgreSQL's uuid type check; fixed

---

## [0.0.3-alpha] - 2026-06-24

### New Modules

#### Navigation (v0.0.1)

- Admin menu builder: create named menus, add items (custom URL, page slug, or module link), single-level parent/child nesting, drag-to-reorder
- Tables: `nav_menus` (UUID, name, slug) and `nav_items` (UUID, menu_id, parent_id, type, label, url, page_slug, sort_order, open_in_new)
- Seeds "Primary Navigation" (slug: `primary`) on install
- `NavHelper::getMenu(string $slug)` - static helper called from theme layouts; returns nested items array with per-request caching
- Both default and clean themes automatically render the primary menu via NavHelper, with dropdown support for nested items
- Permissions: `navigation.view`, `navigation.manage`

#### Analytics (v0.0.1)

- Privacy-friendly page-view tracking: `url_path`, `page_title`, `referrer_host` (hostname only), `ip_hash` (SHA-256 with daily salt - not reversible), `viewed_at`
- Bot filter: 18 user-agent patterns (Googlebot, Bingbot, Slurp, and others)
- `Tracker::record()` called automatically from `ThemeEngine::render()` when module is enabled; wrapped in try-catch
- Admin dashboard: today/week/month view counts, top 10 pages, top 10 referrers, 30-day daily chart (pure Canvas, no external library)
- Permissions: `analytics.view`, `analytics.manage`

### New Theme

#### Clean (v0.0.1)

- Typographic, editorial theme: Georgia/serif body font, black borders, uppercase nav, 2-px thick accents
- Full dark/light mode support (same CSS layer pattern as default theme)
- NavHelper integration - renders Primary Navigation menu same as default theme

### Front-End Theme Improvements

- **Dark/light mode** on both `default` and `clean` themes: three CSS layers (`:root`, OS preference, explicit override), FOUC-prevention inline script, theme toggle button, `localStorage` persistence
- **styles.css as base layer** - both theme layouts load `Public/assets/css/styles.css` before `theme.css`

### Admin - Theme Manager

- **Promoted to a system module** - Theme Manager is now a core admin section at `GET /admin/themes` with its own sidebar nav entry
- **`ThemesController`** - new controller with `index()` (theme card grid) and `setTheme()` (POST handler)
- **Removed from Settings** - the Themes tab is gone from Admin - Settings

### Admin - Module Manager Overhaul

- **Categorized card layout** replaces the previous two-table layout
- **System section** (collapsible) - core modules shown as compact read-only rows; collapsed by default
- **Category sections** - add-on modules grouped by category with card grids; each card shows icon, name, version badge, description, status badge, and action buttons
- **`category` field** added to all `module.json` files

### Module Dependency System

- `module.json` `requires.modules` array: declare which other modules must be installed first
- `ModuleManager::checkModuleDeps()` - blocks install if required modules are missing
- `ModuleManager::checkDependents()` - blocks uninstall if other installed modules depend on this one
- `ModuleManager::getDependencyInfo()` - public method returning per-slug install status for Module Manager UI

### Icons

- `pi-bars` and `pi-chart-bar` added to `Public/assets/css/styles.css` following the existing `mask-image` + URL-encoded SVG data URI pattern

### Mail

- **Comment notification to post author** - when a visitor submits a comment and `comments_require_approval` is enabled, the post author receives an email notification via `comment_pending` template
- **New user welcome email** - welcome email sent automatically on user creation via `welcome` template
