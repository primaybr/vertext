<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-layers me-2 text-primary"></i>Module Manager</h1>
    <p class="vtx-page-desc">Install bundles for quick site setup, or install individual modules a la carte.</p>
  </div>
</div>

<!-- ── Tab Navigation ────────────────────────────────────────────────────── -->
<div class="vtx-mod-tabs mb-4">
  <button type="button" class="vtx-mod-tab active" data-tab="packages">
    <i class="pi pi-layers me-1"></i>Packages
  </button>
  <button type="button" class="vtx-mod-tab" data-tab="modules">
    <i class="pi pi-grid me-1"></i>Modules
  </button>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- TAB: PACKAGES                                                           -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="tab-packages">

<?php if (empty($bundles)): ?>
<div class="vtx-panel">
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-inbox"></i></div>
    <div class="vtx-empty-title">No bundles found</div>
    <div class="vtx-empty-desc">Place bundle definitions in <code>App/Bundles/{slug}/bundle.json</code>.</div>
  </div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem;">
<?php foreach ($bundles as $bundle): ?>
<?php
  $bSlug   = htmlspecialchars($bundle['slug']);
  $bName   = htmlspecialchars($bundle['name'] ?? $bundle['slug']);
  $bDesc   = htmlspecialchars($bundle['description'] ?? '');
  $bIcon   = htmlspecialchars($bundle['icon'] ?? 'pi-layers');
  $bStatus = $bundle['status'] ?? 'none';
  $bCount  = (int) ($bundle['total_count'] ?? 0);
  $bInst   = (int) ($bundle['installed_count'] ?? 0);
?>
<div class="vtx-bundle-card" id="bundle-card-<?php echo $bSlug; ?>">
  <div class="vtx-bundle-card-head">
    <div class="vtx-bundle-icon <?php echo $bStatus === 'installed' ? 'vtx-bundle-icon--done' : ''; ?>">
      <i class="pi <?php echo $bIcon; ?>"></i>
    </div>
    <div style="flex:1;min-width:0;">
      <div class="vtx-bundle-name"><?php echo $bName; ?></div>
      <?php if ($bStatus === 'installed'): ?>
      <span class="vtx-tag success" style="font-size:.6875rem;">Installed</span>
      <?php elseif ($bStatus === 'partial'): ?>
      <span class="vtx-tag warning" style="font-size:.6875rem;">Partial (<?php echo $bInst; ?>/<?php echo $bCount; ?>)</span>
      <?php else: ?>
      <span class="vtx-tag" style="font-size:.6875rem;color:var(--ps-text-muted);">Not Installed</span>
      <?php endif; ?>
    </div>
    <span class="vtx-tag" style="font-size:.6875rem;align-self:flex-start;"><?php echo $bCount; ?> modules</span>
  </div>

  <?php if ($bDesc): ?>
  <div class="vtx-bundle-desc"><?php echo $bDesc; ?></div>
  <?php endif; ?>

  <div class="vtx-bundle-chips">
    <?php foreach ($bundle['modules'] as $bMod): ?>
    <span class="vtx-bundle-chip <?php echo $bMod['installed'] ? 'vtx-bundle-chip--on' : ''; ?>">
      <?php if (!empty($bMod['required'])): ?>
      <span class="vtx-bundle-chip-req" title="Required">*</span>
      <?php endif; ?>
      <?php echo htmlspecialchars($bMod['slug']); ?>
    </span>
    <?php endforeach; ?>
  </div>

  <div class="vtx-module-actions">
    <?php if ($bStatus === 'installed'): ?>
    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
      <i class="pi pi-check me-1"></i>All Installed
    </button>
    <?php else: ?>
    <button type="button" class="btn btn-sm btn-primary bundle-install-btn"
            data-slug="<?php echo $bSlug; ?>"
            data-name="<?php echo $bName; ?>"
            data-modules='<?php echo htmlspecialchars(json_encode($bundle['modules']), ENT_QUOTES); ?>'>
      <i class="pi pi-download me-1"></i>
      <?php echo $bStatus === 'partial' ? 'Complete Bundle' : 'Install Bundle'; ?>
    </button>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</div><!-- /tab-packages -->

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- TAB: MODULES (a la carte)                                               -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="tab-modules" style="display:none;">

<!-- ── System Modules ───────────────────────────────────────────────────── -->
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

</div><!-- /tab-modules -->

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- BUNDLE INSTALL MODAL                                                    -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="bundle-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center;">
  <div id="bundle-modal" style="background:var(--ps-bg);border-radius:12px;padding:1.5rem;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.3);max-height:90vh;overflow-y:auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
      <h3 id="bundle-modal-title" style="font-size:1rem;font-weight:700;margin:0;">Install Bundle</h3>
      <button type="button" id="bundle-modal-close" class="btn btn-sm btn-outline-secondary" style="padding:.25rem .5rem;">
        <i class="pi pi-x"></i>
      </button>
    </div>

    <!-- Module checklist -->
    <div id="bundle-modal-checklist">
      <p style="font-size:.8125rem;color:var(--ps-text-secondary);margin-bottom:.75rem;">
        Select the modules to install. Required modules cannot be deselected.
      </p>
      <div id="bundle-module-list" style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1rem;"></div>
      <div style="display:flex;gap:.5rem;justify-content:flex-end;">
        <button type="button" id="bundle-modal-cancel" class="btn btn-sm btn-outline-secondary">Cancel</button>
        <button type="button" id="bundle-modal-confirm" class="btn btn-sm btn-primary">
          <i class="pi pi-download me-1"></i>Install
        </button>
      </div>
    </div>

    <!-- Progress view -->
    <div id="bundle-modal-progress" style="display:none;">
      <p style="font-size:.8125rem;color:var(--ps-text-secondary);margin-bottom:.75rem;">Installing modules...</p>
      <div id="bundle-progress-list" style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1rem;"></div>
      <div id="bundle-progress-done" style="display:none;text-align:right;margin-top:.5rem;">
        <button type="button" id="bundle-modal-reload" class="btn btn-sm btn-primary">Done</button>
      </div>
    </div>
  </div>
</div>

<style>
/* ── Tab switcher ──────────────────────────────────────────────────────── */
.vtx-mod-tabs {
  display: flex;
  gap: .25rem;
  border-bottom: 2px solid var(--ps-border);
  padding-bottom: 0;
}
.vtx-mod-tab {
  background: none;
  border: none;
  padding: .5rem 1rem;
  font-size: .875rem;
  font-weight: 500;
  color: var(--ps-text-secondary);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  border-radius: 4px 4px 0 0;
  transition: color .15s, border-color .15s;
}
.vtx-mod-tab:hover {
  color: var(--ps-text);
}
.vtx-mod-tab.active {
  color: var(--ps-primary);
  border-bottom-color: var(--ps-primary);
  background: var(--ps-primary-light, rgba(79,70,229,.06));
}

/* ── Bundle cards ──────────────────────────────────────────────────────── */
.vtx-bundle-card {
  border: 1px solid var(--ps-border);
  border-radius: 10px;
  padding: 1.125rem;
  display: flex;
  flex-direction: column;
  gap: .75rem;
  background: var(--ps-bg);
}
.vtx-bundle-card-head {
  display: flex;
  align-items: flex-start;
  gap: .75rem;
}
.vtx-bundle-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: var(--ps-primary-light, rgba(79,70,229,.12));
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.vtx-bundle-icon .pi {
  font-size: 1.125rem;
  color: var(--ps-primary);
}
.vtx-bundle-icon--done {
  background: rgba(34,197,94,.12);
}
.vtx-bundle-icon--done .pi {
  color: #16a34a;
}
.vtx-bundle-name {
  font-size: .9375rem;
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: .2rem;
}
.vtx-bundle-desc {
  font-size: .8125rem;
  color: var(--ps-text-secondary);
  line-height: 1.45;
}
.vtx-bundle-chips {
  display: flex;
  flex-wrap: wrap;
  gap: .3rem;
}
.vtx-bundle-chip {
  display: inline-flex;
  align-items: center;
  gap: .2rem;
  font-size: .6875rem;
  padding: .15rem .45rem;
  border-radius: 4px;
  background: var(--ps-bg-secondary);
  color: var(--ps-text-secondary);
  border: 1px solid var(--ps-border);
}
.vtx-bundle-chip--on {
  background: rgba(34,197,94,.1);
  color: #15803d;
  border-color: rgba(34,197,94,.3);
}
.vtx-bundle-chip-req {
  color: var(--ps-primary);
  font-weight: 700;
  font-size: .6875rem;
}

/* ── Module cards (a la carte tab) ────────────────────────────────────── */
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

/* ── Bundle modal checklist item ───────────────────────────────────────── */
.vtx-bundle-check-item {
  display: flex;
  align-items: center;
  gap: .625rem;
  padding: .5rem .625rem;
  border-radius: 6px;
  border: 1px solid var(--ps-border);
  background: var(--ps-bg-secondary);
}
.vtx-bundle-check-item input[type="checkbox"] {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: var(--ps-primary);
}
.vtx-bundle-check-item label {
  flex: 1;
  font-size: .8125rem;
  cursor: pointer;
}
.vtx-bundle-check-item .vtx-bundle-check-badges {
  display: flex;
  gap: .25rem;
}

/* ── Bundle progress item ──────────────────────────────────────────────── */
.vtx-bundle-progress-item {
  display: flex;
  align-items: center;
  gap: .625rem;
  padding: .4rem .625rem;
  border-radius: 6px;
  font-size: .8125rem;
}
.vtx-bundle-progress-item.pending {
  color: var(--ps-text-muted);
}
.vtx-bundle-progress-item.installing {
  color: var(--ps-primary);
}
.vtx-bundle-progress-item.success {
  color: #16a34a;
}
.vtx-bundle-progress-item.error {
  color: #dc2626;
}
.vtx-bundle-progress-item.skipped {
  color: var(--ps-text-muted);
}
</style>

<script>
// ── Tab switching ───────────────────────────────────────────────────────
(function() {
  var tabs    = document.querySelectorAll('.vtx-mod-tab');
  var panes   = {'packages': document.getElementById('tab-packages'), 'modules': document.getElementById('tab-modules')};
  var stored  = localStorage.getItem('vtx-mod-tab') || 'packages';

  function activateTab(key) {
    tabs.forEach(function(t) { t.classList.toggle('active', t.dataset.tab === key); });
    Object.keys(panes).forEach(function(k) { panes[k].style.display = k === key ? '' : 'none'; });
    localStorage.setItem('vtx-mod-tab', key);
  }

  tabs.forEach(function(btn) {
    btn.addEventListener('click', function() { activateTab(this.dataset.tab); });
  });

  activateTab(stored);
}());

// ── Bundle install modal ────────────────────────────────────────────────
(function() {
  var overlay   = document.getElementById('bundle-modal-overlay');
  var modalEl   = document.getElementById('bundle-modal');
  var titleEl   = document.getElementById('bundle-modal-title');
  var checkList = document.getElementById('bundle-module-list');
  var checkPane = document.getElementById('bundle-modal-checklist');
  var progPane  = document.getElementById('bundle-modal-progress');
  var progList  = document.getElementById('bundle-progress-list');
  var doneDiv   = document.getElementById('bundle-progress-done');
  var confirmBtn= document.getElementById('bundle-modal-confirm');
  var cancelBtn = document.getElementById('bundle-modal-cancel');
  var closeBtn  = document.getElementById('bundle-modal-close');
  var reloadBtn = document.getElementById('bundle-modal-reload');

  var currentModules = [];

  function openModal(bundleName, modules) {
    currentModules = modules;
    titleEl.textContent = 'Install "' + bundleName + '"';

    checkList.innerHTML = '';
    modules.forEach(function(mod, i) {
      var isRequired = !!mod.required;
      var isInstalled = !!mod.installed;

      var item = document.createElement('div');
      item.className = 'vtx-bundle-check-item' + (isInstalled ? ' vtx-bundle-check-item--done' : '');

      var cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.id   = 'bmod-' + i;
      cb.value = mod.slug;
      cb.checked  = !isInstalled;
      cb.disabled = isRequired || isInstalled;

      var lbl = document.createElement('label');
      lbl.htmlFor     = 'bmod-' + i;
      lbl.textContent = mod.slug;

      var badges = document.createElement('div');
      badges.className = 'vtx-bundle-check-badges';

      if (isRequired) {
        var req = document.createElement('span');
        req.className   = 'vtx-tag info';
        req.style.fontSize = '.6rem';
        req.textContent = 'required';
        badges.appendChild(req);
      }
      if (isInstalled) {
        var inst = document.createElement('span');
        inst.className   = 'vtx-tag success';
        inst.style.fontSize = '.6rem';
        inst.textContent = 'installed';
        badges.appendChild(inst);
      }

      item.appendChild(cb);
      item.appendChild(lbl);
      item.appendChild(badges);
      checkList.appendChild(item);
    });

    checkPane.style.display = '';
    progPane.style.display  = 'none';
    doneDiv.style.display   = 'none';
    overlay.style.display   = 'flex';
  }

  function closeModal() {
    overlay.style.display = 'none';
  }

  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  reloadBtn.addEventListener('click', function() { location.reload(); });

  overlay.addEventListener('click', function(e) {
    if (e.target === overlay) closeModal();
  });

  confirmBtn.addEventListener('click', function() {
    var selected = [];
    checkList.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
      if (cb.checked) selected.push(cb.value);
    });
    if (!selected.length) {
      window.Phuse.toast('No modules selected.', 'error');
      return;
    }
    runBundleInstall(selected);
  });

  function runBundleInstall(slugs) {
    checkPane.style.display = 'none';
    progPane.style.display  = '';
    progList.innerHTML = '';
    doneDiv.style.display   = 'none';

    var items = {};
    slugs.forEach(function(slug) {
      var row = document.createElement('div');
      row.className = 'vtx-bundle-progress-item pending';
      row.id = 'prog-' + slug;
      row.innerHTML = '<i class="pi pi-circle"></i> <span>' + slug + '</span>';
      progList.appendChild(row);
      items[slug] = row;
    });

    var formData = new FormData();
    formData.append('csrf_token', '{{csrf_token}}');
    slugs.forEach(function(s) { formData.append('modules[]', s); });

    var xhr = new XMLHttpRequest();
    xhr.open('POST', (window.VTX_BASE_URL || '') + '/admin/modules/install-bundle');
    xhr.onload = function() {
      var res = null;
      try { res = JSON.parse(xhr.responseText); } catch(e) {}
      if (res && res.results) {
        Object.keys(res.results).forEach(function(slug) {
          var r   = res.results[slug];
          var row = items[slug];
          if (!row) return;
          if (r.skipped) {
            row.className = 'vtx-bundle-progress-item skipped';
            row.innerHTML = '<i class="pi pi-minus-circle"></i> <span>' + slug + ' - already installed</span>';
          } else if (r.success) {
            row.className = 'vtx-bundle-progress-item success';
            row.innerHTML = '<i class="pi pi-check-circle"></i> <span>' + (r.name || slug) + ' installed</span>';
          } else {
            row.className = 'vtx-bundle-progress-item error';
            row.innerHTML = '<i class="pi pi-x-circle"></i> <span>' + slug + ' - ' + (r.message || 'failed') + '</span>';
          }
        });
      } else {
        var msg = (res && res.message) ? res.message : 'Bundle install failed.';
        window.Phuse.toast(msg, 'error');
      }
      doneDiv.style.display = '';
    };
    xhr.onerror = function() {
      window.Phuse.toast('Network error during bundle install.', 'error');
      doneDiv.style.display = '';
    };

    slugs.forEach(function(slug) {
      var row = items[slug];
      row.className = 'vtx-bundle-progress-item installing';
      row.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <span>' + slug + ' - installing...</span>';
    });

    xhr.send(formData);
  }

  document.querySelectorAll('.bundle-install-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var name    = this.dataset.name;
      var modules = JSON.parse(this.dataset.modules);
      openModal(name, modules);
    });
  });
}());

// ── Module tab: a la carte listeners ────────────────────────────────────
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
          me.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Installing...';

          window.VtxAjax.postForm(form.action, form, function(ok, res) {
            var msg     = (res && res.message) ? res.message : (ok ? 'Module installed.' : 'Installation failed.');
            var success = ok && res && res.success;
            if (success && res.setup_url) {
              window.Phuse.toast('Module installed! Opening setup wizard...', 'success');
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
      me.textContent = '...';

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
          me.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Uninstalling...';

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
