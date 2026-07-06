<?php
$today      = date('Y-m-d');
$preset7    = date('Y-m-d', strtotime('-6 days'));
$preset30   = date('Y-m-d', strtotime('-29 days'));
$preset90   = date('Y-m-d', strtotime('-89 days'));

$isPreset7  = ($from === $preset7  && $to === $today);
$isPreset30 = ($from === $preset30 && $to === $today);
$isPreset90 = ($from === $preset90 && $to === $today);
$isToday    = ($from === $today    && $to === $today);
$isCustom   = !$isToday && !$isPreset7 && !$isPreset30 && !$isPreset90;

function vtx_delta_html(?float $delta): string {
    if ($delta === null) return '<span style="font-size:.7rem;color:var(--ps-text-muted);">no prior data</span>';
    $dir   = $delta >= 0 ? '▲' : '▼';
    $color = $delta >= 0 ? 'var(--ps-success,#16a34a)' : 'var(--ps-danger,#dc2626)';
    return '<span style="font-size:.75rem;font-weight:600;color:' . $color . ';">'
        . $dir . ' ' . number_format(abs($delta), 1) . '%</span>';
}
?>

<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-chart-bar me-2 text-primary"></i>Analytics</h1>
    <p class="vtx-page-desc">Front-end page view statistics. IPs are hashed daily for privacy.</p>
  </div>
  <div>
    <a href="{{baseUrl}}/admin/analytics/export?from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>"
       class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-download me-1"></i> Export CSV
    </a>
  </div>
</div>

<!-- Date range filter -->
<div class="vtx-panel mb-3">
  <div class="vtx-panel-body" style="padding:.625rem 1rem;">
    <form method="GET" action="{{baseUrl}}/admin/analytics"
          style="display:flex;gap:.625rem;align-items:center;flex-wrap:wrap;">
      <div style="display:flex;gap:.375rem;align-items:center;flex-shrink:0;">
        <input type="date" class="form-control form-control-sm" name="from"
               value="<?php echo $from; ?>" max="<?php echo $today; ?>"
               style="width:140px;">
        <span style="font-size:.8125rem;color:var(--ps-text-muted);">to</span>
        <input type="date" class="form-control form-control-sm" name="to"
               value="<?php echo $to; ?>" max="<?php echo $today; ?>"
               style="width:140px;">
        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
      </div>
      <div style="display:flex;gap:.375rem;flex-wrap:wrap;">
        <a href="{{baseUrl}}/admin/analytics?from=<?php echo $today; ?>&to=<?php echo $today; ?>"
           class="btn btn-sm <?php echo $isToday ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Today</a>
        <a href="{{baseUrl}}/admin/analytics?from=<?php echo $preset7; ?>&to=<?php echo $today; ?>"
           class="btn btn-sm <?php echo $isPreset7 ? 'btn-secondary' : 'btn-outline-secondary'; ?>">7 Days</a>
        <a href="{{baseUrl}}/admin/analytics?from=<?php echo $preset30; ?>&to=<?php echo $today; ?>"
           class="btn btn-sm <?php echo $isPreset30 ? 'btn-secondary' : 'btn-outline-secondary'; ?>">30 Days</a>
        <a href="{{baseUrl}}/admin/analytics?from=<?php echo $preset90; ?>&to=<?php echo $today; ?>"
           class="btn btn-sm <?php echo $isPreset90 ? 'btn-secondary' : 'btn-outline-secondary'; ?>">90 Days</a>
      </div>
      <?php if ($isCustom): ?>
      <span style="font-size:.75rem;color:var(--ps-text-muted);">
        <?php echo date('M j, Y', strtotime($from)); ?> - <?php echo date('M j, Y', strtotime($to)); ?>
        (<?php echo $days; ?> day<?php echo $days !== 1 ? 's' : ''; ?>)
      </span>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Summary cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem;">

  <!-- Selected period total -->
  <div class="vtx-panel" style="padding:1.25rem;">
    <div style="font-size:.75rem;font-weight:500;color:var(--ps-text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem;">
      Selected Period
    </div>
    <div style="font-size:2rem;font-weight:700;color:var(--ps-text-primary);line-height:1;">
      <?php echo number_format($viewsPeriod); ?>
    </div>
    <div style="display:flex;align-items:center;gap:.375rem;margin-top:.375rem;flex-wrap:wrap;">
      <?php echo vtx_delta_html($deltaPeriod); ?>
      <span style="font-size:.7rem;color:var(--ps-text-muted);">vs prev. <?php echo $days; ?> day<?php echo $days !== 1 ? 's' : ''; ?></span>
    </div>
    <div style="font-size:.7rem;color:var(--ps-text-muted);margin-top:.25rem;">
      prev: <?php echo number_format($viewsPrevPeriod); ?> views
    </div>
  </div>

  <!-- Today vs yesterday -->
  <div class="vtx-panel" style="padding:1.25rem;">
    <div style="font-size:.75rem;font-weight:500;color:var(--ps-text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem;">
      Today
    </div>
    <div style="font-size:2rem;font-weight:700;color:var(--ps-text-primary);line-height:1;">
      <?php echo number_format($viewsToday); ?>
    </div>
    <div style="display:flex;align-items:center;gap:.375rem;margin-top:.375rem;">
      <?php echo vtx_delta_html($deltaToday); ?>
      <span style="font-size:.7rem;color:var(--ps-text-muted);">vs yesterday</span>
    </div>
    <div style="font-size:.7rem;color:var(--ps-text-muted);margin-top:.25rem;">
      yesterday: <?php echo number_format($viewsYesterday); ?> views
    </div>
  </div>

  <!-- Daily average -->
  <div class="vtx-panel" style="padding:1.25rem;">
    <div style="font-size:.75rem;font-weight:500;color:var(--ps-text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem;">
      Daily Average
    </div>
    <div style="font-size:2rem;font-weight:700;color:var(--ps-text-primary);line-height:1;">
      <?php echo number_format($dailyAvg, 1); ?>
    </div>
    <div style="font-size:.7rem;color:var(--ps-text-muted);margin-top:.375rem;">
      over <?php echo $days; ?> day<?php echo $days !== 1 ? 's' : ''; ?>
    </div>
  </div>

  <!-- Unique visitors -->
  <div class="vtx-panel" style="padding:1.25rem;">
    <div style="font-size:.75rem;font-weight:500;color:var(--ps-text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem;">
      Unique Visitors
    </div>
    <div style="font-size:2rem;font-weight:700;color:var(--ps-text-primary);line-height:1;">
      <?php echo number_format($uniqueVisitors); ?>
    </div>
    <div style="font-size:.7rem;color:var(--ps-text-muted);margin-top:.375rem;">
      distinct IPs (hashed, daily rotation)
    </div>
  </div>

  <!-- Device breakdown -->
  <?php
  $mobile  = (int) ($deviceBreakdown['mobile']  ?? 0);
  $desktop = (int) ($deviceBreakdown['desktop'] ?? 0);
  $devTotal = $mobile + $desktop;
  $mobPct  = $devTotal > 0 ? round($mobile  / $devTotal * 100) : 0;
  $dskPct  = $devTotal > 0 ? round($desktop / $devTotal * 100) : 0;
  ?>
  <div class="vtx-panel" style="padding:1.25rem;">
    <div style="font-size:.75rem;font-weight:500;color:var(--ps-text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem;">
      Device Breakdown
    </div>
    <?php if ($devTotal > 0): ?>
    <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem;">
      <span style="font-size:.8125rem;min-width:72px;">
        <i class="pi pi-monitor" style="font-size:.75rem;"></i> Desktop
      </span>
      <div style="flex:1;background:var(--ps-border);border-radius:4px;height:8px;overflow:hidden;">
        <div style="width:<?php echo $dskPct; ?>%;background:var(--ps-primary);height:100%;"></div>
      </div>
      <span style="font-size:.75rem;font-weight:600;min-width:36px;text-align:right;"><?php echo $dskPct; ?>%</span>
    </div>
    <div style="display:flex;gap:.5rem;align-items:center;">
      <span style="font-size:.8125rem;min-width:72px;">
        <i class="pi pi-mobile" style="font-size:.75rem;"></i> Mobile
      </span>
      <div style="flex:1;background:var(--ps-border);border-radius:4px;height:8px;overflow:hidden;">
        <div style="width:<?php echo $mobPct; ?>%;background:var(--ps-info,#0ea5e9);height:100%;"></div>
      </div>
      <span style="font-size:.75rem;font-weight:600;min-width:36px;text-align:right;"><?php echo $mobPct; ?>%</span>
    </div>
    <div style="font-size:.7rem;color:var(--ps-text-muted);margin-top:.375rem;">
      based on <?php echo number_format($devTotal); ?> tracked visits
    </div>
    <?php else: ?>
    <div style="font-size:.8125rem;color:var(--ps-text-muted);">No device data yet</div>
    <div style="font-size:.7rem;color:var(--ps-text-muted);margin-top:.25rem;">New visits will be classified as mobile or desktop</div>
    <?php endif; ?>
  </div>

</div>

<!-- Daily trend chart -->
<div class="vtx-panel mb-4">
  <div class="vtx-panel-head">
    <h2 class="vtx-panel-title">
      Daily Views
      <span style="font-size:.8125rem;font-weight:400;color:var(--ps-text-muted);">
        (<?php echo date('M j', strtotime($from)); ?> - <?php echo date('M j, Y', strtotime($to)); ?>)
      </span>
    </h2>
  </div>
  <div class="vtx-panel-body" style="padding:1rem;">
    <canvas id="analytics-chart" height="80"
            data-labels='<?php echo htmlspecialchars(json_encode($chartLabels), ENT_QUOTES); ?>'
            data-values='<?php echo htmlspecialchars(json_encode($chartValues), ENT_QUOTES); ?>'></canvas>
  </div>
</div>

<!-- Top pages + Top referrers -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.25rem;">

  <!-- Top pages -->
  <div class="vtx-panel">
    <div class="vtx-panel-head">
      <h2 class="vtx-panel-title">Top Pages
        <span style="font-size:.8125rem;font-weight:400;color:var(--ps-text-muted);">(selected period)</span>
      </h2>
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
      <h2 class="vtx-panel-title">Top Referrers
        <span style="font-size:.8125rem;font-weight:400;color:var(--ps-text-muted);">(selected period)</span>
      </h2>
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
