<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(($pageTitle ?: $siteName) . ($pageTitle ? ' - ' . $siteName : '')); ?></title>
  <?php if ($pageDesc): ?>
  <meta name="description" content="<?php echo htmlspecialchars($pageDesc); ?>">
  <?php endif; ?>
  <?php if ($pageImage): ?>
  <meta property="og:image" content="<?php echo htmlspecialchars($pageImage); ?>">
  <?php endif; ?>
  <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle ?: $siteName); ?>">
  <?php if ($pageDesc): ?>
  <meta property="og:description" content="<?php echo htmlspecialchars($pageDesc); ?>">
  <?php endif; ?>
  <?php include ROOT . 'App' . DS . 'Views' . DS . '_shared' . DS . 'theme-init.php'; ?>
  <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars($baseUrl . '/assets/images/logo/favicon.svg'); ?>">
  <?php if (!empty($feedUrl)): ?>
  <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($siteName . ' RSS Feed'); ?>" href="<?php echo htmlspecialchars($feedUrl); ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl . '/assets/css/styles.css'); ?>?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($themeUrl . '/css/theme.css'); ?>?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>">
  <?php foreach (\App\CMS\ModuleLoader::frontAssets()['css'] as $__mAsset): ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl . '/assets/' . $__mAsset); ?>">
  <?php endforeach; ?>
  <?php // hreflang alternates for locale path-prefix routing (i18n v0.0.2) ?>
  <?php foreach (\App\CMS\I18n::getSupportedLocales() as $__hl): ?>
  <link rel="alternate" hreflang="<?php echo htmlspecialchars($__hl); ?>" href="<?php echo htmlspecialchars($baseUrl . '/' . $__hl . ($_SERVER['REQUEST_URI'] ?? '/')); ?>">
  <?php endforeach; ?>
  <?php if (class_exists('App\Modules\ThemeCustomizer\ThemeCustomizerHelper')) echo \App\Modules\ThemeCustomizer\ThemeCustomizerHelper::getCss(); ?>
</head>
<body>

<header class="site-header">
  <div class="container">
    <a href="<?php echo htmlspecialchars($baseUrl ?: '/'); ?>" class="site-name">
      <?php
      $tcLogo = class_exists('App\Modules\ThemeCustomizer\ThemeCustomizerHelper')
          ? \App\Modules\ThemeCustomizer\ThemeCustomizerHelper::getLogoUrl() : '';
      if ($tcLogo): ?>
      <img src="<?php echo htmlspecialchars($tcLogo); ?>" alt="<?php echo htmlspecialchars($siteName); ?>" loading="lazy" class="site-logo-img">
      <?php else: echo htmlspecialchars($siteName); endif; ?>
    </a>
    <nav class="site-nav">
      <?php
      $navItems = \App\CMS\NavHelper::getMenu('primary');
      if (!empty($navItems)):
          foreach ($navItems as $navItem):
              $target = !empty($navItem['open_in_new']) ? ' target="_blank" rel="noopener"' : '';
              if (!empty($navItem['children'])):
      ?>
      <div class="nav-dropdown">
        <a href="<?php echo htmlspecialchars($navItem['url']); ?>"<?php echo $target; ?> class="nav-dropdown-toggle">
          <?php echo htmlspecialchars($navItem['label']); ?>
        </a>
        <div class="nav-dropdown-menu">
          <?php foreach ($navItem['children'] as $child):
              $ctarget = !empty($child['open_in_new']) ? ' target="_blank" rel="noopener"' : ''; ?>
          <a href="<?php echo htmlspecialchars($child['url']); ?>"<?php echo $ctarget; ?>>
            <?php echo htmlspecialchars($child['label']); ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <a href="<?php echo htmlspecialchars($navItem['url']); ?>"<?php echo $target; ?>>
        <?php echo htmlspecialchars($navItem['label']); ?>
      </a>
      <?php
          endif;
          endforeach;
      endif; ?>
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
  <?php echo $content; ?>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="site-footer-inner">
      <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?></p>
      <p>Powered by <a href="https://github.com/primaybr/vertext" target="_blank" rel="noopener">Vertext</a></p>
    </div>
  </div>
</footer>

<script src="<?php echo htmlspecialchars($themeUrl . '/js/theme.js'); ?>?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>"></script>
<?php foreach (\App\CMS\ModuleLoader::frontAssets()['js'] as $__mAsset): ?>
<script src="<?php echo htmlspecialchars($baseUrl . '/assets/' . $__mAsset); ?>"></script>
<?php endforeach; ?>
</body>
</html>
