<?php
// Media Picker partial — loaded via CRUD form modal
// On image click → window.__vtxMediaPickerCallback(url, id)
?>
<div class="vtx-media-picker-wrap">

  <!-- Search -->
  <div class="vtx-media-picker-search">
    <input class="form-control form-control-sm" type="search" id="vtx-picker-search"
           placeholder="Search…" value="<?php echo htmlspecialchars($search ?? ''); ?>"
           autocomplete="off">
    <div id="vtx-picker-upload-wrap">
      <div data-vtx-upload
           data-url="<?php echo htmlspecialchars($baseUrl); ?>/admin/media/upload"
           data-csrf="<?php echo htmlspecialchars($csrf_token ?? ''); ?>"
           data-accept="image/*"
           data-max-mb="5"
           class="vtx-picker-upload-zone">
        <i class="pi pi-plus"></i> Upload
      </div>
    </div>
  </div>

  <!-- Grid -->
  <div class="vtx-media-picker-grid" id="vtx-picker-grid">
    <?php if (empty($files)): ?>
    <div class="vtx-empty" style="padding:2rem;grid-column:1/-1;text-align:center;">
      <div class="vtx-empty-ico"><i class="pi pi-image"></i></div>
      <div class="vtx-empty-title" style="font-size:.875rem;">No media files yet</div>
    </div>
    <?php else: ?>
    <?php foreach ($files as $f):
          $isSelected = (int)$f['id'] === (int)($selectedId ?? 0);
    ?>
    <div class="vtx-picker-item <?php echo $isSelected ? 'selected' : ''; ?>"
         data-id="<?php echo $f['id']; ?>"
         data-url="<?php echo htmlspecialchars($f['url']); ?>"
         title="<?php echo htmlspecialchars($f['original_name']); ?>"
         role="button" tabindex="0">
      <img src="<?php echo htmlspecialchars($f['url']); ?>"
           alt="<?php echo htmlspecialchars($f['alt_text'] ?? $f['original_name']); ?>"
           loading="lazy">
      <?php if ($isSelected): ?>
      <div class="vtx-picker-check"><i class="pi pi-check-circle"></i></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if (($pages ?? 1) > 1): ?>
  <div class="vtx-media-picker-pager">
    <?php if ($page > 1): ?>
    <a href="<?php echo htmlspecialchars($baseUrl); ?>/admin/media/picker?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search ?? ''); ?>"
       class="btn btn-outline-secondary btn-sm vtx-picker-page-link">&laquo; Prev</a>
    <?php endif; ?>
    <span style="font-size:.8125rem;color:var(--ps-text-muted);">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
    <?php if ($page < $pages): ?>
    <a href="<?php echo htmlspecialchars($baseUrl); ?>/admin/media/picker?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search ?? ''); ?>"
       class="btn btn-outline-secondary btn-sm vtx-picker-page-link">Next &raquo;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<script>
(function () {
    // Picker panel body: vtx-media-picker.js overlay or fallback to form modal
    function pickerBody() {
        return document.getElementById('vtx-picker-panel-body') ||
               document.getElementById('vtx-form-modal-body');
    }

    function reloadPicker(url) {
        VtxAjax.get(url, function (ok, html) {
            var body = pickerBody();
            if (!body || !ok) return;
            body.innerHTML = html;
            body.querySelectorAll('script').forEach(function (s) {
                var n = document.createElement('script');
                n.textContent = s.textContent;
                s.parentNode.replaceChild(n, s);
            });
        });
    }

    // Item selection
    document.querySelectorAll('.vtx-picker-item').forEach(function (el) {
        el.addEventListener('click', function () {
            if (typeof window.__vtxMediaPickerCallback === 'function') {
                window.__vtxMediaPickerCallback(el.dataset.url, el.dataset.id);
            }
        });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); el.click(); }
        });
    });

    // Pagination
    document.querySelectorAll('.vtx-picker-page-link').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            reloadPicker(a.href);
        });
    });

    // Inline search
    var searchInput = document.getElementById('vtx-picker-search');
    if (searchInput) {
        var timer;
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                reloadPicker('<?php echo htmlspecialchars($baseUrl); ?>/admin/media/picker?search=' +
                    encodeURIComponent(searchInput.value));
            }, 320);
        });
    }

    // Upload complete → reload picker
    pickerBody().addEventListener('vtx:upload:done', function () {
        reloadPicker('<?php echo htmlspecialchars($baseUrl); ?>/admin/media/picker');
    });

    Vtx.load(['upload'], function () {
        document.querySelectorAll('[data-vtx-upload]').forEach(function (el) {
            if (!el._vtxUpload) el._vtxUpload = new VtxUpload({ el: el });
        });
    });
}());
</script>
