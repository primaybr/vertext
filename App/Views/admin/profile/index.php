<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-user me-2 text-primary"></i>My Profile</h1>
    <p class="vtx-page-desc">Update your name, email address, or password.</p>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7 col-xl-6">

    <form method="POST" action="{{baseUrl}}/admin/profile/update">
      <input type="hidden" name="csrf_token" value="{{csrf_token}}">

      <!-- Account Details -->
      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head">
          <h2 class="vtx-panel-title">Account Details</h2>
        </div>
        <div class="vtx-panel-body">

          <div class="vtx-field mb-3">
            <label class="vtx-label" for="name">Name <span class="req">*</span></label>
            <input class="form-control" type="text" id="name" name="name"
                   value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                   required autocomplete="name">
          </div>

          <div class="vtx-field">
            <label class="vtx-label" for="email">Email <span class="req">*</span></label>
            <input class="form-control" type="email" id="email" name="email"
                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                   required autocomplete="email">
          </div>

        </div>
      </div>

      <!-- Change Password -->
      <div class="vtx-panel mb-4">
        <div class="vtx-panel-head">
          <h2 class="vtx-panel-title">Change Password</h2>
          <p class="vtx-panel-desc">Leave both fields blank to keep your current password.</p>
        </div>
        <div class="vtx-panel-body">

          <div class="vtx-field mb-3">
            <label class="vtx-label" for="password">New Password</label>
            <div style="position:relative;">
              <input class="form-control" type="password" id="password" name="password"
                     placeholder="Minimum 8 characters" autocomplete="new-password">
              <button type="button" class="vtx-icon-btn" data-pw-toggle="password"
                      style="position:absolute;right:6px;top:50%;transform:translateY(-50%);border:none;background:none;">
                <i class="pi pi-eye-off"></i>
              </button>
            </div>
          </div>

          <div class="vtx-field">
            <label class="vtx-label" for="password_confirm">Confirm New Password</label>
            <div style="position:relative;">
              <input class="form-control" type="password" id="password_confirm" name="password_confirm"
                     placeholder="Repeat new password" autocomplete="new-password">
              <button type="button" class="vtx-icon-btn" data-pw-toggle="password_confirm"
                      style="position:absolute;right:6px;top:50%;transform:translateY(-50%);border:none;background:none;">
                <i class="pi pi-eye-off"></i>
              </button>
            </div>
          </div>

        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="{{baseUrl}}/admin/dashboard" class="btn btn-outline-secondary">Cancel</a>
      </div>

    </form>

  </div>

  <!-- Account info sidebar -->
  <div class="col-lg-4 offset-lg-1 col-xl-4 offset-xl-1">
    <div class="vtx-panel">
      <div class="vtx-panel-body" style="text-align:center;padding:1.5rem 1rem;">
        <div class="vtx-avatar" style="width:64px;height:64px;font-size:1.5rem;margin:0 auto 1rem;">
          <?php echo htmlspecialchars(mb_strtoupper(mb_substr($user['name'] ?? 'U', 0, 1))); ?>
        </div>
        <div style="font-weight:600;font-size:1rem;color:var(--ps-text-primary);">
          <?php echo htmlspecialchars($user['name'] ?? ''); ?>
        </div>
        <div style="font-size:.875rem;color:var(--ps-text-muted);margin-top:.25rem;">
          <?php echo htmlspecialchars($user['email'] ?? ''); ?>
        </div>
        <?php if (!empty($user['status'])): ?>
        <div style="margin-top:.75rem;">
          <span class="vtx-tag <?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
            <?php echo htmlspecialchars(ucfirst($user['status'])); ?>
          </span>
        </div>
        <?php endif; ?>
        <?php if (!empty($user['created_at'])): ?>
        <div style="font-size:.75rem;color:var(--ps-text-muted);margin-top:1rem;padding-top:.75rem;border-top:1px solid var(--ps-border);">
          Member since <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
