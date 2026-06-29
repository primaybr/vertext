<style>
  .page-content-wrap { padding: 2rem 0; }
  .page-title { font-size: 2rem; font-weight: 800; line-height: 1.25; margin: 0 0 2rem; }
  .page-body { font-size: 1.0625rem; line-height: 1.75; }
  .page-body h1, .page-body h2, .page-body h3 { font-weight: 700; margin: 2rem 0 .75rem; line-height: 1.3; }
  .page-body p { margin: 0 0 1.25rem; }
  .page-body pre { background: var(--clr-surface); padding: 1rem; border-radius: 6px; overflow-x: auto; font-size: .875rem; }
  .page-body blockquote { border-left: 3px solid var(--clr-border); margin: 1.5rem 0; padding: .5rem 1.25rem; color: var(--clr-muted); }
  .page-body img { max-width: 100%; border-radius: 6px; }
  .page-body a { color: var(--clr-accent); }
</style>

<div class="container">
  <div class="page-content-wrap">
    <h1 class="page-title"><?php echo htmlspecialchars($page['title']); ?></h1>
    <div class="page-body">
      <?php echo $page['content']; /* HTML from Quill, sanitized on save */ ?>
    </div>
  </div>
</div>
