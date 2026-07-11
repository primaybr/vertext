<?php
$headline = $block['headline'] ?? '';
$items    = $block['items'] ?? [];
?>
<section class="vtx-lb-section vtx-lb-gallery">
  <div class="container">
    <?php if ($headline): ?><h2 class="vtx-lb-section-title"><?php echo htmlspecialchars($headline); ?></h2><?php endif; ?>
    <div class="vtx-lb-gallery-items">
      <?php foreach ($items as $item): ?>
      <?php if (!empty($item['image'])): ?>
      <figure class="vtx-lb-gallery-item">
        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['alt'] ?? ''); ?>" loading="lazy">
      </figure>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
