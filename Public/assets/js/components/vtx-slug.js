/**
 * vtx-slug — Auto-generate URL slugs from a source input.
 *
 * Usage:
 *   <input id="title" data-vtx-slug-source>
 *   <input id="slug"  data-vtx-slug-target data-vtx-slug-source-id="title">
 *
 * The target input gets a "Reset" link injected after it once the user
 * manually edits it, allowing them to re-enable auto-generation.
 */
(function () {
  'use strict';

  function slugify(text) {
    return text
      .toLowerCase()
      .replace(/[^\w\s-]/g, '')   // remove non-word chars except hyphens
      .replace(/[\s_]+/g, '-')    // spaces/underscores → hyphens
      .replace(/-+/g, '-')        // collapse multiple hyphens
      .replace(/^-+|-+$/g, '');   // trim leading/trailing hyphens
  }

  function initSlugPair(sourceEl, targetEl) {
    var auto = true; // auto-fill active flag
    var resetLink = null;

    function showReset() {
      if (resetLink) return;
      resetLink = document.createElement('span');
      resetLink.innerHTML = ' &bull; <a href="#" style="font-size:.75rem;color:var(--ps-primary,#3b82f6);">Reset to auto</a>';
      resetLink.querySelector('a').addEventListener('click', function (e) {
        e.preventDefault();
        auto = true;
        targetEl.value = slugify(sourceEl.value);
        resetLink.remove();
        resetLink = null;
      });
      targetEl.parentNode.insertBefore(resetLink, targetEl.nextSibling);
    }

    // Source → target auto-fill (debounced)
    var timer;
    sourceEl.addEventListener('input', function () {
      if (!auto) return;
      clearTimeout(timer);
      timer = setTimeout(function () {
        targetEl.value = slugify(sourceEl.value);
      }, 280);
    });

    // Detect manual edit of target
    targetEl.addEventListener('input', function () {
      if (auto && targetEl.value !== slugify(sourceEl.value)) {
        auto = false;
        showReset();
      }
    });

    // Seed on load if target is empty
    if (!targetEl.value && sourceEl.value) {
      targetEl.value = slugify(sourceEl.value);
    }
  }

  function init() {
    document.querySelectorAll('[data-vtx-slug-target]').forEach(function (targetEl) {
      var sourceId = targetEl.dataset.vtxSlugSourceId;
      var sourceEl = sourceId
        ? document.getElementById(sourceId)
        : document.querySelector('[data-vtx-slug-source]');
      if (sourceEl) initSlugPair(sourceEl, targetEl);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Re-init for dynamically loaded modals
  document.addEventListener('vtx:modal:loaded', init);

  window.vtxSlug = { slugify: slugify, init: init };
}());
