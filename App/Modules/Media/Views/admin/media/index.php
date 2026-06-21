<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-image me-2 text-primary"></i>Media Library</h1>
    <p class="vtx-page-desc">Upload and manage images used across your site.</p>
  </div>
  <?php if (\App\CMS\Auth::can('media.upload')): ?>
  <button type="button" class="btn btn-primary"
          id="vtx-media-upload-btn">
    <i class="pi pi-plus me-1"></i> Upload
  </button>
  <?php endif; ?>
</div>

<!-- Upload Zone (hidden by default) -->
<?php if (\App\CMS\Auth::can('media.upload')): ?>
<div id="vtx-upload-zone" class="vtx-panel mb-3" style="display:none;">
  <div class="vtx-panel-body">
    <div data-vtx-upload
         data-url="{{baseUrl}}/admin/media/upload"
         data-csrf="{{csrf_token}}"
         data-accept="image/*"
         style="border:2px dashed var(--ps-border);border-radius:8px;padding:2.5rem;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;">
      <i class="pi pi-image" style="font-size:2rem;color:var(--ps-text-muted);display:block;margin-bottom:.75rem;"></i>
      <div style="font-size:.9375rem;font-weight:500;color:var(--ps-text-secondary);">Drag &amp; drop images here</div>
      <div style="font-size:.8125rem;color:var(--ps-text-muted);margin-top:.25rem;">or click to browse — JPG, PNG, GIF, WebP · max 2 MB</div>
    </div>
  </div>
</div>
<script>
document.getElementById('vtx-media-upload-btn').addEventListener('click', function () {
    var zone = document.getElementById('vtx-upload-zone');
    zone.style.display = zone.style.display === 'none' ? '' : 'none';
});
document.addEventListener('vtx:upload:done', function (e) {
    if (e.detail && e.detail.file) {
        location.reload();
    }
});
</script>
<?php endif; ?>

<!-- Search -->
<div class="vtx-panel mb-3">
  <div class="vtx-panel-body" style="padding:.75rem 1rem;">
    <form method="GET" action="{{baseUrl}}/admin/media" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <div style="flex:1;min-width:200px;">
        <input class="form-control form-control-sm" type="search" name="search"
               value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Search by filename…">
      </div>
      <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
      <?php if (!empty($search)): ?>
      <a href="{{baseUrl}}/admin/media" class="btn btn-link btn-sm text-muted">Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Grid -->
<div class="vtx-panel">
  <?php if (empty($files)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-image"></i></div>
    <div class="vtx-empty-title">No media files yet</div>
    <div class="vtx-empty-desc">Upload your first image to get started.</div>
  </div>
  <?php else: ?>
  <div class="vtx-panel-body">
    <div class="vtx-media-grid" id="vtx-media-grid">
      <?php foreach ($files as $f): ?>
      <div class="vtx-media-card" data-id="<?php echo $f['id']; ?>">
        <div class="vtx-media-thumb">
          <img src="<?php echo htmlspecialchars($f['url']); ?>"
               alt="<?php echo htmlspecialchars($f['alt_text'] ?? $f['original_name']); ?>"
               loading="lazy">
        </div>
        <div class="vtx-media-info">
          <div class="vtx-media-name" title="<?php echo htmlspecialchars($f['original_name']); ?>">
            <?php echo htmlspecialchars($f['original_name']); ?>
          </div>
          <?php if ($f['width'] && $f['height']): ?>
          <div class="vtx-media-meta"><?php echo $f['width']; ?> × <?php echo $f['height']; ?> px</div>
          <?php endif; ?>
        </div>
        <div class="vtx-media-actions">
          <?php if (\App\CMS\Auth::can('media.edit')): ?>
          <button type="button" class="vtx-icon-btn" title="Edit"
                  data-form-url="{{baseUrl}}/admin/media/<?php echo $f['id']; ?>/edit-form"
                  data-form-title="Edit Media">
            <i class="pi pi-edit"></i>
          </button>
          <?php endif; ?>
          <?php if (\App\CMS\Auth::can('media.delete')): ?>
          <form id="del-media-<?php echo $f['id']; ?>" method="POST"
                action="{{baseUrl}}/admin/media/<?php echo $f['id']; ?>/delete" style="display:none;">
            <input type="hidden" name="csrf_token" value="{{csrf_token}}">
          </form>
          <button type="button" class="vtx-icon-btn danger" title="Delete"
                  data-confirm-form="del-media-<?php echo $f['id']; ?>"
                  data-confirm-title="Delete File"
                  data-confirm-message="Delete &quot;<?php echo htmlspecialchars($f['original_name']); ?>&quot;? This cannot be undone."
                  data-confirm-label="Delete"
                  data-confirm-class="btn-danger"
                  data-confirm-ajax="true">
            <i class="pi pi-trash"></i>
          </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Pagination -->
  <?php if (($pages ?? 1) > 1): ?>
  <div class="vtx-panel-body" style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--ps-border);padding-top:.75rem;">
    <span style="font-size:.8125rem;color:var(--ps-text-muted);">
      <?php
      $from = (($page - 1) * 24) + 1;
      $to   = min($page * 24, $total);
      echo "Showing {$from}–{$to} of {$total} files";
      ?>
    </span>
    <div style="display:flex;gap:.25rem;">
      <?php if ($page > 1): ?>
      <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search ?? ''); ?>"
         class="btn btn-outline-secondary btn-sm"><i class="pi pi-arrow-left"></i></a>
      <?php endif; ?>
      <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
      <a href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search ?? ''); ?>"
         class="btn btn-sm <?php echo $p === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>">
        <?php echo $p; ?>
      </a>
      <?php endfor; ?>
      <?php if ($page < $pages): ?>
      <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search ?? ''); ?>"
         class="btn btn-outline-secondary btn-sm"><i class="pi pi-arrow-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<script>Vtx.load('upload');</script>
