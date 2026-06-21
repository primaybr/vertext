<?php $editing = isset($category) && is_array($category) && !empty($category['id']); ?>
<form method="POST"
      action="<?php echo htmlspecialchars($action ?? ''); ?>"
      data-crud-form>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="cat_name">Name <span class="text-danger">*</span></label>
    <input class="form-control" type="text" id="cat_name" name="name"
           value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>"
           placeholder="e.g. Technology" required autofocus>
  </div>

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="cat_desc">Description</label>
    <textarea class="form-control" id="cat_desc" name="description"
              rows="2" placeholder="Optional short description…"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
  </div>

  <div style="display:flex;gap:.5rem;justify-content:flex-end;">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="window.vtxFormModalClose && window.vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm">
      <?php echo $editing ? 'Update Category' : 'Create Category'; ?>
    </button>
  </div>
</form>
