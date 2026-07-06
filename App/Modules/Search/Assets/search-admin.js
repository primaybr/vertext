/* ── admin/search/index.php: reindex button ── */
document.getElementById('reindex-form') && document.getElementById('reindex-form').addEventListener('submit', function(e) {
  e.preventDefault();
  var btn = document.getElementById('reindex-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="pi pi-refresh me-1"></i> Indexing...';
  window.VtxAjax.postForm(this.action, this, function(ok, res) {
    btn.disabled = false;
    btn.innerHTML = '<i class="pi pi-refresh me-1"></i> Reindex Now';
    window.Phuse.toast(res && res.message ? res.message : (ok ? 'Done.' : 'Failed.'),
      ok && res && res.success ? 'success' : 'error');
  });
});
