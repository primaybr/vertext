/* Events Module - Admin scripts */

/* -- admin/_v2_fields.php: dynamic add-ticket-row builder -- */
(function () {
  var addBtn = document.getElementById('ev-add-ticket');
  if (!addBtn) return;

  addBtn.addEventListener('click', function () {
    var row = document.createElement('div');
    row.className = 'ev-ticket-row';
    row.style.cssText = 'display:flex;gap:.5rem;margin-bottom:.5rem;';
    row.innerHTML = '<input class="form-control form-control-sm" type="text" name="ticket_name[]" maxlength="100" placeholder="Ticket name (e.g. General)" style="flex:2;">' +
      '<input class="form-control form-control-sm" type="number" name="ticket_price[]" min="0" step="0.01" placeholder="0.00" style="flex:1;">' +
      '<button type="button" class="vtx-icon-btn danger" onclick="this.parentElement.remove()" title="Remove"><i class="pi pi-trash"></i></button>';
    var rows = document.getElementById('ev-ticket-rows');
    if (rows) rows.appendChild(row);
  });
})();

/* -- admin/attendees.php: attendee status-select → AJAX update -- */
(function () {
  'use strict';

  document.querySelectorAll('[data-attendee-select]').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var to     = sel.value;
      var id     = sel.dataset.attendeeId;
      var scope  = sel.closest('[data-event-id]');
      var evtId  = scope ? scope.dataset.eventId : '';
      var csrf   = scope ? scope.dataset.csrf : '';

      var fd = new FormData();
      fd.append('csrf_token', csrf);
      fd.append('status', to);

      fetch(window.VTX_BASE_URL + '/admin/events/' + evtId + '/attendees/' + id + '/status', {
        method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        Phuse.toast(res.message || (res.success ? 'Updated.' : 'Failed.'), res.success ? 'success' : 'error');
        if (!res.success) { sel.value = sel.dataset.current; return; }
        sel.dataset.current = to;
        var cell = sel.closest('tr').querySelector('[data-attendee-status]');
        var cls  = to === 'confirmed' ? 'success' : (to === 'waitlist' ? 'warning' : 'error');
        var tag = document.createElement('span');
        tag.className = 'vtx-tag ' + cls;
        tag.textContent = to.charAt(0).toUpperCase() + to.slice(1);
        cell.textContent = '';
        cell.appendChild(tag);
        // A promotion changes another row server-side; refresh counts lazily on next visit
      })
      .catch(function () { sel.value = sel.dataset.current; Phuse.toast('Network error.', 'error'); });
    });
  });
})();
