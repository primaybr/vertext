<?php
$q       = htmlspecialchars($q ?? '', ENT_QUOTES);
$results = $results ?? [];
$total   = (int) ($total ?? 0);
?>
<style>
  .sr-page { max-width: 820px; margin: 0 auto; padding: 2rem 1rem; }
  .sr-page h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 1.5rem; }
  .sr-form { display: flex; gap: .5rem; margin-bottom: 2rem; }
  .sr-input { flex: 1; padding: .625rem .875rem; border: 1px solid var(--clr-border); border-radius: .375rem; font-size: 1rem; background: var(--clr-bg); color: var(--clr-text); font-family: inherit; }
  .sr-input:focus { outline: none; border-color: var(--clr-accent); box-shadow: 0 0 0 3px rgba(79,70,229,.12); }
  .sr-btn { padding: .625rem 1.25rem; background: var(--clr-accent); color: #fff; border: none; border-radius: .375rem; font-size: 1rem; cursor: pointer; font-family: inherit; }
  .sr-btn:hover { opacity: .85; }
  .sr-count { font-size: .875rem; color: var(--clr-muted); margin-bottom: 1.25rem; }
  .sr-no-results { color: var(--clr-muted); }
  .sr-list { display: flex; flex-direction: column; gap: 1.5rem; }
  .sr-item {}
  .sr-type { font-size: .6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; padding: .15em .5em; border-radius: .25rem; margin-right: .5rem; display: inline-block; background: var(--clr-surface); color: var(--clr-muted); }
  .sr-type-post { color: var(--clr-accent); }
  .sr-link { font-size: 1.125rem; font-weight: 600; color: var(--clr-accent); text-decoration: none; display: block; margin-top: .25rem; }
  .sr-link:hover { text-decoration: underline; }
  .sr-excerpt { margin: .375rem 0 0; font-size: .875rem; color: var(--clr-muted); line-height: 1.6; }
  .sr-url { font-size: .75rem; color: var(--clr-faint); margin-top: .25rem; }
  .sr-limit { margin-top: 1.5rem; font-size: .875rem; color: var(--clr-faint); }
</style>

<div class="sr-page">
  <h1>Search</h1>

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
