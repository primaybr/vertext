/* vtx-media-picker.js — Media library picker for Vertext CMS */
(function (root) {
    'use strict';

    /**
     * VtxMediaPicker
     *
     * Opens the media library in the CRUD modal.
     * The picker partial calls window.__vtxMediaPickerCallback(url, id) on selection.
     *
     * Mount on a <button> with:
     *   data-vtx-media-picker
     *   data-target-id-input="inputId"        hidden input id for the image id
     *   data-target-url-input="inputId"       hidden input id for the image url
     *   data-target-preview="imgId"           optional <img> id for preview
     *   data-target-preview-wrap="wrapId"     optional wrapper to show/hide
     *
     * @param {object} opts  { btn: Element }
     *
     * Static API:
     *   VtxMediaPicker.open(function(url, id) { ... })
     *   — opens the picker without a button; calls cb on selection.
     */

    // ── Shared overlay builder ────────────────────────────────────────────────
    function openPickerOverlay(url) {
        var existing = document.getElementById('vtx-media-picker-overlay');
        if (existing) existing.remove();

        var overlay = document.createElement('div');
        overlay.id  = 'vtx-media-picker-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.5);' +
            'display:flex;align-items:center;justify-content:center;padding:1rem;';

        var panel = document.createElement('div');
        panel.style.cssText = 'background:var(--ps-bg-surface);border-radius:var(--ps-radius-lg,8px);' +
            'box-shadow:0 8px 40px rgba(0,0,0,.25);width:min(860px,100%);max-height:85vh;' +
            'display:flex;flex-direction:column;overflow:hidden;';

        var header = document.createElement('div');
        header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;' +
            'padding:.875rem 1rem;border-bottom:1px solid var(--ps-border);';
        header.innerHTML = '<span style="font-weight:600;font-size:.9375rem;">Media Library</span>';

        var closeBtn = document.createElement('button');
        closeBtn.type      = 'button';
        closeBtn.innerHTML = '<i class="pi pi-x"></i>';
        closeBtn.style.cssText = 'background:none;border:none;cursor:pointer;font-size:1rem;' +
            'color:var(--ps-text-muted);padding:.25rem;line-height:1;';
        closeBtn.addEventListener('click', function () { overlay.remove(); });

        var body = document.createElement('div');
        body.id            = 'vtx-picker-panel-body';
        body.style.cssText = 'flex:1;overflow-y:auto;padding:1rem;';
        body.innerHTML     = '<div style="text-align:center;padding:2rem;color:var(--ps-text-muted);">Loading…</div>';

        header.appendChild(closeBtn);
        panel.appendChild(header);
        panel.appendChild(body);
        overlay.appendChild(panel);
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.remove();
        });

        VtxAjax.get(url, function (ok, html) {
            if (!ok) {
                body.innerHTML = '<p style="color:var(--ps-danger);padding:1rem;">Failed to load media library.</p>';
                return;
            }
            body.innerHTML = html;
            body.querySelectorAll('script').forEach(function (old) {
                var s = document.createElement('script');
                s.textContent = old.textContent;
                old.parentNode.replaceChild(s, old);
            });

            body.addEventListener('click', function (e) {
                var link = e.target.closest('a[data-picker-page]');
                if (!link) return;
                e.preventDefault();
                body.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--ps-text-muted);">Loading…</div>';
                VtxAjax.get(link.href, function (ok2, html2) {
                    if (!ok2) return;
                    body.innerHTML = html2;
                    body.querySelectorAll('script').forEach(function (old) {
                        var s = document.createElement('script');
                        s.textContent = old.textContent;
                        old.parentNode.replaceChild(s, old);
                    });
                });
            });
        });
    }

    // ── Instance (button-driven) ──────────────────────────────────────────────
    function VtxMediaPicker(opts) {
        this.btn = opts.btn;
        this._bind();
        if (window.Vtx) Vtx._register('media-picker', this);
    }

    VtxMediaPicker.prototype._bind = function () {
        var self = this;
        var btn  = this.btn;

        btn.addEventListener('click', function (e) {
            e.preventDefault();

            var pickerUrl = (window.VTX_BASE_URL || '') + '/admin/media/picker';

            window.__vtxMediaPickerCallback = function (url, id) {
                self._onSelect(url, id);
                var pickerOverlay = document.getElementById('vtx-media-picker-overlay');
                if (pickerOverlay) pickerOverlay.remove();
                window.__vtxMediaPickerCallback = null;
            };

            openPickerOverlay(pickerUrl);
        });
    };

    VtxMediaPicker.prototype._onSelect = function (url, id) {
        var btn  = this.btn;

        var idInput     = document.getElementById(btn.dataset.targetIdInput     || '');
        var urlInput    = document.getElementById(btn.dataset.targetUrlInput    || '');
        var previewImg  = document.getElementById(btn.dataset.targetPreview     || '');
        var previewWrap = document.getElementById(btn.dataset.targetPreviewWrap || '');

        if (idInput)  idInput.value  = id  || '';
        if (urlInput) urlInput.value = url || '';

        if (previewImg && url) {
            previewImg.src = url;
            if (previewWrap) previewWrap.style.display = '';
        }

        btn.innerHTML = '<i class="pi pi-image" style="margin-right:.25rem;"></i>Change Image';
    };

    // ── Static: programmatic open (used by VtxEditor image handler) ──────────
    VtxMediaPicker.open = function (onSelectFn) {
        var pickerUrl = (window.VTX_BASE_URL || '') + '/admin/media/picker';

        window.__vtxMediaPickerCallback = function (url, id) {
            onSelectFn(url, id);
            var overlay = document.getElementById('vtx-media-picker-overlay');
            if (overlay) overlay.remove();
            window.__vtxMediaPickerCallback = null;
        };

        openPickerOverlay(pickerUrl);
    };

    root.VtxMediaPicker = VtxMediaPicker;

    // Auto-init buttons already in the DOM
    document.querySelectorAll('[data-vtx-media-picker]').forEach(function (btn) {
        if (!btn._vtxMediaPicker) btn._vtxMediaPicker = new VtxMediaPicker({ btn: btn });
    });

}(window));
