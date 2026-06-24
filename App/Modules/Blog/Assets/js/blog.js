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
