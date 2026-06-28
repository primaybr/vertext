<?php
$q       = htmlspecialchars($q ?? '', ENT_QUOTES);
$results = $results ?? [];
$total   = (int) ($total ?? 0);
?>
<div class="container" style="max-width:820px;margin:0 auto;padding:2rem 1rem;">
  <h1 style="font-size:1.75rem;font-weight:700;margin-bottom:1.5rem;">Search</h1>

  <form method="GET" action="" style="display:flex;gap:.5rem;margin-bottom:2rem;">
    <input type="text" name="q" value="<?php echo $q; ?>"
           placeholder="Search pages and posts..."
           autofocus
           style="flex:1;padding:.625rem .875rem;border:1px solid var(--ps-border,#ddd);border-radius:.375rem;font-size:1rem;">
    <button type="submit" style="padding:.625rem 1.25rem;background:var(--ps-primary,#2563eb);color:#fff;border:none;border-radius:.375rem;font-size:1rem;cursor:pointer;">
      Search
    </button>
  </form>

  <?php if ($q !== ''): ?>
    <?php if (empty($results)): ?>
      <p style="color:#666;">No results for <strong><?php echo $q; ?></strong>. Try a different search term.</p>
    <?php else: ?>
      <p style="font-size:.875rem;color:#666;margin-bottom:1.25rem;">
        <?php echo $total; ?> result<?php echo $total !== 1 ? 's' : ''; ?> for <strong><?php echo $q; ?></strong>
      </p>
      <div style="display:flex;flex-direction:column;gap:1.5rem;">
        <?php foreach ($results as $r): ?>
          <div>
            <div style="margin-bottom:.25rem;">
              <span style="display:inline-block;font-size:.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;padding:.15em .5em;border-radius:.25rem;background:<?php echo $r['type'] === 'post' ? '#dbeafe' : '#dcfce7'; ?>;color:<?php echo $r['type'] === 'post' ? '#1e40af' : '#166534'; ?>;margin-right:.5rem;">
                <?php echo $r['type'] === 'post' ? 'Blog' : 'Page'; ?>
              </span>
            </div>
            <a href="<?php echo htmlspecialchars($baseUrl . $r['url'], ENT_QUOTES); ?>"
               style="font-size:1.125rem;font-weight:600;color:var(--ps-primary,#2563eb);text-decoration:none;">
              <?php echo htmlspecialchars($r['title'] ?? '', ENT_QUOTES); ?>
            </a>
            <?php if (!empty($r['excerpt'])): ?>
              <p style="margin:.375rem 0 0;font-size:.875rem;color:#555;line-height:1.6;">
                <?php echo htmlspecialchars($r['excerpt'], ENT_QUOTES); ?>
              </p>
            <?php endif; ?>
            <div style="font-size:.75rem;color:#888;margin-top:.25rem;">
              <?php echo htmlspecialchars($baseUrl . $r['url'], ENT_QUOTES); ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($total > 30): ?>
        <p style="margin-top:1.5rem;font-size:.875rem;color:#888;">Showing top 30 results.</p>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</div>
