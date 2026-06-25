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
| Default | `default` | Clean, modern layout with indigo accent; responsive header with mobile hamburger nav |
| Clean | `clean` | Typographic/editorial: Georgia serif body, black borders, uppercase navigation |

Both themes ship with full dark/light mode support.

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
└── clean/
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
└── clean/
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

Both bundled themes implement three CSS layers:

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

**FOUC prevention** - an inline `<script>` in `<head>` applies the saved preference before CSS renders:

```html
<script>(function(){
  var t = localStorage.getItem('vtx-theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
}());</script>
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

## Variables Available in layout.php

| Variable | Source | Description |
| -------- | ------ | ----------- |
| `$content` | Rendered view | The module view HTML to inject |
| `$baseUrl` | `$data['baseUrl']` | Site base URL |
| `$themeUrl` | Computed | URL to `Public/themes/{name}/` |
| `$pageTitle` | `$data['page_title']` | Page `<title>` and og:title |
| `$pageDesc` | `$data['page_description']` | Meta description |
| `$pageImage` | `$data['page_image']` | og:image URL |
| `$siteName` | DB settings | `site_name` from general settings |
| `$siteDesc` | DB settings | `site_description` from general settings |
| `$site` | DB settings | Full `grp='general'` settings array |

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

**2.** Create `App/Themes/my-theme/layout.php`. Include the FOUC script, load `styles.css` first, then your theme CSS:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($pageTitle ?: $siteName) ?></title>
  <script>(function(){var t=localStorage.getItem('vtx-theme');if(t)document.documentElement.setAttribute('data-theme',t);}());</script>
  <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl . '/assets/css/styles.css') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($themeUrl . '/css/theme.css') ?>">
</head>
<body>
  <?= $content ?>
  <script src="<?= htmlspecialchars($themeUrl . '/js/theme.js') ?>"></script>
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

## Graceful Degradation

If the active theme's `layout.php` does not exist, ThemeEngine outputs `$content` directly with no wrapper. Front-end views always render something, even if the theme is misconfigured.
