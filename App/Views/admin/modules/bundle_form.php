<?php
$editing   = is_array($bundle) && !empty($bundle['slug']);
$bundleMods = [];
if ($editing) {
    foreach ($bundle['modules'] as $bm) {
        $bundleMods[$bm['slug']] = !empty($bm['required']);
    }
}

$icons = [
    'pi-layers', 'pi-globe', 'pi-briefcase', 'pi-images', 'pi-mail', 'pi-calendar',
    'pi-star', 'pi-shopping-cart', 'pi-users', 'pi-settings', 'pi-chart-bar',
    'pi-map-pin', 'pi-clipboard', 'pi-code', 'pi-cpu', 'pi-grid', 'pi-inbox',
    'pi-search', 'pi-shield', 'pi-sparkle', 'pi-tag', 'pi-video', 'pi-link',
];
$categories = ['Business', 'Community', 'Complete', 'Content', 'Custom', 'E-Commerce', 'Education', 'Marketing', 'Media', 'Publishing'];
?>

<div class="vtx-page-head">
  <div>
    <a href="<?php echo $baseUrl; ?>/admin/modules" class="vtx-breadcrumb">
      <i class="pi pi-layers me-1"></i> Module Manager
    </a>
    <h1 class="vtx-page-title" style="margin-top:.25rem;">
      <?php echo $editing ? 'Edit Bundle' : 'Create Bundle'; ?>
    </h1>
  </div>
  <div style="display:flex;gap:.5rem;">
    <a href="<?php echo $baseUrl; ?>/admin/modules" class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-arrow-left me-1"></i> Back
    </a>
    <button type="submit" form="bundle-form" class="btn btn-primary btn-sm">
      <i class="pi pi-save me-1"></i> <?php echo $editing ? 'Save Changes' : 'Create Bundle'; ?>
    </button>
  </div>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="vtx-alert vtx-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> mb-3">
  <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<form id="bundle-form" method="POST" action="<?php echo htmlspecialchars($action); ?>" data-editing="<?php echo $editing ? '1' : '0'; ?>">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

  <div style="display:grid;grid-template-columns:1fr 280px;gap:1.25rem;align-items:start;">

    <!-- Main -->
    <div>
      <div class="vtx-panel mb-3">
        <div class="vtx-panel-header">Bundle Details</div>
        <div class="vtx-panel-body">
          <div class="vtx-field mb-3">
            <label class="vtx-label" for="b-name">Name <span class="text-danger">*</span></label>
            <input class="form-control" type="text" id="b-name" name="name" required autofocus
                   value="<?php echo htmlspecialchars($bundle['name'] ?? ''); ?>"
                   placeholder="My Custom Bundle">
          </div>
          <div class="vtx-field mb-3">
            <label class="vtx-label" for="b-slug">Slug</label>
            <input class="form-control" type="text" id="b-slug" name="slug"
                   value="<?php echo htmlspecialchars($bundle['slug'] ?? ''); ?>"
                   placeholder="auto-generated"
                   <?php echo $editing ? 'readonly' : ''; ?>>
            <?php if ($editing): ?>
            <p class="vtx-field-hint">Slug cannot be changed after creation.</p>
            <?php endif; ?>
          </div>
          <div class="vtx-field">
            <label class="vtx-label" for="b-desc">Description</label>
            <textarea class="form-control" id="b-desc" name="description" rows="3"
                      placeholder="What does this bundle include?"><?php echo htmlspecialchars($bundle['description'] ?? ''); ?></textarea>
          </div>
        </div>
      </div>

      <!-- Module selector -->
      <div class="vtx-panel">
        <div class="vtx-panel-header">Modules</div>
        <div class="vtx-panel-body">
          <?php if (empty($allModules)): ?>
          <p class="text-muted" style="font-size:.875rem;">No modules available. Place module packages in <code>App/Modules/</code> and install them first.</p>
          <?php else: ?>
          <p style="font-size:.8125rem;color:var(--ps-text-secondary);margin-bottom:.75rem;">
            Check modules to include. Toggle <strong>Required</strong> for modules that must install with this bundle.
          </p>
          <div style="display:flex;flex-direction:column;gap:.375rem;">
            <?php foreach ($allModules as $mod): ?>
            <?php
              $mSlug    = $mod['slug'];
              $mName    = $mod['name'];
              $checked  = isset($bundleMods[$mSlug]);
              $isReq    = $bundleMods[$mSlug] ?? false;
            ?>
            <div class="bf-mod-row" id="bf-row-<?php echo htmlspecialchars($mSlug); ?>">
              <label class="bf-mod-label">
                <input type="checkbox" name="bundle_modules[]" value="<?php echo htmlspecialchars($mSlug); ?>"
                       class="bf-mod-cb" data-slug="<?php echo htmlspecialchars($mSlug); ?>"
                       <?php echo $checked ? 'checked' : ''; ?>>
                <span class="bf-mod-name"><?php echo htmlspecialchars($mName); ?></span>
                <span class="bf-mod-slug text-muted"><?php echo htmlspecialchars($mSlug); ?></span>
              </label>
              <label class="bf-req-label <?php echo $checked ? '' : 'bf-req-hidden'; ?>" id="bf-req-<?php echo htmlspecialchars($mSlug); ?>">
                <input type="checkbox" name="required[]" value="<?php echo htmlspecialchars($mSlug); ?>"
                       <?php echo $isReq ? 'checked' : ''; ?>>
                Required
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div>
      <div class="vtx-panel mb-3">
        <div class="vtx-panel-header">Appearance</div>
        <div class="vtx-panel-body">
          <div class="vtx-field mb-3">
            <label class="vtx-label">Icon</label>
            <div style="display:grid;grid-template-columns:auto 1fr;gap:.5rem;align-items:center;">
              <div id="b-icon-preview" style="width:38px;height:38px;background:var(--ps-primary-light,rgba(79,70,229,.12));border-radius:8px;display:flex;align-items:center;justify-content:center;">
                <i class="pi <?php echo htmlspecialchars($bundle['icon'] ?? 'pi-layers'); ?>" style="color:var(--ps-primary);"></i>
              </div>
              <select class="form-select" name="icon" id="b-icon-select">
                <?php foreach ($icons as $ic): ?>
                <option value="<?php echo htmlspecialchars($ic); ?>" <?php echo ($bundle['icon'] ?? 'pi-layers') === $ic ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($ic); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="vtx-field">
            <label class="vtx-label" for="b-cat">Category</label>
            <select class="form-select" name="category" id="b-cat">
              <?php foreach ($categories as $cat): ?>
              <option value="<?php echo htmlspecialchars($cat); ?>"
                      <?php echo ($bundle['category'] ?? 'Custom') === $cat ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Live preview -->
      <div class="vtx-panel">
        <div class="vtx-panel-header">Preview</div>
        <div class="vtx-panel-body">
          <div style="border:1px solid var(--ps-border);border-radius:8px;padding:.875rem;">
            <div style="display:flex;align-items:flex-start;gap:.625rem;margin-bottom:.5rem;">
              <div id="prev-icon" style="width:38px;height:38px;background:var(--ps-primary-light,rgba(79,70,229,.12));border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="pi <?php echo htmlspecialchars($bundle['icon'] ?? 'pi-layers'); ?>" style="color:var(--ps-primary);font-size:1.0625rem;" id="prev-icon-i"></i>
              </div>
              <div>
                <div id="prev-name" style="font-size:.875rem;font-weight:700;"><?php echo htmlspecialchars($bundle['name'] ?? 'Bundle Name'); ?></div>
                <span class="vtx-tag" style="font-size:.6rem;" id="prev-count">0 modules</span>
              </div>
            </div>
            <div id="prev-chips" style="display:flex;flex-wrap:wrap;gap:.25rem;margin-top:.5rem;"></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</form>
