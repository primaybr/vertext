<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-home me-2 text-primary"></i>Dashboard</h1>
    <p class="vtx-page-desc">Welcome back, {{currentUser.name}}. Here's your system overview.</p>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
    <span style="font-size:.8125rem;color:var(--ps-text-muted);">
      <i class="pi pi-clock me-1"></i><?php echo date('D, d M Y'); ?>
    </span>
  </div>
</div>

<!-- Stats -->
<div class="vtx-stats">
  <div class="vtx-stat">
    <div class="vtx-stat-val">{{stats.users}}</div>
    <div class="vtx-stat-lbl">Total Users</div>
  </div>
  <div class="vtx-stat">
    <div class="vtx-stat-val">{{stats.roles}}</div>
    <div class="vtx-stat-lbl">Roles</div>
  </div>
  <div class="vtx-stat">
    <div class="vtx-stat-val">{{stats.modules}}</div>
    <div class="vtx-stat-lbl">Active Modules</div>
  </div>
  <div class="vtx-stat">
    <div class="vtx-stat-val">{{stats.settings}}</div>
    <div class="vtx-stat-lbl">Settings</div>
  </div>
</div>

<div class="row g-4">

  <!-- Recent Activity -->
  <div class="col-lg-8">
    <div class="vtx-panel">
      <div class="vtx-panel-head">
        <h2 class="vtx-panel-title"><i class="pi pi-list me-1 text-primary"></i> Recent Activity</h2>
      </div>
      <?php if (empty($recent)): ?>
      <div class="vtx-empty">
        <div class="vtx-empty-ico"><i class="pi pi-list"></i></div>
        <div class="vtx-empty-title">No activity yet</div>
        <div class="vtx-empty-desc">Admin actions will appear here once users start using the system.</div>
      </div>
      <?php else: ?>
      <div class="vtx-table-wrap">
        <table class="vtx-table">
          <thead>
            <tr>
              <th>Action</th>
              <th>User</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $log): ?>
            <tr>
              <td>
                <span class="cell-primary"><?php echo htmlspecialchars($log['action']); ?></span>
                <?php if ($log['resource_type']): ?>
                <span class="cell-muted ms-1"><?php echo htmlspecialchars($log['resource_type']); ?></span>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
              <td class="cell-muted"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="col-lg-4">
    <div class="vtx-panel">
      <div class="vtx-panel-head">
        <h2 class="vtx-panel-title"><i class="pi pi-zap me-1 text-primary"></i> Quick Actions</h2>
      </div>
      <div class="vtx-panel-body" style="display:flex;flex-direction:column;gap:.5rem;">
        <button type="button" class="btn btn-outline-primary text-start"
                data-form-url="{{baseUrl}}/admin/users/form"
                data-form-title="Add User">
          <i class="pi pi-plus me-1"></i> Add New User
        </button>
        <button type="button" class="btn btn-outline-secondary text-start"
                data-form-url="{{baseUrl}}/admin/roles/form"
                data-form-title="Add Role"
                data-form-size="modal-lg">
          <i class="pi pi-shield me-1"></i> Create Role
        </button>
        <a href="{{baseUrl}}/admin/modules" class="btn btn-outline-secondary text-start">
          <i class="pi pi-layers me-1"></i> Manage Modules
        </a>
        <a href="{{baseUrl}}/admin/settings" class="btn btn-outline-secondary text-start">
          <i class="pi pi-settings me-1"></i> Site Settings
        </a>
      </div>
    </div>

    <!-- System Info -->
    <div class="vtx-panel">
      <div class="vtx-panel-head">
        <h2 class="vtx-panel-title"><i class="pi pi-cpu me-1 text-primary"></i> System Info</h2>
      </div>
      <div class="vtx-panel-body" style="font-size:.8125rem;">
        <div style="display:flex;justify-content:space-between;padding:.25rem 0;border-bottom:1px solid var(--ps-border);">
          <span style="color:var(--ps-text-muted);">CMS Version</span>
          <span><?php echo \App\CMS\Version::APP; ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:.25rem 0;border-bottom:1px solid var(--ps-border);">
          <span style="color:var(--ps-text-muted);">PHP Version</span>
          <span><?php echo PHP_VERSION; ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:.25rem 0;border-bottom:1px solid var(--ps-border);">
          <span style="color:var(--ps-text-muted);">Framework</span>
          <span>Phuse <?php echo \App\CMS\Version::PHUSE; ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:.25rem 0;">
          <span style="color:var(--ps-text-muted);">Database</span>
          <span>PostgreSQL</span>
        </div>
      </div>
    </div>
  </div>

</div>
