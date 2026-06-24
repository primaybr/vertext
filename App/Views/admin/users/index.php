<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-users me-2 text-primary"></i>Users</h1>
    <p class="vtx-page-desc">Manage user accounts and role assignments.</p>
  </div>
  <button type="button" class="btn btn-primary"
          data-form-url="{{baseUrl}}/admin/users/form"
          data-form-title="Add User">
    <i class="pi pi-plus me-1"></i> Add User
  </button>
</div>

<!-- Search -->
<div class="vtx-panel mb-3">
  <div class="vtx-panel-body" style="padding:.75rem 1rem;">
    <form method="GET" action="{{baseUrl}}/admin/users" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;">
      <div style="flex:1;min-width:200px;">
        <input class="form-control form-control-sm" type="search" name="search"
               value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Search name or email…"
               data-vtx-search
               data-target="#vtx-users-tbody"
               data-url="{{baseUrl}}/admin/users"
               data-response-selector="table.vtx-table tbody">
      </div>
      <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
      <?php if (!empty($search)): ?>
      <a href="{{baseUrl}}/admin/users" class="btn btn-link btn-sm text-muted">Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Table -->
<div class="vtx-panel">
  <?php if (empty($users)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-users"></i></div>
    <div class="vtx-empty-title">No users found</div>
    <div class="vtx-empty-desc">
      <?php if (!empty($search)): ?>
        No users match "<?php echo htmlspecialchars($search); ?>".
        <a href="{{baseUrl}}/admin/users">Clear search</a>
      <?php else: ?>
        No users yet. <button type="button" class="btn btn-link p-0"
          data-form-url="{{baseUrl}}/admin/users/form"
          data-form-title="Add User">Add the first user</button>
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
          <th style="width:80px;"></th>
        </tr>
      </thead>
      <tbody id="vtx-users-tbody">
        <?php foreach ($users as $row): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:.625rem;">
              <div style="width:32px;height:32px;border-radius:50%;background:var(--ps-primary);
                          display:flex;align-items:center;justify-content:center;
                          font-size:.75rem;font-weight:700;color:#fff;flex-shrink:0;">
                <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
              </div>
              <span class="cell-primary"><?php echo htmlspecialchars($row['name']); ?></span>
            </div>
          </td>
          <td class="cell-muted"><?php echo htmlspecialchars($row['email']); ?></td>
          <td>
            <?php
            $cls = match($row['status'] ?? 'active') {
                'active'   => 'success',
                'inactive' => 'warning',
                'banned'   => 'error',
                default    => ''
            };
            ?>
            <span class="vtx-tag <?php echo $cls; ?>"><?php echo ucfirst($row['status'] ?? 'active'); ?></span>
          </td>
          <td class="cell-muted">
            <?php echo !empty($row['last_login']) ? date('M d, Y', strtotime($row['last_login'])) : '-'; ?>
          </td>
          <td>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <button type="button" class="vtx-icon-btn" title="Edit"
                      data-form-url="{{baseUrl}}/admin/users/<?php echo $row['id']; ?>/form"
                      data-form-title="Edit User">
                <i class="pi pi-edit"></i>
              </button>
              <?php if ((int)$row['id'] !== (int)($currentUser['id'] ?? 0)): ?>
              <form id="del-user-<?php echo $row['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/users/<?php echo $row['id']; ?>/delete"
                    style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Delete"
                      data-confirm-form="del-user-<?php echo $row['id']; ?>"
                      data-confirm-title="Delete User"
                      data-confirm-message="Delete &quot;<?php echo htmlspecialchars($row['name']); ?>&quot;? This cannot be undone."
                      data-confirm-label="Delete"
                      data-confirm-class="btn-danger"
                      data-confirm-ajax="true">
                <i class="pi pi-trash"></i>
              </button>
              <?php endif; ?>
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
      $perPage = 15;
      $from = (($page - 1) * $perPage) + 1;
      $to   = min($page * $perPage, $total);
      echo "Showing {$from}–{$to} of {$total} users";
      ?>
    </span>
    <div style="display:flex;gap:.25rem;">
      <?php if ($page > 1): ?>
      <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search ?? ''); ?>"
         class="btn btn-outline-secondary btn-sm"><i class="pi pi-arrow-left"></i></a>
      <?php endif; ?>
      <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
      <a href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search ?? ''); ?>"
         class="btn btn-sm <?php echo $p === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>">
        <?php echo $p; ?>
      </a>
      <?php endfor; ?>
      <?php if ($page < $pages): ?>
      <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search ?? ''); ?>"
         class="btn btn-outline-secondary btn-sm"><i class="pi pi-arrow-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
