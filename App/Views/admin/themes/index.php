<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-sliders me-2 text-primary"></i>Themes</h1>
    <p class="vtx-page-desc">Choose the active front-end theme for your site.</p>
  </div>
</div>

<div class="vtx-panel">
  <div class="vtx-panel-head">
    <h2 class="vtx-panel-title"><i class="pi pi-palette me-1 text-primary"></i> Front-end Themes</h2>
  </div>
  <div class="vtx-panel-body">
    <?php if (empty($themes)): ?>
    <p class="text-muted">No themes found in <code>App/Themes/</code>.</p>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;">
      <?php foreach ($themes as $theme): ?>
      <div class="vtx-panel" style="border:2px solid <?php echo $theme['active'] ? 'var(--ps-primary)' : 'var(--ps-border)'; ?>;padding:1.25rem;position:relative;">
        <?php if ($theme['active']): ?>
        <span class="vtx-tag success" style="position:absolute;top:.75rem;right:.75rem;">Active</span>
        <?php endif; ?>
        <div style="font-weight:600;margin-bottom:.25rem;"><?php echo htmlspecialchars($theme['name'] ?? $theme['slug']); ?></div>
        <?php if (!empty($theme['description'])): ?>
        <div style="font-size:.8125rem;color:var(--ps-text-muted);margin-bottom:.5rem;"><?php echo htmlspecialchars($theme['description']); ?></div>
        <?php endif; ?>
        <div style="font-size:.75rem;color:var(--ps-text-muted);margin-bottom:1rem;">
          v<?php echo htmlspecialchars($theme['version'] ?? '1.0'); ?>
          <?php if (!empty($theme['author'])): ?> &middot; <?php echo htmlspecialchars($theme['author']); ?><?php endif; ?>
        </div>
        <?php if (!$theme['active']): ?>
        <form class="theme-activate-form" method="POST" action="{{baseUrl}}/admin/themes/set-theme">
          <input type="hidden" name="csrf_token" value="{{csrf_token}}">
          <input type="hidden" name="slug" value="<?php echo htmlspecialchars($theme['slug']); ?>">
          <button type="submit" class="btn btn-sm btn-outline-primary w-100">
            <i class="pi pi-check-circle me-1"></i>Activate
          </button>
        </form>
        <?php else: ?>
        <button type="button" class="btn btn-sm btn-primary w-100" disabled>
          <i class="pi pi-check me-1"></i>Current Theme
        </button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
