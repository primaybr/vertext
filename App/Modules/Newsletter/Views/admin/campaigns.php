<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-mail me-2 text-primary"></i>Campaigns</h1>
    <p class="vtx-page-desc">Compose and send email campaigns to your subscribers.</p>
  </div>
  <?php if (\App\CMS\Auth::can('newsletter.manage')): ?>
  <button type="button" class="btn btn-primary btn-sm"
          data-form-url="<?php echo $baseUrl; ?>/admin/newsletter/campaigns/create"
          data-form-title="New Campaign"
          data-form-size="modal-md">
    <i class="pi pi-plus me-1"></i> New Campaign
  </button>
  <?php endif; ?>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="vtx-alert vtx-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> mb-3">
  <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<div class="vtx-panel">
  <div class="vtx-panel-body p-0">
    <?php if (empty($campaigns)): ?>
    <div style="padding:3rem;text-align:center;color:var(--ps-text-muted);">
      <i class="pi pi-mail pi-3x mb-3" style="opacity:.3;display:block;margin:0 auto 1rem;"></i>
      <p class="mb-1" style="font-weight:600;">No campaigns yet</p>
      <?php if (\App\CMS\Auth::can('newsletter.manage')): ?>
      <button type="button" class="btn btn-sm btn-primary mt-1"
              data-form-url="<?php echo $baseUrl; ?>/admin/newsletter/campaigns/create"
              data-form-title="New Campaign"
              data-form-size="modal-md">
        Create your first campaign
      </button>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="vtx-table">
      <thead>
        <tr>
          <th>Subject</th>
          <th style="text-align:center;width:100px;">Status</th>
          <th style="text-align:center;width:80px;">Sent to</th>
          <th style="width:140px;">Date</th>
          <th style="text-align:right;width:100px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($campaigns as $c): ?>
        <tr>
          <td>
            <strong><?php echo htmlspecialchars($c['subject']); ?></strong>
          </td>
          <td style="text-align:center;">
            <?php if ($c['status'] === 'sent'): ?>
            <span class="badge badge-success">Sent</span>
            <?php elseif ($c['status'] === 'sending'): ?>
            <span class="badge badge-warning">Sending...</span>
            <?php else: ?>
            <span class="badge badge-secondary">Draft</span>
            <?php endif; ?>
          </td>
          <td style="text-align:center;font-weight:600;">
            <?php echo $c['status'] === 'sent' ? (int) $c['sent_count'] : '-'; ?>
          </td>
          <td style="font-size:.8125rem;color:var(--ps-text-muted);white-space:nowrap;">
            <?php echo $c['sent_at']
              ? date('M j, Y', strtotime($c['sent_at']))
              : date('M j, Y', strtotime($c['created_at'])); ?>
          </td>
          <td style="text-align:right;white-space:nowrap;">
            <?php if ($c['status'] !== 'sent' && \App\CMS\Auth::can('newsletter.manage')): ?>
            <a href="<?php echo $baseUrl; ?>/admin/newsletter/campaigns/<?php echo $c['id']; ?>/edit"
               class="btn btn-sm btn-outline-primary me-1" title="Edit">
              <i class="pi pi-edit"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    data-vtx-confirm="Delete campaign &quot;<?php echo htmlspecialchars($c['subject']); ?>&quot;?"
                    data-vtx-action="<?php echo $baseUrl; ?>/admin/newsletter/campaigns/<?php echo $c['id']; ?>/delete"
                    data-vtx-csrf="<?php echo htmlspecialchars($csrf_token ?? ''); ?>"
                    title="Delete">
              <i class="pi pi-trash"></i>
            </button>
            <?php else: ?>
            <a href="<?php echo $baseUrl; ?>/admin/newsletter/campaigns/<?php echo $c['id']; ?>/edit"
               class="btn btn-sm btn-outline-secondary" title="View">
              <i class="pi pi-eye"></i>
            </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (($pages ?? 1) > 1): ?>
    <div class="vtx-pagination" style="padding:.75rem 1rem;">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="?page=<?php echo $p; ?>"
         class="vtx-page-link <?php echo $p === ($page ?? 1) ? 'active' : ''; ?>"><?php echo $p; ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
