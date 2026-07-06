/* Media Module - Admin JS: upload toggle + grid reload */
(function () {
    var uploadBtn = document.getElementById('vtx-media-upload-btn');
    if (!uploadBtn) return; // Not on the media library page

    uploadBtn.addEventListener('click', function () {
        var zone = document.getElementById('vtx-upload-zone');
        if (zone) zone.style.display = zone.style.display === 'none' ? '' : 'none';
    });

    // Reload grid after upload - guard so this only fires on the media page
    document.addEventListener('vtx:upload:done', function () {
        if (document.getElementById('vtx-media-grid')) location.reload();
    });
}());

/* ── admin/media/index.php: bulk-select toolbar (select-all, bulk delete/move) ── */
(function () {
    var bar      = document.getElementById('vtx-media-bulk-bar');
    var allChk   = document.getElementById('vtx-media-select-all');
    var countLbl = document.getElementById('vtx-media-bulk-count');
    var delBtn   = document.getElementById('vtx-media-bulk-delete');
    var grid     = document.getElementById('vtx-media-grid');
    var config   = document.getElementById('vtx-media-config');
    if (!bar || !grid || !config) return;

    var baseUrl = config.dataset.baseUrl;
    var csrf    = config.dataset.csrf;

    function getChecked() {
        return Array.from(grid.querySelectorAll('.vtx-media-card-check:checked'));
    }
    function sync() {
        var checked = getChecked();
        var n = checked.length;
        bar.style.display = n > 0 ? '' : 'none';
        if (countLbl) countLbl.textContent = n + ' selected';
        var all = grid.querySelectorAll('.vtx-media-card-check');
        if (allChk) allChk.checked = all.length > 0 && n === all.length;
        grid.querySelectorAll('.vtx-media-card').forEach(function (card) {
            var chk = card.querySelector('.vtx-media-card-check');
            if (chk) card.classList.toggle('vtx-media-selected', chk.checked);
        });
    }

    grid.addEventListener('change', function (e) {
        if (e.target.classList.contains('vtx-media-card-check')) sync();
    });
    if (allChk) {
        allChk.addEventListener('change', function () {
            grid.querySelectorAll('.vtx-media-card-check').forEach(function (c) {
                c.checked = allChk.checked;
            });
            sync();
        });
    }
    var moveBtn = document.getElementById('vtx-media-bulk-move');
    if (moveBtn) {
        moveBtn.addEventListener('click', function () {
            var ids    = getChecked().map(function (c) { return c.value; });
            var target = document.getElementById('vtx-media-move-target').value;
            if (!ids.length) return;
            if (!target) { Phuse.toast('Choose a destination folder first.', 'error'); return; }
            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('bulk_action', 'move');
            fd.append('folder_id', target);
            ids.forEach(function (id) { fd.append('ids[]', id); });
            fetch(baseUrl + '/admin/media/bulk', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    Phuse.toast(res.message || (res.success ? 'Moved.' : 'Failed.'), res.success ? 'success' : 'error');
                    if (res.success) setTimeout(function () { window.location.reload(); }, 800);
                })
                .catch(function () { Phuse.toast('Network error.', 'error'); });
        });
    }

    if (delBtn) {
        delBtn.addEventListener('click', function () {
            var ids = getChecked().map(function (c) { return c.value; });
            if (!ids.length) return;
            vtxConfirmModal({
                title: 'Delete ' + ids.length + ' file' + (ids.length > 1 ? 's' : ''),
                message: 'This will permanently delete the selected file' + (ids.length > 1 ? 's' : '') + '. This cannot be undone.',
                confirmLabel: 'Delete',
                confirmClass: 'btn-danger',
                onConfirm: function () {
                    var form = document.getElementById('vtx-media-bulk-form');
                    document.getElementById('vtx-media-bulk-action').value = 'delete';
                    ids.forEach(function (id) {
                        var inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'ids[]';
                        inp.value = id;
                        form.appendChild(inp);
                    });
                    VtxAjax.postForm(baseUrl + '/admin/media/bulk', form, function (res) {
                        if (res.success) {
                            Phuse.toast(res.message, 'success');
                            setTimeout(function () { window.location.reload(); }, 800);
                        } else {
                            Phuse.toast(res.message || 'Failed.', 'error');
                        }
                    });
                }
            });
        });
    }
}());

/* ── admin/media/index.php: "Regenerate thumbnails" button ── */
(function () {
    var btn    = document.getElementById('vtx-regen-thumbs-btn');
    var config = document.getElementById('vtx-media-config');
    if (!btn || !config) return;
    var baseUrl = config.dataset.baseUrl;
    var csrf    = config.dataset.csrf;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.innerHTML = '<i class="pi pi-spin pi-refresh me-1"></i> Generating…';
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fetch(baseUrl + '/admin/media/regen-thumbnails', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    if (d.remaining > 0) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="pi pi-refresh me-1"></i> Regenerate Thumbnails <span style="background:var(--ps-warning);color:#000;font-size:.7rem;padding:.15rem .4rem;border-radius:999px;margin-left:.25rem;">' + d.remaining + '</span>';
                    } else {
                        btn.style.display = 'none';
                    }
                    Phuse.toast(d.message, 'success');
                    // Reload grid to show new thumbnails
                    if (d.processed > 0) setTimeout(function () { window.location.reload(); }, 1200);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="pi pi-refresh me-1"></i> Regenerate Thumbnails';
                    Phuse.toast(d.message || 'Failed.', 'error');
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="pi pi-refresh me-1"></i> Regenerate Thumbnails';
            });
    });
}());

/* ── admin/media/index.php: upload flow (folder create/rename/delete) ── */
(function () {
    'use strict';
    var config = document.getElementById('vtx-media-config');
    if (!config) return;
    var baseUrl = config.dataset.baseUrl;
    var csrf    = config.dataset.csrf;

    function post(url, fields, done) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(fields || {}).forEach(function (k) { fd.append(k, fields[k]); });
        fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) { done(res); })
            .catch(function () { done({ success: false, message: 'Network error.' }); });
    }

    var newBtn = document.getElementById('vtx-folder-new');
    if (newBtn) {
        newBtn.addEventListener('click', function () {
            window.vtxPromptModal({
                title: 'New Folder',
                message: 'Folder name:',
                placeholder: 'Folder name',
                confirmLabel: 'Create',
                onConfirm: function (name) {
                    post(baseUrl + '/admin/media/folders/store', { name: name }, function (res) {
                        Phuse.toast(res.message || '', res.success ? 'success' : 'error');
                        if (res.success) setTimeout(function () { window.location = baseUrl + '/admin/media?folder=' + res.id; }, 600);
                    });
                }
            });
        });
    }

    var renameBtn = document.getElementById('vtx-folder-rename');
    if (renameBtn) {
        renameBtn.addEventListener('click', function () {
            window.vtxPromptModal({
                title: 'Rename Folder',
                message: 'Folder name:',
                value: renameBtn.dataset.folderName,
                confirmLabel: 'Rename',
                onConfirm: function (name) {
                    if (name === renameBtn.dataset.folderName) return;
                    post(baseUrl + '/admin/media/folders/' + renameBtn.dataset.folderId + '/rename', { name: name }, function (res) {
                        Phuse.toast(res.message || '', res.success ? 'success' : 'error');
                        if (res.success) setTimeout(function () { window.location.reload(); }, 600);
                    });
                }
            });
        });
    }

    var deleteBtn = document.getElementById('vtx-folder-delete');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            vtxConfirmModal({
                title: 'Delete Folder',
                message: 'Delete "' + deleteBtn.dataset.folderName + '"? Files inside are kept and become Unfiled.',
                confirmLabel: 'Delete',
                confirmClass: 'btn-danger',
                onConfirm: function () {
                    post(baseUrl + '/admin/media/folders/' + deleteBtn.dataset.folderId + '/delete', {}, function (res) {
                        Phuse.toast(res.message || '', res.success ? 'success' : 'error');
                        if (res.success) setTimeout(function () { window.location = baseUrl + '/admin/media'; }, 700);
                    });
                }
            });
        });
    }
}());

/* ── admin/media/index.php: canvas-based inline image editor (crop/rotate/flip/save) ── */
(function () {
    'use strict';
    var modal  = document.getElementById('vtx-imged-modal');
    var canvas = document.getElementById('vtx-imged-canvas');
    var config = document.getElementById('vtx-media-config');
    if (!modal || !canvas || !config) return;
    var baseUrl = config.dataset.baseUrl;
    var csrf    = config.dataset.csrf;

    var ctx   = canvas.getContext('2d');
    var state = null; // {id, img, ops[], crop{x,y,w,h}|null, dragStart}

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-image-editor]');
        if (!btn) return;
        var img = new Image();
        img.onload = function () {
            state = { id: btn.dataset.imageEditor, img: img, ops: [], crop: null, dragStart: null };
            draw();
            modal.style.display = 'flex';
        };
        img.onerror = function () { Phuse.toast('Could not load the image.', 'error'); };
        img.src = btn.dataset.imageUrl + (btn.dataset.imageUrl.indexOf('?') === -1 ? '?' : '&') + 'v=' + Date.now();
    });

    // Render the source image through the pending ops so the preview matches
    // what the server will produce.
    function draw() {
        if (!state) return;

        var off = document.createElement('canvas');
        off.width = state.img.naturalWidth; off.height = state.img.naturalHeight;
        off.getContext('2d').drawImage(state.img, 0, 0);

        state.ops.forEach(function (op) {
            var src = off;
            if (op.op === 'rotate') {
                var nw = src.height, nh = src.width;
                var next = document.createElement('canvas');
                next.width = nw; next.height = nh;
                var nctx = next.getContext('2d');
                nctx.translate(nw / 2, nh / 2);
                nctx.rotate(op.deg * Math.PI / 180);
                nctx.drawImage(src, -src.width / 2, -src.height / 2);
                off = next;
            } else if (op.op === 'flip') {
                var next2 = document.createElement('canvas');
                next2.width = src.width; next2.height = src.height;
                var n2 = next2.getContext('2d');
                if (op.dir === 'h') { n2.translate(src.width, 0); n2.scale(-1, 1); }
                else { n2.translate(0, src.height); n2.scale(1, -1); }
                n2.drawImage(src, 0, 0);
                off = next2;
            }
        });

        state.rendered = off; // post-op pixels; crop coords refer to THIS

        var scale = Math.min(1, 700 / off.width, 480 / off.height);
        canvas.width  = Math.round(off.width * scale);
        canvas.height = Math.round(off.height * scale);
        state.scale   = scale;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(off, 0, 0, canvas.width, canvas.height);

        if (state.crop) {
            var c = state.crop;
            ctx.save();
            ctx.fillStyle = 'rgba(0,0,0,.45)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(off, c.x, c.y, c.w, c.h, c.x * scale, c.y * scale, c.w * scale, c.h * scale);
            ctx.strokeStyle = '#4f46e5';
            ctx.lineWidth = 2;
            ctx.strokeRect(c.x * scale, c.y * scale, c.w * scale, c.h * scale);
            ctx.restore();
        }
    }

    canvas.addEventListener('mousedown', function (e) {
        if (!state) return;
        var r = canvas.getBoundingClientRect();
        var cssScale = canvas.width / r.width; // CSS max-width shrink factor
        state.dragStart = { x: (e.clientX - r.left) * cssScale / state.scale, y: (e.clientY - r.top) * cssScale / state.scale };
    });
    canvas.addEventListener('mousemove', function (e) {
        if (!state || !state.dragStart) return;
        var r = canvas.getBoundingClientRect();
        var cssScale = canvas.width / r.width;
        var cur = { x: (e.clientX - r.left) * cssScale / state.scale, y: (e.clientY - r.top) * cssScale / state.scale };
        state.crop = {
            x: Math.round(Math.min(state.dragStart.x, cur.x)),
            y: Math.round(Math.min(state.dragStart.y, cur.y)),
            w: Math.round(Math.abs(cur.x - state.dragStart.x)),
            h: Math.round(Math.abs(cur.y - state.dragStart.y))
        };
        draw();
    });
    window.addEventListener('mouseup', function () { if (state) state.dragStart = null; });

    document.querySelectorAll('[data-imged-op]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!state) return;
            switch (btn.dataset.imgedOp) {
                case 'rotate-left':  state.ops.push({ op: 'rotate', deg: -90 }); state.crop = null; break;
                case 'rotate-right': state.ops.push({ op: 'rotate', deg: 90 });  state.crop = null; break;
                case 'flip-h':       state.ops.push({ op: 'flip', dir: 'h' });   state.crop = null; break;
                case 'flip-v':       state.ops.push({ op: 'flip', dir: 'v' });   state.crop = null; break;
                case 'crop-clear':   state.crop = null; break;
            }
            draw();
        });
    });

    function save(mode) {
        if (!state) return;
        var ops = state.ops.slice();
        if (state.crop && state.crop.w > 9 && state.crop.h > 9) {
            ops.push({ op: 'crop', x: state.crop.x, y: state.crop.y, w: state.crop.w, h: state.crop.h });
        }
        if (!ops.length) { Phuse.toast('No changes to save.', 'error'); return; }

        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('ops', JSON.stringify(ops));
        fd.append('mode', mode);
        fetch(baseUrl + '/admin/media/' + state.id + '/edit-image', {
            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            Phuse.toast(res.message || (res.success ? 'Saved.' : 'Failed.'), res.success ? 'success' : 'error');
            if (res.success) { modal.style.display = 'none'; setTimeout(function () { window.location.reload(); }, 900); }
        })
        .catch(function () { Phuse.toast('Network error.', 'error'); });
    }

    document.getElementById('vtx-imged-save-copy').addEventListener('click', function () { save('copy'); });
    document.getElementById('vtx-imged-overwrite').addEventListener('click', function () {
        vtxConfirmModal({
            title: 'Overwrite Original',
            message: 'Replace the original file with the edited version? This cannot be undone.',
            confirmLabel: 'Overwrite',
            confirmClass: 'btn-danger',
            onConfirm: function () { save('overwrite'); }
        });
    });
}());
