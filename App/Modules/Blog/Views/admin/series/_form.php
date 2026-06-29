<form id="series-form" method="POST" action="<?php echo $action; ?>" data-vtx-ajax-form>
  <input type="hidden" name="csrf_token" value="{{csrf_token}}">

  <div class="vtx-modal-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
      <div>
        <label class="form-label">Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control form-control-sm" id="series-title"
               value="<?php echo htmlspecialchars($series['title'] ?? ''); ?>" required>
      </div>
      <div>
        <label class="form-label">Slug</label>
        <input type="text" name="slug" class="form-control form-control-sm" id="series-slug"
               value="<?php echo htmlspecialchars($series['slug'] ?? ''); ?>" placeholder="auto-generated">
        <div class="form-hint">Used in front-end series URL (optional).</div>
      </div>
    </div>
    <div style="margin-bottom:1.5rem;">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control form-control-sm" rows="2"
                placeholder="Optional series description"><?php echo htmlspecialchars($series['description'] ?? ''); ?></textarea>
    </div>

    <div>
      <label class="form-label" style="margin-bottom:.625rem;">Posts in this Series</label>
      <p class="form-hint" style="margin-bottom:.75rem;">Check posts to include. Set the sort order (0 = first) to control prev/next navigation.</p>

      <div style="max-height:320px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:6px;">
        <table class="vtx-table" style="margin:0;">
          <thead>
            <tr>
              <th style="width:36px;"></th>
              <th>Post</th>
              <th style="width:80px;">Status</th>
              <th style="width:90px;">Order</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($posts as $i => $p): ?>
            <?php $checked = in_array($p['id'], $seriesPostIds ?? [], true); ?>
            <?php $order   = ($sortMap ?? [])[$p['id']] ?? $i; ?>
            <tr>
              <td style="text-align:center;">
                <input type="checkbox" name="post_ids[]" value="<?php echo $p['id']; ?>"
                       id="sp-<?php echo $p['id']; ?>"
                       class="series-post-check"
                       <?php echo $checked ? 'checked' : ''; ?>>
              </td>
              <td>
                <label for="sp-<?php echo $p['id']; ?>" style="cursor:pointer;font-size:.875rem;font-weight:500;">
                  <?php echo htmlspecialchars($p['title']); ?>
                </label>
              </td>
              <td>
                <span class="vtx-tag <?php echo $p['status'] === 'published' ? 'success' : ''; ?>" style="font-size:.7rem;">
                  <?php echo htmlspecialchars($p['status']); ?>
                </span>
              </td>
              <td>
                <input type="number" name="sort_orders[]" value="<?php echo (int)$order; ?>"
                       class="form-control form-control-sm" style="width:70px;"
                       min="0" max="999" <?php echo $checked ? '' : 'disabled'; ?>>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="vtx-modal-footer">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm">
      <?php echo $series ? 'Update Series' : 'Create Series'; ?>
    </button>
  </div>
</form>

<script>
(function() {
  // Auto-slug
  var titleEl = document.getElementById('series-title');
  var slugEl  = document.getElementById('series-slug');
  if (titleEl && slugEl && !slugEl.value) {
    titleEl.addEventListener('input', function() {
      slugEl.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    });
  }

  // Enable/disable order input based on checkbox
  document.querySelectorAll('.series-post-check').forEach(function(cb) {
    var row  = cb.closest('tr');
    var num  = row ? row.querySelector('input[type="number"]') : null;
    cb.addEventListener('change', function() {
      if (num) num.disabled = !this.checked;
    });
  });
}());
</script>
