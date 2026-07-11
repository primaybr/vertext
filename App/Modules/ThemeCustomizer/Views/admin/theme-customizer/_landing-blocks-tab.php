<?php
$blocksJson = htmlspecialchars(json_encode($blocks ?? []), ENT_QUOTES);
?>
<div class="vtx-panel mb-3">
  <div class="vtx-panel-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;padding:1rem 1.25rem;">
    <div>
      <strong>Editing:</strong> <?php echo htmlspecialchars($activeThemeName); ?> theme<?php if ($archetypeLabel): ?> <span class="vtx-tag info"><?php echo htmlspecialchars($archetypeLabel); ?></span><?php endif; ?>
      <div class="form-text" style="margin-top:.25rem;">Blocks are per-theme. Switch the active theme (Admin &rarr; Themes) to edit a different theme's landing content.</div>
    </div>
    <button type="button" class="btn btn-primary" id="lb-save-btn">
      <i class="pi pi-save me-1"></i>Save Blocks
    </button>
  </div>
</div>

<div id="vtx-lb-message" class="vtx-alert mb-3" style="display:none;"></div>

<div class="tc-layout" style="display:grid;grid-template-columns:1fr 1.2fr;gap:1.5rem;align-items:start;">

  <div>
    <div class="vtx-panel mb-3">
      <div class="vtx-panel-head"><span class="vtx-panel-title">Add a block</span></div>
      <div class="vtx-panel-body" style="display:flex;flex-wrap:wrap;gap:.5rem;padding:1rem 1.25rem;">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-add-block="hero"><i class="pi pi-image me-1"></i>Hero</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-add-block="feature-grid"><i class="pi pi-grid me-1"></i>Feature Grid</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-add-block="testimonials"><i class="pi pi-message me-1"></i>Testimonials</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-add-block="gallery"><i class="pi pi-images me-1"></i>Gallery</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-add-block="cta-banner"><i class="pi pi-flag me-1"></i>CTA Banner</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-add-block="rich-text"><i class="pi pi-edit me-1"></i>Rich Text</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-add-block="stats"><i class="pi pi-chart-bar me-1"></i>Stats</button>
      </div>
    </div>

    <div id="vtx-lb-builder"
         data-base-url="<?php echo htmlspecialchars($baseUrl); ?>"
         data-theme-slug="<?php echo htmlspecialchars($activeThemeSlug); ?>"
         data-csrf="<?php echo htmlspecialchars($csrf_token ?? ''); ?>"
         data-blocks="<?php echo $blocksJson; ?>">
      <div id="vtx-lb-canvas"></div>
      <div id="vtx-lb-canvas-empty" class="vtx-empty" style="display:none;">
        <div class="vtx-empty-ico"><i class="pi pi-inbox"></i></div>
        <div class="vtx-empty-title">No blocks yet</div>
        <div class="vtx-empty-desc">Add a block above to start building this theme's landing page.</div>
      </div>
    </div>
  </div>

  <div class="vtx-panel tc-preview-panel">
    <div class="vtx-panel-head">
      <h6 class="vtx-panel-title"><i class="pi pi-eye"></i> Live Preview</h6>
      <span id="tc-lb-preview-status" style="font-size:.75rem;color:var(--ps-text-muted);"></span>
    </div>
    <div class="tc-preview-frame-wrap">
      <iframe id="tc-lb-preview-frame" src="<?php echo $previewSrc . '&view=blocks'; ?>"
              data-preview-url="<?php echo htmlspecialchars($baseUrl . '/admin/theme-customizer/preview?view=blocks', ENT_QUOTES); ?>"
              data-stage-url="<?php echo htmlspecialchars($baseUrl . '/admin/theme-customizer/landing-blocks/' . $activeThemeSlug . '/preview-stage', ENT_QUOTES); ?>"
              title="Site preview"></iframe>
    </div>
  </div>

</div>
