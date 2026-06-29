<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-clock me-2 text-primary"></i>Audit Log</h1>
    <p class="vtx-page-desc">All admin state-changing actions. <?php echo number_format($total); ?> total entries.</p>
  </div>
</div>

<!-- Filters -->
<div class="vtx-panel mb-4">
  <div class="vtx-panel-body">
    <form method="GET" action="{{baseUrl}}/admin/audit-log" class="d-flex gap-2 flex-wrap align-items-end">
      <div style="flex:1;min-width:160px;">
        <label class="form-label" style="font-size:.8125rem;">Search</label>
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
               class="form-control form-control-sm" placeholder="Action, resource, user...">
      </div>
      <div style="min-width:180px;">
        <label class="form-label" style="font-size:.8125rem;">Action</label>
        <select name="action" class="form-select form-select-sm" data-vtx-select>
          <option value="">All actions</option>
          <?php foreach ($distinctActions as $act): ?>
          <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $actionFilter === $act ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($act); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:140px;">
        <label class="form-label" style="font-size:.8125rem;">From</label>
        <input type="date" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="form-control form-control-sm">
      </div>
      <div style="min-width:140px;">
        <label class="form-label" style="font-size:.8125rem;">To</label>
        <input type="date" name="to" value="<?php echo htmlspecialchars($dateTo); ?>" class="form-control form-control-sm">
      </div>
      <div style="display:flex;gap:.5rem;">
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        <?php if ($search || $actionFilter || $dateFrom || $dateTo): ?>
        <a href="{{baseUrl}}/admin/audit-log" class="btn btn-sm btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Audit Table -->
<div class="vtx-panel">
  <?php if (empty($logs)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-clock"></i></div>
    <div class="vtx-empty-title">No audit entries found</div>
    <div class="vtx-empty-desc">Admin actions will appear here as they happen.</div>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto;">
    <table class="vtx-table">
      <thead>
        <tr>
          <th style="min-width:160px;">Action</th>
          <th>User</th>
          <th>Resource</th>
          <th style="min-width:160px;">Details</th>
          <th>IP</th>
          <th style="min-width:160px;">When</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
        <?php
          $actionParts = explode('.', $log['action'] ?? '', 2);
          $actionType  = $actionParts[1] ?? $log['action'];
          $badgeClass  = match(true) {
            str_contains($log['action'], 'delete') || str_contains($log['action'], 'uninstall') => 'error',
            str_contains($log['action'], 'create') || str_contains($log['action'], 'install')  => 'success',
            str_contains($log['action'], 'login')                                               => 'info',
            str_contains($log['action'], 'update') || str_contains($log['action'], 'save')     => 'warning',
            default => ''
          };
          $details  = is_string($log['details']) ? json_decode($log['details'], true) : ($log['details'] ?? []);
          $resource = trim(($log['resource_type'] ?? '') . ' ' . ($log['resource_id'] ? substr($log['resource_id'], 0, 8) . '...' : ''));
        ?>
        <tr>
          <td>
            <span class="vtx-tag <?php echo $badgeClass; ?>" style="font-size:.6875rem;">
              <?php echo htmlspecialchars($log['action'] ?? ''); ?>
            </span>
          </td>
          <td style="font-size:.8125rem;"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
          <td style="font-size:.8125rem;color:var(--ps-text-secondary);"><?php echo htmlspecialchars($resource); ?></td>
          <td style="font-size:.75rem;color:var(--ps-text-secondary);">
            <?php if (!empty($details) && is_array($details)): ?>
            <?php foreach (array_slice($details, 0, 3) as $k => $v): ?>
            <span style="display:block;">
              <strong><?php echo htmlspecialchars((string) $k); ?>:</strong>
              <?php echo htmlspecialchars(is_scalar($v) ? (string) $v : json_encode($v)); ?>
            </span>
            <?php endforeach; ?>
            <?php endif; ?>
          </td>
          <td style="font-size:.75rem;color:var(--ps-text-muted);"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
          <td style="font-size:.75rem;white-space:nowrap;color:var(--ps-text-secondary);">
            <?php echo $log['created_at'] ? date('M j, Y H:i', strtotime($log['created_at'])) : ''; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="vtx-panel-foot d-flex align-items-center justify-content-between">
    <div style="font-size:.8125rem;color:var(--ps-text-secondary);">
      Page <?php echo $page; ?> of <?php echo $totalPages; ?>
    </div>
    <div style="display:flex;gap:.25rem;">
      <?php
        $baseHref = '{{baseUrl}}/admin/audit-log?' . http_build_query(array_filter(['q' => $search, 'action' => $actionFilter, 'from' => $dateFrom, 'to' => $dateTo]));
      ?>
      <?php if ($page > 1): ?>
      <a href="<?php echo $baseHref . '&page=' . ($page - 1); ?>" class="btn btn-sm btn-outline-secondary">Prev</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
      <a href="<?php echo $baseHref . '&page=' . ($page + 1); ?>" class="btn btn-sm btn-outline-secondary">Next</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>
