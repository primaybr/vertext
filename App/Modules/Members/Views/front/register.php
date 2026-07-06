<div class="mb-auth-wrap">
  <div class="mb-card">
    <h1 class="mb-title">Create Account</h1>
    <p class="mb-sub">Join to unlock member features.</p>

    <?php if (!empty($flash['message'])): ?>
    <div class="mb-alert mb-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?>">
      <?php echo htmlspecialchars($flash['message']); ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo $baseUrl; ?>/account/register" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

      <!-- Honeypot: humans never see or fill this -->
      <div class="mb-honeypot" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="mb-field">
        <label class="mb-label" for="mb-name">Name</label>
        <input class="mb-input" type="text" id="mb-name" name="name" maxlength="120" required
               value="<?php echo htmlspecialchars($old['name'] ?? ''); ?>" autocomplete="name" autofocus>
      </div>

      <div class="mb-field">
        <label class="mb-label" for="mb-email">Email</label>
        <input class="mb-input" type="email" id="mb-email" name="email" required
               value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" autocomplete="email">
      </div>

      <div class="mb-field">
        <label class="mb-label" for="mb-password">Password</label>
        <input class="mb-input" type="password" id="mb-password" name="password" minlength="8" required
               placeholder="Minimum 8 characters" autocomplete="new-password">
      </div>

      <div class="mb-field">
        <label class="mb-label" for="mb-password-confirm">Confirm Password</label>
        <input class="mb-input" type="password" id="mb-password-confirm" name="password_confirm" minlength="8" required
               placeholder="Repeat password" autocomplete="new-password">
      </div>

      <button type="submit" class="mb-btn">Create Account</button>
    </form>

    <p class="mb-alt">
      Already have an account? <a href="<?php echo $baseUrl; ?>/account/login">Sign in</a>
    </p>
  </div>
</div>
