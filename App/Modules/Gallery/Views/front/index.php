<div class="container gallery-page">
  <div class="gallery-page-header">
    <h1>Gallery</h1>
  </div>

  <?php if (empty($galleries)): ?>
  <div class="gallery-empty">
    <p class="empty-icon"><i class="pi pi-images"></i></p>
    <p>No albums published yet.</p>
  </div>
  <?php else: ?>
  <div class="gallery-grid">
    <?php foreach ($galleries as $g): ?>
    <div class="gallery-card">
      <a href="<?php echo $baseUrl; ?>/gallery/<?php echo htmlspecialchars($g['slug']); ?>">
        <div class="gallery-cover">
          <?php if ($g['cover_url']): ?>
          <img src="<?php echo htmlspecialchars($g['cover_url']); ?>"
               alt="<?php echo htmlspecialchars($g['title']); ?>" loading="lazy">
          <?php else: ?>
          <div class="gallery-cover-placeholder"><i class="pi pi-image"></i></div>
          <?php endif; ?>
        </div>
        <div class="gallery-info">
          <div class="gallery-title"><?php echo htmlspecialchars($g['title']); ?></div>
          <?php if (!empty($g['description'])): ?>
          <div class="gallery-desc"><?php echo htmlspecialchars($g['description']); ?></div>
          <?php endif; ?>
          <div class="gallery-count"><?php echo (int) $g['item_count']; ?> photo<?php echo $g['item_count'] !== 1 ? 's' : ''; ?></div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
