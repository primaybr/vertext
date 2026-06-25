<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-layers me-2 text-primary"></i>Module Manager</h1>
    <p class="vtx-page-desc">Install, enable, or disable CMS modules. Core modules are always on.</p>
  </div>
</div>

<!-- ── System Modules ─────────────────────────────────────────────────────── -->
<div class="vtx-panel mb-4" id="system-section">
  <div class="vtx-panel-head" style="cursor:pointer;user-select:none;" id="system-section-toggle">
    <h2 class="vtx-panel-title" style="display:flex;align-items:center;gap:.5rem;">
      <i class="pi pi-cpu text-primary"></i> System
      <span class="vtx-tag info" style="font-size:.6875rem;font-weight:500;">Always On</span>
    </h2>
    <i class="pi pi-chevron-down" id="system-chevron" style="transition:transform .2s;opacity:.5;"></i>
  </div>
  <div id="system-section-body" style="display:none;">
    <div style="padding:.75rem 1.25rem;display:flex;flex-direction:column;gap:0;">
      <?php foreach ($coreModules as $mod): ?>
      <div style="display:flex;align-items:center;gap:.75rem;padding:.625rem 0;border-bottom:1px solid var(--ps-border);">
        <div style="width:32px;height:32px;border-radius:8px;background:var(--ps-bg-secondary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="pi pi-cpu" style="font-size:.875rem;color:var(--ps-text-muted);"></i>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:.875rem;font-weight:600;"><?php echo htmlspecialchars($mod['name']); ?></div>
          <?php if (!empty($mod['description'])): ?>
          <div style="font-size:.75rem;color:var(--ps-text-muted);"><?php echo htmlspecialchars($mod['description']); ?></div>
          <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem;flex-shrink:0;">
          <span class="vtx-tag info" style="font-size:.6875rem;">v<?php echo htmlspecialchars($mod['version'] ?? '1.0.0'); ?></span>
          <button type="button" class="btn btn-outline-secondary btn-sm" disabled style="font-size:.75rem;">Always On</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── Add-on Modules by Category ───────────────────────────────────────── -->
<div id="addons-section">
<?php if (empty($categories)): ?>
<div class="vtx-panel">
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-inbox"></i></div>
    <div class="vtx-empty-title">No add-on modules found</div>
    <div class="vtx-empty-desc">Place a module package folder inside <code>App/Modules/</code> to get started.</div>
  </div>
</div>
<?php else: ?>
<?php foreach ($categories as $catName => $catGroup): ?>
<div class="vtx-panel mb-4" id="category-<?php echo htmlspecialchars(strtolower($catName)); ?>">
  <div class="vtx-panel-head">
    <h2 class="vtx-panel-title"><?php echo htmlspecialchars($catName); ?></h2>
  </div>
  <div class="vtx-panel-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;">

      <?php foreach ($catGroup['installed'] ?? [] as $mod): ?>
      <?php
        $slug    = htmlspecialchars($mod['slug']);
        $name    = htmlspecialchars($mod['name']);
        $icon    = htmlspecialchars($mod['nav_icon'] ?? 'pi-layers');
        $enabled = $mod['status'] === 'enabled';
      ?>
      <div class="vtx-module-card" id="module-card-<?php echo $slug; ?>">
        <div class="vtx-module-card-head">
          <div class="vtx-module-icon">
            <i class="pi <?php echo $icon; ?>"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <div class="vtx-module-name"><?php echo $name; ?></div>
            <span class="vtx-tag <?php echo $enabled ? 'success' : 'error'; ?> module-status-badge" id="badge-<?php echo $slug; ?>" style="font-size:.6875rem;">
              <?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
            </span>
          </div>
          <span class="vtx-tag" style="font-size:.6875rem;align-self:flex-start;">v<?php echo htmlspecialchars($mod['version'] ?? '1.0.0'); ?></span>
        </div>
        <?php if (!empty($mod['description'])): ?>
        <div class="vtx-module-desc"><?php echo htmlspecialchars($mod['description']); ?></div>
        <?php endif; ?>
        <div class="vtx-module-actions">
          <form id="sync-<?php echo $slug; ?>" method="POST"
                action="{{baseUrl}}/admin/modules/<?php echo $slug; ?>/sync-views" style="display:none;">
            <input type="hidden" name="csrf_token" value="{{csrf_token}}">
          </form>
          <form id="uninstall-<?php echo $slug; ?>" method="POST"
                action="{{baseUrl}}/admin/modules/<?php echo $slug; ?>/uninstall" style="display:none;">
            <input type="hidden" name="csrf_token" value="{{csrf_token}}">
          </form>
          <button type="button"
                  class="btn btn-sm module-toggle-btn <?php echo $enabled ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                  data-slug="<?php echo $slug; ?>"
                  data-url="{{baseUrl}}/admin/modules/<?php echo $slug; ?>/toggle"
                  data-csrf="{{csrf_token}}">
            <?php echo $enabled ? 'Disable' : 'Enable'; ?>
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary module-sync-btn"
                  data-slug="<?php echo $slug; ?>"
                  data-form="sync-<?php echo $slug; ?>"
                  title="Sync Views">
            <i class="pi pi-refresh"></i>
          </button>
          <button type="button" class="btn btn-sm btn-outline-danger module-uninstall-btn"
                  data-slug="<?php echo $slug; ?>"
                  data-form="uninstall-<?php echo $slug; ?>"
                  data-name="<?php echo $name; ?>">
            Uninstall
          </button>
        </div>
      </div>
      <?php endforeach; ?>

      <?php foreach ($catGroup['available'] ?? [] as $avail): ?>
      <?php
        $slug  = htmlspecialchars($avail['slug']);
        $name  = htmlspecialchars($avail['name'] ?? $avail['slug']);
        $icon  = htmlspecialchars($avail['nav']['icon'] ?? 'pi-layers');
        $depsOk = !empty($avail['deps_ok']) || empty($avail['deps']);
      ?>
      <div class="vtx-module-card vtx-module-card--available">
        <div class="vtx-module-card-head">
          <div class="vtx-module-icon vtx-module-icon--muted">
            <i class="pi <?php echo $icon; ?>"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <div class="vtx-module-name"><?php echo $name; ?></div>
            <span class="vtx-tag" style="font-size:.6875rem;color:var(--ps-text-muted);">Not Installed</span>
          </div>
          <span class="vtx-tag" style="font-size:.6875rem;align-self:flex-start;">v<?php echo htmlspecialchars($avail['version'] ?? '1.0'); ?></span>
        </div>
        <?php if (!empty($avail['description'])): ?>
        <div class="vtx-module-desc"><?php echo htmlspecialchars($avail['description']); ?></div>
        <?php endif; ?>
        <?php if (!empty($avail['deps'])): ?>
        <div style="display:flex;flex-wrap:wrap;gap:.25rem;margin-bottom:.5rem;">
          <?php foreach ($avail['deps'] as $dep): ?>
          <span class="vtx-tag <?php echo $dep['installed'] ? 'success' : 'error'; ?>"
                style="font-size:.6875rem;"
                title="<?php echo $dep['installed'] ? 'Installed' : 'Not installed'; ?>">
            <?php echo htmlspecialchars($dep['slug']); ?>
          </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="vtx-module-actions">
          <form id="install-<?php echo $slug; ?>" method="POST"
                action="{{baseUrl}}/admin/modules/<?php echo $slug; ?>/install" style="display:none;">
            <input type="hidden" name="csrf_token" value="{{csrf_token}}">
          </form>
          <?php if ($depsOk): ?>
          <button type="button" class="btn btn-sm btn-primary module-install-btn"
                  data-slug="<?php echo $slug; ?>"
                  data-form="install-<?php echo $slug; ?>"
                  data-name="<?php echo $name; ?>">
            <i class="pi pi-download me-1"></i>Install
          </button>
          <?php else: ?>
          <?php $missingDeps = implode(', ', array_column(array_filter($avail['deps'], fn($d) => !$d['installed']), 'slug')); ?>
          <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                  title="Install required module(s) first: <?php echo htmlspecialchars($missingDeps); ?>">
            <i class="pi pi-lock me-1"></i>Install
          </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>

    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div><!-- /addons-section -->

<style>
.vtx-module-card {
  border: 1px solid var(--ps-border);
  border-radius: 8px;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: .625rem;
  background: var(--ps-bg);
}
.vtx-module-card--available {
  opacity: .85;
  border-style: dashed;
}
.vtx-module-card-head {
  display: flex;
  align-items: flex-start;
  gap: .625rem;
}
.vtx-module-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: var(--ps-primary-light, rgba(79,70,229,.12));
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.vtx-module-icon .pi {
  font-size: 1rem;
  color: var(--ps-primary);
}
.vtx-module-icon--muted {
  background: var(--ps-bg-secondary);
}
.vtx-module-icon--muted .pi {
  color: var(--ps-text-muted);
}
.vtx-module-name {
  font-size: .875rem;
  font-weight: 600;
  line-height: 1.2;
  margin-bottom: .2rem;
}
.vtx-module-desc {
  font-size: .8125rem;
  color: var(--ps-text-secondary);
  line-height: 1.4;
}
.vtx-module-actions {
  display: flex;
  gap: .375rem;
  margin-top: auto;
  padding-top: .375rem;
  flex-wrap: wrap;
}
</style>

<script>
function refreshPanels(onDone) {
  location.reload();
  if (onDone) onDone();
}

function attachInstallListeners() {
  document.querySelectorAll('.module-install-btn:not([data-wired])').forEach(function(btn) {
    btn.dataset.wired = '1';
    btn.addEventListener('click', function() {
      var name = this.dataset.name;
      var form = document.getElementById(this.dataset.form);
      var me   = this;

      window.vtxConfirmModal({
        title:        'Install Module',
        message:      'Install "' + name + '"? This will run the module\'s database migrations.',
        confirmLabel: 'Install',
        confirmClass: 'btn-primary',
        onConfirm: function() {
          me.disabled  = true;
          me.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Installing…';

          window.VtxAjax.postForm(form.action, form, function(ok, res) {
            var msg = (res && res.message) ? res.message : (ok ? 'Module installed.' : 'Installation failed.');
            var success = ok && res && res.success;
            if (success && res.setup_url) {
              window.Phuse.toast('Module installed! Opening setup wizard…', 'success');
              setTimeout(function() {
                window.location.href = (window.VTX_BASE_URL || '') + res.setup_url;
              }, 700);
            } else {
              window.Phuse.toast(msg, success ? 'success' : 'error');
              if (success) {
                refreshPanels();
              } else {
                me.disabled  = false;
                me.innerHTML = '<i class="pi pi-download me-1"></i>Install';
              }
            }
          });
        }
      });
    });
  });
}

function attachToggleListeners() {
  document.querySelectorAll('.module-toggle-btn:not([data-wired])').forEach(function(btn) {
    btn.dataset.wired = '1';
    btn.addEventListener('click', function() {
      var slug = this.dataset.slug;
      var url  = this.dataset.url;
      var csrf = this.dataset.csrf;
      var me   = this;

      me.disabled    = true;
      me.textContent = '…';

      window.VtxAjax.post(url, {csrf_token: csrf}, function(ok, res) {
        me.disabled = false;
        if (ok && res && res.success) {
          var badge   = document.getElementById('badge-' + slug);
          var enabled = res.status === 'enabled';
          badge.textContent = enabled ? 'Enabled' : 'Disabled';
          badge.className   = 'vtx-tag ' + (enabled ? 'success' : 'error') + ' module-status-badge';
          me.textContent    = enabled ? 'Disable' : 'Enable';
          me.className      = 'btn btn-sm module-toggle-btn ' + (enabled ? 'btn-outline-warning' : 'btn-outline-success');
        } else {
          me.textContent = 'Error';
          setTimeout(function() { location.reload(); }, 1200);
        }
      });
    });
  });
}

function attachUninstallListeners() {
  document.querySelectorAll('.module-uninstall-btn:not([data-wired])').forEach(function(btn) {
    btn.dataset.wired = '1';
    btn.addEventListener('click', function() {
      var name = this.dataset.name;
      var form = document.getElementById(this.dataset.form);
      var me   = this;

      window.vtxConfirmModal({
        title:        'Uninstall Module',
        message:      'Uninstall "' + name + '"? This will drop all module data and cannot be undone.',
        confirmLabel: 'Uninstall',
        confirmClass: 'btn-danger',
        onConfirm: function() {
          me.disabled  = true;
          me.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Uninstalling…';

          window.VtxAjax.postForm(form.action, form, function(ok, res) {
            var msg = (res && res.message) ? res.message : (ok ? 'Module uninstalled.' : 'Uninstall failed.');
            window.Phuse.toast(msg, (ok && res && res.success) ? 'success' : 'error');
            if (ok && res && res.success) {
              refreshPanels();
            } else {
              me.disabled  = false;
              me.innerHTML = 'Uninstall';
            }
          });
        }
      });
    });
  });
}

function attachSyncListeners() {
  document.querySelectorAll('.module-sync-btn:not([data-wired])').forEach(function(btn) {
    btn.dataset.wired = '1';
    btn.addEventListener('click', function() {
      var form = document.getElementById(this.dataset.form);
      var me   = this;

      me.disabled  = true;
      me.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

      window.VtxAjax.postForm(form.action, form, function(ok, res) {
        me.disabled  = false;
        me.innerHTML = '<i class="pi pi-refresh"></i>';
        var msg = (res && res.message) ? res.message : (ok ? 'Views synced.' : 'Sync failed.');
        window.Phuse.toast(msg, (ok && res && res.success) ? 'success' : 'error');
      });
    });
  });
}

attachInstallListeners();
attachUninstallListeners();
attachToggleListeners();
attachSyncListeners();

// System section collapse
(function() {
  var toggle  = document.getElementById('system-section-toggle');
  var body    = document.getElementById('system-section-body');
  var chevron = document.getElementById('system-chevron');
  if (!toggle || !body) return;
  toggle.addEventListener('click', function() {
    var open = body.style.display === 'none';
    body.style.display = open ? '' : 'none';
    chevron.style.transform = open ? 'rotate(180deg)' : '';
  });
}());
</script>
