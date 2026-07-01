<?php $editing = isset($form) && is_array($form) && !empty($form['id']); ?>
<form method="POST"
      action="<?php echo htmlspecialchars($action ?? ''); ?>"
      data-crud-form>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="form_name">Name <span class="text-danger">*</span></label>
    <input class="form-control" type="text" id="form_name" name="name"
           value="<?php echo htmlspecialchars($form['name'] ?? ''); ?>"
           placeholder="e.g. Contact Us" required autofocus
           data-vtx-slug-source>
  </div>

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="form_slug">Slug</label>
    <input class="form-control" type="text" id="form_slug" name="slug"
           value="<?php echo htmlspecialchars($form['slug'] ?? ''); ?>"
           placeholder="auto-generated"
           data-vtx-slug-target data-vtx-slug-source-id="form_name">
    <p class="vtx-field-hint">Public URL: /forms/{slug}</p>
  </div>

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="form_desc">Description</label>
    <textarea class="form-control" id="form_desc" name="description"
              rows="2" placeholder="Optional short description..."><?php echo htmlspecialchars($form['description'] ?? ''); ?></textarea>
  </div>

  <?php if ($editing): ?>
  <div class="vtx-field mb-3">
    <label class="vtx-label" for="form_status">Status</label>
    <select class="form-select" id="form_status" name="status">
      <option value="active"   <?php echo ($form['status'] ?? '') === 'active'   ? 'selected' : ''; ?>>Active</option>
      <option value="inactive" <?php echo ($form['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
    </select>
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:.5rem;justify-content:flex-end;">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="window.vtxFormModalClose && window.vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm">
      <?php echo $editing ? 'Save Changes' : 'Create Form'; ?>
    </button>
  </div>
</form>
<script>Vtx.load(['slug'], function () { if (window.vtxSlug) window.vtxSlug.init(); });</script>
