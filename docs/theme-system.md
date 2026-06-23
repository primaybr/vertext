# Theme System

Vertext's public-facing pages are rendered through a **ThemeEngine** (`App\Theme\ThemeEngine`) that wraps module front-end views in a shared theme layout. Admin views are unaffected — they always use the admin layout.

## How It Works

1. A front-end controller calls `ThemeEngine::render('modules/blog/front/index', $data)`.
2. ThemeEngine renders the view file into a string (`$content`).
3. It then loads the active theme's `layout.php`, injects `$content` into it, and outputs the full page.
4. On first request, theme assets (css/, js/) are automatically deployed from `App/Themes/{name}/` to `Public/themes/{name}/`.

## Directory Structure

```
App/Themes/
└── default/
    ├── theme.json        Theme manifest
    ├── layout.php        Base HTML page (injects $content)
    ├── css/
    │   └── theme.css     Public stylesheet
    └── js/
        └── theme.js      Public JavaScript
```

Deployed assets (auto-generated, gitignored):

```
Public/themes/
└── default/
    ├── css/theme.css
    └── js/theme.js
```

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

The view file lives at `App/Modules/MyModule/Views/front/index.php` (source) and is deployed to `App/Views/modules/mymodule/front/index.php` at install time. It should be **content-only** — no `<html>`, `<head>`, or `<body>` tags; the layout handles those.

## Creating a Custom Theme

1. Create `App/Themes/my-theme/theme.json`:

```json
{
    "name": "My Theme",
    "slug": "my-theme",
    "version": "1.0.0",
    "description": "Custom theme for My Site",
    "author": "Your Name"
}
```

2. Create `App/Themes/my-theme/layout.php` — a standard HTML document that outputs `<?php echo $content; ?>` in the body.

3. Add your CSS to `App/Themes/my-theme/css/` and JS to `App/Themes/my-theme/js/`.

4. In **Admin → Settings**, set `active_theme` to `my-theme`. ThemeEngine will deploy and use it on the next request.

## Active Theme Setting

The active theme is stored in the `settings` table:

```
key: active_theme
grp: general
value: default
```

Change it through Admin → Settings or directly via the DB. ThemeEngine caches the resolved theme per request (static property).

## Asset URLs in layout.php

```php
<!-- Link to a deployed theme asset -->
<link rel="stylesheet" href="<?= $themeUrl ?>/css/theme.css">
<script src="<?= $themeUrl ?>/js/theme.js" defer></script>

<!-- Or use the helper -->
<link rel="stylesheet" href="<?= App\Theme\ThemeEngine::assetUrl('css/theme.css', $baseUrl) ?>">
```

## Graceful Degradation

If the active theme's `layout.php` does not exist, ThemeEngine outputs `$content` directly with no wrapper. This means front-end views always render something, even if the theme is misconfigured.
