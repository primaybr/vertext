<?php
/**
 * Inline newsletter signup form - rendered by the [newsletter_signup] shortcode.
 * Expects: $baseUrl, $csrf_token, optional $flash (newsletter_flash session).
 */
?>
<div class="nls-box">
  <p class="nls-title">Subscribe to our newsletter</p>
  <p class="nls-sub">Get updates delivered straight to your inbox. Unsubscribe anytime.</p>

  <?php if (!empty($flash['message'])): ?>
  <div class="nls-msg nls-msg--flash <?php echo !empty($flash['success']) ? 'ok' : 'err'; ?>">
    <?php echo htmlspecialchars($flash['message']); ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="<?php echo $baseUrl; ?>/newsletter/subscribe">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
    <input type="hidden" name="source" value="shortcode">
    <div class="nls-row">
      <input class="nls-input" type="email" name="email" placeholder="you@example.com" required>
      <button class="nls-btn" type="submit">Subscribe</button>
    </div>
  </form>
</div>
