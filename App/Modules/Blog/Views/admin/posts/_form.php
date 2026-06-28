<?php
$editing = isset($post) && is_array($post) && !empty($post['id']);
$p       = $editing ? $post : [];
?>
<form method="POST"
      action="<?php echo htmlspecialchars($action ?? ''); ?>"
      data-crud-form
      id="post-editor-form">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
  <input type="hidden" name="reading_time" id="post-reading-time" value="<?php echo (int) ($p['reading_time'] ?? 0); ?>">
  <input type="hidden" name="featured_image_id"  id="post-img-id"  value="<?php echo htmlspecialchars($p['featured_image_id'] ?? ''); ?>">
  <input type="hidden" name="featured_image_url" id="post-img-url" value="<?php echo htmlspecialchars($p['featured_image_url'] ?? ''); ?>">

  <!-- Title -->
  <div class="vtx-field mb-3">
    <label class="vtx-label" for="post-title">Title <span class="text-danger">*</span></label>
    <input class="form-control" type="text" id="post-title" name="title"
           value="<?php echo htmlspecialchars($p['title'] ?? ''); ?>"
           placeholder="Post title…" required autofocus
           data-vtx-slug-source>
  </div>

  <!-- Slug -->
  <div class="vtx-field mb-3">
    <label class="vtx-label" for="post-slug">
      Slug
      <?php if ($editing): ?>
      <span style="font-size:.75rem;font-weight:400;color:var(--ps-text-muted);"> - changing the slug will break existing links</span>
      <?php endif; ?>
    </label>
    <div style="display:flex;align-items:center;gap:.5rem;">
      <span style="font-size:.875rem;color:var(--ps-text-muted);white-space:nowrap;">/<?php echo htmlspecialchars(ltrim($blogBase ?? 'blog', '/')); ?>/</span>
      <input class="form-control" type="text" id="post-slug" name="slug"
             value="<?php echo htmlspecialchars($p['slug'] ?? ''); ?>"
             placeholder="auto-generated-from-title"
             data-vtx-slug-target data-vtx-slug-source-id="post-title">
    </div>
  </div>

  <!-- Body: Quill editor -->
  <div class="vtx-field mb-3">
    <label class="vtx-label">Content <span class="text-danger">*</span></label>
    <div class="vtx-editor-wrap">
      <div id="post-body-editor" style="min-height:260px;max-height:380px;overflow-y:auto;"></div>
    </div>
    <textarea name="body" id="post-body-hidden"
              style="display:none;"><?php echo htmlspecialchars($p['body'] ?? ''); ?></textarea>
    <div style="display:flex;justify-content:space-between;margin-top:.375rem;">
      <span style="font-size:.75rem;color:var(--ps-text-muted);">Rich text editor</span>
      <span id="post-read-time-label" style="font-size:.75rem;color:var(--ps-text-muted);"></span>
    </div>
  </div>

  <!-- Excerpt -->
  <div class="vtx-field mb-3">
    <label class="vtx-label" for="post-excerpt">Excerpt</label>
    <textarea class="form-control" id="post-excerpt" name="excerpt"
              rows="2" placeholder="Brief summary shown in post listings…"><?php echo htmlspecialchars($p['excerpt'] ?? ''); ?></textarea>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="mb-3">
    <!-- Categories -->
    <div class="vtx-field">
      <label class="vtx-label">Categories</label>
      <div style="max-height:130px;overflow-y:auto;border:1px solid var(--ps-border);border-radius:var(--ps-radius);padding:.5rem .75rem;background:var(--ps-bg-input);">
        <?php if (empty($categories)): ?>
        <span style="font-size:.8125rem;color:var(--ps-text-muted);">No categories yet.</span>
        <?php else: foreach ($categories as $cat): ?>
        <label style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;padding:.2rem 0;cursor:pointer;">
          <input type="checkbox" name="category_ids[]"
                 value="<?php echo htmlspecialchars($cat['id']); ?>"
                 <?php echo in_array($cat['id'], (array)($postCatIds ?? [])) ? 'checked' : ''; ?>>
          <?php echo htmlspecialchars($cat['name']); ?>
        </label>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Tags -->
    <div class="vtx-field">
      <label class="vtx-label">Tags</label>
      <div data-vtx-tags
           data-ajax-url="<?php echo htmlspecialchars($baseUrl ?? ''); ?>/admin/blog/tags/search"
           data-value="<?php echo htmlspecialchars($postTagNames ?? ''); ?>">
        <input type="hidden" name="tag_names" id="post-tags-hidden"
               value="<?php echo htmlspecialchars($postTagNames ?? ''); ?>">
      </div>
      <div style="font-size:.75rem;color:var(--ps-text-muted);margin-top:.375rem;">
        Enter or comma to add · start typing to search
      </div>
    </div>
  </div>

  <!-- Featured image -->
  <?php if (!empty($mediaEnabled)): ?>
  <div class="vtx-field mb-3">
    <label class="vtx-label">Featured Image</label>
    <?php $hasImg = !empty($p['featured_image_url']); ?>
    <div id="post-featured-preview" style="<?php echo $hasImg ? '' : 'display:none;'; ?>margin-bottom:.5rem;">
      <div style="position:relative;display:inline-block;">
        <img id="post-featured-img-el"
             src="<?php echo htmlspecialchars($p['featured_image_url'] ?? ''); ?>"
             alt="Featured image"
             style="max-width:100%;max-height:140px;border-radius:var(--ps-radius);border:1px solid var(--ps-border);">
        <button type="button" id="post-featured-remove"
                style="position:absolute;top:.25rem;right:.25rem;background:var(--ps-danger);color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:.6875rem;display:flex;align-items:center;justify-content:center;"
                title="Remove">
          <i class="pi pi-x"></i>
        </button>
      </div>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm"
            data-vtx-media-picker
            data-target-id-input="post-img-id"
            data-target-url-input="post-img-url"
            data-target-preview="post-featured-img-el"
            data-target-preview-wrap="post-featured-preview">
      <i class="pi pi-image me-1"></i>
      <?php echo $hasImg ? 'Change Image' : 'Choose from Media Library'; ?>
    </button>
  </div>
  <?php endif; ?>

  <!-- SEO (collapsible) -->
  <details class="vtx-field mb-3" style="border:1px solid var(--ps-border);border-radius:var(--ps-radius);padding:.75rem;">
    <summary style="cursor:pointer;font-weight:600;font-size:.875rem;color:var(--ps-text-primary);list-style:none;display:flex;align-items:center;gap:.5rem;">
      <i class="pi pi-search"></i> SEO &amp; Social
      <span style="font-size:.75rem;font-weight:400;color:var(--ps-text-muted);">(optional)</span>
    </summary>
    <div style="margin-top:.875rem;">
      <div class="vtx-field mb-3">
        <label class="vtx-label" for="post-meta-title">Meta Title</label>
        <input class="form-control" type="text" id="post-meta-title" name="meta_title"
               maxlength="160"
               value="<?php echo htmlspecialchars($p['meta_title'] ?? ''); ?>"
               placeholder="Defaults to post title if blank">
        <div style="display:flex;justify-content:space-between;margin-top:.25rem;">
          <span class="vtx-help">50–60 characters recommended.</span>
          <span class="vtx-char-count" data-target="post-meta-title" data-max="60"></span>
        </div>
      </div>
      <div class="vtx-field">
        <label class="vtx-label" for="post-meta-desc">Meta Description</label>
        <textarea class="form-control" id="post-meta-desc" name="meta_description"
                  rows="2" maxlength="320"
                  placeholder="Defaults to excerpt if blank"><?php echo htmlspecialchars($p['meta_description'] ?? ''); ?></textarea>
        <div style="display:flex;justify-content:space-between;margin-top:.25rem;">
          <span class="vtx-help">120–160 characters recommended.</span>
          <span class="vtx-char-count" data-target="post-meta-desc" data-max="160"></span>
        </div>
      </div>
    </div>
  </details>

  <!-- Publish settings -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;" class="mb-3">
    <div class="vtx-field">
      <label class="vtx-label" for="post-status">Status</label>
      <select class="form-select" id="post-status" name="status" data-vtx-select>
        <?php
        $statuses = ['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled', 'archived' => 'Archived'];
        foreach ($statuses as $val => $label): ?>
        <option value="<?php echo $val; ?>"
          <?php echo ($p['status'] ?? 'draft') === $val ? 'selected' : ''; ?>>
          <?php echo $label; ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="vtx-field">
      <label class="vtx-label" for="post-pub-date">Publish Date</label>
      <input class="form-control" type="datetime-local" id="post-pub-date" name="published_at"
             value="<?php echo !empty($p['published_at'])
               ? date('Y-m-d\TH:i', strtotime($p['published_at']))
               : ''; ?>">
      <div class="vtx-help">Required when status is Scheduled.</div>
    </div>
    <div class="vtx-field">
      <label class="vtx-label" for="post-expire-date">Expire Date</label>
      <input class="form-control" type="datetime-local" id="post-expire-date" name="expire_at"
             value="<?php echo !empty($p['expire_at'])
               ? date('Y-m-d\TH:i', strtotime($p['expire_at']))
               : ''; ?>">
      <div class="vtx-help">Optional. Content goes offline after this date.</div>
    </div>
  </div>

  <div style="display:flex;gap:.5rem;justify-content:flex-end;border-top:1px solid var(--ps-border);padding-top:.875rem;">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="window.vtxFormModalClose && window.vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="pi pi-check me-1"></i><?php echo $editing ? 'Update Post' : 'Create Post'; ?>
    </button>
  </div>
</form>

<script>
(function () {
    // Load slug component
    Vtx.load(['slug'], function () {
        if (window.vtxSlug) window.vtxSlug.init();
    });

    // Featured image remove button
    var removeBtn = document.getElementById('post-featured-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            document.getElementById('post-img-id').value  = '';
            document.getElementById('post-img-url').value = '';
            var wrap = document.getElementById('post-featured-preview');
            if (wrap) wrap.style.display = 'none';
        });
    }

    // Character counters
    function initCharCounter(inputId, max) {
        var el  = document.getElementById(inputId);
        var ctr = document.querySelector('.vtx-char-count[data-target="' + inputId + '"]');
        if (!el || !ctr) return;
        function upd() {
            var n = el.value.length;
            ctr.textContent = n + ' / ' + max;
            ctr.style.color = n > max ? 'var(--ps-danger)' : 'var(--ps-text-muted)';
        }
        el.addEventListener('input', upd); upd();
    }
    initCharCounter('post-meta-title', 60);
    initCharCounter('post-meta-desc', 160);

    // Load editor, tags, and (when available) media picker together so the
    // inline image handler has VtxMediaPicker ready before it can be triggered
    var _editorComponents = ['editor', 'tags'<?php echo !empty($mediaEnabled) ? ", 'media-picker'" : ''; ?>];
    Vtx.load(_editorComponents, function () {
        var editorEl = document.getElementById('post-body-editor');
        var hiddenEl = document.getElementById('post-body-hidden');
        if (editorEl && hiddenEl && window.VtxEditor) {
            var vtxEd = new VtxEditor({
                container:   editorEl,
                textarea:    hiddenEl,
                mediaPicker: <?php echo !empty($mediaEnabled) ? 'true' : 'false'; ?>,
                onWordCount: function (words) {
                    var mins = Math.max(1, Math.round(words / 200));
                    var rt = document.getElementById('post-reading-time');
                    if (rt) rt.value = mins;
                    var lbl = document.getElementById('post-read-time-label');
                    if (lbl) lbl.textContent = mins + ' min read · ' + words + ' words';
                }
            });
            if (hiddenEl.value) vtxEd.setHTML(hiddenEl.value);
        }

        var tagsEl = document.querySelector('[data-vtx-tags]');
        if (tagsEl && window.VtxTags) new VtxTags({ el: tagsEl });

        <?php if (!empty($mediaEnabled)): ?>
        var pickerBtn = document.querySelector('[data-vtx-media-picker]');
        if (pickerBtn && window.VtxMediaPicker) new VtxMediaPicker({ btn: pickerBtn });
        <?php endif; ?>
    });
}());
</script>
