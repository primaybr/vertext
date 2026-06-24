<?php
// Edit form partial - loads in CRUD modal to edit alt_text and caption
?>
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;">
  <img src="<?php echo htmlspecialchars($file['url'] ?? ''); ?>"
       alt="<?php echo htmlspecialchars($file['alt_text'] ?? ''); ?>"
       style="width:120px;height:80px;object-fit:cover;border-radius:6px;border:1px solid var(--ps-border);flex-shrink:0;">
  <div style="flex:1;min-width:0;">
    <div style="font-size:.8125rem;font-weight:600;color:var(--ps-text-primary);
                overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
      <?php echo htmlspecialchars($file['original_name'] ?? ''); ?>
    </div>
    <?php if (!empty($file['width']) && !empty($file['height'])): ?>
    <div style="font-size:.75rem;color:var(--ps-text-muted);margin-top:.125rem;">
      <?php echo $file['width']; ?> × <?php echo $file['height']; ?> px
    </div>
    <?php endif; ?>
  </div>
</div>

<form method="POST"
      action="<?php echo htmlspecialchars($action ?? ''); ?>"
      data-crud-form>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="alt_text">Alt Text</label>
    <input class="form-control" type="text" id="alt_text" name="alt_text"
           value="<?php echo htmlspecialchars($file['alt_text'] ?? ''); ?>"
           placeholder="Describe this image for accessibility…">
    <div class="vtx-help">Used by screen readers and displayed when the image fails to load.</div>
  </div>

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="caption">Caption</label>
    <textarea class="form-control" id="caption" name="caption" rows="2"
              placeholder="Optional caption…"><?php echo htmlspecialchars($file['caption'] ?? ''); ?></textarea>
  </div>

  <div style="display:flex;gap:.5rem;justify-content:flex-end;padding-top:.25rem;">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="window.vtxFormModalClose && window.vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
  </div>
</form>
