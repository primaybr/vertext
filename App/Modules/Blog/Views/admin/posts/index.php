<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-edit me-2 text-primary"></i>Posts</h1>
    <p class="vtx-page-desc">Write, manage, and publish your blog content.</p>
  </div>
  <?php if (\App\CMS\Auth::can('posts.create')): ?>
  <button type="button" class="btn btn-primary"
          data-form-url="<?php echo $baseUrl; ?>/admin/blog/posts/form"
          data-form-title="New Post"
          data-form-size="modal-xl">
    <i class="pi pi-plus me-1"></i> New Post
  </button>
  <?php endif; ?>
</div>

<!-- Status filter tabs -->
<div class="vtx-panel mb-3">
  <div class="vtx-filter-tabs">
    <a href="<?php echo $baseUrl; ?>/admin/blog/posts"
       class="vtx-filter-tab <?php echo ($status ?? '') === '' ? 'active' : ''; ?>"
       data-ajax-panel="posts-table-panel">
      All
      <span class="count"><?php echo array_sum($counts ?? []); ?></span>
    </a>
    <?php
    $tabDefs = [
      'published' => ['label' => 'Published', 'icon' => 'pi-check-circle'],
      'draft'     => ['label' => 'Draft',     'icon' => 'pi-pencil'],
      'archived'  => ['label' => 'Archived',  'icon' => 'pi-archive'],
    ];
    foreach ($tabDefs as $key => $tab): ?>
    <a href="<?php echo $baseUrl; ?>/admin/blog/posts?status=<?php echo $key; ?>"
       class="vtx-filter-tab <?php echo ($status ?? '') === $key ? 'active' : ''; ?>"
       data-ajax-panel="posts-table-panel">
      <i class="pi <?php echo $tab['icon']; ?>"></i>
      <?php echo $tab['label']; ?>
      <?php if (isset($counts[$key])): ?>
      <span class="count"><?php echo $counts[$key]; ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Search + Bulk bar -->
<div class="vtx-panel mb-3" id="posts-search-bar" data-ajax-refreshable>
  <div class="vtx-panel-body" style="padding:.75rem 1rem;">
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <form method="GET" action="<?php echo $baseUrl; ?>/admin/blog/posts"
            data-ajax-panel="posts-table-panel"
            style="display:flex;gap:.75rem;align-items:center;flex:1;min-width:200px;">
        <?php if (!empty($status)): ?>
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
        <?php endif; ?>
        <input class="form-control form-control-sm" type="search" name="search"
               value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Search posts…"
               style="max-width:320px;">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
        <?php if (!empty($search)): ?>
        <a href="<?php echo $baseUrl; ?>/admin/blog/posts<?php echo $status ? '?status=' . $status : ''; ?>"
           class="btn btn-link btn-sm text-muted"
           data-ajax-panel="posts-table-panel">Clear</a>
        <?php endif; ?>
      </form>

      <div id="vtx-bulk-bar" style="display:none;gap:.5rem;align-items:center;">
        <span id="vtx-bulk-count" style="font-size:.8125rem;color:var(--ps-text-muted);"></span>
        <form id="vtx-bulk-form" method="POST" action="<?php echo $baseUrl; ?>/admin/blog/posts/bulk">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
          <input type="hidden" name="bulk_action" id="vtx-bulk-action" value="">
        </form>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="vtxBulkSubmit('publish')">
          <i class="pi pi-check-circle me-1"></i> Publish
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="vtxBulkSubmit('draft')">
          <i class="pi pi-pencil me-1"></i> Draft
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="vtxBulkConfirmDelete()">
          <i class="pi pi-trash me-1"></i> Delete
        </button>
      </div>
    </div>
  </div>
</div>

<div class="vtx-panel" id="posts-table-panel">
  <?php if (empty($posts)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-edit"></i></div>
    <div class="vtx-empty-title">No posts<?php echo !empty($status) ? ' with status "' . htmlspecialchars($status) . '"' : ''; ?></div>
    <div class="vtx-empty-desc">
      <?php if (!empty($search)): ?>
      No posts match your search.
      <a href="<?php echo $baseUrl; ?>/admin/blog/posts<?php echo $status ? '?status=' . $status : ''; ?>">Clear search</a>
      <?php elseif (\App\CMS\Auth::can('posts.create')): ?>
      <button type="button" class="btn btn-link p-0"
              data-form-url="<?php echo $baseUrl; ?>/admin/blog/posts/form"
              data-form-title="New Post"
              data-form-size="modal-xl">Create your first post</button>
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table" data-vtx-table>
      <thead>
        <tr>
          <th style="width:36px;padding-left:.875rem;">
            <input type="checkbox" id="vtx-check-all" style="cursor:pointer;" title="Select all">
          </th>
          <th>Title</th>
          <th style="width:110px;">Status</th>
          <th style="width:80px;">Read</th>
          <th style="width:130px;">Date</th>
          <th style="width:90px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($posts as $post):
          $statusClass = match($post['status'] ?? '') {
            'published' => 'success',
            'draft'     => 'warning',
            'archived'  => 'gray',
            default     => 'gray',
          };
        ?>
        <tr>
          <td style="padding-left:.875rem;">
            <input type="checkbox" class="vtx-row-check" value="<?php echo $post['id']; ?>" style="cursor:pointer;">
          </td>
          <td class="cell-primary">
            <div style="font-weight:600;color:var(--ps-text-primary);">
              <?php echo htmlspecialchars($post['title']); ?>
            </div>
            <?php if (!empty($post['author_name'])): ?>
            <div style="font-size:.75rem;color:var(--ps-text-muted);margin-top:.125rem;">
              <?php echo htmlspecialchars($post['author_name']); ?>
            </div>
            <?php endif; ?>
          </td>
          <td><span class="vtx-tag <?php echo $statusClass; ?>"><?php echo ucfirst($post['status']); ?></span></td>
          <td style="font-size:.8125rem;color:var(--ps-text-muted);">
            <?php echo ($post['reading_time'] ?? 0) > 0 ? $post['reading_time'] . ' min' : '—'; ?>
          </td>
          <td style="font-size:.8125rem;color:var(--ps-text-muted);">
            <?php echo !empty($post['published_at'])
              ? date('M d, Y', strtotime($post['published_at']))
              : date('M d, Y', strtotime($post['created_at'])); ?>
          </td>
          <td>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <?php if ($post['status'] === 'published'): ?>
              <a href="<?php echo $baseUrl; ?>/blog/<?php echo htmlspecialchars($post['slug'] ?? ''); ?>"
                 target="_blank" rel="noopener" class="vtx-icon-btn" title="View on site">
                <i class="pi pi-external-link"></i>
              </a>
              <?php endif; ?>
              <?php if (\App\CMS\Auth::can('posts.edit')): ?>
              <button type="button" class="vtx-icon-btn" title="Edit"
                      data-form-url="<?php echo $baseUrl; ?>/admin/blog/posts/<?php echo $post['id']; ?>/form"
                      data-form-title="Edit Post"
                      data-form-size="modal-xl">
                <i class="pi pi-edit"></i>
              </button>
              <?php endif; ?>
              <?php if (\App\CMS\Auth::can('posts.delete')): ?>
              <form id="del-post-<?php echo $post['id']; ?>" method="POST"
                    action="<?php echo $baseUrl; ?>/admin/blog/posts/<?php echo $post['id']; ?>/delete"
                    style="display:none;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Move to trash"
                      data-confirm-form="del-post-<?php echo $post['id']; ?>"
                      data-confirm-title="Delete Post"
                      data-confirm-message="Move &quot;<?php echo htmlspecialchars($post['title']); ?>&quot; to trash?"
                      data-confirm-label="Delete"
                      data-confirm-class="btn-danger"
                      data-confirm-ajax="true">
                <i class="pi pi-trash"></i>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (($pages ?? 1) > 1): ?>
  <div class="vtx-panel-foot" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
    <span style="font-size:.8125rem;color:var(--ps-text-muted);">
      Showing <?php echo count($posts); ?> of <?php echo $total; ?> posts
    </span>
    <div style="display:flex;gap:.375rem;">
      <?php for ($pi = max(1, $page - 2); $pi <= min($pages, $page + 2); $pi++): ?>
      <a href="<?php echo $baseUrl; ?>/admin/blog/posts?page=<?php echo $pi;
         echo !empty($status) ? '&status=' . $status : '';
         echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
         class="btn btn-sm <?php echo $pi === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>">
        <?php echo $pi; ?>
      </a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
