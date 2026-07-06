<?php
// Revision diff partial - loaded via vtx-form-modal AJAX GET
// Compares a saved revision against the current page state

function vtxPageRevDiff(string $a, string $b): string {
    $a = strip_tags($a);
    $b = strip_tags($b);
    if ($a === $b) {
        return '';
    }
    $at = preg_split('/(\s+)/', $a, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $bt = preg_split('/(\s+)/', $b, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $m  = count($at);
    $n  = count($bt);
    if ($m * $n > 400000) {
        $ins = 'background:rgba(34,197,94,.22);text-decoration:none;border-radius:2px;padding:0 1px;';
        $del = 'background:rgba(239,68,68,.22);border-radius:2px;padding:0 1px;';
        return '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">'
             . '<div><strong style="font-size:.75rem;color:var(--ps-text-muted);">Revision (before)</strong>'
             . '<div style="white-space:pre-wrap;font-size:.8125rem;margin-top:.375rem;">' . htmlspecialchars($a) . '</div></div>'
             . '<div><strong style="font-size:.75rem;color:var(--ps-text-muted);">Current (after)</strong>'
             . '<div style="white-space:pre-wrap;font-size:.8125rem;margin-top:.375rem;">' . htmlspecialchars($b) . '</div></div>'
             . '</div>'
             . '<div style="font-size:.75rem;color:var(--ps-text-muted);margin-top:.375rem;">'
             . '<ins style="' . $ins . '">Added</ins> &nbsp; <del style="' . $del . '">Removed</del>'
             . '</div>';
    }
    $dp = [];
    for ($i = 0; $i <= $m; $i++) {
        $dp[$i] = array_fill(0, $n + 1, 0);
    }
    for ($i = 1; $i <= $m; $i++) {
        for ($j = 1; $j <= $n; $j++) {
            $dp[$i][$j] = $at[$i - 1] === $bt[$j - 1]
                ? $dp[$i - 1][$j - 1] + 1
                : max($dp[$i - 1][$j], $dp[$i][$j - 1]);
        }
    }
    $diff = [];
    $i = $m;
    $j = $n;
    while ($i > 0 || $j > 0) {
        if ($i > 0 && $j > 0 && $at[$i - 1] === $bt[$j - 1]) {
            array_unshift($diff, ['=', $at[$i - 1]]);
            $i--;
            $j--;
        } elseif ($j > 0 && ($i === 0 || $dp[$i][$j - 1] >= $dp[$i - 1][$j])) {
            array_unshift($diff, ['+', $bt[$j - 1]]);
            $j--;
        } else {
            array_unshift($diff, ['-', $at[$i - 1]]);
            $i--;
        }
    }
    $ins  = 'background:rgba(34,197,94,.22);text-decoration:none;border-radius:2px;padding:0 1px;';
    $del  = 'background:rgba(239,68,68,.22);border-radius:2px;padding:0 1px;';
    $html = '';
    foreach ($diff as [$type, $text]) {
        $t = htmlspecialchars($text);
        $html .= match ($type) {
            '+' => "<ins style=\"{$ins}\">{$t}</ins>",
            '-' => "<del style=\"{$del}\">{$t}</del>",
            default => $t,
        };
    }
    return $html;
}

$r   = $rev;
$cur = $page;

$statusColor = function (string $s): string {
    return match ($s) { 'published' => 'success', 'draft' => 'warning', 'scheduled' => 'primary', default => 'gray' };
};

$fields = [
    ['label' => 'Title',            'rev' => $r['title']            ?? '', 'cur' => $cur['title']            ?? ''],
    ['label' => 'Status',           'rev' => $r['status']           ?? '', 'cur' => $cur['status']           ?? '', 'badge' => true],
    ['label' => 'Slug',             'rev' => $r['slug']             ?? '', 'cur' => $cur['slug']             ?? '', 'note' => 'Slug is not restored to avoid breaking links.'],
    ['label' => 'Excerpt',          'rev' => $r['excerpt']          ?? '', 'cur' => $cur['excerpt']          ?? ''],
    ['label' => 'Meta Title',       'rev' => $r['meta_title']       ?? '', 'cur' => $cur['meta_title']       ?? ''],
    ['label' => 'Meta Description', 'rev' => $r['meta_description'] ?? '', 'cur' => $cur['meta_description'] ?? ''],
];

$bodyDiff    = vtxPageRevDiff($r['body'] ?? '', $cur['content'] ?? '');
$bodyChanged = $bodyDiff !== '';
?>
<div style="padding:0 .125rem;">

  <!-- Metadata bar -->
  <div style="margin-bottom:1rem;padding-bottom:.75rem;border-bottom:1px solid var(--ps-border);font-size:.8125rem;color:var(--ps-text-muted);">
    Revision #<?php echo (int) ($r['revision_number'] ?? 0); ?> &middot;
    Saved <?php echo !empty($r['created_at']) ? date('M d, Y \a\t H:i', strtotime($r['created_at'])) : '-'; ?>
    <?php if (!empty($r['created_by_name'])): ?>
    &middot; by <strong><?php echo htmlspecialchars($r['created_by_name']); ?></strong>
    <?php endif; ?>
  </div>

  <!-- Field comparison grid -->
  <div style="margin-bottom:1.25rem;">
    <div style="display:grid;grid-template-columns:130px 1fr 1fr;gap:.375rem 1rem;padding:.375rem 0;font-size:.75rem;font-weight:600;color:var(--ps-text-muted);border-bottom:2px solid var(--ps-border);">
      <span>Field</span>
      <span>Revision (before)</span>
      <span>Current (after)</span>
    </div>
    <?php foreach ($fields as $f):
      $changed = ($f['rev'] ?? '') !== ($f['cur'] ?? '');
      $rowBg   = $changed ? 'background:rgba(234,179,8,.1);' : '';
    ?>
    <div style="display:grid;grid-template-columns:130px 1fr 1fr;gap:.375rem 1rem;padding:.5rem 0;border-bottom:1px solid var(--ps-border);font-size:.875rem;align-items:start;<?php echo $rowBg; ?>">
      <span style="color:var(--ps-text-muted);font-weight:500;font-size:.8125rem;">
        <?php echo htmlspecialchars($f['label']); ?>
        <?php if ($changed): ?>
        <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--ps-warning);margin-left:4px;vertical-align:middle;" title="Changed"></span>
        <?php endif; ?>
      </span>
      <span style="word-break:break-word;">
        <?php if (!empty($f['badge'])): ?>
          <span class="vtx-tag <?php echo $statusColor($f['rev']); ?>"><?php echo ucfirst(htmlspecialchars($f['rev'] ?: '-')); ?></span>
        <?php elseif ($f['rev'] !== ''): ?>
          <?php echo htmlspecialchars($f['rev']); ?>
        <?php else: ?>
          <span style="color:var(--ps-text-muted);font-style:italic;font-size:.8125rem;">—</span>
        <?php endif; ?>
      </span>
      <span style="word-break:break-word;">
        <?php if (!empty($f['badge'])): ?>
          <span class="vtx-tag <?php echo $statusColor($f['cur']); ?>"><?php echo ucfirst(htmlspecialchars($f['cur'] ?: '-')); ?></span>
        <?php elseif ($f['cur'] !== ''): ?>
          <?php echo htmlspecialchars($f['cur']); ?>
        <?php else: ?>
          <span style="color:var(--ps-text-muted);font-style:italic;font-size:.8125rem;">—</span>
        <?php endif; ?>
        <?php if (!empty($f['note']) && $changed): ?>
        <div style="font-size:.75rem;color:var(--ps-text-muted);margin-top:.25rem;"><?php echo htmlspecialchars($f['note']); ?></div>
        <?php endif; ?>
      </span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Body diff -->
  <div style="margin-bottom:1.25rem;">
    <div style="font-weight:600;font-size:.875rem;margin-bottom:.5rem;">
      Content
      <?php if ($bodyChanged): ?>
      <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--ps-warning);margin-left:4px;vertical-align:middle;" title="Changed"></span>
      <?php endif; ?>
    </div>
    <?php if (!$bodyChanged): ?>
    <p style="font-size:.8125rem;color:var(--ps-text-muted);">No changes in body content.</p>
    <?php else: ?>
    <div style="white-space:pre-wrap;font-size:.8125rem;line-height:1.6;max-height:280px;overflow-y:auto;border:1px solid var(--ps-border);border-radius:var(--ps-radius);padding:.75rem;background:var(--ps-bg-base);"><?php echo $bodyDiff; ?></div>
    <div style="font-size:.75rem;color:var(--ps-text-muted);margin-top:.375rem;">
      <span style="background:rgba(34,197,94,.22);border-radius:2px;padding:0 2px;">Added</span>
      &nbsp;
      <span style="background:rgba(239,68,68,.22);border-radius:2px;padding:0 2px;">Removed</span>
    </div>
    <?php endif; ?>
  </div>

  <!-- Actions -->
  <div style="display:flex;gap:.5rem;justify-content:flex-end;border-top:1px solid var(--ps-border);padding-top:.875rem;">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="window.vtxFormModalClose && window.vtxFormModalClose()">Close</button>
    <?php if (\App\CMS\Auth::can('pages.edit')): ?>
    <form id="vtx-diff-restore-form" method="POST"
          action="<?php echo htmlspecialchars($restoreAction); ?>" style="display:none;">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
    </form>
    <button type="button" class="btn btn-primary btn-sm" id="vtx-diff-restore-btn">
      <i class="pi pi-history me-1"></i>Restore This Revision
    </button>
    <?php endif; ?>
  </div>
</div>
