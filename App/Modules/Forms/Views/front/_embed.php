<?php
/**
 * Embeddable form partial - used by the /forms/{slug} page AND the
 * [form slug="..."] shortcode. Expects: $form, $fields, $flash, $baseUrl,
 * $csrf_token, optional $member (logged-in site user), $mathQuestion.
 */
$vfSettings = json_decode($form['settings'] ?: '{}', true) ?: [];
$vfHasSteps = false;
foreach ($fields as $vfF) {
    if (($vfF['type'] ?? '') === 'step') { $vfHasSteps = true; break; }
}
$vfUid = 'vf-' . htmlspecialchars($form['slug']);
?>
<style>
.vf-card { background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: 8px; padding: 2rem; }
.vf-card h1 { margin-bottom: .5rem; font-size: 1.625rem; }
.vf-card p.desc { color: var(--clr-text-muted, #6b7280); margin-bottom: 1.5rem; }
.vf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }
.vf-col-full { grid-column: 1 / -1; }
.vf-col-half { grid-column: span 1; }
.vf-field { margin-bottom: 1.25rem; }
.vf-field label { display: block; font-size: .875rem; font-weight: 600; margin-bottom: .35rem; }
.vf-field input[type="text"],
.vf-field input[type="email"],
.vf-field input[type="number"],
.vf-field input[type="date"],
.vf-field input[type="file"],
.vf-field textarea,
.vf-field select {
  width: 100%; padding: .6rem .85rem; border: 1px solid var(--clr-border); border-radius: 6px;
  font-size: 1rem; font-family: inherit; background: var(--clr-bg); color: var(--clr-text);
  box-sizing: border-box;
}
.vf-field input:focus, .vf-field textarea:focus, .vf-field select:focus {
  outline: none; border-color: var(--clr-accent); box-shadow: 0 0 0 3px rgba(79,70,229,.15);
}
.vf-field .vf-options { display: flex; flex-direction: column; gap: .4rem; }
.vf-field .vf-options label { display: flex; gap: .5rem; align-items: center; font-weight: 400; cursor: pointer; }
.vf-required { color: #e53e3e; }
.vf-submit, .vf-nav-btn { background: var(--clr-accent); color: #fff; border: none; border-radius: 6px; padding: .65rem 1.75rem; font-size: 1rem; font-weight: 600; cursor: pointer; }
.vf-submit:hover, .vf-nav-btn:hover { opacity: .88; }
.vf-nav-btn.vf-back { background: transparent; color: var(--clr-text-muted, #6b7280); border: 1px solid var(--clr-border); }
.vf-alert { padding: .9rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: .95rem; }
.vf-alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.vf-alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
[data-theme="dark"] .vf-alert-success { background: rgba(16,185,129,.15); color: #6ee7b7; border-color: rgba(110,231,183,.3); }
[data-theme="dark"] .vf-alert-error   { background: rgba(239,68,68,.15);  color: #fca5a5; border-color: rgba(252,165,165,.3); }
.vf-progress { display: flex; gap: .5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.vf-progress-item { display: flex; align-items: center; gap: .4rem; font-size: .8125rem; color: var(--clr-text-muted, #6b7280); }
.vf-progress-item .num { width: 22px; height: 22px; border-radius: 50%; border: 1px solid var(--clr-border);
  display: flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 700; }
.vf-progress-item.active { color: var(--clr-text); font-weight: 600; }
.vf-progress-item.active .num { background: var(--clr-accent); border-color: var(--clr-accent); color: #fff; }
.vf-progress-item.done .num { background: var(--clr-accent); border-color: var(--clr-accent); color: #fff; opacity: .55; }
.vf-step { display: none; }
.vf-step.active { display: block; }
.vf-nav { display: flex; justify-content: space-between; gap: .75rem; margin-top: 1.5rem; }
@media (max-width: 540px) { .vf-grid { grid-template-columns: 1fr; } .vf-col-half { grid-column: 1 / -1; } }
</style>

<div class="vf-card" id="<?php echo $vfUid; ?>">
  <h1><?php echo htmlspecialchars($form['name']); ?></h1>
  <?php if (!empty($form['description'])): ?>
  <p class="desc"><?php echo htmlspecialchars($form['description']); ?></p>
  <?php endif; ?>

  <?php if (!empty($flash['message'])): ?>
  <div class="vf-alert vf-alert-<?php echo ($flash['type'] ?? '') === 'success' ? 'success' : 'error'; ?>">
    <?php echo htmlspecialchars($flash['message']); ?>
  </div>
  <?php endif; ?>

  <?php if (empty($flash) || ($flash['type'] ?? '') !== 'success'): ?>
  <form method="POST" action="<?php echo $baseUrl; ?>/forms/<?php echo htmlspecialchars($form['slug']); ?>/submit"
        enctype="multipart/form-data" data-vf-form>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
    <!-- Honeypot -->
    <div style="position:absolute;left:-9999px;visibility:hidden;" aria-hidden="true">
      <input type="text" name="website" tabindex="-1" autocomplete="off">
    </div>

    <?php if ($vfHasSteps): ?>
    <div class="vf-progress" data-vf-progress></div>
    <?php endif; ?>

    <?php
    // Group fields into steps at 'step' markers (single step when none)
    $vfSteps = [['title' => '', 'fields' => []]];
    foreach ($fields as $vfField) {
        if (($vfField['type'] ?? '') === 'step') {
            $vfSteps[] = ['title' => $vfField['label'] ?? '', 'fields' => []];
            continue;
        }
        $vfSteps[count($vfSteps) - 1]['fields'][] = $vfField;
    }
    // Drop a leading empty step (form starts with a step break)
    if (count($vfSteps) > 1 && empty($vfSteps[0]['fields'])) {
        array_shift($vfSteps);
    }

    foreach ($vfSteps as $stepIdx => $step):
    ?>
    <div class="vf-step<?php echo $stepIdx === 0 ? ' active' : ''; ?>" data-vf-step="<?php echo $stepIdx; ?>"
         data-vf-step-title="<?php echo htmlspecialchars($step['title'] ?: 'Step ' . ($stepIdx + 1)); ?>">
      <div class="vf-grid">
      <?php foreach ($step['fields'] as $field):
        $type  = $field['type'] ?? 'text';
        $id    = 'vf_' . htmlspecialchars($field['id']);
        $name  = htmlspecialchars($field['id']);
        $label = htmlspecialchars($field['label'] ?? $field['id']);
        $req   = !empty($field['required']);
        $ph    = htmlspecialchars($field['placeholder'] ?? '');
        $opts  = $field['options'] ?? [];
        $width = ($field['width'] ?? 'full') === 'half' ? 'half' : 'full';

        // Member prefill: email fields get the member email, a field with id
        // "name" gets the member name (only when no sticky old input exists)
        $prefill = '';
        if (!empty($member)) {
            if ($type === 'email')            $prefill = (string) ($member['email'] ?? '');
            elseif ($field['id'] === 'name')  $prefill = (string) ($member['name'] ?? '');
        }
      ?>
      <div class="vf-field vf-col-<?php echo $width; ?>" data-vf-field="<?php echo $name; ?>"
           <?php if (!empty($field['conditions'])): ?>data-vf-conditions='<?php echo htmlspecialchars(json_encode($field['conditions']), ENT_QUOTES); ?>'<?php endif; ?>>
        <?php if ($type !== 'checkbox'): ?>
        <label for="<?php echo $id; ?>"><?php echo $label; ?><?php if ($req) echo ' <span class="vf-required">*</span>'; ?></label>
        <?php endif; ?>

        <?php if ($type === 'textarea'): ?>
        <textarea id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                  rows="4" placeholder="<?php echo $ph; ?>"
                  <?php echo $req ? 'required' : ''; ?>></textarea>

        <?php elseif ($type === 'select'): ?>
        <select id="<?php echo $id; ?>" name="<?php echo $name; ?>" <?php echo $req ? 'required' : ''; ?>>
          <option value="">-- Select --</option>
          <?php foreach ($opts as $opt): ?>
          <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
          <?php endforeach; ?>
        </select>

        <?php elseif ($type === 'radio'): ?>
        <div class="vf-options">
          <?php foreach ($opts as $oi => $opt): ?>
          <label>
            <input type="radio" name="<?php echo $name; ?>"
                   value="<?php echo htmlspecialchars($opt); ?>"
                   <?php echo ($req && $oi === 0) ? 'required' : ''; ?>>
            <?php echo htmlspecialchars($opt); ?>
          </label>
          <?php endforeach; ?>
        </div>

        <?php elseif ($type === 'checkbox'): ?>
        <div class="vf-options">
          <?php foreach ($opts as $opt): ?>
          <label>
            <input type="checkbox" name="<?php echo $name; ?>[]"
                   value="<?php echo htmlspecialchars($opt); ?>">
            <?php echo htmlspecialchars($opt); ?>
          </label>
          <?php endforeach; ?>
          <?php if (empty($opts)): ?>
          <label>
            <input type="checkbox" name="<?php echo $name; ?>" value="1"
                   <?php echo $req ? 'required' : ''; ?>>
            <?php echo $label; ?>
          </label>
          <?php endif; ?>
        </div>

        <?php elseif ($type === 'file'): ?>
        <input type="file" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
               <?php echo $req ? 'required' : ''; ?>>

        <?php else: ?>
        <input type="<?php echo in_array($type, ['email','number','date']) ? $type : 'text'; ?>"
               id="<?php echo $id; ?>" name="<?php echo $name; ?>"
               placeholder="<?php echo $ph; ?>"
               <?php if ($prefill !== ''): ?>value="<?php echo htmlspecialchars($prefill); ?>"<?php endif; ?>
               <?php echo $req ? 'required' : ''; ?>>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($mathQuestion)): ?>
    <div class="vf-field">
      <label for="<?php echo $vfUid; ?>-math">
        Spam check: what is <?php echo htmlspecialchars($mathQuestion); ?>? <span class="vf-required">*</span>
      </label>
      <input type="number" id="<?php echo $vfUid; ?>-math" name="math_answer" required
             style="max-width:120px;" inputmode="numeric">
    </div>
    <?php endif; ?>

    <?php if (!empty($vfSettings['recaptcha_site_key']) && !empty($vfSettings['recaptcha_secret_key'])): ?>
    <input type="hidden" name="recaptcha_token" data-vf-recaptcha>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($vfSettings['recaptcha_site_key']); ?>" async defer></script>
    <?php endif; ?>

    <?php if ($vfHasSteps): ?>
    <div class="vf-nav">
      <button type="button" class="vf-nav-btn vf-back" data-vf-back style="visibility:hidden;">Back</button>
      <button type="button" class="vf-nav-btn" data-vf-next>Next</button>
      <button type="submit" class="vf-submit" data-vf-submit style="display:none;">Submit</button>
    </div>
    <?php else: ?>
    <div style="margin-top:1.5rem;">
      <button type="submit" class="vf-submit">Submit</button>
    </div>
    <?php endif; ?>
  </form>

  <script>
  (function () {
    'use strict';
    var root = document.getElementById(<?php echo json_encode($vfUid); ?>);
    if (!root) return;
    var form = root.querySelector('[data-vf-form]');

    /* ── Conditional logic ─────────────────────────────────────── */
    function fieldValue(name) {
      var els = form.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]');
      var vals = [];
      els.forEach(function (el) {
        if (el.type === 'radio' || el.type === 'checkbox') {
          if (el.checked) vals.push(el.value);
        } else {
          vals.push(el.value);
        }
      });
      return vals.join(',');
    }

    function evalCondition(c) {
      var v = (fieldValue(c.field) || '').toLowerCase();
      var t = (c.value || '').toLowerCase();
      switch (c.operator) {
        case 'equals':     return v === t;
        case 'not_equals': return v !== t;
        case 'contains':   return v.indexOf(t) !== -1;
        case 'empty':      return v === '';
        case 'not_empty':  return v !== '';
        default:           return false;
      }
    }

    function applyConditions() {
      root.querySelectorAll('[data-vf-conditions]').forEach(function (wrap) {
        var rules;
        try { rules = JSON.parse(wrap.dataset.vfConditions); } catch (e) { return; }
        if (!rules || !rules.length) return;
        var rule    = rules[0];
        var matched = evalCondition(rule);
        var visible = rule.action === 'hide' ? !matched : matched;
        wrap.style.display = visible ? '' : 'none';
        // Disabled inputs are skipped by HTML5 validation AND not submitted
        wrap.querySelectorAll('input, select, textarea').forEach(function (el) {
          el.disabled = !visible;
        });
      });
    }

    form.addEventListener('input', applyConditions);
    form.addEventListener('change', applyConditions);
    applyConditions();

    /* ── Multi-step navigation ─────────────────────────────────── */
    var steps = Array.prototype.slice.call(root.querySelectorAll('[data-vf-step]'));
    if (steps.length > 1) {
      var current  = 0;
      var progress = root.querySelector('[data-vf-progress]');
      var backBtn  = root.querySelector('[data-vf-back]');
      var nextBtn  = root.querySelector('[data-vf-next]');
      var subBtn   = root.querySelector('[data-vf-submit]');

      function renderProgress() {
        progress.innerHTML = '';
        steps.forEach(function (s, i) {
          var item = document.createElement('div');
          item.className = 'vf-progress-item' + (i === current ? ' active' : (i < current ? ' done' : ''));
          item.innerHTML = '<span class="num">' + (i + 1) + '</span><span>' + s.dataset.vfStepTitle + '</span>';
          progress.appendChild(item);
        });
      }

      function show(idx) {
        current = idx;
        steps.forEach(function (s, i) { s.classList.toggle('active', i === idx); });
        backBtn.style.visibility = idx === 0 ? 'hidden' : 'visible';
        nextBtn.style.display = idx === steps.length - 1 ? 'none' : '';
        subBtn.style.display  = idx === steps.length - 1 ? '' : 'none';
        renderProgress();
      }

      function validateStep(idx) {
        var ok = true;
        steps[idx].querySelectorAll('input, select, textarea').forEach(function (el) {
          if (!el.disabled && !el.checkValidity()) {
            if (ok) el.reportValidity();
            ok = false;
          }
        });
        return ok;
      }

      nextBtn.addEventListener('click', function () {
        if (validateStep(current)) show(Math.min(current + 1, steps.length - 1));
      });
      backBtn.addEventListener('click', function () { show(Math.max(current - 1, 0)); });
      show(0);
    }

    /* ── reCAPTCHA v3 token on submit ──────────────────────────── */
    var recaptchaInput = form.querySelector('[data-vf-recaptcha]');
    if (recaptchaInput) {
      var siteKey = <?php echo json_encode($vfSettings['recaptcha_site_key'] ?? ''); ?>;
      form.addEventListener('submit', function (e) {
        if (recaptchaInput.value) return; // token already fetched
        if (typeof grecaptcha === 'undefined') return; // fail open; server decides
        e.preventDefault();
        grecaptcha.ready(function () {
          grecaptcha.execute(siteKey, { action: 'form_submit' }).then(function (token) {
            recaptchaInput.value = token;
            form.submit();
          });
        });
      });
    }
  })();
  </script>
  <?php endif; ?>
</div>
