<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <a href="{{baseUrl}}/admin/events" class="vtx-breadcrumb">
      <i class="pi pi-calendar me-1"></i> Events
    </a>
    <h1 class="vtx-page-title" style="margin-top:.25rem;">
      <i class="pi pi-users me-2 text-primary"></i>Attendees - <?php echo htmlspecialchars($event['title']); ?>
    </h1>
    <p class="vtx-page-desc">
      <?php echo (int) ($counts['confirmed'] ?? 0); ?> confirmed
      &middot; <?php echo (int) ($counts['waitlist'] ?? 0); ?> waitlisted
      &middot; <?php echo (int) ($counts['cancelled'] ?? 0); ?> cancelled
      <?php if ($spotsLeft !== null): ?>
        &middot; <?php echo (int) $spotsLeft; ?> spot(s) left of <?php echo (int) $event['max_attendees']; ?>
      <?php endif; ?>
    </p>
  </div>
  <div style="display:flex;gap:.5rem;">
    <a href="{{baseUrl}}/admin/events/<?php echo $event['id']; ?>/attendees/export" class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-download me-1"></i> Export CSV
    </a>
  </div>
</div>

<div class="vtx-panel" data-event-id="<?php echo htmlspecialchars((string) $event['id']); ?>" data-csrf="{{csrf_token}}">
  <?php if (empty($attendees)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-users"></i></div>
    <div class="vtx-empty-title">No registrations yet</div>
    <div class="vtx-empty-desc">Attendees appear here when visitors RSVP on the public event page.</div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table" data-vtx-table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Ticket</th>
          <th>Status</th>
          <th>Registered</th>
          <th style="width:170px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($attendees as $a): ?>
        <tr data-attendee-row="<?php echo $a['id']; ?>">
          <td><span class="cell-primary"><?php echo htmlspecialchars($a['name']); ?></span></td>
          <td class="cell-muted"><?php echo htmlspecialchars($a['email']); ?></td>
          <td class="cell-muted"><?php echo htmlspecialchars($a['ticket'] ?? '-'); ?></td>
          <td data-attendee-status>
            <?php
            $cls = match($a['status']) {
                'confirmed' => 'success',
                'waitlist'  => 'warning',
                'cancelled' => 'error',
                default     => ''
            };
            ?>
            <span class="vtx-tag <?php echo $cls; ?>"><?php echo ucfirst($a['status']); ?></span>
          </td>
          <td class="cell-muted"><?php echo date('M j, Y H:i', strtotime($a['registered_at'])); ?></td>
          <td>
            <select class="form-select form-select-sm" data-attendee-select
                    data-attendee-id="<?php echo $a['id']; ?>"
                    data-current="<?php echo htmlspecialchars($a['status']); ?>">
              <?php foreach (['confirmed', 'waitlist', 'cancelled'] as $st): ?>
              <option value="<?php echo $st; ?>" <?php echo $a['status'] === $st ? 'selected' : ''; ?>>
                <?php echo ucfirst($st); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
