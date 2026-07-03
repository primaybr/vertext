<?php
/**
 * Inline newsletter signup form - rendered by the [newsletter_signup] shortcode.
 * Expects: $baseUrl, $csrf_token, optional $flash (newsletter_flash session).
 */
?>
<style>
.nls-box { background: var(--clr-surface, #fff); border: 1px solid var(--clr-border, #e5e7eb);
  border-radius: 8px; padding: 1.5rem; margin: 1.5rem 0; }
.nls-title { font-size: 1.1rem; font-weight: 700; margin: 0 0 .25rem; color: var(--clr-text, #111827); }
.nls-sub { font-size: .85rem; color: var(--clr-text-muted, var(--clr-muted, #6b7280)); margin: 0 0 1rem; }
.nls-row { display: flex; gap: .5rem; flex-wrap: wrap; }
.nls-input { flex: 1; min-width: 200px; padding: .55rem .75rem; font-size: .9rem;
  border: 1px solid var(--clr-border, #d1d5db); border-radius: 6px;
  background: var(--clr-bg, #fff); color: var(--clr-text, #111827); box-sizing: border-box; }
.nls-input:focus { outline: 2px solid var(--clr-accent, #4f46e5); outline-offset: 1px; }
.nls-btn { padding: .55rem 1.25rem; font-size: .9rem; font-weight: 600; color: #fff;
  background: var(--clr-accent, #4f46e5); border: none; border-radius: 6px; cursor: pointer; }
.nls-btn:hover { opacity: .9; }
.nls-msg { font-size: .85rem; margin-top: .75rem; padding: .5rem .75rem; border-radius: 6px; }
.nls-msg.ok  { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.nls-msg.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
[data-theme="dark"] .nls-msg.ok  { background: #064e3b; color: #a7f3d0; border-color: #065f46; }
[data-theme="dark"] .nls-msg.err { background: #7f1d1d; color: #fecaca; border-color: #991b1b; }
</style>
<div class="nls-box">
  <p class="nls-title">Subscribe to our newsletter</p>
  <p class="nls-sub">Get updates delivered straight to your inbox. Unsubscribe anytime.</p>

  <?php if (!empty($flash['message'])): ?>
  <div class="nls-msg <?php echo !empty($flash['success']) ? 'ok' : 'err'; ?>" style="margin:0 0 .75rem;">
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
