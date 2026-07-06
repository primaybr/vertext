/* newsletter module admin scripts */

/* -- admin/campaign_form.php: tab switching + send/save/schedule AJAX -- */
(function () {
  var cfg = document.getElementById('nl-campaign-config');
  if (!cfg) return;

  var BASE      = cfg.dataset.baseUrl || '';
  var CSRF      = cfg.dataset.csrf || '';
  var CAMP_ID   = cfg.dataset.campaignId || '';
  var EDITABLE  = cfg.dataset.editable === '1';

  // Tab switching
  document.querySelectorAll('.nl-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.nl-tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const tab = btn.dataset.tab;
      document.getElementById('nl-tab-html').style.display = tab === 'html' ? '' : 'none';
      document.getElementById('nl-tab-text').style.display = tab === 'text' ? '' : 'none';
    });
  });

  if (EDITABLE) {
    // Auto-save on form submit via fetch if action differs from page (AJAX update)
    const form = document.getElementById('nl-campaign-form');
    if (form && CAMP_ID) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        const data = new URLSearchParams(new FormData(form));
        fetch(form.action, { method: 'POST', body: data })
          .then(r => r.json())
          .then(res => showApiMsg(res.success ? 'success' : 'error', res.message || 'Saved.'))
          .catch(() => showApiMsg('error', 'Network error.'));
      });
    }

    // Send campaign
    const sendBtn = document.getElementById('nl-send-btn');
    if (sendBtn) {
      sendBtn.addEventListener('click', () => {
        const count = parseInt(sendBtn.dataset.subCount, 10);
        if (!confirm('Send this campaign to ' + count + ' active subscriber(s)? This cannot be undone.')) return;
        sendBtn.disabled = true;
        fetch(BASE + '/admin/newsletter/campaigns/' + CAMP_ID + '/send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ csrf_token: CSRF }),
        })
        .then(r => r.json())
        .then(res => {
          showApiMsg(res.success ? 'success' : 'error', res.message || '');
          if (res.success) setTimeout(() => location.reload(), 1500);
          else sendBtn.disabled = false;
        })
        .catch(() => { showApiMsg('error', 'Network error.'); sendBtn.disabled = false; });
      });
    }

    // Test send
    const testBtn = document.getElementById('nl-test-btn');
    if (testBtn) {
      testBtn.addEventListener('click', () => {
        document.getElementById('nl-test-modal').style.display = 'flex';
      });
      document.getElementById('nl-test-confirm').addEventListener('click', () => {
        const email = document.getElementById('nl-test-email').value.trim();
        if (!email) return;
        document.getElementById('nl-test-confirm').disabled = true;
        fetch(BASE + '/admin/newsletter/campaigns/' + CAMP_ID + '/test-send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ csrf_token: CSRF, test_email: email }),
        })
        .then(r => r.json())
        .then(res => {
          const msgEl = document.getElementById('nl-test-msg');
          msgEl.className = 'vtx-alert vtx-alert-' + (res.success ? 'success' : 'error');
          msgEl.textContent = res.message || '';
          msgEl.style.display = 'block';
          document.getElementById('nl-test-confirm').disabled = false;
          if (res.success) setTimeout(() => { document.getElementById('nl-test-modal').style.display = 'none'; }, 2000);
        })
        .catch(() => { document.getElementById('nl-test-confirm').disabled = false; });
      });
    }
    // Schedule / unschedule
    const schedBtn = document.getElementById('nl-schedule-btn');
    if (schedBtn) {
      schedBtn.addEventListener('click', () => {
        const when = document.getElementById('nl-schedule-at').value;
        if (!when) { showApiMsg('error', 'Choose a date and time first.'); return; }
        schedBtn.disabled = true;
        fetch(BASE + '/admin/newsletter/campaigns/' + CAMP_ID + '/schedule', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ csrf_token: CSRF, scheduled_at: when }),
        })
        .then(r => r.json())
        .then(res => {
          showApiMsg(res.success ? 'success' : 'error', res.message || '');
          if (res.success) setTimeout(() => location.reload(), 1200);
          else schedBtn.disabled = false;
        })
        .catch(() => { showApiMsg('error', 'Network error.'); schedBtn.disabled = false; });
      });
    }
    const unschedBtn = document.getElementById('nl-unschedule-btn');
    if (unschedBtn) {
      unschedBtn.addEventListener('click', () => {
        unschedBtn.disabled = true;
        fetch(BASE + '/admin/newsletter/campaigns/' + CAMP_ID + '/unschedule', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ csrf_token: CSRF }),
        })
        .then(r => r.json())
        .then(res => {
          showApiMsg(res.success ? 'success' : 'error', res.message || '');
          if (res.success) setTimeout(() => location.reload(), 1200);
          else unschedBtn.disabled = false;
        })
        .catch(() => { showApiMsg('error', 'Network error.'); unschedBtn.disabled = false; });
      });
    }
  }

  function showApiMsg(type, msg) {
    const el = document.getElementById('nl-api-msg');
    el.className = 'vtx-alert vtx-alert-' + type + ' mb-3';
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 5000);
  }
})();

/* -- admin/subscribers.php: reset add/import modals on CRUD success -- */
document.addEventListener('vtx:crud:success', function () {
  var addModal    = document.getElementById('nl-add-modal');
  var importModal = document.getElementById('nl-import-modal');
  if (addModal)    { addModal.style.display = 'none'; addModal.querySelector('form').reset(); }
  if (importModal) { importModal.style.display = 'none'; importModal.querySelector('form').reset(); }
});
