<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-chart-bar me-2 text-primary"></i>Analytics</h1>
    <p class="vtx-page-desc">Front-end page view statistics. IPs are hashed daily for privacy.</p>
  </div>
</div>

<!-- Summary cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
  <div class="vtx-panel" style="padding:1.25rem;text-align:center;">
    <div style="font-size:2rem;font-weight:700;color:var(--ps-primary);"><?php echo number_format($viewsToday); ?></div>
    <div style="font-size:.8125rem;color:var(--ps-text-muted);margin-top:.25rem;">Views Today</div>
  </div>
  <div class="vtx-panel" style="padding:1.25rem;text-align:center;">
    <div style="font-size:2rem;font-weight:700;color:var(--ps-primary);"><?php echo number_format($viewsWeek); ?></div>
    <div style="font-size:.8125rem;color:var(--ps-text-muted);margin-top:.25rem;">Last 7 Days</div>
  </div>
  <div class="vtx-panel" style="padding:1.25rem;text-align:center;">
    <div style="font-size:2rem;font-weight:700;color:var(--ps-primary);"><?php echo number_format($viewsMonth); ?></div>
    <div style="font-size:.8125rem;color:var(--ps-text-muted);margin-top:.25rem;">Last 30 Days</div>
  </div>
</div>

<!-- Daily trend chart -->
<div class="vtx-panel mb-4">
  <div class="vtx-panel-head">
    <h2 class="vtx-panel-title">Daily Views (last 30 days)</h2>
  </div>
  <div class="vtx-panel-body" style="padding:1rem;">
    <canvas id="analytics-chart" height="80"></canvas>
  </div>
</div>

<!-- Top pages + Top referrers -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.25rem;">

  <!-- Top pages -->
  <div class="vtx-panel">
    <div class="vtx-panel-head">
      <h2 class="vtx-panel-title">Top Pages <span class="cell-muted" style="font-size:.8125rem;font-weight:400;">(30 days)</span></h2>
    </div>
    <?php if (empty($topPages)): ?>
    <div class="vtx-empty" style="padding:2rem;">
      <div class="vtx-empty-title">No data yet</div>
      <div class="vtx-empty-desc">Page views will appear here once visitors arrive.</div>
    </div>
    <?php else: ?>
    <div class="vtx-table-wrap">
      <table class="vtx-table">
        <thead>
          <tr>
            <th>Page</th>
            <th style="width:70px;text-align:right;">Views</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topPages as $pg): ?>
          <tr>
            <td>
              <div style="font-size:.8125rem;font-weight:500;"><?php echo htmlspecialchars($pg['page_title'] ?? $pg['url_path']); ?></div>
              <div style="font-size:.75rem;color:var(--ps-text-muted);"><?php echo htmlspecialchars($pg['url_path']); ?></div>
            </td>
            <td style="text-align:right;font-weight:600;"><?php echo number_format((int) $pg['views']); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Top referrers -->
  <div class="vtx-panel">
    <div class="vtx-panel-head">
      <h2 class="vtx-panel-title">Top Referrers <span class="cell-muted" style="font-size:.8125rem;font-weight:400;">(30 days)</span></h2>
    </div>
    <?php if (empty($topReferrers)): ?>
    <div class="vtx-empty" style="padding:2rem;">
      <div class="vtx-empty-title">No referrer data</div>
      <div class="vtx-empty-desc">Referrers show when visitors arrive from other sites.</div>
    </div>
    <?php else: ?>
    <div class="vtx-table-wrap">
      <table class="vtx-table">
        <thead>
          <tr>
            <th>Referrer</th>
            <th style="width:70px;text-align:right;">Views</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topReferrers as $ref): ?>
          <tr>
            <td style="font-size:.8125rem;"><?php echo htmlspecialchars($ref['referrer_host']); ?></td>
            <td style="text-align:right;font-weight:600;"><?php echo number_format((int) $ref['views']); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
(function() {
  var labels = <?php echo json_encode($chartLabels); ?>;
  var values = <?php echo json_encode($chartValues); ?>;

  var canvas = document.getElementById('analytics-chart');
  if (!canvas) return;
  var ctx = canvas.getContext('2d');
  var W = canvas.offsetWidth || 600;
  var H = canvas.offsetHeight || 120;
  canvas.width  = W;
  canvas.height = H;

  var max   = Math.max.apply(null, values.concat([1]));
  var pad   = { top: 10, right: 8, bottom: 28, left: 36 };
  var plotW = W - pad.left - pad.right;
  var plotH = H - pad.top  - pad.bottom;
  var step  = plotW / (labels.length - 1 || 1);

  var style = getComputedStyle(document.documentElement);
  var clrLine   = style.getPropertyValue('--ps-primary').trim()  || '#4f46e5';
  var clrMuted  = style.getPropertyValue('--ps-text-muted').trim() || '#6b7280';
  var clrBorder = style.getPropertyValue('--ps-border').trim()  || '#e5e7eb';

  // Grid lines
  ctx.strokeStyle = clrBorder;
  ctx.lineWidth   = 1;
  [0, 0.25, 0.5, 0.75, 1].forEach(function(frac) {
    var y = pad.top + plotH * (1 - frac);
    ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(pad.left + plotW, y); ctx.stroke();
    ctx.fillStyle = clrMuted;
    ctx.font      = '10px system-ui, sans-serif';
    ctx.textAlign = 'right';
    ctx.fillText(Math.round(max * frac), pad.left - 4, y + 3);
  });

  // Area fill
  ctx.beginPath();
  ctx.moveTo(pad.left, pad.top + plotH);
  values.forEach(function(v, i) {
    var x = pad.left + i * step;
    var y = pad.top + plotH - (v / max) * plotH;
    ctx.lineTo(x, y);
  });
  ctx.lineTo(pad.left + (values.length - 1) * step, pad.top + plotH);
  ctx.closePath();
  ctx.fillStyle = clrLine + '22';
  ctx.fill();

  // Line
  ctx.beginPath();
  ctx.strokeStyle = clrLine;
  ctx.lineWidth   = 2;
  ctx.lineJoin    = 'round';
  values.forEach(function(v, i) {
    var x = pad.left + i * step;
    var y = pad.top + plotH - (v / max) * plotH;
    i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
  });
  ctx.stroke();

  // X-axis labels (every ~7 days)
  ctx.fillStyle = clrMuted;
  ctx.font      = '10px system-ui, sans-serif';
  ctx.textAlign = 'center';
  var every = Math.ceil(labels.length / 6);
  labels.forEach(function(lbl, i) {
    if (i % every === 0 || i === labels.length - 1) {
      ctx.fillText(lbl, pad.left + i * step, H - 6);
    }
  });
})();
</script>
