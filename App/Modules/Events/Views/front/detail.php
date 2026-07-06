<div class="container ev-detail-page">

  <!-- Breadcrumb -->
  <nav class="ev-breadcrumb">
    <a href="<?php echo $baseUrl; ?>/events">Events</a>
    <span class="sep">/</span>
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
          <i class="pi pi-calendar"></i>
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
          <i class="pi pi-clock"></i>
          <div>
            <div class="ev-info-label">Time</div>
            <div class="ev-info-val">
              <?php echo date('g:i A', $start); ?>
              <?php if ($event['end_at']): ?>
              <span class="ev-info-note">
                &ndash; <?php echo date('g:i A', strtotime($event['end_at'])); ?>
              </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php if ($event['location']): ?>
        <div class="ev-info-row">
          <i class="pi pi-map-pin"></i>
          <div>
            <div class="ev-info-label">Location</div>
            <div class="ev-info-val"><?php echo htmlspecialchars($event['location']); ?></div>
          </div>
        </div>
        <?php endif; ?>
        <?php if ((int)$event['rsvp_count'] > 0): ?>
        <div class="ev-info-row">
          <i class="pi pi-users"></i>
          <div>
            <div class="ev-info-label">Attending</div>
            <div class="ev-info-val">
              <?php echo (int) $event['rsvp_count']; ?> registered
              <?php if (isset($spots_left) && $spots_left !== null): ?>
              <span class="ev-info-note">&middot; <?php echo (int) $spots_left; ?> spot(s) left</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <div class="ev-info-row">
          <i class="pi pi-calendar"></i>
          <div>
            <div class="ev-info-label">Calendar</div>
            <div class="ev-info-val">
              <a href="<?php echo $baseUrl; ?>/events/<?php echo htmlspecialchars($event['slug']); ?>/ical"
                 class="ev-info-link">
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
        <p class="ev-rsvp-done">
          <i class="pi pi-calendar me-1"></i> This event has passed.
        </p>
        <?php else: ?>
        <p class="ev-rsvp-prompt">
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
        <p class="ev-rsvp-note">
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
