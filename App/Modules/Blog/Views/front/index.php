<div class="container blog-index">
  <div class="blog-page-header">
    <h1><?php echo htmlspecialchars($settings['blog_title'] ?? 'Blog'); ?></h1>
    <?php if (!empty($settings['blog_description'])): ?>
    <p><?php echo htmlspecialchars($settings['blog_description']); ?></p>
    <?php endif; ?>
  </div>

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
      <p><a href="<?php echo $baseUrl; ?>/blog/<?php echo htmlspecialchars($post['slug']); ?>" class="read-more">Read more &rarr;</a></p>
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
