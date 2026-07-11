<?php
$headline    = $block['headline']    ?? '';
$subheadline = $block['subheadline'] ?? '';
$ctaText     = $block['cta_text']    ?? '';
$ctaLink     = $block['cta_link']    ?? '';
$image       = $block['image']       ?? '';
?>
<section class="vtx-lb-hero<?php echo $image ? ' vtx-lb-hero--has-image' : ''; ?>"<?php if ($image): ?> style="background-image:url('<?php echo htmlspecialchars($image); ?>');"<?php endif; ?>>
  <div class="container">
    <?php if ($headline): ?><h1 class="vtx-lb-hero-headline"><?php echo htmlspecialchars($headline); ?></h1><?php endif; ?>
    <?php if ($subheadline): ?><p class="vtx-lb-hero-subheadline"><?php echo htmlspecialchars($subheadline); ?></p><?php endif; ?>
    <?php if ($ctaText && $ctaLink): ?>
    <a href="<?php echo htmlspecialchars($ctaLink); ?>" class="vtx-lb-btn vtx-lb-btn-primary"><?php echo htmlspecialchars($ctaText); ?></a>
    <?php endif; ?>
  </div>
</section>
