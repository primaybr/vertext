<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title">
      <a href="{{baseUrl}}/admin/navigation" class="text-muted" style="font-weight:400;font-size:1rem;">Navigation</a>
      <span class="text-muted mx-1">/</span>
      <i class="pi pi-bars me-2 text-primary"></i><?php echo htmlspecialchars($menu['name']); ?>
    </h1>
    <p class="vtx-page-desc">
      Theme slug: <code><?php echo htmlspecialchars($menu['slug']); ?></code>
      - use <code>NavHelper::getMenu('<?php echo htmlspecialchars($menu['slug']); ?>')</code> in your theme.
    </p>
  </div>
  <?php if (\App\CMS\Auth::can('navigation.manage')): ?>
  <div style="display:flex;gap:.5rem;align-items:center;">
    <?php if (!empty($moduleRoutes)): ?>
    <button type="button" class="btn btn-outline-secondary" id="sync-modules-btn" title="Insert any module nav routes not yet in this menu">
      <i class="pi pi-refresh me-1"></i> Sync module routes
    </button>
    <form id="sync-modules-form" method="POST" action="{{baseUrl}}/admin/navigation/<?php echo $menu['id']; ?>/sync-modules" style="display:none;">
      <input type="hidden" name="csrf_token" value="{{csrf_token}}">
    </form>
    <?php endif; ?>
    <button type="button" class="btn btn-primary" id="add-item-btn">
      <i class="pi pi-plus me-1"></i> Add Item
    </button>
  </div>
  <?php endif; ?>
</div>

<div class="vtx-panel" id="items-panel" data-menu-id="<?php echo htmlspecialchars((string) $menu['id'], ENT_QUOTES); ?>">
  <?php if (empty($parents)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-list"></i></div>
    <div class="vtx-empty-title">No items yet</div>
    <div class="vtx-empty-desc">Add links to build your navigation menu.</div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table" id="items-table">
      <thead>
        <tr>
          <th style="width:32px;"></th>
          <th>Label</th>
          <th>Type</th>
          <th>URL / Page</th>
          <th style="width:90px;"></th>
        </tr>
      </thead>
      <tbody id="items-tbody">
        <?php foreach ($parents as $idx => $item): ?>
        <tr data-id="<?php echo $item['id']; ?>" data-sort="<?php echo (int)$item['sort_order']; ?>">
          <td style="color:var(--ps-text-muted);cursor:grab;text-align:center;">
            <i class="pi pi-equals" style="font-size:.875rem;"></i>
          </td>
          <td>
            <span style="font-weight:500;"><?php echo htmlspecialchars($item['label']); ?></span>
            <?php if (!empty($item['open_in_new'])): ?>
            <span class="vtx-tag" style="margin-left:4px;font-size:.7rem;">new tab</span>
            <?php endif; ?>
          </td>
          <td>
            <?php $tagClass = match($item['type']) { 'page' => 'info', 'module' => 'primary', default => '' }; ?>
            <span class="vtx-tag <?php echo $tagClass; ?>">
              <?php echo $item['type'] === 'module' ? 'Module' : ucfirst(htmlspecialchars($item['type'])); ?>
            </span>
          </td>
          <td style="font-size:.8125rem;color:var(--ps-text-muted);max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?php if ($item['type'] === 'page'): ?>
            /<?php echo htmlspecialchars($item['page_slug'] ?? ''); ?>
            <?php else: ?>
            <?php echo htmlspecialchars($item['url'] ?? ''); ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if (\App\CMS\Auth::can('navigation.manage')): ?>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <button type="button" class="vtx-icon-btn edit-item-btn" title="Edit"
                      data-id="<?php echo $item['id']; ?>"
                      data-menu-id="<?php echo $menu['id']; ?>"
                      data-type="<?php echo htmlspecialchars($item['type']); ?>"
                      data-label="<?php echo htmlspecialchars($item['label']); ?>"
                      data-url="<?php echo htmlspecialchars($item['url'] ?? ''); ?>"
                      data-page-slug="<?php echo htmlspecialchars($item['page_slug'] ?? ''); ?>"
                      data-open-in-new="<?php echo $item['open_in_new'] ? '1' : '0'; ?>">
                <i class="pi pi-edit"></i>
              </button>
              <form id="del-item-<?php echo $item['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/navigation/<?php echo $menu['id']; ?>/items/<?php echo $item['id']; ?>/delete"
                    style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Remove"
                      data-confirm-form="del-item-<?php echo $item['id']; ?>"
                      data-confirm-title="Remove Item"
                      data-confirm-message="Remove &quot;<?php echo htmlspecialchars($item['label']); ?>&quot; from this menu?"
                      data-confirm-label="Remove"
                      data-confirm-class="btn-danger"
                      data-confirm-ajax="true">
                <i class="pi pi-trash"></i>
              </button>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php if (!empty($children[$item['id']])): ?>
        <?php foreach ($children[$item['id']] as $child): ?>
        <tr data-id="<?php echo $child['id']; ?>" data-parent-id="<?php echo $item['id']; ?>" data-sort="<?php echo (int)$child['sort_order']; ?>" style="background:var(--ps-surface-alt,#f8f9fa);">
          <td style="color:var(--ps-text-muted);text-align:center;padding-left:2rem;">
            <i class="pi pi-arrow-right" style="font-size:.75rem;"></i>
          </td>
          <td>
            <span style="color:var(--ps-text-muted);font-size:.875rem;"><?php echo htmlspecialchars($child['label']); ?></span>
            <?php if (!empty($child['open_in_new'])): ?>
            <span class="vtx-tag" style="margin-left:4px;font-size:.7rem;">new tab</span>
            <?php endif; ?>
          </td>
          <td>
            <?php $childTagClass = match($child['type']) { 'page' => 'info', 'module' => 'primary', default => '' }; ?>
            <span class="vtx-tag <?php echo $childTagClass; ?>" style="font-size:.75rem;">
              <?php echo $child['type'] === 'module' ? 'Module' : ucfirst(htmlspecialchars($child['type'])); ?>
            </span>
          </td>
          <td style="font-size:.8125rem;color:var(--ps-text-muted);">
            <?php if ($child['type'] === 'page'): ?>
            /<?php echo htmlspecialchars($child['page_slug'] ?? ''); ?>
            <?php else: ?>
            <?php echo htmlspecialchars($child['url'] ?? ''); ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if (\App\CMS\Auth::can('navigation.manage')): ?>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <button type="button" class="vtx-icon-btn edit-item-btn" title="Edit"
                      data-id="<?php echo $child['id']; ?>"
                      data-menu-id="<?php echo $menu['id']; ?>"
                      data-type="<?php echo htmlspecialchars($child['type']); ?>"
                      data-label="<?php echo htmlspecialchars($child['label']); ?>"
                      data-url="<?php echo htmlspecialchars($child['url'] ?? ''); ?>"
                      data-page-slug="<?php echo htmlspecialchars($child['page_slug'] ?? ''); ?>"
                      data-open-in-new="<?php echo $child['open_in_new'] ? '1' : '0'; ?>">
                <i class="pi pi-edit"></i>
              </button>
              <form id="del-item-<?php echo $child['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/navigation/<?php echo $menu['id']; ?>/items/<?php echo $child['id']; ?>/delete"
                    style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Remove"
                      data-confirm-form="del-item-<?php echo $child['id']; ?>"
                      data-confirm-title="Remove Item"
                      data-confirm-message="Remove &quot;<?php echo htmlspecialchars($child['label']); ?>&quot;?"
                      data-confirm-label="Remove"
                      data-confirm-class="btn-danger"
                      data-confirm-ajax="true">
                <i class="pi pi-trash"></i>
              </button>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="vtx-panel-body" style="border-top:1px solid var(--ps-border);padding:.75rem 1rem;">
    <p style="margin:0;font-size:.8125rem;color:var(--ps-text-muted);">
      <i class="pi pi-info-circle me-1"></i>
      Drag rows to reorder, or use the order controls. Changes are saved automatically.
    </p>
  </div>
  <?php endif; ?>
</div>

<?php if (\App\CMS\Auth::can('navigation.manage')): ?>
<!-- Add / Edit Item Modal -->
<div class="modal fade" id="item-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="item-modal-title">Add Item</h5>
        <button type="button" class="btn-close" data-dismiss="modal"></button>
      </div>
      <form id="item-form" method="POST" action="">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="{{csrf_token}}">
          <input type="hidden" name="_item_id" id="item-id-field" value="">

          <div class="mb-3">
            <label class="form-label">Type</label>
            <select class="form-select" name="type" id="item-type-select">
              <option value="custom">Custom URL</option>
              <?php if ($pagesEnabled && !empty($availablePages)): ?>
              <option value="page">Page</option>
              <?php endif; ?>
              <?php if (!empty($moduleRoutes)): ?>
              <option value="module">Module Route</option>
              <?php endif; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Label <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="label" id="item-label" required placeholder="e.g. About Us">
          </div>

          <div id="custom-url-field" class="mb-3">
            <label class="form-label">URL <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="url" id="item-url" placeholder="https://example.com or /about">
          </div>

          <?php if ($pagesEnabled && !empty($availablePages)): ?>
          <div id="page-select-field" class="mb-3" style="display:none;">
            <label class="form-label">Page <span class="text-danger">*</span></label>
            <select class="form-select" name="page_slug" id="item-page-slug">
              <option value="">- Select page -</option>
              <?php foreach ($availablePages as $pg): ?>
              <option value="<?php echo htmlspecialchars($pg['slug']); ?>"><?php echo htmlspecialchars($pg['title']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <?php if (!empty($moduleRoutes)): ?>
          <div id="module-route-field" class="mb-3" style="display:none;">
            <label class="form-label">Module Route <span class="text-danger">*</span></label>
            <select class="form-select" id="item-module-route">
              <option value="">- Select route -</option>
              <?php foreach ($moduleRoutes as $mr): ?>
              <option value="<?php echo htmlspecialchars($mr['path']); ?>"
                      data-label="<?php echo htmlspecialchars($mr['label']); ?>">
                <?php echo htmlspecialchars($mr['label']); ?> &mdash; <?php echo htmlspecialchars($mr['path']); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" name="open_in_new" id="item-open-in-new" value="1">
            <label class="form-check-label" for="item-open-in-new">Open in new tab</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="item-submit-btn">
            <i class="pi pi-check me-1"></i><span id="item-submit-label">Add Item</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>
