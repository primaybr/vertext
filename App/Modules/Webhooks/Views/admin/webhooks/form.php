<?php
$isEdit        = $endpoint !== null;
$ep            = $endpoint ?? [];
$savedEvents   = json_decode($ep['events'] ?? '[]', true) ?: [];
$currentSecret = $ep['secret'] ?? $generatedSecret ?? '';
$isEnabled     = isset($ep['enabled']) ? (bool)$ep['enabled'] : true;
$isModal       = $isModal ?? false;
?>

<?php if (!$isModal): ?>
<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title">
      <i class="pi pi-link me-2 text-primary"></i>
      <?php echo $isEdit ? 'Edit Webhook' : 'New Webhook Endpoint'; ?>
    </h1>
    <p class="vtx-page-desc">
      <?php echo $isEdit ? 'Update the endpoint URL, secret, or subscribed events.' : 'Configure an endpoint to receive signed event payloads.'; ?>
    </p>
  </div>
  <div>
    <a href="{{baseUrl}}/admin/webhooks" class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-arrow-left me-1"></i> Back
    </a>
  </div>
</div>
<?php endif; ?>

<form data-crud-form method="POST" action="<?php echo $action; ?>">
  <input type="hidden" name="csrf_token" value="{{csrf_token}}">

  <?php if ($isModal): ?>
  <!-- Modal: single-column layout -->
  <div class="vtx-panel mb-3">
    <div class="vtx-panel-head"><h2 class="vtx-panel-title">Endpoint</h2></div>
    <div class="vtx-panel-body">
      <div class="vtx-field">
        <label class="vtx-label" for="wh-name">Name</label>
        <input type="text" id="wh-name" name="name" class="form-control"
               value="<?php echo htmlspecialchars($ep['name'] ?? ''); ?>"
               placeholder="e.g. Slack Notifications" required autofocus>
      </div>
      <div class="vtx-field mt-3">
        <label class="vtx-label" for="wh-url">Payload URL</label>
        <input type="url" id="wh-url" name="url" class="form-control"
               value="<?php echo htmlspecialchars($ep['url'] ?? ''); ?>"
               placeholder="https://example.com/webhooks/vertext" required>
        <div class="vtx-hint">Must be a publicly accessible https URL.</div>
      </div>
      <div class="vtx-field mt-3">
        <label class="vtx-label" for="wh-secret">Secret</label>
        <div class="wh-inline-group">
          <input type="text" id="wh-secret" name="secret" class="form-control wh-mono-input"
                 value="<?php echo htmlspecialchars($currentSecret); ?>"
                 placeholder="Random secret for HMAC verification" required>
          <?php if (!$isEdit): ?>
          <button type="button" class="btn btn-outline-secondary btn-sm wh-shrink-0"
                  onclick="document.getElementById('wh-secret').value = Array.from(crypto.getRandomValues(new Uint8Array(20))).map(b=>b.toString(16).padStart(2,'0')).join('')">
            Regenerate
          </button>
          <?php endif; ?>
        </div>
        <div class="vtx-hint">Used to compute <code>X-Vertext-Signature</code>.</div>
      </div>
      <div class="vtx-field mt-3">
        <label class="wh-checkbox-label">
          <input type="hidden" name="enabled" value="0">
          <input type="checkbox" name="enabled" value="1"
                 <?php echo $isEnabled ? 'checked' : ''; ?>>
          <span>Active - deliver events to this endpoint</span>
        </label>
      </div>
    </div>
  </div>

  <div class="vtx-panel mb-3">
    <div class="vtx-panel-head"><h2 class="vtx-panel-title">Events</h2></div>
    <div class="vtx-panel-body">
      <p class="wh-events-desc">
        Select the events this endpoint should receive.
      </p>
      <div class="wh-events-grid">
        <?php foreach ($availableEvents as $slug => $label):
            if ($slug === 'ping') continue;
            $checked = in_array($slug, $savedEvents, true) ? 'checked' : '';
        ?>
        <label class="vtx-event-label">
          <input type="checkbox" name="events[]" value="<?php echo htmlspecialchars($slug); ?>"
                 <?php echo $checked; ?>>
          <span class="vtx-event-label-text">
            <span><?php echo htmlspecialchars($label); ?></span>
            <code><?php echo htmlspecialchars($slug); ?></code>
          </span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="wh-actions-end">
    <button type="submit" class="btn btn-primary">
      <?php echo $isEdit ? 'Save Changes' : 'Create Endpoint'; ?>
    </button>
  </div>

  <?php else: ?>
  <!-- Full-page: two-column layout -->
  <div class="wh-two-col">

    <div>
      <div class="vtx-panel mb-3">
        <div class="vtx-panel-head"><h2 class="vtx-panel-title">Endpoint</h2></div>
        <div class="vtx-panel-body">
          <div class="vtx-field">
            <label class="vtx-label" for="wh-name">Name</label>
            <input type="text" id="wh-name" name="name" class="form-control"
                   value="<?php echo htmlspecialchars($ep['name'] ?? ''); ?>"
                   placeholder="e.g. Slack Notifications" required>
          </div>
          <div class="vtx-field mt-3">
            <label class="vtx-label" for="wh-url">Payload URL</label>
            <input type="url" id="wh-url" name="url" class="form-control"
                   value="<?php echo htmlspecialchars($ep['url'] ?? ''); ?>"
                   placeholder="https://example.com/webhooks/vertext" required>
            <div class="vtx-hint">Must be a publicly accessible https URL.</div>
          </div>
          <div class="vtx-field mt-3">
            <label class="vtx-label" for="wh-secret">Secret</label>
            <div class="wh-inline-group">
              <input type="text" id="wh-secret" name="secret" class="form-control wh-mono-input"
                     value="<?php echo htmlspecialchars($currentSecret); ?>"
                     placeholder="Random secret for HMAC verification" required>
              <?php if (!$isEdit): ?>
              <button type="button" class="btn btn-outline-secondary btn-sm wh-shrink-0"
                      onclick="document.getElementById('wh-secret').value = Array.from(crypto.getRandomValues(new Uint8Array(20))).map(b=>b.toString(16).padStart(2,'0')).join('')">
                Regenerate
              </button>
              <?php endif; ?>
            </div>
            <div class="vtx-hint">Used to compute <code>X-Vertext-Signature</code>. Store it securely on your receiver.</div>
          </div>
        </div>
      </div>

      <div class="vtx-panel">
        <div class="vtx-panel-head"><h2 class="vtx-panel-title">Events</h2></div>
        <div class="vtx-panel-body">
          <p class="wh-events-desc">
            Select the events this endpoint should receive.
          </p>
          <div class="wh-events-grid">
            <?php foreach ($availableEvents as $slug => $label):
                if ($slug === 'ping') continue;
                $checked = in_array($slug, $savedEvents, true) ? 'checked' : '';
            ?>
            <label class="vtx-event-label">
              <input type="checkbox" name="events[]" value="<?php echo htmlspecialchars($slug); ?>"
                     <?php echo $checked; ?>>
              <span class="vtx-event-label-text">
                <span><?php echo htmlspecialchars($label); ?></span>
                <code><?php echo htmlspecialchars($slug); ?></code>
              </span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div>
      <div class="vtx-panel mb-3">
        <div class="vtx-panel-head"><h2 class="vtx-panel-title">Options</h2></div>
        <div class="vtx-panel-body">
          <label class="wh-checkbox-label">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1"
                   <?php echo $isEnabled ? 'checked' : ''; ?>>
            <span>Active - deliver events to this endpoint</span>
          </label>
        </div>
      </div>

      <div class="vtx-panel">
        <div class="vtx-panel-body wh-actions-stack">
          <button type="submit" class="btn btn-primary">
            <?php echo $isEdit ? 'Save Changes' : 'Create Endpoint'; ?>
          </button>
          <a href="{{baseUrl}}/admin/webhooks" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </div>

  </div>
  <?php endif; ?>

</form>
