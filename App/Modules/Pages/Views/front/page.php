<?php $pageTemplate = $page['template'] ?? 'default'; ?>
<style>
  .page-content-wrap { padding: 2rem 0; }
  .page-title { font-size: 2rem; font-weight: 800; line-height: 1.25; margin: 0 0 2rem; }
  /* Template variants */
  .page-tpl-full-width .page-inner { max-width: none; }
  .page-tpl-sidebar .page-columns { display: grid; grid-template-columns: 1fr 260px; gap: 2.5rem; align-items: start; }
  .page-tpl-sidebar .page-aside { background: var(--clr-surface); border: 1px solid var(--clr-border);
    border-radius: 8px; padding: 1.25rem; font-size: .9rem; }
  .page-tpl-sidebar .page-aside h4 { margin: 0 0 .5rem; font-size: .95rem; }
  .page-tpl-landing .page-title { display: none; }
  @media (max-width: 720px) { .page-tpl-sidebar .page-columns { grid-template-columns: 1fr; } }
  .page-body { font-size: 1.0625rem; line-height: 1.75; }
  .page-body h1, .page-body h2, .page-body h3 { font-weight: 700; margin: 2rem 0 .75rem; line-height: 1.3; }
  .page-body p { margin: 0 0 1.25rem; }
  .page-body pre { background: var(--clr-surface); padding: 1rem; border-radius: 6px; overflow-x: auto; font-size: .875rem; }
  .page-body blockquote { border-left: 3px solid var(--clr-border); margin: 1.5rem 0; padding: .5rem 1.25rem; color: var(--clr-muted); }
  .page-body img { max-width: 100%; border-radius: 6px; }
  .page-body a { color: var(--clr-accent); }
</style>

<div class="<?php echo $pageTemplate === 'full-width' ? 'container-wide' : 'container'; ?> page-tpl-<?php echo htmlspecialchars($pageTemplate); ?>">
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
          <p style="color:var(--clr-muted);margin:0;">Add <code>sidebar_title</code> / <code>sidebar_html</code> custom fields to fill this sidebar.</p>
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
