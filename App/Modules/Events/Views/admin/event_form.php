<?php
$editing = isset($event) && is_array($event) && !empty($event['id']);
$isModal = $isModal ?? false;
?>

<?php if (!$isModal): ?>
<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <a href="<?php echo $baseUrl; ?>/admin/events" class="vtx-breadcrumb">
      <i class="pi pi-calendar me-1"></i> Events
    </a>
    <h1 class="vtx-page-title" style="margin-top:.25rem;">
      <?php echo $editing ? htmlspecialchars($event['title']) : 'New Event'; ?>
    </h1>
  </div>
  <div style="display:flex;gap:.5rem;">
    <a href="<?php echo $baseUrl; ?>/admin/events" class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-arrow-left me-1"></i> Back
    </a>
    <button type="submit" form="ev-form" class="btn btn-primary btn-sm">
      <i class="pi pi-save me-1"></i> <?php echo $editing ? 'Save Changes' : 'Create Event'; ?>
    </button>
  </div>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="vtx-alert vtx-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> mb-3">
  <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($isModal): ?>
<!-- Modal layout: single column, everything inside form -->
<form id="ev-form" data-crud-form method="POST" action="<?php echo htmlspecialchars($action ?? ''); ?>">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

  <div class="vtx-panel mb-3">
    <div class="vtx-panel-header">Event Details</div>
    <div class="vtx-panel-body">
      <div class="vtx-field mb-3">
        <label class="vtx-label" for="ev-title">Title <span class="text-danger">*</span></label>
        <input class="form-control" type="text" id="ev-title" name="title"
               value="<?php echo htmlspecialchars($event['title'] ?? ''); ?>"
               placeholder="Event title..." required autofocus
               data-vtx-slug-source>
      </div>
      <div class="vtx-field mb-3">
        <label class="vtx-label" for="ev-slug">Slug</label>
        <input class="form-control" type="text" id="ev-slug" name="slug"
               value="<?php echo htmlspecialchars($event['slug'] ?? ''); ?>"
               placeholder="auto-generated"
               data-vtx-slug-target data-vtx-slug-source-id="ev-title">
        <p class="vtx-field-hint">Public URL: /events/{slug}</p>
      </div>
      <div class="vtx-field mb-3">
        <label class="vtx-label" for="ev-desc">Short Description</label>
        <textarea class="form-control" id="ev-desc" name="description" rows="2"
                  placeholder="Brief summary shown in listings..."><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
      </div>
      <div class="vtx-field">
        <label class="vtx-label" for="ev-body">Full Details</label>
        <textarea class="form-control" id="ev-body" name="body" rows="5"
                  placeholder="Full event details, schedule, speakers, etc."
                  style="font-family:monospace;font-size:.875rem;"><?php echo htmlspecialchars($event['body'] ?? ''); ?></textarea>
      </div>
    </div>
  </div>

  <div class="vtx-panel mb-3">
    <div class="vtx-panel-header">Date &amp; Location</div>
    <div class="vtx-panel-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="vtx-field">
          <label class="vtx-label" for="ev-start">Start <span class="text-danger">*</span></label>
          <input class="form-control" type="datetime-local" id="ev-start" name="start_at"
                 value="<?php echo htmlspecialchars($event && !empty($event['start_at']) ? date('Y-m-d\TH:i', strtotime($event['start_at'])) : ''); ?>"
                 required>
        </div>
        <div class="vtx-field">
          <label class="vtx-label" for="ev-end">End</label>
          <input class="form-control" type="datetime-local" id="ev-end" name="end_at"
                 value="<?php echo htmlspecialchars($event && !empty($event['end_at']) ? date('Y-m-d\TH:i', strtotime($event['end_at'])) : ''); ?>">
        </div>
      </div>
      <div class="vtx-field mt-3">
        <label class="vtx-label" for="ev-location">Location</label>
        <input class="form-control" type="text" id="ev-location" name="location"
               value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>"
               placeholder="Address, venue name, or Online">
      </div>
    </div>
  </div>

  <div class="vtx-panel mb-3">
    <div class="vtx-panel-header">Publish</div>
    <div class="vtx-panel-body">
      <div class="vtx-field">
        <label class="vtx-label">Status</label>
        <select class="form-select" name="status">
          <option value="draft"     <?php echo ($event['status'] ?? 'draft') === 'draft'     ? 'selected' : ''; ?>>Draft</option>
          <option value="published" <?php echo ($event['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
        </select>
      </div>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end;">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="pi pi-save me-1"></i> <?php echo $editing ? 'Save Changes' : 'Create Event'; ?>
    </button>
  </div>
</form>

<?php else: ?>
<!-- Full-page layout: two columns -->
<div style="display:grid;grid-template-columns:1fr 280px;gap:1.25rem;align-items:start;">

  <div>
    <form id="ev-form" data-crud-form method="POST" action="<?php echo htmlspecialchars($action ?? ''); ?>">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

      <div class="vtx-panel mb-3">
        <div class="vtx-panel-header">Event Details</div>
        <div class="vtx-panel-body">
          <div class="vtx-field mb-3">
            <label class="vtx-label" for="ev-title">Title <span class="text-danger">*</span></label>
            <input class="form-control" type="text" id="ev-title" name="title"
                   value="<?php echo htmlspecialchars($event['title'] ?? ''); ?>"
                   placeholder="Event title..." required autofocus
                   data-vtx-slug-source>
          </div>
          <div class="vtx-field mb-3">
            <label class="vtx-label" for="ev-slug">Slug</label>
            <input class="form-control" type="text" id="ev-slug" name="slug"
                   value="<?php echo htmlspecialchars($event['slug'] ?? ''); ?>"
                   placeholder="auto-generated"
                   data-vtx-slug-target data-vtx-slug-source-id="ev-title">
            <p class="vtx-field-hint">Public URL: /events/{slug}</p>
          </div>
          <div class="vtx-field mb-3">
            <label class="vtx-label" for="ev-desc">Short Description</label>
            <textarea class="form-control" id="ev-desc" name="description" rows="2"
                      placeholder="Brief summary shown in listings..."><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
          </div>
          <div class="vtx-field">
            <label class="vtx-label" for="ev-body">Full Details</label>
            <textarea class="form-control" id="ev-body" name="body" rows="8"
                      placeholder="Full event details, schedule, speakers, etc."
                      style="font-family:monospace;font-size:.875rem;"><?php echo htmlspecialchars($event['body'] ?? ''); ?></textarea>
          </div>
        </div>
      </div>

      <div class="vtx-panel mb-3">
        <div class="vtx-panel-header">Date &amp; Location</div>
        <div class="vtx-panel-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="vtx-field">
              <label class="vtx-label" for="ev-start">Start <span class="text-danger">*</span></label>
              <input class="form-control" type="datetime-local" id="ev-start" name="start_at"
                     value="<?php echo htmlspecialchars($event && !empty($event['start_at']) ? date('Y-m-d\TH:i', strtotime($event['start_at'])) : ''); ?>"
                     required>
            </div>
            <div class="vtx-field">
              <label class="vtx-label" for="ev-end">End</label>
              <input class="form-control" type="datetime-local" id="ev-end" name="end_at"
                     value="<?php echo htmlspecialchars($event && !empty($event['end_at']) ? date('Y-m-d\TH:i', strtotime($event['end_at'])) : ''); ?>">
            </div>
          </div>
          <div class="vtx-field mt-3">
            <label class="vtx-label" for="ev-location">Location</label>
            <input class="form-control" type="text" id="ev-location" name="location"
                   value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>"
                   placeholder="Address, venue name, or Online">
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- Sidebar -->
  <div>
    <div class="vtx-panel mb-3">
      <div class="vtx-panel-header">Publish</div>
      <div class="vtx-panel-body">
        <div class="vtx-field mb-3">
          <label class="vtx-label">Status</label>
          <select class="form-select" name="status" form="ev-form">
            <option value="draft"     <?php echo ($event['status'] ?? 'draft') === 'draft'     ? 'selected' : ''; ?>>Draft</option>
            <option value="published" <?php echo ($event['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
          </select>
        </div>
        <?php if ($editing && $event['status'] === 'published'): ?>
        <a href="<?php echo $baseUrl; ?>/events/<?php echo htmlspecialchars($event['slug']); ?>"
           target="_blank" class="btn btn-outline-secondary btn-sm w-100">
          <i class="pi pi-external-link me-1"></i> View Event
        </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($editing): ?>
    <div class="vtx-panel mb-3">
      <div class="vtx-panel-header">Stats</div>
      <div class="vtx-panel-body" style="font-size:.875rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="color:var(--ps-text-muted);">RSVPs</span>
          <strong style="font-size:1.25rem;"><?php echo (int) ($event['rsvp_count'] ?? 0); ?></strong>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>
<?php endif; ?>

<script>
Vtx.load(['slug'], function () { if (window.vtxSlug) window.vtxSlug.init(); });
</script>
<style>
.vtx-breadcrumb { font-size:.8125rem;color:var(--ps-text-muted);text-decoration:none; }
.vtx-breadcrumb:hover { color:var(--ps-accent); }
</style>
