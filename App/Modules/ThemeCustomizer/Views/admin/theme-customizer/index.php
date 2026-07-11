<?php
$s = $settings ?? [];
$primaryColor = htmlspecialchars($s['primary_color'] ?? '#1E3A5F');
$fontFamily   = htmlspecialchars($s['font_family']   ?? 'system');
$cornerStyle  = $s['corner_style'] ?? 'subtle';
if (!in_array($cornerStyle, ['sharp', 'subtle', 'rounded'], true)) {
    $cornerStyle = 'subtle';
}
$logoUrl              = htmlspecialchars($s['logo_url']      ?? '');
$customCss            = htmlspecialchars($s['custom_css']    ?? '');
$landingBlocksEnabled = ($s['landing_blocks_enabled'] ?? '0') === '1';

$fonts = [
    'system'  => 'System Default',
    "Inter, 'Segoe UI', sans-serif"         => 'Inter',
    "Georgia, 'Times New Roman', serif"     => 'Georgia (Serif)',
    "'Courier New', Courier, monospace"      => 'Courier (Mono)',
    "'Helvetica Neue', Helvetica, sans-serif" => 'Helvetica',
];

$corners = [
    'sharp'   => 'Sharp',
    'subtle'  => 'Subtle',
    'rounded' => 'Rounded',
];
$previewSrc = htmlspecialchars(
    $baseUrl . '/admin/theme-customizer/preview'
    . '?primary_color=' . urlencode($s['primary_color'] ?? '#1E3A5F')
    . '&font_family=' . urlencode($fontFamily === 'system' ? '' : ($s['font_family'] ?? ''))
    . '&corner_style=' . urlencode($cornerStyle)
);

$archetypes = ['default' => 'Business Suite', 'clean' => 'Marketplace', 'field' => 'Coffee Shop', 'frame' => 'Product Showcase'];
$activeThemeSlug  = $activeTheme ?? 'default';
$activeThemeName  = $activeThemeSlug;
foreach (($themes ?? []) as $t) {
    if (($t['slug'] ?? '') === $activeThemeSlug) {
        $activeThemeName = $t['name'] ?? $activeThemeSlug;
        break;
    }
}
$archetypeLabel = $archetypes[$activeThemeSlug] ?? '';
?>
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-palette me-2 text-primary"></i>Theme Customizer</h1>
    <p class="vtx-page-desc">Adjust the public site appearance and homepage content.</p>
  </div>
</div>

<?php if (!empty($flash_success)): ?>
<div class="vtx-alert vtx-alert-success mb-3"><?php echo htmlspecialchars($flash_success); ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
<div class="vtx-alert vtx-alert-danger mb-3"><?php echo htmlspecialchars($flash_error); ?></div>
<?php endif; ?>

<div class="vtx-tc-tabs mb-3">
  <button type="button" class="vtx-tc-tab active" data-tab="appearance">
    <i class="pi pi-palette me-1"></i>Appearance
  </button>
  <button type="button" class="vtx-tc-tab" data-tab="landing-blocks">
    <i class="pi pi-layers me-1"></i>Landing Blocks
  </button>
</div>

<div id="tab-appearance">
<form method="POST" action="<?php echo $baseUrl; ?>/admin/theme-customizer/save">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

  <div class="tc-layout" style="display:grid;grid-template-columns:1fr 1.2fr;gap:1.5rem;align-items:start;">

    <div>
      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head"><h6 class="vtx-panel-title">Colors</h6></div>
        <div class="vtx-panel-body" style="padding:1.25rem;">
          <div class="mb-3">
            <label class="form-label" for="primary_color">Primary Accent Color</label>
            <div style="display:flex;align-items:center;gap:.75rem;">
              <input type="color" id="primary_color_picker" value="<?php echo $primaryColor; ?>"
                     style="width:44px;height:36px;padding:2px;border:1px solid var(--ps-border);border-radius:var(--ps-radius-sm);cursor:pointer;">
              <input type="text" id="primary_color" name="primary_color"
                     value="<?php echo $primaryColor; ?>"
                     class="form-control"
                     placeholder="#1E3A5F"
                     pattern="^#[0-9A-Fa-f]{6}$"
                     style="width:120px;font-family:var(--ps-font-mono);">
              <span id="primary_color_preview" style="width:36px;height:36px;border-radius:var(--ps-radius-sm);border:1px solid var(--ps-border);background:<?php echo $primaryColor; ?>;"></span>
            </div>
            <div class="form-text">Used for buttons, links, and accents on the public site.</div>
          </div>
        </div>
      </div>

      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head"><h6 class="vtx-panel-title">Typography</h6></div>
        <div class="vtx-panel-body" style="padding:1.25rem;">
          <div class="mb-3">
            <label class="form-label" for="font_family">Font Family</label>
            <select id="font_family" name="font_family" class="form-select" data-vtx-select>
              <?php foreach ($fonts as $val => $label): ?>
              <option value="<?php echo htmlspecialchars($val); ?>"<?php echo $fontFamily === $val ? ' selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Applied to all body text on the public site.</div>
          </div>
        </div>
      </div>

      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head"><h6 class="vtx-panel-title">Corner Style</h6></div>
        <div class="vtx-panel-body" style="padding:1.25rem;">
          <div class="tc-corner-group">
            <?php foreach ($corners as $val => $label): ?>
            <label class="tc-corner-option" data-corner="<?php echo $val; ?>">
              <input type="radio" name="corner_style" value="<?php echo $val; ?>"
                     class="tc-corner-input"<?php echo $cornerStyle === $val ? ' checked' : ''; ?>>
              <span><span class="tc-corner-swatch"></span><?php echo $label; ?></span>
            </label>
            <?php endforeach; ?>
          </div>
          <div class="form-text">Controls the roundness of buttons, cards, and menus on the public site.</div>
        </div>
      </div>

      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head"><h6 class="vtx-panel-title">Logo</h6></div>
        <div class="vtx-panel-body" style="padding:1.25rem;">
          <div class="mb-3">
            <label class="form-label" for="logo_url">Logo URL</label>
            <input type="text" id="logo_url" name="logo_url" class="form-control"
                   value="<?php echo $logoUrl; ?>"
                   placeholder="/uploads/logo.png or https://...">
            <div class="form-text">Leave blank to show the site name as text. Use Media to upload your logo first.</div>
          </div>
          <?php if ($logoUrl): ?>
          <div style="margin-top:.75rem;">
            <img src="<?php echo $logoUrl; ?>" alt="Current logo" style="max-height:60px;max-width:240px;border:1px solid var(--ps-border);border-radius:var(--ps-radius-sm);padding:.25rem;">
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head"><h6 class="vtx-panel-title">Homepage</h6></div>
        <div class="vtx-panel-body" style="padding:1.25rem;">
          <div class="vtx-field" style="display:flex;align-items:center;gap:.75rem;">
            <input class="form-check-input" type="checkbox" id="landing_blocks_enabled" name="landing_blocks_enabled"
                   value="1" <?php echo $landingBlocksEnabled ? 'checked' : ''; ?>>
            <label class="vtx-label" for="landing_blocks_enabled" style="margin:0;">Use this theme's block-based landing page</label>
          </div>
          <div class="form-text">When off, the homepage shows the default "you have a CMS" page. When on, it shows the active theme's Landing Blocks content (edit in the tab above).</div>
        </div>
      </div>

      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head"><h6 class="vtx-panel-title">Custom CSS</h6></div>
        <div class="vtx-panel-body" style="padding:1.25rem;">
          <textarea id="custom_css" name="custom_css" class="form-control"
                    rows="10"
                    placeholder="/* Add any custom CSS here */&#10;&#10;.site-header { background: #fff; }&#10;"
                    style="font-family:var(--ps-font-mono);font-size:.8125rem;"><?php echo $customCss; ?></textarea>
          <div class="form-text">Injected into <code>&lt;style&gt;</code> on every public page. Overrides theme defaults - not reflected in the live preview.</div>
        </div>
      </div>
    </div>

    <div class="vtx-panel tc-preview-panel">
      <div class="vtx-panel-head">
        <h6 class="vtx-panel-title"><i class="pi pi-eye"></i> Live Preview</h6>
        <span id="tc-preview-status" style="font-size:.75rem;color:var(--ps-text-muted);"></span>
      </div>
      <div class="tc-preview-frame-wrap">
        <iframe id="tc-preview-frame" src="<?php echo $previewSrc; ?>"
                data-preview-url="<?php echo htmlspecialchars($baseUrl . '/admin/theme-customizer/preview', ENT_QUOTES); ?>"
                title="Site preview"></iframe>
      </div>
    </div>

  </div>

  <div style="margin-top:1.5rem;display:flex;gap:.75rem;">
    <button type="submit" class="btn btn-primary">
      <i class="pi pi-save me-1"></i> Save Changes
    </button>
    <a href="<?php echo $baseUrl; ?>/" target="_blank" class="btn btn-secondary">
      <i class="pi pi-external-link me-1"></i> View Site
    </a>
  </div>
</form>
</div>

<div id="tab-landing-blocks" style="display:none;">
  <?php include __DIR__ . '/_landing-blocks-tab.php'; ?>
</div>
