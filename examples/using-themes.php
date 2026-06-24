<?php
/**
 * Example: Using the ThemeEngine for Public Front-End Pages
 *
 * ThemeEngine (App\Theme\ThemeEngine) wraps module front-end views in
 * the active site theme. Admin views use a separate layout and are unaffected.
 *
 * The active theme is set in Admin → Settings (key: active_theme, default: 'default').
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
// $baseUrl, $items, and any other $data keys are available via extract().

?>
<style>
.my-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.5rem; }
</style>

<div class="container" style="padding: 2rem 0">
    <h1>Items</h1>
    <div class="my-grid">
        <?php foreach ($items as $item): ?>
            <a href="<?= $baseUrl ?>/my-items/<?= htmlspecialchars($item['slug']) ?>">
                <?= htmlspecialchars($item['title']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php
*/

// ── 3. Using ThemeEngine::assetUrl() for theme assets in views ────────────────
/*
// Inside a front-end view or a theme layout:
use App\Theme\ThemeEngine;

$iconUrl = ThemeEngine::assetUrl('images/logo.svg', $baseUrl);
// → "https://mysite.test/themes/default/images/logo.svg"
*/

// ── 4. Manually deploying theme assets after editing source files ──────────────
/*
// Source (git-tracked):  App/Themes/default/css/theme.css
// Deployed (generated):  Public/themes/default/css/theme.css
//
// ThemeEngine deploys automatically on the first request after a fresh install.
// After editing source files, redeploy manually:

use App\Theme\ThemeEngine;

ThemeEngine::deploy();            // deploy active theme
ThemeEngine::deploy('my-theme'); // deploy a specific theme
*/

// ── 5. Creating a custom theme ────────────────────────────────────────────────
/*
// Required files:
//   App/Themes/my-theme/theme.json
//   App/Themes/my-theme/layout.php
//   App/Themes/my-theme/css/theme.css   (optional)
//   App/Themes/my-theme/js/theme.js     (optional)

// Minimal layout.php:
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle ?: $siteName) ?></title>
    <link rel="stylesheet" href="<?= $themeUrl ?>/css/theme.css">
</head>
<body>
    <?= $content ?>
    <script src="<?= $themeUrl ?>/js/theme.js" defer></script>
</body>
</html>

// Then set active_theme = 'my-theme' in Admin → Settings.
*/
