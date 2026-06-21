<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-home me-2 text-primary"></i>Blog Dashboard</h1>
    <p class="vtx-page-desc">Overview of your blog activity.</p>
  </div>
  <?php if (\App\CMS\Auth::can('posts.create')): ?>
  <a href="{{baseUrl}}/admin/blog/posts" class="btn btn-primary">
    <i class="pi pi-plus me-1"></i> New Post
  </a>
  <?php endif; ?>
</div>

<!-- Stat Cards -->
<div class="vtx-blog-stats">
  <div class="vtx-stat">
    <div class="vtx-stat-ico blue"><i class="pi pi-file-edit"></i></div>
    <div class="vtx-stat-val"><?php echo number_format($totalPosts); ?></div>
    <div class="vtx-stat-lbl">Total Posts</div>
  </div>
  <div class="vtx-stat">
    <div class="vtx-stat-ico green"><i class="pi pi-check-circle"></i></div>
    <div class="vtx-stat-val"><?php echo number_format($published); ?></div>
    <div class="vtx-stat-lbl">Published</div>
  </div>
  <div class="vtx-stat">
    <div class="vtx-stat-ico amber"><i class="pi pi-clock"></i></div>
    <div class="vtx-stat-val"><?php echo number_format($drafts); ?></div>
    <div class="vtx-stat-lbl">Drafts</div>
  </div>
  <?php if (\App\CMS\Auth::can('comments.view')): ?>
  <div class="vtx-stat">
    <div class="vtx-stat-ico red"><i class="pi pi-message"></i></div>
    <div class="vtx-stat-val"><?php echo number_format($pendingComments); ?></div>
    <div class="vtx-stat-lbl">Pending Comments</div>
  </div>
  <?php endif; ?>
</div>

<!-- Posts over last 30 days -->
<div class="vtx-panel mb-4">
  <div class="vtx-panel-head">
    <span class="vtx-panel-title">Published Posts — Last 30 Days</span>
  </div>
  <div class="vtx-panel-body" style="padding:1.25rem 1rem .5rem;">
    <canvas data-vtx-chart
            data-type="line"
            data-labels="<?php echo htmlspecialchars(json_encode($chartLabels)); ?>"
            data-values="<?php echo htmlspecialchars(json_encode($chartValues)); ?>"
            data-label="Posts Published"
            data-color="#2563EB"
            style="height:220px;width:100%;display:block;"></canvas>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;align-items:start;">

  <!-- Recent Posts -->
  <div class="vtx-panel">
    <div class="vtx-panel-head">
      <span class="vtx-panel-title">Recent Posts</span>
      <a href="{{baseUrl}}/admin/blog/posts" class="btn btn-link btn-sm p-0">View all</a>
    </div>
    <?php if (empty($recentPosts)): ?>
    <div class="vtx-empty" style="padding:1.5rem;">
      <div class="vtx-empty-title" style="font-size:.875rem;">No posts yet</div>
    </div>
    <?php else: ?>
    <div class="vtx-panel-body" style="padding:0;">
      <?php foreach ($recentPosts as $p):
            $statusClass = match($p['status']) {
                'published' => 'success',
                'draft'     => 'amber',
                'archived'  => 'gray',
                default     => 'gray',
            };
      ?>
      <div style="display:flex;align-items:center;gap:.75rem;padding:.625rem 1rem;
                  border-bottom:1px solid var(--ps-border);">
        <span class="vtx-tag <?php echo $statusClass; ?>" style="flex-shrink:0;font-size:.6875rem;">
          <?php echo ucfirst($p['status']); ?>
        </span>
        <div style="flex:1;min-width:0;">
          <a href="{{baseUrl}}/admin/blog/posts/<?php echo $p['id']; ?>/form"
             style="font-size:.8125rem;font-weight:500;color:var(--ps-text-primary);
                    text-decoration:none;white-space:nowrap;overflow:hidden;
                    text-overflow:ellipsis;display:block;">
            <?php echo htmlspecialchars($p['title']); ?>
          </a>
          <div style="font-size:.75rem;color:var(--ps-text-muted);">
            <?php echo htmlspecialchars($p['author_name'] ?? '—'); ?>
          </div>
        </div>
        <div style="font-size:.75rem;color:var(--ps-text-muted);flex-shrink:0;">
          <?php echo date('M j', strtotime($p['created_at'])); ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Pending Comments -->
  <?php if (\App\CMS\Auth::can('comments.view')): ?>
  <div class="vtx-panel">
    <div class="vtx-panel-head">
      <span class="vtx-panel-title">Pending Comments</span>
      <a href="{{baseUrl}}/admin/blog/comments" class="btn btn-link btn-sm p-0">Moderate</a>
    </div>
    <?php if (empty($recentComments)): ?>
    <div class="vtx-empty" style="padding:1.5rem;">
      <div class="vtx-empty-title" style="font-size:.875rem;">No pending comments</div>
    </div>
    <?php else: ?>
    <div class="vtx-panel-body" style="padding:0;">
      <?php foreach ($recentComments as $c): ?>
      <div style="padding:.75rem 1rem;border-bottom:1px solid var(--ps-border);">
        <div style="display:flex;align-items:baseline;justify-content:space-between;gap:.5rem;margin-bottom:.25rem;">
          <span style="font-size:.8125rem;font-weight:600;color:var(--ps-text-primary);">
            <?php echo htmlspecialchars($c['author_name']); ?>
          </span>
          <span style="font-size:.75rem;color:var(--ps-text-muted);flex-shrink:0;">
            on <em><?php echo htmlspecialchars($c['post_title'] ?? '—'); ?></em>
          </span>
        </div>
        <p style="font-size:.8125rem;color:var(--ps-text-secondary);margin:0;
                  overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
          <?php echo htmlspecialchars(substr($c['body'], 0, 120)); ?>
        </p>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<script>Vtx.load('chart');</script>
