<?php
$blogBase = '/' . trim($settings['blog_base_path'] ?? 'blog', '/');
?>
<style>
  .post-back { font-size:.875rem; margin-bottom:2rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
  .post-back a { color:var(--clr-muted); text-decoration:none; }
  .post-back a:hover { color:var(--clr-accent); }
  .post-header { margin-bottom:2rem; }
  .post-header h1 { font-size:2rem; font-weight:800; line-height:1.25; margin:0 0 .875rem; }
  .meta { font-size:.8125rem; color:var(--clr-faint); display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
  .featured-img { width:100%; max-height:400px; object-fit:cover; border-radius:8px; margin-bottom:2rem; }
  .post-body { font-size:1.0625rem; }
  .post-body h1,.post-body h2,.post-body h3 { font-weight:700; margin:2rem 0 .75rem; line-height:1.3; }
  .post-body p { margin:0 0 1.25rem; }
  .post-body pre { background:var(--clr-surface); padding:1rem; border-radius:6px; overflow-x:auto; font-size:.875rem; }
  .post-body blockquote { border-left:3px solid var(--clr-border); margin:1.5rem 0; padding:.5rem 1.25rem; color:var(--clr-muted); }
  .post-body img { max-width:100%; border-radius:6px; }
  .post-tags { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:2.5rem; padding-top:1.5rem; border-top:1px solid var(--clr-border); }
  .tag { font-size:.75rem; background:var(--clr-surface); color:var(--clr-muted); padding:.25rem .625rem; border-radius:999px; text-decoration:none; }
  .tag:hover { background:var(--clr-border); }

  /* Reading list */
  .reading-list-btn { display:inline-flex; align-items:center; gap:.375rem; font-size:.8125rem; padding:.375rem .75rem; border:1px solid var(--clr-border); border-radius:6px; background:var(--clr-bg); cursor:pointer; color:var(--clr-text); font-weight:500; transition:all .15s; }
  .reading-list-btn:hover { border-color:var(--clr-accent); color:var(--clr-accent); }
  .reading-list-btn.saved { background:var(--clr-surface); border-color:var(--clr-accent); color:var(--clr-accent); }

  /* Series box */
  .series-box { background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:8px; padding:1.25rem 1.5rem; margin-bottom:2rem; }
  .series-box-label { font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--clr-accent); margin-bottom:.5rem; }
  .series-box-title { font-size:1rem; font-weight:700; margin-bottom:.75rem; }
  .series-posts { list-style:none; margin:0 0 1rem; padding:0; }
  .series-posts li { font-size:.875rem; padding:.25rem 0; display:flex; align-items:center; gap:.5rem; }
  .series-posts li.current { font-weight:700; color:var(--clr-accent); }
  .series-posts li.current::before { content:"&#9658;"; font-size:.625rem; }
  .series-posts li:not(.current)::before { content:""; display:inline-block; width:.75rem; }
  .series-posts a { color:var(--clr-muted); text-decoration:none; }
  .series-posts a:hover { color:var(--clr-accent); }
  .series-nav { display:flex; gap:.75rem; flex-wrap:wrap; }
  .series-nav a { font-size:.8125rem; padding:.375rem .875rem; border:1px solid var(--clr-border); border-radius:6px; color:var(--clr-accent); text-decoration:none; background:var(--clr-bg); }
  .series-nav a:hover { background:var(--clr-surface); }

  /* Comments */
  .comments { margin-top:3rem; padding-top:1.5rem; border-top:1px solid var(--clr-border); }
  .comment { padding:1rem 0; border-bottom:1px solid var(--clr-border); }
  .comment-author { font-weight:600; font-size:.875rem; }
  .comment-date { font-size:.75rem; color:var(--clr-faint); margin-left:.5rem; }
  .comment-body { margin-top:.25rem; color:var(--clr-muted); }
  .comment-reply-btn { font-size:.75rem; color:var(--clr-muted); background:none; border:none; cursor:pointer; padding:0; margin-top:.375rem; }
  .comment-reply-btn:hover { color:var(--clr-accent); }
  .comment-replies { padding-left:1.5rem; border-left:2px solid var(--clr-border); margin-top:.5rem; }
  .reply-form { margin-top:.75rem; padding:1rem; background:var(--clr-surface); border-radius:6px; display:none; }
  .reply-form.open { display:block; }
  .comment-form { margin-top:2rem; }
  .comment-form h3 { font-size:1.125rem; margin-bottom:1rem; }
  .field { margin-bottom:1rem; }
  .field label { display:block; font-size:.875rem; font-weight:600; margin-bottom:.375rem; }
  .field input,.field textarea { width:100%; padding:.5rem .75rem; border:1px solid var(--clr-border); border-radius:6px; font-size:.9375rem; font-family:inherit; box-sizing:border-box; background:var(--clr-bg); color:var(--clr-text); }
  .field textarea { resize:vertical; min-height:100px; }
  .btn { padding:.5rem 1.25rem; background:var(--clr-accent); color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:.9375rem; font-weight:600; }
  .btn:hover { opacity:.85; }
  .btn-sm { padding:.35rem .875rem; font-size:.8125rem; }
  .notice { padding:.75rem 1rem; border-radius:6px; font-size:.875rem; margin-bottom:1rem; }
  .notice.success { background:#d1fae5; color:#065f46; }
  .notice.error   { background:#fee2e2; color:#991b1b; }
  [data-theme="dark"] .notice.success { background:rgba(16,185,129,.15); color:#6ee7b7; }
  [data-theme="dark"] .notice.error   { background:rgba(239,68,68,.15); color:#fca5a5; }

  /* Related posts */
  .related { margin-top:3rem; padding-top:1.5rem; border-top:1px solid var(--clr-border); }
  .related h2 { font-size:1.125rem; font-weight:700; margin-bottom:1.25rem; }
  .related-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1.25rem; }
  .related-card { border:1px solid var(--clr-border); border-radius:8px; overflow:hidden; text-decoration:none; color:inherit; display:block; transition:box-shadow .15s; background:var(--clr-bg); }
  .related-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.12); }
  .related-card-img { width:100%; height:140px; object-fit:cover; display:block; background:var(--clr-surface); }
  .related-card-body { padding:.875rem 1rem; }
  .related-card-title { font-size:.9375rem; font-weight:600; line-height:1.35; margin:0 0 .375rem; }
  .related-card-meta { font-size:.75rem; color:var(--clr-faint); }
</style>

<div class="container">
  <div class="post-back">
    <a href="<?php echo $baseUrl . $blogBase; ?>">&larr; <?php echo htmlspecialchars($settings['blog_title'] ?? 'Blog'); ?></a>
    <button type="button" class="reading-list-btn" id="reading-list-btn"
            data-post-id="<?php echo htmlspecialchars($post['id']); ?>"
            data-post-title="<?php echo htmlspecialchars($post['title']); ?>"
            data-post-slug="<?php echo htmlspecialchars($post['slug']); ?>"
            data-post-url="<?php echo htmlspecialchars($baseUrl . $blogBase . '/' . $post['slug']); ?>">
      <span id="rl-icon">&#9776;</span>
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
    <h2 style="font-size:1.25rem;margin-bottom:1.25rem;">
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
          <button type="button" class="btn btn-sm" style="background:var(--clr-muted);margin-left:.5rem;" onclick="toggleReplyForm('reply-<?php echo $c['id']; ?>')">Cancel</button>
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

<script>
(function() {
  var POST_ID    = <?php echo json_encode($post['id']); ?>;
  var POST_TITLE = <?php echo json_encode($post['title']); ?>;
  var POST_SLUG  = <?php echo json_encode($post['slug']); ?>;
  var POST_URL   = <?php echo json_encode($baseUrl . $blogBase . '/' . $post['slug']); ?>;
  var KEY = 'vtx_reading_list';

  function getList() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch(e) { return []; } }
  function saveList(l) { localStorage.setItem(KEY, JSON.stringify(l)); }
  function isSaved() { return getList().some(function(p) { return p.id === POST_ID; }); }

  var btn   = document.getElementById('reading-list-btn');
  var icon  = document.getElementById('rl-icon');
  var label = document.getElementById('rl-label');

  function updateBtn() {
    if (isSaved()) {
      btn.classList.add('saved');
      icon.textContent = '✓';
      label.textContent = 'Saved to Reading List';
    } else {
      btn.classList.remove('saved');
      icon.textContent = '☰';
      label.textContent = 'Save to Reading List';
    }
  }

  if (btn) {
    updateBtn();
    btn.addEventListener('click', function() {
      var list = getList();
      if (isSaved()) {
        saveList(list.filter(function(p) { return p.id !== POST_ID; }));
      } else {
        list.push({ id: POST_ID, title: POST_TITLE, slug: POST_SLUG, url: POST_URL });
        saveList(list);
      }
      updateBtn();
    });
  }
}());

function toggleReplyForm(id) {
  var el = document.getElementById(id);
  if (el) el.classList.toggle('open');
}
</script>
