<?php $pageTemplate = $page['template'] ?? 'default'; ?>

<?php
  $pageContainerClass = match ($pageTemplate) {
      'full-width' => 'container-wide',
      'default'    => 'container-prose',
      default      => 'container', // sidebar, landing - need more than prose width
  };
?>
<div class="<?php echo $pageContainerClass; ?> page-tpl-<?php echo htmlspecialchars($pageTemplate); ?>">
  <div class="page-content-wrap page-inner">
    <h1 class="page-title"><?php echo htmlspecialchars($page['title']); ?></h1>

    <?php if ($pageTemplate === 'sidebar'): ?>
    <div class="page-columns">
      <div class="page-body">
        <?php echo $page['content']; /* HTML from Quill, sanitized on save */ ?>
      </div>
      <aside class="page-aside">
        <?php
        // Custom fields drive the sidebar: sidebar_title + sidebar_html (trusted, admin-authored)
        $sidebarTitle = $pageMeta['sidebar_title'] ?? '';
        $sidebarHtml  = $pageMeta['sidebar_html'] ?? '';
        ?>
        <?php if ($sidebarTitle !== ''): ?><h4><?php echo htmlspecialchars($sidebarTitle); ?></h4><?php endif; ?>
        <?php if ($sidebarHtml !== ''): ?>
          <?php echo $sidebarHtml; ?>
        <?php else: ?>
          <p class="page-aside-empty">Add <code>sidebar_title</code> / <code>sidebar_html</code> custom fields to fill this sidebar.</p>
        <?php endif; ?>
      </aside>
    </div>
    <?php else: ?>
    <div class="page-body">
      <?php echo $page['content']; /* HTML from Quill, sanitized on save */ ?>
    </div>
    <?php endif; ?>
  </div>
</div>
