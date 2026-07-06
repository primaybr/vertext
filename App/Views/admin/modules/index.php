<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-layers me-2 text-primary"></i>Module Manager</h1>
    <p class="vtx-page-desc">Install bundles for quick site setup, or install individual modules a la carte.</p>
  </div>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="vtx-alert vtx-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> mb-3">
  <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<!-- ── Tab Navigation ────────────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;">
  <div class="vtx-mod-tabs">
    <button type="button" class="vtx-mod-tab active" data-tab="packages">
      <i class="pi pi-layers me-1"></i>Packages
    </button>
    <button type="button" class="vtx-mod-tab" data-tab="modules">
      <i class="pi pi-grid me-1"></i>Modules
    </button>
  </div>
  <a href="{{baseUrl}}/admin/modules/bundles/create" class="btn btn-sm btn-outline-secondary" id="create-bundle-btn" style="display:none;">
    <i class="pi pi-plus me-1"></i>Create Bundle
  </a>
  <button type="button" class="btn btn-sm btn-outline-secondary" id="url-install-btn" style="display:none;">
    <i class="pi pi-link me-1"></i>Install from URL
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
    <?php if (empty($bundle['builtin'])): ?>
    <a href="{{baseUrl}}/admin/modules/bundles/<?php echo $bSlug; ?>/edit"
       class="btn btn-sm btn-outline-secondary" title="Edit bundle">
      <i class="pi pi-edit"></i>
    </a>
    <form class="bundle-delete-form" method="POST"
          action="{{baseUrl}}/admin/modules/bundles/<?php echo $bSlug; ?>/delete" style="display:inline;">
      <input type="hidden" name="csrf_token" value="{{csrf_token}}">
      <button type="button" class="btn btn-sm btn-outline-danger bundle-delete-btn"
              data-name="<?php echo $bName; ?>">
        <i class="pi pi-trash"></i>
      </button>
    </form>
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
        <div style="width:32px;height:32px;border-radius:8px;background:var(--ps-bg-subtle);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
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
                  data-name="<?php echo $name; ?>"
                  data-settings='<?php echo htmlspecialchars(json_encode($avail['install_settings'] ?? []), ENT_QUOTES); ?>'>
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
<div id="bundle-modal-overlay" data-csrf="{{csrf_token}}" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center;">
  <div id="bundle-modal" style="background:var(--ps-bg-base);border-radius:12px;padding:1.5rem;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.3);max-height:90vh;overflow-y:auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
      <h3 id="bundle-modal-title" style="font-size:1rem;font-weight:700;margin:0;">Install Bundle</h3>
      <button type="button" id="bundle-modal-close" class="btn btn-sm btn-outline-secondary" style="padding:.25rem .5rem;">
        <i class="pi pi-x"></i>
      </button>
    </div>

    <!-- Step 1: Module checklist -->
    <div id="bundle-modal-checklist">
      <p style="font-size:.8125rem;color:var(--ps-text);margin-bottom:.75rem;">
        Select the modules to install. Required modules cannot be deselected.
      </p>
      <div id="bundle-module-list" style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1rem;"></div>
      <div style="display:flex;gap:.5rem;justify-content:flex-end;">
        <button type="button" id="bundle-modal-cancel" class="btn btn-sm btn-outline-secondary">Cancel</button>
        <button type="button" id="bundle-modal-confirm" class="btn btn-sm btn-primary">
          <i class="pi pi-arrow-right me-1"></i>Next
        </button>
      </div>
    </div>

    <!-- Step 2: Configure (shown only when modules have install_settings) -->
    <div id="bundle-modal-configure" style="display:none;">
      <p style="font-size:.8125rem;color:var(--ps-text);margin-bottom:.75rem;">
        Configure the modules before installing.
      </p>
      <div id="bundle-config-fields" style="display:flex;flex-direction:column;gap:1rem;margin-bottom:1rem;"></div>
      <div style="display:flex;gap:.5rem;justify-content:flex-end;">
        <button type="button" id="bundle-config-back" class="btn btn-sm btn-outline-secondary">
          <i class="pi pi-arrow-left me-1"></i>Back
        </button>
        <button type="button" id="bundle-config-confirm" class="btn btn-sm btn-primary">
          <i class="pi pi-download me-1"></i>Install
        </button>
      </div>
    </div>

    <!-- Step 3: Progress view -->
    <div id="bundle-modal-progress" style="display:none;">
      <p style="font-size:.8125rem;color:var(--ps-text);margin-bottom:.75rem;">Installing modules...</p>
      <div id="bundle-progress-list" style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1rem;"></div>
      <div id="bundle-progress-done" style="display:none;text-align:right;margin-top:.5rem;">
        <button type="button" id="bundle-modal-reload" class="btn btn-sm btn-primary">Done</button>
      </div>
    </div>
  </div>
</div>

<!-- Configure modal for individual a-la-carte install -->
<div id="mod-config-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1060;align-items:center;justify-content:center;">
  <div id="mod-config-modal" style="background:var(--ps-bg-base);border-radius:12px;padding:1.5rem;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
      <h3 id="mod-config-title" style="font-size:1rem;font-weight:700;margin:0;">Configure Module</h3>
      <button type="button" id="mod-config-close" class="btn btn-sm btn-outline-secondary" style="padding:.25rem .5rem;">
        <i class="pi pi-x"></i>
      </button>
    </div>
    <p style="font-size:.8125rem;color:var(--ps-text);margin-bottom:.875rem;">
      Set initial configuration. You can change these later in module settings.
    </p>
    <div id="mod-config-fields" style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1.25rem;"></div>
    <div style="display:flex;gap:.5rem;justify-content:flex-end;">
      <button type="button" id="mod-config-cancel" class="btn btn-sm btn-outline-secondary">Cancel</button>
      <button type="button" id="mod-config-confirm" class="btn btn-sm btn-primary">
        <i class="pi pi-download me-1"></i>Install
      </button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- MARKETPLACE: INSTALL FROM URL MODAL                                    -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="url-modal-overlay" data-csrf="{{csrf_token}}" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1070;align-items:center;justify-content:center;">
  <div id="url-modal" style="background:var(--ps-bg-base);border-radius:12px;padding:1.5rem;width:100%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,.3);max-height:90vh;overflow-y:auto;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
      <h3 style="font-size:1rem;font-weight:700;margin:0;"><i class="pi pi-link me-2 text-primary"></i>Install Module from URL</h3>
      <button type="button" id="url-modal-close" class="btn btn-sm btn-outline-secondary" style="padding:.25rem .5rem;">
        <i class="pi pi-x"></i>
      </button>
    </div>

    <!-- Step 1: URL input -->
    <div id="url-step-input">
      <p style="font-size:.8125rem;color:var(--ps-text-secondary);margin-bottom:.875rem;">
        Enter the direct HTTPS URL to a Vertext module ZIP archive. Only trusted sources.
      </p>
      <div style="margin-bottom:.875rem;">
        <label style="display:block;font-size:.8125rem;font-weight:600;margin-bottom:.3rem;" for="url-input">Module ZIP URL</label>
        <input class="form-control" type="url" id="url-input" placeholder="https://example.com/my-module-v1.0.0.zip"
               style="font-size:.875rem;">
      </div>
      <div id="url-fetch-error" style="display:none;font-size:.8125rem;padding:.5rem .75rem;border-radius:5px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;margin-bottom:.75rem;"></div>
      <div style="display:flex;gap:.5rem;justify-content:flex-end;">
        <button type="button" id="url-modal-cancel1" class="btn btn-sm btn-outline-secondary">Cancel</button>
        <button type="button" id="url-fetch-btn" class="btn btn-sm btn-primary">
          <i class="pi pi-download me-1"></i>Download &amp; Verify
        </button>
      </div>
    </div>

    <!-- Step 2: Verification -->
    <div id="url-step-verify" style="display:none;">
      <div style="margin-bottom:1rem;">
        <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.875rem;">
          <div style="width:38px;height:38px;border-radius:8px;background:rgba(34,197,94,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="pi pi-check-circle" style="color:#16a34a;font-size:1.125rem;"></i>
          </div>
          <div>
            <div id="url-mod-name" style="font-size:.9375rem;font-weight:700;"></div>
            <div style="display:flex;gap:.375rem;margin-top:.15rem;">
              <span id="url-mod-slug" class="vtx-tag" style="font-size:.6875rem;"></span>
              <span id="url-mod-version" class="vtx-tag info" style="font-size:.6875rem;"></span>
            </div>
          </div>
        </div>
        <div id="url-mod-desc" style="font-size:.8125rem;color:var(--ps-text-secondary);margin-bottom:.875rem;"></div>
        <div style="background:var(--ps-bg-subtle);border:1px solid var(--ps-border);border-radius:6px;padding:.625rem .75rem;">
          <div style="font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--ps-text-muted);margin-bottom:.25rem;">
            SHA-256 Hash — compare with publisher&rsquo;s checksum
          </div>
          <code id="url-mod-hash" style="font-size:.75rem;word-break:break-all;display:block;line-height:1.5;"></code>
        </div>
      </div>
      <div id="url-install-error" style="display:none;font-size:.8125rem;padding:.5rem .75rem;border-radius:5px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;margin-bottom:.75rem;"></div>
      <div style="display:flex;gap:.5rem;justify-content:flex-end;">
        <button type="button" id="url-verify-back" class="btn btn-sm btn-outline-secondary">
          <i class="pi pi-arrow-left me-1"></i>Back
        </button>
        <button type="button" id="url-install-confirm" class="btn btn-sm btn-primary">
          <i class="pi pi-download me-1"></i>Install Module
        </button>
      </div>
    </div>

  </div>
</div>

