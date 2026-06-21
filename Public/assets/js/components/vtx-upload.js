/* vtx-upload.js — Drag-and-drop file upload zone for Vertext CMS */
(function (root) {
    'use strict';

    /**
     * VtxUpload
     *
     * Mount on an element with:
     *   data-vtx-upload
     *   data-url="/admin/media/upload"   (required)
     *   data-csrf="TOKEN"               (optional, reads hidden input if absent)
     *   data-accept="image/*"           (optional MIME filter)
     *   data-max-mb="10"               (optional max file size in MB)
     *
     * Fires CustomEvent 'vtx:upload:done' on the element with detail {url, id, filename}.
     * Fires CustomEvent 'vtx:upload:error' with detail {message}.
     */
    function VtxUpload(opts) {
        this.el      = opts.el;
        this.url     = this.el.dataset.url     || '';
        this.accept  = this.el.dataset.accept  || 'image/*';
        this.maxMb   = parseFloat(this.el.dataset.maxMb || '20');
        this._bind();
        if (window.Vtx) Vtx._register('upload', this);
    }

    VtxUpload.prototype._getCsrf = function () {
        var d = this.el.dataset.csrf;
        if (d) return d;
        var inp = document.querySelector('input[name=csrf_token]');
        return inp ? inp.value : '';
    };

    VtxUpload.prototype._bind = function () {
        var self  = this;
        var el    = this.el;

        // File input (hidden)
        this.fileInput        = document.createElement('input');
        this.fileInput.type   = 'file';
        this.fileInput.accept = this.accept;
        this.fileInput.style.display = 'none';
        el.appendChild(this.fileInput);

        el.addEventListener('click', function (e) {
            if (e.target.closest('[data-vtx-media-picker]')) return;
            self.fileInput.click();
        });

        this.fileInput.addEventListener('change', function () {
            if (self.fileInput.files.length) self._upload(self.fileInput.files[0]);
            self.fileInput.value = '';
        });

        // Drag-and-drop
        el.addEventListener('dragover', function (e) {
            e.preventDefault();
            el.classList.add('vtx-upload-drag-over');
        });
        el.addEventListener('dragleave', function () {
            el.classList.remove('vtx-upload-drag-over');
        });
        el.addEventListener('drop', function (e) {
            e.preventDefault();
            el.classList.remove('vtx-upload-drag-over');
            var files = e.dataTransfer.files;
            if (files.length) self._upload(files[0]);
        });
    };

    VtxUpload.prototype._upload = function (file) {
        var self = this;

        if (this.maxMb && file.size > this.maxMb * 1024 * 1024) {
            this._emit('error', { message: 'File is too large. Maximum is ' + this.maxMb + ' MB.' });
            if (typeof Phuse !== 'undefined') Phuse.toast('File too large (max ' + this.maxMb + ' MB).', 'error');
            return;
        }

        var fd = new FormData();
        fd.append('file', file);
        fd.append('csrf_token', this._getCsrf());

        this._setLoading(true);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', this.url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                self._setProgress(Math.round((e.loaded / e.total) * 100));
            }
        });

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            self._setLoading(false);
            var res;
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = {}; }
            if (xhr.status >= 200 && xhr.status < 300 && res && res.success) {
                self._emit('done', { url: res.url, id: res.id, filename: res.filename });
                if (typeof Phuse !== 'undefined') Phuse.toast(res.message || 'Uploaded.', 'success');
            } else {
                var msg = (res && res.message) ? res.message : 'Upload failed.';
                self._emit('error', { message: msg });
                if (typeof Phuse !== 'undefined') Phuse.toast(msg, 'error');
            }
        };

        xhr.send(fd);
    };

    VtxUpload.prototype._setLoading = function (on) {
        var overlay = this.el.querySelector('.vtx-upload-overlay');
        if (!overlay && on) {
            overlay = document.createElement('div');
            overlay.className = 'vtx-upload-overlay';
            overlay.style.cssText = 'position:absolute;inset:0;background:rgba(var(--ps-bg-surface-rgb,255,255,255),.8);' +
                'display:flex;align-items:center;justify-content:center;z-index:10;border-radius:inherit;';
            overlay.innerHTML = '<span style="font-size:.875rem;color:var(--ps-text-muted);">Uploading…</span>' +
                '<div class="vtx-upload-bar" style="position:absolute;bottom:0;left:0;height:3px;' +
                'background:var(--ps-primary);width:0;transition:width .2s;border-radius:0 0 inherit inherit;"></div>';
            var pos = getComputedStyle(this.el).position;
            if (pos === 'static') this.el.style.position = 'relative';
            this.el.appendChild(overlay);
        }
        if (overlay && !on) overlay.remove();
    };

    VtxUpload.prototype._setProgress = function (pct) {
        var bar = this.el.querySelector('.vtx-upload-bar');
        if (bar) bar.style.width = pct + '%';
    };

    VtxUpload.prototype._emit = function (name, detail) {
        this.el.dispatchEvent(new CustomEvent('vtx:upload:' + name, { bubbles: true, detail: detail }));
    };

    root.VtxUpload = VtxUpload;

    // Auto-init
    document.querySelectorAll('[data-vtx-upload]').forEach(function (el) {
        if (!el._vtxUpload) el._vtxUpload = new VtxUpload({ el: el });
    });

}(window));
