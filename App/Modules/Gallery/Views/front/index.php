<style>
  .gallery-page-header { margin-bottom: 2.5rem; }
  .gallery-page-header h1 { font-size: 1.75rem; font-weight: 800; margin: 0; }
  .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.5rem; }
  .gallery-card { border-radius: 10px; overflow: hidden; background: var(--clr-surface); box-shadow: 0 1px 3px rgba(0,0,0,.08); transition: transform .15s, box-shadow .15s; }
  .gallery-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.15); }
  .gallery-card a { text-decoration: none; display: block; color: inherit; }
  .gallery-cover { aspect-ratio: 4/3; background: var(--clr-surface); overflow: hidden; }
  .gallery-cover img { width: 100%; height: 100%; object-fit: cover; transition: transform .25s; }
  .gallery-card:hover .gallery-cover img { transform: scale(1.04); }
  .gallery-cover-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--clr-faint); font-size: 2.5rem; }
  .gallery-info { padding: 1rem; }
  .gallery-title { font-size: 1rem; font-weight: 700; margin: 0 0 .25rem; }
  .gallery-desc { font-size: .875rem; color: var(--clr-muted); margin: 0 0 .5rem; }
  .gallery-count { font-size: .8125rem; color: var(--clr-faint); }
  .gallery-empty { text-align: center; padding: 5rem 1rem; color: var(--clr-faint); }
</style>

<div class="container">
  <div class="gallery-page-header">
    <h1>Gallery</h1>
  </div>

  <?php if (empty($galleries)): ?>
  <div class="gallery-empty">
    <p style="font-size:2rem;margin-bottom:.5rem;">📷</p>
    <p>No albums published yet.</p>
  </div>
  <?php else: ?>
  <div class="gallery-grid">
    <?php foreach ($galleries as $g): ?>
    <div class="gallery-card">
      <a href="<?php echo $baseUrl; ?>/gallery/<?php echo htmlspecialchars($g['slug']); ?>">
        <div class="gallery-cover">
          <?php if ($g['cover_url']): ?>
          <img src="<?php echo htmlspecialchars($g['cover_url']); ?>"
               alt="<?php echo htmlspecialchars($g['title']); ?>" loading="lazy">
          <?php else: ?>
          <div class="gallery-cover-placeholder">🖼</div>
          <?php endif; ?>
        </div>
        <div class="gallery-info">
          <div class="gallery-title"><?php echo htmlspecialchars($g['title']); ?></div>
          <?php if (!empty($g['description'])): ?>
          <div class="gallery-desc"><?php echo htmlspecialchars($g['description']); ?></div>
          <?php endif; ?>
          <div class="gallery-count"><?php echo (int) $g['item_count']; ?> photo<?php echo $g['item_count'] !== 1 ? 's' : ''; ?></div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
