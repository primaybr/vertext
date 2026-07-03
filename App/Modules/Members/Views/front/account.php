<?php include __DIR__ . '/_styles.php'; ?>
<div class="mb-account-wrap">

  <div class="mb-account-head">
    <div>
      <h1 class="mb-title">My Account</h1>
      <div class="mb-meta">
        Member since <?php echo !empty($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : '-'; ?>
        <?php if (!empty($user['verified_at'])): ?> &middot; Email verified<?php endif; ?>
      </div>
    </div>
    <a class="mb-btn-outline" href="<?php echo $baseUrl; ?>/account/logout">Sign out</a>
  </div>

  <?php if (!empty($flash['message'])): ?>
  <div class="mb-alert mb-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?>">
    <?php echo htmlspecialchars($flash['message']); ?>
  </div>
  <?php endif; ?>

  <div class="mb-card">
    <form method="POST" action="<?php echo $baseUrl; ?>/account/update" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

      <h2 class="mb-section-title">Profile</h2>

      <div class="mb-field">
        <label class="mb-label" for="mb-name">Name</label>
        <input class="mb-input" type="text" id="mb-name" name="name" maxlength="120" required
               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" autocomplete="name">
      </div>

      <div class="mb-field">
        <label class="mb-label" for="mb-email">Email</label>
        <input class="mb-input" type="email" id="mb-email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
        <div class="mb-meta">Email addresses cannot be changed.</div>
      </div>

      <hr class="mb-divider">

      <h2 class="mb-section-title">Change Password</h2>
      <p class="mb-sub">Leave these fields blank to keep your current password.</p>

      <div class="mb-field">
        <label class="mb-label" for="mb-current">Current Password</label>
        <input class="mb-input" type="password" id="mb-current" name="current_password" autocomplete="current-password">
      </div>

      <div class="mb-field">
        <label class="mb-label" for="mb-password">New Password</label>
        <input class="mb-input" type="password" id="mb-password" name="password" minlength="8"
               placeholder="Minimum 8 characters" autocomplete="new-password">
      </div>

      <div class="mb-field">
        <label class="mb-label" for="mb-password-confirm">Confirm New Password</label>
        <input class="mb-input" type="password" id="mb-password-confirm" name="password_confirm" minlength="8"
               placeholder="Repeat new password" autocomplete="new-password">
      </div>

      <button type="submit" class="mb-btn">Save Changes</button>
    </form>
  </div>

</div>
