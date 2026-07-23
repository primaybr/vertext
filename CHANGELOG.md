# Vertext CMS - Changelog

All notable changes to Vertext CMS are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Upcoming]

## [0.1.4b] - 2026-07-23

### Added

- **`I18n::getDefaultLocale()`** - returns the site's configured default locale (`settings.default_locale`) independent of the current visitor's session override, for themes using a no-prefix-for-default URL scheme (bare path = default locale, `/xx/` = everything else). `resolveLocale()` now delegates to it instead of duplicating the same settings lookup inline.
- **Pages can now have a translated version of the same slug.** `pages.slug` was globally unique, so the same slug (e.g. `about`) couldn't have both an `id` and `en` row side by side. Migrated to a composite `(slug, lang)` unique index, and the front-end `PageController` now serves the visitor's locale when a translated row exists for a slug, falling back to any language instead of a 404 when it doesn't - the same graceful-degradation shape `Blog\Controllers\Front\BlogController`'s listing page already used.
- **`I18n::path($baseUrl, $path)` / the global `site_path()` helper** - the one place every internal same-site page link should now be built: bare path for the default locale, `/xx/` prefix for everything else, matching the current visitor's locale (session-sticky, via `I18n::getLocale()`). Themes/modules building internal links should always route through this instead of raw `$baseUrl . '/...'` concatenation - asset URLs (CSS/JS/images) must still bypass it and stay unprefixed.
- **`PageCache::serve()` now takes an optional `$ttl` parameter** (defaults to the existing `DEFAULT_TTL`) - lets a page whose content ages faster than the 10-minute default (e.g. a trending/ranking page) use a shorter staleness window without a separate caching mechanism. Fully backward-compatible; every existing bare `PageCache::serve();` call is unaffected.

### Fixed

- **Blog posts never actually appeared in the sitemap** - `SitemapController` queried a table called `blog_posts`, which doesn't exist (the real table is `posts`); the query silently failed inside its own `catch` block, so `sitemap.xml` quietly omitted every post with no visible error.
- **A translated page contributed the same sitemap URL twice** - with Pages now supporting one row per locale, `SitemapController` still emitted a bare `/{slug}` URL for every row regardless of language, so a page with an `id` and `en` version listed the identical URL twice instead of once each. Now builds the URL the same way `Config/Routes.php`'s own prefix-stripping does: bare path for the default locale, `/xx/` prefix for everything else.
- **A site with locale-prefixed URLs (e.g. Carikno's `/en/`) could show the wrong-locale nav to a visitor sharing the cache window** - `NavHelper::getMenu()`'s 5-minute fragment cache was keyed only by menu slug, but its resolved item URLs are locale-dependent once a theme adopts `site_path()`; now keyed by locale too. `resolveUrl()`'s `page`/`module` branches and `buildFromModuleRoutes()`'s auto-generated items now route through `I18n::path()` instead of raw `$baseUrl` concatenation, so nav links carry the current visitor's locale prefix like every other internal link (`custom`-type links, which may be external, are left untouched).
- **The shared 404 page's "Go to Homepage" link ignored the visitor's locale** - `App/Views/error/_404-content.php` built its link from a bare `$baseUrl`; now routes through `site_path()`.
- **Blog's front-end links (post/category links, pagination, comment form actions, series navigation, related posts, the reading-list share URL, and the legacy-base-path 301 redirect) all ignored the visitor's locale** - every one of them concatenated `$baseUrl` directly; now route through `site_path()`.
- **A write through `Core\Model` wiped the entire file-based query cache, not just the entries a write could actually affect** - `Core\Cache\QueryCache`'s cache filenames were keyed only by an MD5 of the SQL+params, with no table name embedded, so the already-existing `clearTableCache($table)` method was silently a no-op for any real table and `Model::clearQueryCache()` fell back to wiping everything on every `save()`/`update()`/`delete()`. Cache filenames now embed every table a query touches (`FROM`/`JOIN` identifiers, which `Core\Model`'s query builder never quotes, so they're safely extractable), and `clearQueryCache()` now calls `clearTableCache($this->table)` instead of a blanket `clear()`. New test coverage: `tests/Core/Cache/QueryCacheTest.php`. Synced to Phuse v1.3.0.
- GD's WebP decoder can hit an unrecoverable, uncatchable "gd-webp cannot allocate temporary buffer"
  fatal error on certain animated WebP images - crashed the whole PHP process even though
  `getimagesizefromstring()` reported perfectly reasonable dimensions (found via a real crawled
  Carikno marketplace image). GD only ever supports a WebP's first static frame anyway, so
  `Core/Utilities/Image/ImageTrait.php` now detects the RIFF/WEBP container's `ANIM` chunk signature
  and rejects gracefully before ever calling the crashing function, instead of relying on GD to fail
  safely. Synced to Phuse v1.3.0.

---

## [0.1.1b] - 2026-07-12

### Fixed

- The 404 page didn't adhere to the active theme and looked visibly broken - `App/Views/error/404.php`
  hand-duplicated the site header/nav/footer instead of rendering through `ThemeEngine`, so it never
  picked up theme/CSS changes, and its content used inline `style="..."` attributes and an
  `onclick="history.back()"` handler, both silently blocked by the site's CSP (`style-src`/`script-src
  'self'`, no `unsafe-inline`) - the page rendered unstyled and its "Go Back" button didn't work.
  Rewritten to render through `ThemeEngine::render()` like every other front-end page (so it now gets
  the real theme's header/nav/footer/accent color, automatically, forever) with real CSS classes and
  an external script for the back-button, verified live via Playwright (console errors 8 -> 1, the
  remaining one being the 404 response itself).
- `styles.css`/`theme.css`/`theme.js` cache-busted on `?v=<hash of Version::APP>` - a value that only
  changes on a release cut, so any edit made between releases (like this session's Landing Blocks
  CSS) never invalidated already-cached copies. A tab left open, or reloaded without a hard-refresh,
  kept serving pre-edit CSS indefinitely, while the Theme Customizer's live-preview iframe (which
  force-reloads with its own timestamp on every edit) always showed the current file - the two could
  drift apart and look different for no reason other than one tab being staler than the other. Now
  cache-busts on the file's own mtime instead, so any edit is immediately visible on next load.
- The GitHub Actions test run failed outright - `Config/Config.php` is gitignored (it can hold a
  developer's local site settings) so a fresh CI checkout never has one, and every test touching
  `Core\Config`/`Controller`/`Router`/`Template\Parser` failed with "Configuration file not found"
  (25+ errors across `ConfigTest`, `ControllerTest`, `RouterTest`, `TemplateTest` and others).
  `tests/bootstrap.php` now writes a throwaway test config on the fly when none exists (mirroring
  the same backup/write/restore pattern already used for `Storage/db.php`), leaving a developer's
  real local config untouched while giving CI exactly what it needs.
- The test suite still exited non-zero on GitHub Actions even after the above fix, despite every
  test passing - PHPUnit 10 defaults `failOnPhpunitWarning` to `true`, and a stale `phpunit.xml`
  (`<coverage><include>`/`<exclude>`, moved to a top-level `<source>` block several PHPUnit versions
  ago) was triggering an XML-schema-validation warning on every run, which counts as exactly that
  kind of warning. Fixed the schema (added `<source>`, `<coverage>` now only holds `<report>`), added
  `failOnPhpunitWarning="false"` next to the existing `failOnWarning`/`failOnDeprecation` flags, and
  added `--no-coverage` to the CI run step since no coverage report is consumed by the pipeline.
  Verified the causation directly: with the flag removed, the exact same all-green test output flips
  from exit 0 to exit 1, and back again with it restored.

## [0.1.1-beta] - 2026-07-11

Real module and theme versioning, per-theme landing pages, and finished-out SEO essentials.

### Module Versioning

- Core/system modules (Authentication, Dashboard, Users, Roles, Module Manager, CMS Settings, Theme
  Manager) no longer show a version frozen at whatever the CMS version was on the day the site was
  first installed - they now always display the CMS's actual running version.
- Installed modules whose on-disk `module.json` version is newer than what's actually installed now
  show an "Update available" badge and button in **Admin -> Modules**. Clicking it runs the module's
  own migration logic (if it defines one) and records the new version - no action needed for modules
  that don't define one, the version is simply brought up to date.

### Theme Landing Pages

- Each of the 4 built-in themes can now show a real, content-rich homepage instead of the generic
  "you have a CMS" placeholder - toggle it on under **Theme Customizer -> Homepage**. Default shows
  a Business Suite layout, Clean a Marketplace layout, Field a Coffee Shop layout, and Frame a
  Product Showcase layout, each shipped with its own starter copy.
- New **Landing Blocks** tab in Theme Customizer: add, reorder, and edit Hero, Feature Grid,
  Testimonials, Gallery, CTA Banner, Rich Text, and Stats blocks via drag-and-drop. Content is
  independent per theme - switching themes switches which set of blocks is shown.
- Added live preview to the Landing Blocks tab: it's now a two-column layout (builder left, preview
  right) matching Appearance, updating ~350ms after you stop editing - text fields, item add/
  reorder/delete, block add/reorder/delete, and Rich Text content all trigger it. Pending edits are
  staged server-side (session, sanitized identically to Save) rather than round-tripped through the
  preview URL directly - a blocks payload (rich text, multiple items, image URLs) is far too large
  for a query string the way the Appearance tab's color/font/corner-style overrides work.
- Fixed two bugs found while building the above, both of which meant Landing Blocks editing was
  more broken than it looked:
  - **Save Blocks never actually worked.** `$this->input->post('blocks')` sanitizes with
    `htmlspecialchars()` by default, which mangles every double-quote in the JSON-encoded payload
    into `&quot;` before `json_decode()` ever sees it - decoding then fails on any non-trivial
    payload, so every real save attempt hit "Invalid blocks payload." Same root cause as the
    Theme Customizer font-family bug above; same fix (read raw, validate after).
  - **Rich Text block edits were never captured for saving.** The block editor (Quill, via
    `VtxEditor`) syncs its content into a hidden textarea via plain JS property assignment
    (`textarea.value = ...`), which does not fire a native `input` event - so the generic
    field-change listener that populates the in-memory blocks array never ran for it. Whatever was
    in a Rich Text block when its card was built is what got saved, not what was actually typed.
    Now re-read directly from the textarea right before it matters (staging or saving).

### SEO Essentials

- `/sitemap.xml` now includes Events, Gallery, and Videos alongside Pages and Blog, each with its
  own on/off toggle under the new **Admin -> Sitemap** settings screen.
- New `/robots.txt`, always blocking `/admin/`, with an admin-configurable list of additional
  disallowed paths.
- Every page now includes a canonical URL and Twitter Card tags alongside the existing Open Graph
  tags, via one shared `seo-meta.php` partial included by all 4 themes.

### Security

- Fixed a stored XSS bypass in rich-text sanitization, found by automated review of the new Landing
  Blocks Rich Text block and traced back to an identical flaw in Blog's post body sanitizer
  (`PostsController::sanitizeHtml()`, the code this session's `LandingBlocksHelper` copy was modeled
  on). The `strip_tags()` + regex approach had two bypasses that let attacker-controlled markup
  survive unescaped into a page rendered directly to visitors: an **unquoted attribute value**
  (`<img onerror=alert(1)>` - no quotes at all, so neither regex matched) and a **single-quoted
  `javascript:` href** (`<a href='javascript:alert(1)'>` - only the double-quoted form was
  neutralized). Both call sites now delegate to a new shared `App\CMS\HtmlSanitizer`, which parses
  into a real DOM tree and rebuilds every surviving tag's attributes from an explicit allowlist
  instead of regex-stripping the raw string - closes the whole bypass class rather than one instance
  of it. Also tightened URL validation everywhere it's used (this sanitizer, Landing Blocks'
  link/image fields, and Theme Customizer's logo URL) to reject protocol-relative `//host` URLs,
  previously treated as a safe relative path.
- Fixed a worse, related gap found while auditing the above: Pages' `content` field (also a Quill
  rich-text body) was saved with **no sanitization call at all** on both create and update, despite
  a code comment on the front-end view claiming it was "sanitized on save." Now runs through the
  same `App\CMS\HtmlSanitizer`.
- Synced Phuse framework to v1.2.9, a security-focused core update:
  - The template engine's `{% if %}`/`{% while %}` conditions no longer run through `eval()` -
    replaced with a small expression parser that understands comparisons and boolean logic only,
    closing off a structural code-execution risk in condition handling.
  - `{{ variable }}` output is now HTML-escaped by default everywhere in the template engine.
    Views that intentionally render pre-built HTML through a plain `{{ }}` (rather than the
    existing `{!! !!}` raw-output syntax) will now see it escaped - none were found in Vertext's
    own views, but this is worth a visual pass after upgrading a customized install.
  - A new `CSRFMiddleware` now double-checks CSRF tokens on every non-GET request as a
    framework-level backstop, on top of Vertext's existing per-action checks. Requests
    authenticated via the REST API's Bearer key are exempted, since that flow was never
    CSRF-exposed in the first place.
  - The session cookie's `Secure` flag is now set based on the actual request scheme instead of
    being hardcoded `true` - fixes session/login persistence on non-HTTPS installs (most local dev
    setups).
- Added `Core\Env` (`.env` file loading) and `Core\Template\SafeHtml` (marks a string as
  pre-rendered, trusted HTML so the new output-escaping skips it) from the same sync - unused by
  Vertext today, available for modules that need them.
- Fixed Theme Customizer's live-preview iframe failing to render at all. Its preview action renders
  straight through `ThemeEngine::render()` rather than `adminRender()`, so it never picked up
  `BaseController`'s CSP override and was left with `SecurityHeadersMiddleware`'s global baseline -
  `X-Frame-Options: DENY` and `frame-ancestors 'none'`, both of which block *any* framing, including
  the same-origin `<iframe>` on the Theme Customizer page one route over that embeds this exact URL.
  The preview action now re-emits its own header pair with framing relaxed to `SAMEORIGIN`/
  `frame-ancestors 'self'` only - still blocks framing from any other origin, everything else in the
  policy stays as strict as the front-end baseline.
- Fixed Theme Customizer's accent color / font / corner style / custom CSS never actually applying
  anywhere - not just in the preview above, but on the live public site too, likely since the
  strict site-wide CSP went live (0.1.0-beta). `ThemeCustomizerHelper` injected its computed CSS as
  an inline `<style>` block, but the CSP sends `style-src 'self'` with no `unsafe-inline` - browsers
  silently refuse to apply an inline `<style>` tag CSP doesn't cover, so the override was always
  present in the HTML and always ignored. Switched to writing a real file and linking it
  (`<link rel="stylesheet">`, CSP-compliant with no policy changes needed): a regenerated-on-Save
  `Public/assets/generated/theme-custom.css` for the live site, and an always-fresh
  `theme-preview.css` for the customizer's live preview.
- Fixed font-family choices being corrupted before they were ever validated. `$this->input->post()`/
  `->get()` sanitize with `htmlspecialchars()` by default, which turns the `'` in e.g.
  `"Georgia, 'Times New Roman', serif"` into `&apos;` - every built-in font option except "system"
  has a quote in its CSS value, so 3 of 4 font choices silently broke. The same default sanitization
  was also mangling any logo URL with a `&` in its query string, and quietly corrupting the custom
  CSS textarea (which isn't HTML - running it through an HTML-escaper was never correct to begin
  with). All four fields are now read raw and validated/allowlisted immediately after, same pattern
  already used elsewhere in the codebase for Quill body content.
- Fixed nav links inside the Theme Customizer's live-preview iframe going blank when clicked. A
  clicked link navigates the iframe to a normal front-end page, which - unlike the one preview route
  above - has no `frame-ancestors` override, so it hit the identical CSP block. Preview pages now
  intercept and cancel all link clicks (matching how other CMS theme customizers with live preview
  behave - the iframe is a snapshot of the current page's styling, not a browsable copy of the site).

### Admin UI

- New `vtx-tooltip` component (`data-vtx-tooltip="Label"` on any element) - the admin panel's first
  real tooltip system, replacing ad-hoc native `title="..."` attributes. Delegated on `document` so
  it works on AJAX-loaded content automatically, shows on hover and keyboard focus, and sets
  `aria-label` on icon-only triggers that don't already have one.
- **Module Manager -> Modules (a la carte)**: Enable/Disable, Sync Views, Uninstall, and Install are
  now icon-only buttons with tooltips instead of mixed text/icon buttons - less visual clutter in a
  dense card grid. Update is unchanged (icon + text), since it's the one action worth calling out
  explicitly rather than leaving to icon recognition alone.

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

