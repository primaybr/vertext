/* -- admin/theme-customizer/index.php: tab switching (Appearance / Landing Blocks) -- */
(function () {
  var tabs  = document.querySelectorAll('.vtx-tc-tab');
  var panes = { 'appearance': document.getElementById('tab-appearance'), 'landing-blocks': document.getElementById('tab-landing-blocks') };
  if (!tabs.length || !panes.appearance || !panes['landing-blocks']) return; // Only present on admin/theme-customizer

  function activate(key) {
    tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.tab === key); });
    Object.keys(panes).forEach(function (k) { panes[k].style.display = k === key ? '' : 'none'; });
  }

  tabs.forEach(function (btn) {
    btn.addEventListener('click', function () { activate(this.dataset.tab); });
  });
})();

/* -- admin/theme-customizer/index.php: Landing Blocks drag-drop editor -- */
(function () {
  var wrapper = document.getElementById('vtx-lb-builder');
  if (!wrapper) return; // Only present on admin/theme-customizer

  var BASE   = wrapper.dataset.baseUrl;
  var THEME  = wrapper.dataset.themeSlug;
  var CSRF   = wrapper.dataset.csrf;
  var blocks = [];
  try { blocks = JSON.parse(wrapper.dataset.blocks || '[]'); } catch (e) { blocks = []; }

  var canvas = document.getElementById('vtx-lb-canvas');
  var empty  = document.getElementById('vtx-lb-canvas-empty');

  // -- Live preview (debounced stage-and-reload, mirrors tc-admin.js's
  //    color/font preview) - a blocks payload is too large for a query string,
  //    so pending edits are staged server-side (session) and the iframe just
  //    reloads afterward. ------------------------------------------------------
  var previewFrame  = document.getElementById('tc-lb-preview-frame');
  var previewStatus = document.getElementById('tc-lb-preview-status');
  var stageUrl      = previewFrame ? previewFrame.dataset.stageUrl : null;
  var previewUrl    = previewFrame ? previewFrame.dataset.previewUrl : null;
  var stageDebounce = null;

  function stagePreview() {
    if (!previewFrame || !stageUrl) return;
    if (previewStatus) previewStatus.textContent = 'Updating...';
    clearTimeout(stageDebounce);
    stageDebounce = setTimeout(function () {
      fetch(stageUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: CSRF, blocks: JSON.stringify(blocks) })
      })
        .then(function () {
          // Cache-bust: unlike the Appearance tab's preview URL, this one has no
          // query params that change per edit, so the browser would otherwise
          // treat repeated reloads of the same URL as cacheable. previewUrl
          // already carries ?view=blocks, hence & here rather than ?.
          previewFrame.src = previewUrl + '&t=' + Date.now();
        })
        .catch(function () {
          if (previewStatus) previewStatus.textContent = '';
        });
    }, 350);
  }

  if (previewFrame) {
    previewFrame.addEventListener('load', function () {
      if (previewStatus) previewStatus.textContent = '';
    });
  }

  // Rich-text blocks (see wireCard() below) sync their Quill content into a
  // hidden textarea via plain property assignment (VtxEditor), which does NOT
  // fire a native 'input' event - so blocks[idx].html can go stale relative to
  // what's actually in the editor. Re-read every rich-text textarea's current
  // value right before it matters (staging or saving), rather than trusting
  // whatever the last 'input'-driven write happened to be.
  function syncRichTextFields() {
    canvas.querySelectorAll('[data-lb-field="html"]').forEach(function (textarea) {
      var idx = currentBlockIdx(textarea);
      if (idx !== -1 && blocks[idx]) blocks[idx].html = textarea.value;
    });
  }

  var BLOCK_LABELS = {
    'hero': 'Hero', 'feature-grid': 'Feature Grid', 'testimonials': 'Testimonials',
    'gallery': 'Gallery', 'cta-banner': 'CTA Banner', 'rich-text': 'Rich Text', 'stats': 'Stats'
  };

  var ITEM_SPECS = {
    'feature-grid': [
      { key: 'icon', label: 'Icon class', placeholder: 'pi-star' },
      { key: 'title', label: 'Title' },
      { key: 'text', label: 'Text', textarea: true }
    ],
    'testimonials': [
      { key: 'quote', label: 'Quote', textarea: true },
      { key: 'author', label: 'Author' },
      { key: 'role', label: 'Role' },
      { key: 'avatar', label: 'Avatar URL' }
    ],
    'gallery': [
      { key: 'image', label: 'Image URL' },
      { key: 'alt', label: 'Alt text' }
    ],
    'stats': [
      { key: 'number', label: 'Number' },
      { key: 'label', label: 'Label' }
    ]
  };

  function defaultsFor(type) {
    switch (type) {
      case 'hero':         return { type: type, headline: '', subheadline: '', cta_text: '', cta_link: '', image: '' };
      case 'feature-grid':  return { type: type, headline: '', columns: 3, items: [] };
      case 'testimonials':  return { type: type, headline: '', items: [] };
      case 'gallery':       return { type: type, headline: '', items: [] };
      case 'cta-banner':    return { type: type, headline: '', text: '', button_text: '', button_link: '' };
      case 'rich-text':     return { type: type, html: '' };
      case 'stats':         return { type: type, items: [] };
      default:              return { type: type };
    }
  }

  function esc(str) {
    return String(str == null ? '' : str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function updateEmptyState() {
    empty.style.display = canvas.children.length ? 'none' : 'block';
  }

  function reindexBlockCards() {
    Array.prototype.forEach.call(canvas.children, function (card, i) { card.dataset.blockIdx = String(i); });
  }

  function currentBlockIdx(el) {
    var card = el.closest('[data-block-idx]');
    return card ? parseInt(card.dataset.blockIdx, 10) : -1;
  }

  // -- Simple field inputs (non-repeating fields on a block) ------------------
  function fieldRow(labelText, inputHtml) {
    return '<div class="vtx-field mb-2"><label class="vtx-label" style="font-size:.75rem;">' + esc(labelText) + '</label>' + inputHtml + '</div>';
  }

  function textInput(field, value, placeholder) {
    return '<input class="form-control form-control-sm" type="text" data-lb-field="' + field + '" value="' + esc(value) + '" placeholder="' + esc(placeholder || '') + '">';
  }

  function textareaInput(field, value) {
    return '<textarea class="form-control form-control-sm" rows="2" data-lb-field="' + field + '">' + esc(value) + '</textarea>';
  }

  // -- Body builders per block type --------------------------------------------
  function buildBody(block) {
    switch (block.type) {
      case 'hero':
        return fieldRow('Headline', textInput('headline', block.headline))
             + fieldRow('Subheadline', textareaInput('subheadline', block.subheadline))
             + '<div style="display:flex;gap:.5rem;">' + fieldRow('Button text', textInput('cta_text', block.cta_text)) + fieldRow('Button link', textInput('cta_link', block.cta_link, '/contact')) + '</div>'
             + fieldRow('Background image URL', textInput('image', block.image));

      case 'feature-grid':
        return fieldRow('Headline', textInput('headline', block.headline))
             + fieldRow('Columns', '<select class="form-select form-select-sm" data-lb-field="columns">'
                 + [2, 3, 4].map(function (n) { return '<option value="' + n + '"' + (block.columns === n ? ' selected' : '') + '>' + n + '</option>'; }).join('')
                 + '</select>')
             + itemsListMarkup(block);

      case 'testimonials':
        return fieldRow('Headline', textInput('headline', block.headline)) + itemsListMarkup(block);

      case 'gallery':
        return fieldRow('Headline', textInput('headline', block.headline)) + itemsListMarkup(block);

      case 'cta-banner':
        return fieldRow('Headline', textInput('headline', block.headline))
             + fieldRow('Text', textareaInput('text', block.text))
             + '<div style="display:flex;gap:.5rem;">' + fieldRow('Button text', textInput('button_text', block.button_text)) + fieldRow('Button link', textInput('button_link', block.button_link, '/contact')) + '</div>';

      case 'rich-text':
        return '<div class="vtx-field mb-2"><div data-lb-editor-container style="min-height:160px;border:1px solid var(--ps-border);border-radius:var(--ps-radius-sm);"></div>'
             + '<textarea data-lb-field="html" style="display:none;">' + esc(block.html) + '</textarea></div>';

      case 'stats':
        return itemsListMarkup(block);

      default:
        return '';
    }
  }

  function itemsListMarkup(block) {
    var spec = ITEM_SPECS[block.type];
    if (!spec) return '';
    var rows = (block.items || []).map(function (item, i) { return itemRowHtml(block.type, item, i); }).join('');
    return '<div class="vtx-lb-items" data-item-list>' + rows + '</div>'
         + '<button type="button" class="btn btn-sm btn-link p-0 mt-1" data-add-item><i class="pi pi-plus me-1"></i>Add item</button>';
  }

  function itemRowHtml(blockType, item, idx) {
    var spec = ITEM_SPECS[blockType];
    var fields = spec.map(function (f) {
      var val = item[f.key] || '';
      var input = f.textarea
        ? '<textarea class="form-control form-control-sm" rows="2" data-item-field="' + f.key + '">' + esc(val) + '</textarea>'
        : '<input class="form-control form-control-sm" type="text" data-item-field="' + f.key + '" value="' + esc(val) + '" placeholder="' + esc(f.placeholder || '') + '">';
      return '<div class="vtx-field" style="flex:1;min-width:120px;"><label class="vtx-label" style="font-size:.7rem;">' + esc(f.label) + '</label>' + input + '</div>';
    }).join('');
    return '<div class="vtx-lb-item-row" data-item-idx="' + idx + '" draggable="true">'
         + '<div class="vtx-lb-item-handle" title="Drag to reorder"><i class="pi pi-bars"></i></div>'
         + '<div class="vtx-lb-item-body" style="display:flex;gap:.5rem;flex-wrap:wrap;">' + fields + '</div>'
         + '<button type="button" class="vtx-lb-item-delete" data-delete-item title="Remove item"><i class="pi pi-x"></i></button>'
         + '</div>';
  }

  // -- Card construction --------------------------------------------------------
  function buildCard(block, idx) {
    var card = document.createElement('div');
    card.className = 'vtx-lb-card';
    card.dataset.blockIdx = String(idx);
    card.draggable = true;

    card.innerHTML =
      '<div class="vtx-lb-card-head">'
      + '<div class="vtx-lb-card-handle" title="Drag to reorder"><i class="pi pi-bars"></i></div>'
      + '<span class="vtx-tag" style="font-size:.75rem;">' + esc(BLOCK_LABELS[block.type] || block.type) + '</span>'
      + '<button type="button" class="vtx-lb-card-delete" data-delete-block title="Remove block"><i class="pi pi-trash"></i></button>'
      + '</div>'
      + '<div class="vtx-lb-card-body">' + buildBody(block) + '</div>';

    wireCard(card, block);
    return card;
  }

  function wireCard(card, block) {
    // Simple field inputs
    card.querySelectorAll('[data-lb-field]').forEach(function (input) {
      input.addEventListener('input', function () {
        var idx = currentBlockIdx(input);
        var key = input.dataset.lbField;
        blocks[idx][key] = (key === 'columns') ? parseInt(input.value, 10) : input.value;
        stagePreview();
      });
    });

    // Rich-text editor (lazy VtxEditor instance, persists across reorders since
    // the DOM node is moved, never rebuilt). This page has no [data-vtx-editor]
    // element for Vtx.autoInit() to detect, so the 'editor' component must be
    // fetched explicitly here rather than assuming window.VtxEditor exists.
    var editorContainer = card.querySelector('[data-lb-editor-container]');
    if (editorContainer && window.Vtx) {
      var textarea = card.querySelector('[data-lb-field="html"]');
      window.Vtx.load(['editor', 'media-picker'], function () {
        var vtxEd = new VtxEditor({ container: editorContainer, textarea: textarea, mediaPicker: true });
        if (textarea.value) vtxEd.setHTML(textarea.value);
      });
      // Quill's contenteditable root fires native 'input' on user edits (unlike
      // the hidden textarea VtxEditor writes to via plain property assignment,
      // see syncRichTextFields() above) - bubbles up to editorContainer.
      editorContainer.addEventListener('input', function () {
        var idx = currentBlockIdx(editorContainer);
        if (idx !== -1) blocks[idx].html = textarea.value;
        stagePreview();
      });
    }

    // Item add/delete/edit
    var addBtn = card.querySelector('[data-add-item]');
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        var idx = currentBlockIdx(addBtn);
        blocks[idx].items = blocks[idx].items || [];
        blocks[idx].items.push({});
        var list = card.querySelector('[data-item-list]');
        var itemIdx = blocks[idx].items.length - 1;
        var row = document.createElement('div');
        row.innerHTML = itemRowHtml(blocks[idx].type, {}, itemIdx);
        var newRow = row.firstElementChild;
        list.appendChild(newRow);
        wireItemRow(newRow, card);
        initItemDrag(list, card);
        stagePreview();
      });
    }
    card.querySelectorAll('[data-item-list] .vtx-lb-item-row').forEach(function (row) {
      wireItemRow(row, card);
    });
    var itemList = card.querySelector('[data-item-list]');
    if (itemList) initItemDrag(itemList, card);
  }

  function wireItemRow(row, card) {
    row.querySelectorAll('[data-item-field]').forEach(function (input) {
      input.addEventListener('input', function () {
        var blockIdx = currentBlockIdx(row);
        var itemIdx  = parseInt(row.dataset.itemIdx, 10);
        blocks[blockIdx].items[itemIdx][input.dataset.itemField] = input.value;
        stagePreview();
      });
    });
    var delBtn = row.querySelector('[data-delete-item]');
    delBtn.addEventListener('click', function () {
      var blockIdx = currentBlockIdx(row);
      var itemIdx  = parseInt(row.dataset.itemIdx, 10);
      blocks[blockIdx].items.splice(itemIdx, 1);
      row.remove();
      reindexItemRows(card);
      stagePreview();
    });
  }

  function reindexItemRows(card) {
    var list = card.querySelector('[data-item-list]');
    if (!list) return;
    Array.prototype.forEach.call(list.children, function (row, i) { row.dataset.itemIdx = String(i); });
  }

  // -- Item-level drag-drop (stopPropagation so it never bubbles into the
  //    parent block's drag handlers) -------------------------------------------
  function initItemDrag(list, card) {
    var dragSrc = null;
    Array.prototype.forEach.call(list.children, function (row) {
      if (row.dataset.wiredDrag) return;
      row.dataset.wiredDrag = '1';

      row.addEventListener('dragstart', function (e) {
        e.stopPropagation();
        dragSrc = row;
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
      });
      row.addEventListener('dragend', function (e) { e.stopPropagation(); row.classList.remove('dragging'); });
      row.addEventListener('dragover', function (e) { e.preventDefault(); e.stopPropagation(); e.dataTransfer.dropEffect = 'move'; });
      row.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!dragSrc || dragSrc === row) return;
        var blockIdx = currentBlockIdx(row);
        var srcIdx   = parseInt(dragSrc.dataset.itemIdx, 10);
        var dstIdx   = parseInt(row.dataset.itemIdx, 10);
        var moved    = blocks[blockIdx].items.splice(srcIdx, 1)[0];
        blocks[blockIdx].items.splice(dstIdx, 0, moved);
        if (srcIdx < dstIdx) row.after(dragSrc); else row.before(dragSrc);
        reindexItemRows(card);
        stagePreview();
      });
    });
  }

  // -- Block-level drag-drop ----------------------------------------------------
  var dragSrcBlock = null;
  function initBlockDrag(card) {
    card.addEventListener('dragstart', function (e) {
      dragSrcBlock = card;
      card.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    card.addEventListener('dragend', function () { card.classList.remove('dragging'); });
    card.addEventListener('dragover', function (e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; });
    card.addEventListener('drop', function (e) {
      e.preventDefault();
      if (!dragSrcBlock || dragSrcBlock === card) return;
      var srcIdx = parseInt(dragSrcBlock.dataset.blockIdx, 10);
      var dstIdx = parseInt(card.dataset.blockIdx, 10);
      var moved  = blocks.splice(srcIdx, 1)[0];
      blocks.splice(dstIdx, 0, moved);
      if (srcIdx < dstIdx) card.after(dragSrcBlock); else card.before(dragSrcBlock);
      reindexBlockCards();
      stagePreview();
    });
  }

  // -- Add / delete block --------------------------------------------------------
  document.querySelectorAll('[data-add-block]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var type  = btn.dataset.addBlock;
      var block = defaultsFor(type);
      blocks.push(block);
      var card = buildCard(block, blocks.length - 1);
      initBlockDrag(card);
      canvas.appendChild(card);
      updateEmptyState();
      stagePreview();
    });
  });

  canvas.addEventListener('click', function (e) {
    var delBtn = e.target.closest('[data-delete-block]');
    if (!delBtn) return;
    var card = delBtn.closest('[data-block-idx]');
    var idx  = parseInt(card.dataset.blockIdx, 10);
    blocks.splice(idx, 1);
    card.remove();
    reindexBlockCards();
    updateEmptyState();
    stagePreview();
  });

  // -- Save -----------------------------------------------------------------
  document.getElementById('lb-save-btn').addEventListener('click', function () {
    syncRichTextFields();
    var btn = this;
    btn.disabled = true;
    var original = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving...';

    fetch(BASE + '/admin/theme-customizer/landing-blocks/' + THEME + '/save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ csrf_token: CSRF, blocks: JSON.stringify(blocks) })
    })
      .then(function (r) { return r.json(); })
      .then(function (res) { showMsg(res.success ? 'success' : 'error', res.message || 'Saved.'); })
      .catch(function () { showMsg('error', 'Network error. Please try again.'); })
      .finally(function () { btn.disabled = false; btn.innerHTML = original; });
  });

  function showMsg(type, msg) {
    var el = document.getElementById('vtx-lb-message');
    el.className = 'vtx-alert vtx-alert-' + type + ' mb-3';
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(function () { el.style.display = 'none'; }, 4000);
  }

  // -- Initial render -------------------------------------------------------
  blocks.forEach(function (block, idx) {
    var card = buildCard(block, idx);
    initBlockDrag(card);
    canvas.appendChild(card);
  });
  updateEmptyState();
})();
