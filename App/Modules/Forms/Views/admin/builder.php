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

<div style="display:grid;grid-template-columns:280px 1fr;gap:1.25rem;align-items:start;">

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

<script>
(function () {
  const BASE   = <?php echo json_encode($baseUrl); ?>;
  const FORM_ID = <?php echo json_encode($formId); ?>;
  const CSRF   = <?php echo json_encode($csrf_token ?? ''); ?>;
  let fields   = <?php echo json_encode(array_values($form['fields'] ?? [])); ?>;

  // ── Render ────────────────────────────────────────────────────────────────
  function render() {
    const canvas = document.getElementById('vtx-field-canvas');
    const empty  = document.getElementById('vtx-canvas-empty');
    const count  = document.getElementById('vtx-field-count');
    count.textContent = fields.length;
    empty.style.display = fields.length ? 'none' : 'block';

    // Remove old field rows (not the empty placeholder)
    canvas.querySelectorAll('.vtx-field-row').forEach(el => el.remove());

    fields.forEach((field, idx) => {
      const row = buildRow(field, idx);
      canvas.appendChild(row);
    });
    initDrag();
  }

  function buildRow(field, idx) {
    const row = document.createElement('div');
    row.className = 'vtx-field-row';
    row.dataset.idx = idx;
    row.draggable = true;

    const hasOptions = ['select', 'radio', 'checkbox'].includes(field.type);

    // Step breaks are page separators, not inputs - render a slim divider row
    if (field.type === 'step') {
      row.innerHTML = `
        <div class="vtx-field-row-handle" title="Drag to reorder"><i class="pi pi-bars"></i></div>
        <div class="vtx-field-row-body" style="display:flex;align-items:center;gap:.75rem;">
          <span class="vtx-tag" style="flex-shrink:0;"><i class="pi pi-arrow-right me-1"></i>Step break</span>
          <div class="vtx-field" style="flex:1;margin:0;">
            <input class="form-control form-control-sm" type="text" value="${esc(field.label)}"
                   data-field-prop="label" data-idx="${idx}" placeholder="Step title (shown in progress bar)">
          </div>
        </div>
        <button type="button" class="vtx-field-row-delete" data-delete-idx="${idx}" title="Remove step break">
          <i class="pi pi-trash"></i>
        </button>
      `;
      row.querySelectorAll('[data-field-prop]').forEach(input => {
        input.addEventListener('input', () => {
          fields[parseInt(input.dataset.idx, 10)][input.dataset.fieldProp] = input.value;
        });
      });
      row.querySelector('[data-delete-idx]').addEventListener('click', () => {
        fields.splice(idx, 1);
        render();
      });
      return row;
    }

    // Conditional logic summary for the toggle button
    const condCount = (field.conditions || []).length;

    row.innerHTML = `
      <div class="vtx-field-row-handle" title="Drag to reorder"><i class="pi pi-bars"></i></div>
      <div class="vtx-field-row-body">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end;">
          <div class="vtx-field" style="flex:2;min-width:140px;">
            <label class="vtx-label" style="font-size:.75rem;">Label</label>
            <input class="form-control form-control-sm" type="text" value="${esc(field.label)}"
                   data-field-prop="label" data-idx="${idx}">
          </div>
          <div class="vtx-field" style="flex:1;min-width:100px;">
            <label class="vtx-label" style="font-size:.75rem;">ID / Name</label>
            <input class="form-control form-control-sm" type="text" value="${esc(field.id)}"
                   data-field-prop="id" data-idx="${idx}">
          </div>
          <div class="vtx-field" style="min-width:80px;">
            <label class="vtx-label" style="font-size:.75rem;">Type</label>
            <input class="form-control form-control-sm" value="${esc(field.type)}" readonly
                   style="background:var(--ps-bg-alt);cursor:default;">
          </div>
          <div class="vtx-field" style="min-width:90px;">
            <label class="vtx-label" style="font-size:.75rem;">Width</label>
            <select class="form-select form-select-sm" data-field-prop="width" data-idx="${idx}">
              <option value="full" ${field.width === 'full' ? 'selected' : ''}>Full</option>
              <option value="half" ${field.width === 'half' ? 'selected' : ''}>Half</option>
            </select>
          </div>
          <label class="vtx-label" style="display:flex;align-items:center;gap:.3rem;font-size:.8rem;cursor:pointer;white-space:nowrap;margin-bottom:2px;">
            <input type="checkbox" ${field.required ? 'checked' : ''} data-field-prop="required" data-idx="${idx}"> Required
          </label>
        </div>
        ${!['checkbox', 'radio', 'select', 'file'].includes(field.type) ? `
        <div class="vtx-field mt-2">
          <label class="vtx-label" style="font-size:.75rem;">Placeholder</label>
          <input class="form-control form-control-sm" type="text" value="${esc(field.placeholder || '')}"
                 data-field-prop="placeholder" data-idx="${idx}" placeholder="Optional...">
        </div>` : ''}
        ${hasOptions ? `
        <div class="vtx-field mt-2">
          <label class="vtx-label" style="font-size:.75rem;">Options <span style="font-weight:400;color:var(--ps-text-muted);">(one per line)</span></label>
          <textarea class="form-control form-control-sm" rows="3"
                    data-field-prop="options" data-idx="${idx}"
                    placeholder="Option 1&#10;Option 2">${(field.options || []).join('\n')}</textarea>
        </div>` : ''}
        <div class="mt-2">
          <button type="button" class="btn btn-sm btn-link p-0" data-toggle-logic="${idx}" style="font-size:.75rem;">
            <i class="pi pi-sliders me-1"></i>Conditional logic${condCount ? ' (' + condCount + ')' : ''}
          </button>
          <div data-logic-panel="${idx}" style="display:${condCount ? 'block' : 'none'};margin-top:.5rem;
               padding:.6rem;border:1px dashed var(--ps-border);border-radius:6px;">
            <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;font-size:.8rem;">
              <select class="form-select form-select-sm" style="width:auto;" data-cond-prop="action" data-idx="${idx}">
                <option value="show" ${(field.conditions?.[0]?.action || 'show') === 'show' ? 'selected' : ''}>Show</option>
                <option value="hide" ${(field.conditions?.[0]?.action) === 'hide' ? 'selected' : ''}>Hide</option>
              </select>
              <span>this field when</span>
              <select class="form-select form-select-sm" style="width:auto;min-width:110px;" data-cond-prop="field" data-idx="${idx}">
                <option value="">-- field --</option>
                ${fields.filter((f, fi) => fi !== idx && f.type !== 'step' && f.type !== 'file')
                        .map(f => `<option value="${esc(f.id)}" ${(field.conditions?.[0]?.field) === f.id ? 'selected' : ''}>${esc(f.label)}</option>`).join('')}
              </select>
              <select class="form-select form-select-sm" style="width:auto;" data-cond-prop="operator" data-idx="${idx}">
                ${['equals','not_equals','contains','empty','not_empty']
                  .map(op => `<option value="${op}" ${(field.conditions?.[0]?.operator || 'equals') === op ? 'selected' : ''}>${op.replace('_',' ')}</option>`).join('')}
              </select>
              <input class="form-control form-control-sm" style="width:120px;" type="text" placeholder="value"
                     value="${esc(field.conditions?.[0]?.value || '')}" data-cond-prop="value" data-idx="${idx}">
              <button type="button" class="vtx-icon-btn" title="Clear rule" data-cond-clear="${idx}">
                <i class="pi pi-x-circle"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      <button type="button" class="vtx-field-row-delete" data-delete-idx="${idx}" title="Remove field">
        <i class="pi pi-trash"></i>
      </button>
    `;

    // Conditional logic wiring
    const logicBtn = row.querySelector('[data-toggle-logic]');
    if (logicBtn) {
      logicBtn.addEventListener('click', () => {
        const panel = row.querySelector('[data-logic-panel]');
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
      });
    }
    row.querySelectorAll('[data-cond-prop]').forEach(input => {
      input.addEventListener('input', () => {
        const i = parseInt(input.dataset.idx, 10);
        if (!fields[i].conditions || !fields[i].conditions.length) {
          fields[i].conditions = [{ field: '', operator: 'equals', value: '', action: 'show' }];
        }
        fields[i].conditions[0][input.dataset.condProp] = input.value;
      });
    });
    const clearBtn = row.querySelector('[data-cond-clear]');
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        fields[idx].conditions = [];
        render();
      });
    }

    // Live-update field props
    row.querySelectorAll('[data-field-prop]').forEach(input => {
      input.addEventListener('input', () => {
        const prop = input.dataset.fieldProp;
        const i    = parseInt(input.dataset.idx, 10);
        if (prop === 'required') {
          fields[i].required = input.checked;
        } else if (prop === 'options') {
          fields[i].options = input.value.split('\n').map(s => s.trim()).filter(Boolean);
        } else {
          fields[i][prop] = input.value;
        }
      });
    });

    row.querySelector('[data-delete-idx]').addEventListener('click', () => {
      fields.splice(idx, 1);
      render();
    });

    return row;
  }

  // ── Drag-to-reorder ────────────────────────────────────────────────────────
  let dragSrc = null;

  function initDrag() {
    document.querySelectorAll('.vtx-field-row').forEach(row => {
      row.addEventListener('dragstart', e => {
        dragSrc = row;
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
      });
      row.addEventListener('dragend', () => row.classList.remove('dragging'));
      row.addEventListener('dragover', e => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
      });
      row.addEventListener('drop', e => {
        e.preventDefault();
        if (!dragSrc || dragSrc === row) return;
        const srcIdx  = parseInt(dragSrc.dataset.idx, 10);
        const dstIdx  = parseInt(row.dataset.idx, 10);
        const moved   = fields.splice(srcIdx, 1)[0];
        fields.splice(dstIdx, 0, moved);
        render();
      });
    });
  }

  // ── Add field ──────────────────────────────────────────────────────────────
  document.querySelectorAll('[data-add-field]').forEach(btn => {
    btn.addEventListener('click', () => {
      const type = btn.dataset.addField;
      const id   = type + '_' + (fields.length + 1);
      const label = type === 'step' ? '' : capitalize(type) + ' ' + (fields.length + 1);
      fields.push({ id, type, label,
                    placeholder: '', required: false, options: [], width: 'full', conditions: [] });
      render();
    });
  });

  // ── Save ───────────────────────────────────────────────────────────────────
  document.getElementById('vtx-builder-save').addEventListener('click', () => {
    const settings = {
      success_message:      document.getElementById('vtx-success-msg').value.trim(),
      notification_email:   document.getElementById('vtx-notify-email').value.trim(),
      math_challenge:       document.getElementById('vtx-math-challenge').checked,
      recaptcha_site_key:   document.getElementById('vtx-recaptcha-site').value.trim(),
      recaptcha_secret_key: document.getElementById('vtx-recaptcha-secret').value.trim(),
    };

    // Drop empty condition rules before saving
    fields.forEach(f => {
      if (f.conditions && f.conditions.length && !f.conditions[0].field) f.conditions = [];
    });

    const btn = document.getElementById('vtx-builder-save');
    btn.disabled = true;

    fetch(BASE + '/admin/forms/' + FORM_ID + '/save-fields', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ csrf_token: CSRF, fields: JSON.stringify(fields), settings: JSON.stringify(settings) }),
    })
    .then(r => r.json())
    .then(res => {
      showMsg(res.success ? 'success' : 'error', res.message || 'Saved.');
    })
    .catch(() => showMsg('error', 'Network error. Please try again.'))
    .finally(() => { btn.disabled = false; });
  });

  // ── Utils ─────────────────────────────────────────────────────────────────
  function esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
  function showMsg(type, msg) {
    const el = document.getElementById('vtx-builder-message');
    el.className = 'vtx-alert vtx-alert-' + type + ' mb-3';
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
  }

  render();
})();
</script>
