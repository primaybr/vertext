<style>
  .cat-back { font-size: .875rem; margin-bottom: 2rem; }
  .cat-back a { color: #6b7280; }
  .cat-back a:hover { color: #4f46e5; }
  .cat-header { margin-bottom: 2.5rem; }
  .cat-header h1 { font-size: 1.75rem; font-weight: 800; margin: 0 0 .375rem; }
  .cat-header p { color: #6b7280; margin: 0; }
  .post-list { list-style: none; padding: 0; margin: 0; }
  .post-item { padding: 1.5rem 0; border-bottom: 1px solid #f3f4f6; }
  .post-item:last-child { border-bottom: none; }
  .post-title { font-size: 1.25rem; font-weight: 700; margin: 0 0 .375rem; }
  .post-meta { font-size: .8125rem; color: #9ca3af; margin-bottom: .625rem; }
  .post-excerpt { color: #4b5563; margin: 0 0 .75rem; font-size: .9375rem; }
  .read-more { font-size: .875rem; font-weight: 600; }
  .pagination { display: flex; gap: .5rem; justify-content: center; padding: 2.5rem 0; }
  .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 6px; font-size: .875rem; border: 1px solid #e5e7eb; }
  .pagination .current { background: #4f46e5; color: #fff; border-color: #4f46e5; }
  .empty { text-align: center; padding: 4rem 1rem; color: #9ca3af; }
</style>

<div class="container">
  <div class="cat-back">
    <a href="<?php echo $baseUrl; ?>/blog">← <?php echo htmlspecialchars($settings['blog_title'] ?? 'Blog'); ?></a>
  </div>

  <div class="cat-header">
    <h1><?php echo htmlspecialchars($category['name']); ?></h1>
    <?php if (!empty($category['description'])): ?>
    <p><?php echo htmlspecialchars($category['description']); ?></p>
    <?php endif; ?>
  </div>

  <?php if (empty($posts)): ?>
  <div class="empty">
    <p>No posts in this category yet.</p>
  </div>
  <?php else: ?>
  <ul class="post-list">
    <?php foreach ($posts as $post): ?>
    <li class="post-item">
      <h2 class="post-title">
        <a href="<?php echo $baseUrl; ?>/blog/<?php echo htmlspecialchars($post['slug']); ?>">
          <?php echo htmlspecialchars($post['title']); ?>
        </a>
      </h2>
      <div class="post-meta">
        <?php if (!empty($post['author_name'])): ?>
        <span><?php echo htmlspecialchars($post['author_name']); ?></span> ·
        <?php endif; ?>
        <?php if (!empty($post['published_at'])): ?>
        <?php echo date('F j, Y', strtotime($post['published_at'])); ?>
        <?php endif; ?>
      </div>
      <?php if (!empty($post['excerpt'])): ?>
      <p class="post-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
      <?php endif; ?>
      <a href="<?php echo $baseUrl; ?>/blog/<?php echo htmlspecialchars($post['slug']); ?>" class="read-more">Read more →</a>
    </li>
    <?php endforeach; ?>
  </ul>

  <?php if (($pages ?? 1) > 1): ?>
  <nav class="pagination" aria-label="Pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php if ($i === ($page ?? 1)): ?>
    <span class="current"><?php echo $i; ?></span>
    <?php else: ?>
    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
    <?php endif; ?>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
