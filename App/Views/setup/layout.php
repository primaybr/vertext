<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{pageTitle}} - Vertext CMS</title>
  <link rel="icon" type="image/svg+xml" href="{{assetsUrl}}images/logo/favicon.svg">
  <link rel="stylesheet" href="{{assetsUrl}}css/styles.css?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>">
  <link rel="stylesheet" href="{{assetsUrl}}css/admin.css?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>">
  <?php include ROOT . 'App' . DS . 'Views' . DS . '_shared' . DS . 'theme-init.php'; ?>
</head>
<body>

<div class="vtx-setup">
  {!! content !!}
</div>

<script src="{{assetsUrl}}js/scripts.js?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>"></script>
<script src="{{assetsUrl}}js/admin.js?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>"></script>
</body>
</html>
