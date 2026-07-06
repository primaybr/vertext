/* Vertext CMS - Admin JS */
(function () {
    'use strict';

    /* -- Theme ------------------------------------------------- */
    /* Unified storage key (0.0.9) - was 'phuse-theme'; migrate once so
       existing admins keep their preference. Front-end uses the same
       'vtx-theme' key, see scripts.js. */
    function readThemePreference() {
        var t = localStorage.getItem('vtx-theme');
        if (t) return t;
        var legacy = localStorage.getItem('phuse-theme');
        if (legacy) {
            localStorage.setItem('vtx-theme', legacy);
            localStorage.removeItem('phuse-theme');
        }
        return legacy;
    }

    function applyTheme() {
        var t = readThemePreference();
        if (t) document.documentElement.setAttribute('data-theme', t);
        syncThemeIcon();
    }

    function toggleTheme() {
        var cur  = document.documentElement.getAttribute('data-theme') || 'light';
        var next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('vtx-theme', next);
        syncThemeIcon();
    }

    function syncThemeIcon() {
        var el = document.getElementById('theme-icon');
        if (!el) return;
        var t = document.documentElement.getAttribute('data-theme') || 'light';
        el.className = t === 'dark' ? 'pi pi-sun' : 'pi pi-moon';
    }

    /* -- Sidebar ----------------------------------------------- */
    function initSidebar() {
        var toggle  = document.getElementById('sidebar-toggle');
        var sidebar = document.getElementById('vtx-sidebar');
        var overlay = document.getElementById('vtx-overlay');

        function open()  { sidebar && sidebar.classList.add('open');    overlay && overlay.classList.add('show'); }
        function close() { sidebar && sidebar.classList.remove('open'); overlay && overlay.classList.remove('show'); }

        if (toggle)  toggle.addEventListener('click', function () { sidebar.classList.contains('open') ? close() : open(); });
        if (overlay) overlay.addEventListener('click', close);

        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    }

    /* -- Nav Group (subnav toggle) ---------------------------- */
    function initNavGroups() {
        document.querySelectorAll('.vtx-nav-group-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var group = btn.closest('.vtx-nav-group');
                if (!group) return;
                var isOpen = group.classList.toggle('open');
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });
    }

    /* -- User Dropdown ----------------------------------------- */
    function initUserMenu() {
        var trigger = document.getElementById('user-menu-trigger');
        var menu    = document.getElementById('user-menu');
        if (!trigger || !menu) return;

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            menu.classList.toggle('open');
        });

        document.addEventListener('click', function () { menu.classList.remove('open'); });
    }

    /* -- AJAX -------------------------------------------------- */
    window.VtxAjax = {
        // POST JSON body (for simple payloads: module toggle, DB test)
        post: function (url, data, cb) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                var res;
                try { res = JSON.parse(xhr.responseText); } catch (e) { res = {}; }
                cb(xhr.status >= 200 && xhr.status < 300, res, xhr.status);
            };
            xhr.send(JSON.stringify(data));
        },

        // POST FormData (for CRUD forms: checkboxes, selects, file inputs)
        postForm: function (url, formEl, cb) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                var res;
                try { res = JSON.parse(xhr.responseText); } catch (e) { res = {}; }
                cb(xhr.status >= 200 && xhr.status < 300, res, xhr.status);
            };
            xhr.send(new FormData(formEl));
        },

        // GET (for loading form partials into the CRUD modal)
        get: function (url, cb) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                cb(xhr.status >= 200 && xhr.status < 300, xhr.responseText, xhr.status);
            };
            xhr.send();
        }
    };

    /* -- Vtx Component Loader ---------------------------------- */
    window.Vtx = (function () {
        var BASE     = (window.VTX_ASSETS_URL || '') + 'js/components/';
        var VERSIONS = { search: 1, datatable: 2, select: 1, editor: 2, tags: 3, chart: 1, upload: 1, 'media-picker': 2, slug: 1 };
        var _loaded    = {};
        var _instances = {};

        function loadScript(name, cb) {
            if (_loaded[name]) { cb && cb(); return; }
            var s = document.createElement('script');
            s.src = BASE + 'vtx-' + name + '.js?v=' + (VERSIONS[name] || 1);
            s.onload = function () { _loaded[name] = true; cb && cb(); };
            document.head.appendChild(s);
        }

        return {
            VERSIONS: VERSIONS,

            // Dynamically load one or more components; calls cb when all are ready
            load: function (names, cb) {
                var pending = names.length;
                if (pending === 0) { cb && cb(); return; }
                names.forEach(function (name) {
                    loadScript(name, function () {
                        if (--pending === 0 && cb) cb();
                    });
                });
            },

            // Scan DOM and load only the components needed on this page
            autoInit: function () {
                var needed = [];
                if (document.querySelector('[data-vtx-search]'))        needed.push('search');
                if (document.querySelector('[data-vtx-table]'))         needed.push('datatable');
                if (document.querySelector('[data-vtx-select]'))        needed.push('select');
                if (document.querySelector('[data-vtx-editor]'))        needed.push('editor');
                if (document.querySelector('[data-vtx-tags]'))          needed.push('tags');
                if (document.querySelector('canvas[data-vtx-chart]'))   needed.push('chart');
                if (document.querySelector('[data-vtx-upload]'))        needed.push('upload');
                if (document.querySelector('[data-vtx-media-picker]'))  needed.push('media-picker');
                if (needed.length) this.load(needed);
            },

            // Called by each component to register its instance
            _register: function (type, instance) {
                if (!_instances[type]) _instances[type] = [];
                _instances[type].push(instance);
            },

            // Retrieve a registered instance; el is optional (returns first match)
            getInstance: function (type, el) {
                var list = _instances[type] || [];
                if (el) return list.filter(function (i) { return i.el === el; })[0] || null;
                return list[0] || null;
            },

            _instances: _instances
        };
    }());

    /* -- Shared modal helpers ---------------------------------- */
    function showBackdrop() {
        var bd = document.getElementById('vtx-modal-backdrop');
        if (!bd) return;
        bd.style.display = 'block';
        setTimeout(function () { bd.classList.add('show'); }, 10);
    }

    function hideBackdrop() {
        var bd = document.getElementById('vtx-modal-backdrop');
        if (!bd) return;
        bd.classList.remove('show');
        setTimeout(function () { bd.style.display = 'none'; }, 200);
    }

    /* -- Confirm Modal ----------------------------------------- */
    window.vtxModalClose = function () {
        var modal = document.getElementById('vtx-confirm-modal');
        if (modal) modal.classList.remove('show');
        if (!document.getElementById('vtx-form-modal')?.classList.contains('show')) {
            hideBackdrop();
            document.body.style.overflow = '';
        }
    };

    window.vtxConfirmModal = function (opts) {
        var modal   = document.getElementById('vtx-confirm-modal');
        var titleEl = document.getElementById('vtx-confirm-title');
        var msgEl   = document.getElementById('vtx-confirm-message');
        var inputEl = document.getElementById('vtx-confirm-input');
        var okBtn   = document.getElementById('vtx-modal-confirm');
        if (!modal) { if (typeof opts.onConfirm === 'function') opts.onConfirm(); return; }

        if (titleEl) titleEl.textContent = opts.title   || 'Confirm';
        if (msgEl)   msgEl.textContent   = opts.message || 'Are you sure?';
        if (inputEl) inputEl.style.display = 'none';
        if (okBtn) {
            okBtn.textContent = opts.confirmLabel || 'Confirm';
            okBtn.className   = 'btn btn-sm ' + (opts.confirmClass || 'btn-danger');
            okBtn.onclick     = function () {
                window.vtxModalClose();
                if (typeof opts.onConfirm === 'function') opts.onConfirm();
            };
        }

        showBackdrop();
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    };

    /* -- Prompt Modal ------------------------------------------- */
    /* Modal-based replacement for window.prompt(). Reuses the confirm
       modal's markup with its text input shown. opts.onConfirm receives
       the trimmed input value; empty submissions are rejected with a
       toast and the modal stays open so the user can retry. */
    window.vtxPromptModal = function (opts) {
        var modal   = document.getElementById('vtx-confirm-modal');
        var titleEl = document.getElementById('vtx-confirm-title');
        var msgEl   = document.getElementById('vtx-confirm-message');
        var inputEl = document.getElementById('vtx-confirm-input');
        var okBtn   = document.getElementById('vtx-modal-confirm');
        if (!modal || !inputEl) {
            var v = window.prompt(opts.message || '', opts.value || '');
            if (v !== null && typeof opts.onConfirm === 'function') opts.onConfirm(v.trim());
            return;
        }

        if (titleEl) titleEl.textContent = opts.title   || 'Enter a value';
        if (msgEl)   msgEl.textContent   = opts.message || '';
        inputEl.style.display = '';
        inputEl.value          = opts.value       || '';
        inputEl.placeholder    = opts.placeholder || '';

        function submit() {
            var val = inputEl.value.trim();
            if (!val) {
                Phuse.toast('This field is required.', 'error');
                inputEl.focus();
                return;
            }
            window.vtxModalClose();
            if (typeof opts.onConfirm === 'function') opts.onConfirm(val);
        }

        if (okBtn) {
            okBtn.textContent = opts.confirmLabel || 'Save';
            okBtn.className   = 'btn btn-sm ' + (opts.confirmClass || 'btn-primary');
            okBtn.onclick     = submit;
        }
        inputEl.onkeydown = function (e) { if (e.key === 'Enter') { e.preventDefault(); submit(); } };

        showBackdrop();
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { inputEl.focus(); inputEl.select(); }, 50);
    };

    function initConfirmModal() {
        var closeBtn  = document.getElementById('vtx-modal-close');
        var cancelBtn = document.getElementById('vtx-modal-cancel');
        var backdrop  = document.getElementById('vtx-modal-backdrop');

        if (closeBtn)  closeBtn.addEventListener('click',  window.vtxModalClose);
        if (cancelBtn) cancelBtn.addEventListener('click', window.vtxModalClose);
        if (backdrop)  backdrop.addEventListener('click',  function () {
            window.vtxModalClose();
            window.vtxFormModalClose();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { window.vtxModalClose(); window.vtxFormModalClose(); }
        });

        // [data-confirm-form] buttons - show confirm modal, then submit or AJAX-delete
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-confirm-form]');
            if (!btn) return;
            e.preventDefault();
            var isAjax = btn.dataset.confirmAjax === 'true';
            window.vtxConfirmModal({
                title:        btn.dataset.confirmTitle   || 'Confirm',
                message:      btn.dataset.confirmMessage || 'Are you sure?',
                confirmLabel: btn.dataset.confirmLabel   || 'Confirm',
                confirmClass: btn.dataset.confirmClass   || 'btn-danger',
                onConfirm: function () {
                    var f = document.getElementById(btn.dataset.confirmForm);
                    if (!f) return;
                    if (isAjax) {
                        VtxAjax.postForm(f.action, f, function (ok, res) {
                            var msg = (res && res.message) ? res.message : (ok ? 'Done.' : 'An error occurred.');
                            Phuse.toast(msg, ok && res && res.success ? 'success' : 'error');
                            if (ok && res && res.success) {
                                var row = btn.closest('tr');
                                if (row) row.remove();
                                document.dispatchEvent(new CustomEvent('vtx:crud:success', { detail: { action: 'delete' } }));
                            }
                        });
                    } else {
                        f.submit();
                    }
                }
            });
        });
    }

    /* -- CRUD Form Modal --------------------------------------- */
    window.vtxFormModalClose = function () {
        var modal = document.getElementById('vtx-form-modal');
        if (modal) modal.classList.remove('show');
        if (!document.getElementById('vtx-confirm-modal')?.classList.contains('show')) {
            hideBackdrop();
            document.body.style.overflow = '';
        }
    };

    function runScriptsIn(container) {
        container.querySelectorAll('script').forEach(function (oldScript) {
            var newScript = document.createElement('script');
            newScript.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    function initPasswordTogglesIn(container) {
        container.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = document.getElementById(btn.dataset.pwToggle);
                if (!target) return;
                var isText = target.type === 'text';
                target.type = isText ? 'password' : 'text';
                var icon = btn.querySelector('.pi');
                if (icon) icon.className = isText ? 'pi pi-eye' : 'pi pi-eye-off';
            });
        });
    }

    function initFormModal() {
        var closeBtn = document.getElementById('vtx-form-modal-close');
        if (closeBtn) closeBtn.addEventListener('click', window.vtxFormModalClose);

        // [data-form-url] triggers: load partial via GET and display in modal
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-form-url]');
            if (!btn) return;
            e.preventDefault();

            var url     = btn.dataset.formUrl;
            var title   = btn.dataset.formTitle || '';
            var size    = btn.dataset.formSize  || 'modal-lg';
            var dialog  = document.getElementById('vtx-form-modal-dialog');
            var titleEl = document.getElementById('vtx-form-modal-title');
            var body    = document.getElementById('vtx-form-modal-body');
            var modal   = document.getElementById('vtx-form-modal');

            if (dialog)  dialog.className   = 'modal-dialog ' + size;
            if (titleEl) titleEl.textContent = title;
            if (body)    body.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--ps-text-muted);">Loading…</div>';

            showBackdrop();
            if (modal) modal.classList.add('show');
            document.body.style.overflow = 'hidden';

            VtxAjax.get(url, function (ok, html) {
                if (!body) return;
                if (!ok) {
                    body.innerHTML = '<p style="padding:1rem;color:var(--ps-danger);">Failed to load form.</p>';
                    return;
                }
                body.innerHTML = html;
                runScriptsIn(body);
                initPasswordTogglesIn(body);
                document.dispatchEvent(new CustomEvent('vtx:modal:loaded', { detail: { body: body } }));
                // Auto-enhance any [data-vtx-select] elements inside the loaded form
                if (body.querySelector('[data-vtx-select]')) {
                    Vtx.load(['select'], function () {
                        body.querySelectorAll('[data-vtx-select]').forEach(function (el) {
                            if (!el._vtxSelect) new Vtx.Select({ el: el });
                        });
                    });
                }
            });
        });

        // Delegated submit for [data-crud-form] forms loaded into the modal body
        document.addEventListener('submit', function (e) {
            var form = e.target.closest('[data-crud-form]');
            if (!form) return;
            e.preventDefault();

            var submitBtn   = form.querySelector('[type=submit]');
            var originalTxt = submitBtn ? submitBtn.textContent : '';
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving…'; }

            VtxAjax.postForm(form.action, form, function (ok, res) {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalTxt; }
                var msg = (res && res.message) ? res.message : (ok ? 'Saved.' : 'An error occurred.');
                if (ok && res && res.success) {
                    window.vtxFormModalClose();
                    Phuse.toast(msg, 'success');
                    document.dispatchEvent(new CustomEvent('vtx:crud:success', { detail: { action: 'save' } }));
                    if (res.redirect) { window.location.href = res.redirect; return; }
                    // Prefer VtxDataTable.reload() when a table is registered; else DOMParser fallback
                    var dt = Vtx.getInstance('table');
                    if (dt) {
                        dt.reload();
                    } else {
                        VtxAjax.get(window.location.href, function (ok2, html) {
                            if (!ok2) return;
                            var doc     = new DOMParser().parseFromString(html, 'text/html');
                            var newBody = doc.querySelector('table.vtx-table tbody');
                            var curBody = document.querySelector('table.vtx-table tbody');
                            if (newBody && curBody) {
                                curBody.innerHTML = newBody.innerHTML;
                            } else {
                                // Empty state → first item: no table in DOM yet; swap identified panels
                                document.querySelectorAll('[id].vtx-panel').forEach(function (panel) {
                                    var fresh = doc.getElementById(panel.id);
                                    if (fresh) panel.innerHTML = fresh.innerHTML;
                                });
                            }
                        });
                    }
                } else {
                    Phuse.toast(msg, 'error');
                }
            });
        });
    }

    /* -- AJAX Panel Navigation --------------------------------- */
    // Fetches `url`, replaces #panelId, refreshes filter tabs and any
    // [data-ajax-refreshable] panels, re-inits VtxDataTable, fires
    // vtx:panel:replaced so pages can re-attach row-level listeners.
    window.vtxAjaxNav = function (url, panelId, opts) {
        var panel = document.getElementById(panelId);
        if (!panel) { window.location.href = url; return; }

        opts = opts || {};
        panel.classList.add('vtx-loading');

        var fetchUrl = url + (url.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now();
        VtxAjax.get(fetchUrl, function (ok, html) {
            panel.classList.remove('vtx-loading');
            if (!ok) return;

            var doc = new DOMParser().parseFromString(html, 'text/html');

            // Refresh filter tabs if present
            var freshTabs = doc.querySelector('.vtx-filter-tabs');
            var curTabs   = document.querySelector('.vtx-filter-tabs');
            if (freshTabs && curTabs) curTabs.innerHTML = freshTabs.innerHTML;

            // Refresh any panel that opted in to refresh on AJAX nav
            document.querySelectorAll('[data-ajax-refreshable]').forEach(function (el) {
                if (!el.id) return;
                var fresh = doc.getElementById(el.id);
                if (fresh) el.innerHTML = fresh.innerHTML;
            });

            // Replace the primary target panel
            var freshPanel = doc.getElementById(panelId);
            if (!freshPanel) return;

            if (Vtx._instances['table']) Vtx._instances['table'] = [];
            panel.innerHTML = freshPanel.innerHTML;

            var newTbl = panel.querySelector('[data-vtx-table]');
            if (newTbl) {
                Vtx.load(['datatable'], function () {
                    if (window.VtxDataTable) new VtxDataTable({ el: newTbl });
                });
            }

            document.dispatchEvent(new CustomEvent('vtx:panel:replaced', {
                detail: { panelId: panelId }
            }));

            if (!opts.silent) history.pushState(null, '', url);
        });
    };

    function initAjaxNav() {
        // Form submit → AJAX nav
        document.addEventListener('submit', function (e) {
            var form    = e.target;
            var panelId = form.dataset.ajaxPanel;
            if (!panelId) return;
            e.preventDefault();

            var params = new URLSearchParams();
            new FormData(form).forEach(function (v, k) {
                if (v !== '') params.append(k, v);
            });
            var qs  = params.toString();
            var url = form.action + (qs ? '?' + qs : '');
            window.vtxAjaxNav(url, panelId);
        });

        // Link click → AJAX nav
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[data-ajax-panel]');
            if (!link) return;
            e.preventDefault();
            window.vtxAjaxNav(link.href, link.dataset.ajaxPanel);
        });

        // Browser back/forward: full reload to sync URL with content
        window.addEventListener('popstate', function () { window.location.reload(); });
    }

    /* -- AJAX Forms (e.g. Settings save) ---------------------- */
    function initAjaxForms() {
        document.addEventListener('submit', function (e) {
            var form = e.target.closest('[data-ajax-form]');
            if (!form) return;
            e.preventDefault();

            var submitBtn   = form.querySelector('[type=submit]');
            var originalTxt = submitBtn ? submitBtn.textContent : '';
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving…'; }

            VtxAjax.postForm(form.action, form, function (ok, res) {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalTxt; }
                var msg = (res && res.message) ? res.message : (ok ? 'Saved.' : 'An error occurred.');
                Phuse.toast(msg, ok && res && res.success ? 'success' : 'error');
                if (ok && res && res.success) {
                    var panelId = form.dataset.ajaxPanel;
                    if (panelId && window.vtxAjaxNav) window.vtxAjaxNav(window.location.href, panelId, { silent: true });
                }
            });
        });
    }

    /* -- Setup Wizard ------------------------------------------ */
    function initSetup() {
        var form = document.getElementById('setup-form');
        if (!form) return;

        var testBtn = document.getElementById('test-db-btn');
        if (testBtn) {
            testBtn.addEventListener('click', function () {
                testBtn.disabled = true;
                testBtn.textContent = 'Testing…';

                var payload = {
                    host:     document.getElementById('db_host')     ? document.getElementById('db_host').value     : '',
                    port:     document.getElementById('db_port')     ? document.getElementById('db_port').value     : '',
                    database: document.getElementById('db_name')     ? document.getElementById('db_name').value     : '',
                    username: document.getElementById('db_user')     ? document.getElementById('db_user').value     : '',
                    password: document.getElementById('db_pass')     ? document.getElementById('db_pass').value     : ''
                };

                VtxAjax.post(form.dataset.testUrl || '/setup/test-db', payload, function (ok, res) {
                    testBtn.disabled = false;
                    testBtn.textContent = 'Test Connection';
                    var good = ok && res && res.success;
                    var type = good ? (res.exists ? 'success' : 'info') : 'error';
                    var msg  = good ? res.message : (res && res.message ? res.message : 'Connection failed');
                    Phuse.toast(msg, type);
                });
            });
        }
    }

    /* -- Toggle Password Visibility --------------------------- */
    function initPasswordToggle() {
        initPasswordTogglesIn(document);
    }

    /* -- Public API (called by partial refreshes e.g. module manager nav swap) */
    window.vtxInitNavGroups = initNavGroups;

    /* -- Init -------------------------------------------------- */
    applyTheme();

    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initNavGroups();
        initUserMenu();
        initConfirmModal();
        initFormModal();
        initAjaxForms();
        initAjaxNav();
        initSetup();
        initPasswordToggle();
        syncThemeIcon();

        var themeBtn = document.getElementById('theme-toggle');
        if (themeBtn) themeBtn.addEventListener('click', toggleTheme);

        Vtx.autoInit();
    });

}());
