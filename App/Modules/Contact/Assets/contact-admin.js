/* ── admin/contact/index.php: delete-submission AJAX handler ── */
document.querySelectorAll('[data-action="delete"]').forEach(btn => {
    btn.addEventListener('click', function () {
        if (!confirm('Delete this submission?')) return;
        const id = this.dataset.id;
        const baseUrl = this.dataset.baseUrl;
        fetch(`${baseUrl}/admin/contact/${id}/delete`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'csrf_token=' + encodeURIComponent(this.dataset.csrf)
        })
        .then(r => r.json())
        .then(d => { if (d.success) this.closest('tr').remove(); else Phuse.toast(d.message, 'error'); })
        .catch(() => Phuse.toast('Request failed.', 'error'));
    });
});

/* ── admin/contact/settings.php: toggle auto-reply message field visibility ── */
document.getElementById('autoReply')?.addEventListener('change', function () {
    document.getElementById('autoReplyMsg').style.display = this.checked ? '' : 'none';
});

/* ── admin/contact/view.php: delete/spam/mark-read actions ── */
(function () {
    const actions = document.getElementById('contactItemActions');
    if (!actions) return;

    const id = actions.dataset.id;
    const token = actions.dataset.csrf;
    const base = actions.dataset.baseUrl;

    function doAction(action, cb) {
        fetch(`${base}/admin/contact/${id}/${action}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'csrf_token=' + encodeURIComponent(token)
        })
        .then(r => r.json())
        .then(d => { if (d.success) cb(); else Phuse.toast(d.message, 'error'); })
        .catch(() => Phuse.toast('Request failed.', 'error'));
    }

    document.getElementById('btnDelete')?.addEventListener('click', function () {
        if (!confirm('Permanently delete this submission?')) return;
        doAction('delete', () => { window.location.href = base + '/admin/contact'; });
    });

    document.getElementById('btnSpam')?.addEventListener('click', function () {
        doAction('mark-spam', () => { this.remove(); });
    });

    document.getElementById('btnRead')?.addEventListener('click', function () {
        doAction('mark-read', () => { this.remove(); });
    });
})();
