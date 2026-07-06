<div class="container ev-page">
  <div class="ev-page-header">
    <h1>Events</h1>
  </div>

  <div class="ev-layout">
    <div>
      <!-- Tabs -->
      <div class="ev-tabs">
        <button class="ev-tab active" data-tab="upcoming" onclick="switchTab('upcoming',this)">
          Upcoming (<?php echo count($upcoming); ?>)
        </button>
        <button class="ev-tab" data-tab="past" onclick="switchTab('past',this)">
          Past
        </button>
      </div>

      <!-- Upcoming -->
      <div id="ev-tab-upcoming" class="ev-list">
        <?php if (empty($upcoming)): ?>
        <div class="ev-empty"><p>No upcoming events at this time.</p></div>
        <?php else: ?>
        <?php foreach ($upcoming as $ev): ?>
        <div class="ev-card" id="ev-<?php echo date('Y-m-d', strtotime($ev['start_at'])); ?>">
          <div class="ev-date-badge">
            <div class="day"><?php echo date('j', strtotime($ev['start_at'])); ?></div>
            <div class="mon"><?php echo date('M', strtotime($ev['start_at'])); ?></div>
          </div>
          <div>
            <h3><a href="<?php echo $baseUrl; ?>/events/<?php echo htmlspecialchars($ev['slug']); ?>"><?php echo htmlspecialchars($ev['title']); ?></a></h3>
            <div class="ev-meta">
              <span><i class="pi pi-clock"></i><?php echo date('g:i A', strtotime($ev['start_at'])); ?></span>
              <?php if ($ev['location']): ?>
              <span><i class="pi pi-map-pin"></i><?php echo htmlspecialchars($ev['location']); ?></span>
              <?php endif; ?>
              <?php if ($ev['rsvp_count'] > 0): ?>
              <span><i class="pi pi-users"></i><?php echo (int) $ev['rsvp_count']; ?> interested</span>
              <?php endif; ?>
            </div>
            <?php if ($ev['description']): ?>
            <p class="ev-desc"><?php echo htmlspecialchars($ev['description']); ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Past -->
      <div id="ev-tab-past" class="ev-list" style="display:none;">
        <?php if (empty($past)): ?>
        <div class="ev-empty"><p>No past events.</p></div>
        <?php else: ?>
        <?php foreach ($past as $ev): ?>
        <div class="ev-card ev-card--past">
          <div class="ev-date-badge ev-date-badge--past">
            <div class="day"><?php echo date('j', strtotime($ev['start_at'])); ?></div>
            <div class="mon"><?php echo date('M', strtotime($ev['start_at'])); ?></div>
          </div>
          <div>
            <h3><a href="<?php echo $baseUrl; ?>/events/<?php echo htmlspecialchars($ev['slug']); ?>"><?php echo htmlspecialchars($ev['title']); ?></a></h3>
            <div class="ev-meta">
              <span><?php echo date('g:i A', strtotime($ev['start_at'])); ?></span>
              <?php if ($ev['location']): ?>
              <span><?php echo htmlspecialchars($ev['location']); ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Calendar sidebar -->
    <aside class="ev-cal-panel">
      <div class="ev-cal-header">
        <button class="ev-cal-nav" id="cal-prev">&lsaquo;</button>
        <span id="cal-title"></span>
        <button class="ev-cal-nav" id="cal-next">&rsaquo;</button>
      </div>
      <canvas id="evCal" width="230" height="190"
              data-dates="<?php echo htmlspecialchars(json_encode(array_unique(array_map(fn($e) => date('Y-m-d', strtotime($e['start_at'])), $upcoming))), ENT_QUOTES); ?>"></canvas>
    </aside>
  </div>
</div>
