<div class="vtx-lb-page">
<?php foreach (($blocks ?? []) as $block): ?>
<?php
    $partial = match ($block['type'] ?? '') {
        'hero'         => '_block-hero.php',
        'feature-grid' => '_block-feature-grid.php',
        'testimonials' => '_block-testimonials.php',
        'gallery'      => '_block-gallery.php',
        'cta-banner'   => '_block-cta-banner.php',
        'rich-text'    => '_block-rich-text.php',
        'stats'        => '_block-stats.php',
        default        => null,
    };
    if ($partial) {
        include __DIR__ . '/' . $partial;
    }
?>
<?php endforeach; ?>
</div>
