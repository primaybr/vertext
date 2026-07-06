<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-inbox me-2 text-primary"></i>All Submissions</h1>
    <p class="vtx-page-desc">View submissions across all forms.</p>
  </div>
</div>

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
          <th style="width:180px;">Form</th>
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
            <a href="<?php echo $baseUrl; ?>/admin/forms/<?php echo $sub['form_id']; ?>/submissions">
              <?php echo htmlspecialchars($sub['form_name'] ?? '(unknown)'); ?>
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
            <button type="button" class="btn btn-sm btn-outline-secondary vtx-sub-preview-link"
                    data-url="<?php echo $baseUrl; ?>/admin/forms/<?php echo $sub['form_id']; ?>/submissions/<?php echo $sub['id']; ?>">
              <i class="pi pi-eye"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if (($pages ?? 1) > 1): ?>
    <div class="vtx-pagination" style="padding:.75rem 1rem;">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="?page=<?php echo $p; ?>"
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
