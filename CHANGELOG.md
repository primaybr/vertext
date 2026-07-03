# Vertext CMS - Changelog

All notable changes to Vertext CMS are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [0.0.8-alpha] - 2026-07-03

### Core - Accounts & Security

- **Argon2id password hashing** - `Core\Security\Password` (added in the Phuse 1.2.8 sync but never
  wired in) now backs `Auth::attempt()`, `UserModel::hashPassword()`, the setup wizard, and every
  password-setting form; legacy bcrypt hashes upgrade transparently on the next successful login
  via `needsRehash()`
- **Password reset** - `GET/POST /admin/forgot-password` and `/admin/reset-password`; single-use
  SHA-256-hashed tokens with a 24-hour expiry (`password_resets` table, auto-created); anti-enumeration
  responses; rate-limited via the existing `login_attempts` window; completing a reset revokes every
  session for the account; "Forgot your password?" link on the login screen
- **Active sessions** - `user_sessions` table tracks each admin login (device, IP, last active);
  My Profile gains an Active Sessions panel with per-device Revoke and "Sign out everywhere else";
  Users admin shows a session-count badge and lets admins revoke any user's sessions; revoked
  sessions are ended on their next request by a `BaseController` check
- **Avatars** - profile avatar upload (GD center-crop to 128x128 JPEG, stored as
  `Public/uploads/avatars/{user_id}.jpg`, no DB column needed); shown in the admin sidebar, topbar
  user menu, and Users table
- **Fixed a 2FA login regression** - the Phuse 1.2.8 sync made `Session::set($key, null)` throw,
  which broke `AuthController::completePendingLogin()` (runs on every 2FA login) and the 2FA setup
  flow; `set(null)` now unsets the key (fixed in both Vertext's vendored `Core/` and upstream Phuse)

### New Module - Members (v0.0.1)

- Front-end visitor accounts in a new `site_users` table, fully separate from admin `users`
- Public routes: `/account/register`, `/account/login`, `/account`, `/account/verify?token=`,
  `/account/logout`; themed with `--clr-*` variables and dark-mode support
- Email verification (optional via `members_require_verification` install setting); honeypot on
  registration; login rate limiting (separate scope from admin); Argon2id hashing
- `App\CMS\SiteAuth` static helper mirrors `Auth` with its own session namespace, so a member
  session and an admin session coexist
- Admin: `/admin/members` with status tabs (pending/active/suspended), search, in-place AJAX
  activate/suspend, resend-verification, and delete; permissions `members.view`, `members.manage`
- Integrations: Forms pre-fill name/email for logged-in members; Events RSVPs record `site_user_id`;
  webhook `user.registered` fires on account activation

### Forms Builder v0.0.2

- **Conditional logic** - per-field show/hide rule (`equals`, `not equals`, `contains`, `empty`,
  `not empty` against another field); evaluated live in front-end JS and mirrored server-side, so a
  required field hidden by its rule never fails validation
- **Multi-step forms** - new "Step Break" builder block splits the form into pages with a numbered
  progress indicator and validated Next/Back navigation; the server still validates everything on
  the final submit
- **File upload field actually stores files** (v1 rendered the input but dropped the upload) -
  validated by extension + MIME sniff (jpg/png/gif/webp/pdf/txt/doc/docx, 10 MB), stored under
  `Public/uploads/forms/{form_id}/`, linked from the submission detail view
- **Email notification** - optional per-form notification address; sends a formatted submission
  summary via the shared Mailer (`form_notification` template)
- **Anti-spam** - optional math challenge ("what is 3 + 4?", session-verified) and optional
  reCAPTCHA v3 (per-form site/secret keys; server-side verify; scores below 0.5 rejected)
- **`[form slug="..."]` shortcode** - embeds any form inside Pages and Blog posts via the new
  `App\CMS\Shortcodes` resolver; the embed shares one partial (`front/_embed.php`) with the
  standalone `/forms/{slug}` page
- **Fixed: form settings were never saved** - the builder collected the success message but never
  sent it; settings now persist (and are whitelisted server-side)

### Newsletter v0.0.2

- **Audience segments** - `newsletter_segments` table with rule-based filters (source, subscribed
  date range); segment CRUD at Newsletter > Segments with live match counts; campaigns can target
  one segment or all active subscribers
- **Scheduled sends** - schedule a campaign for a future date/time; due campaigns send automatically
  on admin page load (same cron-free pattern as scheduled posts), with a status claim to prevent
  double-sending
- **Open tracking** - per-subscriber 1x1 GIF pixel; unique opens recorded in `newsletter_opens`
  and shown per campaign (atomic counter increments)
- **Click tracking** - campaign links are rewritten through `/newsletter/track/click/{campaign}`;
  per-URL counts in `campaign_links`; the redirect only honors URLs recorded at send time
  (open-redirect protection - unknown URLs bounce to the homepage)
- **Welcome email** - optional subject/body sent once when a subscriber becomes active (immediately,
  or after double opt-in confirmation); full multi-step series deferred
- **CSV file import** - the import modal accepts a .csv upload (BOM-safe) alongside the paste box
- **`[newsletter_signup]` shortcode** - embeddable subscribe box for Pages and posts

### Events v0.0.2

- **Per-attendee RSVPs** - new `event_rsvps` table (name, email, ticket, status, cancel token,
  optional `site_user_id`); replaces the v1 anonymous cookie counter; `events.rsvp_count` now always
  mirrors confirmed registrations
- **Capacity & waiting list** - optional `max_attendees`; when full, new registrations join a
  waiting list; cancelling a confirmed spot auto-promotes the earliest waitlisted attendee (who is
  notified by email); admin can also force status changes with a capacity guard
- **Ticket types** - optional named tickets with display prices (free/paid labels); ticket choice
  is validated and stored per RSVP; no payment processing in this release (display only)
- **iCal** - `GET /events/{slug}/ical` serves an RFC 5545 .ics (with RRULE for recurring events);
  the confirmation email carries an "Add to calendar" link instead of an attachment (the native
  Mailer has no attachment support - deferred rather than risk the hand-rolled MIME builders)
- **Recurring events** - daily/weekly/monthly recurrence with interval and until-date;
  `EventHelper::expandRecurrences()` feeds the public listing and calendar with future occurrences
- **Attendee admin** - `/admin/events/{id}/attendees` with status dropdowns, waitlist counts, and
  CSV export; confirmation/waitlist/promotion emails with cancel links
- **Webhook `event.rsvp`** payload now includes the attendee (name, email, status, ticket)

### REST API v0.0.1

- Read-only JSON API: `GET /api/v1/posts`, `/posts/{slug}`, `/pages`, `/pages/{slug}`, `/events`,
  `/events/{slug}` with the envelope `{data, meta: {current_page, per_page, total, last_page}}`;
  published-content only; `?lang=` filter on posts/pages; `?upcoming=1` on events
- **API keys** - `/admin/api-keys` (permission `api.manage`): create (plaintext shown exactly once,
  only its SHA-256 stored), revoke, delete, last-used tracking; `Authorization: Bearer vtx_...`
- **Rate limiting** - fixed 60-second window: 30 req/min anonymous per IP, 100 req/min per key;
  429 with `Retry-After`; counters in the self-creating `api_rate_windows` table
- `.htaccess` now forwards the `Authorization` header to PHP (fcgid strips it by default)
- Framework note: raw `Connection` DML never commits in this stack (PDO runs without autocommit;
  only `Model` wraps writes in begin/commit) - the rate limiter was rewritten onto the Model API
  and the convention is now documented for module authors

### Media v0.0.4 & Pages v0.0.3

- **Media folders** - `media_folders` table + `folder_id` on files; folder chips with counts, new
  folder / rename / delete (files fall back to Unfiled), bulk "Move to folder", per-folder upload
  targeting, and a folder filter inside the media picker modal
- **Browser image editor** - crop (drag selection), rotate 90 degrees either way, and flip H/V on a
  live canvas preview; "Save as Copy" or "Overwrite Original"; server applies the same operations
  with GD and regenerates the thumbnail
- **Page templates** - `template` column (`default`, `full-width`, `sidebar`, `landing`) with a
  picker in the page form; the front view renders each variant (`sidebar` reads the
  `sidebar_title` / `sidebar_html` custom fields)
- **Custom fields** - `page_meta` table + key/value editor in the page form;
  `PageHelper::getMeta()` / `getAllMeta()` for themes
- **Fixed: pages were unreachable when Blog is mounted at `/`** - Blog's root catch-all shadowed
  the Pages catch-all; a missing post now falls through to the page renderer

### Performance & Caching

- **Full-page cache** (`App\CMS\PageCache`) - opt-in via Settings > Cache; public Pages and Blog
  GETs are served from disk for 10 minutes with an `X-Vertext-Cache: hit` header; automatically
  skipped for logged-in admins/members, pending flash messages, query strings, and any page whose
  HTML embeds a CSRF token (embedded forms are never shared between visitors); invalidated on every
  post/page/nav/settings save
- **Fragment cache** - `NavHelper::getMenu()` cached for 5 minutes; invalidated on any Navigation
  change
- **Asset fingerprinting** - new `asset_url()` helper plus `Version::APP`-derived `?v=` hashes in
  the admin layouts and both theme layouts; replaces the hand-bumped (and drifting) `?v=142`-style
  query strings, so every release busts browser caches automatically
- **Cache stats panel** in Settings (page/fragment/other counts + size) alongside the existing
  Clear All Cache button; `loading="lazy"` on theme layout images

### i18n v0.0.2

- **Locale path-prefix routing** - `/{locale}/...` (e.g. `/id/events`) sets the locale and routes
  normally; unknown prefixes fall through untouched; `hreflang` alternate tags in both theme layouts
- **Content language filtering** - blog listings filter by the visitor's locale when that locale has
  posts (graceful fallback to everything otherwise); posts and pages gain a Language selector in
  their edit forms
- **Translation manager** - `/admin/translations` (permission `translations.manage`): edit any
  locale's strings against the English reference, untranslated-string highlighting and counts, and
  "Add Locale" scaffolding; writes are atomic and round-trip validated before replacing the live
  `App/Lang/{locale}/{group}.php` file

---

## Upcoming

### [0.0.9-alpha] - Theming & Styling Overhaul

Dedicated visual release driving toward a production-ready beta (0.1.0). Principles: simple,
elegant, professional, flat, no gradients - at enterprise standard.

- **Wider layout system (front-end)** - industry-standard widths (design target 1440 px): default
  `.container` grows from 700-760 px to ~1240 px, new `.container-prose` (~740 px) preserves
  readable article measure, `.container-wide` at ~1440 px; applied to both bundled themes
- **Wider layout system (admin)** - content area capped at 1584 px (IBM Carbon grid max) and
  centered on ultra-wide monitors; wider modals; denser data tables; a documented spacing scale
- **Design-token unification** - one documented token set bridging `--ps-*` (admin) and `--clr-*`
  (themes); unify the two dark-mode localStorage keys (`phuse-theme` vs `vtx-theme`); Theme
  Customizer extended to drive front-end tokens
- **Flat design audit** - remove remaining shadows/decoration inconsistencies, normalize radius and
  border tokens, WCAG AA contrast pass in both light and dark modes, visible focus states
- **Component polish** - a single visual language for tables, forms, buttons, cards, modals,
  toasts, empty states, and pagination across admin, themes, and every module view
- **Typography scale** - modular heading scale and consistent line-height rhythm
- **Responsive retune** - breakpoints re-checked at 360 / 768 / 1024 / 1440 / 1920

---

## [0.0.7d-alpha] - 2026-07-02

### Blog Module - Base Path Change Bugs

Changing Blog's front-end base path (Blog Settings) had three compounding bugs, all reproduced and
verified live against the `vertext` database/site before and after the fix.

- **Fixed other modules' front-end routes 404ing when Blog's base path was set to `/`** -
  `Blog/Module.php::registerRoutes()` registered its post-slug route as `/([a-z0-9\-]+)`, which
  collapses into a global single-segment catch-all once the base path is root. Because
  `ModuleManager::loadRoutes()` loads modules alphabetically, Blog's catch-all was inserted ahead of
  Contact/Events/Gallery/Search/Videos's own specific routes, and the router's first-match-wins
  matching shadowed them permanently - re-syncing views or clearing the cache couldn't fix it, since
  the bug was in route *order*, not cached content. Moved the root-only catch-all out of the module
  and into `Config/Routes.php`, registered after `ModuleManager::loadRoutes($router)` (same pattern
  already used by `Pages/Module.php` for its own catch-all, see the comment there). Verified live:
  `/contact`, `/events`, `/search`, `/videos`, `/gallery` all 404'd with Blog at `/` before the fix
  and returned 200 after, with no code changes other than clearing the route cache.
- **Fixed path changes not updating navigation or the view cache** - `BlogSettingsController::save()`
  and `BlogSetupController::complete()` only called `ModuleManager::clearRouteCache()` on a path
  change; Blog's own primary-nav item (seeded once at install time) was never kept in sync, and
  compiled view templates were never purged. Added `Blog\Module::syncNavItem()`, called from both
  controllers, and added a `Core\Cache\TemplateCache::clear()` call inside
  `ModuleManager::clearRouteCache()` so both route and view cache are purged together.
- **Fixed Blog's nav item not disappearing when it becomes the homepage** - `syncNavItem()` removes
  Blog's primary-nav entry when the new base path is `/` (a "Blog" link to the homepage is redundant
  once Blog serves it), and re-creates it if the path later moves off root. `Module::install()` now
  skips seeding the nav item in the first place when installed directly at root.
- **Fixed two other places that could resurrect the stale "/blog" nav link independently of the
  above** - both read the *static* `"/blog"` default from `module.json`'s `nav_routes`, with no idea
  the path is runtime-configurable or that root means "hide it": `NavHelper::buildFromModuleRoutes()`
  (the fallback that fills in nav items for modules not yet present in the DB-driven menu - this is
  exactly what re-added the link after `syncNavItem()` removed it, since removing the DB row is what
  makes the fallback think Blog was never synced) and
  `NavigationController::syncModules()`/`builder()` (the "Sync Modules" admin action and its available-
  routes list). Both now resolve Blog's path via `Blog\Module::basePath()` live and skip it entirely
  at root, via a shared `resolveModuleRoutePath()` helper. Verified live: `/admin` and the front-end
  both stopped showing `/blog` after clearing the two stale rows these had independently inserted.
- **Found (not fixed) a latent ORM bug while building the above**: `BuildersTrait::where($key,
  $value)`'s operator/value auto-detection (`Core/Database/Builders/BuildersTrait.php:12-15,328-333`)
  misreads a 2-arg call as `where($key, $operator)` whenever `$value` case-insensitively matches one
  of its whitelisted operator tokens (`+ - * / % & | ^ = > < ... AND OR LIKE IN ...`). A value that
  happens to be exactly `/` (e.g. a root URL) gets used as a literal SQL operator instead of a bound
  value, corrupting the query. Hit while matching Blog's nav item by URL; worked around by matching
  on `(type, label)` instead. Left unfixed since `Core/` is vendored framework code, not
  Vertext-owned - flagging here for awareness since it can silently corrupt any `where()` call with
  a coincidentally operator-shaped value.

---

## [0.0.7c-alpha] - 2026-07-02

### Phuse Framework Sync (1.2.6 -> 1.2.8)

Six real bugs were found and fixed while writing new test coverage for Phuse 1.2.8, all of which
also existed in Vertext's own copy of the same Core code (Vertext historically vendors Phuse's
`Core/` directory rather than pulling it as a Composer dependency). All fixes below were verified
directly against Vertext's live PostgreSQL database and the running site (`vertext.test`), not just
in isolation.

- **Fixed a dormant slug-generation crash** - `Core\Http\URI::makeURL()` failed on every call (a
  regex delimiter collision), though nothing in Vertext's app code called it directly (Vertext uses
  `Str::slug()` for actual slug generation) - fixed for correctness and for any future/module code
  that might call it.
- **Fixed silently-broken file caching** - `Core\Cache\FileCache::isCacheValid()` compared a raw
  serialized string against an array key, so every cached entry was reported expired immediately
  after being written. This was actively degrading Vertext's caching (query/template caching is
  enabled in `Config/Database.php`) - every cache read was silently forcing a fresh regenerate.
- **Fixed a connection-lifecycle fatal risk** - `Drivers\PgSQL`/`MySQL::connect()` swallowed
  `PDOException` via `echo` instead of rethrowing, `Connection::__construct()` never validated the
  resulting PDO handle, and a since-removed `Connection::__destruct()` raced against
  `ConnectionPool`'s shutdown-time cleanup - together these could fatal with "call to a member
  function prepare() on null" (an `\Error`, uncaught by the pool's `\Exception`-only catch blocks).
  This is Vertext's live, only-used database driver path - fixed and verified against the real
  `vertext` PostgreSQL database, including forcing garbage collection to confirm no shutdown fatal.
- **Fixed the MySQL query builder** (`Core\Database\Builders\MySQL`/`BuildersTrait`) - dead,
  accidentally-commented-out `compile()`/`resetQuery()` code in `BuildersTrait` (the tail of an old
  implementation got swallowed into a docblock that never closed with `*/`). Not directly load-bearing
  for Vertext (Postgres-only in production), but `PgSQL.php`'s own duplicate `compile()`/`resetQuery()`
  were removed in favor of the now-shared trait versions - verified Vertext's Postgres-specific
  overrides (`quoteIdentifier()` double-quoting, `insertIgnore()` `ON CONFLICT`, `ilike()`, etc.)
  still work correctly afterward.
- **Fixed non-functional encryption** - `Core\Security\Encryption` never actually worked: its
  SHA-512 key never matched any AES-256 cipher's 32-byte requirement, and the configured cipher
  (`aes-256-cbc-hmac-sha256`) itself fails under `openssl_encrypt()` on OpenSSL 3.x regardless of
  key length. Switched to SHA-256 key derivation + `aes-256-cbc`. Not currently used anywhere in
  Vertext's app code, so no data-migration concern.
- **Fixed `CacheManager` preset methods** - `createMemoryConfig()`/`createFileConfig()` used
  snake_case option keys that don't match `CacheConfig`'s camelCase properties, so the presets
  silently had no effect. Not currently called from Vertext's app code.

**New capabilities pulled in from Phuse 1.2.7/1.2.8:**

- **Router named routes** - `$router->get(...)->name('users.edit')` + `$router->route('users.edit', [$id])` for reverse URL generation, alongside the existing UUID-safe route capture casting and FQCN module-controller resolution (both preserved).
- **`Core\Security\Password`** (new) - `hash()`/`verify()`/`needsRehash()` wrapper (Argon2id default, bcrypt fallback).
- **9 new Validator rules** - `date`, `datetime`, `uuid`, `fileType`, `fileSize`, `confirmed`, `distinct`, `json`, `unique` (the DB-backed one, via `Core\Model`), plus the `password` rule from 1.2.7.
- **4 new Middleware** for the `MiddlewareStack` pipeline - `RateLimitMiddleware`, `TrimStrings`, `ConvertEmptyStringsToNull`, `LogRequest`.

`Version::PHUSE` bumped to `1.2.8`.

### Phuse Framework Sync - Bugs Found via Phuse's Unit Test Hardening Pass

Fixing Phuse's own pre-existing test suite backlog (14 errors/27 failures/2 risky, unrelated to
the 1.2.8 work above) surfaced 5 more real bugs in the same vendored Core code Vertext carries -
all fixed here and verified against the live `vertext` database and running site.

- **Fixed a dead-code trap**: `Core\Exception\SystemException` and `ConfigurationException` were
  referenced throughout (`Container.php`, `Base.php`, `Http/URI.php`, `Http/Session.php`) but only
  ever defined inside `Core/Exception/PhuseExceptions.php` - a file bundling six exception classes
  that the autoloader (direct namespace-to-filepath mapping) can never actually load. Extracted
  both into their own properly-named files; deleted `PhuseExceptions.php` entirely (confirmed
  unreferenced anywhere in Vertext, same as in Phuse - its other four classes were exact-name
  duplicates of already-used standalone files, or (`FilesystemException`) unused).
- **Fixed `Core\Utilities\Image\ImageTrait::setImageSource()`** - called `$this->getImageType()`,
  a method that was never implemented anywhere, so every single `Image` operation fataled.
  Implemented via `getimagesize()` + `image_type_to_extension()`. Vertext's `App/` code doesn't
  call `Core\Utilities\Image\Image` directly today, but this was a real landmine for any future use.
- **Fixed `Core\Utilities\Upload\UploadConfig::setAllowedMimes()`** - its validation regex rejected
  `.` in MIME subtypes, so the real, IANA-registered MIME type for `.docx` files was rejected as
  "invalid format." Widened to the full RFC 6838 token character set (`!#$&-^_.+`).
- **Fixed `UploadConfig::setImageDimensions()`** - unconditionally required all four dimensions to
  be positive, but `forDocuments()` deliberately passes `(0, 0, 0, 0)` as a documented sentinel
  meaning "skip image validation" (documents aren't images). Added a special case allowing that
  exact all-zero combination through. Vertext's Media module currently only uses
  `UploadConfig::forImages()` (`App/Modules/Media/Controllers/Admin/MediaController.php`), so
  neither Upload bug was hit in production today, but both would have blocked a future
  "Documents" upload type immediately.

---

## [0.0.7b-alpha] - 2026-07-01

### Phuse Framework Sync (1.2.5 -> 1.2.6)

- **Icon system split** - `.pi` / `.pi-*` rules moved out of `styles.css` into a dedicated `Public/assets/css/icons.css`, matching upstream Phuse 1.2.6; `styles.css` now pulls it in with `@import url("icons.css?v=1")` placed before all other rules (a mid-file `@import` is silently ignored by browsers per the CSS spec - verified in-browser after the fix)
- **25 new icons** available: `pi-clipboard`, `pi-spinner`, `pi-circle`, `pi-map`, `pi-verified`, `pi-shopping-cart`, `pi-print`, `pi-play-circle`, `pi-minus-circle`, `pi-key`, `pi-puzzle`, `pi-package`, `pi-languages`, `pi-send`, `pi-log-in`, `pi-log-out`, `pi-help-circle`, `pi-rss`, `pi-share-2`, `pi-thumbs-up`, `pi-flag`, `pi-server`, `pi-cloud`, `pi-wrench`, `pi-building` - fixes several icons that were already referenced in admin views (Forms, ModuleLoader fallback, webhook logs) but rendered blank because the icon didn't exist yet
- Vertext's own manually-added `pi-clipboard` (added locally as a stopgap before upstream had one) is now superseded by upstream's - same shape, no visual change
- `Version::PHUSE` bumped to `1.2.6`; `styles.css?v=` cache-bust query bumped to `142` across all views
