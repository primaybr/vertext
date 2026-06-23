<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-tag me-2 text-primary"></i>Tags</h1>
    <p class="vtx-page-desc">Fine-grained labels attached to individual posts.</p>
  </div>
  <?php if (\App\CMS\Auth::can('tags.create')): ?>
  <button type="button" class="btn btn-primary"
          data-form-url="{{baseUrl}}/admin/blog/tags/form"
          data-form-title="New Tag">
    <i class="pi pi-plus me-1"></i> Add Tag
  </button>
  <?php endif; ?>
</div>

<div class="vtx-panel mb-3" id="tags-search-bar" data-ajax-refreshable>
  <div class="vtx-panel-body" style="padding:.75rem 1rem;">
    <form method="GET" action="{{baseUrl}}/admin/blog/tags"
          data-ajax-panel="tags-table-panel"
          style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <div style="flex:1;min-width:200px;">
        <input class="form-control form-control-sm" type="search" name="search"
               value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Search tags…">
      </div>
      <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
      <?php if (!empty($search)): ?>
      <a href="{{baseUrl}}/admin/blog/tags" class="btn btn-link btn-sm text-muted"
         data-ajax-panel="tags-table-panel">Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="vtx-panel" id="tags-table-panel">
  <?php if (empty($tags)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-tag"></i></div>
    <div class="vtx-empty-title">No tags yet</div>
    <div class="vtx-empty-desc">Tags are created automatically when you tag a post, or add them manually here.</div>
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
        <?php foreach ($tags as $tag): ?>
        <tr>
          <td class="cell-primary"><?php echo htmlspecialchars($tag['name']); ?></td>
          <td><code style="font-size:.75rem;color:var(--ps-text-muted);"><?php echo htmlspecialchars($tag['slug']); ?></code></td>
          <td><span class="vtx-tag gray"><?php echo $tag['post_count']; ?></span></td>
          <td>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <?php if (\App\CMS\Auth::can('tags.edit')): ?>
              <button type="button" class="vtx-icon-btn" title="Edit"
                      data-form-url="{{baseUrl}}/admin/blog/tags/<?php echo $tag['id']; ?>/form"
                      data-form-title="Edit Tag">
                <i class="pi pi-edit"></i>
              </button>
              <?php endif; ?>
              <?php if (\App\CMS\Auth::can('tags.delete')): ?>
              <form id="del-tag-<?php echo $tag['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/blog/tags/<?php echo $tag['id']; ?>/delete" style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Delete"
                      data-confirm-form="del-tag-<?php echo $tag['id']; ?>"
                      data-confirm-title="Delete Tag"
                      data-confirm-message="Delete tag &quot;<?php echo htmlspecialchars($tag['name']); ?>&quot;?"
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
  <?php endif; ?>
</div>
