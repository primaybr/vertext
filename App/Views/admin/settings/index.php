<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-settings me-2 text-primary"></i>Settings</h1>
    <p class="vtx-page-desc">Configure site-wide options.</p>
  </div>
</div>

<form method="POST" action="{{baseUrl}}/admin/settings/save" data-ajax-form>
  <input type="hidden" name="csrf_token" value="{{csrf_token}}">

  <div class="row g-4">

    <!-- Site Settings -->
    <div class="col-lg-8">

      <!-- General -->
      <?php if (!empty($grouped['general'])): ?>
      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head">
          <h2 class="vtx-panel-title"><i class="pi pi-globe me-1 text-primary"></i> General</h2>
        </div>
        <div class="vtx-panel-body">

          <div class="vtx-field">
            <label class="vtx-label" for="site_name">Site Name</label>
            <input class="form-control" type="text" id="site_name" name="site_name"
                   value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>"
                   placeholder="My Website">
          </div>

          <div class="vtx-field">
            <label class="vtx-label" for="site_url">Site URL</label>
            <input class="form-control" type="url" id="site_url" name="site_url"
                   value="<?php echo htmlspecialchars($settings['site_url'] ?? ''); ?>"
                   placeholder="https://example.com">
          </div>

          <div class="vtx-field">
            <label class="vtx-label" for="site_description">Site Description</label>
            <textarea class="form-control" id="site_description" name="site_description"
                      rows="2" placeholder="A short tagline for your site."><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
          </div>

          <div class="vtx-field">
            <label class="vtx-label" for="admin_email">Admin Email</label>
            <input class="form-control" type="email" id="admin_email" name="admin_email"
                   value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>"
                   placeholder="admin@example.com">
          </div>

        </div>
      </div>
      <?php endif; ?>

      <!-- Locale -->
      <?php if (!empty($grouped['locale'])): ?>
      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head">
          <h2 class="vtx-panel-title"><i class="pi pi-map me-1 text-primary"></i> Locale</h2>
        </div>
        <div class="vtx-panel-body">

          <div class="row g-3">
            <div class="col-md-6">
              <div class="vtx-field">
                <label class="vtx-label" for="default_language">Default Language</label>
                <select class="form-select" id="default_language" name="default_language">
                  <option value="en" <?php echo ($settings['default_language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                  <option value="id" <?php echo ($settings['default_language'] ?? '') === 'id' ? 'selected' : ''; ?>>Bahasa Indonesia</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="vtx-field">
                <label class="vtx-label" for="timezone">Timezone</label>
                <input class="form-control" type="text" id="timezone" name="timezone"
                       value="<?php echo htmlspecialchars($settings['timezone'] ?? 'UTC'); ?>"
                       placeholder="UTC">
                <div class="vtx-help">e.g. Asia/Jakarta, America/New_York</div>
              </div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="vtx-field">
                <label class="vtx-label" for="date_format">Date Format</label>
                <input class="form-control" type="text" id="date_format" name="date_format"
                       value="<?php echo htmlspecialchars($settings['date_format'] ?? 'Y-m-d'); ?>"
                       placeholder="Y-m-d">
                <div class="vtx-help">PHP date() format string</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="vtx-field">
                <label class="vtx-label" for="time_format">Time Format</label>
                <input class="form-control" type="text" id="time_format" name="time_format"
                       value="<?php echo htmlspecialchars($settings['time_format'] ?? 'H:i'); ?>"
                       placeholder="H:i">
              </div>
            </div>
          </div>

        </div>
      </div>
      <?php endif; ?>

    </div><!-- /col-lg-8 -->

    <!-- Sidebar: System toggles -->
    <div class="col-lg-4">
      <?php if (!empty($grouped['system'])): ?>
      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head">
          <h2 class="vtx-panel-title"><i class="pi pi-cpu me-1 text-primary"></i> System</h2>
        </div>
        <div class="vtx-panel-body">

          <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem 0;">
            <div>
              <div style="font-size:.875rem;font-weight:500;">Maintenance Mode</div>
              <div class="vtx-help" style="display:block;margin-top:1px;">
                Temporarily make the site unavailable to visitors.
              </div>
            </div>
            <label style="position:relative;display:inline-flex;align-items:center;cursor:pointer;flex-shrink:0;">
              <input type="hidden" name="maintenance_mode" value="0">
              <input type="checkbox" name="maintenance_mode" value="1"
                     <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>
                     style="width:36px;height:20px;appearance:none;border-radius:20px;
                            background:var(--ps-border);cursor:pointer;transition:background .2s;
                            outline:none;"
                     class="vtx-toggle-input">
            </label>
          </div>

        </div>
      </div>
      <?php endif; ?>

      <!-- Cache Management -->
      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head">
          <h2 class="vtx-panel-title"><i class="pi pi-database me-1 text-primary"></i> Cache</h2>
        </div>
        <div class="vtx-panel-body">
          <p style="font-size:.8125rem;color:var(--ps-text-secondary);margin-bottom:.875rem;">
            <?php $vtxCacheCount = $cacheFileCount ?? 0; ?>
            <?php if ($vtxCacheCount > 0): ?>
              <strong><?php echo $vtxCacheCount; ?></strong> cached file<?php echo $vtxCacheCount !== 1 ? 's' : ''; ?> currently stored.
            <?php else: ?>
              Cache is empty.
            <?php endif; ?>
          </p>
          <button type="button" class="btn btn-outline-warning w-100" style="font-size:.8125rem;"
                  data-confirm-form="vtx-clear-cache-form"
                  data-confirm-title="Clear Cache"
                  data-confirm-message="All cached files will be permanently deleted. This cannot be undone."
                  data-confirm-label="Clear Cache"
                  data-confirm-class="btn-warning">
            <i class="pi pi-trash me-1"></i> Clear All Cache
          </button>
        </div>
      </div>

      <!-- Save -->
      <div class="vtx-panel">
        <div class="vtx-panel-body" style="display:flex;flex-direction:column;gap:.625rem;">
          <button type="submit" class="btn btn-primary w-100">
            <i class="pi pi-check me-1"></i> Save Settings
          </button>
          <a href="{{baseUrl}}/admin/settings" class="btn btn-outline-secondary w-100">
            Discard Changes
          </a>
        </div>
      </div>
    </div>

  </div><!-- /row -->
</form>

<!-- Clear-cache form lives outside the settings form to avoid nested-form issues -->
<form id="vtx-clear-cache-form" method="POST" action="{{baseUrl}}/admin/settings/clear-cache" style="display:none;">
  <input type="hidden" name="csrf_token" value="{{csrf_token}}">
</form>

<style>
.vtx-toggle-input:checked { background: var(--ps-primary); }
</style>

