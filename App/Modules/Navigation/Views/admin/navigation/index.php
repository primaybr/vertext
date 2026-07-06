<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-bars me-2 text-primary"></i>Navigation</h1>
    <p class="vtx-page-desc">Manage front-end navigation menus and their items.</p>
  </div>
  <?php if (\App\CMS\Auth::can('navigation.manage')): ?>
  <button type="button" class="btn btn-primary" id="new-menu-btn">
    <i class="pi pi-plus me-1"></i> New Menu
  </button>
  <?php endif; ?>
</div>

<div class="vtx-panel" id="menus-panel">
  <?php if (empty($menus)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-bars"></i></div>
    <div class="vtx-empty-title">No menus yet</div>
    <div class="vtx-empty-desc">Create a navigation menu to start adding links.</div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Slug</th>
          <th>Items</th>
          <th style="width:100px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($menus as $menu): ?>
        <tr>
          <td>
            <a href="{{baseUrl}}/admin/navigation/<?php echo htmlspecialchars($menu['id']); ?>"
               style="font-weight:600;color:var(--ps-text);">
              <?php echo htmlspecialchars($menu['name']); ?>
            </a>
            <?php if ($menu['slug'] === 'primary'): ?>
            <span class="vtx-tag info" style="margin-left:6px;">Primary</span>
            <?php endif; ?>
          </td>
          <td><code style="font-size:.8125rem;"><?php echo htmlspecialchars($menu['slug']); ?></code></td>
          <td class="cell-muted"><?php echo (int) $menu['item_count']; ?> item<?php echo $menu['item_count'] !== 1 ? 's' : ''; ?></td>
          <td>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <a href="{{baseUrl}}/admin/navigation/<?php echo htmlspecialchars($menu['id']); ?>"
                 class="vtx-icon-btn" title="Edit menu">
                <i class="pi pi-edit"></i>
              </a>
              <?php if (\App\CMS\Auth::can('navigation.manage') && $menu['slug'] !== 'primary'): ?>
              <form id="del-menu-<?php echo $menu['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/navigation/<?php echo htmlspecialchars($menu['id']); ?>/delete"
                    style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Delete menu"
                      data-confirm-form="del-menu-<?php echo $menu['id']; ?>"
                      data-confirm-title="Delete Menu"
                      data-confirm-message="Delete &quot;<?php echo htmlspecialchars($menu['name']); ?>&quot;? All items will be removed."
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

<?php if (\App\CMS\Auth::can('navigation.manage')): ?>
<!-- New Menu Modal -->
<div class="modal fade" id="new-menu-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">New Menu</h5>
        <button type="button" class="btn-close" data-dismiss="modal"></button>
      </div>
      <form id="new-menu-form" method="POST" action="{{baseUrl}}/admin/navigation/store">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="{{csrf_token}}">
          <div class="mb-3">
            <label class="form-label">Menu Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" required placeholder="e.g. Footer Links">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="new-menu-submit">
            <i class="pi pi-plus me-1"></i>Create Menu
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>
