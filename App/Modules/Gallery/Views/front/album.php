<div class="container">
  <nav class="album-breadcrumb">
    <a href="<?php echo $baseUrl; ?>/gallery">Gallery</a>
    <span class="sep">/</span>
    <span><?php echo htmlspecialchars($gallery['title']); ?></span>
  </nav>

  <div class="album-header">
    <h1><?php echo htmlspecialchars($gallery['title']); ?></h1>
    <?php if (!empty($gallery['description'])): ?>
    <p><?php echo htmlspecialchars($gallery['description']); ?></p>
    <?php endif; ?>
  </div>

  <?php if (empty($items)): ?>
  <p class="album-empty">No photos in this album yet.</p>
  <?php else: ?>
  <div class="photo-grid" id="vtx-photo-grid">
    <?php foreach ($items as $i => $item): ?>
    <div class="photo-item"
         data-index="<?php echo $i; ?>"
         data-url="<?php echo htmlspecialchars($item['url']); ?>"
         data-caption="<?php echo htmlspecialchars($item['caption'] ?? ''); ?>">
      <img src="<?php echo htmlspecialchars($item['thumbnail_url']); ?>"
           alt="<?php echo htmlspecialchars($item['alt_text'] ?? ''); ?>"
           loading="lazy">
      <div class="photo-overlay">
        <?php if (!empty($item['caption'])): ?>
        <span class="photo-caption"><?php echo htmlspecialchars($item['caption']); ?></span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Lightbox -->
<div class="vtx-lightbox" id="vtx-lightbox" role="dialog" aria-modal="true">
  <button class="vtx-lb-close" id="vtx-lb-close" aria-label="Close">&times;</button>
  <button class="vtx-lb-arrow vtx-lb-prev" id="vtx-lb-prev" aria-label="Previous">&#8249;</button>
  <img src="" alt="" id="vtx-lb-img">
  <button class="vtx-lb-arrow vtx-lb-next" id="vtx-lb-next" aria-label="Next">&#8250;</button>
  <div class="vtx-lb-counter" id="vtx-lb-counter"></div>
  <div class="vtx-lb-caption" id="vtx-lb-caption"></div>
</div>
