<form method="POST" action="<?php echo $action; ?>" data-crud-form>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="seg-name">Segment Name <span class="req">*</span></label>
    <input class="form-control" type="text" id="seg-name" name="name" maxlength="150" required
           value="<?php echo htmlspecialchars($segment['name'] ?? ''); ?>"
           placeholder="e.g. Blog widget signups">
  </div>

  <p style="font-size:.8125rem;color:var(--ps-text-muted);margin-bottom:.75rem;">
    All rules are optional and combined with AND. A segment with no rules matches every active subscriber.
  </p>

  <div class="vtx-field mb-3">
    <label class="vtx-label" for="seg-source">Source</label>
    <select class="form-select" id="seg-source" name="rule_source">
      <option value="">Any source</option>
      <?php $currentSource = $segment['rules_decoded']['source'] ?? ''; ?>
      <?php foreach (($sources ?? []) as $src): ?>
      <option value="<?php echo htmlspecialchars($src); ?>" <?php echo $src === $currentSource ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($src); ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="row g-3">
    <div class="col-6">
      <div class="vtx-field mb-3">
        <label class="vtx-label" for="seg-after">Subscribed After</label>
        <input class="form-control" type="date" id="seg-after" name="rule_subscribed_after"
               value="<?php echo htmlspecialchars($segment['rules_decoded']['subscribed_after'] ?? ''); ?>">
      </div>
    </div>
    <div class="col-6">
      <div class="vtx-field mb-3">
        <label class="vtx-label" for="seg-before">Subscribed Before</label>
        <input class="form-control" type="date" id="seg-before" name="rule_subscribed_before"
               value="<?php echo htmlspecialchars($segment['rules_decoded']['subscribed_before'] ?? ''); ?>">
      </div>
    </div>
  </div>

  <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:.5rem;">
    <button type="button" class="btn btn-outline-secondary" onclick="vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary">
      <?php echo $segment ? 'Save Changes' : 'Create Segment'; ?>
    </button>
  </div>
</form>
