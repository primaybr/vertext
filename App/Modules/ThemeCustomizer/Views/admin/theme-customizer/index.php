<?php
$s = $settings ?? [];
$primaryColor = htmlspecialchars($s['primary_color'] ?? '#2563EB');
$fontFamily   = htmlspecialchars($s['font_family']   ?? 'system');
$logoUrl      = htmlspecialchars($s['logo_url']      ?? '');
$customCss    = htmlspecialchars($s['custom_css']    ?? '');

$fonts = [
    'system'  => 'System Default',
    "Inter, 'Segoe UI', sans-serif"         => 'Inter',
    "Georgia, 'Times New Roman', serif"     => 'Georgia (Serif)',
    "'Courier New', Courier, monospace"      => 'Courier (Mono)',
    "'Helvetica Neue', Helvetica, sans-serif" => 'Helvetica',
];
?>
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-palette me-2 text-primary"></i>Theme Customizer</h1>
    <p class="vtx-page-desc">Adjust the public site appearance. Changes apply immediately after saving.</p>
  </div>
</div>

<?php if (!empty($flash_success)): ?>
<div class="vtx-alert vtx-alert-success mb-3"><?php echo htmlspecialchars($flash_success); ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
<div class="vtx-alert vtx-alert-danger mb-3"><?php echo htmlspecialchars($flash_error); ?></div>
<?php endif; ?>

<form method="POST" action="<?php echo $baseUrl; ?>/admin/theme-customizer/save">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">

    <div>
      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head"><h6 class="vtx-panel-title">Colors</h6></div>
        <div class="vtx-panel-body" style="padding:1.25rem;">
          <div class="mb-3">
            <label class="form-label" for="primary_color">Primary Accent Color</label>
            <div style="display:flex;align-items:center;gap:.75rem;">
              <input type="color" id="primary_color_picker" value="<?php echo $primaryColor; ?>"
                     style="width:44px;height:36px;padding:2px;border:1px solid var(--ps-border);border-radius:var(--ps-radius-sm);cursor:pointer;"
                     oninput="document.getElementById('primary_color').value=this.value;document.getElementById('primary_color_preview').style.background=this.value;">
              <input type="text" id="primary_color" name="primary_color"
                     value="<?php echo $primaryColor; ?>"
                     class="form-control"
                     placeholder="#2563EB"
                     pattern="^#[0-9A-Fa-f]{6}$"
                     style="width:120px;font-family:var(--ps-font-mono);"
                     oninput="syncColorPicker(this.value)">
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
    </div>

    <div>
      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head"><h6 class="vtx-panel-title">Custom CSS</h6></div>
        <div class="vtx-panel-body" style="padding:1.25rem;">
          <textarea id="custom_css" name="custom_css" class="form-control"
                    rows="16"
                    placeholder="/* Add any custom CSS here */&#10;&#10;.site-header { background: #fff; }&#10;"
                    style="font-family:var(--ps-font-mono);font-size:.8125rem;"><?php echo $customCss; ?></textarea>
          <div class="form-text">Injected into <code>&lt;style&gt;</code> on every public page. Overrides theme defaults.</div>
        </div>
      </div>

      <div class="vtx-panel" style="background:var(--ps-bg-subtle);">
        <div class="vtx-panel-body" style="padding:1.25rem;">
          <h6 style="margin:0 0 .5rem;font-weight:600;font-size:.875rem;">Live preview</h6>
          <p style="font-size:.8125rem;color:var(--ps-text-muted);margin:0 0 .75rem;">
            Your primary color applied to sample elements:
          </p>
          <div id="preview-area" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
            <button type="button" id="preview-btn" style="padding:.4rem .9rem;border:none;border-radius:var(--ps-radius-sm);color:#fff;font-size:.875rem;cursor:default;background:<?php echo $primaryColor; ?>;">
              Button
            </button>
            <a id="preview-link" href="#" onclick="return false" style="font-size:.875rem;color:<?php echo $primaryColor; ?>;">
              Link example
            </a>
            <span id="preview-badge" style="display:inline-block;padding:.2em .6em;border-radius:.25rem;font-size:.75rem;font-weight:600;color:#fff;background:<?php echo $primaryColor; ?>;">
              Badge
            </span>
          </div>
        </div>
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

<script>
function syncColorPicker(val) {
  if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
    document.getElementById('primary_color_picker').value = val;
    document.getElementById('primary_color_preview').style.background = val;
    document.getElementById('preview-btn').style.background = val;
    document.getElementById('preview-link').style.color = val;
    document.getElementById('preview-badge').style.background = val;
  }
}
document.getElementById('primary_color_picker').addEventListener('input', function() {
  document.getElementById('primary_color').value = this.value;
  syncColorPicker(this.value);
});
</script>
