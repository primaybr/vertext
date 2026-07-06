<?php
/**
 * Capacity, recurrence, and ticket fields - included by event_form.php
 * inside the <form>. Expects $event (nullable array).
 */
$rule    = json_decode((string) ($event['recurrence_rule'] ?? ''), true) ?: [];
$tickets = json_decode((string) ($event['tickets'] ?? '[]'), true) ?: [];
?>
<div class="vtx-panel mb-3">
  <div class="vtx-panel-header">Capacity &amp; Recurrence</div>
  <div class="vtx-panel-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="vtx-field">
        <label class="vtx-label" for="ev-max">Max Attendees</label>
        <input class="form-control" type="number" id="ev-max" name="max_attendees" min="0"
               value="<?php echo (int) ($event['max_attendees'] ?? 0) ?: ''; ?>"
               placeholder="Unlimited">
        <p class="vtx-field-hint">When full, new RSVPs join a waiting list.</p>
      </div>
      <div class="vtx-field">
        <label class="vtx-label" for="ev-rec-freq">Repeats</label>
        <select class="form-select" id="ev-rec-freq" name="recurrence_freq">
          <option value="">Does not repeat</option>
          <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label): ?>
          <option value="<?php echo $value; ?>" <?php echo ($rule['freq'] ?? '') === $value ? 'selected' : ''; ?>>
            <?php echo $label; ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="mt-3">
      <div class="vtx-field">
        <label class="vtx-label" for="ev-rec-interval">Every</label>
        <input class="form-control" type="number" id="ev-rec-interval" name="recurrence_interval" min="1"
               value="<?php echo (int) ($rule['interval'] ?? 1); ?>">
        <p class="vtx-field-hint">e.g. 2 + Weekly = every two weeks.</p>
      </div>
      <div class="vtx-field">
        <label class="vtx-label" for="ev-rec-until">Repeat Until</label>
        <input class="form-control" type="date" id="ev-rec-until" name="recurrence_until"
               value="<?php echo htmlspecialchars($rule['until'] ?? ''); ?>">
        <p class="vtx-field-hint">Empty = repeats indefinitely.</p>
      </div>
    </div>
  </div>
</div>

<div class="vtx-panel mb-3">
  <div class="vtx-panel-header">Ticket Types</div>
  <div class="vtx-panel-body">
    <p style="font-size:.8125rem;color:var(--ps-text-muted);margin-bottom:.75rem;">
      Optional. Price 0 = free. Display only - Vertext does not process payments.
    </p>
    <div id="ev-ticket-rows">
      <?php foreach ($tickets as $t): ?>
      <div class="ev-ticket-row" style="display:flex;gap:.5rem;margin-bottom:.5rem;">
        <input class="form-control form-control-sm" type="text" name="ticket_name[]" maxlength="100"
               placeholder="Ticket name (e.g. General)" value="<?php echo htmlspecialchars($t['name'] ?? ''); ?>" style="flex:2;">
        <input class="form-control form-control-sm" type="number" name="ticket_price[]" min="0" step="0.01"
               placeholder="0.00" value="<?php echo htmlspecialchars((string) ($t['price'] ?? '')); ?>" style="flex:1;">
        <button type="button" class="vtx-icon-btn danger" onclick="this.parentElement.remove()" title="Remove">
          <i class="pi pi-trash"></i>
        </button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="ev-add-ticket">
      <i class="pi pi-plus me-1"></i> Add Ticket Type
    </button>
  </div>
</div>
