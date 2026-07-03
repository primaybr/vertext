<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <a href="<?php echo $baseUrl; ?>/admin/forms" class="vtx-breadcrumb">
      <i class="pi pi-clipboard me-1"></i> Forms
    </a>
    <span class="vtx-breadcrumb-sep">/</span>
    <a href="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions" class="vtx-breadcrumb">
      <?php echo htmlspecialchars($form['name']); ?>
    </a>
    <h1 class="vtx-page-title" style="margin-top:.25rem;">
      Submission Detail
    </h1>
  </div>
  <div style="display:flex;gap:.5rem;align-items:center;">
    <a href="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions"
       class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-arrow-left me-1"></i> Back
    </a>
    <?php if (\App\CMS\Auth::can('forms.delete_submission')): ?>
    <button type="button" class="btn btn-outline-danger btn-sm"
            data-vtx-confirm="Delete this submission permanently?"
            data-vtx-action="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions/<?php echo $sub['id']; ?>/delete"
            data-vtx-csrf="<?php echo htmlspecialchars($csrf_token ?? ''); ?>"
            data-vtx-redirect="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions">
      <i class="pi pi-trash me-1"></i> Delete
    </button>
    <?php endif; ?>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 280px;gap:1.25rem;align-items:start;">

  <!-- Field data -->
  <div class="vtx-panel">
    <div class="vtx-panel-header">Submitted Data</div>
    <div class="vtx-panel-body">
      <?php if (empty($fields)): ?>
      <p style="color:var(--ps-text-muted);">No fields defined for this form.</p>
      <?php else: ?>
        <?php foreach ($fields as $field): ?>
        <div class="vtx-field mb-3">
          <label class="vtx-label"><?php echo htmlspecialchars($field['label'] ?? $field['id']); ?></label>
          <?php
          $val = $data[$field['id']] ?? null;
          if (is_array($val)) {
            echo '<div style="color:var(--ps-text);">' . htmlspecialchars(json_encode($val, JSON_UNESCAPED_UNICODE)) . '</div>';
          } elseif (($field['type'] ?? '') === 'file' && is_string($val) && str_starts_with($val, 'uploads/forms/')) {
            // File upload: value is a stored path - link to it
            echo '<div><a href="' . htmlspecialchars($baseUrl . '/' . $val) . '" target="_blank" rel="noopener">'
               . '<i class="pi pi-file me-1"></i>' . htmlspecialchars(basename($val)) . '</a></div>';
          } elseif ($val !== null && $val !== '') {
            echo '<div style="color:var(--ps-text);white-space:pre-wrap;">' . htmlspecialchars((string) $val) . '</div>';
          } else {
            echo '<div style="color:var(--ps-text-muted);font-style:italic;">- empty -</div>';
          }
          ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- Extra data keys not in current field schema -->
      <?php
      $fieldIds = array_column($fields, 'id');
      $extra = array_diff_key($data, array_flip($fieldIds));
      if ($extra): ?>
      <hr>
      <p style="font-size:.8125rem;color:var(--ps-text-muted);margin-bottom:.5rem;">Additional data:</p>
      <?php foreach ($extra as $key => $val): ?>
      <div class="vtx-field mb-2">
        <label class="vtx-label" style="font-family:monospace;"><?php echo htmlspecialchars($key); ?></label>
        <div style="color:var(--ps-text);"><?php echo htmlspecialchars(is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : (string) $val); ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Metadata sidebar -->
  <div class="vtx-panel">
    <div class="vtx-panel-header">Metadata</div>
    <div class="vtx-panel-body">
      <div style="font-size:.8125rem;display:grid;gap:.75rem;">
        <div>
          <span style="color:var(--ps-text-muted);">Status</span>
          <div style="margin-top:.2rem;">
            <?php if ($sub['status'] === 'unread'): ?>
            <span class="badge badge-primary">Unread</span>
            <?php else: ?>
            <span class="badge badge-secondary">Read</span>
            <?php endif; ?>
          </div>
        </div>
        <div>
          <span style="color:var(--ps-text-muted);">Submitted At</span>
          <div style="margin-top:.2rem;font-weight:600;">
            <?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($sub['submitted_at']))); ?>
          </div>
        </div>
        <div>
          <span style="color:var(--ps-text-muted);">Form</span>
          <div style="margin-top:.2rem;">
            <a href="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions">
              <?php echo htmlspecialchars($form['name']); ?>
            </a>
          </div>
        </div>
        <div>
          <span style="color:var(--ps-text-muted);">Submission ID</span>
          <div style="margin-top:.2rem;font-family:monospace;font-size:.75rem;word-break:break-all;color:var(--ps-text-muted);">
            <?php echo htmlspecialchars($sub['id']); ?>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
