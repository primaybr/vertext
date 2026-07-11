/* Vertext CMS - Admin Page-Specific Scripts
   One-off behavior for individual core admin pages that doesn't belong in the
   shared admin.js app chrome. Organized by page, each section header names
   the view file it was extracted from. Every handler is scoped with an
   element-existence check so this file is safe to load on every admin page. */
(function () {
    'use strict';

    /* -- admin/roles/_form.php: select-all / clear-all permissions -- */
    /* Delegated on document since this form is loaded into a modal via AJAX. */
    document.addEventListener('click', function (e) {
        if (e.target.id === 'r-select-all') {
            document.querySelectorAll('.r-perm-cb').forEach(function (cb) { cb.checked = true; });
        } else if (e.target.id === 'r-clear-all') {
            document.querySelectorAll('.r-perm-cb').forEach(function (cb) { cb.checked = false; });
        }
    });

    /* -- admin/_layouts/base.php: flash message toast ------------- */
    document.addEventListener('DOMContentLoaded', function () {
        var flash = document.getElementById('vtx-flash-data');
        if (flash) {
            Phuse.toast(flash.dataset.message, flash.dataset.type || 'info');
        }
    });

    /* -- admin/api_keys/index.php: create-key prompt, copy-to-clipboard -- */
    (function () {
        var createBtn = document.getElementById('ak-create-btn');
        if (createBtn) {
            createBtn.addEventListener('click', function () {
                window.vtxPromptModal({
                    title: 'New API Key',
                    message: 'Name for the new API key (e.g. "Mobile app", "Zapier"):',
                    placeholder: 'Key name',
                    confirmLabel: 'Create',
                    onConfirm: function (name) {
                        var fd = new FormData();
                        fd.append('csrf_token', createBtn.dataset.csrf);
                        fd.append('name', name);
                        fetch(window.VTX_BASE_URL + '/admin/api-keys/store', {
                            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (!res.success) { Phuse.toast(res.message || 'Failed.', 'error'); return; }
                            document.getElementById('ak-reveal-key').textContent = res.key;
                            document.getElementById('ak-reveal').style.display = '';
                            Phuse.toast(res.message, 'success');
                            // Show the new row without losing the revealed key: append to table lazily
                            // (full list refresh happens on next navigation)
                        })
                        .catch(function () { Phuse.toast('Network error.', 'error'); });
                    }
                });
            });
        }

        var copyBtn = document.getElementById('ak-copy-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var key = document.getElementById('ak-reveal-key').textContent;
                navigator.clipboard.writeText(key).then(function () {
                    Phuse.toast('Key copied to clipboard.', 'success');
                });
            });
        }
    })();

    /* -- admin/roles/permissions.php: create/cancel panel, auto-slug, create/delete AJAX -- */
    (function () {
        var createBtn = document.getElementById('create-perm-btn');
        var cancelBtn = document.getElementById('cancel-perm-btn');
        var formPanel = document.getElementById('create-perm-form');
        var form      = document.getElementById('perm-create-form');
        if (!createBtn || !cancelBtn || !formPanel || !form) return;

        createBtn.addEventListener('click', function () { formPanel.style.display = ''; createBtn.style.display = 'none'; });
        cancelBtn.addEventListener('click', function () { formPanel.style.display = 'none'; createBtn.style.display = ''; });

        // Auto-generate slug from name
        form.querySelector('[name="name"]').addEventListener('input', function () {
            var slug = this.value.toLowerCase().replace(/[^a-z0-9\.\-_]+/g, '.').replace(/\.+/g, '.').replace(/^\.|\.$/g, '');
            form.querySelector('[name="slug"]').value = slug;
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Creating...';
            window.VtxAjax.postForm(window.VTX_BASE_URL + '/admin/roles/permissions/store', form, function (ok, res) {
                btn.disabled = false;
                btn.textContent = 'Create Permission';
                var success = ok && res && res.success;
                window.Phuse.toast((res && res.message) || (success ? 'Created.' : 'Failed.'), success ? 'success' : 'error');
                if (success) { location.reload(); }
            });
        });

        document.querySelectorAll('.perm-delete-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id    = this.dataset.id;
                var slug  = this.dataset.slug;
                var token = this.dataset.csrf;
                window.vtxConfirmModal({
                    title: 'Delete Permission',
                    message: 'Delete "' + slug + '"? Any roles with this permission will lose it.',
                    confirmLabel: 'Delete',
                    confirmClass: 'btn-danger',
                    onConfirm: function () {
                        var fd = new FormData();
                        fd.append('csrf_token', token);
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', window.VTX_BASE_URL + '/admin/roles/permissions/' + id + '/delete');
                        xhr.onload = function () {
                            var res = null;
                            try { res = JSON.parse(xhr.responseText); } catch (e) {}
                            var success = res && res.success;
                            window.Phuse.toast((res && res.message) || (success ? 'Deleted.' : 'Failed.'), success ? 'success' : 'error');
                            if (success) { location.reload(); }
                        };
                        xhr.send(fd);
                    }
                });
            });
        });
    })();

    /* -- admin/settings/index.php: SMTP toggle, test-mail, maintenance toggle, run-migration -- */
    window.vtxToggleSmtp = function (val) {
        var f = document.getElementById('vtx-smtp-fields');
        if (f) f.style.display = val === 'smtp' ? '' : 'none';
    };

    (function () {
        var testBtn = document.getElementById('vtx-test-mail-btn');
        if (!testBtn) return;
        testBtn.addEventListener('click', function () {
            var res = document.getElementById('vtx-test-mail-result');
            res.style.display = 'block';
            res.style.background = 'var(--ps-bg-subtle)';
            res.textContent = 'Sending…';
            fetch(window.VTX_BASE_URL + '/admin/settings/test-mail', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf_token=' + encodeURIComponent(document.querySelector('[name=csrf_token]').value)
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                res.style.background = d.success ? '#dcfce7' : '#fee2e2';
                res.style.color      = d.success ? '#166534' : '#991b1b';
                res.textContent = d.message;
            })
            .catch(function () { res.textContent = 'Request failed.'; });
        });
    })();

    (function () {
        var btn = document.getElementById('vtx-maint-toggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            btn.disabled = true;
            btn.classList.add('vtx-pill-toggle--loading');
            var fd = new FormData();
            fd.append('csrf_token', btn.dataset.csrf);
            fetch(window.VTX_BASE_URL + '/admin/settings/toggle-maintenance', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    btn.disabled = false;
                    btn.classList.remove('vtx-pill-toggle--loading');
                    if (d.success) {
                        var on = d.enabled;
                        btn.classList.toggle('vtx-pill-toggle--on', on);
                        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                        var lbl = document.getElementById('vtx-maint-status');
                        if (lbl) lbl.textContent = on ? 'On' : 'Off';
                        var banner = document.getElementById('vtx-maint-banner');
                        if (banner) banner.style.display = on ? 'flex' : 'none';
                        Phuse.toast(d.message, 'success');
                        setTimeout(function () { window.location.reload(); }, 1200);
                    } else {
                        Phuse.toast(d.message || 'Failed to save.', 'error');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.classList.remove('vtx-pill-toggle--loading');
                    Phuse.toast('Request failed.', 'error');
                });
        });
    })();

    (function () {
        var btn = document.getElementById('vtx-run-migration-btn');
        var result = document.getElementById('vtx-migration-result');
        if (!btn) return;
        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="pi pi-spinner me-1"></i> Running...';
            result.style.display = 'none';
            var fd = new FormData();
            fd.append('csrf_token', btn.dataset.csrf);
            fetch(window.VTX_BASE_URL + '/admin/settings/run-migration', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="pi pi-database me-1"></i> Run Migration';
                    result.style.display = 'block';
                    if (d.success) {
                        result.style.background = 'color-mix(in srgb,var(--ps-success,#22c55e) 12%,transparent)';
                        result.style.border = '1px solid color-mix(in srgb,var(--ps-success,#22c55e) 40%,transparent)';
                        result.style.color = 'var(--ps-success,#16a34a)';
                        result.textContent = d.message;
                        Phuse.toast(d.message, 'success');
                    } else {
                        result.style.background = 'color-mix(in srgb,var(--ps-danger,#ef4444) 12%,transparent)';
                        result.style.border = '1px solid color-mix(in srgb,var(--ps-danger,#ef4444) 40%,transparent)';
                        result.style.color = 'var(--ps-danger,#dc2626)';
                        result.textContent = d.message;
                        Phuse.toast(d.message || 'Migration failed.', 'error');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="pi pi-database me-1"></i> Run Migration';
                    Phuse.toast('Request failed.', 'error');
                });
        });
    })();

    /* -- admin/themes/index.php: theme-activate form -> AJAX submit -- */
    document.querySelectorAll('.theme-activate-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = this.querySelector('button[type=submit]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Activating...';
            window.VtxAjax.postForm(this.action, this, function (ok, res) {
                if (ok && res && res.success) {
                    window.Phuse.toast(res.message || 'Theme activated.', 'success');
                    setTimeout(function () { location.reload(); }, 600);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="pi pi-check-circle me-1"></i>Activate';
                    window.Phuse.toast((res && res.message) || 'Failed to activate theme.', 'error');
                }
            });
        });
    });

    /* -- admin/translations/index.php: translation-save form -> AJAX -- */
    /* Delegated on document since this view can be reloaded via AJAX (renderPartial). */
    document.addEventListener('submit', function (e) {
        if (e.target && e.target.id === 'tr-form') {
            e.preventDefault();
            var form = e.target;
            var fd = new FormData(form);
            fd.append('csrf_token', form.dataset.csrf);
            fd.append('locale', form.dataset.locale);
            fd.append('group', form.dataset.group);
            fetch(window.VTX_BASE_URL + '/admin/translations/save', {
                method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (res) { Phuse.toast(res.message || (res.success ? 'Saved.' : 'Failed.'), res.success ? 'success' : 'error'); })
            .catch(function () { Phuse.toast('Network error.', 'error'); });
        }
    });

    /* -- admin/profile/2fa.php: copy-secret-to-clipboard with fallback selection -- */
    window.copySecret = function () {
        var el  = document.getElementById('secret-display');
        var btn = document.getElementById('copy-btn');
        if (!el || !btn) return;
        var raw = el.dataset.secret || '';
        navigator.clipboard.writeText(raw.replace(/=+$/, '')).then(function () {
            btn.innerHTML = '<i class="pi pi-check"></i>';
            setTimeout(function () { btn.innerHTML = '<i class="pi pi-copy"></i>'; }, 2000);
        }).catch(function () {
            el.style.outline = '2px solid var(--ps-primary)';
            window.getSelection().selectAllChildren(el);
        });
    };

    /* -- admin/profile/2fa_backup_codes.php: copy-all-codes + print-codes -- */
    window.copyAllCodes = function () {
        var grid = document.getElementById('codes-grid');
        if (!grid) return;
        var codes = JSON.parse(grid.dataset.codes);
        navigator.clipboard.writeText(codes.join('\n')).then(function () {
            Phuse.toast('Backup codes copied to clipboard.', 'success');
        });
    };
    window.printCodes = function () {
        var grid = document.getElementById('codes-grid');
        if (!grid) return;
        var codes = JSON.parse(grid.dataset.codes);
        var w = window.open('', '_blank', 'width=400,height=500');
        w.document.write(
            '<html><head><title>Vertext CMS - Backup Codes</title>' +
            '<style>body{font-family:monospace;padding:2rem;}h2{margin-bottom:1rem;}' +
            'code{display:block;padding:.3rem;border:1px solid #ccc;border-radius:4px;' +
            'margin:.25rem 0;font-size:1rem;letter-spacing:.08em;}</style></head><body>' +
            '<h2>Vertext CMS - 2FA Backup Codes</h2>' +
            '<p>Each code can only be used once.</p>' +
            codes.map(function (c) { return '<code>' + c + '</code>'; }).join('') +
            '</body></html>'
        );
        w.document.close();
        w.print();
    };

    /* -- admin/modules/bundle_form.php: auto-slug, icon preview, module checkbox sync -- */
    (function () {
        var form = document.getElementById('bundle-form');
        var nameInput = document.getElementById('b-name');
        if (!form || !nameInput) return;
        var editing = form.dataset.editing === '1';

        if (!editing) {
            nameInput.addEventListener('input', function () {
                var s = this.value.toLowerCase().replace(/[^a-z0-9\s\-]/g, '').trim().replace(/\s+/g, '-');
                document.getElementById('b-slug').value = s;
                document.getElementById('prev-name').textContent = this.value || 'Bundle Name';
            });
        } else {
            nameInput.addEventListener('input', function () {
                document.getElementById('prev-name').textContent = this.value || 'Bundle Name';
            });
        }

        // Icon preview
        var iconSelect = document.getElementById('b-icon-select');
        if (iconSelect) {
            iconSelect.addEventListener('change', function () {
                var ic = this.value;
                document.querySelector('#b-icon-preview i').className = 'pi ' + ic;
                document.getElementById('prev-icon-i').className       = 'pi ' + ic + ' pi-1x';
            });
        }

        // Module checkboxes -> show/hide required toggle + update preview chips
        var checkboxes = document.querySelectorAll('.bf-mod-cb');
        function updatePreview() {
            var chips   = document.getElementById('prev-chips');
            var countEl = document.getElementById('prev-count');
            chips.innerHTML = '';
            var count = 0;
            checkboxes.forEach(function (cb) {
                var req = document.getElementById('bf-req-' + cb.dataset.slug);
                if (req) req.classList.toggle('bf-req-hidden', !cb.checked);
                if (cb.checked) {
                    count++;
                    var span = document.createElement('span');
                    span.className = 'vtx-tag';
                    span.style.fontSize = '.65rem';
                    span.textContent = cb.dataset.slug;
                    chips.appendChild(span);
                }
            });
            countEl.textContent = count + ' module' + (count !== 1 ? 's' : '');
        }
        checkboxes.forEach(function (cb) { cb.addEventListener('change', updatePreview); });
        updatePreview();
    })();
})();

/* ---------------------------------------------------------------------- */
/* admin/modules/index.php: Module Manager (tab switching, bundle-install  */
/* modal wizard, a-la-carte install/toggle/uninstall/sync, install-from-   */
/* URL flow). Kept as its own top-level section (not nested in the IIFE   */
/* above) to preserve the exact global scoping this code had when it ran  */
/* inline on the page - some functions (refreshPanels, attachInstall-      */
/* Listeners, etc.) are plain top-level declarations relied on by other    */
/* statements later in this same block.                                    */
/* ---------------------------------------------------------------------- */

// -- Tab switching -------------------------------------------------------
(function() {
  var tabs    = document.querySelectorAll('.vtx-mod-tab');
  var panes   = {'packages': document.getElementById('tab-packages'), 'modules': document.getElementById('tab-modules')};
  if (!tabs.length || !panes.packages || !panes.modules) return; // Only present on admin/modules
  var stored  = localStorage.getItem('vtx-mod-tab') || 'packages';
  var createBtn = document.getElementById('create-bundle-btn');
  var urlBtn    = document.getElementById('url-install-btn');

  function activateTab(key) {
    tabs.forEach(function(t) { t.classList.toggle('active', t.dataset.tab === key); });
    Object.keys(panes).forEach(function(k) { panes[k].style.display = k === key ? '' : 'none'; });
    if (createBtn) createBtn.style.display = key === 'packages' ? '' : 'none';
    if (urlBtn)    urlBtn.style.display    = key === 'modules'  ? '' : 'none';
    localStorage.setItem('vtx-mod-tab', key);
  }

  tabs.forEach(function(btn) {
    btn.addEventListener('click', function() { activateTab(this.dataset.tab); });
  });

  activateTab(stored);
}());

// -- Bundle install modal ------------------------------------------------
(function() {
  var overlay    = document.getElementById('bundle-modal-overlay');
  var titleEl    = document.getElementById('bundle-modal-title');
  var checkList  = document.getElementById('bundle-module-list');
  var checkPane  = document.getElementById('bundle-modal-checklist');
  var configPane = document.getElementById('bundle-modal-configure');
  var configFlds = document.getElementById('bundle-config-fields');
  var progPane   = document.getElementById('bundle-modal-progress');
  var progList   = document.getElementById('bundle-progress-list');
  var doneDiv    = document.getElementById('bundle-progress-done');
  var confirmBtn = document.getElementById('bundle-modal-confirm');
  var configBack = document.getElementById('bundle-config-back');
  var configConf = document.getElementById('bundle-config-confirm');
  var cancelBtn  = document.getElementById('bundle-modal-cancel');
  var closeBtn   = document.getElementById('bundle-modal-close');
  var reloadBtn  = document.getElementById('bundle-modal-reload');
  if (!overlay || !titleEl || !checkList || !checkPane || !configPane || !configFlds ||
      !progPane || !progList || !doneDiv || !confirmBtn || !configBack || !configConf ||
      !cancelBtn || !closeBtn || !reloadBtn) return; // Only present on admin/modules

  var currentModules   = [];
  var pendingConfigMods = [];  // modules that have install_settings

  function showPane(which) {
    checkPane.style.display  = which === 'check'    ? '' : 'none';
    configPane.style.display = which === 'configure' ? '' : 'none';
    progPane.style.display   = which === 'progress' ? '' : 'none';
  }

  function openModal(bundleName, modules) {
    currentModules = modules;
    titleEl.textContent = 'Install "' + bundleName + '"';

    checkList.innerHTML = '';
    modules.forEach(function(mod, i) {
      var isRequired  = !!mod.required;
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

    showPane('check');
    doneDiv.style.display = 'none';
    overlay.style.display = 'flex';
  }

  function buildConfigPane(selected) {
    pendingConfigMods = [];
    configFlds.innerHTML = '';

    currentModules.forEach(function(mod) {
      if (!selected.includes(mod.slug)) return;
      if (!mod.install_settings || !mod.install_settings.length) return;
      pendingConfigMods.push(mod);

      var section = document.createElement('div');
      section.style.cssText = 'border:1px solid var(--ps-border);border-radius:7px;padding:.875rem;';

      var heading = document.createElement('div');
      heading.style.cssText = 'font-size:.8125rem;font-weight:600;margin-bottom:.625rem;color:var(--ps-text-secondary);text-transform:uppercase;letter-spacing:.04em;';
      heading.textContent = mod.slug;
      section.appendChild(heading);

      mod.install_settings.forEach(function(field) {
        var grp = document.createElement('div');
        grp.style.cssText = 'margin-bottom:.5rem;';

        var lbl = document.createElement('label');
        lbl.style.cssText = 'display:block;font-size:.8125rem;margin-bottom:.25rem;font-weight:500;';
        lbl.textContent = field.label + (field.required ? ' *' : '');

        var inp = document.createElement('input');
        inp.className    = 'form-control form-control-sm';
        inp.type         = field.type || 'text';
        inp.name         = 'install_config[' + mod.slug + '][' + field.name + ']';
        inp.placeholder  = field.placeholder || field.default || '';
        inp.value        = field.default || '';
        inp.required     = !!field.required;

        grp.appendChild(lbl);
        grp.appendChild(inp);
        section.appendChild(grp);
      });

      configFlds.appendChild(section);
    });

    return pendingConfigMods.length > 0;
  }

  function closeModal() {
    overlay.style.display = 'none';
  }

  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  reloadBtn.addEventListener('click', function() { location.reload(); });
  configBack.addEventListener('click', function() { showPane('check'); });

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
    var hasConfig = buildConfigPane(selected);
    if (hasConfig) {
      showPane('configure');
    } else {
      runBundleInstall(selected, {});
    }
  });

  configConf.addEventListener('click', function() {
    var selected = [];
    checkList.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
      if (cb.checked) selected.push(cb.value);
    });
    // Collect config values from config pane inputs
    var config = {};
    configFlds.querySelectorAll('input[name^="install_config"]').forEach(function(inp) {
      // Parse install_config[slug][key]
      var m = inp.name.match(/^install_config\[([^\]]+)\]\[([^\]]+)\]$/);
      if (m) {
        if (!config[m[1]]) config[m[1]] = {};
        config[m[1]][m[2]] = inp.value;
      }
    });
    runBundleInstall(selected, config);
  });

  function runBundleInstall(slugs, config) {
    showPane('progress');
    progList.innerHTML  = '';
    doneDiv.style.display = 'none';

    var items = {};
    slugs.forEach(function(slug) {
      var row = document.createElement('div');
      row.className = 'vtx-bundle-progress-item pending';
      row.id = 'prog-' + slug;
      var icon = document.createElement('i');
      icon.className = 'pi pi-circle';
      var span = document.createElement('span');
      span.textContent = slug;
      row.appendChild(icon);
      row.appendChild(document.createTextNode(' '));
      row.appendChild(span);
      progList.appendChild(row);
      items[slug] = row;
    });

    var formData = new FormData();
    formData.append('csrf_token', overlay.dataset.csrf);
    slugs.forEach(function(s) { formData.append('modules[]', s); });

    // Append per-module install_config
    if (config && typeof config === 'object') {
      Object.keys(config).forEach(function(slug) {
        Object.keys(config[slug]).forEach(function(key) {
          formData.append('install_config[' + slug + '][' + key + ']', config[slug][key]);
        });
      });
    }

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

// -- Module tab: a la carte listeners ------------------------------------
function refreshPanels(onDone) {
  location.reload();
  if (onDone) onDone();
}

// -- A-la-carte configure modal ------------------------------------------
(function() {
  var overlay    = document.getElementById('mod-config-overlay');
  var titleEl    = document.getElementById('mod-config-title');
  var fieldsEl   = document.getElementById('mod-config-fields');
  var closeBtn   = document.getElementById('mod-config-close');
  var cancelBtn  = document.getElementById('mod-config-cancel');
  var confirmBtn = document.getElementById('mod-config-confirm');
  if (!overlay || !titleEl || !fieldsEl || !closeBtn || !cancelBtn || !confirmBtn) return; // Only present on admin/modules

  var pendingForm     = null;
  var pendingBtn      = null;
  var pendingSettings = [];

  window._openModConfigModal = function(name, settings, form, btn) {
    pendingForm     = form;
    pendingBtn      = btn;
    pendingSettings = settings;
    titleEl.textContent = 'Configure "' + name + '"';

    fieldsEl.innerHTML = '';
    settings.forEach(function(field) {
      var grp = document.createElement('div');
      var lbl = document.createElement('label');
      lbl.style.cssText = 'display:block;font-size:.8125rem;margin-bottom:.3rem;font-weight:500;';
      lbl.textContent = field.label + (field.required ? ' *' : '');
      var inp = document.createElement('input');
      inp.className   = 'form-control form-control-sm';
      inp.type        = field.type || 'text';
      inp.name        = 'install_config[' + field.name + ']';
      inp.placeholder = field.placeholder || field.default || '';
      inp.value       = field.default || '';
      inp.required    = !!field.required;
      grp.appendChild(lbl);
      grp.appendChild(inp);
      fieldsEl.appendChild(grp);
    });

    overlay.style.display = 'flex';
  };

  function closeConfigModal() { overlay.style.display = 'none'; }

  closeBtn.addEventListener('click', closeConfigModal);
  cancelBtn.addEventListener('click', closeConfigModal);
  overlay.addEventListener('click', function(e) { if (e.target === overlay) closeConfigModal(); });

  confirmBtn.addEventListener('click', function() {
    // Inject config as hidden inputs into the install form
    fieldsEl.querySelectorAll('input').forEach(function(inp) {
      var hidden = pendingForm.querySelector('input[name="' + inp.name + '"]');
      if (hidden) {
        hidden.value = inp.value;
      } else {
        hidden = document.createElement('input');
        hidden.type  = 'hidden';
        hidden.name  = inp.name;
        hidden.value = inp.value;
        pendingForm.appendChild(hidden);
      }
    });
    closeConfigModal();

    // Proceed with install
    pendingBtn.disabled  = true;
    pendingBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Installing...';
    window.VtxAjax.postForm(pendingForm.action, pendingForm, function(ok, res) {
      var msg     = (res && res.message) ? res.message : (ok ? 'Module installed.' : 'Installation failed.');
      var success = ok && res && res.success;
      if (success && res.setup_url) {
        window.Phuse.toast('Module installed! Opening setup wizard...', 'success');
        setTimeout(function() { window.location.href = (window.VTX_BASE_URL || '') + res.setup_url; }, 700);
      } else {
        window.Phuse.toast(msg, success ? 'success' : 'error');
        if (success) { refreshPanels(); }
        else {
          pendingBtn.disabled  = false;
          pendingBtn.innerHTML = '<i class="pi pi-download me-1"></i>Install';
        }
      }
    });
  });
}());

function attachInstallListeners() {
  document.querySelectorAll('.module-install-btn:not([data-wired])').forEach(function(btn) {
    btn.dataset.wired = '1';
    btn.addEventListener('click', function() {
      var name     = this.dataset.name;
      var form     = document.getElementById(this.dataset.form);
      var me       = this;
      var settings = [];
      try { settings = JSON.parse(this.dataset.settings || '[]'); } catch(e) {}

      // If module has install_settings, show configure modal first
      if (settings && settings.length) {
        window._openModConfigModal(name, settings, form, me);
        return;
      }

      window.vtxConfirmModal({
        title:        'Install Module',
        message:      'Install "' + name + '"? This will run the module\'s database migrations.',
        confirmLabel: 'Install',
        confirmClass: 'btn-primary',
        onConfirm: function() {
          me.disabled  = true;
          me.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

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
                me.innerHTML = '<i class="pi pi-download"></i>';
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

      me.disabled  = true;
      me.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

      window.VtxAjax.post(url, {csrf_token: csrf}, function(ok, res) {
        me.disabled = false;
        if (ok && res && res.success) {
          var badge   = document.getElementById('badge-' + slug);
          var enabled = res.status === 'enabled';
          badge.textContent = enabled ? 'Enabled' : 'Disabled';
          badge.className   = 'vtx-tag ' + (enabled ? 'success' : 'error') + ' module-status-badge';
          me.innerHTML         = '<i class="pi ' + (enabled ? 'pi-x-circle' : 'pi-check-circle') + '"></i>';
          me.className         = 'btn btn-sm module-toggle-btn ' + (enabled ? 'btn-outline-warning' : 'btn-outline-success');
          me.dataset.vtxTooltip = enabled ? 'Disable' : 'Enable';
        } else {
          me.innerHTML = '<i class="pi pi-alert-triangle"></i>';
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
          me.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

          window.VtxAjax.postForm(form.action, form, function(ok, res) {
            var msg = (res && res.message) ? res.message : (ok ? 'Module uninstalled.' : 'Uninstall failed.');
            window.Phuse.toast(msg, (ok && res && res.success) ? 'success' : 'error');
            if (ok && res && res.success) {
              refreshPanels();
            } else {
              me.disabled  = false;
              me.innerHTML = '<i class="pi pi-trash"></i>';
            }
          });
        }
      });
    });
  });
}

function attachUpdateListeners() {
  document.querySelectorAll('.module-update-btn:not([data-wired])').forEach(function(btn) {
    btn.dataset.wired = '1';
    btn.addEventListener('click', function() {
      var slug = this.dataset.slug;
      var name = this.dataset.name;
      var from = this.dataset.from;
      var to   = this.dataset.to;
      var form = document.getElementById(this.dataset.form);
      var me   = this;

      window.vtxConfirmModal({
        title:        'Update Module',
        message:      'Update "' + name + '" from v' + from + ' to v' + to + '? This will run the module\'s upgrade routine.',
        confirmLabel: 'Update',
        confirmClass: 'btn-warning',
        onConfirm: function() {
          me.disabled  = true;
          me.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Updating...';

          window.VtxAjax.postForm(form.action, form, function(ok, res) {
            var msg     = (res && res.message) ? res.message : (ok ? 'Module updated.' : 'Update failed.');
            var success = ok && res && res.success;
            window.Phuse.toast(msg, success ? 'success' : 'error');

            if (success) {
              var versionTag = document.getElementById('version-' + slug);
              if (versionTag) { versionTag.textContent = 'v' + res.version; }
              var badge = document.getElementById('update-badge-' + slug);
              if (badge) { badge.remove(); }
              me.remove();
            } else {
              me.disabled  = false;
              me.innerHTML = '<i class="pi pi-arrow-up me-1"></i>Update';
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
attachUpdateListeners();

// -- Bundle delete --------------------------------------------------------
document.querySelectorAll('.bundle-delete-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var name = this.dataset.name;
    var form = this.closest('.bundle-delete-form');
    window.vtxConfirmModal({
      title:        'Delete Bundle',
      message:      'Delete "' + name + '"? The bundle.json file will be removed. Installed modules are not affected.',
      confirmLabel: 'Delete',
      confirmClass: 'btn-danger',
      onConfirm: function() {
        window.VtxAjax.postForm(form.action, form, function(ok, res) {
          var msg = (res && res.message) ? res.message : (ok ? 'Bundle deleted.' : 'Delete failed.');
          window.Phuse.toast(msg, (ok && res && res.success) ? 'success' : 'error');
          if (ok && res && res.success) { location.reload(); }
        });
      }
    });
  });
});

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

// -- Marketplace: Install from URL ----------------------------------------
(function() {
  var overlay     = document.getElementById('url-modal-overlay');
  var stepInput   = document.getElementById('url-step-input');
  var stepVerify  = document.getElementById('url-step-verify');
  var urlInput    = document.getElementById('url-input');
  var fetchBtn    = document.getElementById('url-fetch-btn');
  var fetchErr    = document.getElementById('url-fetch-error');
  var installErr  = document.getElementById('url-install-error');
  var installBtn  = document.getElementById('url-install-confirm');
  var backBtn     = document.getElementById('url-verify-back');
  var trigBtn     = document.getElementById('url-install-btn');
  if (!overlay || !stepInput || !stepVerify || !urlInput || !fetchBtn || !installBtn || !trigBtn) return; // Only present on admin/modules
  var csrfToken   = overlay.dataset.csrf;

  function openModal() {
    urlInput.value           = '';
    fetchErr.style.display   = 'none';
    installErr.style.display = 'none';
    stepInput.style.display  = '';
    stepVerify.style.display = 'none';
    overlay.style.display    = 'flex';
    setTimeout(function() { urlInput.focus(); }, 50);
  }

  function closeModal() {
    overlay.style.display = 'none';
  }

  function showFetchError(msg) {
    fetchErr.textContent   = msg;
    fetchErr.style.display = '';
  }

  function showInstallError(msg) {
    installErr.textContent   = msg;
    installErr.style.display = '';
  }

  if (trigBtn) {
    trigBtn.addEventListener('click', openModal);
  }
  document.getElementById('url-modal-close').addEventListener('click', closeModal);
  document.getElementById('url-modal-cancel1').addEventListener('click', closeModal);
  backBtn.addEventListener('click', function() {
    stepVerify.style.display = 'none';
    stepInput.style.display  = '';
    installErr.style.display = 'none';
  });
  overlay.addEventListener('click', function(e) { if (e.target === overlay) closeModal(); });

  // Step 1: Download & Verify
  fetchBtn.addEventListener('click', function() {
    var url = urlInput.value.trim();
    if (!url) { showFetchError('Please enter a URL.'); return; }

    fetchErr.style.display = 'none';
    fetchBtn.disabled      = true;
    fetchBtn.innerHTML     = '<span class="spinner-border spinner-border-sm me-1"></span>Downloading...';

    var fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('url', url);

    fetch((window.VTX_BASE_URL || '') + '/admin/modules/fetch-url', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        fetchBtn.disabled  = false;
        fetchBtn.innerHTML = '<i class="pi pi-download me-1"></i>Download &amp; Verify';

        if (!res.success) {
          showFetchError(res.message || 'Download failed.');
          return;
        }

        // Populate verification step
        document.getElementById('url-mod-name').textContent    = res.name;
        document.getElementById('url-mod-slug').textContent    = res.slug;
        document.getElementById('url-mod-version').textContent = 'v' + res.version;
        document.getElementById('url-mod-desc').textContent    = res.description || '';
        document.getElementById('url-mod-hash').textContent    = res.hash;

        stepInput.style.display  = 'none';
        stepVerify.style.display = '';
      })
      .catch(function() {
        fetchBtn.disabled  = false;
        fetchBtn.innerHTML = '<i class="pi pi-download me-1"></i>Download &amp; Verify';
        showFetchError('Network error. Please try again.');
      });
  });

  // Step 2: Confirm Install
  installBtn.addEventListener('click', function() {
    installErr.style.display = 'none';
    installBtn.disabled      = true;
    installBtn.innerHTML     = '<span class="spinner-border spinner-border-sm me-1"></span>Installing...';

    var fd = new FormData();
    fd.append('csrf_token', csrfToken);

    fetch((window.VTX_BASE_URL || '') + '/admin/modules/install-from-url', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        installBtn.disabled  = false;
        installBtn.innerHTML = '<i class="pi pi-download me-1"></i>Install Module';

        if (res.success) {
          window.Phuse.toast(res.message || 'Module installed.', 'success');
          closeModal();
          setTimeout(function() { location.reload(); }, 800);
        } else {
          showInstallError(res.message || 'Installation failed.');
        }
      })
      .catch(function() {
        installBtn.disabled  = false;
        installBtn.innerHTML = '<i class="pi pi-download me-1"></i>Install Module';
        showInstallError('Network error. Please try again.');
      });
  });
}());
