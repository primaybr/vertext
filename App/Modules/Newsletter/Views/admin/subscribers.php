<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-users me-2 text-primary"></i>Subscribers</h1>
    <p class="vtx-page-desc">Manage newsletter subscribers.</p>
  </div>
  <div style="display:flex;gap:.5rem;">
    <?php if (\App\CMS\Auth::can('newsletter.export')): ?>
    <a href="<?php echo $baseUrl; ?>/admin/newsletter/subscribers/export" class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-save me-1"></i> Export CSV
    </a>
    <?php endif; ?>
    <?php if (\App\CMS\Auth::can('newsletter.manage')): ?>
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="document.getElementById('nl-import-modal').style.display='flex'">
      <i class="pi pi-plus me-1"></i> Import
    </button>
    <button type="button" class="btn btn-primary btn-sm"
            onclick="document.getElementById('nl-add-modal').style.display='flex'">
      <i class="pi pi-plus me-1"></i> Add Subscriber
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Status tabs -->
<div class="vtx-panel mb-3">
  <div class="vtx-filter-tabs">
    <a href="<?php echo $baseUrl; ?>/admin/newsletter/subscribers"
       class="vtx-filter-tab <?php echo empty($status) ? 'active' : ''; ?>">
      All <span class="count"><?php echo array_sum($counts ?? []); ?></span>
    </a>
    <?php foreach (['active' => 'Active', 'pending' => 'Pending', 'unsubscribed' => 'Unsubscribed'] as $st => $label): ?>
    <a href="?status=<?php echo $st; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
       class="vtx-filter-tab <?php echo $status === $st ? 'active' : ''; ?>">
      <?php echo $label; ?>
      <?php if (!empty($counts[$st])): ?><span class="count"><?php echo $counts[$st]; ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Search + Flash -->
<div class="vtx-panel mb-3">
  <div class="vtx-panel-body" style="padding:.75rem 1rem;">
    <form method="GET" action="<?php echo $baseUrl; ?>/admin/newsletter/subscribers"
          style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <?php if ($status): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>"><?php endif; ?>
      <input class="form-control form-control-sm" type="search" name="search"
             value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Search by email or name..."
             style="max-width:320px;">
      <button type="submit" class="btn btn-outline-secondary btn-sm">Search</button>
      <?php if ($search): ?>
      <a href="?<?php echo $status ? 'status=' . urlencode($status) : ''; ?>" class="btn btn-link btn-sm text-muted">Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="vtx-alert vtx-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> mb-3">
  <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<!-- Table -->
<div class="vtx-panel">
  <div class="vtx-panel-body p-0">
    <?php if (empty($subs)): ?>
    <div style="padding:3rem;text-align:center;color:var(--ps-text-muted);">
      <i class="pi pi-users pi-3x mb-3" style="opacity:.3;display:block;margin:0 auto 1rem;"></i>
      <p class="mb-0">No subscribers yet.</p>
    </div>
    <?php else: ?>
    <table class="vtx-table">
      <thead>
        <tr>
          <th>Email</th>
          <th>Name</th>
          <th style="text-align:center;">Status</th>
          <th style="text-align:center;">Source</th>
          <th style="width:120px;">Subscribed</th>
          <th style="text-align:right;width:60px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subs as $sub): ?>
        <tr>
          <td><?php echo htmlspecialchars($sub['email']); ?></td>
          <td style="color:var(--ps-text-muted);"><?php echo htmlspecialchars($sub['name'] ?? ''); ?></td>
          <td style="text-align:center;">
            <?php if ($sub['status'] === 'active'): ?>
            <span class="badge badge-success">Active</span>
            <?php elseif ($sub['status'] === 'pending'): ?>
            <span class="badge badge-warning">Pending</span>
            <?php else: ?>
            <span class="badge badge-secondary">Unsubscribed</span>
            <?php endif; ?>
          </td>
          <td style="text-align:center;font-size:.8125rem;color:var(--ps-text-muted);">
            <?php echo htmlspecialchars($sub['source'] ?? ''); ?>
          </td>
          <td style="font-size:.8125rem;color:var(--ps-text-muted);white-space:nowrap;">
            <?php echo date('M j, Y', strtotime($sub['created_at'])); ?>
          </td>
          <td style="text-align:right;">
            <?php if (\App\CMS\Auth::can('newsletter.manage')): ?>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    data-vtx-confirm="Remove &quot;<?php echo htmlspecialchars($sub['email']); ?>&quot; from subscribers?"
                    data-vtx-action="<?php echo $baseUrl; ?>/admin/newsletter/subscribers/<?php echo $sub['id']; ?>/delete"
                    data-vtx-csrf="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
              <i class="pi pi-trash"></i>
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (($pages ?? 1) > 1): ?>
    <div class="vtx-pagination" style="padding:.75rem 1rem;">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="?page=<?php echo $p; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
         class="vtx-page-link <?php echo $p === ($page ?? 1) ? 'active' : ''; ?>"><?php echo $p; ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Add Subscriber inline modal -->
<?php if (\App\CMS\Auth::can('newsletter.manage')): ?>
<div id="nl-add-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1050;align-items:center;justify-content:center;">
  <div style="background:var(--ps-bg-base);border-radius:8px;padding:1.5rem;width:100%;max-width:420px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
    <h5 style="margin:0 0 1rem;">Add Subscriber</h5>
    <form data-crud-form action="<?php echo $baseUrl; ?>/admin/newsletter/subscribers/store" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
      <div class="vtx-field mb-3">
        <label class="vtx-label">Email <span class="text-danger">*</span></label>
        <input class="form-control" type="email" name="email" required autofocus placeholder="email@example.com">
      </div>
      <div class="vtx-field mb-3">
        <label class="vtx-label">Name</label>
        <input class="form-control" type="text" name="name" placeholder="Optional">
      </div>
      <div style="display:flex;gap:.5rem;justify-content:flex-end;">
        <button type="button" class="btn btn-outline-secondary btn-sm"
                onclick="document.getElementById('nl-add-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Import modal -->
<div id="nl-import-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1050;align-items:center;justify-content:center;">
  <div style="background:var(--ps-bg-base);border-radius:8px;padding:1.5rem;width:100%;max-width:520px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
    <h5 style="margin:0 0 .5rem;">Import Subscribers</h5>
    <p style="font-size:.875rem;color:var(--ps-text-muted);margin-bottom:1rem;">Upload a .csv file or paste rows below - one per line, <code>email,name</code> format. Duplicates are skipped.</p>
    <form data-crud-form action="<?php echo $baseUrl; ?>/admin/newsletter/subscribers/import" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
      <div class="vtx-field mb-3">
        <label class="vtx-label" style="font-size:.8rem;">CSV File</label>
        <input class="form-control" type="file" name="csv_file" accept=".csv,.txt,text/csv">
      </div>
      <div class="vtx-field mb-3">
        <label class="vtx-label" style="font-size:.8rem;">Or paste CSV rows</label>
        <textarea class="form-control" name="csv_data" rows="6"
                  placeholder="user@example.com,John Smith&#10;another@example.com,Jane Doe"
                  style="font-family:monospace;font-size:.8125rem;"></textarea>
      </div>
      <div style="display:flex;gap:.5rem;justify-content:flex-end;">
        <button type="button" class="btn btn-outline-secondary btn-sm"
                onclick="document.getElementById('nl-import-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Import</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
