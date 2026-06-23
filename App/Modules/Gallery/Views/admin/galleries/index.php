<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-images me-2 text-primary"></i>Gallery</h1>
    <p class="vtx-page-desc">Create and manage public photo albums.</p>
  </div>
  <?php if (\App\CMS\Auth::can('gallery.create')): ?>
  <button type="button" class="btn btn-primary"
          data-form-url="{{baseUrl}}/admin/gallery/form"
          data-form-title="New Album">
    <i class="pi pi-plus me-1"></i> New Album
  </button>
  <?php endif; ?>
</div>

<div class="vtx-panel">
  <?php if (empty($galleries)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-images"></i></div>
    <div class="vtx-empty-title">No albums yet</div>
    <div class="vtx-empty-desc">Create your first gallery album.</div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table">
      <thead>
        <tr>
          <th style="width:64px;"></th>
          <th>Title</th>
          <th>Images</th>
          <th>Status</th>
          <th>Created</th>
          <th style="width:100px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($galleries as $g): ?>
        <tr>
          <td>
            <?php if ($g['cover_url']): ?>
            <img src="<?php echo htmlspecialchars($g['cover_url']); ?>"
                 alt="" style="width:48px;height:48px;object-fit:cover;border-radius:4px;">
            <?php else: ?>
            <div style="width:48px;height:48px;background:var(--ps-bg-alt);border-radius:4px;display:flex;align-items:center;justify-content:center;">
              <i class="pi pi-image" style="color:var(--ps-text-muted);font-size:1.25rem;"></i>
            </div>
            <?php endif; ?>
          </td>
          <td>
            <a href="{{baseUrl}}/admin/gallery/<?php echo $g['id']; ?>/items"
               style="font-weight:600;"><?php echo htmlspecialchars($g['title']); ?></a>
          </td>
          <td><?php echo (int) $g['item_count']; ?></td>
          <td>
            <?php $sc = ['published' => 'success', 'draft' => 'secondary', 'archived' => 'warning'][$g['status']] ?? 'secondary'; ?>
            <span class="vtx-badge vtx-badge-<?php echo $sc; ?>"><?php echo ucfirst($g['status']); ?></span>
          </td>
          <td style="font-size:.8125rem;color:var(--ps-text-muted);">
            <?php echo date('M j, Y', strtotime($g['created_at'])); ?>
          </td>
          <td>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <a href="{{baseUrl}}/admin/gallery/<?php echo $g['id']; ?>/items"
                 class="vtx-icon-btn" title="Manage Images"><i class="pi pi-images"></i></a>
              <?php if (\App\CMS\Auth::can('gallery.edit')): ?>
              <button type="button" class="vtx-icon-btn" title="Edit"
                      data-form-url="{{baseUrl}}/admin/gallery/<?php echo $g['id']; ?>/form"
                      data-form-title="Edit Album">
                <i class="pi pi-edit"></i>
              </button>
              <?php endif; ?>
              <?php if (\App\CMS\Auth::can('gallery.delete')): ?>
              <form id="del-gallery-<?php echo $g['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/gallery/<?php echo $g['id']; ?>/delete" style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Delete"
                      data-confirm-form="del-gallery-<?php echo $g['id']; ?>"
                      data-confirm-title="Delete Album"
                      data-confirm-message="Delete &quot;<?php echo htmlspecialchars($g['title']); ?>&quot; and all its images? This cannot be undone."
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
