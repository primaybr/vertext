<?php
$headline = $block['headline'] ?? '';
$columns  = (int) ($block['columns'] ?? 3);
$items    = $block['items'] ?? [];
?>
<section class="vtx-lb-section vtx-lb-feature-grid">
  <div class="container">
    <?php if ($headline): ?><h2 class="vtx-lb-section-title"><?php echo htmlspecialchars($headline); ?></h2><?php endif; ?>
    <div class="vtx-lb-feature-grid-items vtx-lb-cols-<?php echo $columns; ?>">
      <?php foreach ($items as $item): ?>
      <div class="vtx-lb-feature-item">
        <?php // Badge backdrop (.vtx-lb-feature-icon) and icon glyph (.pi, which
              // paints itself via background-color:currentColor + a mask) must
              // be separate elements - putting both classes on one <i> made the
              // badge's own `background` shorthand silently override the icon's
              // mask fill color, rendering a flat color swatch with no visible
              // icon shape at all. ?>
        <?php if (!empty($item['icon'])): ?><span class="vtx-lb-feature-icon"><i class="pi <?php echo htmlspecialchars($item['icon']); ?>" aria-hidden="true"></i></span><?php endif; ?>
        <?php if (!empty($item['title'])): ?><h3 class="vtx-lb-feature-title"><?php echo htmlspecialchars($item['title']); ?></h3><?php endif; ?>
        <?php if (!empty($item['text'])): ?><p class="vtx-lb-feature-text"><?php echo htmlspecialchars($item['text']); ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
