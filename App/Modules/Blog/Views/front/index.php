<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($settings['blog_title'] ?? 'Blog'); ?></title>
  <?php if (!empty($settings['blog_description'])): ?>
  <meta name="description" content="<?php echo htmlspecialchars($settings['blog_description']); ?>">
  <?php endif; ?>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, -apple-system, sans-serif; color: #111; background: #fff; line-height: 1.6; }
    a { color: #4f46e5; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .container { max-width: 720px; margin: 0 auto; padding: 0 1.5rem; }
    header { border-bottom: 1px solid #e5e7eb; padding: 1.5rem 0; margin-bottom: 3rem; }
    header h1 { font-size: 1.5rem; font-weight: 700; margin: 0; }
    header p { margin: .25rem 0 0; color: #6b7280; font-size: .9375rem; }
    .post-list { list-style: none; padding: 0; margin: 0; }
    .post-item { padding: 1.75rem 0; border-bottom: 1px solid #f3f4f6; }
    .post-item:last-child { border-bottom: none; }
    .post-title { font-size: 1.375rem; font-weight: 700; margin: 0 0 .5rem; line-height: 1.3; }
    .post-meta { font-size: .8125rem; color: #9ca3af; margin-bottom: .75rem; display: flex; gap: 1rem; flex-wrap: wrap; }
    .post-excerpt { color: #4b5563; margin: 0 0 .875rem; }
    .post-cats { display: flex; gap: .5rem; flex-wrap: wrap; }
    .post-cat { font-size: .75rem; background: #f3f4f6; color: #6b7280; padding: .125rem .5rem; border-radius: 999px; }
    .read-more { font-size: .875rem; font-weight: 600; }
    .pagination { display: flex; gap: .5rem; justify-content: center; padding: 2.5rem 0; }
    .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center;
      width: 36px; height: 36px; border-radius: 6px; font-size: .875rem;
      border: 1px solid #e5e7eb; }
    .pagination .current { background: #4f46e5; color: #fff; border-color: #4f46e5; }
    .empty { text-align: center; padding: 4rem 1rem; color: #9ca3af; }
  </style>
</head>
<body>
<div class="container">
  <header>
    <h1><a href="<?php echo $baseUrl; ?>/blog"><?php echo htmlspecialchars($settings['blog_title'] ?? 'Blog'); ?></a></h1>
    <?php if (!empty($settings['blog_description'])): ?>
    <p><?php echo htmlspecialchars($settings['blog_description']); ?></p>
    <?php endif; ?>
  </header>

  <?php if (empty($posts)): ?>
  <div class="empty">
    <p>No posts published yet.</p>
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
        <span><?php echo htmlspecialchars($post['author_name']); ?></span>
        <?php endif; ?>
        <?php if (!empty($post['published_at'])): ?>
        <span><?php echo date('F j, Y', strtotime($post['published_at'])); ?></span>
        <?php endif; ?>
        <?php if (!empty($post['reading_time'])): ?>
        <span><?php echo (int)$post['reading_time']; ?> min read</span>
        <?php endif; ?>
      </div>
      <?php if (!empty($post['excerpt'])): ?>
      <p class="post-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
      <?php endif; ?>
      <?php if (!empty($post['categories'])): ?>
      <div class="post-cats">
        <?php foreach ($post['categories'] as $cat): ?>
        <a href="<?php echo $baseUrl; ?>/blog/category/<?php echo htmlspecialchars($cat['slug']); ?>"
           class="post-cat"><?php echo htmlspecialchars($cat['name']); ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <p><a href="<?php echo $baseUrl; ?>/blog/<?php echo htmlspecialchars($post['slug']); ?>" class="read-more">Read more →</a></p>
    </li>
    <?php endforeach; ?>
  </ul>

  <?php if (($pages ?? 1) > 1): ?>
  <nav class="pagination" aria-label="Pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php if ($i === ($page ?? 1)): ?>
    <span class="current"><?php echo $i; ?></span>
    <?php else: ?>
    <a href="<?php echo $baseUrl; ?>/blog?page=<?php echo $i; ?>"><?php echo $i; ?></a>
    <?php endif; ?>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
