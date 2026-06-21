<?php $editing = isset($user) && is_array($user) && !empty($user['id']); ?>

<form method="POST" action="<?php echo htmlspecialchars($action); ?>" data-crud-form>
  <input type="hidden" name="csrf_token" value="{{csrf_token}}">

  <div class="vtx-field">
    <label class="vtx-label" for="u-name">Full Name <span class="req">*</span></label>
    <input class="form-control" type="text" id="u-name" name="name"
           value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
           placeholder="Jane Doe" required autocomplete="off">
  </div>

  <div class="vtx-field">
    <label class="vtx-label" for="u-email">Email Address <span class="req">*</span></label>
    <input class="form-control" type="email" id="u-email" name="email"
           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
           placeholder="jane@example.com" required autocomplete="off">
  </div>

  <div class="vtx-field">
    <label class="vtx-label" for="u-password">
      Password
      <?php if (!$editing): ?>
        <span class="req">*</span>
      <?php else: ?>
        <span class="vtx-help" style="display:inline;">(leave blank to keep current)</span>
      <?php endif; ?>
    </label>
    <div style="position:relative;">
      <input class="form-control" type="password" id="u-password" name="password"
             placeholder="<?php echo $editing ? 'Leave blank to keep current' : 'At least 8 characters'; ?>"
             autocomplete="new-password"
             <?php echo !$editing ? 'minlength="8" required' : ''; ?>>
      <button type="button" class="vtx-icon-btn" data-pw-toggle="u-password"
              style="position:absolute;right:6px;top:50%;transform:translateY(-50%);border:none;background:none;">
        <i class="pi pi-eye-off"></i>
      </button>
    </div>
  </div>

  <div class="vtx-field">
    <label class="vtx-label" for="u-status">Status</label>
    <select class="form-select" id="u-status" name="status" data-vtx-select>
      <?php
      $currentStatus = $user['status'] ?? 'active';
      foreach (['active' => 'Active', 'inactive' => 'Inactive', 'banned' => 'Banned'] as $val => $lbl):
      ?>
      <option value="<?php echo $val; ?>" <?php echo $currentStatus === $val ? 'selected' : ''; ?>>
        <?php echo $lbl; ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if (!empty($roles)): ?>
  <div class="vtx-field">
    <label class="vtx-label">Roles</label>
    <?php $assignedIds = array_map('strval', $userRoleIds ?? []); ?>
    <div style="display:flex;flex-direction:column;gap:.375rem;max-height:160px;overflow-y:auto;
                padding:.25rem .125rem;border:1px solid var(--ps-border);border-radius:var(--ps-radius);
                background:var(--ps-bg-base);">
      <?php foreach ($roles as $role): ?>
      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.875rem;
                    padding:.375rem .625rem;border-radius:4px;">
        <input type="checkbox" name="roles[]" value="<?php echo $role['id']; ?>"
               <?php echo in_array((string)$role['id'], $assignedIds) ? 'checked' : ''; ?>
               style="width:15px;height:15px;flex-shrink:0;">
        <?php echo htmlspecialchars($role['name']); ?>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div style="display:flex;justify-content:flex-end;gap:.5rem;
              padding-top:.875rem;margin-top:.875rem;border-top:1px solid var(--ps-border);">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="window.vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="pi pi-check me-1"></i><?php echo $editing ? 'Update User' : 'Create User'; ?>
    </button>
  </div>
</form>
