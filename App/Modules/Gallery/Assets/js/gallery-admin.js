/* ── admin/galleries/_form.php: media-picker wiring + cover-image remove ── */
/* _form.php is loaded into the AJAX modal, so init re-runs each time via the
   'vtx:modal:loaded' event admin.js dispatches after injecting the form HTML. */
document.addEventListener('vtx:modal:loaded', function (e) {
    var body = e.detail.body;
    if (!body.querySelector('#gallery-cover-id')) return;

    Vtx.load(['slug', 'media-picker'], function () {
        if (window.vtxSlug) window.vtxSlug.init();
        var pickerBtn = body.querySelector('[data-vtx-media-picker]');
        if (pickerBtn && window.VtxMediaPicker) new VtxMediaPicker({ btn: pickerBtn });
    });

    var removeBtn = body.querySelector('#gallery-cover-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            body.querySelector('#gallery-cover-id').value = '';
            body.querySelector('#gallery-cover-url-hidden').value = '';
            var wrap = body.querySelector('#gallery-cover-preview');
            if (wrap) wrap.style.display = 'none';
        });
    }
});

/* ── admin/galleries/items.php: media-picker overlay + drag reorder ── */
(function () {
    var galleryIdEl = document.getElementById('vtx-gallery-id');
    if (!galleryIdEl) return;

    var galleryId = galleryIdEl.value;
    var csrfEl    = document.getElementById('vtx-gallery-csrf');
    var csrf      = csrfEl ? csrfEl.value : '';
    var grid      = document.getElementById('vtx-gallery-grid');
    var pageRoot  = document.getElementById('vtx-gallery-page');
    var baseUrl   = pageRoot ? pageRoot.dataset.baseUrl : '';

    if (!grid) return;

    // ── Add button → multi-select media picker ──────────────────────────────
    var addBtn = document.getElementById('vtx-gallery-add-btn');
    if (addBtn) {
        addBtn.addEventListener('click', openGalleryPicker);
    }

    var _sel = {};  // id → true for currently selected items

    function openGalleryPicker() {
        _sel = {};
        // Null out any stale single-select callback from other pickers on this page
        window.__vtxMediaPickerCallback = null;

        var existing = document.getElementById('vtx-media-picker-overlay');
        if (existing) existing.remove();

        var overlay = document.createElement('div');
        overlay.id  = 'vtx-media-picker-overlay';

        var panel = document.createElement('div');
        panel.className = 'vtx-media-picker-panel';

        var header = document.createElement('div');
        header.className = 'vtx-media-picker-header';

        var title = document.createElement('span');
        title.className   = 'vtx-media-picker-title';
        title.textContent = 'Add Images';

        var actions = document.createElement('div');
        actions.style.cssText = 'display:flex;align-items:center;gap:.5rem;';

        var doneBtn = document.createElement('button');
        doneBtn.type          = 'button';
        doneBtn.className     = 'btn btn-sm btn-primary';
        doneBtn.style.display = 'none';
        doneBtn.textContent   = 'Add Selected (0)';

        var closeBtn = document.createElement('button');
        closeBtn.type      = 'button';
        closeBtn.className = 'vtx-media-picker-close';
        closeBtn.innerHTML = '<i class="pi pi-x"></i>';
        closeBtn.addEventListener('click', closeOverlay);

        actions.appendChild(doneBtn);
        actions.appendChild(closeBtn);
        header.appendChild(title);
        header.appendChild(actions);

        // id="vtx-picker-panel-body" required by picker.php's pickerBody()
        var pickerBody = document.createElement('div');
        pickerBody.id = 'vtx-picker-panel-body';
        pickerBody.innerHTML = '<div class="vtx-picker-loading">Loading…</div>';

        panel.appendChild(header);
        panel.appendChild(pickerBody);
        overlay.appendChild(panel);
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeOverlay();
        });

        function closeOverlay() {
            overlay.remove();
        }

        // Event delegation on pickerBody — works even after search/pagination reloads
        // because the element reference is stable; only its innerHTML changes
        pickerBody.addEventListener('click', function (e) {
            var item = e.target.closest('.vtx-picker-item');
            if (!item) return;
            var id = item.dataset.id;
            if (!id) return;
            if (_sel[id]) {
                delete _sel[id];
            } else {
                _sel[id] = true;
            }
            var count = Object.keys(_sel).length;
            doneBtn.textContent   = 'Add Selected (' + count + ')';
            doneBtn.style.display = count > 0 ? '' : 'none';
            item.classList.toggle('selected', !!_sel[id]);
        });

        // Load picker HTML (renderPartial returns bare HTML, no layout)
        VtxAjax.get(baseUrl + '/admin/media/picker', function (ok, html) {
            if (!ok) {
                pickerBody.innerHTML = '<p class="vtx-picker-error">Failed to load media library.</p>';
                return;
            }
            pickerBody.innerHTML = html;
            // Re-execute injected scripts for search/pagination/upload
            pickerBody.querySelectorAll('script').forEach(function (old) {
                var s = document.createElement('script');
                s.textContent = old.textContent;
                old.parentNode.replaceChild(s, old);
            });
        });

        // Done: fire all POSTs in parallel, insert tiles into grid via AJAX (no reload)
        doneBtn.addEventListener('click', function () {
            var ids = Object.keys(_sel);
            if (!ids.length) return;
            doneBtn.disabled    = true;
            doneBtn.textContent = 'Adding…';
            var remaining = ids.length;
            var added = 0;
            function finish(data) {
                if (data && data.item_id) {
                    added++;
                    appendGridItem(data);
                }
                if (--remaining <= 0) {
                    closeOverlay();
                    if (added > 0) {
                        var empty = document.getElementById('vtx-gallery-empty');
                        if (empty) empty.style.display = 'none';
                        window.Phuse && window.Phuse.toast(
                            added + (added === 1 ? ' image' : ' images') + ' added.', 'success'
                        );
                    }
                }
            }
            ids.forEach(function (id) {
                postAddImage(id).then(finish, function () { finish(null); });
            });
        });
    }

    function postAddImage(mediaId) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('media_file_id', mediaId);
        return fetch(baseUrl + '/admin/gallery/' + galleryId + '/items/add', {
            method: 'POST',
            body: fd
        }).then(function (r) {
            var status = r.status;
            return r.text().then(function (text) {
                try {
                    var d = JSON.parse(text);
                    if (!d.success) {
                        if (d.message !== 'Image already in this album.') {
                            window.Phuse && window.Phuse.toast(d.message || 'Add failed.', 'error');
                        }
                        return null;
                    }
                    return d;
                } catch (e) {
                    window.Phuse && window.Phuse.toast(
                        'Server error (' + status + '): ' + text.substring(0, 100), 'error'
                    );
                    return null;
                }
            });
        });
    }

    function appendGridItem(data) {
        var div = document.createElement('div');
        div.className = 'vtx-gallery-item';
        div.dataset.id = data.item_id;
        div.style.cssText = 'position:relative;border-radius:8px;overflow:hidden;background:var(--ps-bg-alt);aspect-ratio:1;cursor:grab;';
        var img = document.createElement('img');
        img.src   = data.thumbnail_url;
        img.alt   = data.name || '';
        img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
        var removeBtn = document.createElement('button');
        removeBtn.type      = 'button';
        removeBtn.className = 'vtx-gallery-remove';
        removeBtn.dataset.id = data.item_id;
        removeBtn.style.cssText = 'position:absolute;top:.375rem;right:.375rem;background:rgba(0,0,0,.65);color:#fff;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:.75rem;display:flex;align-items:center;justify-content:center;';
        removeBtn.title       = 'Remove';
        removeBtn.textContent = '×';
        var handle = document.createElement('div');
        handle.className  = 'drag-handle';
        handle.style.cssText = 'position:absolute;bottom:.375rem;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.5);color:#fff;border-radius:4px;padding:.125rem .375rem;font-size:.7rem;cursor:grab;';
        handle.textContent = '⠇';
        div.appendChild(img);
        div.appendChild(removeBtn);
        div.appendChild(handle);
        makeDraggable(div);
        grid.appendChild(div);
    }

    // ── Remove buttons ──────────────────────────────────────────────────────
    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('.vtx-gallery-remove');
        if (!btn) return;
        var itemId = btn.dataset.id;
        window.vtxConfirmModal({
            title: 'Remove Image',
            message: 'Remove this image from the album?',
            confirmLabel: 'Remove',
            confirmClass: 'btn-danger',
            onConfirm: function () {
                var fd = new FormData();
                fd.append('csrf_token', csrf);
                fetch(baseUrl + '/admin/gallery/' + galleryId + '/items/' + itemId + '/remove', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.success) {
                            btn.closest('.vtx-gallery-item').remove();
                            if (!grid.querySelector('.vtx-gallery-item')) {
                                var empty = document.getElementById('vtx-gallery-empty');
                                if (empty) empty.style.display = '';
                            }
                        } else {
                            window.Phuse && window.Phuse.toast(d.message || 'Remove failed.', 'error');
                        }
                    });
            }
        });
    });

    // ── Drag-to-reorder ────────────────────────────────────────────────────
    var dragging = null;
    Array.from(grid.querySelectorAll('.vtx-gallery-item')).forEach(function (el) { makeDraggable(el); });

    function makeDraggable(el) {
        el.draggable = true;
        el.addEventListener('dragstart', function () {
            dragging = el;
            setTimeout(function () { el.style.opacity = '.4'; }, 0);
        });
        el.addEventListener('dragend', function () {
            el.style.opacity = '';
            dragging = null;
            saveOrder();
        });
        el.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (dragging && dragging !== el) {
                var r = el.getBoundingClientRect();
                if (e.clientX < r.left + r.width / 2) {
                    grid.insertBefore(dragging, el);
                } else {
                    grid.insertBefore(dragging, el.nextSibling);
                }
            }
        });
    }

    function saveOrder() {
        var order = Array.from(grid.querySelectorAll('.vtx-gallery-item')).map(function (el, i) {
            return { id: el.dataset.id, sort_order: i };
        });
        fetch(baseUrl + '/admin/gallery/' + galleryId + '/items/reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(order)
        });
    }
}());
