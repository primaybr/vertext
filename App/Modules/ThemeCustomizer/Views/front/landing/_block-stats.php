<?php $items = $block['items'] ?? []; ?>
<section class="vtx-lb-section vtx-lb-stats">
  <div class="container vtx-lb-stats-items">
    <?php foreach ($items as $item): ?>
    <div class="vtx-lb-stat">
      <?php if (!empty($item['number'])): ?><div class="vtx-lb-stat-number"><?php echo htmlspecialchars($item['number']); ?></div><?php endif; ?>
      <?php if (!empty($item['label'])): ?><div class="vtx-lb-stat-label"><?php echo htmlspecialchars($item['label']); ?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</section>
