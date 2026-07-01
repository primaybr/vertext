<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <a href="<?php echo $baseUrl; ?>/admin/forms" class="vtx-breadcrumb">
      <i class="pi pi-clipboard me-1"></i> Forms
    </a>
    <h1 class="vtx-page-title" style="margin-top:.25rem;">
      <i class="pi pi-inbox me-2 text-primary"></i><?php echo htmlspecialchars($form['name']); ?> - Submissions
    </h1>
  </div>
  <div style="display:flex;gap:.5rem;">
    <a href="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/builder"
       class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-sliders me-1"></i> Builder
    </a>
    <?php if (\App\CMS\Auth::can('forms.export') && !empty($subs)): ?>
    <a href="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions/export"
       class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-save me-1"></i> Export CSV
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Status filter tabs -->
<div class="vtx-panel mb-3">
  <div class="vtx-filter-tabs">
    <a href="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions"
       class="vtx-filter-tab <?php echo empty($status) ? 'active' : ''; ?>">
      All <span class="count"><?php echo (int) $total; ?></span>
    </a>
    <a href="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions?status=unread"
       class="vtx-filter-tab <?php echo $status === 'unread' ? 'active' : ''; ?>">
      Unread
    </a>
  </div>
</div>

<!-- Flash -->
<?php if (!empty($flash['message'])): ?>
<div class="vtx-alert vtx-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> mb-3">
  <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<div class="vtx-panel">
  <div class="vtx-panel-body p-0">
    <?php if (empty($subs)): ?>
    <div style="padding:3rem;text-align:center;color:var(--ps-text-muted);">
      <i class="pi pi-inbox pi-3x mb-3" style="opacity:.3;display:block;margin:0 auto 1rem;"></i>
      <p class="mb-0">No submissions yet.</p>
    </div>
    <?php else: ?>
    <table class="vtx-table">
      <thead>
        <tr>
          <th style="width:160px;">Submitted</th>
          <th>Preview</th>
          <th style="text-align:center;width:80px;">Status</th>
          <th style="text-align:right;width:80px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subs as $sub): ?>
        <tr <?php echo $sub['status'] === 'unread' ? 'style="font-weight:600;"' : ''; ?>>
          <td style="white-space:nowrap;font-size:.8125rem;color:var(--ps-text-muted);">
            <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($sub['submitted_at']))); ?>
          </td>
          <td>
            <a href="#" class="vtx-sub-preview-link"
               data-url="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions/<?php echo $sub['id']; ?>">
              <?php echo $sub['preview'] ? htmlspecialchars($sub['preview']) : '<span style="color:var(--ps-text-muted);">(empty)</span>'; ?>
            </a>
          </td>
          <td style="text-align:center;">
            <?php if ($sub['status'] === 'unread'): ?>
            <span class="badge badge-primary">Unread</span>
            <?php else: ?>
            <span class="badge badge-secondary">Read</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right;">
            <?php if (\App\CMS\Auth::can('forms.delete_submission')): ?>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    data-vtx-confirm="Delete this submission permanently?"
                    data-vtx-action="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions/<?php echo $sub['id']; ?>/delete"
                    data-vtx-csrf="<?php echo htmlspecialchars($csrf_token ?? ''); ?>"
                    title="Delete">
              <i class="pi pi-trash"></i>
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if (($pages ?? 1) > 1): ?>
    <div class="vtx-pagination" style="padding:.75rem 1rem;">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="?page=<?php echo $p; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?>"
         class="vtx-page-link <?php echo $p === ($page ?? 1) ? 'active' : ''; ?>">
        <?php echo $p; ?>
      </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Submission detail modal -->
<div id="vtx-sub-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1050;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:var(--ps-bg-base);border-radius:8px;width:100%;max-width:680px;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.2);">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:.875rem 1.25rem;border-bottom:1px solid var(--ps-border);flex-shrink:0;">
      <span style="font-weight:600;">Submission Detail</span>
      <button type="button" id="vtx-sub-modal-close" style="background:none;border:none;cursor:pointer;font-size:1.125rem;color:var(--ps-text-muted);line-height:1;">&times;</button>
    </div>
    <div id="vtx-sub-modal-body" style="overflow-y:auto;padding:1.25rem;flex:1;"></div>
  </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('vtx-sub-modal');
    var body    = document.getElementById('vtx-sub-modal-body');
    document.getElementById('vtx-sub-modal-close').addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    function close() { overlay.style.display = 'none'; body.innerHTML = ''; }
    document.querySelectorAll('.vtx-sub-preview-link').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            body.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--ps-text-muted);">Loading…</div>';
            overlay.style.display = 'flex';
            VtxAjax.get(a.dataset.url, function (ok, html) {
                body.innerHTML = ok ? html : '<p style="color:var(--ps-danger);padding:1rem;">Failed to load submission.</p>';
            });
        });
    });
}());
</script>
