<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(!empty($post['meta_title']) ? $post['meta_title'] : $post['title']); ?></title>
  <?php $desc = $post['meta_description'] ?? $post['excerpt'] ?? ''; ?>
  <?php if ($desc): ?><meta name="description" content="<?php echo htmlspecialchars($desc); ?>"><?php endif; ?>
  <?php if (!empty($post['featured_image_url'])): ?>
  <meta property="og:image" content="<?php echo htmlspecialchars($post['featured_image_url']); ?>">
  <?php elseif (!empty($settings['og_default_image'])): ?>
  <meta property="og:image" content="<?php echo htmlspecialchars($settings['og_default_image']); ?>">
  <?php endif; ?>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, -apple-system, sans-serif; color: #111; background: #fff; line-height: 1.7; }
    a { color: #4f46e5; }
    .container { max-width: 720px; margin: 0 auto; padding: 0 1.5rem; }
    header { border-bottom: 1px solid #e5e7eb; padding: 1.25rem 0; margin-bottom: 2.5rem; }
    header a { font-weight: 600; text-decoration: none; color: #4f46e5; font-size: .9375rem; }
    .post-header { margin-bottom: 2rem; }
    h1 { font-size: 2rem; font-weight: 800; line-height: 1.25; margin: 0 0 .875rem; }
    .meta { font-size: .8125rem; color: #9ca3af; display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .featured-img { width: 100%; max-height: 400px; object-fit: cover; border-radius: 8px; margin-bottom: 2rem; }
    .post-body { font-size: 1.0625rem; }
    .post-body h1, .post-body h2, .post-body h3 { font-weight: 700; margin: 2rem 0 .75rem; line-height: 1.3; }
    .post-body p { margin: 0 0 1.25rem; }
    .post-body pre { background: #f3f4f6; padding: 1rem; border-radius: 6px; overflow-x: auto; font-size: .875rem; }
    .post-body blockquote { border-left: 3px solid #e5e7eb; margin: 1.5rem 0; padding: .5rem 1.25rem; color: #6b7280; }
    .post-body img { max-width: 100%; border-radius: 6px; }
    .post-tags { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid #f3f4f6; }
    .tag { font-size: .75rem; background: #f3f4f6; color: #6b7280; padding: .25rem .625rem; border-radius: 999px; }
    .comments { margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid #f3f4f6; }
    .comment { padding: 1rem 0; border-bottom: 1px solid #f9fafb; }
    .comment-author { font-weight: 600; font-size: .875rem; }
    .comment-date { font-size: .75rem; color: #9ca3af; margin-left: .5rem; }
    .comment-body { margin-top: .25rem; color: #4b5563; }
    .comment-form { margin-top: 2rem; }
    .comment-form h3 { font-size: 1.125rem; margin-bottom: 1rem; }
    .field { margin-bottom: 1rem; }
    .field label { display: block; font-size: .875rem; font-weight: 600; margin-bottom: .375rem; }
    .field input, .field textarea { width: 100%; padding: .5rem .75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: .9375rem; }
    .field textarea { resize: vertical; min-height: 100px; }
    .btn { padding: .5rem 1.25rem; background: #4f46e5; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: .9375rem; font-weight: 600; }
    .btn:hover { background: #4338ca; }
    .notice { padding: .75rem 1rem; border-radius: 6px; font-size: .875rem; }
    .notice.success { background: #d1fae5; color: #065f46; }
    .notice.error   { background: #fee2e2; color: #991b1b; }
  </style>
</head>
<body>
<div class="container">
  <header>
    <a href="<?php echo $baseUrl; ?>/blog">← <?php echo htmlspecialchars($settings['blog_title'] ?? 'Blog'); ?></a>
  </header>

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

  <!-- Comments -->
  <?php if (!empty($settings['comments_enabled'])): ?>
  <div class="comments">
    <?php if (!empty($comments)): ?>
    <h2 style="font-size:1.25rem;margin-bottom:1.25rem;">
      <?php echo count($comments); ?> Comment<?php echo count($comments) !== 1 ? 's' : ''; ?>
    </h2>
    <?php foreach ($comments as $c): ?>
    <div class="comment">
      <span class="comment-author"><?php echo htmlspecialchars($c['author_name']); ?></span>
      <span class="comment-date"><?php echo date('M j, Y', strtotime($c['created_at'])); ?></span>
      <div class="comment-body"><?php echo nl2br(htmlspecialchars($c['body'])); ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="comment-form">
      <h3>Leave a Comment</h3>
      <?php if (!empty($commentFlash)): ?>
      <div class="notice <?php echo $commentFlash['type'] === 'success' ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($commentFlash['message']); ?>
      </div>
      <?php endif; ?>
      <form method="POST" action="<?php echo $baseUrl; ?>/blog/<?php echo htmlspecialchars($post['slug']); ?>/comment">
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
</body>
</html>
