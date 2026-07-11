<?php
$headline   = $block['headline']    ?? '';
$text       = $block['text']        ?? '';
$buttonText = $block['button_text'] ?? '';
$buttonLink = $block['button_link'] ?? '';
?>
<section class="vtx-lb-section vtx-lb-cta-banner">
  <div class="container">
    <?php if ($headline): ?><h2 class="vtx-lb-cta-headline"><?php echo htmlspecialchars($headline); ?></h2><?php endif; ?>
    <?php if ($text): ?><p class="vtx-lb-cta-text"><?php echo htmlspecialchars($text); ?></p><?php endif; ?>
    <?php if ($buttonText && $buttonLink): ?>
    <a href="<?php echo htmlspecialchars($buttonLink); ?>" class="vtx-lb-btn vtx-lb-btn-primary"><?php echo htmlspecialchars($buttonText); ?></a>
    <?php endif; ?>
  </div>
</section>
