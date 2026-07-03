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
         data-url="{{baseUrl}}/admin/media/upload<?php echo ($folder !== '' && $folder !== 'unfiled') ? '?folder_id=' . urlencode($folder) : ''; ?>"
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

<!-- Folders + search -->
<div class="vtx-panel mb-3">
  <div class="vtx-panel-body" style="padding:.75rem 1rem;display:flex;flex-direction:column;gap:.75rem;">
    <div style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;">
      <i class="pi pi-package" style="color:var(--ps-text-muted);margin-right:.15rem;"></i>
      <a href="{{baseUrl}}/admin/media" class="btn btn-sm <?php echo $folder === '' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
        All (<?php echo (int) ($total ?? 0); ?><?php echo $folder !== '' ? '+' : ''; ?>)
      </a>
      <a href="{{baseUrl}}/admin/media?folder=unfiled" class="btn btn-sm <?php echo $folder === 'unfiled' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
        Unfiled
      </a>
      <?php foreach (($folders ?? []) as $fld): ?>
      <a href="{{baseUrl}}/admin/media?folder=<?php echo $fld['id']; ?>"
         class="btn btn-sm <?php echo $folder === $fld['id'] ? 'btn-primary' : 'btn-outline-secondary'; ?>">
        <?php echo htmlspecialchars($fld['name']); ?> (<?php echo (int) $fld['count']; ?>)
      </a>
      <?php endforeach; ?>
      <?php if (\App\CMS\Auth::can('media.upload')): ?>
      <button type="button" class="btn btn-sm btn-link" id="vtx-folder-new" title="New folder">
        <i class="pi pi-plus me-1"></i>Folder
      </button>
      <?php endif; ?>
      <?php if ($currentFolder ?? null): ?>
      <span style="margin-left:auto;display:flex;gap:.25rem;">
        <?php if (\App\CMS\Auth::can('media.edit')): ?>
        <button type="button" class="vtx-icon-btn" id="vtx-folder-rename" title="Rename this folder"
                data-folder-id="<?php echo $currentFolder['id']; ?>"
                data-folder-name="<?php echo htmlspecialchars($currentFolder['name']); ?>">
          <i class="pi pi-edit"></i>
        </button>
        <?php endif; ?>
        <?php if (\App\CMS\Auth::can('media.delete')): ?>
        <button type="button" class="vtx-icon-btn danger" id="vtx-folder-delete" title="Delete this folder"
                data-folder-id="<?php echo $currentFolder['id']; ?>"
                data-folder-name="<?php echo htmlspecialchars($currentFolder['name']); ?>">
          <i class="pi pi-trash"></i>
        </button>
        <?php endif; ?>
      </span>
      <?php endif; ?>
    </div>
    <form method="GET" action="{{baseUrl}}/admin/media" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder ?? ''); ?>">
      <div style="flex:1;min-width:200px;">
        <input class="form-control form-control-sm" type="search" name="search"
               value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Search by filename…">
      </div>
      <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
      <?php if (!empty($search)): ?>
      <a href="{{baseUrl}}/admin/media?folder=<?php echo urlencode($folder ?? ''); ?>" class="btn btn-link btn-sm text-muted">Clear</a>
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
    <div style="margin-left:auto;display:flex;gap:.5rem;align-items:center;">
      <select class="form-select form-select-sm" id="vtx-media-move-target" style="width:auto;">
        <option value="">Move to…</option>
        <option value="unfiled">Unfiled</option>
        <?php foreach (($folders ?? []) as $fld): ?>
        <option value="<?php echo $fld['id']; ?>"><?php echo htmlspecialchars($fld['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="vtx-media-bulk-move">
        <i class="pi pi-arrow-right me-1"></i> Move
      </button>
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
          <button type="button" class="vtx-icon-btn" title="Edit details"
                  data-form-url="{{baseUrl}}/admin/media/<?php echo $f['id']; ?>/edit-form"
                  data-form-title="Edit Media">
            <i class="pi pi-edit"></i>
          </button>
          <?php if (str_starts_with((string) $f['mime_type'], 'image/') && $f['mime_type'] !== 'image/gif'): ?>
          <button type="button" class="vtx-icon-btn" title="Edit image (crop / rotate / flip)"
                  data-image-editor="<?php echo $f['id']; ?>"
                  data-image-url="<?php echo htmlspecialchars($f['url']); ?>"
                  data-image-w="<?php echo (int) $f['width']; ?>"
                  data-image-h="<?php echo (int) $f['height']; ?>">
            <i class="pi pi-sliders"></i>
          </button>
          <?php endif; ?>
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
    var moveBtn = document.getElementById('vtx-media-bulk-move');
    if (moveBtn) {
        moveBtn.addEventListener('click', function () {
            var ids    = getChecked().map(function (c) { return c.value; });
            var target = document.getElementById('vtx-media-move-target').value;
            if (!ids.length) return;
            if (!target) { Phuse.toast('Choose a destination folder first.', 'error'); return; }
            var fd = new FormData();
            fd.append('csrf_token', '{{csrf_token}}');
            fd.append('bulk_action', 'move');
            fd.append('folder_id', target);
            ids.forEach(function (id) { fd.append('ids[]', id); });
            fetch('{{baseUrl}}/admin/media/bulk', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    Phuse.toast(res.message || (res.success ? 'Moved.' : 'Failed.'), res.success ? 'success' : 'error');
                    if (res.success) setTimeout(function () { window.location.reload(); }, 800);
                })
                .catch(function () { Phuse.toast('Network error.', 'error'); });
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

<?php if (\App\CMS\Auth::can('media.upload')): ?>
<script>
(function () {
    'use strict';
    function post(url, fields, done) {
        var fd = new FormData();
        fd.append('csrf_token', '{{csrf_token}}');
        Object.keys(fields || {}).forEach(function (k) { fd.append(k, fields[k]); });
        fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) { done(res); })
            .catch(function () { done({ success: false, message: 'Network error.' }); });
    }

    var newBtn = document.getElementById('vtx-folder-new');
    if (newBtn) {
        newBtn.addEventListener('click', function () {
            var name = window.prompt('New folder name:', '');
            if (name === null) return;
            name = name.trim();
            if (!name) return;
            post('{{baseUrl}}/admin/media/folders/store', { name: name }, function (res) {
                Phuse.toast(res.message || '', res.success ? 'success' : 'error');
                if (res.success) setTimeout(function () { window.location = '{{baseUrl}}/admin/media?folder=' + res.id; }, 600);
            });
        });
    }

    var renameBtn = document.getElementById('vtx-folder-rename');
    if (renameBtn) {
        renameBtn.addEventListener('click', function () {
            var name = window.prompt('Rename folder:', renameBtn.dataset.folderName);
            if (name === null) return;
            name = name.trim();
            if (!name || name === renameBtn.dataset.folderName) return;
            post('{{baseUrl}}/admin/media/folders/' + renameBtn.dataset.folderId + '/rename', { name: name }, function (res) {
                Phuse.toast(res.message || '', res.success ? 'success' : 'error');
                if (res.success) setTimeout(function () { window.location.reload(); }, 600);
            });
        });
    }

    var deleteBtn = document.getElementById('vtx-folder-delete');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            vtxConfirmModal({
                title: 'Delete Folder',
                message: 'Delete "' + deleteBtn.dataset.folderName + '"? Files inside are kept and become Unfiled.',
                confirmLabel: 'Delete',
                confirmClass: 'btn-danger',
                onConfirm: function () {
                    post('{{baseUrl}}/admin/media/folders/' + deleteBtn.dataset.folderId + '/delete', {}, function (res) {
                        Phuse.toast(res.message || '', res.success ? 'success' : 'error');
                        if (res.success) setTimeout(function () { window.location = '{{baseUrl}}/admin/media'; }, 700);
                    });
                }
            });
        });
    }
}());
</script>
<?php endif; ?>

<?php if (\App\CMS\Auth::can('media.edit')): ?>
<!-- Image editor modal -->
<div id="vtx-imged-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1060;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:var(--ps-bg-base);border-radius:8px;padding:1.25rem;width:100%;max-width:760px;max-height:92vh;overflow:auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
      <h5 style="margin:0;">Edit Image</h5>
      <button type="button" class="vtx-icon-btn" onclick="document.getElementById('vtx-imged-modal').style.display='none'">
        <i class="pi pi-x-circle"></i>
      </button>
    </div>
    <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.75rem;">
      <button type="button" class="btn btn-outline-secondary btn-sm" data-imged-op="rotate-left" title="Rotate left"><i class="pi pi-refresh me-1"></i>Rotate L</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-imged-op="rotate-right" title="Rotate right"><i class="pi pi-refresh me-1"></i>Rotate R</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-imged-op="flip-h" title="Flip horizontal">Flip H</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-imged-op="flip-v" title="Flip vertical">Flip V</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-imged-op="crop-clear" title="Clear crop selection">Clear Crop</button>
      <span style="margin-left:auto;font-size:.75rem;color:var(--ps-text-muted);align-self:center;" id="vtx-imged-hint">
        Drag on the image to select a crop area
      </span>
    </div>
    <div style="text-align:center;background:var(--ps-bg-alt);border-radius:6px;padding:.5rem;">
      <canvas id="vtx-imged-canvas" style="max-width:100%;cursor:crosshair;"></canvas>
    </div>
    <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1rem;flex-wrap:wrap;">
      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('vtx-imged-modal').style.display='none'">Cancel</button>
      <button type="button" class="btn btn-outline-primary btn-sm" id="vtx-imged-save-copy"><i class="pi pi-plus me-1"></i>Save as Copy</button>
      <button type="button" class="btn btn-primary btn-sm" id="vtx-imged-overwrite"><i class="pi pi-save me-1"></i>Overwrite Original</button>
    </div>
  </div>
</div>

<script>
(function () {
    'use strict';
    var modal   = document.getElementById('vtx-imged-modal');
    var canvas  = document.getElementById('vtx-imged-canvas');
    var ctx     = canvas.getContext('2d');
    var state   = null; // {id, img, ops[], crop{x,y,w,h}|null, dragStart}

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-image-editor]');
        if (!btn) return;
        var img = new Image();
        img.onload = function () {
            state = { id: btn.dataset.imageEditor, img: img, ops: [], crop: null, dragStart: null };
            draw();
            modal.style.display = 'flex';
        };
        img.onerror = function () { Phuse.toast('Could not load the image.', 'error'); };
        img.src = btn.dataset.imageUrl + (btn.dataset.imageUrl.indexOf('?') === -1 ? '?' : '&') + 'v=' + Date.now();
    });

    // Render the source image through the pending ops so the preview matches
    // what the server will produce.
    function draw() {
        if (!state) return;

        var off = document.createElement('canvas');
        off.width = state.img.naturalWidth; off.height = state.img.naturalHeight;
        off.getContext('2d').drawImage(state.img, 0, 0);

        state.ops.forEach(function (op) {
            var src = off;
            if (op.op === 'rotate') {
                var nw = src.height, nh = src.width;
                var next = document.createElement('canvas');
                next.width = nw; next.height = nh;
                var nctx = next.getContext('2d');
                nctx.translate(nw / 2, nh / 2);
                nctx.rotate(op.deg * Math.PI / 180);
                nctx.drawImage(src, -src.width / 2, -src.height / 2);
                off = next;
            } else if (op.op === 'flip') {
                var next2 = document.createElement('canvas');
                next2.width = src.width; next2.height = src.height;
                var n2 = next2.getContext('2d');
                if (op.dir === 'h') { n2.translate(src.width, 0); n2.scale(-1, 1); }
                else { n2.translate(0, src.height); n2.scale(1, -1); }
                n2.drawImage(src, 0, 0);
                off = next2;
            }
        });

        state.rendered = off; // post-op pixels; crop coords refer to THIS

        var scale = Math.min(1, 700 / off.width, 480 / off.height);
        canvas.width  = Math.round(off.width * scale);
        canvas.height = Math.round(off.height * scale);
        state.scale   = scale;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(off, 0, 0, canvas.width, canvas.height);

        if (state.crop) {
            var c = state.crop;
            ctx.save();
            ctx.fillStyle = 'rgba(0,0,0,.45)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(off, c.x, c.y, c.w, c.h, c.x * scale, c.y * scale, c.w * scale, c.h * scale);
            ctx.strokeStyle = '#4f46e5';
            ctx.lineWidth = 2;
            ctx.strokeRect(c.x * scale, c.y * scale, c.w * scale, c.h * scale);
            ctx.restore();
        }
    }

    canvas.addEventListener('mousedown', function (e) {
        if (!state) return;
        var r = canvas.getBoundingClientRect();
        var cssScale = canvas.width / r.width; // CSS max-width shrink factor
        state.dragStart = { x: (e.clientX - r.left) * cssScale / state.scale, y: (e.clientY - r.top) * cssScale / state.scale };
    });
    canvas.addEventListener('mousemove', function (e) {
        if (!state || !state.dragStart) return;
        var r = canvas.getBoundingClientRect();
        var cssScale = canvas.width / r.width;
        var cur = { x: (e.clientX - r.left) * cssScale / state.scale, y: (e.clientY - r.top) * cssScale / state.scale };
        state.crop = {
            x: Math.round(Math.min(state.dragStart.x, cur.x)),
            y: Math.round(Math.min(state.dragStart.y, cur.y)),
            w: Math.round(Math.abs(cur.x - state.dragStart.x)),
            h: Math.round(Math.abs(cur.y - state.dragStart.y))
        };
        draw();
    });
    window.addEventListener('mouseup', function () { if (state) state.dragStart = null; });

    document.querySelectorAll('[data-imged-op]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!state) return;
            switch (btn.dataset.imgedOp) {
                case 'rotate-left':  state.ops.push({ op: 'rotate', deg: -90 }); state.crop = null; break;
                case 'rotate-right': state.ops.push({ op: 'rotate', deg: 90 });  state.crop = null; break;
                case 'flip-h':       state.ops.push({ op: 'flip', dir: 'h' });   state.crop = null; break;
                case 'flip-v':       state.ops.push({ op: 'flip', dir: 'v' });   state.crop = null; break;
                case 'crop-clear':   state.crop = null; break;
            }
            draw();
        });
    });

    function save(mode) {
        if (!state) return;
        var ops = state.ops.slice();
        if (state.crop && state.crop.w > 9 && state.crop.h > 9) {
            ops.push({ op: 'crop', x: state.crop.x, y: state.crop.y, w: state.crop.w, h: state.crop.h });
        }
        if (!ops.length) { Phuse.toast('No changes to save.', 'error'); return; }

        var fd = new FormData();
        fd.append('csrf_token', '{{csrf_token}}');
        fd.append('ops', JSON.stringify(ops));
        fd.append('mode', mode);
        fetch('{{baseUrl}}/admin/media/' + state.id + '/edit-image', {
            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            Phuse.toast(res.message || (res.success ? 'Saved.' : 'Failed.'), res.success ? 'success' : 'error');
            if (res.success) { modal.style.display = 'none'; setTimeout(function () { window.location.reload(); }, 900); }
        })
        .catch(function () { Phuse.toast('Network error.', 'error'); });
    }

    document.getElementById('vtx-imged-save-copy').addEventListener('click', function () { save('copy'); });
    document.getElementById('vtx-imged-overwrite').addEventListener('click', function () {
        vtxConfirmModal({
            title: 'Overwrite Original',
            message: 'Replace the original file with the edited version? This cannot be undone.',
            confirmLabel: 'Overwrite',
            confirmClass: 'btn-danger',
            onConfirm: function () { save('overwrite'); }
        });
    });
}());
</script>
<?php endif; ?>
