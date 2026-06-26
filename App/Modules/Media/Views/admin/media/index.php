<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-image me-2 text-primary"></i>Media Library</h1>
    <p class="vtx-page-desc">Upload and manage images used across your site.</p>
  </div>
  <div style="display:flex;gap:.5rem;align-items:center;">
    <?php if (\App\CMS\Auth::can('media.edit') && ($missingThumbCount ?? 0) > 0): ?>
    <button type="button" class="btn btn-outline-secondary btn-sm"
            id="vtx-regen-thumbs-btn" title="Generate missing thumbnails">
      <i class="pi pi-refresh me-1"></i>
      Regenerate Thumbnails
      <span class="vtx-badge" style="background:var(--ps-warning);color:#000;font-size:.7rem;padding:.15rem .4rem;border-radius:999px;margin-left:.25rem;"><?php echo (int)($missingThumbCount ?? 0); ?></span>
    </button>
    <?php endif; ?>
    <?php if (\App\CMS\Auth::can('media.upload')): ?>
    <button type="button" class="btn btn-primary"
            id="vtx-media-upload-btn">
      <i class="pi pi-plus me-1"></i> Upload
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Upload Zone (hidden by default) -->
<?php if (\App\CMS\Auth::can('media.upload')): ?>
<div id="vtx-upload-zone" class="vtx-panel mb-3" style="display:none;">
  <div class="vtx-panel-body">
    <div data-vtx-upload
         data-url="{{baseUrl}}/admin/media/upload"
         data-csrf="{{csrf_token}}"
         data-accept="image/*"
         data-max-mb="5"
         style="border:2px dashed var(--ps-border);border-radius:8px;padding:2.5rem;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;">
      <i class="pi pi-image" style="font-size:2rem;color:var(--ps-text-muted);display:block;margin-bottom:.75rem;"></i>
      <div style="font-size:.9375rem;font-weight:500;color:var(--ps-text-secondary);">Drag &amp; drop images here</div>
      <div style="font-size:.8125rem;color:var(--ps-text-muted);margin-top:.25rem;">or click to browse - JPG, PNG, GIF, WebP · max 5 MB</div>
    </div>
  </div>
</div>
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

<?php if (\App\CMS\Auth::can('media.delete')): ?>
<!-- Hidden bulk form -->
<form id="vtx-media-bulk-form" method="POST" action="{{baseUrl}}/admin/media/bulk" style="display:none;">
  <input type="hidden" name="csrf_token" value="{{csrf_token}}">
  <input type="hidden" name="bulk_action" id="vtx-media-bulk-action" value="">
</form>

<!-- Bulk action bar -->
<div id="vtx-media-bulk-bar" class="vtx-panel mb-3" style="display:none;">
  <div class="vtx-panel-body" style="padding:.5rem 1rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.875rem;font-weight:500;">
      <input type="checkbox" id="vtx-media-select-all">
      <span id="vtx-media-bulk-count">0 selected</span>
    </label>
    <div style="margin-left:auto;display:flex;gap:.5rem;">
      <button type="button" class="btn btn-danger btn-sm" id="vtx-media-bulk-delete">
        <i class="pi pi-trash me-1"></i> Delete Selected
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

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
        <?php if (\App\CMS\Auth::can('media.delete')): ?>
        <input type="checkbox" class="vtx-media-card-check" value="<?php echo $f['id']; ?>" title="Select">
        <?php endif; ?>
        <div class="vtx-media-thumb">
          <img src="<?php echo htmlspecialchars($f['thumbnail_url'] ?? $f['url']); ?>"
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

<?php if (\App\CMS\Auth::can('media.delete')): ?>
<script>
(function () {
    var bar      = document.getElementById('vtx-media-bulk-bar');
    var allChk   = document.getElementById('vtx-media-select-all');
    var countLbl = document.getElementById('vtx-media-bulk-count');
    var delBtn   = document.getElementById('vtx-media-bulk-delete');
    var grid     = document.getElementById('vtx-media-grid');
    if (!bar || !grid) return;

    function getChecked() {
        return Array.from(grid.querySelectorAll('.vtx-media-card-check:checked'));
    }
    function sync() {
        var checked = getChecked();
        var n = checked.length;
        bar.style.display = n > 0 ? '' : 'none';
        if (countLbl) countLbl.textContent = n + ' selected';
        var all = grid.querySelectorAll('.vtx-media-card-check');
        if (allChk) allChk.checked = all.length > 0 && n === all.length;
        grid.querySelectorAll('.vtx-media-card').forEach(function (card) {
            var chk = card.querySelector('.vtx-media-card-check');
            if (chk) card.classList.toggle('vtx-media-selected', chk.checked);
        });
    }

    grid.addEventListener('change', function (e) {
        if (e.target.classList.contains('vtx-media-card-check')) sync();
    });
    if (allChk) {
        allChk.addEventListener('change', function () {
            grid.querySelectorAll('.vtx-media-card-check').forEach(function (c) {
                c.checked = allChk.checked;
            });
            sync();
        });
    }
    if (delBtn) {
        delBtn.addEventListener('click', function () {
            var ids = getChecked().map(function (c) { return c.value; });
            if (!ids.length) return;
            vtxConfirmModal({
                title: 'Delete ' + ids.length + ' file' + (ids.length > 1 ? 's' : ''),
                message: 'This will permanently delete the selected file' + (ids.length > 1 ? 's' : '') + '. This cannot be undone.',
                confirmLabel: 'Delete',
                confirmClass: 'btn-danger',
                onConfirm: function () {
                    var form = document.getElementById('vtx-media-bulk-form');
                    document.getElementById('vtx-media-bulk-action').value = 'delete';
                    ids.forEach(function (id) {
                        var inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'ids[]';
                        inp.value = id;
                        form.appendChild(inp);
                    });
                    VtxAjax.postForm('{{baseUrl}}/admin/media/bulk', form, function (res) {
                        if (res.success) {
                            Phuse.toast(res.message, 'success');
                            setTimeout(function () { window.location.reload(); }, 800);
                        } else {
                            Phuse.toast(res.message || 'Failed.', 'error');
                        }
                    });
                }
            });
        });
    }
}());
</script>
<?php endif; ?>

<?php if (\App\CMS\Auth::can('media.edit')): ?>
<script>
(function () {
    var btn = document.getElementById('vtx-regen-thumbs-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.innerHTML = '<i class="pi pi-spin pi-refresh me-1"></i> Generating…';
        var fd = new FormData();
        fd.append('csrf_token', '{{csrf_token}}');
        fetch('{{baseUrl}}/admin/media/regen-thumbnails', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    if (d.remaining > 0) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="pi pi-refresh me-1"></i> Regenerate Thumbnails <span style="background:var(--ps-warning);color:#000;font-size:.7rem;padding:.15rem .4rem;border-radius:999px;margin-left:.25rem;">' + d.remaining + '</span>';
                    } else {
                        btn.style.display = 'none';
                    }
                    Phuse.toast(d.message, 'success');
                    // Reload grid to show new thumbnails
                    if (d.processed > 0) setTimeout(function () { window.location.reload(); }, 1200);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="pi pi-refresh me-1"></i> Regenerate Thumbnails';
                    Phuse.toast(d.message || 'Failed.', 'error');
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="pi pi-refresh me-1"></i> Regenerate Thumbnails';
            });
    });
}());
</script>
<?php endif; ?>
