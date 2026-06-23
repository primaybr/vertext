<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-layers me-2 text-primary"></i>Module Manager</h1>
    <p class="vtx-page-desc">Install, enable, or disable CMS modules. Core modules are always on.</p>
  </div>
</div>

<!-- ── Installed Modules ─────────────────────────────────────────────────── -->
<div class="vtx-panel mb-4" id="installed-panel">
  <div class="vtx-panel-head">
    <h2 class="vtx-panel-title">Installed Modules</h2>
  </div>

  <?php if (empty($modules)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-layers"></i></div>
    <div class="vtx-empty-title">No modules registered</div>
    <div class="vtx-empty-desc">Install a module from the section below to get started.</div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table">
      <thead>
        <tr>
          <th>Module</th>
          <th>Version</th>
          <th>Type</th>
          <th>Status</th>
          <th style="width:180px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($modules as $mod): ?>
        <tr id="module-row-<?php echo htmlspecialchars($mod['slug']); ?>">
          <td>
            <div class="cell-primary"><?php echo htmlspecialchars($mod['name']); ?></div>
            <?php if (!empty($mod['description'])): ?>
            <div class="cell-muted" style="font-size:.8125rem;">
              <?php echo htmlspecialchars($mod['description']); ?>
            </div>
            <?php endif; ?>
          </td>
          <td class="cell-muted"><?php echo htmlspecialchars($mod['version'] ?? '1.0.0'); ?></td>
          <td>
            <?php if (!empty($mod['is_core'])): ?>
            <span class="vtx-tag info">Core</span>
            <?php else: ?>
            <span class="vtx-tag">Add-on</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="vtx-tag <?php echo $mod['status'] === 'enabled' ? 'success' : 'error'; ?> module-status-badge"
                  id="badge-<?php echo htmlspecialchars($mod['slug']); ?>">
              <?php echo ucfirst($mod['status']); ?>
            </span>
          </td>
          <td>
            <?php if (!empty($mod['is_core'])): ?>
              <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Core modules cannot be disabled">
                Always On
              </button>
            <?php else: ?>
              <form id="sync-<?php echo htmlspecialchars($mod['slug']); ?>" method="POST"
                    action="{{baseUrl}}/admin/modules/<?php echo htmlspecialchars($mod['slug']); ?>/sync-views"
                    style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <form id="uninstall-<?php echo htmlspecialchars($mod['slug']); ?>" method="POST"
                    action="{{baseUrl}}/admin/modules/<?php echo htmlspecialchars($mod['slug']); ?>/uninstall"
                    style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <div class="btn-group btn-group-sm" role="group">
                <button type="button"
                        class="btn module-toggle-btn <?php echo $mod['status'] === 'enabled' ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                        data-slug="<?php echo htmlspecialchars($mod['slug']); ?>"
                        data-url="{{baseUrl}}/admin/modules/<?php echo htmlspecialchars($mod['slug']); ?>/toggle"
                        data-csrf="{{csrf_token}}">
                  <?php echo $mod['status'] === 'enabled' ? 'Disable' : 'Enable'; ?>
                </button>
                <button type="button" class="btn btn-outline-secondary module-sync-btn"
                        data-slug="<?php echo htmlspecialchars($mod['slug']); ?>"
                        data-form="sync-<?php echo htmlspecialchars($mod['slug']); ?>"
                        title="Redeploy module views from source">
                  <i class="pi pi-refresh"></i>
                </button>
                <button type="button" class="btn btn-outline-danger module-uninstall-btn"
                        data-slug="<?php echo htmlspecialchars($mod['slug']); ?>"
                        data-form="uninstall-<?php echo htmlspecialchars($mod['slug']); ?>"
                        data-name="<?php echo htmlspecialchars($mod['name']); ?>">
                  Uninstall
                </button>
              </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ── Available Modules (not yet installed) ────────────────────────────── -->
<div class="vtx-panel" id="available-panel">
  <div class="vtx-panel-head">
    <h2 class="vtx-panel-title">Available to Install</h2>
    <span class="cell-muted" style="font-size:.875rem;">
      Modules found in <code>App/Modules/</code> but not yet installed.
    </span>
  </div>

  <?php if (empty($available)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-inbox"></i></div>
    <div class="vtx-empty-title">No additional modules found</div>
    <div class="vtx-empty-desc">
      Place a module package folder inside <code>App/Modules/</code> and it will appear here.
    </div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table">
      <thead>
        <tr>
          <th>Module</th>
          <th>Version</th>
          <th>Author</th>
          <th style="width:120px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($available as $avail): ?>
        <tr>
          <td>
            <div class="cell-primary"><?php echo htmlspecialchars($avail['name'] ?? $avail['slug']); ?></div>
            <?php if (!empty($avail['description'])): ?>
            <div class="cell-muted" style="font-size:.8125rem;">
              <?php echo htmlspecialchars($avail['description']); ?>
            </div>
            <?php endif; ?>
          </td>
          <td class="cell-muted"><?php echo htmlspecialchars($avail['version'] ?? '—'); ?></td>
          <td class="cell-muted"><?php echo htmlspecialchars($avail['author'] ?? '—'); ?></td>
          <td>
            <form id="install-<?php echo htmlspecialchars($avail['slug']); ?>" method="POST"
                  action="{{baseUrl}}/admin/modules/<?php echo htmlspecialchars($avail['slug']); ?>/install"
                  style="display:none;">
              <input type="hidden" name="csrf_token" value="{{csrf_token}}">
            </form>
            <button type="button" class="btn btn-sm btn-primary module-install-btn"
                    data-slug="<?php echo htmlspecialchars($avail['slug']); ?>"
                    data-form="install-<?php echo htmlspecialchars($avail['slug']); ?>"
                    data-name="<?php echo htmlspecialchars($avail['name'] ?? $avail['slug']); ?>">
              <i class="pi pi-download me-1"></i>Install
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
function refreshPanels(onDone) {
  var sep = window.location.search ? '&' : '?';
  window.VtxAjax.get(window.location.href + sep + '_=' + new Date().getTime(), function(ok, html) {
    if (!ok) { location.reload(); return; }
    var doc = new DOMParser().parseFromString(html, 'text/html');
    ['installed-panel', 'available-panel'].forEach(function(id) {
      var fresh   = doc.getElementById(id);
      var current = document.getElementById(id);
      if (fresh && current) current.innerHTML = fresh.innerHTML;
    });
    // Refresh sidebar nav so module links appear/disappear immediately
    var freshNav   = doc.querySelector('.vtx-sidebar-nav');
    var currentNav = document.querySelector('.vtx-sidebar-nav');
    if (freshNav && currentNav) {
      currentNav.innerHTML = freshNav.innerHTML;
      if (typeof window.vtxInitNavGroups === 'function') window.vtxInitNavGroups();
    }
    attachToggleListeners();
    attachInstallListeners();
    attachUninstallListeners();
    attachSyncListeners();
    if (onDone) onDone();
  });
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

attachInstallListeners();
attachUninstallListeners();
attachToggleListeners();
attachSyncListeners();

function attachSyncListeners() {
  document.querySelectorAll('.module-sync-btn:not([data-wired])').forEach(function(btn) {
    btn.dataset.wired = '1';
    btn.addEventListener('click', function() {
      var slug = this.dataset.slug;
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
</script>
