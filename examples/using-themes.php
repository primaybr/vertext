<?php
/**
 * Example: Using the ThemeEngine for Public Front-End Pages
 *
 * ThemeEngine (App\Theme\ThemeEngine) wraps module front-end views in
 * the active site theme. Admin views use a separate layout and are unaffected.
 *
 * Change the active theme: Admin -> Themes -> Activate.
 * Bundled themes: "default" (modern), "clean" (typographic serif).
 * Both support dark/light mode with OS preference detection and a toggle button.
 */

// ── 1. Render a front-end view inside the active theme ────────────────────────
/*
namespace App\Modules\MyModule\Controllers\Front;

use Core\Controller;
use App\Theme\ThemeEngine;

class MyController extends Controller
{
    public function index(): void
    {
        $items = (new \Core\Model('my_items'))
            ->where('status', 'published')
            ->orderBy('created_at', 'DESC')
            ->get() ?: [];

        ThemeEngine::render('modules/mymodule/front/index', [
            'items'            => $items,
            'baseUrl'          => $this->baseUrl,
            // These keys are consumed by layout.php:
            'page_title'       => 'My Items',
            'page_description' => 'Browse our full collection.',
            'page_image'       => '',            // og:image URL (optional)
        ]);
    }

    public function single(string $slug): void
    {
        $item = (new \Core\Model('my_items'))
            ->where('slug', $slug)
            ->where('status', 'published')
            ->get(1);

        if (!$item) {
            http_response_code(404);
            ThemeEngine::render('modules/mymodule/front/index', [
                'items'      => [],
                'baseUrl'    => $this->baseUrl,
                'page_title' => '404 - Not Found',
            ]);
            return;
        }

        ThemeEngine::render('modules/mymodule/front/single', [
            'item'             => $item,
            'baseUrl'          => $this->baseUrl,
            'page_title'       => $item['meta_title'] ?: $item['title'],
            'page_description' => $item['meta_description'] ?: mb_substr(strip_tags($item['description'] ?? ''), 0, 160),
        ]);
    }
}
*/

// ── 2. Writing a content-only front-end view ──────────────────────────────────
/*
// App/Modules/MyModule/Views/front/index.php
// No <html>/<head>/<body> - ThemeEngine's layout.php provides those.
// styles.css (Phuse framework) is loaded by layout.php before theme.css,
// so grid, utility classes, and custom properties are available here too.

?>
<div class="container" style="padding: 2rem 0">
    <h1>Items</h1>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.5rem;">
        <?php foreach ($items as $item): ?>
        <a href="<?= $baseUrl ?>/my-items/<?= htmlspecialchars($item['slug']) ?>">
            <?= htmlspecialchars($item['title']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php
*/

// ── 3. Dark/light mode in a custom theme layout ───────────────────────────────
/*
// Three things are required in layout.php for dark/light mode to work:
//
// a) FOUC prevention script in <head> (before any CSS):
<script>(function(){var t=localStorage.getItem('vtx-theme');if(t)document.documentElement.setAttribute('data-theme',t);}());</script>
//
// b) Load styles.css (Phuse base) THEN theme.css:
<link rel="stylesheet" href="<?= htmlspecialchars($baseUrl . '/assets/css/styles.css') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars($themeUrl . '/css/theme.css') ?>">
//
// c) Theme toggle button (theme.js reads 'vtx-theme' from localStorage):
<button id="theme-toggle">Toggle</button>
<script src="<?= htmlspecialchars($themeUrl . '/js/theme.js') ?>"></script>
//
// In theme.css use three layers:
//   :root { --color-bg: #fff; }
//   @media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { --color-bg: #0f172a; } }
//   [data-theme="dark"] { --color-bg: #0f172a; }
*/

// ── 4. Manually deploying theme assets after editing source files ──────────────
/*
// Source (git-tracked):  App/Themes/default/css/theme.css
// Deployed (generated):  Public/themes/default/css/theme.css
//
// ThemeEngine deploys automatically on the first request after a fresh install.
// After editing source files, redeploy manually or use Admin -> Settings -> Clear Cache:

use App\Theme\ThemeEngine;

ThemeEngine::deploy();             // deploy active theme
ThemeEngine::deploy('my-theme');   // deploy a specific theme
*/

// ── 5. Creating a custom theme ────────────────────────────────────────────────
/*
// Required files:
//   App/Themes/my-theme/theme.json
//   App/Themes/my-theme/layout.php
//   App/Themes/my-theme/css/theme.css   (optional but recommended)
//   App/Themes/my-theme/js/theme.js     (optional; copy from default theme for toggle support)

// Minimal layout.php with dark/light mode support:
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(($pageTitle ?: $siteName)) ?></title>
    <script>(function(){var t=localStorage.getItem('vtx-theme');if(t)document.documentElement.setAttribute('data-theme',t);}());</script>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl . '/assets/css/styles.css') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($themeUrl . '/css/theme.css') ?>">
</head>
<body>
    <?= $content ?>
    <script src="<?= htmlspecialchars($themeUrl . '/js/theme.js') ?>"></script>
</body>
</html>

// Activate: Admin -> Themes -> Activate next to your theme name.
*/

// ── 6. Checking the active theme programmatically ─────────────────────────────
/*
use App\Theme\ThemeEngine;

$activeSlug = ThemeEngine::activeTheme();   // 'default', 'clean', etc.

// Discover all available themes (reads App/Themes/{name}/theme.json):
$themes = ThemeEngine::discover();
// Returns: [['slug' => 'default', 'name' => 'Default', 'active' => true, ...], ...]
*/
