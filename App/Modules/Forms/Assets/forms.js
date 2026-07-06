/* Forms Builder - shared utilities (admin builder logic lives in forms-admin.js) */

/* ── front/_embed.php: conditional fields, multi-step nav, reCAPTCHA v3 ── */
(function () {
  'use strict';

  function initForm(root) {
    var form = root.querySelector('[data-vf-form]');
    if (!form) return;

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
      var siteKey = root.dataset.recaptchaKey || '';
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
  }

  document.querySelectorAll('.vf-card[data-form-uid]').forEach(initForm);
})();
