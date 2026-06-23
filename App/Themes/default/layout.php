<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(($pageTitle ?: $siteName) . ($pageTitle ? ' — ' . $siteName : '')); ?></title>
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
  <link rel="stylesheet" href="<?php echo htmlspecialchars($themeUrl . '/css/theme.css'); ?>">
</head>
<body>

<!-- Site Header -->
<header class="site-header">
  <div class="container">
    <a href="<?php echo htmlspecialchars($baseUrl ?: '/'); ?>" class="site-name">
      <?php echo htmlspecialchars($siteName); ?>
    </a>
    <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <nav class="site-nav">
      <?php
      // Dynamic nav links from installed module settings
      // Blog link — shown if blog module is active
      $blogBase = trim($site['blog_base_path'] ?? 'blog', '/');
      if ($blogBase):
      ?>
      <a href="<?php echo htmlspecialchars($baseUrl . '/' . $blogBase); ?>">Blog</a>
      <?php endif; ?>
      <?php
      // Contact link — shown if contact_path is set
      $contactPath = trim($site['contact_path'] ?? '', '/');
      if ($contactPath):
      ?>
      <a href="<?php echo htmlspecialchars($baseUrl . '/' . $contactPath); ?>">Contact</a>
      <?php endif; ?>
      <?php
      // Videos link — shown if videos module is active (no setting, just try)
      // Gallery link
      $galleryPath = trim($site['gallery_path'] ?? '', '/');
      if ($galleryPath):
      ?>
      <a href="<?php echo htmlspecialchars($baseUrl . '/' . $galleryPath); ?>">Gallery</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<!-- Main Content -->
<main>
  <?php echo $content; ?>
</main>

<!-- Site Footer -->
<footer class="site-footer">
  <div class="container">
    <div class="site-footer-inner">
      <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?></p>
      <p>Powered by <a href="https://github.com/your-username/vertext" target="_blank" rel="noopener">Vertext</a></p>
    </div>
  </div>
</footer>

<script src="<?php echo htmlspecialchars($themeUrl . '/js/theme.js'); ?>"></script>
</body>
</html>
