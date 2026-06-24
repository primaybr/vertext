<?php $editing = isset($page) && is_array($page) && !empty($page['id']); ?>
<form method="POST"
      action="<?php echo htmlspecialchars($action ?? ''); ?>"
      data-crud-form>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="page-title">Title <span class="text-danger">*</span></label>
    <input class="form-control" type="text" id="page-title" name="title"
           value="<?php echo htmlspecialchars($page['title'] ?? ''); ?>"
           placeholder="Page title…" required autofocus
           data-vtx-slug-source>
  </div>

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="page-slug">
      Slug
      <?php if ($editing): ?>
      <span style="font-size:.75rem;font-weight:400;color:var(--ps-text-muted);"> - changing the slug will break existing links</span>
      <?php endif; ?>
    </label>
    <div style="display:flex;align-items:center;gap:.5rem;">
      <span style="font-size:.875rem;color:var(--ps-text-muted);">/</span>
      <input class="form-control" type="text" id="page-slug" name="slug"
             value="<?php echo htmlspecialchars($page['slug'] ?? ''); ?>"
             placeholder="auto-generated-from-title"
             data-vtx-slug-target data-vtx-slug-source-id="page-title">
    </div>
  </div>

  <div class="vtx-field mb-3">
    <label class="vtx-label">Content</label>
    <div class="vtx-editor-wrap">
      <div id="page-body-editor" style="min-height:240px;max-height:360px;overflow-y:auto;"></div>
    </div>
    <textarea name="content" id="page-body-hidden"
              style="display:none;"><?php echo htmlspecialchars($page['content'] ?? ''); ?></textarea>
  </div>

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="page-excerpt">Excerpt</label>
    <textarea class="form-control" id="page-excerpt" name="excerpt"
              rows="2" placeholder="Short description shown in link previews…"><?php echo htmlspecialchars($page['excerpt'] ?? ''); ?></textarea>
  </div>

  <details class="vtx-field mb-3" style="border:1px solid var(--ps-border);border-radius:var(--ps-radius);padding:.75rem;">
    <summary style="cursor:pointer;font-weight:600;font-size:.875rem;list-style:none;display:flex;align-items:center;gap:.5rem;">
      <i class="pi pi-search"></i> SEO
      <span style="font-size:.75rem;font-weight:400;color:var(--ps-text-muted);">(optional)</span>
    </summary>
    <div style="margin-top:.875rem;">
      <div class="vtx-field mb-2">
        <label class="vtx-label" for="page-meta-title">Meta Title</label>
        <input class="form-control" type="text" id="page-meta-title" name="meta_title"
               maxlength="160" value="<?php echo htmlspecialchars($page['meta_title'] ?? ''); ?>">
      </div>
      <div class="vtx-field">
        <label class="vtx-label" for="page-meta-desc">Meta Description</label>
        <textarea class="form-control" id="page-meta-desc" name="meta_description"
                  rows="2" maxlength="320"><?php echo htmlspecialchars($page['meta_description'] ?? ''); ?></textarea>
      </div>
    </div>
  </details>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="mb-3">
    <div class="vtx-field">
      <label class="vtx-label" for="page-status">Status</label>
      <select class="form-control" id="page-status" name="status">
        <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $val => $label): ?>
        <option value="<?php echo $val; ?>"
          <?php echo ($page['status'] ?? 'draft') === $val ? 'selected' : ''; ?>>
          <?php echo $label; ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="vtx-field">
      <label class="vtx-label" for="page-order">Sort Order</label>
      <input class="form-control" type="number" id="page-order" name="sort_order" min="0"
             value="<?php echo (int) ($page['sort_order'] ?? 0); ?>">
    </div>
  </div>

  <div style="display:flex;gap:.5rem;justify-content:flex-end;border-top:1px solid var(--ps-border);padding-top:.875rem;">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="window.vtxFormModalClose && window.vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="pi pi-check me-1"></i><?php echo $editing ? 'Update Page' : 'Create Page'; ?>
    </button>
  </div>
</form>
<script>
(function () {
    Vtx.load(['slug', 'editor'], function () {
        if (window.vtxSlug) window.vtxSlug.init();
        var editorEl = document.getElementById('page-body-editor');
        var hiddenEl = document.getElementById('page-body-hidden');
        if (editorEl && hiddenEl && window.VtxEditor) {
            var ed = new VtxEditor({ container: editorEl, textarea: hiddenEl });
            if (hiddenEl.value) ed.setHTML(hiddenEl.value);
        }
    });
}());
</script>
