(function () {
    'use strict';

    /* ── admin/pages/_form.php: slug + rich-text editor init ── */
    /* This form is loaded into the AJAX modal (admin.js dispatches
       'vtx:modal:loaded' after each load), so init must re-run every time. */
    document.addEventListener('vtx:modal:loaded', function (e) {
        var body = e.detail.body;
        if (!body.querySelector('#page-body-editor')) return;
        Vtx.load(['slug', 'editor'], function () {
            if (window.vtxSlug) window.vtxSlug.init();
            var editorEl = body.querySelector('#page-body-editor');
            var hiddenEl = body.querySelector('#page-body-hidden');
            if (editorEl && hiddenEl && window.VtxEditor) {
                var ed = new VtxEditor({ container: editorEl, textarea: hiddenEl });
                if (hiddenEl.value) ed.setHTML(hiddenEl.value);
            }
        });
    });

    /* ── admin/pages/_revision_diff.php: restore-revision confirm ── */
    /* Also modal-loaded content, so bind via the same event rather than
       a one-time getElementById at page-load time. */
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

    /* ── admin/pages/revisions.php: generic confirm-modal delegated handler ── */
    /* Full top-level page (not modal-loaded), so a plain document-level
       delegated listener bound once at page-load time is sufficient. */
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
})();
