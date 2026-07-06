<!-- Page Header -->
<div class="vtx-page-head" data-members-page data-csrf="{{csrf_token}}">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-users me-2 text-primary"></i>Members</h1>
    <p class="vtx-page-desc">Site visitor accounts registered through the front-end.</p>
  </div>
</div>

<!-- Status tabs + search -->
<div class="vtx-panel mb-3">
  <div class="vtx-panel-body" style="padding:.75rem 1rem;display:flex;gap:1rem;flex-wrap:wrap;align-items:center;justify-content:space-between;">
    <div style="display:flex;gap:.25rem;flex-wrap:wrap;">
      <?php
        $tabs = ['' => 'All', 'active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'];
        foreach ($tabs as $key => $label):
          $count  = $counts[$key === '' ? 'all' : $key] ?? 0;
          $active = ($status ?? '') === $key;
      ?>
      <a href="{{baseUrl}}/admin/members?status=<?php echo $key; ?>"
         class="btn btn-sm <?php echo $active ? 'btn-primary' : 'btn-outline-secondary'; ?>">
        <?php echo $label; ?> (<?php echo (int) $count; ?>)
      </a>
      <?php endforeach; ?>
    </div>
    <form method="GET" action="{{baseUrl}}/admin/members" style="display:flex;gap:.5rem;align-items:center;">
      <input type="hidden" name="status" value="<?php echo htmlspecialchars($status ?? ''); ?>">
      <input class="form-control form-control-sm" type="search" name="search" style="min-width:200px;"
             value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Search name or email…">
      <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
    </form>
  </div>
</div>

<!-- Table -->
<div class="vtx-panel">
  <?php if (empty($members)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-users"></i></div>
    <div class="vtx-empty-title">No members found</div>
    <div class="vtx-empty-desc">
      <?php if (!empty($search) || !empty($status)): ?>
        No members match the current filter. <a href="{{baseUrl}}/admin/members">Clear filters</a>
      <?php else: ?>
        Nobody has registered yet. Members can sign up at <code>/account/register</code>.
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table" data-vtx-table data-sortable>
      <thead>
        <tr>
          <th data-sort="name">Name</th>
          <th data-sort="email">Email</th>
          <th data-sort="status">Status</th>
          <th data-sort="last_login">Last Login</th>
          <th data-sort="created_at">Registered</th>
          <th style="width:130px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $row): ?>
        <tr data-member-row="<?php echo $row['id']; ?>">
          <td>
            <div style="display:flex;align-items:center;gap:.625rem;">
              <div style="width:32px;height:32px;border-radius:50%;background:var(--ps-primary-fill);
                          display:flex;align-items:center;justify-content:center;
                          font-size:.75rem;font-weight:700;color:#fff;flex-shrink:0;">
                <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
              </div>
              <span class="cell-primary"><?php echo htmlspecialchars($row['name']); ?></span>
            </div>
          </td>
          <td class="cell-muted"><?php echo htmlspecialchars($row['email']); ?></td>
          <td data-member-status>
            <?php
            $cls = match($row['status'] ?? 'pending') {
                'active'    => 'success',
                'pending'   => 'warning',
                'suspended' => 'error',
                default     => ''
            };
            ?>
            <span class="vtx-tag <?php echo $cls; ?>"><?php echo ucfirst($row['status'] ?? 'pending'); ?></span>
          </td>
          <td class="cell-muted">
            <?php echo !empty($row['last_login']) ? date('M d, Y', strtotime($row['last_login'])) : '-'; ?>
          </td>
          <td class="cell-muted">
            <?php echo !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : '-'; ?>
          </td>
          <td>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <!-- All action buttons rendered; inapplicable ones hidden so JS can swap in place -->
              <button type="button" class="vtx-icon-btn" title="Resend verification email"
                      data-member-action="resend"
                      data-member-id="<?php echo $row['id']; ?>"
                      <?php if ($row['status'] !== 'pending') echo 'style="display:none;"'; ?>>
                <i class="pi pi-send"></i>
              </button>

              <button type="button" class="vtx-icon-btn" title="Activate"
                      data-member-action="status" data-member-status-to="active"
                      data-member-id="<?php echo $row['id']; ?>"
                      data-member-name="<?php echo htmlspecialchars($row['name']); ?>"
                      <?php if ($row['status'] === 'active') echo 'style="display:none;"'; ?>>
                <i class="pi pi-check-circle"></i>
              </button>

              <button type="button" class="vtx-icon-btn" title="Suspend"
                      data-member-action="status" data-member-status-to="suspended"
                      data-member-id="<?php echo $row['id']; ?>"
                      data-member-name="<?php echo htmlspecialchars($row['name']); ?>"
                      <?php if ($row['status'] !== 'active') echo 'style="display:none;"'; ?>>
                <i class="pi pi-minus-circle"></i>
              </button>

              <form id="del-member-<?php echo $row['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/members/<?php echo $row['id']; ?>/delete"
                    style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Delete"
                      data-confirm-form="del-member-<?php echo $row['id']; ?>"
                      data-confirm-title="Delete Member"
                      data-confirm-message="Delete &quot;<?php echo htmlspecialchars($row['name']); ?>&quot;? Their account will be removed."
                      data-confirm-label="Delete"
                      data-confirm-class="btn-danger"
                      data-confirm-ajax="true">
                <i class="pi pi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if (($pages ?? 1) > 1): ?>
  <div class="vtx-panel-body" style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--ps-border);padding-top:.75rem;">
    <span style="font-size:.8125rem;color:var(--ps-text-muted);">
      <?php
      $perPage = 20;
      $from = (($page - 1) * $perPage) + 1;
      $to   = min($page * $perPage, $total);
      echo "Showing {$from}-{$to} of {$total} members";
      ?>
    </span>
    <div style="display:flex;gap:.25rem;">
      <?php $qs = '&search=' . urlencode($search ?? '') . '&status=' . urlencode($status ?? ''); ?>
      <?php if ($page > 1): ?>
      <a href="?page=<?php echo $page - 1; echo $qs; ?>" class="btn btn-outline-secondary btn-sm"><i class="pi pi-arrow-left"></i></a>
      <?php endif; ?>
      <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
      <a href="?page=<?php echo $p; echo $qs; ?>"
         class="btn btn-sm <?php echo $p === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $p; ?></a>
      <?php endfor; ?>
      <?php if ($page < $pages): ?>
      <a href="?page=<?php echo $page + 1; echo $qs; ?>" class="btn btn-outline-secondary btn-sm"><i class="pi pi-arrow-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
