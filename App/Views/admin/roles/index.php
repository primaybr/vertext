<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-shield me-2 text-primary"></i>Roles</h1>
    <p class="vtx-page-desc">Manage roles and their permission sets.</p>
  </div>
  <button type="button" class="btn btn-primary"
          data-form-url="{{baseUrl}}/admin/roles/form"
          data-form-title="Add Role"
          data-form-size="modal-lg">
    <i class="pi pi-plus me-1"></i> Add Role
  </button>
</div>

<div class="vtx-panel">
  <?php if (empty($roles)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-shield"></i></div>
    <div class="vtx-empty-title">No roles yet</div>
    <div class="vtx-empty-desc">
      <button type="button" class="btn btn-link p-0"
              data-form-url="{{baseUrl}}/admin/roles/form"
              data-form-title="Add Role"
              data-form-size="modal-lg">Create the first role</button>
    </div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table" data-vtx-table data-sortable>
      <thead>
        <tr>
          <th data-sort="name">Name</th>
          <th data-sort="description">Description</th>
          <th data-sort="permissions">Permissions</th>
          <th data-sort="type">Type</th>
          <th style="width:80px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($roles as $role): ?>
        <tr>
          <td class="cell-primary"><?php echo htmlspecialchars($role['name']); ?></td>
          <td class="cell-muted"><?php echo htmlspecialchars($role['description'] ?? '-'); ?></td>
          <td>
            <span style="font-size:.8125rem;font-weight:600;"><?php echo (int)($role['perm_count'] ?? 0); ?></span>
            <span class="cell-muted ms-1">permissions</span>
          </td>
          <td>
            <?php if (!empty($role['is_system'])): ?>
            <span class="vtx-tag info">System</span>
            <?php else: ?>
            <span class="vtx-tag">Custom</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <button type="button" class="vtx-icon-btn" title="Edit"
                      data-form-url="{{baseUrl}}/admin/roles/<?php echo $role['id']; ?>/form"
                      data-form-title="Edit Role"
                      data-form-size="modal-lg">
                <i class="pi pi-edit"></i>
              </button>
              <?php if (empty($role['is_system'])): ?>
              <form id="del-role-<?php echo $role['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/roles/<?php echo $role['id']; ?>/delete"
                    style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Delete"
                      data-confirm-form="del-role-<?php echo $role['id']; ?>"
                      data-confirm-title="Delete Role"
                      data-confirm-message="Delete &quot;<?php echo htmlspecialchars($role['name']); ?>&quot;? This cannot be undone."
                      data-confirm-label="Delete"
                      data-confirm-class="btn-danger"
                      data-confirm-ajax="true">
                <i class="pi pi-trash"></i>
              </button>
              <?php else: ?>
              <span class="vtx-icon-btn" style="opacity:.3;cursor:not-allowed;" title="System roles cannot be deleted">
                <i class="pi pi-trash"></i>
              </span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
