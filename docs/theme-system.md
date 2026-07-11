# Theme System

Vertext's public-facing pages are rendered through a **ThemeEngine** (`App\Theme\ThemeEngine`) that wraps module front-end views in a shared theme layout. Admin views are unaffected - they always use the admin layout.

## How It Works

1. A front-end controller calls `ThemeEngine::render('modules/blog/front/index', $data)`.
2. ThemeEngine renders the view file into a string (`$content`).
3. It then loads the active theme's `layout.php`, injects `$content` into it, and outputs the full page.
4. On first request, theme assets (css/, js/) are automatically deployed from `App/Themes/{name}/` to `Public/themes/{name}/`.

## Bundled Themes

| Theme | Slug | Description |
| --- | --- | --- |
| Default | `default` | Clean, modern layout sharing the admin panel's navy "Precision Ledger" identity (IBM Plex Sans); responsive header with mobile hamburger nav |
| Clean | `clean` | Typographic/editorial: Georgia serif body, black borders, uppercase navigation |
| Field | `field` | Warm, tactile: stone-beige surfaces, forest-green accent, Lora serif headings over Karla sans body, dashed "stitched" dividers |
| Frame | `frame` | Minimal gallery/portfolio: warm ivory by day / charcoal by night, muted brass accent, Space Grotesk display headings, small-caps mono nav |

All four themes ship with full dark/light mode support.

## Directory Structure

```text
App/Themes/
├── default/
│   ├── theme.json        Theme manifest
│   ├── layout.php        Base HTML page (injects $content)
│   ├── css/
│   │   └── theme.css     Theme stylesheet (layered on top of styles.css)
│   └── js/
│       └── theme.js      Theme JavaScript (toggle + mobile nav + smooth scroll)
├── clean/
│   ├── theme.json
│   ├── layout.php
│   ├── css/theme.css
│   └── js/theme.js
├── field/
│   ├── theme.json
│   ├── layout.php
│   ├── css/theme.css
│   └── js/theme.js
└── frame/
    ├── theme.json
    ├── layout.php
    ├── css/theme.css
    └── js/theme.js
```

Deployed assets (auto-generated, do not edit directly):

```text
Public/themes/
├── default/
│   ├── css/theme.css
│   └── js/theme.js
├── clean/
│   ├── css/theme.css
│   └── js/theme.js
├── field/
│   ├── css/theme.css
│   └── js/theme.js
└── frame/
    ├── css/theme.css
    └── js/theme.js
```

## CSS Loading Order

Every theme layout loads two stylesheets in this order:

```html
<link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/styles.css">
<link rel="stylesheet" href="<?= $themeUrl ?>/css/theme.css">
```

`styles.css` is the Phuse CSS framework (grid, utilities, form styles, custom properties). `theme.css` extends and overrides it with theme-specific colors and layout. This mirrors the admin pattern (`styles.css` + `admin.css`), so front-end views can use the same utility classes.

## Dark/Light Mode

All bundled themes implement three CSS layers:

```css
/* Layer 1: light mode defaults */
:root {
  --color-bg: #ffffff;
  --color-accent: #4f46e5;
}

/* Layer 2: OS preference (dark), unless user has explicitly chosen light */
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    --color-bg: #0f172a;
    --color-accent: #818cf8;
  }
}

/* Layer 3: explicit user override */
[data-theme="dark"] {
  --color-bg: #0f172a;
  --color-accent: #818cf8;
}
```

**FOUC prevention** - a shared partial (`App/Views/_shared/theme-init.php`) is `<?php include ?>`'d
at the top of every layout's `<head>` and applies the saved preference before CSS renders. It's
kept as an inline `<script>` (not moved to an external file) so it runs before first paint with no
extra network request:

```html
<?php include ROOT . 'App' . DS . 'Views' . DS . '_shared' . DS . 'theme-init.php'; ?>
```

```html
<!-- theme-init.php contents -->
<script>(function(){try{var t=localStorage.getItem('vtx-theme');if(!t){var l=localStorage.getItem('phuse-theme');if(l){localStorage.setItem('vtx-theme',l);localStorage.removeItem('phuse-theme');t=l;}}if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})()</script>
```

**Toggle** - `theme.js` reads the current effective theme, flips it, saves to `localStorage` under key `vtx-theme`, and sets `data-theme` on `<html>`.

## ThemeEngine API

```php
use App\Theme\ThemeEngine;

// Render a front-end view inside the active theme layout
ThemeEngine::render(string $view, array $data = []): void

// Get the active theme slug (reads settings, defaults to 'default')
ThemeEngine::activeTheme(): string

// Build a URL to a theme asset
ThemeEngine::assetUrl('css/theme.css', $baseUrl): string

// Manually re-deploy theme assets (e.g. after editing source files)
ThemeEngine::deploy(string $theme = ''): bool

// Discover all available themes (reads App/Themes/*/theme.json)
ThemeEngine::discover(): array
```

## Module Front-End Assets

Modules can ship their own front-end CSS/JS (e.g. a blog post's styling, a gallery's lightbox
script) via `\App\CMS\ModuleLoader::frontAssets()`, which reads the top-level `assets.css`/`assets.js`
keys of each enabled module's `module.json` (sibling to the admin-only `assets.admin.*` keys) and
returns versioned URL paths relative to `assetsUrl`. All four bundled themes call it in `layout.php`:

```php
<?php foreach (\App\CMS\ModuleLoader::frontAssets()['css'] as $__mAsset): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl . '/assets/' . $__mAsset); ?>">
<?php endforeach; ?>
```

with the equivalent `<script src>` loop before `</body>`. A custom theme's `layout.php` should
include both loops (after its own `theme.css`/`theme.js`) so installed modules' front-end styling
and behavior actually reach the public site.

## Variables Available in layout.php

| Variable | Source | Description |
| -------- | ------ | ----------- |
| `$content` | Rendered view | The module view HTML to inject |
| `$baseUrl` | `$data['baseUrl']` | Site base URL |
| `$themeUrl` | Computed | URL to `Public/themes/{name}/` |
| `$pageTitle` | `$data['page_title']` | Page `<title>` and og:title |
| `$pageDesc` | `$data['page_description']` | Meta description |
| `$pageImage` | `$data['page_image']` | og:image URL |
| `$canonicalUrl` | Computed | Current request's clean URL (query string stripped), prefers the `site_url` setting over the detected host |
| `$siteName` | DB settings | `site_name` from general settings |
| `$siteDesc` | DB settings | `site_description` from general settings |
| `$site` | DB settings | Full `grp='general'` settings array |

`$pageTitle`/`$pageDesc`/`$pageImage`/`$canonicalUrl`/`$siteName` feed into `App/Views/_shared/seo-meta.php` (0.1.1), a shared partial all 4 bundled themes `<?php include ?>` in their `<head>` - it emits `<title>`, meta description, `<link rel="canonical">`, and the og:*/twitter:* tags in one place. A custom theme should include it too rather than hand-rolling these tags, so canonical/Twitter Card support comes for free and stays in sync with the other themes.

## Using ThemeEngine in a Front-End Controller

```php
namespace App\Modules\MyModule\Controllers\Front;

use Core\Controller;
use App\Theme\ThemeEngine;

class MyController extends Controller
{
    public function index(): void
    {
        $items = (new \Core\Model('my_items'))->where('status', 'published')->get();

        ThemeEngine::render('modules/mymodule/front/index', [
            'items'            => $items,
            'baseUrl'          => $this->baseUrl,
            'page_title'       => 'My Items',
            'page_description' => 'Browse all items.',
        ]);
    }
}
```

The view file lives at `App/Modules/MyModule/Views/front/index.php` (source) and is deployed to `App/Views/modules/mymodule/front/index.php` at install time. It should be **content-only** - no `<html>`, `<head>`, or `<body>` tags; the layout handles those.

## Creating a Custom Theme

**1.** Create `App/Themes/my-theme/theme.json`:

```json
{
    "name": "My Theme",
    "slug": "my-theme",
    "version": "1.0.0",
    "description": "Custom theme for My Site",
    "author": "Your Name"
}
```

**2.** Create `App/Themes/my-theme/layout.php`. Include the shared `seo-meta.php` partial (title,
meta description, canonical URL, og:*/twitter:* tags - all computed for you) and the FOUC-prevention
partial, load `styles.css` first, then your theme CSS, then the `frontAssets()` loops so installed
modules' front-end CSS/JS actually reach the page:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <?php include ROOT . 'App' . DS . 'Views' . DS . '_shared' . DS . 'seo-meta.php'; ?>
  <?php include ROOT . 'App' . DS . 'Views' . DS . '_shared' . DS . 'theme-init.php'; ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl . '/assets/css/styles.css') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($themeUrl . '/css/theme.css') ?>">
  <?php foreach (\App\CMS\ModuleLoader::frontAssets()['css'] as $__mAsset): ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl . '/assets/' . $__mAsset) ?>">
  <?php endforeach; ?>
</head>
<body>
  <?= $content ?>
  <script src="<?= htmlspecialchars($themeUrl . '/js/theme.js') ?>"></script>
  <?php foreach (\App\CMS\ModuleLoader::frontAssets()['js'] as $__mAsset): ?>
  <script src="<?= htmlspecialchars($baseUrl . '/assets/' . $__mAsset) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
```

**3.** Add your CSS to `App/Themes/my-theme/css/theme.css` and JS to `App/Themes/my-theme/js/theme.js`.

**4.** Go to **Admin → Themes** and click **Activate** next to your theme.

## Switching Themes

Go to **Admin → Themes**. Each discovered theme appears as a card. Click **Activate** to switch the active theme immediately; `ThemeEngine::deploy()` runs automatically to copy assets to `Public/themes/`.

The active theme is stored in the `settings` table under key `active_theme`.

## Asset URLs in layout.php

```html
<!-- Link to a deployed theme asset -->
<link rel="stylesheet" href="<?= $themeUrl ?>/css/theme.css">
<script src="<?= $themeUrl ?>/js/theme.js" defer></script>
```

## Width System (0.0.9)

Both bundled themes share the same container scale, defined per-theme in `theme.css` so each can
tune its own gutter/padding feel:

| Class | Max width | Use for |
| --- | --- | --- |
| `.container` | 1240px | Page shell - header, footer, listing pages |
| `.container-wide` | 1440px | Full-width templates (Pages `full-width` template) |
| `.container-prose` | 740px | Long-form reading content - blog posts, the Pages `default` template |

All three use fluid `clamp()` side padding rather than a fixed value, so gutters scale smoothly
between mobile and desktop instead of jumping at a breakpoint.

Templates that mix prose with wider elements (e.g. a blog post's body text next to a related-posts
grid) should wrap only the prose-bearing markup in `.container-prose`; don't force an entire mixed
layout into the narrow measure just because part of it is body copy.

## `--ps-*` / `--clr-*` Token Bridge (0.0.9)

The admin panel (`Public/assets/css/styles.css` + `admin.css`, tokens prefixed `--ps-*`) and the
front-end themes (`--clr-*`) are separate CSS systems - a front-end page never loads `styles.css`'s
admin styles, and vice versa. Each theme's `:root` block now also aliases the common `--ps-*` names
to its own `--clr-*` values (e.g. `--ps-primary: var(--clr-accent);`) so that any future shared or
imported component CSS written against either naming convention still resolves sensibly, without
requiring the two systems to be merged.

The admin panel has its own distinct visual identity ("Precision Ledger" - navy accent, IBM Plex
typography, a vertex-tick motif) that intentionally does not extend to the front-end themes, which
remain independently brandable per-site via **Admin → Theme Customizer**.

### Theme Customizer

The Theme Customizer module's accent color and font settings drive **both** systems at once:
`ThemeCustomizerHelper::buildCss()` emits `--ps-primary`/`--ps-font-sans` overrides (for any admin
surface that reads them) *and* `--clr-accent`/`--clr-link`/`body{font-family}` overrides (for the
active front-end theme) from the same saved settings, so the color picker actually changes the
public site.

This CSS is written to a real file and linked (`<link rel="stylesheet">`), not echoed as an inline
`<style>` block - the site's CSP sends `style-src 'self'` with no `unsafe-inline`, and browsers
silently ignore inline `<style>` tags that policy doesn't cover. `ThemeCustomizerHelper::cssUrl()`
handles both cases each theme's `layout.php` needs:

- **Live site**: `Public/assets/generated/theme-custom.css`, regenerated by
  `ThemeCustomizerController::save()` (and lazily on first request if missing) - a real,
  cacheable file, not recomputed from the database on every page load.
- **Customizer live preview**: `Public/assets/generated/theme-preview.css`, rewritten on every
  call while a preview override is active (`setPreviewOverrides()`), with a cache-busting `?t=`
  query param so the iframe's debounced reload always fetches the latest edit.

Both files live under `Public/assets/generated/` (gitignored, created on demand - same treatment as
`Public/themes/`).

## Graceful Degradation

If the active theme's `layout.php` does not exist, ThemeEngine outputs `$content` directly with no wrapper. Front-end views always render something, even if the theme is misconfigured.
