<?php
$headline = $block['headline'] ?? '';
$items    = $block['items'] ?? [];
?>
<section class="vtx-lb-section vtx-lb-testimonials">
  <div class="container">
    <?php if ($headline): ?><h2 class="vtx-lb-section-title"><?php echo htmlspecialchars($headline); ?></h2><?php endif; ?>
    <div class="vtx-lb-testimonials-items">
      <?php foreach ($items as $item): ?>
      <div class="vtx-lb-testimonial-card">
        <?php if (!empty($item['avatar'])): ?>
        <img class="vtx-lb-testimonial-avatar" src="<?php echo htmlspecialchars($item['avatar']); ?>" alt="<?php echo htmlspecialchars($item['author'] ?? ''); ?>">
        <?php endif; ?>
        <?php if (!empty($item['quote'])): ?><p class="vtx-lb-testimonial-quote">&ldquo;<?php echo htmlspecialchars($item['quote']); ?>&rdquo;</p><?php endif; ?>
        <?php if (!empty($item['author'])): ?><div class="vtx-lb-testimonial-author"><?php echo htmlspecialchars($item['author']); ?></div><?php endif; ?>
        <?php if (!empty($item['role'])): ?><div class="vtx-lb-testimonial-role"><?php echo htmlspecialchars($item['role']); ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
