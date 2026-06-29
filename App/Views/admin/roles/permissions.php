<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-shield me-2 text-primary"></i>Permissions</h1>
    <p class="vtx-page-desc">All permission slugs registered in the system. Module permissions are managed by their module's install process.</p>
  </div>
  <div style="display:flex;gap:.5rem;">
    <a href="{{baseUrl}}/admin/roles" class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-arrow-left me-1"></i> Roles
    </a>
    <button type="button" class="btn btn-primary btn-sm" id="create-perm-btn">
      <i class="pi pi-plus me-1"></i> New Permission
    </button>
  </div>
</div>

<!-- Create Permission Form (inline collapsible) -->
<div id="create-perm-form" class="vtx-panel mb-4" style="display:none;">
  <div class="vtx-panel-head">
    <h2 class="vtx-panel-title">New Custom Permission</h2>
  </div>
  <div class="vtx-panel-body">
    <form id="perm-create-form">
      <input type="hidden" name="csrf_token" value="{{csrf_token}}">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:1rem;">
        <div>
          <label class="form-label">Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control form-control-sm" placeholder="View Reports" required>
        </div>
        <div>
          <label class="form-label">Slug <span class="text-danger">*</span></label>
          <input type="text" name="slug" class="form-control form-control-sm" placeholder="reports.view" required>
          <div class="form-hint">Lowercase. Use dots for namespacing (e.g. module.action)</div>
        </div>
        <div>
          <label class="form-label">Module / Group</label>
          <input type="text" name="module" class="form-control form-control-sm" placeholder="custom" value="custom">
        </div>
        <div>
          <label class="form-label">Description</label>
          <input type="text" name="description" class="form-control form-control-sm" placeholder="Optional description">
        </div>
      </div>
      <div style="display:flex;gap:.5rem;">
        <button type="submit" class="btn btn-sm btn-primary">Create Permission</button>
        <button type="button" id="cancel-perm-btn" class="btn btn-sm btn-outline-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Permissions grouped by module -->
<?php foreach ($grouped as $moduleName => $modulePerms): ?>
<div class="vtx-panel mb-3">
  <div class="vtx-panel-head">
    <h2 class="vtx-panel-title" style="font-size:.875rem;text-transform:uppercase;letter-spacing:.04em;">
      <?php echo htmlspecialchars($moduleName ?: 'Core'); ?>
      <span style="font-size:.75rem;font-weight:400;color:var(--ps-text-muted);"><?php echo count($modulePerms); ?> permissions</span>
    </h2>
  </div>
  <div class="vtx-table-wrap">
    <table class="vtx-table">
      <thead>
        <tr>
          <th>Slug</th>
          <th>Name</th>
          <th>Description</th>
          <th style="width:80px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($modulePerms as $perm): ?>
        <?php $isCustom = ($perm['module'] ?? 'core') === 'custom'; ?>
        <tr>
          <td><code style="font-size:.8125rem;"><?php echo htmlspecialchars($perm['slug']); ?></code></td>
          <td style="font-size:.8125rem;"><?php echo htmlspecialchars($perm['name']); ?></td>
          <td style="font-size:.8125rem;color:var(--ps-text-secondary);"><?php echo htmlspecialchars($perm['description'] ?? ''); ?></td>
          <td>
            <?php if ($isCustom): ?>
            <button type="button"
                    class="btn btn-sm btn-outline-danger perm-delete-btn"
                    data-id="<?php echo htmlspecialchars($perm['id']); ?>"
                    data-slug="<?php echo htmlspecialchars($perm['slug']); ?>">
              <i class="pi pi-trash"></i>
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<script>
(function() {
  var createBtn  = document.getElementById('create-perm-btn');
  var cancelBtn  = document.getElementById('cancel-perm-btn');
  var formPanel  = document.getElementById('create-perm-form');
  var form       = document.getElementById('perm-create-form');

  createBtn.addEventListener('click', function() { formPanel.style.display = ''; createBtn.style.display = 'none'; });
  cancelBtn.addEventListener('click', function() { formPanel.style.display = 'none'; createBtn.style.display = ''; });

  // Auto-generate slug from name
  form.querySelector('[name="name"]').addEventListener('input', function() {
    var slug = this.value.toLowerCase().replace(/[^a-z0-9\.\-_]+/g, '.').replace(/\.+/g, '.').replace(/^\.|\.$/g, '');
    form.querySelector('[name="slug"]').value = slug;
  });

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = form.querySelector('[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Creating...';
    window.VtxAjax.postForm('{{baseUrl}}/admin/roles/permissions/store', form, function(ok, res) {
      btn.disabled = false;
      btn.textContent = 'Create Permission';
      var success = ok && res && res.success;
      window.Phuse.toast((res && res.message) || (success ? 'Created.' : 'Failed.'), success ? 'success' : 'error');
      if (success) { location.reload(); }
    });
  });

  document.querySelectorAll('.perm-delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id   = this.dataset.id;
      var slug = this.dataset.slug;
      window.vtxConfirmModal({
        title: 'Delete Permission',
        message: 'Delete "' + slug + '"? Any roles with this permission will lose it.',
        confirmLabel: 'Delete',
        confirmClass: 'btn-danger',
        onConfirm: function() {
          var fd = new FormData();
          fd.append('csrf_token', '{{csrf_token}}');
          var xhr = new XMLHttpRequest();
          xhr.open('POST', window.VTX_BASE_URL + '/admin/roles/permissions/' + id + '/delete');
          xhr.onload = function() {
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch(e) {}
            var success = res && res.success;
            window.Phuse.toast((res && res.message) || (success ? 'Deleted.' : 'Failed.'), success ? 'success' : 'error');
            if (success) { location.reload(); }
          };
          xhr.send(fd);
        }
      });
    });
  });
}());
</script>
