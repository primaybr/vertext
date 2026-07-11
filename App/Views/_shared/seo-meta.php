  <title><?php echo htmlspecialchars(($pageTitle ?: $siteName) . ($pageTitle ? ' - ' . $siteName : '')); ?></title>
  <?php if ($pageDesc): ?>
  <meta name="description" content="<?php echo htmlspecialchars($pageDesc); ?>">
  <?php endif; ?>
  <?php if (!empty($canonicalUrl)): ?>
  <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">
  <?php endif; ?>
  <?php if ($pageImage): ?>
  <meta property="og:image" content="<?php echo htmlspecialchars($pageImage); ?>">
  <?php endif; ?>
  <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle ?: $siteName); ?>">
  <?php if ($pageDesc): ?>
  <meta property="og:description" content="<?php echo htmlspecialchars($pageDesc); ?>">
  <?php endif; ?>
  <meta name="twitter:card" content="<?php echo $pageImage ? 'summary_large_image' : 'summary'; ?>">
  <meta name="twitter:title" content="<?php echo htmlspecialchars($pageTitle ?: $siteName); ?>">
  <?php if ($pageDesc): ?>
  <meta name="twitter:description" content="<?php echo htmlspecialchars($pageDesc); ?>">
  <?php endif; ?>
  <?php if ($pageImage): ?>
  <meta name="twitter:image" content="<?php echo htmlspecialchars($pageImage); ?>">
  <?php endif; ?>
