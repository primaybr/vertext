<?php $editing = isset($gallery) && is_array($gallery) && !empty($gallery['id']); ?>
<form method="POST"
      action="<?php echo htmlspecialchars($action ?? ''); ?>"
      data-crud-form>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
  <input type="hidden" name="cover_image_id" id="gallery-cover-id"
         value="<?php echo htmlspecialchars($gallery['cover_image_id'] ?? ''); ?>">

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="gallery-title">Title <span class="text-danger">*</span></label>
    <input class="form-control" type="text" id="gallery-title" name="title"
           value="<?php echo htmlspecialchars($gallery['title'] ?? ''); ?>"
           placeholder="Album title…" required autofocus
           data-vtx-slug-source>
  </div>

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="gallery-slug">Slug</label>
    <div style="display:flex;align-items:center;gap:.5rem;">
      <span style="font-size:.875rem;color:var(--ps-text-muted);">/gallery/</span>
      <input class="form-control" type="text" id="gallery-slug" name="slug"
             value="<?php echo htmlspecialchars($gallery['slug'] ?? ''); ?>"
             placeholder="auto-generated"
             data-vtx-slug-target data-vtx-slug-source-id="gallery-title">
    </div>
  </div>

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="gallery-desc">Description</label>
    <textarea class="form-control" id="gallery-desc" name="description"
              rows="2"><?php echo htmlspecialchars($gallery['description'] ?? ''); ?></textarea>
  </div>

  <!-- Cover image -->
  <?php $hasCover = !empty($gallery['cover_url']); ?>
  <div class="vtx-field mb-3">
    <label class="vtx-label">Cover Image</label>
    <div id="gallery-cover-preview" style="<?php echo $hasCover ? '' : 'display:none;'; ?>margin-bottom:.5rem;">
      <div style="position:relative;display:inline-block;">
        <img id="gallery-cover-img" src="<?php echo htmlspecialchars($gallery['cover_url'] ?? ''); ?>"
             alt="" style="max-width:100%;max-height:120px;border-radius:var(--ps-radius);border:1px solid var(--ps-border);">
        <button type="button" id="gallery-cover-remove"
                style="position:absolute;top:.25rem;right:.25rem;background:var(--ps-danger);color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:.6875rem;"
                title="Remove">×</button>
      </div>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm"
            data-vtx-media-picker
            data-target-id-input="gallery-cover-id"
            data-target-url-input="gallery-cover-url-hidden"
            data-target-preview="gallery-cover-img"
            data-target-preview-wrap="gallery-cover-preview">
      <i class="pi pi-image me-1"></i>
      <?php echo $hasCover ? 'Change Cover' : 'Choose Cover Image'; ?>
    </button>
    <input type="hidden" id="gallery-cover-url-hidden" value="<?php echo htmlspecialchars($gallery['cover_url'] ?? ''); ?>">
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="mb-3">
    <div class="vtx-field">
      <label class="vtx-label" for="gallery-status">Status</label>
      <select class="form-control" id="gallery-status" name="status">
        <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $val => $lbl): ?>
        <option value="<?php echo $val; ?>"
          <?php echo ($gallery['status'] ?? 'draft') === $val ? 'selected' : ''; ?>>
          <?php echo $lbl; ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div style="display:flex;gap:.5rem;justify-content:flex-end;border-top:1px solid var(--ps-border);padding-top:.875rem;">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="window.vtxFormModalClose && window.vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="pi pi-check me-1"></i><?php echo $editing ? 'Update Album' : 'Create Album'; ?>
    </button>
  </div>
</form>
<script>
(function () {
    Vtx.load(['slug', 'media-picker'], function () {
        if (window.vtxSlug) window.vtxSlug.init();
        var pickerBtn = document.querySelector('[data-vtx-media-picker]');
        if (pickerBtn && window.VtxMediaPicker) new VtxMediaPicker({ btn: pickerBtn });
    });
    var removeBtn = document.getElementById('gallery-cover-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            document.getElementById('gallery-cover-id').value = '';
            document.getElementById('gallery-cover-url-hidden').value = '';
            var wrap = document.getElementById('gallery-cover-preview');
            if (wrap) wrap.style.display = 'none';
        });
    }
}());
</script>
