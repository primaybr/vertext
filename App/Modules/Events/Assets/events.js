/* Events Module - Front-end scripts */

/* -- front/index.php: tab switcher -- */
function switchTab(tab, btn) {
  document.querySelectorAll('.ev-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  ['upcoming', 'past'].forEach(t => {
    var el = document.getElementById('ev-tab-' + t);
    if (el) el.style.display = t === tab ? '' : 'none';
  });
}

/* -- front/index.php: canvas calendar renderer -- */
(function () {
  const canvas = document.getElementById('evCal');
  if (!canvas) return;

  const eventDates = JSON.parse(canvas.dataset.dates || '[]');

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

    const titleEl = document.getElementById('cal-title');
    if (titleEl) titleEl.textContent = monthNames[curMonth] + ' ' + curYear;

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

  const prevBtn = document.getElementById('cal-prev');
  if (prevBtn) prevBtn.addEventListener('click', () => {
    curMonth--;
    if (curMonth < 0) { curMonth = 11; curYear--; }
    draw();
  });
  const nextBtn = document.getElementById('cal-next');
  if (nextBtn) nextBtn.addEventListener('click', () => {
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
