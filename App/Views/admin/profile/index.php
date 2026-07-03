<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-user me-2 text-primary"></i>My Profile</h1>
    <p class="vtx-page-desc">Update your name, email address, or password.</p>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7 col-xl-6">

    <form method="POST" action="{{baseUrl}}/admin/profile/update" enctype="multipart/form-data">
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

          <div class="vtx-field mb-3">
            <label class="vtx-label" for="email">Email <span class="req">*</span></label>
            <input class="form-control" type="email" id="email" name="email"
                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                   required autocomplete="email">
          </div>

          <div class="vtx-field">
            <label class="vtx-label" for="avatar">Avatar</label>
            <input class="form-control" type="file" id="avatar" name="avatar" accept="image/*">
            <div style="font-size:.75rem;color:var(--ps-text-muted);margin-top:.35rem;">
              JPG, PNG, GIF, or WebP. Cropped to a 128x128 square. Max 2 MB.
            </div>
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

    <!-- Two-Factor Authentication card -->
    <div class="vtx-panel mt-4">
      <div class="vtx-panel-body" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:.75rem;">
          <span style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;
                       background:<?php echo ($twofa_enabled ?? false) ? 'var(--ps-success-bg,#d1fae5)' : 'var(--ps-warning-bg,#fef3c7)'; ?>;">
            <i class="pi pi-shield" style="font-size:1rem;color:<?php echo ($twofa_enabled ?? false) ? 'var(--ps-success,#059669)' : 'var(--ps-warning,#d97706)'; ?>;"></i>
          </span>
          <div>
            <div style="font-weight:600;font-size:.9rem;">Two-Factor Authentication</div>
            <div style="font-size:.8rem;color:var(--ps-text-muted);">
              <?php if ($twofa_enabled ?? false): ?>
                Your account has an extra layer of protection.
              <?php else: ?>
                Not enabled - your account relies on password only.
              <?php endif; ?>
            </div>
          </div>
        </div>
        <a href="{{baseUrl}}/admin/profile/2fa"
           class="btn btn-sm <?php echo ($twofa_enabled ?? false) ? 'btn-outline-secondary' : 'btn-outline-primary'; ?>">
          <?php echo ($twofa_enabled ?? false) ? 'Manage 2FA' : 'Enable 2FA'; ?>
        </a>
      </div>
    </div>

    <!-- Active Sessions -->
    <div class="vtx-panel mt-4">
      <div class="vtx-panel-head" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div>
          <h2 class="vtx-panel-title">Active Sessions</h2>
          <p class="vtx-panel-desc">Devices currently signed in to your account.</p>
        </div>
        <?php if (count($sessions ?? []) > 1): ?>
        <form method="POST" action="{{baseUrl}}/admin/profile/sessions/revoke-others"
              onsubmit="return confirm('Sign out of all other sessions?');">
          <input type="hidden" name="csrf_token" value="{{csrf_token}}">
          <button type="submit" class="btn btn-sm btn-outline-danger">
            <i class="pi pi-log-out me-1"></i> Sign out everywhere else
          </button>
        </form>
        <?php endif; ?>
      </div>
      <div class="vtx-panel-body" style="padding:0;">
        <?php if (empty($sessions)): ?>
          <div style="padding:1rem 1.25rem;font-size:.85rem;color:var(--ps-text-muted);">No tracked sessions.</div>
        <?php else: ?>
          <?php foreach ($sessions as $s): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 1.25rem;border-bottom:1px solid var(--ps-border);flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:.75rem;min-width:0;">
              <i class="pi pi-monitor" style="font-size:1.1rem;color:var(--ps-text-muted);flex-shrink:0;"></i>
              <div style="min-width:0;">
                <div style="font-weight:600;font-size:.85rem;">
                  <?php echo htmlspecialchars($s['device'] ?? 'Unknown device'); ?>
                  <?php if (!empty($s['is_current'])): ?>
                    <span class="vtx-tag success" style="margin-left:.4rem;">This device</span>
                  <?php endif; ?>
                </div>
                <div style="font-size:.75rem;color:var(--ps-text-muted);">
                  IP <?php echo htmlspecialchars($s['ip'] ?? '-'); ?>
                  &middot; Last active <?php echo !empty($s['last_active']) ? date('M j, Y H:i', strtotime($s['last_active'])) : '-'; ?>
                </div>
              </div>
            </div>
            <?php if (empty($s['is_current'])): ?>
            <form method="POST" action="{{baseUrl}}/admin/profile/sessions/<?php echo htmlspecialchars($s['id']); ?>/revoke">
              <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
            </form>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- Account info sidebar -->
  <div class="col-lg-4 offset-lg-1 col-xl-4 offset-xl-1">
    <div class="vtx-panel">
      <div class="vtx-panel-body" style="text-align:center;padding:1.5rem 1rem;">
        <?php if (!empty($avatar_url)): ?>
        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar"
             style="width:64px;height:64px;border-radius:50%;object-fit:cover;margin:0 auto .5rem;display:block;">
        <form method="POST" action="{{baseUrl}}/admin/profile/avatar/remove" style="margin-bottom:.75rem;">
          <input type="hidden" name="csrf_token" value="{{csrf_token}}">
          <button type="submit" class="btn btn-sm" style="border:none;background:none;color:var(--ps-danger,#dc2626);font-size:.75rem;padding:0;">
            Remove avatar
          </button>
        </form>
        <?php else: ?>
        <div class="vtx-avatar" style="width:64px;height:64px;font-size:1.5rem;margin:0 auto 1rem;">
          <?php echo htmlspecialchars(mb_strtoupper(mb_substr($user['name'] ?? 'U', 0, 1))); ?>
        </div>
        <?php endif; ?>
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
