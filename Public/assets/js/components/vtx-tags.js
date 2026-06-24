/* vtx-tags.js - Tag chip input with AJAX autocomplete for Vertext CMS */
(function (root) {
    'use strict';

    /**
     * VtxTags
     *
     * Mount on a container div that has:
     *   data-vtx-tags
     *   data-ajax-url="..."   (optional AJAX search endpoint)
     *   data-value="tag1, tag2"  (initial comma-separated tags)
     *
     * Place a <input type="hidden" name="tag_names"> inside the container.
     */
    function VtxTags(opts) {
        if (opts.el._vtxTags) return; // already initialized
        this.el      = opts.el;
        this.el._vtxTags = this;
        this.hidden  = this.el.querySelector('input[type=hidden]');
        this.ajaxUrl = this.el.dataset.ajaxUrl || '';
        this._tags   = [];
        this._debounceTimer = null;
        this._build();
        this._loadInitial();
        Vtx._register('tags', this);
    }

    VtxTags.prototype._build = function () {
        var self = this;

        this.wrap = document.createElement('div');
        this.wrap.className = 'vtx-tags-wrap';
        this.wrap.style.cssText = 'display:flex;flex-wrap:wrap;gap:.375rem;align-items:center;' +
            'padding:.375rem .5rem;border:1px solid var(--ps-border);border-radius:var(--ps-radius);' +
            'background:var(--ps-bg-input);min-height:38px;cursor:text;';

        this.inputEl = document.createElement('input');
        this.inputEl.className      = 'vtx-tags-input';
        this.inputEl.type           = 'text';
        this.inputEl.placeholder    = 'Add tags…';
        this.inputEl.autocomplete   = 'off';
        this.inputEl.style.cssText  = 'border:none;outline:none;background:transparent;flex:1;min-width:80px;' +
            'font-size:.875rem;color:var(--ps-text-primary);padding:0;';

        this.dropdown = document.createElement('div');
        this.dropdown.className     = 'vtx-tags-dropdown';
        this.dropdown.style.cssText = 'position:absolute;z-index:9999;background:var(--ps-bg-surface);' +
            'border:1px solid var(--ps-border);border-radius:var(--ps-radius);box-shadow:0 4px 16px rgba(0,0,0,.12);' +
            'min-width:200px;max-height:180px;overflow-y:auto;display:none;';

        this.wrap.appendChild(this.inputEl);
        this.el.style.position = 'relative';
        this.el.appendChild(this.wrap);
        this.el.appendChild(this.dropdown);

        // Click wrap → focus input
        this.wrap.addEventListener('click', function () { self.inputEl.focus(); });

        this.inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                self._addFromInput();
            } else if (e.key === 'Backspace' && !self.inputEl.value && self._tags.length) {
                self._remove(self._tags[self._tags.length - 1]);
            }
        });

        this.inputEl.addEventListener('input', function () {
            clearTimeout(self._debounceTimer);
            var q = self.inputEl.value.trim();
            if (!q || !self.ajaxUrl) { self._closeDropdown(); return; }
            self._debounceTimer = setTimeout(function () { self._search(q); }, 220);
        });

        this.inputEl.addEventListener('blur', function () {
            setTimeout(function () { self._closeDropdown(); }, 200);
            if (self.inputEl.value.trim()) self._addFromInput();
        });
    };

    VtxTags.prototype._loadInitial = function () {
        var val = (this.el.dataset.value || '').trim();
        if (!val) return;
        var self = this;
        val.split(',').forEach(function (t) {
            var clean = t.trim();
            if (clean) self._add(clean, false);
        });
        this._sync();
    };

    VtxTags.prototype._add = function (name, sync) {
        if (!name || this._tags.indexOf(name) !== -1) return;
        this._tags.push(name);

        var chip = document.createElement('span');
        chip.className  = 'vtx-tag-chip';
        chip.style.cssText = 'display:inline-flex;align-items:center;gap:.25rem;padding:.125rem .5rem;' +
            'background:var(--ps-primary-light, rgba(var(--ps-primary-rgb,79,70,229),.12));' +
            'color:var(--ps-primary);border-radius:999px;font-size:.8125rem;white-space:nowrap;';
        chip.dataset.tag = name;

        var label = document.createElement('span');
        label.textContent = name;

        var close = document.createElement('button');
        close.type      = 'button';
        close.innerHTML = '&times;';
        close.style.cssText = 'background:none;border:none;cursor:pointer;padding:0 .125rem;' +
            'color:inherit;font-size:.9375rem;line-height:1;opacity:.7;';

        var self = this;
        close.addEventListener('click', function (e) { e.stopPropagation(); self._remove(name); });

        chip.appendChild(label);
        chip.appendChild(close);
        this.wrap.insertBefore(chip, this.inputEl);

        if (sync !== false) this._sync();
    };

    VtxTags.prototype._remove = function (name) {
        var idx = this._tags.indexOf(name);
        if (idx === -1) return;
        this._tags.splice(idx, 1);
        var chip = this.wrap.querySelector('[data-tag="' + CSS.escape(name) + '"]');
        if (chip) chip.remove();
        this._sync();
    };

    VtxTags.prototype._addFromInput = function () {
        var val = this.inputEl.value.replace(/,/g, '').trim();
        if (val) {
            this._add(val);
            this.inputEl.value = '';
        }
        this._closeDropdown();
    };

    VtxTags.prototype._sync = function () {
        if (this.hidden) this.hidden.value = this._tags.join(', ');
    };

    VtxTags.prototype._search = function (q) {
        var self = this;
        VtxAjax.get(this.ajaxUrl + '?q=' + encodeURIComponent(q), function (ok, html) {
            if (!ok) return;
            var results;
            try { results = JSON.parse(html); } catch (e) { return; }
            if (!Array.isArray(results) || !results.length) { self._closeDropdown(); return; }

            self.dropdown.innerHTML = '';
            results.slice(0, 8).forEach(function (item) {
                var name    = item.name || item;
                var isAdded = self._tags.indexOf(name) !== -1;
                var row = document.createElement('div');
                row.style.cssText = 'padding:.375rem .75rem;font-size:.875rem;display:flex;' +
                    'justify-content:space-between;align-items:center;gap:.5rem;' +
                    (isAdded
                        ? 'color:var(--ps-text-muted);cursor:default;'
                        : 'color:var(--ps-text-primary);cursor:pointer;');

                var label = document.createElement('span');
                label.textContent = name;
                row.appendChild(label);

                if (isAdded) {
                    var badge = document.createElement('span');
                    badge.textContent = 'added';
                    badge.style.cssText = 'font-size:.6875rem;opacity:.6;flex-shrink:0;';
                    row.appendChild(badge);
                }

                row.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    if (!isAdded) self._add(name);
                    self.inputEl.value = '';
                    self._closeDropdown();
                });

                if (!isAdded) {
                    row.addEventListener('mouseover', function () { row.style.background = 'var(--ps-hover)'; });
                    row.addEventListener('mouseout',  function () { row.style.background = ''; });
                }

                self.dropdown.appendChild(row);
            });

            self.dropdown.style.display = 'block';
            self._positionDropdown();
        });
    };

    VtxTags.prototype._positionDropdown = function () {
        var rect = this.wrap.getBoundingClientRect();
        this.dropdown.style.top   = (this.wrap.offsetTop + this.wrap.offsetHeight + 2) + 'px';
        this.dropdown.style.left  = this.wrap.offsetLeft + 'px';
        this.dropdown.style.width = this.wrap.offsetWidth + 'px';
    };

    VtxTags.prototype._closeDropdown = function () {
        this.dropdown.style.display = 'none';
    };

    root.VtxTags = VtxTags;

    // Auto-init
    document.querySelectorAll('[data-vtx-tags]').forEach(function (el) {
        if (!el._vtxTags) el._vtxTags = new VtxTags({ el: el });
    });

}(window));
