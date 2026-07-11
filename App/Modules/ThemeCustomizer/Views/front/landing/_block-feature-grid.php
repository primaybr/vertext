<?php
$headline = $block['headline'] ?? '';
$columns  = (int) ($block['columns'] ?? 3);
$items    = $block['items'] ?? [];
?>
<section class="vtx-lb-section vtx-lb-feature-grid">
  <div class="container">
    <?php if ($headline): ?><h2 class="vtx-lb-section-title"><?php echo htmlspecialchars($headline); ?></h2><?php endif; ?>
    <div class="vtx-lb-feature-grid-items" style="--vtx-lb-cols:<?php echo $columns; ?>;">
      <?php foreach ($items as $item): ?>
      <div class="vtx-lb-feature-item">
        <?php if (!empty($item['icon'])): ?><i class="pi <?php echo htmlspecialchars($item['icon']); ?> vtx-lb-feature-icon"></i><?php endif; ?>
        <?php if (!empty($item['title'])): ?><h3 class="vtx-lb-feature-title"><?php echo htmlspecialchars($item['title']); ?></h3><?php endif; ?>
        <?php if (!empty($item['text'])): ?><p class="vtx-lb-feature-text"><?php echo htmlspecialchars($item['text']); ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
