<style>
.ev-page { padding: 3rem 0; }
.ev-layout { display: grid; grid-template-columns: 1fr 260px; gap: 2rem; align-items: start; }
.ev-tabs { display: flex; gap: 0; margin-bottom: 1.5rem; border-bottom: 2px solid var(--clr-border); }
.ev-tab { background: none; border: none; padding: .5rem 1rem; font-size: .9375rem; font-weight: 600;
  color: var(--clr-text-muted, #6b7280); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.ev-tab.active { color: var(--clr-accent); border-bottom-color: var(--clr-accent); }
.ev-list { display: grid; gap: 1rem; }
.ev-card { background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: 8px; padding: 1.25rem; display: grid; grid-template-columns: auto 1fr; gap: 1rem; }
.ev-date-badge { background: var(--clr-accent); color: #fff; border-radius: 6px; padding: .4rem .7rem; text-align: center; min-width: 52px; }
.ev-date-badge .day { font-size: 1.5rem; font-weight: 700; line-height: 1; }
.ev-date-badge .mon { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; }
.ev-card h3 { margin: 0 0 .3rem; font-size: 1.0625rem; }
.ev-card h3 a { color: var(--clr-text); text-decoration: none; }
.ev-card h3 a:hover { color: var(--clr-accent); }
.ev-meta { font-size: .8125rem; color: var(--clr-text-muted, #6b7280); display: flex; flex-wrap: wrap; gap: .5rem 1rem; margin-top: .25rem; }
.ev-desc { font-size: .9rem; margin: .5rem 0 0; color: var(--clr-text, #374151); }
.ev-empty { text-align: center; padding: 2rem; color: var(--clr-text-muted, #6b7280); }
/* Calendar sidebar */
.ev-cal-panel { background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: 8px; padding: 1rem; position: sticky; top: 1rem; }
.ev-cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .5rem; font-weight: 600; }
.ev-cal-nav { background: none; border: 1px solid var(--clr-border); border-radius: 4px; padding: .2rem .5rem; cursor: pointer; font-size: .875rem; color: var(--clr-text); }
canvas#evCal { width: 100%; display: block; }
@media (max-width: 720px) { .ev-layout { grid-template-columns: 1fr; } .ev-cal-panel { display: none; } }
</style>

<div class="container ev-page">
  <h1 style="margin-bottom:1.5rem;">Events</h1>

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
              <span><i class="pi pi-clock" style="margin-right:.3rem;"></i><?php echo date('g:i A', strtotime($ev['start_at'])); ?></span>
              <?php if ($ev['location']): ?>
              <span><i class="pi pi-map-pin" style="margin-right:.3rem;"></i><?php echo htmlspecialchars($ev['location']); ?></span>
              <?php endif; ?>
              <?php if ($ev['rsvp_count'] > 0): ?>
              <span><i class="pi pi-users" style="margin-right:.3rem;"></i><?php echo (int) $ev['rsvp_count']; ?> interested</span>
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
        <div class="ev-card" style="opacity:.7;">
          <div class="ev-date-badge" style="background:var(--clr-border,#e5e7eb);color:var(--clr-text-muted,#6b7280);">
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
      <canvas id="evCal" width="230" height="190"></canvas>
    </aside>
  </div>
</div>

<script>
function switchTab(tab, btn) {
  document.querySelectorAll('.ev-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  ['upcoming','past'].forEach(t => {
    document.getElementById('ev-tab-' + t).style.display = t === tab ? '' : 'none';
  });
}

// Canvas calendar
(function () {
  const eventDates = <?php
    $dates = array_map(fn($e) => date('Y-m-d', strtotime($e['start_at'])), $upcoming);
    echo json_encode(array_unique($dates));
  ?>;

  const canvas = document.getElementById('evCal');
  const ctx    = canvas.getContext('2d');
  const ratio  = window.devicePixelRatio || 1;
  canvas.width  = 230 * ratio;
  canvas.height = 190 * ratio;
  ctx.scale(ratio, ratio);

  const today  = new Date();
  let curYear  = today.getFullYear();
  let curMonth = today.getMonth();

  function draw() {
    const W = 230, H = 190;
    ctx.clearRect(0, 0, W, H);

    const monthNames = ['January','February','March','April','May','June',
                        'July','August','September','October','November','December'];
    const dayNames   = ['Su','Mo','Tu','We','Th','Fr','Sa'];

    document.getElementById('cal-title').textContent = monthNames[curMonth] + ' ' + curYear;

    const cellW = W / 7;
    const cellH = 24;
    const startY = 28;

    // Day headers
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--clr-text-muted') || '#9ca3af';
    ctx.font = '10px system-ui,sans-serif';
    ctx.textAlign = 'center';
    dayNames.forEach((d, i) => ctx.fillText(d, cellW * i + cellW / 2, startY));

    const firstDay  = new Date(curYear, curMonth, 1).getDay();
    const daysCount = new Date(curYear, curMonth + 1, 0).getDate();
    const accent    = getComputedStyle(document.documentElement).getPropertyValue('--clr-accent') || '#4f46e5';
    const textColor = getComputedStyle(document.documentElement).getPropertyValue('--clr-text') || '#111827';

    let col = firstDay, row = 0;
    for (let d = 1; d <= daysCount; d++) {
      const x  = col * cellW + cellW / 2;
      const y  = startY + 16 + row * cellH;
      const ds = curYear + '-' + String(curMonth + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
      const isToday   = (curYear === today.getFullYear() && curMonth === today.getMonth() && d === today.getDate());
      const hasEvent  = eventDates.includes(ds);

      if (isToday) {
        ctx.beginPath();
        ctx.arc(x, y - 4, 11, 0, Math.PI * 2);
        ctx.fillStyle = accent + '22';
        ctx.fill();
      }

      ctx.fillStyle = isToday ? accent : textColor;
      ctx.font = isToday ? 'bold 11px system-ui,sans-serif' : '11px system-ui,sans-serif';
      ctx.fillText(String(d), x, y);

      if (hasEvent) {
        ctx.beginPath();
        ctx.arc(x, y + 5, 2.5, 0, Math.PI * 2);
        ctx.fillStyle = accent;
        ctx.fill();
      }

      col++;
      if (col === 7) { col = 0; row++; }
    }
  }

  document.getElementById('cal-prev').addEventListener('click', () => {
    curMonth--;
    if (curMonth < 0) { curMonth = 11; curYear--; }
    draw();
  });
  document.getElementById('cal-next').addEventListener('click', () => {
    curMonth++;
    if (curMonth > 11) { curMonth = 0; curYear++; }
    draw();
  });

  canvas.addEventListener('click', function (e) {
    const rect   = canvas.getBoundingClientRect();
    const scaleX = 230 / rect.width;
    const cx     = (e.clientX - rect.left) * scaleX;
    const cy     = (e.clientY - rect.top)  * scaleX;
    const cellW  = 230 / 7, cellH = 24, startY = 28;
    const firstDay = new Date(curYear, curMonth, 1).getDay();

    let col = firstDay, row = 0;
    const daysCount = new Date(curYear, curMonth + 1, 0).getDate();
    for (let d = 1; d <= daysCount; d++) {
      const x = col * cellW + cellW / 2, y = startY + 16 + row * cellH;
      if (Math.abs(cx - x) < cellW / 2 && Math.abs(cy - y) < cellH / 2) {
        const ds = curYear + '-' + String(curMonth + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        if (eventDates.includes(ds)) {
          const el = document.getElementById('ev-' + ds);
          if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        break;
      }
      col++;
      if (col === 7) { col = 0; row++; }
    }
  });

  draw();
  // Redraw on dark mode toggle
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', draw);
  document.addEventListener('vtx:themeChanged', draw);
})();
</script>
