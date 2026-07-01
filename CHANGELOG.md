# Vertext CMS - Changelog

All notable changes to Vertext CMS are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [0.0.7-alpha] - 2026-07-01

### Core

- **Two-factor authentication (2FA)** - TOTP (RFC 6238) via the `TwoFactor` module; `AuthController` adds a second step after successful credentials; `TotpHelper` handles key generation, QR code URL, and 30-second window validation; backup codes (8 one-time codes, bcrypt-stored); cookie-based trusted-device option (30 days); enable/disable flow in My Profile at `/admin/profile/2fa`; install setting for TOTP issuer name
- **i18n Foundation** - `App\CMS\I18n`: `setLocale()`, `getLocale()` (session then `default_locale` settings fallback), `get(key, replacements[])` with dot-notation file groups (`admin.save` loads `App/Lang/{locale}/admin.php` key `save`), `date(timestamp, format)` using `IntlDateFormatter` when `ext/intl` is available; `App/Lang/{locale}/{file}.php` file structure; English (`en`) and Indonesian (`id`) stubs included; global `__()` helper loaded by `Core/Boot.php`; locale switcher `<select>` in admin topbar; `POST /admin/settings/set-locale`; `GET /?lang={locale}` sets session for front-end visitors; `lang VARCHAR(10) NOT NULL DEFAULT 'en'` added to `posts` and `pages` CREATE TABLE; `I18n::migrate()` wired into Settings > Run Migration for existing installs
- **Module scaffold CLI** - `php vertext make:module Foo` generates `App/Modules/Foo/` with `Module.php`, `module.json`, stub controller, and stub views; `php vertext make:bundle Foo` generates `App/Bundles/foo/bundle.json` skeleton; binary at `vertext` in the project root; see [docs/cli.md](docs/cli.md)

### New Modules

#### Forms Builder (v0.0.1)

- `form_definitions` table (id, name, slug, description, fields JSON, notification_email, success_message, status) and `form_submissions` table (id, form_id, data JSON, ip, user_agent, submitted_at)
- Admin CRUD: field builder with drag-to-reorder; field types: text, textarea, email, select, checkbox, radio; CSV export of all submissions per form
- Front-end public form at `/forms/{slug}` with honeypot field and per-form rate limiting (3 attempts per 60-second window per IP)
- Webhook dispatch: `form.submitted` event with full submission payload after each accepted entry
- Permissions: `forms.view`, `forms.manage`, `forms.export`

#### Newsletter (v0.0.1)

- `newsletter_subscribers` (email, name, confirmed, token, subscribed_at, unsubscribed_at) and `newsletter_campaigns` (subject, html_body, sent_at, recipient_count)
- Double opt-in: subscription inserts a pending record; confirmation link in the verification email sets `confirmed = true`
- Admin: subscriber list with status filters; CSV import/export; campaign creation with Quill HTML editor; test-send to admin email before blast; unsubscribe link injected into every sent email
- `install_settings` for `newsletter_from_name` and `newsletter_from_email` shown during install wizard
- Webhook dispatch: `newsletter.subscribed` and `newsletter.unsubscribed` events
- Permissions: `newsletter.view`, `newsletter.manage`, `newsletter.send`

#### Events (v0.0.1)

- `events` table (title, slug, description, location, start_at, end_at, max_attendees, status, featured_image_url + 6 audit columns) and `event_rsvps` table (event_id, name, email, notes, attended, rsvped_at)
- Admin CRUD with date/time pickers, location field, max attendees cap; RSVP list per event with attended toggle and CSV export
- Public listings: upcoming and past tabs; Canvas calendar sidebar (accent dots on event days, retina-aware with `devicePixelRatio`, `vtx:themeChanged` listener); click a date scrolls to that day's cards
- Public detail page: two-column layout (collapses at 720 px); RSVP form with cookie-based duplicate prevention per browser; RSVP automatically closed when at capacity or event is in the past; flash messages from session
- Webhook dispatch: `event.rsvp` event after each accepted RSVP
- All CSS uses `--clr-*` variables; dark-mode overrides for semantic alert colors
- Permissions: `events.view`, `events.manage`, `events.rsvp`

### Bundle System Extensions

- **Two new built-in bundles** - Marketing Suite (Forms + Newsletter + Analytics + Webhooks + Contact) and Events Portal (Events + Navigation + Contact + Analytics + Sitemap)
- **Updated bundles** - Content Portal (added Forms + Newsletter); Full Stack (all 15 modules); Business Site (added Forms); all four original bundles gain `"builtin": true`
- **Custom bundle builder** - `GET /admin/modules/bundles/create` / edit / update / delete for non-builtin bundles; bundle stored as `App/Bundles/{slug}/bundle.json` with `"custom": true`; icon picker, category selector, module checklist with per-module "Required" toggle; live preview card in sidebar
- **Configure step in bundle install modal** - checklist (step 1) - configure settings (step 2) - progress (step 3); step 2 only shown when a selected, non-installed module declares `install_settings`; per-module values stored to `settings` table after install
- **A-la-carte configure overlay** - individual module install button opens a configure modal when the module has `install_settings`; values stored after install

### Module Marketplace

- **Install from URL** - "Install from URL" button in Module Manager toolbar opens a two-step modal
- `POST /admin/modules/fetch-url`: downloads a ZIP from any HTTPS URL (50 MB max), validates via magic bytes, reads `module.json` from root or GitHub archive nested directory, returns metadata preview and SHA-256 hash for review
- **SSRF prevention** - hostname resolved to IP with `gethostbyname()`; `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` blocks private and reserved ranges; HTTPS-only enforcement
- **One-use session token** - hash stored in session at fetch time; `POST /admin/modules/install-from-url` verifies hash before extracting; path traversal protection on all entries; GitHub archive top-level prefix stripping
- **ZipArchive guard** - both endpoints return a clear user-facing error if `php_zip` is not loaded
- `ModuleManager::removeExtractedDir()` - cleanup on failed installs

---

## Upcoming

### [0.0.8-alpha]

#### User Authentication (Front-end)

- Public registration, login, and profile pages for site visitors (`site_users` table separate from admin `users`)
- Email verification; session-based auth for front-end modules (Forms pre-fill, RSVP owner tracking)

#### Module Enhancements

- **Forms Builder v2** - conditional field logic; file upload field type; reCAPTCHA v3 integration
- **Newsletter v2** - subscriber segments; scheduled campaigns; basic welcome-series automation
- **Events v2** - ticket types (free, paid); waiting list when capacity reached; iCal export (`.ics`)
- **REST API** - JSON API endpoints for Pages, Blog Posts, and Events; API key authentication; rate limiting

#### DX / Infrastructure

- **Media folders** - organize the media library into named folders; folder picker in the upload dialog and media picker modal
- **Performance** - query result caching for public page/post renders; asset fingerprinting for HTTP cache-busting
- **i18n v2** - translation management UI in admin; URL path-prefix routing (`/id/...`); `lang` column filtering on public page/post queries

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

### Added

- **Maintenance Mode** - `App\CMS\Maintenance::check()` called from `Config/Routes.php` before routing; bypasses `/admin`, `/setup`, and logged-in admin sessions automatically; serves `App/Views/maintenance.php` - standalone 503 page with inline CSS and dark mode support; settings panel shows a pill toggle wired to `POST /admin/settings/toggle-maintenance`
- **`App\CMS\Version`** - canonical version constant (`Version::APP`); admin sidebar reads from it instead of a hardcoded string
