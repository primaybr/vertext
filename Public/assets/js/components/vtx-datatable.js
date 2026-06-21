/* Vertext CMS — VtxDataTable Component v1.0.0
 *
 * Declarative:  <table class="vtx-table" data-vtx-table data-sortable data-selectable>
 * Imperative:   new Vtx.DataTable({ el: tableEl, sortable: true, selectable: true })
 */
(function () {
    'use strict';

    function VtxDataTable(opts) {
        if (!opts || !opts.el) return;

        var tableEl = typeof opts.el === 'string'
            ? document.querySelector(opts.el) : opts.el;
        if (!tableEl || tableEl.tagName !== 'TABLE') return;

        var sortable   = opts.sortable   !== undefined ? opts.sortable   : tableEl.hasAttribute('data-sortable');
        var selectable = opts.selectable !== undefined ? opts.selectable : tableEl.hasAttribute('data-selectable');
        var emptyMsg   = opts.emptyMessage || tableEl.dataset.emptyMessage || 'No records found.';
        var onSelect   = opts.onSelect || null;

        var thead = tableEl.querySelector('thead');
        var tbody = tableEl.querySelector('tbody');
        if (!tbody) return;

        var sortState = { col: -1, dir: 'asc' };

        // Wrap table in a relative container for the loading overlay
        var container = tableEl.parentElement;
        if (!container.classList.contains('vtx-table-container')) {
            var wrap = document.createElement('div');
            wrap.className = 'vtx-table-container';
            tableEl.parentNode.insertBefore(wrap, tableEl);
            wrap.appendChild(tableEl);
            container = wrap;
        }

        // Loading overlay (hidden by default)
        var overlay = document.createElement('div');
        overlay.className = 'vtx-table-overlay';
        overlay.innerHTML = '<span class="vtx-table-spinner"></span>';
        container.appendChild(overlay);

        /* ── Sorting ──────────────────────────────────────────── */
        if (sortable && thead) {
            var sortHeaders = Array.from(thead.querySelectorAll('th[data-sort]'));
            sortHeaders.forEach(function (th) {
                th.classList.add('vtx-sort-th');

                var arrow = document.createElement('span');
                arrow.className = 'vtx-sort-arrow';
                th.appendChild(arrow);

                th.addEventListener('click', function () {
                    var colIdx = getCellIndex(th);
                    if (sortState.col === colIdx) {
                        sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortState.col = colIdx;
                        sortState.dir = 'asc';
                    }
                    updateSortUI(sortHeaders, th, sortState.dir);
                    sortRows(colIdx, sortState.dir);
                });
            });
        }

        function getCellIndex(th) {
            return Array.from(th.closest('tr').cells).indexOf(th);
        }

        function updateSortUI(headers, activeTh, dir) {
            headers.forEach(function (h) {
                h.classList.remove('vtx-sort-asc', 'vtx-sort-desc');
                h.setAttribute('aria-sort', 'none');
            });
            activeTh.classList.add(dir === 'asc' ? 'vtx-sort-asc' : 'vtx-sort-desc');
            activeTh.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
        }

        function sortRows(colIdx, dir) {
            var rows = Array.from(tbody.querySelectorAll('tr:not(.vtx-empty-row)'));
            rows.sort(function (a, b) {
                var cellA = a.cells[colIdx] ? a.cells[colIdx].textContent.trim() : '';
                var cellB = b.cells[colIdx] ? b.cells[colIdx].textContent.trim() : '';
                var numA  = parseFloat(cellA.replace(/[^\d.-]/g, ''));
                var numB  = parseFloat(cellB.replace(/[^\d.-]/g, ''));
                var cmp   = (!isNaN(numA) && !isNaN(numB))
                    ? numA - numB
                    : cellA.localeCompare(cellB, undefined, { sensitivity: 'base' });
                return dir === 'asc' ? cmp : -cmp;
            });
            rows.forEach(function (r) { tbody.appendChild(r); });
        }

        /* ── Row Selection ────────────────────────────────────── */
        var masterCheck = null;

        if (selectable && thead) {
            var headerRow = thead.querySelector('tr');
            if (headerRow) {
                var thCheck = document.createElement('th');
                thCheck.className = 'vtx-check-col';
                masterCheck = document.createElement('input');
                masterCheck.type = 'checkbox';
                masterCheck.className = 'vtx-row-check-master';
                masterCheck.setAttribute('aria-label', 'Select all rows');
                thCheck.appendChild(masterCheck);
                headerRow.insertBefore(thCheck, headerRow.firstChild);

                masterCheck.addEventListener('change', function () {
                    Array.from(tbody.querySelectorAll('.vtx-row-check')).forEach(function (cb) {
                        cb.checked = masterCheck.checked;
                        cb.closest('tr').classList.toggle('vtx-row-selected', masterCheck.checked);
                    });
                    fireSelectionChange();
                });

                // Inject checkboxes into existing rows
                Array.from(tbody.querySelectorAll('tr')).forEach(addRowCheckbox);
            }
        }

        function addRowCheckbox(row) {
            if (row.querySelector('.vtx-row-check') || row.classList.contains('vtx-empty-row')) return;
            var td = document.createElement('td');
            td.className = 'vtx-check-col';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'vtx-row-check';
            cb.setAttribute('aria-label', 'Select row');
            td.appendChild(cb);
            row.insertBefore(td, row.firstChild);
            cb.addEventListener('change', function () {
                row.classList.toggle('vtx-row-selected', cb.checked);
                syncMasterCheck();
                fireSelectionChange();
            });
        }

        function syncMasterCheck() {
            if (!masterCheck) return;
            var all     = Array.from(tbody.querySelectorAll('.vtx-row-check'));
            var checked = all.filter(function (c) { return c.checked; });
            masterCheck.indeterminate = checked.length > 0 && checked.length < all.length;
            masterCheck.checked = all.length > 0 && checked.length === all.length;
        }

        function fireSelectionChange() {
            var rows = getSelected();
            if (typeof onSelect === 'function') onSelect(rows);
            tableEl.dispatchEvent(new CustomEvent('vtx:table:selection-change', {
                bubbles: true, detail: { rows: rows }
            }));
        }

        function getSelected() {
            return Array.from(tbody.querySelectorAll('.vtx-row-check:checked')).map(function (cb) {
                return cb.closest('tr');
            });
        }

        /* ── Empty state ──────────────────────────────────────── */
        function checkEmpty() {
            var dataRows = Array.from(tbody.querySelectorAll('tr:not(.vtx-empty-row)'));
            var emptyRow = tbody.querySelector('.vtx-empty-row');

            if (dataRows.length === 0 && !emptyRow) {
                var colCount = thead ? thead.querySelector('tr').cells.length : 1;
                var tr = document.createElement('tr');
                tr.className = 'vtx-empty-row';
                tr.innerHTML = '<td colspan="' + colCount + '" class="vtx-table-empty-cell">' + emptyMsg + '</td>';
                tbody.appendChild(tr);
            } else if (dataRows.length > 0 && emptyRow) {
                emptyRow.remove();
            }
        }

        checkEmpty();

        // Refresh empty-state when VtxSearch injects new rows into this tbody
        document.addEventListener('vtx:search:result', function (e) {
            if (e.detail && e.detail.el === tbody) checkEmpty();
        });

        /* ── AJAX Reload ──────────────────────────────────────── */
        function reload(url) {
            overlay.style.display = 'flex';

            VtxAjax.get(url || window.location.href, function (ok, html) {
                overlay.style.display = 'none';
                if (!ok) return;

                var doc     = new DOMParser().parseFromString(html, 'text/html');
                var newBody = doc.querySelector('table.vtx-table tbody');
                if (!newBody) return;

                tbody.innerHTML = newBody.innerHTML;

                // Re-inject checkboxes on fresh rows after reload
                if (selectable) {
                    Array.from(tbody.querySelectorAll('tr')).forEach(addRowCheckbox);
                    syncMasterCheck();
                }

                checkEmpty();

                // Re-apply current sort if one was active
                if (sortable && sortState.col > -1) {
                    sortRows(sortState.col, sortState.dir);
                }

                tableEl.dispatchEvent(new CustomEvent('vtx:table:reloaded', { bubbles: true }));
            });
        }

        /* ── Public API ───────────────────────────────────────── */
        var self = {
            el: tableEl,
            reload: reload,
            getSelected: getSelected,
            clearSelection: function () {
                Array.from(tbody.querySelectorAll('.vtx-row-check')).forEach(function (cb) {
                    cb.checked = false;
                    cb.closest('tr').classList.remove('vtx-row-selected');
                });
                if (masterCheck) { masterCheck.checked = false; masterCheck.indeterminate = false; }
                fireSelectionChange();
            }
        };

        Vtx._register('table', self);
        return self;
    }

    VtxDataTable.version = '1.0.0';
    window.Vtx.DataTable = VtxDataTable;

    function autoInit() {
        document.querySelectorAll('[data-vtx-table]').forEach(function (el) {
            new VtxDataTable({ el: el });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }

}());
