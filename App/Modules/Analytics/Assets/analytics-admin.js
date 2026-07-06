/* ── admin/analytics/index.php: canvas chart renderer ── */
(function() {
  var canvas = document.getElementById('analytics-chart');
  if (!canvas) return;

  var labels = JSON.parse(canvas.dataset.labels || '[]');
  var values = JSON.parse(canvas.dataset.values || '[]');

  var ctx = canvas.getContext('2d');
  var W = canvas.offsetWidth || 600;
  var H = canvas.offsetHeight || 120;
  canvas.width  = W;
  canvas.height = H;

  var max   = Math.max.apply(null, values.concat([1]));
  var pad   = { top: 10, right: 8, bottom: 28, left: 38 };
  var plotW = W - pad.left - pad.right;
  var plotH = H - pad.top  - pad.bottom;
  var step  = plotW / (labels.length - 1 || 1);

  var style = getComputedStyle(document.documentElement);
  var clrLine   = style.getPropertyValue('--ps-primary').trim()    || '#4f46e5';
  var clrMuted  = style.getPropertyValue('--ps-text-muted').trim() || '#6b7280';
  var clrBorder = style.getPropertyValue('--ps-border').trim()     || '#e5e7eb';

  // Grid lines + Y-axis labels
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

  // Line stroke
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

  // X-axis labels (adaptive density based on range)
  ctx.fillStyle = clrMuted;
  ctx.font      = '10px system-ui, sans-serif';
  ctx.textAlign = 'center';
  var every = Math.max(1, Math.ceil(labels.length / 7));
  labels.forEach(function(lbl, i) {
    if (i % every === 0 || i === labels.length - 1) {
      ctx.fillText(lbl, pad.left + i * step, H - 6);
    }
  });
})();
