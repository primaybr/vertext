/* Vertext CMS - VtxSearch Component v1.0.0
 *
 * Declarative:  <input data-vtx-search data-target="#tbody" data-url="/admin/posts">
 * Imperative:   new Vtx.Search({ input: el, target: '#tbody', url: '/admin/posts' })
 */
(function () {
    'use strict';

    function VtxSearch(opts) {
        if (!opts || !opts.input) return;

        var inputEl = typeof opts.input === 'string'
            ? document.querySelector(opts.input) : opts.input;
        if (!inputEl) return;

        var targetSel  = opts.target           || inputEl.dataset.target;
        var url        = opts.url              || inputEl.dataset.url || window.location.pathname;
        var responseSel = opts.responseSelector || inputEl.dataset.responseSelector || 'table.vtx-table tbody';
        var debounceMs = opts.debounce !== undefined
            ? parseInt(opts.debounce)
            : (inputEl.dataset.debounce ? parseInt(inputEl.dataset.debounce) : 350);
        var onResult   = opts.onResult || null;

        var targetEl = targetSel ? document.querySelector(targetSel) : null;
        var timer    = null;
        var wrapper  = ensureWrapper(inputEl);
        var clearBtn = buildClearBtn(wrapper);

        function ensureWrapper(el) {
            var existing = el.closest('.vtx-search-wrap');
            if (existing) return existing;
            var wrap = document.createElement('div');
            wrap.className = 'vtx-search-wrap';
            el.parentNode.insertBefore(wrap, el);
            wrap.appendChild(el);
            return wrap;
        }

        function buildClearBtn(wrap) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'vtx-search-clear';
            btn.setAttribute('aria-label', 'Clear search');
            btn.innerHTML = '<i class="pi pi-x"></i>';
            btn.style.display = 'none';
            wrap.appendChild(btn);
            btn.addEventListener('click', function () { self.clear(); });
            return btn;
        }

        function setLoading(on) {
            wrapper.classList.toggle('vtx-search--loading', on);
        }

        function syncClearBtn() {
            clearBtn.style.display = inputEl.value.length ? '' : 'none';
        }

        function doSearch(query) {
            setLoading(true);

            // Push search term to URL bar without page reload
            try {
                var sp = new URLSearchParams(window.location.search);
                if (query) { sp.set('search', query); } else { sp.delete('search'); }
                sp.delete('page');
                var qs = sp.toString();
                window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
            } catch (e) {}

            var sep      = url.indexOf('?') === -1 ? '?' : '&';
            var fetchUrl = url + sep + 'search=' + encodeURIComponent(query) + '&page=1';

            VtxAjax.get(fetchUrl, function (ok, html) {
                setLoading(false);
                if (!ok) return;

                var doc   = new DOMParser().parseFromString(html, 'text/html');
                var newEl = doc.querySelector(responseSel);

                if (targetEl && newEl) {
                    targetEl.innerHTML = newEl.innerHTML;
                    var hasRows = targetEl.querySelector('tr') !== null;
                    if (!hasRows) {
                        inputEl.dispatchEvent(new CustomEvent('vtx:search:empty', {
                            bubbles: true, detail: { query: query }
                        }));
                    }
                    inputEl.dispatchEvent(new CustomEvent('vtx:search:result', {
                        bubbles: true, detail: { query: query, el: targetEl }
                    }));
                }

                if (typeof onResult === 'function') onResult(query, newEl);
            });
        }

        inputEl.addEventListener('input', function () {
            syncClearBtn();
            clearTimeout(timer);
            timer = setTimeout(function () { doSearch(inputEl.value.trim()); }, debounceMs);
        });

        // Suppress Enter from submitting any parent form
        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(timer);
                doSearch(inputEl.value.trim());
            }
        });

        var self = {
            el: inputEl,
            search: function (query) {
                inputEl.value = query;
                syncClearBtn();
                doSearch(query);
            },
            clear: function () {
                inputEl.value = '';
                syncClearBtn();
                doSearch('');
                inputEl.focus();
            }
        };

        syncClearBtn();
        Vtx._register('search', self);
        return self;
    }

    VtxSearch.version = '1.0.0';
    window.Vtx.Search = VtxSearch;

    function autoInit() {
        document.querySelectorAll('[data-vtx-search]').forEach(function (el) {
            new VtxSearch({ input: el });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }

}());
