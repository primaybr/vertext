/* ── admin/navigation/builder.php: menu-item editor + drag reorder ── */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    var itemModalEl = document.getElementById('item-modal');
    if (!itemModalEl) return;

    var itemsPanel = document.getElementById('items-panel');
    var MENU_ID   = itemsPanel ? itemsPanel.dataset.menuId : '';
    var BASE_URL  = window.VTX_BASE_URL || '';
    var itemModal = Phuse.modal(itemModalEl);

    // Type toggle
    document.getElementById('item-type-select').addEventListener('change', function() {
      toggleTypeFields(this.value);
    });

    // Module route select - auto-fill label and url
    var mrSel = document.getElementById('item-module-route');
    if (mrSel) {
      mrSel.addEventListener('change', function() {
        var opt = this.options[this.selectedIndex];
        if (opt && opt.value) {
          document.getElementById('item-url').value   = opt.value;
          var labelEl = document.getElementById('item-label');
          if (!labelEl.value) labelEl.value = opt.dataset.label || '';
        }
      });
    }

    // Sync module routes button
    var syncBtn  = document.getElementById('sync-modules-btn');
    var syncForm = document.getElementById('sync-modules-form');
    if (syncBtn && syncForm) {
      syncBtn.addEventListener('click', function() {
        syncBtn.disabled = true;
        window.VtxAjax.postForm(syncForm.action, syncForm, function(ok, res) {
          syncBtn.disabled = false;
          window.Phuse.toast((res && res.message) || (ok ? 'Synced.' : 'Sync failed.'), (ok && res && res.success) ? 'success' : 'error');
          if (ok && res && res.success && res.added > 0) {
            setTimeout(function() { location.reload(); }, 600);
          }
        });
      });
    }

    // Add Item button
    document.getElementById('add-item-btn').addEventListener('click', function() {
      openItemModal();
    });

    // Edit buttons (also re-bound after reload)
    bindEditButtons();

    // Form submit
    document.getElementById('item-form').addEventListener('submit', handleItemSubmit);

    // Confirm-ajax for delete
    initConfirmAjax();

    function toggleTypeFields(type) {
      document.getElementById('custom-url-field').style.display = type === 'custom' ? '' : 'none';
      var pf = document.getElementById('page-select-field');
      if (pf) pf.style.display = type === 'page' ? '' : 'none';
      var mf = document.getElementById('module-route-field');
      if (mf) mf.style.display = type === 'module' ? '' : 'none';
    }

    function openItemModal(data) {
      document.getElementById('item-modal-title').textContent = data ? 'Edit Item' : 'Add Item';
      document.getElementById('item-submit-label').textContent = data ? 'Save Changes' : 'Add Item';
      document.getElementById('item-id-field').value      = data ? data.id : '';
      document.getElementById('item-type-select').value   = data ? data.type : 'custom';
      document.getElementById('item-label').value         = data ? data.label : '';
      document.getElementById('item-url').value           = data ? data.url : '';
      var pslug = document.getElementById('item-page-slug');
      if (pslug) pslug.value = data ? (data.pageSlug || '') : '';
      var mr = document.getElementById('item-module-route');
      if (mr) mr.value = (data && data.type === 'module') ? (data.url || '') : '';
      document.getElementById('item-open-in-new').checked = data ? (data.openInNew === '1') : false;
      toggleTypeFields(data ? data.type : 'custom');
      if (data) {
        document.getElementById('item-type-select').disabled = true;
      } else {
        document.getElementById('item-type-select').disabled = false;
      }
      itemModal.show();
    }

    function bindEditButtons() {
      document.querySelectorAll('.edit-item-btn:not([data-wired])').forEach(function(btn) {
        btn.dataset.wired = '1';
        btn.addEventListener('click', function() {
          openItemModal({
            id:        this.dataset.id,
            type:      this.dataset.type,
            label:     this.dataset.label,
            url:       this.dataset.url,
            pageSlug:  this.dataset.pageSlug,
            openInNew: this.dataset.openInNew,
          });
        });
      });
    }

    function handleItemSubmit(e) {
      e.preventDefault();
      var btn      = document.getElementById('item-submit-btn');
      var itemId   = document.getElementById('item-id-field').value;
      var isEdit   = !!itemId;
      var action   = isEdit
        ? BASE_URL + '/admin/navigation/' + MENU_ID + '/items/' + itemId + '/update'
        : BASE_URL + '/admin/navigation/' + MENU_ID + '/items/store';

      btn.disabled = true;

      window.VtxAjax.postForm(action, this, function(ok, res) {
        if (ok && res && res.success) {
          window.Phuse.toast(res.message || 'Saved.', 'success');
          itemModal.hide();
          setTimeout(function() { location.reload(); }, 400);
        } else {
          btn.disabled = false;
          window.Phuse.toast((res && res.message) || 'Failed to save item.', 'error');
        }
      });
    }

    function initConfirmAjax() {
      document.querySelectorAll('[data-confirm-form][data-confirm-ajax="true"]:not([data-wired])').forEach(function(btn) {
        btn.dataset.wired = '1';
        btn.addEventListener('click', function() {
          var formId = this.dataset.confirmForm;
          var form   = document.getElementById(formId);
          var me     = this;
          window.vtxConfirmModal({
            title:        this.dataset.confirmTitle || 'Confirm',
            message:      this.dataset.confirmMessage || 'Are you sure?',
            confirmLabel: this.dataset.confirmLabel || 'Confirm',
            confirmClass: this.dataset.confirmClass || 'btn-danger',
            onConfirm: function() {
              me.disabled = true;
              window.VtxAjax.postForm(form.action, form, function(ok, res) {
                var msg = (res && res.message) ? res.message : (ok ? 'Done.' : 'Failed.');
                window.Phuse.toast(msg, (ok && res && res.success) ? 'success' : 'error');
                if (ok && res && res.success) {
                  setTimeout(function() { location.reload(); }, 400);
                } else {
                  me.disabled = false;
                }
              });
            }
          });
        });
      });
    }
  });
})();

/* ── admin/navigation/index.php: new-menu modal + create-menu AJAX ── */
(function () {
  'use strict';

  var newMenuBtn = document.getElementById('new-menu-btn');
  if (!newMenuBtn) return;

  var _newMenuModal = null;
  function getNewMenuModal() {
    if (!_newMenuModal) _newMenuModal = Phuse.modal(document.getElementById('new-menu-modal'));
    return _newMenuModal;
  }

  newMenuBtn.addEventListener('click', function() {
    getNewMenuModal().show();
  });

  document.getElementById('new-menu-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('new-menu-submit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';

    window.VtxAjax.postForm(this.action, this, function(ok, res) {
      if (ok && res && res.success) {
        window.Phuse.toast(res.message || 'Menu created.', 'success');
        getNewMenuModal().hide();
        setTimeout(function() { location.reload(); }, 500);
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="pi pi-plus me-1"></i>Create Menu';
        window.Phuse.toast((res && res.message) || 'Failed to create menu.', 'error');
      }
    });
  });
})();
