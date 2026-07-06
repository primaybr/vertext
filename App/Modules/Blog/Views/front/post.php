<?php
$rawBlogBase = trim($settings['blog_base_path'] ?? 'blog', '/');
$blogBase    = $rawBlogBase === '' ? '' : '/' . $rawBlogBase;
?>

<div class="container-prose">
  <div class="post-breadcrumb">
    <nav class="crumbs">
      <a href="<?php echo $baseUrl . ($blogBase !== '' ? $blogBase : '/'); ?>"><?php echo htmlspecialchars($settings['blog_title'] ?? 'Blog'); ?></a>
      <span class="sep">/</span>
      <span><?php echo htmlspecialchars($post['title']); ?></span>
    </nav>
    <button type="button" class="reading-list-btn" id="reading-list-btn"
            data-post-id="<?php echo htmlspecialchars($post['id']); ?>"
            data-post-title="<?php echo htmlspecialchars($post['title']); ?>"
            data-post-slug="<?php echo htmlspecialchars($post['slug']); ?>"
            data-post-url="<?php echo htmlspecialchars($baseUrl . $blogBase . '/' . $post['slug']); ?>">
      <i id="rl-icon" class="pi pi-menu"></i>
      <span id="rl-label">Save to Reading List</span>
    </button>
  </div>

  <article>
    <div class="post-header">
      <h1><?php echo htmlspecialchars($post['title']); ?></h1>
      <div class="meta">
        <?php if (!empty($post['author_name'])): ?>
        <span><?php echo htmlspecialchars($post['author_name']); ?></span>
        <?php endif; ?>
        <?php if (!empty($post['published_at'])): ?>
        <time datetime="<?php echo $post['published_at']; ?>">
          <?php echo date('F j, Y', strtotime($post['published_at'])); ?>
        </time>
        <?php endif; ?>
        <?php if (!empty($post['reading_time'])): ?>
        <span><?php echo (int)$post['reading_time']; ?> min read</span>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($post['featured_image_url'])): ?>
    <img src="<?php echo htmlspecialchars($post['featured_image_url']); ?>"
         alt="<?php echo htmlspecialchars($post['title']); ?>"
         class="featured-img">
    <?php endif; ?>

    <!-- Series navigation (if part of a series) -->
    <?php if (!empty($series)): ?>
    <div class="series-box">
      <div class="series-box-label">Part of a Series</div>
      <div class="series-box-title"><?php echo htmlspecialchars($series['title']); ?></div>
      <ol class="series-posts">
        <?php foreach ($series['posts'] as $sp): ?>
        <?php $isCurrent = ($sp['post_id'] === $post['id']); ?>
        <li class="<?php echo $isCurrent ? 'current' : ''; ?>">
          <?php if ($isCurrent): ?>
          <?php echo htmlspecialchars($sp['title']); ?>
          <?php else: ?>
          <a href="<?php echo htmlspecialchars($baseUrl . $blogBase . '/' . $sp['slug']); ?>">
            <?php echo htmlspecialchars($sp['title']); ?>
          </a>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ol>
      <?php if (!empty($series['prev']) || !empty($series['next'])): ?>
      <div class="series-nav">
        <?php if (!empty($series['prev'])): ?>
        <a href="<?php echo htmlspecialchars($baseUrl . $blogBase . '/' . $series['prev']['slug']); ?>">
          &larr; <?php echo htmlspecialchars($series['prev']['title']); ?>
        </a>
        <?php endif; ?>
        <?php if (!empty($series['next'])): ?>
        <a href="<?php echo htmlspecialchars($baseUrl . $blogBase . '/' . $series['next']['slug']); ?>">
          <?php echo htmlspecialchars($series['next']['title']); ?> &rarr;
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="post-body">
      <?php echo $post['body']; /* HTML from Quill, sanitized on save */ ?>
    </div>

    <?php if (!empty($post['tags'])): ?>
    <div class="post-tags">
      <?php foreach ($post['tags'] as $tag): ?>
      <span class="tag"><?php echo htmlspecialchars($tag['name']); ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </article>

  <!-- Related posts -->
  <?php if (!empty($relatedPosts)): ?>
  <div class="related">
    <h2>Related Posts</h2>
    <div class="related-grid">
      <?php foreach ($relatedPosts as $rp): ?>
      <a href="<?php echo htmlspecialchars($baseUrl . $blogBase . '/' . $rp['slug']); ?>" class="related-card">
        <?php if (!empty($rp['featured_image_url'])): ?>
        <img src="<?php echo htmlspecialchars($rp['featured_image_url']); ?>"
             alt="<?php echo htmlspecialchars($rp['title']); ?>"
             class="related-card-img">
        <?php else: ?>
        <div class="related-card-img"></div>
        <?php endif; ?>
        <div class="related-card-body">
          <div class="related-card-title"><?php echo htmlspecialchars($rp['title']); ?></div>
          <?php if (!empty($rp['published_at'])): ?>
          <div class="related-card-meta"><?php echo date('M j, Y', strtotime($rp['published_at'])); ?></div>
          <?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Comments -->
  <?php if (!empty($commentsEnabled)): ?>
  <div class="comments">
    <?php if (!empty($threadedComments)): ?>
    <?php
    $totalComments = 0;
    $countAll = function(array $nodes) use (&$countAll, &$totalComments) {
        foreach ($nodes as $n) {
            $totalComments++;
            if (!empty($n['replies'])) $countAll($n['replies']);
        }
    };
    $countAll($threadedComments);
    ?>
    <h2 class="comments-count">
      <?php echo $totalComments; ?> Comment<?php echo $totalComments !== 1 ? 's' : ''; ?>
    </h2>
    <?php
    $renderComment = null;
    $renderComment = function(array $c, string $postSlug, string $baseUrl, string $blogBase, string $csrfToken) use (&$renderComment): void {
    ?>
    <div class="comment" id="comment-<?php echo $c['id']; ?>">
      <span class="comment-author"><?php echo htmlspecialchars($c['author_name']); ?></span>
      <span class="comment-date"><?php echo date('M j, Y', strtotime($c['created_at'])); ?></span>
      <div class="comment-body"><?php echo nl2br(htmlspecialchars($c['body'])); ?></div>
      <?php if (empty($c['parent_comment_id'])): ?>
      <button type="button" class="comment-reply-btn" onclick="toggleReplyForm('reply-<?php echo $c['id']; ?>')">Reply</button>
      <div class="reply-form" id="reply-<?php echo $c['id']; ?>">
        <form method="POST" action="<?php echo $baseUrl . $blogBase; ?>/<?php echo htmlspecialchars($postSlug); ?>/comment">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="parent_comment_id" value="<?php echo $c['id']; ?>">
          <div class="field">
            <label>Name *</label>
            <input type="text" name="author_name" required maxlength="120">
          </div>
          <div class="field">
            <label>Comment *</label>
            <textarea name="body" required maxlength="2000" rows="3"></textarea>
          </div>
          <button type="submit" class="btn btn-sm">Post Reply</button>
          <button type="button" class="btn btn-sm btn-cancel" onclick="toggleReplyForm('reply-<?php echo $c['id']; ?>')">Cancel</button>
        </form>
      </div>
      <?php endif; ?>
      <?php if (!empty($c['replies'])): ?>
      <div class="comment-replies">
        <?php foreach ($c['replies'] as $reply): ?>
          <?php $renderComment($reply, $postSlug, $baseUrl, $blogBase, $csrfToken); ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php }; ?>
    <?php foreach ($threadedComments as $c): ?>
      <?php $renderComment($c, $post['slug'], $baseUrl, $blogBase, $csrf_token ?? ''); ?>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="comment-form">
      <h3>Leave a Comment</h3>
      <?php if (!empty($commentFlash)): ?>
      <div class="notice <?php echo ($commentFlash['type'] ?? '') === 'success' ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($commentFlash['message'] ?? ''); ?>
      </div>
      <?php endif; ?>
      <form method="POST" action="<?php echo $baseUrl . $blogBase; ?>/<?php echo htmlspecialchars($post['slug']); ?>/comment">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
        <div class="field">
          <label for="c-name">Name *</label>
          <input type="text" id="c-name" name="author_name" required maxlength="120">
        </div>
        <div class="field">
          <label for="c-email">Email (not shown)</label>
          <input type="email" id="c-email" name="author_email" maxlength="180">
        </div>
        <div class="field">
          <label for="c-body">Comment *</label>
          <textarea id="c-body" name="body" required maxlength="2000"></textarea>
        </div>
        <button type="submit" class="btn">Post Comment</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
