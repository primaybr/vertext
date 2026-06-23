<?php $editing = isset($tag) && is_array($tag) && !empty($tag['id']); ?>
<form method="POST"
      action="<?php echo htmlspecialchars($action ?? ''); ?>"
      data-crud-form>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="tag_name">Name <span class="text-danger">*</span></label>
    <input class="form-control" type="text" id="tag_name" name="name"
           value="<?php echo htmlspecialchars($tag['name'] ?? ''); ?>"
           placeholder="e.g. javascript" required autofocus
           data-vtx-slug-source>
  </div>

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="tag_slug">Slug</label>
    <input class="form-control" type="text" id="tag_slug" name="slug"
           value="<?php echo htmlspecialchars($tag['slug'] ?? ''); ?>"
           placeholder="auto-generated"
           data-vtx-slug-target data-vtx-slug-source-id="tag_name">
  </div>

  <div style="display:flex;gap:.5rem;justify-content:flex-end;">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="window.vtxFormModalClose && window.vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm">
      <?php echo $editing ? 'Update Tag' : 'Create Tag'; ?>
    </button>
  </div>
</form>
<script>Vtx.load(['slug'], function () { if (window.vtxSlug) window.vtxSlug.init(); });</script>
