/* ── admin/index.php: member status/resend-verification actions ── */
(function () {
  'use strict';

  // Status change + resend verification: AJAX with in-place row update
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-member-action]');
    if (!btn) return;
    e.preventDefault();

    var id     = btn.dataset.memberId;
    var action = btn.dataset.memberAction;
    var base   = window.VTX_BASE_URL + '/admin/members/' + id;
    var csrf   = document.querySelector('[data-members-page]');
    csrf = csrf ? csrf.dataset.csrf : '';

    function post(url, fields, done) {
      var fd = new FormData();
      fd.append('csrf_token', csrf);
      Object.keys(fields || {}).forEach(function (k) { fd.append(k, fields[k]); });
      fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) { done(true, res); })
        .catch(function () { done(false, null); });
    }

    if (action === 'resend') {
      post(base + '/resend-verification', {}, function (ok, res) {
        Phuse.toast(res && res.message ? res.message : (ok ? 'Sent.' : 'Failed.'),
                    ok && res && res.success ? 'success' : 'error');
      });
      return;
    }

    if (action === 'status') {
      var to   = btn.dataset.memberStatusTo;
      var name = btn.dataset.memberName || 'this member';
      window.vtxConfirmModal({
        title:        to === 'active' ? 'Activate Member' : 'Suspend Member',
        message:      (to === 'active' ? 'Activate' : 'Suspend') + ' "' + name + '"?',
        confirmLabel: to === 'active' ? 'Activate' : 'Suspend',
        confirmClass: to === 'active' ? 'btn-primary' : 'btn-danger',
        onConfirm: function () {
          post(base + '/status', { status: to }, function (ok, res) {
            var success = ok && res && res.success;
            Phuse.toast(res && res.message ? res.message : (ok ? 'Done.' : 'Failed.'),
                        success ? 'success' : 'error');
            if (!success) return;
            // Update the status tag + swap action buttons in place
            var row = document.querySelector('[data-member-row="' + id + '"]');
            if (!row) return;
            var cell = row.querySelector('[data-member-status]');
            if (cell) {
              var cls = to === 'active' ? 'success' : 'error';
              cell.innerHTML = '<span class="vtx-tag ' + cls + '">' + (to.charAt(0).toUpperCase() + to.slice(1)) + '</span>';
            }
            // Toggle which status button shows
            row.querySelectorAll('[data-member-action="status"], [data-member-action="resend"]').forEach(function (b) {
              if (b.dataset.memberStatusTo === (to === 'active' ? 'suspended' : 'active')) {
                b.style.display = '';
              } else {
                b.style.display = 'none';
              }
            });
            // Ensure the opposite action exists; simplest robust path is a soft refresh
            // of counts - the tab counts are server-rendered, so we leave them until
            // the next navigation rather than reloading the page.
          });
        }
      });
    }
  });
})();
