# Vertext CMS - Changelog

All notable changes to Vertext CMS are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [0.1.0-beta] - 2026-07-07

Vertext's first public self-host beta: safe production defaults, a general database migration
system, backup/restore tooling, security headers extended to the public site and API, an
automated test suite with CI, and new guidance docs for self-hosters. Scoped for people
comfortable running their own PHP/PostgreSQL server, not enterprise environments - see
[Known Limitations](docs/known-limitations.md) for what this release does not cover.

### Safe defaults on install

- The setup wizard now sets `env => production` by default when installation completes, instead of
  silently leaving new installs in `development` mode (which shows detailed error output, including
  file paths, to any visitor who triggers an error). An "Enable debug mode for this install"
  checkbox is available for those who genuinely want a dev/staging install.
- The requirements check now also verifies the `gd`, `fileinfo`, `intl`, and `zip` extensions -
  `gd`/`fileinfo` block installation if missing (Media uploads and image resizing depend on them),
  `intl`/`zip` warn without blocking (both degrade gracefully).
- New [Going to Production](docs/going-to-production.md) checklist, linked from the wizard's final
  step - environment, file permissions, HTTPS, backups, and known limitations in one place.

### Migrations, backups, and upgrading

- General-purpose database migrations: `php vertext migrate up` and `php vertext migrate status`
  discover and run any file under `Migrations/`, tracked in a new `schema_migrations` table - no
  more hardcoded, two-file install step. Existing installs and fresh ones now go through the exact
  same runner.
- `php vertext backup` and `php vertext restore` - a single archive of your database data,
  `Public/uploads/`, and config, with credentials redacted by default (`--include-secrets` to
  include them). No `pg_dump`/shell dependency - pure PHP, works anywhere PHP can reach your
  database. See [Backup & Restore](docs/backup-restore.md).
- New [Upgrading](docs/upgrading.md) doc covering the git-pull + `migrate up` upgrade path.

### Security

- CSP, X-Frame-Options, X-Content-Type-Options, and Referrer-Policy headers - previously admin-only
  - now apply to the public front-end and the REST API too, via a new shared
  `Core\Middleware\SecurityHeadersMiddleware` (Phuse v1.2.8d). The front-end and API get a stricter
  policy than admin (no `unsafe-inline`); the bundled themes' one remaining inline style attribute
  was moved to a CSS class to comply with it.
- `Strict-Transport-Security` (HSTS) is now sent automatically whenever `'https' => true` is set in
  config.
- Fixed a real bug in two-factor authentication: backup codes could never actually be redeemed,
  regardless of what was typed, because the code was hashed with its display-format dash still
  attached while verification stripped it before comparing. Anyone who lost their authenticator app
  and needed a backup code to get back in would have been locked out.

### Testing & CI

- Vertext now has an automated test suite covering both the underlying framework and representative
  CMS flows - setup install, admin login/2FA, Blog CRUD and public routing, Forms submission, and
  the REST API's rate limiting - run automatically on every push via GitHub Actions (PHP 8.2 and
  8.3, against a real PostgreSQL service). `composer install && vendor/bin/phpunit` now works
  locally too, which it never could before (no `composer.json` existed).

### Documentation

- New: [Going to Production](docs/going-to-production.md), [Upgrading](docs/upgrading.md),
  [Backup & Restore](docs/backup-restore.md), [Troubleshooting](docs/troubleshooting.md), and
  [Known Limitations](docs/known-limitations.md) - clearer guidance for self-hosters running into
  install or runtime issues, and an honest list of what this beta doesn't cover yet.

## [0.0.9d-alpha] - 2026-07-07

Front-end performance parity with the admin panel, plus two framework-level fixes in the
underlying Phuse engine (now v1.2.8c).

### Front-end now gets the same output optimization the admin panel already had

- **HTML minification** - admin pages have always been minified for free via the Phuse template
  engine (`Core\Template\Parser`); the public front-end never went through that engine at all, so
  100% of front-end HTML shipped unminified. Fixed by minifying the final rendered output in
  `App\Theme\ThemeEngine::render()` using the same framework minifier, with no new config surface.
- **Full-page caching extended to every front-end module** - `App\CMS\PageCache` (a real HTTP
  output cache with a 10-minute TTL) was only wired into Blog and Pages. Extended to Videos,
  Events, Gallery, Search, Contact, Forms, and the Members register/login pages, with matching
  cache-invalidation (`flushPages()`) added to the Videos/Events/Gallery admin controllers so
  edits show up immediately instead of waiting out the TTL. Remains **opt-in** (Admin > Settings,
  off by default) - unchanged for existing installs. Found and fixed a subtler bug while wiring
  this in: Events, Forms, and Contact each replace their form with a one-time "thank you" message
  after a successful submission (no CSRF token present in that state), which meant the page cache's
  usual CSRF-token safety check couldn't catch it - that personalized one-time message could have
  been cached and shown to the next visitor. Those three now skip caching outright whenever a flash
  message was just consumed.

### Framework fixes (Phuse v1.2.8c)

- **The admin "template cache" never actually cached anything.** `Core\Template\Parser::render()`
  computed a cache key, immediately deleted any existing cache file for it, and always re-rendered
  from scratch - the methods to actually serve a valid cached copy (`TemplateCache::hasValidCache()`
  / `getCached()`) were fully implemented but never called from anywhere. Fixed so a matching,
  unexpired cache entry is served directly, skipping the template-compile and minify work; still
  auto-invalidates whenever the template file or its data changes (both are part of the cache key).
- **No response compression anywhere.** Added transparent gzip compression for every response -
  admin, front-end, and the REST API - via a single `ob_gzhandler` output buffer, so clients that
  support it get a substantially smaller response with no per-route changes.

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

