<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-settings me-2 text-primary"></i>Newsletter Settings</h1>
  </div>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="vtx-alert vtx-alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> mb-3">
  <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<div style="max-width:640px;">
  <form method="POST" action="<?php echo $baseUrl; ?>/admin/newsletter/settings/save">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

    <div class="vtx-panel mb-3">
      <div class="vtx-panel-header">Sender Identity</div>
      <div class="vtx-panel-body">
        <div class="vtx-field mb-3">
          <label class="vtx-label" for="nl-from-name">From Name</label>
          <input class="form-control" type="text" id="nl-from-name" name="newsletter_from_name"
                 value="<?php echo htmlspecialchars($settings['newsletter_from_name'] ?? ''); ?>"
                 placeholder="Your site name">
        </div>
        <div class="vtx-field">
          <label class="vtx-label" for="nl-from-email">From Email</label>
          <input class="form-control" type="email" id="nl-from-email" name="newsletter_from_email"
                 value="<?php echo htmlspecialchars($settings['newsletter_from_email'] ?? ''); ?>"
                 placeholder="newsletter@yourdomain.com">
          <p class="vtx-field-hint">Leave empty to use the global mail sender from Settings.</p>
        </div>
      </div>
    </div>

    <div class="vtx-panel mb-3">
      <div class="vtx-panel-header">Double Opt-in</div>
      <div class="vtx-panel-body">
        <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer;">
          <input type="checkbox" name="newsletter_double_optin" value="1"
                 style="margin-top:.2rem;"
                 <?php echo ($settings['newsletter_double_optin'] ?? '0') === '1' ? 'checked' : ''; ?>>
          <span>
            <strong>Require email confirmation</strong><br>
            <span style="font-size:.875rem;color:var(--ps-text-muted);">
              New subscribers receive a confirmation email and are set to "pending" until they click the link.
            </span>
          </span>
        </label>

        <div class="vtx-field mt-3">
          <label class="vtx-label" for="nl-confirm-subject">Confirmation Email Subject</label>
          <input class="form-control" type="text" id="nl-confirm-subject" name="newsletter_confirm_subject"
                 value="<?php echo htmlspecialchars($settings['newsletter_confirm_subject'] ?? 'Please confirm your subscription'); ?>">
        </div>
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;">
      <button type="submit" class="btn btn-primary">Save Settings</button>
    </div>
  </form>
</div>
