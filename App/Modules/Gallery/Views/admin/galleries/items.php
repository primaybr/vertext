<div class="vtx-page-head">
  <div>
    <a href="{{baseUrl}}/admin/gallery" style="font-size:.875rem;color:var(--ps-text-muted);">
      ← Gallery
    </a>
    <h1 class="vtx-page-title" style="margin-top:.25rem;">
      <i class="pi pi-images me-2 text-primary"></i><?php echo htmlspecialchars($gallery['title']); ?>
    </h1>
    <p class="vtx-page-desc">Drag to reorder. Click the media icon to add images.</p>
  </div>
  <?php if (\App\CMS\Auth::can('gallery.edit')): ?>
  <button type="button" class="btn btn-primary" id="vtx-gallery-add-btn">
    <i class="pi pi-plus me-1"></i> Add Images
  </button>
  <?php endif; ?>
</div>

<!-- Hidden CSRF & gallery ID for JS -->
<input type="hidden" id="vtx-gallery-id" value="<?php echo htmlspecialchars($gallery['id']); ?>">
<input type="hidden" id="vtx-gallery-csrf" value="{{csrf_token}}">

<!-- Image Grid -->
<div class="vtx-panel">
  <?php if (empty($items)): ?>
  <div class="vtx-empty" id="vtx-gallery-empty">
    <div class="vtx-empty-ico"><i class="pi pi-image"></i></div>
    <div class="vtx-empty-title">No images yet</div>
    <div class="vtx-empty-desc">Click "Add Images" to add photos from the media library.</div>
  </div>
  <?php endif; ?>
  <div id="vtx-gallery-grid" class="vtx-gallery-items-grid"
       style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:1rem;padding:1rem;">
    <?php foreach ($items as $item): ?>
    <div class="vtx-gallery-item" data-id="<?php echo $item['id']; ?>"
         style="position:relative;border-radius:8px;overflow:hidden;background:var(--ps-bg-alt);aspect-ratio:1;cursor:grab;">
      <img src="<?php echo htmlspecialchars($item['thumbnail_url']); ?>"
           alt="<?php echo htmlspecialchars($item['alt_text'] ?? $item['original_name'] ?? ''); ?>"
           style="width:100%;height:100%;object-fit:cover;display:block;">
      <?php if (\App\CMS\Auth::can('gallery.edit')): ?>
      <button type="button" class="vtx-gallery-remove"
              data-id="<?php echo $item['id']; ?>"
              style="position:absolute;top:.375rem;right:.375rem;background:rgba(0,0,0,.65);color:#fff;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:.75rem;display:flex;align-items:center;justify-content:center;"
              title="Remove">×</button>
      <?php endif; ?>
      <div class="drag-handle"
           style="position:absolute;bottom:.375rem;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.5);color:#fff;border-radius:4px;padding:.125rem .375rem;font-size:.7rem;cursor:grab;">
        ⠿
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
(function () {
    var galleryId = document.getElementById('vtx-gallery-id').value;
    var csrf      = document.getElementById('vtx-gallery-csrf').value;
    var grid      = document.getElementById('vtx-gallery-grid');
    var baseUrl   = '{{baseUrl}}';

    // ── Add button → open media picker ──
    var addBtn = document.getElementById('vtx-gallery-add-btn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            // Open a lightweight media picker overlay using the existing picker endpoint
            var frame = document.createElement('iframe');
            frame.src = baseUrl + '/admin/media/picker';
            frame.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;border:none;z-index:9999;background:#fff;';
            document.body.appendChild(frame);

            window.__vtxMediaPickerCallback = function (url, id) {
                document.body.removeChild(frame);
                delete window.__vtxMediaPickerCallback;
                addImageToGallery(id);
            };
        });
    }

    function addImageToGallery(mediaId) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('media_file_id', mediaId);
        fetch(baseUrl + '/admin/gallery/' + galleryId + '/items/add', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    window.location.reload();
                } else {
                    if (window.vtxToast) window.vtxToast(d.message || 'Failed.', 'error');
                    else alert(d.message || 'Failed.');
                }
            });
    }

    // ── Remove buttons ──
    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('.vtx-gallery-remove');
        if (!btn) return;
        var itemId = btn.dataset.id;
        if (!confirm('Remove this image from the album?')) return;
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fetch(baseUrl + '/admin/gallery/' + galleryId + '/items/' + itemId + '/remove', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    btn.closest('.vtx-gallery-item').remove();
                    if (!grid.querySelector('.vtx-gallery-item')) {
                        var empty = document.getElementById('vtx-gallery-empty');
                        if (empty) empty.style.display = '';
                    }
                } else {
                    if (window.vtxToast) window.vtxToast(d.message || 'Failed.', 'error');
                }
            });
    });

    // ── Drag-to-reorder ──
    var dragging = null;
    var items    = Array.from(grid.querySelectorAll('.vtx-gallery-item'));
    items.forEach(function (el) { makeDraggable(el); });

    function makeDraggable(el) {
        el.draggable = true;
        el.addEventListener('dragstart', function () {
            dragging = el;
            setTimeout(function () { el.style.opacity = '.4'; }, 0);
        });
        el.addEventListener('dragend', function () {
            el.style.opacity = '';
            dragging = null;
            saveOrder();
        });
        el.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (dragging && dragging !== el) {
                var r = el.getBoundingClientRect();
                var mid = r.left + r.width / 2;
                if (e.clientX < mid) {
                    grid.insertBefore(dragging, el);
                } else {
                    grid.insertBefore(dragging, el.nextSibling);
                }
            }
        });
    }

    function saveOrder() {
        var order = Array.from(grid.querySelectorAll('.vtx-gallery-item')).map(function (el, i) {
            return { id: el.dataset.id, sort_order: i };
        });
        fetch(baseUrl + '/admin/gallery/' + galleryId + '/items/reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(order)
        });
    }
}());
</script>
