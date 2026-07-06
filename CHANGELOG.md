# Vertext CMS - Changelog

All notable changes to Vertext CMS are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Upcoming] - 0.1.0

Production-readiness beta: safe defaults on install (`env` flips to `production`), CI +
runnable test suite, feature test coverage for CMS modules, a general migration system,
backup/restore tooling, security headers on the public site and API (not just admin), and
upgrade/troubleshooting docs. Scoped as a public self-host beta, not enterprise-hardened -
see the eventual release notes for what's explicitly deferred.

## [0.0.9c-alpha] - 2026-07-06

Five more bugs found through manual testing, plus a framework-level fix in the underlying Phuse
engine (now v1.2.8b).

- **Contact Settings crashed with a PHP parse error on every load** - a malformed method call
  (`$this->flash('flash' => 'success', ...)`, not valid call syntax) in `ContactSettingsController`.
- **Media Library's "move file to folder" and Blog's bulk comment moderation (approve/spam/delete)
  always failed** - both built their `id IN (...)` SQL with mismatched positional/named parameter
  styles, which PDO rejects. This is also why every folder's file counter always showed 0: no file
  could ever actually be moved into one. Fixed, and found the identical root cause is itself
  traceable to a bug in Phuse's query builder - `whereIn()` combined with `update()` or `delete()`
  silently dropped part of the WHERE clause. Fixed at the framework level (Phuse v1.2.8b) and
  verified against a live database.
- **Media's bulk action toolbar was invisible to any role without delete rights** - the entire
  bulk-select UI (including "Move to folder", which only needs edit rights) was gated behind the
  delete permission. Each action now shows based on its own required permission.
- **Members' Account page had a narrower layout than every other front-end page** - its title/header
  row was never placed in the shared page container, so it sat at a different horizontal position
  than Search, Videos, Gallery, etc. Also added a site-wide fix for a related but separate visual
  glitch: short pages without a scrollbar rendered a few pixels wider than tall ones, making the
  shared header look inconsistent between pages.
- **Form Builder's field-type buttons rendered with no styling** - their CSS was declared as a
  front-end asset instead of an admin one, so it was never loaded on the admin page that uses it.
  Also added the project's missing `.form-select-sm` style (present for `.form-control` but never
  defined for `.form-select`).

## [0.0.9b-alpha] - 2026-07-06

Two bugs found through manual testing after the 0.0.9 styling/identity pass, both fixed.

- **Blog's reading-list button stopped swapping to a checkmark icon after saving a post** -
  a regression from replacing its old emoji-based icon with the shared icon set: the module's
  asset version wasn't bumped, so browsers kept serving the previously-cached script against the
  new markup. Bumped Blog to `0.0.8` to bust the cache.
- **Every save on the Videos admin page failed with "Security token invalid"** - the create/edit
  form's hidden CSRF field referenced a variable name no controller ever provides, so the token
  was always submitted empty and validation always failed. The identical mismatch was also found
  and fixed in three Contact module admin views (inbox delete, single-message actions, and the
  settings form), which had the same bug. Bumped Videos to `0.0.3` and Contact to `0.0.3`.

## [0.0.9-alpha] - 2026-07-04

Styling and admin identity overhaul, plus one feature addition: a live-preview Theme Customizer.
Also includes a follow-up codebase-wide audit (2026-07-04): zero inline `<style>`/`<script>` blocks
remain in any view file (system, theme, or module), plus a new framework capability and several
real bugs found along the way.

### Inline CSS/JS Extraction

- Audited every view file under `App/Views/`, `App/Modules/*/Views/`, and `App/Themes/*/layout.php`.
  Extracted all inline CSS into per-page/per-module `.css` files and all inline JS into `.js` files -
  15 modules (Blog, Contact, Events, Forms, Gallery, Members, Newsletter, Pages, Search,
  ThemeCustomizer, Videos, Analytics, Media, Navigation, Webhooks) plus system admin views
  (`api_keys`, `roles`, `settings`, `themes`, `translations`, `profile`, `modules`) and the
  655-line Module Manager install wizard.
- **New: `ModuleLoader::frontAssets()`** - modules can now declare front-end CSS/JS in `module.json`
  (top-level `assets.css`/`assets.js`, sibling to the existing admin-only `assets.admin.*`), injected
  into all four bundled theme layouts. This didn't exist before - front-end module views had no
  choice but to inline their CSS, which is what most of them were doing.
- **New: `vtx:modal:loaded` event** - `admin.js`'s CRUD modal loader now dispatches this on
  `document` (with `event.detail.body`) after injecting AJAX-fetched form HTML, so externalized
  scripts that need to re-initialize a component (a rich-text editor, a slug generator) on every
  modal open can listen for it instead of relying on inline `<script>` re-execution.
- **Deduplicated 10 copies of the theme-init FOUC-prevention script** into one shared partial,
  `App/Views/_shared/theme-init.php`, `<?php include ?>`'d by every layout. Kept inline deliberately
  (an external file would reintroduce the flash-of-wrong-theme it exists to prevent) - centralizing
  the source fixed a real staleness bug where `default/landing.php` and `default/welcome.php` still
  read the pre-0.0.9 `phuse-theme` key with no migration to `vtx-theme`.
- Two narrow, deliberate exceptions remain, both documented in code: `App/Views/maintenance.php`'s
  CSS (fires too early in the request lifecycle for a reliable base URL, and a 503 page needs zero
  external dependencies) and `Media/Views/admin/media/picker.php`'s script (a self-contained AJAX
  partial pattern - both its own reload and Gallery's picker overlay re-execute its embedded
  `<script>` tag by design; externalizing it would require rewiring every caller).

### Bugs Found and Fixed During Verification

- **`admin-pages.js` crashed on every admin page except Module Manager** - three IIFEs extracted
  from `admin/modules/index.php` (tab switching, the bundle-install wizard, the a-la-carte configure
  modal) called `document.getElementById(...)` without checking the result, which is only ever
  non-null on that one page. Since this script now loads globally, the first crash aborted all
  later top-level statements in the same file for the rest of that pageload. No user-facing impact
  in practice (none of that code is needed outside Module Manager), but it spammed the console
  everywhere else - fixed with existence guards.
- **`tc-admin.js` (Theme Customizer) crashed on every other admin page** - same root cause,
  `frame.dataset.previewUrl` on a null element. Fixed with a guard.
- **`videos-admin.js`'s `vtx:crud:success` listener fired globally** - previously scoped to the
  Videos page by virtue of being inline only there; now that it's a global script, saving or
  deleting a record in *any* module would trigger an unwanted full-page reload. Scoped the reload
  to only fire when `#vtx-videos-index` is present.
- **Gallery's `_form.php` used a dead custom hook** (`window.vtxInitGalleryForm`, never called by
  anything) instead of hooking into the modal lifecycle - replaced with `vtx:modal:loaded`.
- **Admin dark-mode toggle silently fought a second theme system** - `scripts.js` (loaded on admin
  pages for its shared `Phuse.toast()`/`Phuse.modal()` utilities) unconditionally ran its own
  front-end `Phuse.darkMode()` on every page, which read the pre-0.0.9 `phuse-theme` key (not the
  unified `vtx-theme` key `admin.js` uses), reset `data-theme` to system/legacy preference right
  after `admin.js` had already set it, and injected a second floating toggle button. Fixed by
  skipping `Phuse.darkMode()` entirely whenever the admin's own `#theme-toggle` button is present.

### Two New Front-End Themes - Field and Frame

- **Field** - warm, tactile alternative: stone-beige surfaces (`#EFEBE3`), deep pine-green accent
  (`#2F4538`), Lora serif headings over Karla sans body text, soft rounded corners, and a dashed
  "stitched" rule motif on the header/footer/dropdown edges instead of a plain hairline.
- **Frame** - minimal gallery/portfolio identity: warm ivory by day / charcoal by night
  (`#F5F0E8` / `#16181C`), muted brass-and-bronze accent, bold geometric Space Grotesk headings
  over IBM Plex Sans body, near-flat corners, and a small-caps monospace nav/caption treatment.
- Both follow the same structure as `default`/`clean` (`theme.json`, `layout.php`, `css/theme.css`,
  `js/theme.js`), the same 1240px/1440px/740px container widths introduced earlier in 0.0.9, and
  the same `--clr-accent` (flips per color scheme) / `--clr-accent-fill` (stable) split so module
  views that fill buttons with the accent color keep working with no theme-specific changes.
  Self-hosted Lora, Karla, and Space Grotesk (SIL OFL 1.1) added to `Public/assets/fonts/` and
  `@font-face`-declared in `styles.css` alongside the existing IBM Plex families.
- Vertext now ships 4 selectable themes on **Admin -> Themes** instead of 2.

### Front-End Identity + Consistency Pass

- **The `default` front theme now shares the admin's "Precision Ledger" identity** - navy accent
  (was indigo `#4f46e5`), self-hosted IBM Plex Sans (was system-ui). Previously 0.0.9 only touched
  the admin panel, so a site's public face and its own admin looked like two unrelated products.
  `clean` theme is intentionally left as its own distinct editorial alternative (serif body,
  uppercase nav) - not everything needs to converge on one look. `default` theme bumped to
  `v0.0.2` in `theme.json` to reflect the identity change (shown live on **Admin -> Themes**,
  which reads the manifest directly - no separate version store to keep in sync).
- Front themes gained the same `--clr-accent` (flips for dark-mode contrast) / `--clr-accent-fill`
  (stable navy, for solid button/badge fills) split introduced for the admin panel, for the same
  reason: a dozen-plus module views (Contact, Forms, Search, Blog pagination, Events RSVP/date
  badges) fill buttons with `var(--clr-accent)`, which would otherwise wash out in dark mode once
  the default stopped being a bright, uniformly-legible indigo.
- `ThemeCustomizerHelper::getCss()` now also sets `--clr-accent-fill`/`--clr-accent-rgb` when an
  admin picks a custom accent color, so the picker's choice is what actually renders on buttons in
  both light and dark mode, not just the theme's own unconfigured default.
- Fixed three more stale hardcoded `rgba(79,70,229,...)` focus-ring glows (leftover from the old
  indigo default, dating to before this project's Vertext branding even existed) in Contact,
  Search, and the embedded Forms widget - same class of bug as the `#2563EB` sweep earlier in
  0.0.9, just missed because these were focus states, not visible without clicking into a field.
- Theme Customizer's two-column live-preview layout didn't collapse on narrow viewports - below
  900px it now stacks controls above a fixed-height preview instead of squeezing both columns
  into unusable widths.
- Analytics' dashboard stat numbers were colored with `--ps-primary` (glows bright blue in dark
  mode); changed to the same neutral `--ps-text-primary` treatment as the Dashboard's stat ledger,
  so the two "big number" patterns in the admin panel read as one system instead of two.

### Theme Customizer - Live Preview Builder

- **Live preview iframe** - the customizer now renders an actual preview page (representative
  hero, buttons, cards) through the active theme, updating ~350ms after any change instead of the
  old static color-swatch mockup; nothing is saved until "Save Changes"
- **New: Corner Style** - Sharp / Subtle / Rounded, controlling `--radius-sm`/`--radius-md` across
  buttons, cards, and menus; `default` theme gained these radius tokens to match `clean` (it
  previously hardcoded its one `border-radius` value with no variable)
- `ThemeCustomizerHelper::getCss()` now accepts an overrides array (and a `setPreviewOverrides()`
  static setter the preview route uses) so the same code path renders both the saved site CSS and
  the iframe's pending, unsaved state
- **Fixed a real save bug** - `Model::save($data, $update)` defaults to an INSERT unless `$update`
  is explicitly passed `true`; the customizer's update branch never passed it, so calling Save a
  *second* time on any already-existing setting threw a 500 (`Invalid parameter number: :where_0`)
  - the WHERE clause's bind survived into an INSERT query that never referenced it. First save
    always looked fine (it takes the insert path), which is why this had gone unnoticed.

### Real Logo

- Vertext's first real logomark (a vertex/"V" mark) replaces the placeholder "V" letter used in
  the sidebar brand, login/reset/forgot-password screens, and the setup wizard, and now backs the
  browser favicon on both the admin panel and the public site (previously there was none)
- Vectorized from the source artwork with a one-off GD-based tracer (binary mask -> Moore-neighbor
  boundary trace -> Ramer-Douglas-Peucker simplification) rather than hand-redrawn, so the SVG
  path is measured from the actual artwork; three transparent-background variants live under
  `Public/assets/images/logo/`: `logo-light.svg` (navy, light backgrounds), `logo-dark.svg`
  (white, dark backgrounds), `favicon.svg` (navy, square). Each is ~450-480 bytes versus the
  746 KB source PNG

### Admin Identity - "Precision Ledger"

- **New accent color** - retired the stock Tailwind-blue-600 `--ps-primary` (`#2563EB`) for a
  deeper enterprise navy (`#1E3A5F`); semantic colors (success/danger/warning/info) unchanged
- **Self-hosted typography** - `--ps-font-sans`/`--ps-font-mono` now use IBM Plex Sans/Mono
  (vendored under `Public/assets/fonts/`, SIL Open Font License - no Google Fonts CDN call)
- **Vertex-tick motif** - a small filled square replaces the tinted-background active state on
  top-level sidebar navigation; the same treatment replaces the setup wizard's colored step-dots
- **Stat ledger strip** - dashboard and module stat tiles dropped the icon-chip + big-number card
  pattern for a flat horizontal strip (mono uppercase label, large number, hairline dividers)
- **Flat status badges** - `.vtx-tag` dropped the tinted pill shape for a flat label with a small
  colored dot prefix
- **Dark-mode contrast fix** - splitting `--ps-primary` (now brightens to `#60A5FA` in dark mode,
  used for text/icon/border foregrounds) from the new `--ps-primary-fill` (stays navy in both
  modes, used for solid fills like buttons/avatars/logo marks) after the navy swap made icons,
  links, and tab indicators unreadable against dark surfaces across the whole framework

### Width System

- Front-end `.container` 760px -> 1240px, new `.container-prose` (~740px, used by blog posts and
  the default page template) preserves reading measure, `.container-wide` -> 1440px; fluid gutters
  via `clamp()` on both themes (`default` and `clean`)
- Admin `.vtx-content` capped at 1584px centered on ultrawide displays; `.modal-lg`/`.modal-xl`
  widened; new `.vtx-table--dense` modifier; spacing scale tokens `--sp-1` through `--sp-8`

### Token Unification

- Bridged `--ps-*`/`--clr-*` naming with alias custom properties in both front themes
- Unified the two dark-mode localStorage keys (admin `phuse-theme`, front `vtx-theme`) into one
  (`vtx-theme`), with a one-time migration read of the old admin key
- `ThemeCustomizer`'s accent color and font settings now also drive `--clr-accent`/`--clr-link`
  and `body{font-family}` on the front-end - previously they only set `--ps-*` variables that no
  front-end theme ever reads, so the customizer's color picker silently did nothing on the public
  site

### Fixes

- `.vtx-main` had no `min-width: 0`, so wide tables (e.g. Recent Activity) forced the whole admin
  page to overflow horizontally on mobile instead of scrolling within their own panel
- `App/Views/setup/layout.php` had hardcoded `?v=142`/`?v=4` cache-bust query strings (dating back
  to 0.0.7b) instead of the version-hash pattern used everywhere else, so setup wizard CSS could
  never cache-bust across releases
- Several front-end module pages (Events, Videos, Contact, Forms) combined `.container` with their
  own page-level class that set a `padding` shorthand (e.g. `padding: 3rem 0`), which zeroed out
  the new fluid side padding due to equal CSS specificity - page titles on those pages sat flush
  against the viewport edge instead of aligning with the header/footer; fixed by switching those
  rules to `padding-top`/`padding-bottom` only
- Contact, the embedded Forms page, and Search each wrapped their entire page (title included) in
  an independently-centered narrower column, so their titles landed at a different horizontal
  position than every other top-level page (Events, Gallery, Videos, Blog index); titles now stay
  on the same full-width `.container` as the rest of the site, with only the actual form/input
  width constrained below the title - `.container-prose` is reserved for genuine long-form reading
  content (blog posts, the Pages default template), not utility pages
- Page titles also disagreed on font size: Search rendered its `<h1>` at 1.75rem while everything
  else used the shared `h1 { font-size: 2rem }` from `styles.css`, and Blog's index/category pages
  and both Gallery pages each set their own smaller size (1.5rem-1.75rem) on top of it - all of
  them now inherit the shared default instead of redeclaring their own

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
