<?php $editing = isset($campaign) && is_array($campaign) && !empty($campaign['id']); ?>
<?php $isSent = $editing && ($campaign['status'] ?? '') === 'sent'; ?>
<?php $isModal = $isModal ?? false; ?>

<?php if ($isModal): ?>
<form data-crud-form action="<?php echo htmlspecialchars($action ?? ''); ?>" method="POST">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
  <div class="vtx-field mb-3">
    <label class="vtx-label" for="nl-subject-m">Subject <span class="text-danger">*</span></label>
    <input class="form-control" type="text" id="nl-subject-m" name="subject"
           placeholder="Your email subject..." required autofocus>
  </div>
  <div class="vtx-field mb-3">
    <label class="vtx-label" for="nl-preview-m">Preview Text</label>
    <input class="form-control" type="text" id="nl-preview-m" name="preview_text"
           placeholder="Brief preview shown in email clients...">
  </div>
  <p style="font-size:.8125rem;color:var(--ps-text-muted);margin-bottom:.75rem;">
    After saving you'll be taken to the campaign editor to write the content.
  </p>
  <div style="display:flex;gap:.5rem;justify-content:flex-end;">
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm"><i class="pi pi-plus me-1"></i> Create Campaign</button>
  </div>
</form>
<?php return; ?>
<?php endif; ?>

<!-- Config data for newsletter-admin.js (tab switching + send/save/schedule AJAX) -->
<div id="nl-campaign-config"
     data-base-url="<?php echo htmlspecialchars($baseUrl); ?>"
     data-csrf="<?php echo htmlspecialchars($csrf_token ?? ''); ?>"
     data-campaign-id="<?php echo htmlspecialchars($campaign['id'] ?? ''); ?>"
     data-editable="<?php echo ($editing && !$isSent) ? '1' : '0'; ?>"
     hidden></div>

<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <a href="<?php echo $baseUrl; ?>/admin/newsletter/campaigns" class="vtx-breadcrumb">
      <i class="pi pi-mail me-1"></i> Campaigns
    </a>
    <h1 class="vtx-page-title" style="margin-top:.25rem;">
      <?php echo $editing ? htmlspecialchars($campaign['subject']) : 'New Campaign'; ?>
    </h1>
  </div>
  <div style="display:flex;gap:.5rem;align-items:center;">
    <a href="<?php echo $baseUrl; ?>/admin/newsletter/campaigns" class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-arrow-left me-1"></i> Back
    </a>
    <?php if ($editing && !$isSent && \App\CMS\Auth::can('newsletter.manage')): ?>
    <button type="button" id="nl-test-btn" class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-mail me-1"></i> Test Send
    </button>
    <button type="button" id="nl-send-btn" class="btn btn-primary btn-sm"
            data-campaign-id="<?php echo htmlspecialchars($campaign['id']); ?>"
            data-sub-count="<?php echo (int) ($activeCount ?? 0); ?>">
      <i class="pi pi-zap me-1"></i> Send Campaign
    </button>
    <?php endif; ?>
    <?php if (!$isSent): ?>
    <button type="submit" form="nl-campaign-form" class="btn btn-primary btn-sm">
      <i class="pi pi-save me-1"></i> Save Draft
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="vtx-alert vtx-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> mb-3">
  <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<?php if ($isSent): ?>
<div class="vtx-alert vtx-alert-success mb-3">
  This campaign was sent to <strong><?php echo (int) $campaign['sent_count']; ?></strong> subscriber(s)
  on <?php echo date('F j, Y \a\t g:i A', strtotime($campaign['sent_at'])); ?>.
</div>
<?php endif; ?>

<div id="nl-api-msg" class="mb-3" style="display:none;"></div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start;">

  <div>
    <form id="nl-campaign-form" method="POST" action="<?php echo htmlspecialchars($action ?? ''); ?>">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

      <div class="vtx-panel mb-3">
        <div class="vtx-panel-header">Email Details</div>
        <div class="vtx-panel-body">
          <div class="vtx-field mb-3">
            <label class="vtx-label" for="nl-subject">Subject <span class="text-danger">*</span></label>
            <input class="form-control" type="text" id="nl-subject" name="subject"
                   value="<?php echo htmlspecialchars($campaign['subject'] ?? ''); ?>"
                   placeholder="Your email subject..." required
                   <?php echo $isSent ? 'readonly' : ''; ?>>
          </div>
          <div class="vtx-field mb-3">
            <label class="vtx-label" for="nl-preview">Preview Text</label>
            <input class="form-control" type="text" id="nl-preview" name="preview_text"
                   value="<?php echo htmlspecialchars($campaign['preview_text'] ?? ''); ?>"
                   placeholder="Brief preview shown in email clients..."
                   <?php echo $isSent ? 'readonly' : ''; ?>>
          </div>
          <div class="vtx-field">
            <label class="vtx-label" for="nl-segment">Audience</label>
            <select class="form-select" id="nl-segment" name="segment_id" <?php echo $isSent ? 'disabled' : ''; ?>>
              <option value="">All active subscribers</option>
              <?php foreach (($segments ?? []) as $seg): ?>
              <option value="<?php echo htmlspecialchars($seg['id']); ?>"
                      <?php echo ($campaign['segment_id'] ?? '') === $seg['id'] ? 'selected' : ''; ?>>
                Segment: <?php echo htmlspecialchars($seg['name']); ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div style="font-size:.75rem;color:var(--ps-text-muted);margin-top:.35rem;">
              Manage segments under <a href="<?php echo $baseUrl; ?>/admin/newsletter/segments">Newsletter &rarr; Segments</a>.
            </div>
          </div>
        </div>
      </div>

      <div class="vtx-panel mb-3">
        <div class="vtx-panel-header" style="display:flex;gap:.5rem;">
          <button type="button" class="nl-tab-btn active" data-tab="html">HTML</button>
          <button type="button" class="nl-tab-btn" data-tab="text">Plain Text</button>
        </div>
        <div class="vtx-panel-body p-0">
          <div id="nl-tab-html">
            <textarea class="form-control" name="body_html" rows="18"
                      style="border:0;border-radius:0 0 6px 6px;font-family:monospace;font-size:.8125rem;resize:vertical;"
                      placeholder="&lt;h1&gt;Hello!&lt;/h1&gt;&#10;&lt;p&gt;Your message here...&lt;/p&gt;"
                      <?php echo $isSent ? 'readonly' : ''; ?>><?php echo htmlspecialchars($campaign['body_html'] ?? ''); ?></textarea>
          </div>
          <div id="nl-tab-text" style="display:none;">
            <textarea class="form-control" name="body_text" rows="18"
                      style="border:0;border-radius:0 0 6px 6px;font-family:monospace;font-size:.8125rem;resize:vertical;"
                      placeholder="Plain text version of your email..."
                      <?php echo $isSent ? 'readonly' : ''; ?>><?php echo htmlspecialchars($campaign['body_text'] ?? ''); ?></textarea>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- Sidebar -->
  <div>
    <?php if ($editing): ?>
    <div class="vtx-panel mb-3">
      <div class="vtx-panel-header">Details</div>
      <div class="vtx-panel-body" style="font-size:.8125rem;display:grid;gap:.6rem;">
        <div>
          <span style="color:var(--ps-text-muted);">Status</span>
          <div style="margin-top:.2rem;">
            <?php if ($campaign['status'] === 'sent'): ?>
            <span class="badge badge-success">Sent</span>
            <?php elseif ($campaign['status'] === 'sending'): ?>
            <span class="badge badge-warning">Sending...</span>
            <?php elseif ($campaign['status'] === 'scheduled'): ?>
            <span class="badge badge-primary">Scheduled</span>
            <?php else: ?>
            <span class="badge badge-secondary">Draft</span>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($campaign['sent_at']): ?>
        <div>
          <span style="color:var(--ps-text-muted);">Sent</span>
          <div style="margin-top:.2rem;font-weight:600;"><?php echo date('M j, Y g:i A', strtotime($campaign['sent_at'])); ?></div>
        </div>
        <div>
          <span style="color:var(--ps-text-muted);">Delivered to</span>
          <div style="margin-top:.2rem;font-weight:600;"><?php echo (int) $campaign['sent_count']; ?> subscriber(s)</div>
        </div>
        <div>
          <span style="color:var(--ps-text-muted);">Unique opens</span>
          <div style="margin-top:.2rem;font-weight:600;"><?php echo (int) ($campaign['open_count'] ?? 0); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($campaign['status'] === 'scheduled' && !empty($campaign['scheduled_at'])): ?>
        <div>
          <span style="color:var(--ps-text-muted);">Scheduled for</span>
          <div style="margin-top:.2rem;font-weight:600;"><?php echo date('M j, Y g:i A', strtotime($campaign['scheduled_at'])); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!$isSent): ?>
        <div>
          <span style="color:var(--ps-text-muted);">Active subscribers</span>
          <div style="margin-top:.2rem;font-weight:600;"><?php echo (int) ($activeCount ?? 0); ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($editing && !$isSent && \App\CMS\Auth::can('newsletter.manage')): ?>
    <div class="vtx-panel mb-3">
      <div class="vtx-panel-header">Schedule</div>
      <div class="vtx-panel-body" style="font-size:.8125rem;">
        <?php if ($campaign['status'] === 'scheduled'): ?>
        <p class="mb-2">Sends automatically at
          <strong><?php echo date('M j, Y g:i A', strtotime($campaign['scheduled_at'])); ?></strong>
          (checked whenever the admin is opened).</p>
        <button type="button" class="btn btn-outline-danger btn-sm w-100" id="nl-unschedule-btn">
          <i class="pi pi-x-circle me-1"></i> Cancel Schedule
        </button>
        <?php else: ?>
        <div class="vtx-field mb-2">
          <label class="vtx-label" for="nl-schedule-at" style="font-size:.75rem;">Send at</label>
          <input class="form-control form-control-sm" type="datetime-local" id="nl-schedule-at">
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm w-100" id="nl-schedule-btn">
          <i class="pi pi-clock me-1"></i> Schedule Send
        </button>
        <p style="font-size:.7rem;color:var(--ps-text-muted);margin:.5rem 0 0;">
          Cron-free: due campaigns send when an admin page is opened at or after this time.
        </p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="vtx-panel">
      <div class="vtx-panel-header">Tips</div>
      <div class="vtx-panel-body" style="font-size:.8125rem;color:var(--ps-text-muted);line-height:1.6;">
        <p class="mb-2">Write the HTML body in the editor. An unsubscribe link is added automatically to every email.</p>
        <p class="mb-0">Use the Plain Text tab for a fallback version. Most clients show HTML when available.</p>
      </div>
    </div>
  </div>

</div>

<!-- Test send modal -->
<?php if ($editing && !$isSent): ?>
<div id="nl-test-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1050;align-items:center;justify-content:center;">
  <div style="background:var(--ps-bg-base);border-radius:8px;padding:1.5rem;width:100%;max-width:380px;">
    <h5 style="margin:0 0 1rem;">Test Send</h5>
    <div class="vtx-field mb-3">
      <label class="vtx-label">Send to</label>
      <input class="form-control" type="email" id="nl-test-email" placeholder="your@email.com">
    </div>
    <div id="nl-test-msg" style="display:none;" class="mb-3"></div>
    <div style="display:flex;gap:.5rem;justify-content:flex-end;">
      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('nl-test-modal').style.display='none'">Cancel</button>
      <button type="button" class="btn btn-primary btn-sm" id="nl-test-confirm">Send Test</button>
    </div>
  </div>
</div>
<?php endif; ?>
