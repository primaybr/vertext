<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <a href="<?php echo $baseUrl; ?>/admin/forms" class="vtx-breadcrumb">
      <i class="pi pi-clipboard me-1"></i> Forms
    </a>
    <h1 class="vtx-page-title" style="margin-top:.25rem;">
      <i class="pi pi-sliders me-2 text-primary"></i><?php echo htmlspecialchars($form['name']); ?>
    </h1>
  </div>
  <div style="display:flex;gap:.5rem;align-items:center;">
    <a href="<?php echo $baseUrl; ?>/forms/<?php echo htmlspecialchars($form['slug']); ?>"
       target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-external-link me-1"></i> Preview
    </a>
    <a href="<?php echo $baseUrl; ?>/admin/forms/<?php echo $form['id']; ?>/submissions"
       class="btn btn-outline-secondary btn-sm">
      <i class="pi pi-inbox me-1"></i> Submissions
    </a>
    <button type="button" class="btn btn-primary btn-sm" id="vtx-builder-save">
      <i class="pi pi-save me-1"></i> Save Fields
    </button>
  </div>
</div>

<div id="vtx-builder-message" class="mb-3" style="display:none;"></div>

<div id="vtx-form-builder" style="display:grid;grid-template-columns:280px 1fr;gap:1.25rem;align-items:start;"
     data-base-url="<?php echo htmlspecialchars($baseUrl); ?>"
     data-form-id="<?php echo htmlspecialchars((string) $formId); ?>"
     data-csrf="<?php echo htmlspecialchars($csrf_token ?? ''); ?>"
     data-fields='<?php echo htmlspecialchars(json_encode(array_values($form['fields'] ?? [])), ENT_QUOTES); ?>'>

  <!-- Left: Field type palette -->
  <div>
    <div class="vtx-panel">
      <div class="vtx-panel-header">Add Field</div>
      <div class="vtx-panel-body" style="padding:.5rem;">
        <?php
        $fieldTypes = [
          ['type' => 'text',     'icon' => 'pi-edit',   'label' => 'Text'],
          ['type' => 'email',    'icon' => 'pi-mail',   'label' => 'Email'],
          ['type' => 'textarea', 'icon' => 'pi-file',   'label' => 'Textarea'],
          ['type' => 'number',   'icon' => 'pi-list',   'label' => 'Number'],
          ['type' => 'date',     'icon' => 'pi-calendar','label' => 'Date'],
          ['type' => 'select',   'icon' => 'pi-chevron-down', 'label' => 'Dropdown'],
          ['type' => 'radio',    'icon' => 'pi-check-circle', 'label' => 'Radio'],
          ['type' => 'checkbox', 'icon' => 'pi-check',  'label' => 'Checkbox'],
          ['type' => 'file',     'icon' => 'pi-image',  'label' => 'File Upload'],
          ['type' => 'step',     'icon' => 'pi-arrow-right', 'label' => 'Step Break'],
        ];
        foreach ($fieldTypes as $ft): ?>
        <button type="button" class="vtx-field-type-btn" data-add-field="<?php echo $ft['type']; ?>">
          <i class="pi <?php echo $ft['icon']; ?> me-2"></i><?php echo $ft['label']; ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="vtx-panel mt-3">
      <div class="vtx-panel-header">Form Settings</div>
      <div class="vtx-panel-body">
        <?php $settings = json_decode($form['settings'] ?? '{}', true) ?: []; ?>
        <div class="vtx-field mb-2">
          <label class="vtx-label" style="font-size:.8rem;" for="vtx-success-msg">Success Message</label>
          <textarea class="form-control form-control-sm" id="vtx-success-msg" rows="2"
            placeholder="Thank you! Your response has been submitted."><?php
            echo htmlspecialchars($settings['success_message'] ?? '');
          ?></textarea>
        </div>
        <div class="vtx-field mb-2">
          <label class="vtx-label" style="font-size:.8rem;" for="vtx-notify-email">Notification Email</label>
          <input class="form-control form-control-sm" type="email" id="vtx-notify-email"
                 placeholder="Leave empty for no email"
                 value="<?php echo htmlspecialchars($settings['notification_email'] ?? ''); ?>">
          <div style="font-size:.7rem;color:var(--ps-text-muted);margin-top:.25rem;">
            Receives an email for every submission.
          </div>
        </div>
        <div class="vtx-field mb-2">
          <label class="vtx-label" style="display:flex;align-items:center;gap:.4rem;font-size:.8rem;cursor:pointer;">
            <input type="checkbox" id="vtx-math-challenge" <?php echo !empty($settings['math_challenge']) ? 'checked' : ''; ?>>
            Math challenge (anti-spam)
          </label>
          <div style="font-size:.7rem;color:var(--ps-text-muted);margin-top:.25rem;">
            Asks visitors a simple "3 + 4 = ?" question.
          </div>
        </div>
        <div class="vtx-field mb-2">
          <label class="vtx-label" style="font-size:.8rem;" for="vtx-recaptcha-site">reCAPTCHA v3 Site Key</label>
          <input class="form-control form-control-sm" type="text" id="vtx-recaptcha-site"
                 placeholder="Optional" value="<?php echo htmlspecialchars($settings['recaptcha_site_key'] ?? ''); ?>">
        </div>
        <div class="vtx-field mb-2">
          <label class="vtx-label" style="font-size:.8rem;" for="vtx-recaptcha-secret">reCAPTCHA v3 Secret Key</label>
          <input class="form-control form-control-sm" type="password" id="vtx-recaptcha-secret"
                 placeholder="Optional" value="<?php echo htmlspecialchars($settings['recaptcha_secret_key'] ?? ''); ?>">
          <div style="font-size:.7rem;color:var(--ps-text-muted);margin-top:.25rem;">
            Both keys required to enable. Scores below 0.5 are rejected.
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: Field canvas -->
  <div class="vtx-panel">
    <div class="vtx-panel-header" style="display:flex;justify-content:space-between;align-items:center;">
      <span>Fields <span id="vtx-field-count" class="badge badge-secondary ms-1">0</span></span>
      <span style="font-size:.75rem;color:var(--ps-text-muted);">Drag to reorder</span>
    </div>
    <div class="vtx-panel-body p-0">
      <div id="vtx-field-canvas" style="min-height:200px;padding:.5rem;">
        <div id="vtx-canvas-empty" style="padding:2rem;text-align:center;color:var(--ps-text-muted);">
          <i class="pi pi-sliders pi-2x mb-2" style="opacity:.3;display:block;margin:0 auto;"></i>
          Add fields from the left panel
        </div>
      </div>
    </div>
  </div>

</div>
