<?php $editing = isset($role) && is_array($role) && !empty($role['id']); ?>

<form method="POST" action="<?php echo htmlspecialchars($action); ?>" data-crud-form>
  <input type="hidden" name="csrf_token" value="{{csrf_token}}">

  <div class="row g-3">

    <!-- Role details (left column) -->
    <div class="col-md-4">

      <div class="vtx-field">
        <label class="vtx-label" for="r-name">Role Name <span class="req">*</span></label>
        <input class="form-control" type="text" id="r-name" name="name"
               value="<?php echo htmlspecialchars($role['name'] ?? ''); ?>"
               placeholder="e.g. Editor" required autocomplete="off"
               <?php echo (!empty($role['is_system'])) ? 'readonly' : ''; ?>>
        <?php if (!empty($role['is_system'])): ?>
        <div class="vtx-help">System roles cannot be renamed.</div>
        <?php endif; ?>
      </div>

      <div class="vtx-field">
        <label class="vtx-label" for="r-description">Description</label>
        <textarea class="form-control" id="r-description" name="description"
                  rows="3" placeholder="What is this role for?"><?php echo htmlspecialchars($role['description'] ?? ''); ?></textarea>
      </div>

    </div>

    <!-- Permissions grid (right column) -->
    <div class="col-md-8">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;">
        <label class="vtx-label" style="margin:0;">Permissions</label>
        <div style="display:flex;gap:.375rem;">
          <button type="button" id="r-select-all" class="btn btn-outline-secondary btn-sm" style="font-size:.75rem;padding:.2rem .5rem;">All</button>
          <button type="button" id="r-clear-all"  class="btn btn-outline-secondary btn-sm" style="font-size:.75rem;padding:.2rem .5rem;">None</button>
        </div>
      </div>
      <?php if (empty($perms)): ?>
      <p style="font-size:.875rem;color:var(--ps-text-muted);">No permissions defined.</p>
      <?php else: ?>
      <?php $assignedIds = $rolePerms ?? []; ?>
      <div style="max-height:280px;overflow-y:auto;border:1px solid var(--ps-border);
                  border-radius:var(--ps-radius);padding:.5rem .625rem;background:var(--ps-bg-base);">
        <?php foreach ($perms as $module => $permissions): ?>
        <div style="margin-bottom:.875rem;">
          <div style="font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
                      color:var(--ps-text-muted);margin-bottom:.375rem;">
            <?php echo htmlspecialchars(ucfirst($module)); ?>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.25rem;">
            <?php foreach ($permissions as $perm): ?>
            <label class="perm-label" style="display:flex;align-items:center;gap:.4rem;
                          padding:.25rem .375rem;border-radius:4px;cursor:pointer;font-size:.8125rem;">
              <input type="checkbox" name="permissions[]" value="<?php echo $perm['id']; ?>"
                     class="r-perm-cb"
                     <?php echo in_array($perm['id'], $assignedIds) ? 'checked' : ''; ?>
                     style="width:14px;height:14px;flex-shrink:0;">
              <?php echo htmlspecialchars($perm['name']); ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /row -->

  <div style="display:flex;justify-content:flex-end;gap:.5rem;
              padding-top:.875rem;margin-top:.875rem;border-top:1px solid var(--ps-border);">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="window.vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="pi pi-check me-1"></i><?php echo $editing ? 'Update Role' : 'Create Role'; ?>
    </button>
  </div>
</form>

<script>
(function () {
    var cbs = document.querySelectorAll('.r-perm-cb');
    var selAll = document.getElementById('r-select-all');
    var clrAll = document.getElementById('r-clear-all');
    if (selAll) selAll.addEventListener('click', function () { cbs.forEach(function (cb) { cb.checked = true; }); });
    if (clrAll) clrAll.addEventListener('click', function () { cbs.forEach(function (cb) { cb.checked = false; }); });
})();
</script>
