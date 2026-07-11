/* Vertext CMS - VtxTooltip Component v1.0.0
 *
 * <button data-vtx-tooltip="Sync Views"><i class="pi pi-refresh"></i></button>
 *
 * Delegated on document (mouseover/mouseout, focusin/focusout) rather than bound
 * per-element, so any [data-vtx-tooltip] element - including ones inserted later
 * via AJAX modal content - works immediately with no re-initialization call.
 *
 * If the trigger has no other accessible name (no visible text, no existing
 * aria-label), sets aria-label from the same attribute value - icon-only buttons
 * get a real screen-reader label, not just a hover-only visual tooltip.
 */
(function () {
    'use strict';

    var SHOW_DELAY = 300;
    var tip        = null;
    var showTimer  = null;
    var current    = null;

    function ensureTip() {
        if (tip) return tip;
        tip = document.createElement('div');
        tip.className = 'vtx-tooltip';
        tip.setAttribute('role', 'tooltip');
        tip.id = 'vtx-tooltip';
        document.body.appendChild(tip);
        return tip;
    }

    function ensureLabel(el) {
        if (el.hasAttribute('aria-label') || el.hasAttribute('aria-labelledby')) return;
        if (el.textContent && el.textContent.trim() !== '') return;
        var text = el.getAttribute('data-vtx-tooltip');
        if (text) el.setAttribute('aria-label', text);
    }

    function position(el) {
        var t       = ensureTip();
        var rect    = el.getBoundingClientRect();
        var tipRect = t.getBoundingClientRect(); // measurable even while opacity:0/visibility:hidden

        var top     = rect.top - tipRect.height - 8;
        var flipped = top < 4;
        if (flipped) top = rect.bottom + 8;

        var left = rect.left + (rect.width / 2) - (tipRect.width / 2);
        left = Math.max(4, Math.min(left, window.innerWidth - tipRect.width - 4));

        t.style.top  = top + 'px';
        t.style.left = left + 'px';
        t.classList.toggle('is-below', flipped);
    }

    function show(el) {
        var text = el.getAttribute('data-vtx-tooltip');
        if (!text) return;
        clearTimeout(showTimer);
        showTimer = setTimeout(function () {
            current = el;
            var t = ensureTip();
            t.textContent = text;
            el.setAttribute('aria-describedby', 'vtx-tooltip');
            position(el);
            t.classList.add('is-visible');
        }, SHOW_DELAY);
    }

    function hide(el) {
        clearTimeout(showTimer);
        if (current !== el) return;
        current = null;
        if (tip) tip.classList.remove('is-visible');
        if (el) el.removeAttribute('aria-describedby');
    }

    document.addEventListener('mouseover', function (e) {
        var el = e.target.closest('[data-vtx-tooltip]');
        if (el) { ensureLabel(el); show(el); }
    });

    document.addEventListener('mouseout', function (e) {
        var el = e.target.closest('[data-vtx-tooltip]');
        if (el && !el.contains(e.relatedTarget)) hide(el);
    });

    document.addEventListener('focusin', function (e) {
        var el = e.target.closest('[data-vtx-tooltip]');
        if (el) { ensureLabel(el); show(el); }
    });

    document.addEventListener('focusout', function (e) {
        var el = e.target.closest('[data-vtx-tooltip]');
        if (el) hide(el);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && current) hide(current);
    });

    window.addEventListener('scroll', function () {
        if (current) position(current);
    }, true);

    window.Vtx = window.Vtx || {};
    window.Vtx.Tooltip = { show: show, hide: hide };
}());
