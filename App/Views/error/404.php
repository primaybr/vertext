<?php
// Load site settings
$_errSiteName = 'My Site';
$_errBaseUrl  = '';
$_errTheme    = 'default';

try {
    $rows = (new \Core\Model('settings'))->select('key, value')->get() ?: [];
    foreach ($rows as $row) {
        if ($row['key'] === 'site_name') $_errSiteName = $row['value'];
        if ($row['key'] === 'site_url')  $_errBaseUrl  = rtrim($row['value'], '/');
        if ($row['key'] === 'theme')     $_errTheme    = $row['value'] ?: 'default';
    }
} catch (\Throwable) {}

// Derive base URL from server globals if settings unavailable
if (!$_errBaseUrl && isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $_errBaseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
}

$_errThemeUrl = $_errBaseUrl . '/themes/' . $_errTheme;
// $assetsUrl is injected by Error::loadTemplate() via include scope
$_errAssetsUrl = $assetsUrl ?? ($_errBaseUrl . '/assets/');

// Load navigation
$_errNavItems = [];
try {
    $_errNavItems = \App\CMS\NavHelper::getMenu('primary');
} catch (\Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Page Not Found - <?php echo htmlspecialchars($_errSiteName); ?></title>
  <script>(function(){var t=localStorage.getItem('vtx-theme');if(t)document.documentElement.setAttribute('data-theme',t);}());</script>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($_errAssetsUrl . 'css/styles.css'); ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($_errThemeUrl . '/css/theme.css'); ?>">
</head>
<body>

<header class="site-header">
  <div class="container">
    <a href="<?php echo htmlspecialchars($_errBaseUrl ?: '/'); ?>" class="site-name">
      <?php echo htmlspecialchars($_errSiteName); ?>
    </a>
    <nav class="site-nav">
      <?php foreach ($_errNavItems as $item):
          $target = !empty($item['open_in_new']) ? ' target="_blank" rel="noopener"' : '';
          if (!empty($item['children'])): ?>
      <div class="nav-dropdown">
        <a href="<?php echo htmlspecialchars($item['url']); ?>"<?php echo $target; ?> class="nav-dropdown-toggle">
          <?php echo htmlspecialchars($item['label']); ?>
        </a>
        <div class="nav-dropdown-menu">
          <?php foreach ($item['children'] as $child):
              $ct = !empty($child['open_in_new']) ? ' target="_blank" rel="noopener"' : ''; ?>
          <a href="<?php echo htmlspecialchars($child['url']); ?>"<?php echo $ct; ?>>
            <?php echo htmlspecialchars($child['label']); ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <a href="<?php echo htmlspecialchars($item['url']); ?>"<?php echo $target; ?>>
        <?php echo htmlspecialchars($item['label']); ?>
      </a>
      <?php endif; endforeach; ?>
      <?php if (empty($_errNavItems)): ?>
      <a href="<?php echo htmlspecialchars($_errBaseUrl ?: '/'); ?>">Home</a>
      <?php endif; ?>
    </nav>
    <div class="header-actions">
      <button id="theme-toggle" class="theme-toggle" aria-label="Toggle color theme">
        <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
      <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<main>
  <div style="display:flex;align-items:center;justify-content:center;padding:5rem 1.5rem;min-height:60vh;">
    <div style="text-align:center;max-width:480px;">
      <div style="font-size:6rem;font-weight:800;line-height:1;color:var(--ps-primary,#6366f1);margin-bottom:1rem;letter-spacing:-2px;">404</div>
      <h1 style="font-size:1.5rem;font-weight:600;margin-bottom:.625rem;">Page Not Found</h1>
      <p style="color:var(--ps-text-muted,#6b7280);margin-bottom:2rem;line-height:1.6;">
        The page you&rsquo;re looking for doesn&rsquo;t exist or has been moved.
      </p>
      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
        <a href="<?php echo htmlspecialchars($_errBaseUrl ?: '/'); ?>" class="btn btn-primary">
          Go to Homepage
        </a>
        <button onclick="history.back()" class="btn btn-outline-secondary">
          Go Back
        </button>
      </div>
    </div>
  </div>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="site-footer-inner">
      <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($_errSiteName); ?></p>
      <p>Powered by <a href="https://github.com/primaybr/vertext" target="_blank" rel="noopener">Vertext</a></p>
    </div>
  </div>
</footer>

<script src="<?php echo htmlspecialchars($_errThemeUrl . '/js/theme.js'); ?>"></script>
</body>
</html>
