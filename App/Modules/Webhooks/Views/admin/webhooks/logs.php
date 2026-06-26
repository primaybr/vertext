<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-list me-2 text-primary"></i>Delivery Logs</h1>
    <p class="vtx-page-desc">
      <strong><?php echo htmlspecialchars($endpoint['name']); ?></strong>
      - <span style="color:var(--ps-text-muted);font-size:.875rem;"><?php echo htmlspecialchars($endpoint['url']); ?></span>
    </p>
  </div>
  <div>
    <a href="{{baseUrl}}/admin/webhooks" class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-arrow-left me-1"></i> Back
    </a>
  </div>
</div>

<div class="vtx-panel">
  <?php if (empty($logs)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-list"></i></div>
    <div class="vtx-empty-title">No deliveries yet</div>
    <div class="vtx-empty-desc">Use the test button to trigger a test delivery, or wait for a real event.</div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table">
      <thead>
        <tr>
          <th style="width:44px;">Status</th>
          <th>Event</th>
          <th style="width:80px;text-align:center;">HTTP</th>
          <th style="width:80px;text-align:right;">Duration</th>
          <th>Response</th>
          <th style="width:150px;">Delivered</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td>
            <?php if ($log['success']): ?>
            <i class="pi pi-check" style="color:var(--ps-success,#16a34a);"></i>
            <?php else: ?>
            <i class="pi pi-times" style="color:var(--ps-danger,#dc2626);"></i>
            <?php endif; ?>
          </td>
          <td>
            <code style="font-size:.8125rem;"><?php echo htmlspecialchars($log['event']); ?></code>
          </td>
          <td style="text-align:center;">
            <?php $code = (int)$log['response_code'];
            $codeColor = $code >= 200 && $code < 300 ? 'var(--ps-success,#16a34a)' : ($code === 0 ? 'var(--ps-text-muted)' : 'var(--ps-danger,#dc2626)'); ?>
            <span style="font-weight:600;color:<?php echo $codeColor; ?>;font-size:.875rem;">
              <?php echo $code ?: '-'; ?>
            </span>
          </td>
          <td style="text-align:right;font-size:.8125rem;color:var(--ps-text-muted);">
            <?php echo number_format((int)$log['duration_ms']); ?>ms
          </td>
          <td style="font-size:.75rem;color:var(--ps-text-muted);max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
              title="<?php echo htmlspecialchars($log['response_body'] ?? ''); ?>">
            <?php echo htmlspecialchars(mb_substr($log['response_body'] ?? '', 0, 80)); ?>
          </td>
          <td style="font-size:.8125rem;color:var(--ps-text-muted);">
            <?php echo htmlspecialchars(substr($log['created_at'] ?? '', 0, 16)); ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="vtx-panel-body" style="border-top:1px solid var(--ps-border);padding:.625rem 1rem;">
    <span style="font-size:.8125rem;color:var(--ps-text-muted);">Showing last <?php echo count($logs); ?> deliveries.</span>
  </div>
  <?php endif; ?>
</div>
