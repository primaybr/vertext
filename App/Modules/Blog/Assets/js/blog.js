/* Blog Module - Admin JS: bulk post actions */
(function () {
    var bulkBar   = document.getElementById('vtx-bulk-bar');
    var bulkCount = document.getElementById('vtx-bulk-count');

    // Only active on the posts list page
    if (!bulkBar) return;

    function getChecked() {
        return document.querySelectorAll('.vtx-row-check:checked');
    }

    function updateBulkBar() {
        var n = getChecked().length;
        bulkBar.style.display = n > 0 ? 'flex' : 'none';
        if (bulkCount) bulkCount.textContent = n + ' selected';
    }

    function initCheckboxes() {
        var allCheck = document.getElementById('vtx-check-all');
        if (allCheck) {
            allCheck.addEventListener('change', function () {
                document.querySelectorAll('.vtx-row-check').forEach(function (cb) {
                    cb.checked = allCheck.checked;
                });
                updateBulkBar();
            });
        }
        document.querySelectorAll('.vtx-row-check').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var all = document.querySelectorAll('.vtx-row-check');
                if (allCheck) allCheck.checked = getChecked().length === all.length;
                updateBulkBar();
            });
        });
    }

    initCheckboxes();

    // Re-init after AJAX panel replacement (filter tab navigation)
    document.addEventListener('vtx:panel:replaced', function (e) {
        if (e.detail && e.detail.panelId === 'posts-table-panel') {
            bulkBar   = document.getElementById('vtx-bulk-bar');
            bulkCount = document.getElementById('vtx-bulk-count');
            initCheckboxes();
        }
    });

    // Refresh table after any CRUD operation
    document.addEventListener('vtx:crud:success', function () {
        if (!document.getElementById('posts-table-panel')) return;
        if (window.vtxAjaxNav) window.vtxAjaxNav(window.location.href, 'posts-table-panel', { silent: true });
    });

    window.vtxBulkSubmit = function (action) {
        var checked = getChecked();
        if (!checked.length) return;
        var form        = document.getElementById('vtx-bulk-form');
        var actionInput = document.getElementById('vtx-bulk-action');
        if (!form || !actionInput) return;
        form.querySelectorAll('[name="ids[]"]').forEach(function (el) { el.remove(); });
        checked.forEach(function (cb) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = cb.value;
            form.appendChild(inp);
        });
        actionInput.value = action;
        VtxAjax.postForm(form.action, form, function (ok, res) {
            var msg = (res && res.message) ? res.message : (ok ? 'Done.' : 'An error occurred.');
            Phuse.toast(msg, ok && res && res.success ? 'success' : 'error');
            if (ok && res && res.success) location.reload();
        });
    };

    window.vtxBulkConfirmDelete = function () {
        var n = getChecked().length;
        if (!n) return;
        vtxConfirmModal({
            title:        'Delete Posts',
            message:      'Move ' + n + ' selected post(s) to trash?',
            confirmLabel: 'Delete',
            confirmClass: 'btn-danger',
            onConfirm:    function () { window.vtxBulkSubmit('delete'); }
        });
    };
}());

/* -- admin/posts/_revision_diff.php: restore-revision confirm -- */
/* Modal-loaded content, so bind via the 'vtx:modal:loaded' event admin.js
   dispatches after each AJAX modal load, rather than a one-time getElementById. */
document.addEventListener('vtx:modal:loaded', function (e) {
    var body = e.detail.body;
    var btn  = body.querySelector('#vtx-diff-restore-btn');
    var form = body.querySelector('#vtx-diff-restore-form');
    if (!btn || !form) return;
    btn.addEventListener('click', function () {
        var me = btn;
        window.vtxConfirmModal({
            title:        'Restore Revision',
            message:      'Restore this revision? The current version will be saved as a new revision first.',
            confirmLabel: 'Restore',
            confirmClass: 'btn-primary',
            onConfirm: function () {
                me.disabled = true;
                window.VtxAjax.postForm(form.action, form, function (ok, res) {
                    window.Phuse.toast(
                        res && res.message ? res.message : (ok ? 'Done.' : 'Failed.'),
                        ok && res && res.success ? 'success' : 'error'
                    );
                    if (ok && res && res.success) {
                        setTimeout(function () {
                            if (window.vtxFormModalClose) window.vtxFormModalClose();
                            location.reload();
                        }, 600);
                    } else {
                        me.disabled = false;
                    }
                });
            }
        });
    });
});

/* -- admin/posts/revisions.php: generic confirm-modal delegated handler -- */
/* Full top-level page, plain document-level delegation is sufficient. */
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-confirm-form][data-confirm-ajax="true"]');
    if (!btn) return;
    var form = document.getElementById(btn.dataset.confirmForm);
    if (!form) return;
    window.vtxConfirmModal({
        title:        btn.dataset.confirmTitle,
        message:      btn.dataset.confirmMessage,
        confirmLabel: btn.dataset.confirmLabel,
        confirmClass: btn.dataset.confirmClass,
        onConfirm: function () {
            btn.disabled = true;
            window.VtxAjax.postForm(form.action, form, function (ok, res) {
                window.Phuse.toast(res && res.message ? res.message : (ok ? 'Done.' : 'Failed.'),
                    ok && res && res.success ? 'success' : 'error');
                if (ok && res && res.success) setTimeout(function () { location.reload(); }, 600);
                else btn.disabled = false;
            });
        }
    });
});

/* -- admin/posts/_form.php: slug, featured-image remove, char counters, editor/tags/media-picker init -- */
/* Modal-loaded content, re-runs each time via 'vtx:modal:loaded'. */
document.addEventListener('vtx:modal:loaded', function (e) {
    var body = e.detail.body;
    var form = body.querySelector('#post-editor-form');
    if (!form) return;

    Vtx.load(['slug'], function () {
        if (window.vtxSlug) window.vtxSlug.init();
    });

    var removeBtn = body.querySelector('#post-featured-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            body.querySelector('#post-img-id').value  = '';
            body.querySelector('#post-img-url').value = '';
            var wrap = body.querySelector('#post-featured-preview');
            if (wrap) wrap.style.display = 'none';
        });
    }

    function initCharCounter(inputId, max) {
        var el  = body.querySelector('#' + inputId);
        var ctr = body.querySelector('.vtx-char-count[data-target="' + inputId + '"]');
        if (!el || !ctr) return;
        function upd() {
            var n = el.value.length;
            ctr.textContent = n + ' / ' + max;
            ctr.style.color = n > max ? 'var(--ps-danger)' : 'var(--ps-text-muted)';
        }
        el.addEventListener('input', upd); upd();
    }
    initCharCounter('post-meta-title', 60);
    initCharCounter('post-meta-desc', 160);

    var mediaEnabled = form.dataset.mediaEnabled === '1';
    var _editorComponents = ['editor', 'tags'].concat(mediaEnabled ? ['media-picker'] : []);
    Vtx.load(_editorComponents, function () {
        var editorEl = body.querySelector('#post-body-editor');
        var hiddenEl = body.querySelector('#post-body-hidden');
        if (editorEl && hiddenEl && window.VtxEditor) {
            var vtxEd = new VtxEditor({
                container:   editorEl,
                textarea:    hiddenEl,
                mediaPicker: mediaEnabled,
                onWordCount: function (words) {
                    var mins = Math.max(1, Math.round(words / 200));
                    var rt = body.querySelector('#post-reading-time');
                    if (rt) rt.value = mins;
                    var lbl = body.querySelector('#post-read-time-label');
                    if (lbl) lbl.textContent = mins + ' min read · ' + words + ' words';
                }
            });
            if (hiddenEl.value) vtxEd.setHTML(hiddenEl.value);
        }

        var tagsEl = body.querySelector('[data-vtx-tags]');
        if (tagsEl && window.VtxTags) new VtxTags({ el: tagsEl });

        if (mediaEnabled) {
            var pickerBtn = body.querySelector('[data-vtx-media-picker]');
            if (pickerBtn && window.VtxMediaPicker) new VtxMediaPicker({ btn: pickerBtn });
        }
    });
});

/* -- admin/series/_form.php: auto-slug + order-input enable/disable -- */
/* Modal-loaded content, re-runs each time via 'vtx:modal:loaded'. */
document.addEventListener('vtx:modal:loaded', function (e) {
    var body    = e.detail.body;
    var titleEl = body.querySelector('#series-title');
    var slugEl  = body.querySelector('#series-slug');
    if (!titleEl || !slugEl) return;

    if (!slugEl.value) {
        titleEl.addEventListener('input', function () {
            slugEl.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        });
    }

    body.querySelectorAll('.series-post-check').forEach(function (cb) {
        var row = cb.closest('tr');
        var num = row ? row.querySelector('input[type="number"]') : null;
        cb.addEventListener('change', function () {
            if (num) num.disabled = !this.checked;
        });
    });
});

/* -- admin/settings/index.php: base-path change warning banner -- */
(function () {
    var pathInput    = document.getElementById('blog_base_path');
    var warning      = document.getElementById('blog-path-seo-warning');
    var oldPathLabel = document.getElementById('old-path-label');
    if (!pathInput) return;
    var originalPath = pathInput.value.trim();

    function checkChange() {
        if (!warning) return;
        var current = pathInput.value.trim().replace(/^\/+|\/+$/g, '');
        var changed = current !== originalPath;
        warning.style.display = changed ? '' : 'none';
        if (changed && oldPathLabel) {
            oldPathLabel.textContent = originalPath ? '/' + originalPath : '/';
        }
    }
    pathInput.addEventListener('input', checkChange);
})();

/* -- admin/setup/index.php: live URL-preview updater -- */
(function () {
    var input   = document.getElementById('blog_base_path');
    var preview = document.getElementById('path-preview-val');
    if (!input || !preview) return;

    function update() {
        var val = (input.value || '').trim().replace(/^\/+|\/+$/g, '');
        preview.textContent = val ? window.location.origin + '/' + val + '/' : window.location.origin + '/';
    }
    input.addEventListener('input', update);
    update();
})();
