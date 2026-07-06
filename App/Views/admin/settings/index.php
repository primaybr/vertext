<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-settings me-2 text-primary"></i>Settings</h1>
    <p class="vtx-page-desc">Configure site-wide options.</p>
  </div>
</div>

<!-- Settings tabs -->
<div style="display:flex;gap:.25rem;margin-bottom:1.5rem;border-bottom:1px solid var(--ps-border);">
  <?php
  $vtxTab = $_GET['tab'] ?? 'general';
  $tabs = ['general' => 'General', 'mail' => 'Mail'];
  foreach ($tabs as $tabKey => $tabLabel): ?>
  <a href="{{baseUrl}}/admin/settings?tab=<?php echo $tabKey; ?>"
     style="padding:.5rem 1rem;font-size:.875rem;font-weight:500;text-decoration:none;border-bottom:2px solid transparent;color:var(--ps-text-secondary);<?php echo $vtxTab === $tabKey ? 'border-bottom-color:var(--ps-primary);color:var(--ps-primary);' : ''; ?>">
    <?php echo $tabLabel; ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($vtxTab === 'mail'): ?>
<!-- ── Mail Settings ──────────────────────────────────────────────────── -->
<form method="POST" action="{{baseUrl}}/admin/settings/save-mail" data-ajax-form id="mail-settings-form">
  <input type="hidden" name="csrf_token" value="{{csrf_token}}">
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head">
          <h2 class="vtx-panel-title"><i class="pi pi-envelope me-1 text-primary"></i> Mail Transport</h2>
        </div>
        <div class="vtx-panel-body">

          <div class="vtx-field mb-3">
            <label class="vtx-label" for="mail_driver">Driver</label>
            <select class="form-select" id="mail_driver" name="mail_driver" data-vtx-select onchange="vtxToggleSmtp(this.value)">
              <option value="mail" <?php echo ($settings['mail_driver'] ?? 'mail') === 'mail' ? 'selected' : ''; ?>>PHP mail() - uses server sendmail</option>
              <option value="smtp" <?php echo ($settings['mail_driver'] ?? '') === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
            </select>
          </div>

          <div id="vtx-smtp-fields" style="<?php echo ($settings['mail_driver'] ?? 'mail') === 'smtp' ? '' : 'display:none'; ?>">
            <div class="row g-3 mb-3">
              <div class="col-md-8">
                <div class="vtx-field">
                  <label class="vtx-label" for="mail_host">SMTP Host</label>
                  <input class="form-control" type="text" id="mail_host" name="mail_host"
                         value="<?php echo htmlspecialchars($settings['mail_host'] ?? ''); ?>"
                         placeholder="smtp.gmail.com">
                </div>
              </div>
              <div class="col-md-4">
                <div class="vtx-field">
                  <label class="vtx-label" for="mail_port">Port</label>
                  <input class="form-control" type="number" id="mail_port" name="mail_port"
                         value="<?php echo htmlspecialchars($settings['mail_port'] ?? '587'); ?>"
                         placeholder="587">
                </div>
              </div>
            </div>

            <div class="vtx-field mb-3">
              <label class="vtx-label" for="mail_encryption">Encryption</label>
              <select class="form-select" id="mail_encryption" name="mail_encryption" data-vtx-select>
                <option value="tls" <?php echo ($settings['mail_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (port 587)</option>
                <option value="ssl" <?php echo ($settings['mail_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL (port 465)</option>
                <option value="" <?php echo ($settings['mail_encryption'] ?? 'x') === '' ? 'selected' : ''; ?>>None</option>
              </select>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <div class="vtx-field">
                  <label class="vtx-label" for="mail_username">Username</label>
                  <input class="form-control" type="text" id="mail_username" name="mail_username"
                         value="<?php echo htmlspecialchars($settings['mail_username'] ?? ''); ?>"
                         autocomplete="off"
                         placeholder="you@example.com">
                </div>
              </div>
              <div class="col-md-6">
                <div class="vtx-field">
                  <label class="vtx-label" for="mail_password">Password / App key</label>
                  <input class="form-control" type="password" id="mail_password" name="mail_password"
                         value="<?php echo htmlspecialchars($settings['mail_password'] ?? ''); ?>"
                         autocomplete="new-password">
                </div>
              </div>
            </div>
          </div><!-- /smtp-fields -->

          <div class="row g-3">
            <div class="col-md-6">
              <div class="vtx-field">
                <label class="vtx-label" for="mail_from_address">From Address</label>
                <input class="form-control" type="email" id="mail_from_address" name="mail_from_address"
                       value="<?php echo htmlspecialchars($settings['mail_from_address'] ?? $settings['admin_email'] ?? ''); ?>"
                       placeholder="no-reply@example.com">
              </div>
            </div>
            <div class="col-md-6">
              <div class="vtx-field">
                <label class="vtx-label" for="mail_from_name">From Name</label>
                <input class="form-control" type="text" id="mail_from_name" name="mail_from_name"
                       value="<?php echo htmlspecialchars($settings['mail_from_name'] ?? $settings['site_name'] ?? ''); ?>"
                       placeholder="My Website">
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="vtx-panel mb-4">
        <div class="vtx-panel-body" style="display:flex;flex-direction:column;gap:.625rem;">
          <button type="submit" class="btn btn-primary w-100">
            <i class="pi pi-check me-1"></i> Save Mail Settings
          </button>
          <button type="button" class="btn btn-outline-secondary w-100" id="vtx-test-mail-btn">
            <i class="pi pi-envelope me-1"></i> Send Test Email
          </button>
          <div id="vtx-test-mail-result" style="font-size:.8125rem;display:none;padding:.5rem;border-radius:4px;"></div>
        </div>
      </div>
      <div class="vtx-panel">
        <div class="vtx-panel-body">
          <p style="font-size:.8125rem;color:var(--ps-text-secondary);margin:0;">
            <strong>Gmail SMTP:</strong> host <code>smtp.gmail.com</code>, port 587, TLS. Use an App Password if 2FA is enabled.
          </p>
        </div>
      </div>
    </div>
  </div>
</form>

<?php else: ?>
<form method="POST" action="{{baseUrl}}/admin/settings/save" data-ajax-form id="vtx-settings-form">
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
                <select class="form-select" id="default_language" name="default_language" data-vtx-select>
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

          <?php $maintenanceOn = ($settings['maintenance_mode'] ?? '0') === '1'; ?>
          <div id="vtx-maint-banner"
               style="<?php echo $maintenanceOn ? 'display:flex' : 'display:none'; ?>;background:color-mix(in srgb,var(--ps-warning) 12%,transparent);border:1px solid color-mix(in srgb,var(--ps-warning) 40%,transparent);border-radius:6px;padding:.5rem .75rem;margin-bottom:.75rem;font-size:.8125rem;align-items:center;gap:.5rem;">
            <i class="pi pi-warning-circle" style="color:var(--ps-warning);flex-shrink:0;"></i>
            <span><strong>Maintenance mode is ON.</strong> Visitors see the maintenance page. Admins bypass it - test in a private window.</span>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem 0;">
            <div>
              <div style="font-size:.875rem;font-weight:500;">Maintenance Mode</div>
              <div class="vtx-help" style="display:block;margin-top:1px;">
                Shows a maintenance page to visitors. Admins always bypass it automatically.
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:.625rem;">
              <span id="vtx-maint-status" style="font-size:.8125rem;color:var(--ps-text-secondary);"><?php echo $maintenanceOn ? 'On' : 'Off'; ?></span>
              <button type="button"
                      id="vtx-maint-toggle"
                      class="vtx-pill-toggle<?php echo $maintenanceOn ? ' vtx-pill-toggle--on' : ''; ?>"
                      aria-pressed="<?php echo $maintenanceOn ? 'true' : 'false'; ?>"
                      title="Toggle maintenance mode"
                      data-csrf="{{csrf_token}}">
                <span class="vtx-pill-toggle__knob"></span>
              </button>
            </div>
          </div>

          <hr style="border-color:var(--ps-border);margin:.75rem 0;">

          <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:.5rem 0;gap:1rem;">
            <div>
              <div style="font-size:.875rem;font-weight:500;">UUID Migration</div>
              <div class="vtx-help" style="display:block;margin-top:1px;">
                Convert all primary keys from integer (SERIAL) to UUID. Safe to run multiple times - tables already on UUID are skipped automatically. <strong>Back up your database first.</strong>
              </div>
            </div>
            <button type="button"
                    id="vtx-run-migration-btn"
                    class="btn btn-sm btn-outline-primary"
                    style="white-space:nowrap;flex-shrink:0;"
                    data-csrf="{{csrf_token}}">
              <i class="pi pi-database me-1"></i> Run Migration
            </button>
          </div>
          <div id="vtx-migration-result" style="display:none;margin-top:.5rem;font-size:.8125rem;border-radius:6px;padding:.5rem .75rem;"></div>

        </div>
      </div>
      <?php endif; ?>

      <!-- Cache Management -->
      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head">
          <h2 class="vtx-panel-title"><i class="pi pi-database me-1 text-primary"></i> Cache</h2>
        </div>
        <div class="vtx-panel-body">
          <!-- Full-page cache toggle (submits with the main Save Settings form) -->
          <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer;margin-bottom:.875rem;">
            <input type="checkbox" name="cache_pages_enabled" value="1" form="vtx-settings-form"
                   style="margin-top:.2rem;"
                   <?php echo ($settings['cache_pages_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
            <span style="font-size:.8125rem;">
              <strong>Full-page cache</strong><br>
              <span style="color:var(--ps-text-muted);">
                Serve public pages and posts from disk for 10 minutes. Skipped automatically for
                logged-in visitors and pages containing forms.
              </span>
            </span>
          </label>

          <?php $cs = $cacheStats ?? ['pages' => 0, 'fragments' => 0, 'other' => 0, 'bytes' => 0]; ?>
          <p style="font-size:.8125rem;color:var(--ps-text-secondary);margin-bottom:.875rem;">
            <strong><?php echo (int) $cs['pages']; ?></strong> page(s),
            <strong><?php echo (int) $cs['fragments']; ?></strong> fragment(s),
            <strong><?php echo (int) $cs['other']; ?></strong> other cached file(s)
            &middot; <?php echo number_format(($cs['bytes'] ?? 0) / 1024, 1); ?> KB total.
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

<?php endif; ?>

