<div class="vtx-form-error alert alert-danger" style="display:none"></div>

<form method="POST"
      action="<?= $baseUrl ?>/admin/videos/<?= $video ? $video['id'].'/update' : 'store' ?>"
      data-vtx-ajax>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

    <div class="mb-3">
        <label class="form-label">Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control"
               value="<?= htmlspecialchars($video['title'] ?? '') ?>"
               required data-vtx-slug-source>
    </div>

    <div class="mb-3">
        <label class="form-label">Slug</label>
        <input type="text" name="slug" class="form-control font-monospace"
               value="<?= htmlspecialchars($video['slug'] ?? '') ?>"
               data-vtx-slug-target placeholder="auto-generated">
    </div>

    <div class="row">
        <div class="col-sm-4 mb-3">
            <label class="form-label">Provider</label>
            <select name="provider" class="form-select" id="vp-provider">
                <?php foreach (['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'other' => 'Other'] as $k => $l): ?>
                    <option value="<?= $k ?>" <?= ($video['provider'] ?? 'youtube') === $k ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-8 mb-3">
            <label class="form-label">Video URL or Embed URL <span class="text-danger">*</span></label>
            <input type="url" name="embed_url" class="form-control" required
                   value="<?= htmlspecialchars($video['embed_url'] ?? '') ?>"
                   placeholder="https://www.youtube.com/watch?v=...">
            <div class="form-text" id="vp-hint">Paste any YouTube, Vimeo, or embed URL.</div>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($video['description'] ?? '') ?></textarea>
    </div>

    <div class="row">
        <div class="col-sm-6 mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="draft"     <?= ($video['status'] ?? 'draft') === 'draft'     ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= ($video['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
        </div>
        <div class="col-sm-6 mb-3">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" min="0"
                   value="<?= (int) ($video['sort_order'] ?? 0) ?>">
        </div>
    </div>

    <details class="mb-3">
        <summary class="text-muted small mb-2" style="cursor:pointer">SEO / Meta (optional)</summary>
        <div class="pt-2">
            <div class="mb-2">
                <label class="form-label small">Meta Title</label>
                <input type="text" name="meta_title" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($video['meta_title'] ?? '') ?>">
            </div>
            <div class="mb-2">
                <label class="form-label small">Meta Description</label>
                <textarea name="meta_description" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($video['meta_description'] ?? '') ?></textarea>
            </div>
        </div>
    </details>

    <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">
            <?= $video ? 'Update Video' : 'Add Video' ?>
        </button>
    </div>
</form>

<script>
Vtx.load(['slug'], function () {
    Vtx.slug.init(document.querySelector('[data-vtx-slug-source]'), document.querySelector('[data-vtx-slug-target]'));
});

document.getElementById('vp-provider')?.addEventListener('change', function () {
    const hints = {
        youtube: 'Paste any YouTube URL (watch, share, or embed).',
        vimeo:   'Paste any Vimeo URL.',
        other:   'Paste the full iframe src URL.',
    };
    document.getElementById('vp-hint').textContent = hints[this.value] || '';
});
</script>
