<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-layers me-2 text-primary"></i>Categories</h1>
    <p class="vtx-page-desc">Organise posts into broad topics.</p>
  </div>
  <?php if (\App\CMS\Auth::can('categories.create')): ?>
  <button type="button" class="btn btn-primary"
          data-form-url="{{baseUrl}}/admin/blog/categories/form"
          data-form-title="New Category">
    <i class="pi pi-plus me-1"></i> Add Category
  </button>
  <?php endif; ?>
</div>

<div class="vtx-panel mb-3" id="categories-search-bar" data-ajax-refreshable>
  <div class="vtx-panel-body" style="padding:.75rem 1rem;">
    <form method="GET" action="{{baseUrl}}/admin/blog/categories"
          data-ajax-panel="categories-table-panel"
          style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <div style="flex:1;min-width:200px;">
        <input class="form-control form-control-sm" type="search" name="search"
               value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Search categories…">
      </div>
      <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
      <?php if (!empty($search)): ?>
      <a href="{{baseUrl}}/admin/blog/categories" class="btn btn-link btn-sm text-muted"
         data-ajax-panel="categories-table-panel">Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="vtx-panel" id="categories-table-panel">
  <?php if (empty($categories)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-layers"></i></div>
    <div class="vtx-empty-title">No categories yet</div>
    <div class="vtx-empty-desc">Categories help readers find related posts.</div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table" data-vtx-table data-sortable>
      <thead>
        <tr>
          <th data-sort="name">Name</th>
          <th data-sort="slug">Slug</th>
          <th data-sort="post_count" style="width:100px;">Posts</th>
          <th style="width:80px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $cat): ?>
        <tr>
          <td class="cell-primary"><?php echo htmlspecialchars($cat['name']); ?></td>
          <td><code style="font-size:.75rem;color:var(--ps-text-muted);"><?php echo htmlspecialchars($cat['slug']); ?></code></td>
          <td><span class="vtx-tag gray"><?php echo $cat['post_count']; ?></span></td>
          <td>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <?php if (\App\CMS\Auth::can('categories.edit')): ?>
              <button type="button" class="vtx-icon-btn" title="Edit"
                      data-form-url="{{baseUrl}}/admin/blog/categories/<?php echo $cat['id']; ?>/form"
                      data-form-title="Edit Category">
                <i class="pi pi-edit"></i>
              </button>
              <?php endif; ?>
              <?php if (\App\CMS\Auth::can('categories.delete')): ?>
              <form id="del-cat-<?php echo $cat['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/blog/categories/<?php echo $cat['id']; ?>/delete" style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Delete"
                      data-confirm-form="del-cat-<?php echo $cat['id']; ?>"
                      data-confirm-title="Delete Category"
                      data-confirm-message="Delete &quot;<?php echo htmlspecialchars($cat['name']); ?>&quot;? Posts in this category will be unaffected."
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
  <div class="vtx-panel-body" style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--ps-border);padding-top:.75rem;">
    <span style="font-size:.8125rem;color:var(--ps-text-muted);">Showing <?php echo count($categories); ?> of <?php echo $total; ?></span>
    <div style="display:flex;gap:.25rem;">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search ?? ''); ?>"
         class="btn btn-sm <?php echo $p === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>"
         data-ajax-panel="categories-table-panel">
        <?php echo $p; ?>
      </a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
