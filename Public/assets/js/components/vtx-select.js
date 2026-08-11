/* Vertext CMS - VtxSelect Component v1.2.1
 *
 * Declarative:  <select data-vtx-select data-searchable data-placeholder="Choose…">
 * Imperative:   new Vtx.Select({ el: selectEl, searchable: true, placeholder: 'Choose…' })
 *
 * Remote search: <select data-vtx-select data-searchable data-ajax-url="/admin/.../search">
 * With both `data-searchable` and `data-ajax-url` set, options are never inline in the
 * <select> (except the one already-selected option, so its label shows without a fetch) -
 * every open and every keystroke (debounced 220ms) queries `{ajaxUrl}?q={term}` and expects
 * JSON `[{value, label, disabled?}, ...]` back, already filtered/limited server-side. Use
 * this instead of rendering thousands of <option> tags inline: besides the page weight, the
 * admin HTML minifier can hit PHP's internal PCRE limit on a large enough page and crash
 * (see Core\Utilities\Text\HTML - fixed defensively there too, but avoiding the giant
 * <option> list in the first place is the real fix for this component's own forms).
 * `data-ajax-url` WITHOUT `data-searchable` keeps the older "load full list once, filter
 * client-side" behavior.
 *
 * The native <select> stays in the DOM (hidden) so form serialization works unchanged.
 * The dropdown is rendered as a body-level portal (position:fixed) so it is never
 * clipped by ancestor overflow:hidden containers or fixed-height panels.
 */
(function () {
    'use strict';

    var _uid = 0;

    function VtxSelect(opts) {
        if (!opts || !opts.el) return;

        var selectEl = typeof opts.el === 'string'
            ? document.querySelector(opts.el) : opts.el;
        if (!selectEl || selectEl.tagName !== 'SELECT') return;
        if (selectEl._vtxSelect) return; // already enhanced

        var searchable  = opts.searchable  !== undefined ? opts.searchable  : selectEl.hasAttribute('data-searchable');
        var multiple    = opts.multiple    !== undefined ? opts.multiple    : selectEl.multiple;
        var placeholder = opts.placeholder || selectEl.dataset.placeholder
            || (selectEl.options[0] && selectEl.options[0].value === '' ? selectEl.options[0].text : 'Select…');
        var ajaxUrl     = opts.ajaxUrl     || selectEl.dataset.ajaxUrl || null;
        var onChange    = opts.onChange    || null;
        var id          = 'vtx-sel-' + (++_uid);

        // Both set - options come from the server per-keystroke, never preloaded in bulk.
        var remoteSearch = !!(ajaxUrl && searchable);

        var options   = [];   // [{ value, label, disabled }]
        var selected  = [];   // array of string values
        // value -> label for every option ever selected/rendered, kept independent of
        // `options` so a remote search replacing `options` can't blank out the trigger's
        // label for whatever was already picked (e.g. the label of a pre-selected option
        // isn't necessarily present in the results of a later, unrelated search query).
        var selectedLabels = {};
        var ajaxCache = null;
        var searchToken = 0; // increments per remote-search request; stale responses are dropped
        var searchDebounceTimer = null;
        var isOpen    = false;

        /* -- Build widget DOM ----------------------------------- */
        var container = document.createElement('div');
        container.className = 'vtx-select';
        container.id = id;

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'vtx-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', id + '-list');
        if (selectEl.disabled) {
            trigger.disabled = true;
            container.classList.add('vtx-select--disabled');
        }

        var valueEl = document.createElement('span');
        valueEl.className = 'vtx-select-value';
        trigger.appendChild(valueEl);

        var chevron = document.createElement('i');
        chevron.className = 'pi pi-chevron-down vtx-select-chevron';
        trigger.appendChild(chevron);

        // Dropdown is a body-level portal - NOT inside container.
        // This means no ancestor overflow:hidden can clip it.
        var dropdown = document.createElement('div');
        dropdown.className = 'vtx-select-dropdown';
        dropdown.setAttribute('aria-hidden', 'true');

        var searchWrap = null;
        var searchInput = null;
        if (searchable) {
            searchWrap = document.createElement('div');
            searchWrap.className = 'vtx-select-search-wrap';
            searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.className = 'vtx-select-search';
            searchInput.placeholder = 'Search…';
            searchInput.setAttribute('aria-label', 'Search options');
            searchInput.setAttribute('autocomplete', 'off');
            searchWrap.appendChild(searchInput);
            dropdown.appendChild(searchWrap);
        }

        var listEl = document.createElement('ul');
        listEl.className = 'vtx-select-list';
        listEl.id = id + '-list';
        listEl.setAttribute('role', 'listbox');
        listEl.setAttribute('aria-multiselectable', multiple ? 'true' : 'false');
        dropdown.appendChild(listEl);

        container.appendChild(trigger);

        // Hide native select, insert widget, keep native inside container for form association
        selectEl.style.display = 'none';
        selectEl.setAttribute('aria-hidden', 'true');
        selectEl.parentNode.insertBefore(container, selectEl);
        container.appendChild(selectEl);

        // Mount dropdown at body level (portal) so it is never clipped
        document.body.appendChild(dropdown);

        /* -- Position dropdown via trigger's bounding rect ----- */
        function positionDropdown() {
            var rect  = trigger.getBoundingClientRect();
            var below = window.innerHeight - rect.bottom;
            var flipUp = below < 240 && rect.top > 240;

            dropdown.style.left  = rect.left + 'px';
            dropdown.style.width = rect.width + 'px';

            if (flipUp) {
                dropdown.style.top    = 'auto';
                dropdown.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
                dropdown.classList.add('is-above');
            } else {
                dropdown.style.top    = (rect.bottom + 4) + 'px';
                dropdown.style.bottom = 'auto';
                dropdown.classList.remove('is-above');
            }
        }

        /* -- Read native state ---------------------------------- */
        function readNativeOptions() {
            options = [];
            // A blank-value <option> (e.g. "- None -") used to be skipped here entirely, so
            // once such a field had a real value selected, there was no list item left to
            // click to clear it back to blank - confirmed live on Carikno's Categories
            // "Parent Category" field. It's kept in `options` now so it renders as a normal,
            // clickable (or disabled, if the option itself is disabled) list entry.
            Array.from(selectEl.options).forEach(function (opt) {
                var o = { value: opt.value, label: opt.text.trim(), disabled: opt.disabled };
                options.push(o);
                selectedLabels[o.value] = o.label;
            });
        }

        function resolveLabel(value) {
            if (selectedLabels[value] !== undefined) return selectedLabels[value];
            var o = findOption(value);
            return o ? o.label : null;
        }

        function readNativeSelected() {
            selected = Array.from(selectEl.selectedOptions)
                .map(function (o) { return o.value; })
                .filter(function (v) { return v !== ''; });
        }

        /* -- Render options list -------------------------------- */
        function renderList(filter) {
            filter = (filter || '').toLowerCase().trim();
            listEl.innerHTML = '';

            var visible = filter
                ? options.filter(function (o) { return o.label.toLowerCase().indexOf(filter) !== -1; })
                : options;

            if (visible.length === 0) {
                var li = document.createElement('li');
                li.className = 'vtx-select-no-results';
                li.textContent = filter ? 'No results for "' + filter + '"' : 'No options available';
                listEl.appendChild(li);
                return;
            }

            visible.forEach(function (opt) {
                var li = document.createElement('li');
                li.className = 'vtx-select-option';
                if (selected.indexOf(opt.value) !== -1) li.classList.add('is-selected');
                if (opt.disabled) li.classList.add('is-disabled');
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', selected.indexOf(opt.value) !== -1 ? 'true' : 'false');
                li.dataset.value = opt.value;
                li.textContent = opt.label;

                if (!opt.disabled) {
                    li.addEventListener('mousedown', function (e) {
                        e.preventDefault(); // prevent input blur before selection registers
                        pickOption(opt.value);
                    });
                }

                listEl.appendChild(li);
            });
        }

        /* -- Render trigger label / tags ------------------------ */
        function renderTrigger() {
            valueEl.innerHTML = '';

            if (selected.length === 0) {
                var ph = document.createElement('span');
                ph.className = 'vtx-select-placeholder';
                ph.textContent = placeholder;
                valueEl.appendChild(ph);
                return;
            }

            if (multiple) {
                selected.forEach(function (val) {
                    var optLabel = resolveLabel(val);
                    if (optLabel === null) return;
                    var tag = document.createElement('span');
                    tag.className = 'vtx-select-tag';

                    var label = document.createElement('span');
                    label.textContent = optLabel;
                    tag.appendChild(label);

                    var rm = document.createElement('button');
                    rm.type = 'button';
                    rm.className = 'vtx-select-tag-rm';
                    rm.setAttribute('aria-label', 'Remove ' + optLabel);
                    rm.innerHTML = '&#x2715;';
                    rm.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        deselect(val);
                    });
                    tag.appendChild(rm);
                    valueEl.appendChild(tag);
                });
            } else {
                var resolved = resolveLabel(selected[0]);
                valueEl.textContent = resolved !== null ? resolved : placeholder;
            }
        }

        function findOption(val) {
            return options.filter(function (o) { return o.value === val; })[0] || null;
        }

        /* -- Sync native select --------------------------------- */
        function syncNative() {
            // In remote-search mode, a value picked from a server search result has no
            // backing <option> in the native <select> (only the pre-selected option, if
            // any, is ever rendered inline) - add one, or the form would submit nothing
            // for this field despite the widget showing a selection.
            selected.forEach(function (val) {
                var exists = Array.from(selectEl.options).some(function (opt) { return opt.value === val; });
                if (!exists) {
                    selectEl.add(new Option(resolveLabel(val) || val, val, false, false));
                }
            });

            Array.from(selectEl.options).forEach(function (opt) {
                opt.selected = selected.indexOf(opt.value) !== -1;
            });
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
            if (typeof onChange === 'function') {
                var val = multiple ? selected.slice() : (selected[0] || '');
                onChange(val);
            }
        }

        /* -- Pick / deselect ------------------------------------ */
        function pickOption(value) {
            var opt = findOption(value);
            if (opt) selectedLabels[value] = opt.label;

            if (multiple) {
                var idx = selected.indexOf(value);
                if (idx === -1) { selected.push(value); }
                else            { selected.splice(idx, 1); }
            } else {
                selected = [value];
                close();
            }
            syncNative();
            renderTrigger();
            renderList(searchInput ? searchInput.value : '');
        }

        function deselect(value) {
            var idx = selected.indexOf(value);
            if (idx !== -1) selected.splice(idx, 1);
            syncNative();
            renderTrigger();
            renderList(searchInput ? searchInput.value : '');
        }

        /* -- Open / close --------------------------------------- */
        function open() {
            if (isOpen || trigger.disabled) return;

            // Close any other open vtx-selects
            document.querySelectorAll('.vtx-select.is-open').forEach(function (el) {
                if (el !== container && el._vtxSelectInst) el._vtxSelectInst.close();
            });

            if (remoteSearch) {
                openDropdown();
                remoteSearchFetch('');
            } else if (ajaxUrl && !ajaxCache) {
                loadAjaxOptions();
            } else {
                openDropdown();
            }
        }

        function openDropdown() {
            isOpen = true;
            container.classList.add('is-open');
            dropdown.classList.add('vtx-select-dropdown--open');
            dropdown.setAttribute('aria-hidden', 'false');
            trigger.setAttribute('aria-expanded', 'true');
            renderList('');
            positionDropdown();

            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
        }

        function close() {
            if (!isOpen) return;
            isOpen = false;
            clearTimeout(searchDebounceTimer);
            container.classList.remove('is-open');
            dropdown.classList.remove('vtx-select-dropdown--open');
            dropdown.classList.remove('is-above');
            dropdown.setAttribute('aria-hidden', 'true');
            trigger.setAttribute('aria-expanded', 'false');
            if (searchInput) searchInput.value = '';
        }

        /* -- Reposition on scroll / resize --------------------- */
        function onScrollOrResize() {
            if (isOpen) positionDropdown();
        }
        // Capture phase catches scrolls inside any ancestor container
        window.addEventListener('scroll', onScrollOrResize, true);
        window.addEventListener('resize', onScrollOrResize);

        /* -- Remote search (data-searchable + data-ajax-url) ---- */
        function remoteSearchFetch(query) {
            var token = ++searchToken;
            listEl.innerHTML = '<li class="vtx-select-no-results">Loading…</li>';

            VtxAjax.get(ajaxUrl + '?q=' + encodeURIComponent(query), function (ok, text) {
                if (token !== searchToken) return; // superseded by a newer search

                if (!ok) {
                    listEl.innerHTML = '<li class="vtx-select-no-results">Failed to load options.</li>';
                    return;
                }
                var data;
                try { data = JSON.parse(text); } catch (e) { data = []; }
                options = data.map(function (item) {
                    return { value: String(item.value), label: String(item.label), disabled: !!item.disabled };
                });
                renderList('');
            });
        }

        /* -- AJAX option loading -------------------------------- */
        function loadAjaxOptions() {
            listEl.innerHTML = '<li class="vtx-select-no-results">Loading…</li>';
            openDropdown();

            VtxAjax.get(ajaxUrl, function (ok, text) {
                if (!ok) {
                    listEl.innerHTML = '<li class="vtx-select-no-results">Failed to load options.</li>';
                    return;
                }
                var data;
                try { data = JSON.parse(text); } catch (e) { data = []; }
                ajaxCache = data;
                options = data.map(function (item) {
                    return { value: String(item.value), label: String(item.label), disabled: !!item.disabled };
                });
                renderList('');
            });
        }

        /* -- Keyboard navigation -------------------------------- */
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            isOpen ? close() : open();
        });

        trigger.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                e.preventDefault();
                open();
            } else if (e.key === 'Escape') {
                close();
            }
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                if (remoteSearch) {
                    var q = searchInput.value;
                    clearTimeout(searchDebounceTimer);
                    searchDebounceTimer = setTimeout(function () { remoteSearchFetch(q); }, 220);
                } else {
                    renderList(searchInput.value);
                }
            });
            searchInput.addEventListener('keydown', handleListKeydown);
        }

        dropdown.addEventListener('keydown', handleListKeydown);

        function handleListKeydown(e) {
            var items = Array.from(listEl.querySelectorAll('.vtx-select-option:not(.is-disabled)'));
            var focused = listEl.querySelector('.vtx-select-option.is-focused');
            var idx = focused ? items.indexOf(focused) : -1;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setFocused(items[idx + 1] || items[0]);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setFocused(items[idx - 1] || items[items.length - 1]);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (focused) pickOption(focused.dataset.value);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                close();
                trigger.focus();
            } else if (e.key === 'Tab') {
                close();
            }
        }

        function setFocused(li) {
            if (!li) return;
            listEl.querySelectorAll('.vtx-select-option.is-focused').forEach(function (el) {
                el.classList.remove('is-focused');
            });
            li.classList.add('is-focused');
            li.scrollIntoView({ block: 'nearest' });
        }

        // Close on outside click - must check both container and dropdown
        // since dropdown is no longer a descendant of container
        document.addEventListener('click', function (e) {
            if (!container.contains(e.target) && !dropdown.contains(e.target)) close();
        });

        /* -- Initial render --------------------------------------
         * Always read whatever's in the native <select> - for a static select
         * that's the full option list; for an ajax/remote-search select it's
         * only the 0-1 option the server pre-rendered for the current value
         * (see the module docblock), which is exactly what's needed to show
         * the right label immediately without waiting on a fetch. */
        readNativeOptions();
        readNativeSelected();
        renderTrigger();

        /* -- Public API ----------------------------------------- */
        var self = {
            el: selectEl,
            container: container,

            open: open,
            close: close,

            getValue: function () {
                return multiple ? selected.slice() : (selected[0] || '');
            },

            setValue: function (val) {
                selected = Array.isArray(val) ? val.map(String) : (val !== '' ? [String(val)] : []);
                syncNative();
                renderTrigger();
                renderList(searchInput ? searchInput.value : '');
            },

            setOptions: function (newOpts) {
                options = newOpts.map(function (o) {
                    return { value: String(o.value), label: String(o.label), disabled: !!o.disabled };
                });
                var keep = selected.slice();
                selectEl.innerHTML = '';
                options.forEach(function (o) {
                    var opt = new Option(o.label, o.value, false, keep.indexOf(o.value) !== -1);
                    opt.disabled = o.disabled;
                    selectEl.add(opt);
                });
                selected = keep.filter(function (v) {
                    return options.some(function (o) { return o.value === v; });
                });
                renderTrigger();
                renderList('');
            },

            destroy: function () {
                clearTimeout(searchDebounceTimer);
                window.removeEventListener('scroll', onScrollOrResize, true);
                window.removeEventListener('resize', onScrollOrResize);
                if (dropdown.parentNode) dropdown.parentNode.removeChild(dropdown);
                if (container.parentNode) container.parentNode.removeChild(container);
                selectEl.style.display = '';
                selectEl.removeAttribute('aria-hidden');
                selectEl._vtxSelect = null;
            }
        };

        container._vtxSelectInst = self;
        selectEl._vtxSelect = self;
        Vtx._register('select', self);
        return self;
    }

    VtxSelect.version = '1.2.1';
    window.Vtx.Select = VtxSelect;

    function autoInit() {
        document.querySelectorAll('[data-vtx-select]').forEach(function (el) {
            new VtxSelect({ el: el });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }

}());
