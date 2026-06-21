<?php $editing = isset($tag) && is_array($tag) && !empty($tag['id']); ?>
<form method="POST"
      action="<?php echo htmlspecialchars($action ?? ''); ?>"
      data-crud-form>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="tag_name">Name <span class="text-danger">*</span></label>
    <input class="form-control" type="text" id="tag_name" name="name"
           value="<?php echo htmlspecialchars($tag['name'] ?? ''); ?>"
           placeholder="e.g. javascript" required autofocus>
  </div>

  <div style="display:flex;gap:.5rem;justify-content:flex-end;">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="window.vtxFormModalClose && window.vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm">
      <?php echo $editing ? 'Update Tag' : 'Create Tag'; ?>
    </button>
  </div>
</form>
