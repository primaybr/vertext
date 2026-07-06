/* ── admin/webhooks/index.php: test-webhook button ── */
function vtxTestWebhook(id) {
    var btn = document.getElementById('test-btn-' + id);
    if (btn) { btn.disabled = true; btn.querySelector('i').className = 'pi pi-spin pi-refresh'; }
    var wrap = document.getElementById('webhooks-index-wrap');
    var csrfToken = wrap ? wrap.dataset.csrf : '';
    var baseUrl = wrap ? wrap.dataset.baseUrl : '';
    var fd = new FormData();
    fd.append('csrf_token', csrfToken);
    VtxAjax.postForm(baseUrl + '/admin/webhooks/' + id + '/test', fd, function (res) {
        if (btn) { btn.disabled = false; btn.querySelector('i').className = 'pi pi-refresh'; }
        Phuse.toast(res.message, res.success ? 'success' : 'error');
        if (res.success) setTimeout(function() { window.location.reload(); }, 1200);
    });
}
