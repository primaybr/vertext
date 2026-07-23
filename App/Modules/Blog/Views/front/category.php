<?php
$rawBlogBase = trim($settings['blog_base_path'] ?? 'blog', '/');
$blogBase    = $rawBlogBase === '' ? '' : '/' . $rawBlogBase;
?>
<div class="container blog-category">
  <nav class="cat-breadcrumb">
    <a href="<?php echo htmlspecialchars(site_path($baseUrl, $blogBase !== '' ? $blogBase : '/')); ?>"><?php echo htmlspecialchars($settings['blog_title'] ?? 'Blog'); ?></a>
    <span class="sep">/</span>
    <span><?php echo htmlspecialchars($category['name']); ?></span>
  </nav>

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
        <a href="<?php echo htmlspecialchars(site_path($baseUrl, $blogBase . '/' . $post['slug'])); ?>">
          <?php echo htmlspecialchars($post['title']); ?>
        </a>
      </h2>
      <div class="post-meta">
        <?php if (!empty($post['author_name'])): ?>
        <span><?php echo htmlspecialchars($post['author_name']); ?></span> &middot;
        <?php endif; ?>
        <?php if (!empty($post['published_at'])): ?>
        <?php echo date('F j, Y', strtotime($post['published_at'])); ?>
        <?php endif; ?>
      </div>
      <?php if (!empty($post['excerpt'])): ?>
      <p class="post-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
      <?php endif; ?>
      <a href="<?php echo htmlspecialchars(site_path($baseUrl, $blogBase . '/' . $post['slug'])); ?>" class="read-more">Read more &rarr;</a>
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
