<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-file me-2 text-primary"></i>Pages</h1>
    <p class="vtx-page-desc">Manage static pages on your site.</p>
  </div>
  <?php if (\App\CMS\Auth::can('pages.create')): ?>
  <button type="button" class="btn btn-primary"
          data-form-url="{{baseUrl}}/admin/pages/form"
          data-form-title="New Page">
    <i class="pi pi-plus me-1"></i> New Page
  </button>
  <?php endif; ?>
</div>

<!-- Search -->
<div class="vtx-panel mb-3">
  <div class="vtx-panel-body" style="padding:.75rem 1rem;">
    <form method="GET" action="{{baseUrl}}/admin/pages" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <div style="flex:1;min-width:200px;">
        <input class="form-control form-control-sm" type="search" name="search"
               value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Search pages…">
      </div>
      <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
      <?php if (!empty($search)): ?>
      <a href="{{baseUrl}}/admin/pages" class="btn btn-link btn-sm text-muted">Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Table -->
<div class="vtx-panel" id="pages-table-panel">
  <?php if (empty($pages)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-file"></i></div>
    <div class="vtx-empty-title">No pages yet</div>
    <div class="vtx-empty-desc">Create your first static page.</div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Slug</th>
          <th>Status</th>
          <th>Order</th>
          <th>Created</th>
          <th style="width:80px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pages as $p): ?>
        <tr>
          <td>
            <span style="font-weight:600;"><?php echo htmlspecialchars($p['title']); ?></span>
          </td>
          <td>
            <code style="font-size:.8125rem;">/<?php echo htmlspecialchars($p['slug']); ?></code>
          </td>
          <td>
            <?php
            $statusColors = ['published' => 'success', 'draft' => 'secondary', 'archived' => 'warning'];
            $sc = $statusColors[$p['status']] ?? 'secondary';
            ?>
            <span class="vtx-badge vtx-badge-<?php echo $sc; ?>">
              <?php echo ucfirst($p['status']); ?>
            </span>
          </td>
          <td><?php echo (int) $p['sort_order']; ?></td>
          <td style="font-size:.8125rem;color:var(--ps-text-muted);">
            <?php echo date('M j, Y', strtotime($p['created_at'])); ?>
          </td>
          <td>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <?php if (\App\CMS\Auth::can('pages.edit')): ?>
              <button type="button" class="vtx-icon-btn" title="Edit"
                      data-form-url="{{baseUrl}}/admin/pages/<?php echo $p['id']; ?>/form"
                      data-form-title="Edit Page">
                <i class="pi pi-edit"></i>
              </button>
              <?php endif; ?>
              <?php if (\App\CMS\Auth::can('pages.delete')): ?>
              <form id="del-page-<?php echo $p['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/pages/<?php echo $p['id']; ?>/delete" style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Delete"
                      data-confirm-form="del-page-<?php echo $p['id']; ?>"
                      data-confirm-title="Delete Page"
                      data-confirm-message="Delete &quot;<?php echo htmlspecialchars($p['title']); ?>&quot;? This cannot be undone."
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

  <!-- Pagination -->
  <?php if (($totalPages ?? 1) > 1): ?>
  <div class="vtx-panel-body" style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--ps-border);padding-top:.75rem;">
    <span style="font-size:.8125rem;color:var(--ps-text-muted);"><?php echo (int)$total; ?> pages total</span>
    <div style="display:flex;gap:.25rem;">
      <?php if ($page > 1): ?>
      <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search ?? ''); ?>"
         class="btn btn-outline-secondary btn-sm"><i class="pi pi-arrow-left"></i></a>
      <?php endif; ?>
      <?php if ($page < ($pages ?? 1)): ?>
      <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search ?? ''); ?>"
         class="btn btn-outline-secondary btn-sm"><i class="pi pi-arrow-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
