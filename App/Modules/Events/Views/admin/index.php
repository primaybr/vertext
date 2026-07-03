<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-calendar me-2 text-primary"></i>Events</h1>
    <p class="vtx-page-desc">Manage events, dates, and RSVP counts.</p>
  </div>
  <?php if (\App\CMS\Auth::can('events.manage')): ?>
  <button type="button" class="btn btn-primary btn-sm"
          data-form-url="<?php echo $baseUrl; ?>/admin/events/create"
          data-form-title="New Event" data-form-size="modal-lg">
    <i class="pi pi-plus me-1"></i> New Event
  </button>
  <?php endif; ?>
</div>

<!-- Status tabs -->
<div class="vtx-panel mb-3">
  <div class="vtx-filter-tabs">
    <a href="<?php echo $baseUrl; ?>/admin/events"
       class="vtx-filter-tab <?php echo empty($status) ? 'active' : ''; ?>">
      All <span class="count"><?php echo ($counts['published'] ?? 0) + ($counts['draft'] ?? 0); ?></span>
    </a>
    <a href="?status=published"
       class="vtx-filter-tab <?php echo $status === 'published' ? 'active' : ''; ?>">
      Published <span class="count"><?php echo (int) ($counts['published'] ?? 0); ?></span>
    </a>
    <a href="?status=draft"
       class="vtx-filter-tab <?php echo $status === 'draft' ? 'active' : ''; ?>">
      Draft <span class="count"><?php echo (int) ($counts['draft'] ?? 0); ?></span>
    </a>
  </div>
</div>

<!-- Search -->
<div class="vtx-panel mb-3">
  <div class="vtx-panel-body" style="padding:.75rem 1rem;">
    <form method="GET" action="<?php echo $baseUrl; ?>/admin/events"
          style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <?php if ($status): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>"><?php endif; ?>
      <input class="form-control form-control-sm" type="search" name="search"
             value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Search events..."
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

<div class="vtx-panel">
  <div class="vtx-panel-body p-0">
    <?php if (empty($events)): ?>
    <div style="padding:3rem;text-align:center;color:var(--ps-text-muted);">
      <i class="pi pi-calendar pi-3x mb-3" style="opacity:.3;display:block;margin:0 auto 1rem;"></i>
      <p class="mb-1" style="font-weight:600;">No events yet</p>
      <?php if (\App\CMS\Auth::can('events.manage')): ?>
      <button type="button" class="btn btn-sm btn-primary mt-1"
              data-form-url="<?php echo $baseUrl; ?>/admin/events/create"
              data-form-title="New Event" data-form-size="modal-lg">Create your first event</button>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="vtx-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Date</th>
          <th>Location</th>
          <th style="text-align:center;width:70px;">RSVPs</th>
          <th style="text-align:center;width:90px;">Status</th>
          <th style="text-align:right;width:80px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($events as $ev): ?>
        <tr>
          <td><strong><?php echo htmlspecialchars($ev['title']); ?></strong></td>
          <td style="white-space:nowrap;font-size:.8125rem;color:var(--ps-text-muted);">
            <?php echo date('M j, Y g:i A', strtotime($ev['start_at'])); ?>
            <?php if ($ev['end_at']): ?>
            <br><span style="font-size:.75rem;">&nbsp;- <?php echo date('M j, Y g:i A', strtotime($ev['end_at'])); ?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:.8125rem;color:var(--ps-text-muted);"><?php echo htmlspecialchars($ev['location'] ?? ''); ?></td>
          <td style="text-align:center;font-weight:600;"><?php echo (int) $ev['rsvp_count']; ?></td>
          <td style="text-align:center;">
            <?php if ($ev['status'] === 'published'): ?>
            <span class="badge badge-success">Published</span>
            <?php else: ?>
            <span class="badge badge-secondary">Draft</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right;white-space:nowrap;">
            <a href="<?php echo $baseUrl; ?>/admin/events/<?php echo $ev['id']; ?>/attendees"
               class="btn btn-sm btn-outline-secondary me-1" title="Attendees">
              <i class="pi pi-users"></i>
            </a>
            <?php if (\App\CMS\Auth::can('events.manage')): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary me-1" title="Edit"
                    data-form-url="<?php echo $baseUrl; ?>/admin/events/<?php echo $ev['id']; ?>/edit"
                    data-form-title="Edit Event" data-form-size="modal-lg">
              <i class="pi pi-edit"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    data-vtx-confirm="Delete &quot;<?php echo htmlspecialchars($ev['title']); ?>&quot;?"
                    data-vtx-action="<?php echo $baseUrl; ?>/admin/events/<?php echo $ev['id']; ?>/delete"
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
