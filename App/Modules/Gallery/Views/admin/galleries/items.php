<div class="vtx-page-head" id="vtx-gallery-page" data-base-url="{{baseUrl}}">
  <div>
    <a href="{{baseUrl}}/admin/gallery" style="font-size:.875rem;color:var(--ps-text-muted);">
      ← Gallery
    </a>
    <h1 class="vtx-page-title" style="margin-top:.25rem;">
      <i class="pi pi-images me-2 text-primary"></i><?php echo htmlspecialchars($gallery['title']); ?>
    </h1>
    <p class="vtx-page-desc">Drag to reorder. Click the media icon to add images.</p>
  </div>
  <?php if (\App\CMS\Auth::can('gallery.edit')): ?>
  <button type="button" class="btn btn-primary" id="vtx-gallery-add-btn">
    <i class="pi pi-plus me-1"></i> Add Images
  </button>
  <?php endif; ?>
</div>

<!-- Hidden CSRF & gallery ID for JS -->
<input type="hidden" id="vtx-gallery-id" value="<?php echo htmlspecialchars($gallery['id']); ?>">
<input type="hidden" id="vtx-gallery-csrf" value="{{csrf_token}}">

<!-- Image Grid -->
<div class="vtx-panel">
  <?php if (empty($items)): ?>
  <div class="vtx-empty" id="vtx-gallery-empty">
    <div class="vtx-empty-ico"><i class="pi pi-image"></i></div>
    <div class="vtx-empty-title">No images yet</div>
    <div class="vtx-empty-desc">Click "Add Images" to add photos from the media library.</div>
  </div>
  <?php endif; ?>
  <div id="vtx-gallery-grid" class="vtx-gallery-items-grid"
       style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:1rem;padding:1rem;">
    <?php foreach ($items as $item): ?>
    <div class="vtx-gallery-item" data-id="<?php echo $item['id']; ?>"
         style="position:relative;border-radius:8px;overflow:hidden;background:var(--ps-bg-alt);aspect-ratio:1;cursor:grab;">
      <img src="<?php echo htmlspecialchars($item['thumbnail_url']); ?>"
           alt="<?php echo htmlspecialchars($item['alt_text'] ?? $item['original_name'] ?? ''); ?>"
           style="width:100%;height:100%;object-fit:cover;display:block;">
      <?php if (\App\CMS\Auth::can('gallery.edit')): ?>
      <button type="button" class="vtx-gallery-remove"
              data-id="<?php echo $item['id']; ?>"
              style="position:absolute;top:.375rem;right:.375rem;background:rgba(0,0,0,.65);color:#fff;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:.75rem;display:flex;align-items:center;justify-content:center;"
              title="Remove">×</button>
      <?php endif; ?>
      <div class="drag-handle"
           style="position:absolute;bottom:.375rem;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.5);color:#fff;border-radius:4px;padding:.125rem .375rem;font-size:.7rem;cursor:grab;">
        ⠿
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
