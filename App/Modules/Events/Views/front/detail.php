<div class="container" style="padding: 3rem 0;">

  <!-- Breadcrumb -->
  <nav style="font-size:.8125rem;margin-bottom:1.5rem;color:var(--clr-text-muted,#6b7280);">
    <a href="<?php echo $baseUrl; ?>/events" style="color:var(--clr-text-muted);text-decoration:none;">Events</a>
    <span style="margin:0 .4rem;">/</span>
    <span><?php echo htmlspecialchars($event['title']); ?></span>
  </nav>

  <?php if (!empty($flash['message'])): ?>
  <div class="ev-alert ev-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> mb-4">
    <?php echo htmlspecialchars($flash['message']); ?>
  </div>
  <?php endif; ?>

  <div class="ev-detail-layout">
    <!-- Main content -->
    <article class="ev-detail-main">
      <?php if ($event['featured_image']): ?>
      <div class="ev-hero-img">
        <img src="<?php echo htmlspecialchars($event['featured_image']); ?>"
             alt="<?php echo htmlspecialchars($event['title']); ?>">
      </div>
      <?php endif; ?>

      <h1 class="ev-detail-title"><?php echo htmlspecialchars($event['title']); ?></h1>

      <?php if ($event['description']): ?>
      <p class="ev-detail-desc"><?php echo htmlspecialchars($event['description']); ?></p>
      <?php endif; ?>

      <?php if ($event['body']): ?>
      <div class="ev-detail-body">
        <?php echo nl2br(htmlspecialchars($event['body'])); ?>
      </div>
      <?php endif; ?>
    </article>

    <!-- Sidebar -->
    <aside class="ev-detail-aside">

      <!-- Date/Time card -->
      <div class="ev-info-card">
        <div class="ev-info-row">
          <i class="pi pi-calendar" style="color:var(--clr-accent);"></i>
          <div>
            <div class="ev-info-label">Date</div>
            <div class="ev-info-val">
              <?php
                $start = strtotime($event['start_at']);
                echo date('l, F j, Y', $start);
              ?>
            </div>
          </div>
        </div>
        <div class="ev-info-row">
          <i class="pi pi-clock" style="color:var(--clr-accent);"></i>
          <div>
            <div class="ev-info-label">Time</div>
            <div class="ev-info-val">
              <?php echo date('g:i A', $start); ?>
              <?php if ($event['end_at']): ?>
              <span style="color:var(--clr-text-muted,#6b7280);">
                &ndash; <?php echo date('g:i A', strtotime($event['end_at'])); ?>
              </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php if ($event['location']): ?>
        <div class="ev-info-row">
          <i class="pi pi-map-pin" style="color:var(--clr-accent);"></i>
          <div>
            <div class="ev-info-label">Location</div>
            <div class="ev-info-val"><?php echo htmlspecialchars($event['location']); ?></div>
          </div>
        </div>
        <?php endif; ?>
        <?php if ((int)$event['rsvp_count'] > 0): ?>
        <div class="ev-info-row">
          <i class="pi pi-users" style="color:var(--clr-accent);"></i>
          <div>
            <div class="ev-info-label">Attending</div>
            <div class="ev-info-val">
              <?php echo (int) $event['rsvp_count']; ?> registered
              <?php if (isset($spots_left) && $spots_left !== null): ?>
              <span style="color:var(--clr-text-muted,#9ca3af);font-weight:400;">&middot; <?php echo (int) $spots_left; ?> spot(s) left</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <div class="ev-info-row">
          <i class="pi pi-calendar" style="color:var(--clr-accent);"></i>
          <div>
            <div class="ev-info-label">Calendar</div>
            <div class="ev-info-val">
              <a href="<?php echo $baseUrl; ?>/events/<?php echo htmlspecialchars($event['slug']); ?>/ical"
                 style="color:var(--clr-accent);text-decoration:none;font-size:.875rem;">
                Add to calendar (.ics)
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- RSVP card -->
      <?php
        $past   = strtotime($event['start_at']) < time() && empty($event['recurrence_rule']);
        $isFull = isset($spots_left) && $spots_left !== null && $spots_left <= 0;
      ?>
      <div class="ev-rsvp-card">
        <?php if ($past): ?>
        <p class="ev-rsvp-done" style="color:var(--clr-text-muted,#6b7280);">
          <i class="pi pi-calendar me-1"></i> This event has passed.
        </p>
        <?php else: ?>
        <p style="margin:0 0 .75rem;font-size:.9375rem;font-weight:600;">
          <?php echo $isFull ? 'Event is full - join the waiting list' : 'Register for this event'; ?>
        </p>
        <form method="POST" action="<?php echo $baseUrl; ?>/events/<?php echo htmlspecialchars($event['slug']); ?>/rsvp">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <div class="ev-rsvp-field">
            <input class="ev-rsvp-input" type="text" name="name" maxlength="120" required
                   placeholder="Your name"
                   value="<?php echo htmlspecialchars($member['name'] ?? ''); ?>">
          </div>
          <div class="ev-rsvp-field">
            <input class="ev-rsvp-input" type="email" name="email" required
                   placeholder="you@example.com"
                   value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>">
          </div>
          <?php if (!empty($tickets)): ?>
          <div class="ev-rsvp-field">
            <select class="ev-rsvp-input" name="ticket" required>
              <option value="">-- Choose a ticket --</option>
              <?php foreach ($tickets as $t): ?>
              <option value="<?php echo htmlspecialchars($t['name'] ?? ''); ?>">
                <?php
                  echo htmlspecialchars($t['name'] ?? '');
                  $price = (float) ($t['price'] ?? 0);
                  echo $price > 0 ? ' - ' . number_format($price, 2) : ' - Free';
                ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <button type="submit" class="btn-rsvp">
            <i class="pi pi-check me-1"></i> <?php echo $isFull ? 'Join Waiting List' : 'Register'; ?>
          </button>
        </form>
        <p style="font-size:.75rem;margin:.5rem 0 0;color:var(--clr-text-muted,#9ca3af);">
          You will receive a confirmation email with a calendar invite and a cancellation link.
        </p>
        <?php endif; ?>
      </div>

      <!-- Back link -->
      <a href="<?php echo $baseUrl; ?>/events" class="ev-back-link">
        <i class="pi pi-arrow-left me-1"></i> All Events
      </a>
    </aside>
  </div>
</div>

<style>
.ev-detail-layout { display:grid; grid-template-columns:1fr 280px; gap:2rem; align-items:start; }
.ev-hero-img { margin-bottom:1.5rem; border-radius:8px; overflow:hidden; }
.ev-hero-img img { width:100%; max-height:360px; object-fit:cover; display:block; }
.ev-detail-title { font-size:1.75rem; font-weight:700; margin:0 0 .75rem; color:var(--clr-text); }
.ev-detail-desc { font-size:1.0625rem; color:var(--clr-text-muted,#6b7280); margin:0 0 1.5rem; line-height:1.6; }
.ev-detail-body { font-size:.9375rem; line-height:1.75; color:var(--clr-text); }
.ev-info-card { background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:8px; padding:1rem; margin-bottom:1rem; display:grid; gap:.85rem; }
.ev-info-row { display:grid; grid-template-columns:20px 1fr; gap:.6rem; align-items:start; }
.ev-info-label { font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:var(--clr-text-muted,#9ca3af); line-height:1.2; }
.ev-info-val { font-size:.9375rem; font-weight:500; line-height:1.4; }
.ev-rsvp-card { background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:8px; padding:1rem; margin-bottom:1rem; }
.ev-rsvp-done { margin:0; font-size:.9375rem; }
.ev-rsvp-field { margin-bottom:.6rem; }
.ev-rsvp-input { width:100%; padding:.5rem .7rem; border:1px solid var(--clr-border); border-radius:6px;
  font-size:.875rem; font-family:inherit; background:var(--clr-bg); color:var(--clr-text); box-sizing:border-box; }
.ev-rsvp-input:focus { outline:none; border-color:var(--clr-accent); }
.btn-rsvp { display:block; width:100%; padding:.625rem 1rem; background:var(--clr-accent); color:#fff; border:none; border-radius:6px; font-size:.9375rem; font-weight:600; cursor:pointer; text-align:center; }
.btn-rsvp:hover { opacity:.9; }
.ev-back-link { display:inline-flex; align-items:center; font-size:.875rem; color:var(--clr-text-muted,#6b7280); text-decoration:none; }
.ev-back-link:hover { color:var(--clr-accent); }
.ev-alert { padding:.75rem 1rem; border-radius:6px; font-size:.9rem; }
.ev-alert-success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
.ev-alert-error   { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
.ev-alert-info    { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
@media (prefers-color-scheme: dark) {
  .ev-alert-success { background:#14532d; color:#bbf7d0; border-color:#166534; }
  .ev-alert-error   { background:#7f1d1d; color:#fecaca; border-color:#991b1b; }
  .ev-alert-info    { background:#1e3a5f; color:#bfdbfe; border-color:#1d4ed8; }
}
@media (max-width: 720px) { .ev-detail-layout { grid-template-columns:1fr; } }
</style>
