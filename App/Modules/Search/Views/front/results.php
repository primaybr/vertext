<?php
$q       = htmlspecialchars($q ?? '', ENT_QUOTES);
$results = $results ?? [];
$total   = (int) ($total ?? 0);
?>
<div class="container sr-page">
  <h1 class="sr-page-header">Search</h1>

  <form method="GET" action="" class="sr-form">
    <input type="text" name="q" value="<?php echo $q; ?>"
           placeholder="Search pages and posts&hellip;"
           autofocus class="sr-input">
    <button type="submit" class="sr-btn">Search</button>
  </form>

  <?php if ($q !== ''): ?>
    <?php if (empty($results)): ?>
      <p class="sr-no-results">No results for <strong><?php echo $q; ?></strong>. Try a different search term.</p>
    <?php else: ?>
      <p class="sr-count">
        <?php echo $total; ?> result<?php echo $total !== 1 ? 's' : ''; ?> for <strong><?php echo $q; ?></strong>
      </p>
      <div class="sr-list">
        <?php foreach ($results as $r): ?>
          <div class="sr-item">
            <div>
              <span class="sr-type <?php echo $r['type'] === 'post' ? 'sr-type-post' : ''; ?>">
                <?php echo $r['type'] === 'post' ? 'Blog' : 'Page'; ?>
              </span>
            </div>
            <a href="<?php echo htmlspecialchars($baseUrl . $r['url'], ENT_QUOTES); ?>" class="sr-link">
              <?php echo htmlspecialchars($r['title'] ?? '', ENT_QUOTES); ?>
            </a>
            <?php if (!empty($r['excerpt'])): ?>
              <p class="sr-excerpt"><?php echo htmlspecialchars($r['excerpt'], ENT_QUOTES); ?></p>
            <?php endif; ?>
            <div class="sr-url"><?php echo htmlspecialchars($baseUrl . $r['url'], ENT_QUOTES); ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($total > 30): ?>
        <p class="sr-limit">Showing top 30 results.</p>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</div>
