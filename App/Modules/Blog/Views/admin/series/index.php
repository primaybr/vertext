<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-layers me-2 text-primary"></i>Post Series</h1>
    <p class="vtx-page-desc">Group related posts into ordered series with prev/next navigation.</p>
  </div>
  <button type="button" class="btn btn-primary btn-sm"
          data-form-url="{{baseUrl}}/admin/blog/series/form"
          data-form-title="New Series"
          data-form-size="modal-lg">
    <i class="pi pi-plus me-1"></i> New Series
  </button>
</div>

<div class="vtx-panel">
  <?php if (empty($series)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-layers"></i></div>
    <div class="vtx-empty-title">No series yet</div>
    <div class="vtx-empty-desc">
      <button type="button" class="btn btn-link p-0"
              data-form-url="{{baseUrl}}/admin/blog/series/form"
              data-form-title="New Series"
              data-form-size="modal-lg">Create the first series</button>
    </div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table" data-vtx-table data-sortable>
      <thead>
        <tr>
          <th data-sort="title">Title</th>
          <th data-sort="slug">Slug</th>
          <th data-sort="posts">Posts</th>
          <th style="width:80px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($series as $s): ?>
        <tr>
          <td class="cell-primary"><?php echo htmlspecialchars($s['title']); ?></td>
          <td><code style="font-size:.8125rem;"><?php echo htmlspecialchars($s['slug']); ?></code></td>
          <td>
            <span style="font-size:.8125rem;font-weight:600;"><?php echo (int)($s['post_count'] ?? 0); ?></span>
            <span class="cell-muted ms-1">posts</span>
          </td>
          <td>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <button type="button" class="vtx-icon-btn" title="Edit"
                      data-form-url="{{baseUrl}}/admin/blog/series/<?php echo $s['id']; ?>/form"
                      data-form-title="Edit Series"
                      data-form-size="modal-lg">
                <i class="pi pi-edit"></i>
              </button>
              <form id="del-series-<?php echo $s['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/blog/series/<?php echo $s['id']; ?>/delete"
                    style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Delete"
                      data-confirm-form="del-series-<?php echo $s['id']; ?>"
                      data-confirm-title="Delete Series"
                      data-confirm-message="Delete &quot;<?php echo htmlspecialchars($s['title']); ?>&quot;? Posts will not be deleted."
                      data-confirm-label="Delete"
                      data-confirm-class="btn-danger"
                      data-confirm-ajax="true">
                <i class="pi pi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
