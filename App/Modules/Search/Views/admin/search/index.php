<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-search me-2 text-primary"></i>Search</h1>
    <p class="vtx-page-desc">Full-text search index for public content. Reindex after publishing new content.</p>
  </div>
  <?php if (\App\CMS\Auth::can('search.manage')): ?>
  <form method="POST" action="<?php echo $baseUrl; ?>/admin/search/reindex" data-ajax-json id="reindex-form">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
    <button type="submit" class="btn btn-primary" id="reindex-btn">
      <i class="pi pi-refresh me-1"></i> Reindex Now
    </button>
  </form>
  <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
  <div class="vtx-panel" style="padding:1.25rem;">
    <div style="font-size:.75rem;color:var(--ps-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">Total Indexed</div>
    <div style="font-size:2rem;font-weight:700;color:var(--ps-text-primary);"><?php echo number_format($total ?? 0); ?></div>
    <div style="font-size:.8125rem;color:var(--ps-text-muted);">items in index</div>
  </div>
  <div class="vtx-panel" style="padding:1.25rem;">
    <div style="font-size:.75rem;color:var(--ps-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">Pages</div>
    <div style="font-size:2rem;font-weight:700;color:var(--ps-text-primary);"><?php echo (int) (($counts ?? [])['page'] ?? 0); ?></div>
    <div style="font-size:.8125rem;color:var(--ps-text-muted);">published pages</div>
  </div>
  <div class="vtx-panel" style="padding:1.25rem;">
    <div style="font-size:.75rem;color:var(--ps-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">Blog Posts</div>
    <div style="font-size:2rem;font-weight:700;color:var(--ps-text-primary);"><?php echo (int) (($counts ?? [])['post'] ?? 0); ?></div>
    <div style="font-size:.8125rem;color:var(--ps-text-muted);">published posts</div>
  </div>
  <div class="vtx-panel" style="padding:1.25rem;">
    <div style="font-size:.75rem;color:var(--ps-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">Last Indexed</div>
    <div style="font-size:1.125rem;font-weight:600;color:var(--ps-text-primary);">
      <?php echo $lastIndexed ? date('M d, Y H:i', strtotime($lastIndexed)) : '-'; ?>
    </div>
    <div style="font-size:.8125rem;color:var(--ps-text-muted);">most recent reindex</div>
  </div>
</div>

<div class="vtx-panel">
  <div class="vtx-panel-body" style="padding:1.25rem;">
    <h6 style="margin:0 0 .75rem;font-weight:600;">How it works</h6>
    <ul style="margin:0;padding-left:1.25rem;font-size:.875rem;color:var(--ps-text-muted);line-height:1.75;">
      <li>The index is built from published Pages and Blog posts.</li>
      <li>Click <strong>Reindex Now</strong> after publishing or editing content to keep results fresh.</li>
      <li>Visitors search at <code><?php echo $baseUrl; ?>/search?q=...</code></li>
      <li>Results are limited to 30 items, ranked by text match.</li>
    </ul>
  </div>
</div>
