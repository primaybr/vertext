<div class="mb-auth-wrap">
  <div class="mb-card">
    <h1 class="mb-title">Sign In</h1>
    <p class="mb-sub">Welcome back.</p>

    <?php if (!empty($flash['message'])): ?>
    <div class="mb-alert mb-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?>">
      <?php echo htmlspecialchars($flash['message']); ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo $baseUrl; ?>/account/login" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

      <div class="mb-field">
        <label class="mb-label" for="mb-email">Email</label>
        <input class="mb-input" type="email" id="mb-email" name="email" required autocomplete="email" autofocus>
      </div>

      <div class="mb-field">
        <label class="mb-label" for="mb-password">Password</label>
        <input class="mb-input" type="password" id="mb-password" name="password" required autocomplete="current-password">
      </div>

      <button type="submit" class="mb-btn">Sign In</button>
    </form>

    <p class="mb-alt">
      No account yet? <a href="<?php echo $baseUrl; ?>/account/register">Create one</a>
    </p>
  </div>
</div>
