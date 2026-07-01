<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-clipboard me-2 text-primary"></i>Forms</h1>
    <p class="vtx-page-desc">Build forms, collect submissions, and export data.</p>
  </div>
  <?php if (\App\CMS\Auth::can('forms.manage')): ?>
  <button type="button" class="btn btn-primary"
          data-form-url="<?php echo $baseUrl; ?>/admin/forms/create"
          data-form-title="New Form"
          data-form-size="modal-md">
    <i class="pi pi-plus me-1"></i> New Form
  </button>
  <?php endif; ?>
</div>

<!-- Search bar -->
<div class="vtx-panel mb-3">
  <div class="vtx-panel-body" style="padding:.75rem 1rem;">
    <form method="GET" action="<?php echo $baseUrl; ?>/admin/forms"
          style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <input class="form-control form-control-sm" type="search" name="search"
             value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Search forms..."
             style="max-width:320px;">
      <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
      <?php if (!empty($search)): ?>
      <a href="<?php echo $baseUrl; ?>/admin/forms" class="btn btn-link btn-sm text-muted">Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Flash -->
<?php if (!empty($flash['message'])): ?>
<div class="vtx-alert vtx-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> mb-3">
  <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<!-- Forms table -->
<div class="vtx-panel" id="forms-table-panel">
  <div class="vtx-panel-body p-0">
    <?php if (empty($forms)): ?>
    <div style="padding:3rem;text-align:center;color:var(--ps-text-muted);">
      <i class="pi pi-clipboard pi-3x mb-3" style="opacity:.3;display:block;margin:0 auto 1rem;"></i>
      <p class="mb-1" style="font-size:1rem;font-weight:600;">No forms yet</p>
      <?php if (\App\CMS\Auth::can('forms.manage')): ?>
      <p class="mb-0">
        <button type="button" class="btn btn-sm btn-primary"
                data-form-url="<?php echo $baseUrl; ?>/admin/forms/create"
                data-form-title="New Form"
                data-form-size="modal-md">
          Create your first form
        </button>
      </p>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="vtx-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Slug / URL</th>
          <th style="text-align:center;">Submissions</th>
          <th style="text-align:center;">Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($forms as $form): ?>
        <tr>
          <td>
            <strong><?php echo htmlspecialchars($form['name']); ?></strong>
            <?php if ($form['unread_count'] > 0): ?>
            <span class="badge badge-primary ms-1"><?php echo (int) $form['unread_count']; ?> new</span>
            <?php endif; ?>
          </td>
          <td>
            <span style="font-family:monospace;font-size:.8125rem;color:var(--ps-text-muted);">/forms/<?php echo htmlspecialchars($form['slug']); ?></span>
            <a href="<?php echo $baseUrl; ?>/forms/<?php echo htmlspecialchars($form['slug']); ?>"
               target="_blank" class="ms-1" style="font-size:.75rem;">
              <i class="pi pi-external-link"></i>
            </a>
          </td>
          <td style="text-align:center;">
            <a href="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions"
               style="font-weight:600;">
              <?php echo (int) $form['submission_count']; ?>
            </a>
          </td>
          <td style="text-align:center;">
            <?php if ($form['status'] === 'active'): ?>
            <span class="badge badge-success">Active</span>
            <?php else: ?>
            <span class="badge badge-secondary">Inactive</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right;white-space:nowrap;">
            <a href="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/builder"
               class="btn btn-sm btn-outline-primary me-1" title="Field Builder">
              <i class="pi pi-sliders"></i>
            </a>
            <a href="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions"
               class="btn btn-sm btn-outline-secondary me-1" title="Submissions">
              <i class="pi pi-inbox"></i>
            </a>
            <?php if (\App\CMS\Auth::can('forms.manage')): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                    data-form-url="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/edit"
                    data-form-title="Edit Form"
                    data-form-size="modal-md"
                    title="Edit">
              <i class="pi pi-edit"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    data-vtx-confirm="Delete &quot;<?php echo htmlspecialchars($form['name']); ?>&quot;? All submissions will also be deleted."
                    data-vtx-action="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/delete"
                    data-vtx-csrf="<?php echo htmlspecialchars($csrf_token ?? ''); ?>"
                    title="Delete">
              <i class="pi pi-trash"></i>
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Pagination -->
    <?php if (($pages ?? 1) > 1): ?>
    <div class="vtx-pagination" style="padding:.75rem 1rem;">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="?page=<?php echo $p; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
         class="vtx-page-link <?php echo $p === ($page ?? 1) ? 'active' : ''; ?>">
        <?php echo $p; ?>
      </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
