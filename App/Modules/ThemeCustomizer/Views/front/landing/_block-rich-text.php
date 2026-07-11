<?php $html = $block['html'] ?? ''; ?>
<?php if (trim($html) !== ''): ?>
<section class="vtx-lb-section vtx-lb-rich-text">
  <div class="container vtx-lb-rich-text-content">
    <?php echo $html; ?>
  </div>
</section>
<?php endif; ?>
