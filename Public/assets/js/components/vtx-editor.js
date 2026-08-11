/* vtx-editor.js - Quill v2 adapter for Vertext CMS */
(function (root) {
    'use strict';

    var QUILL_JS  = (window.VTX_ASSETS_URL || '') + 'js/vendors/quill.min.js';
    var QUILL_CSS = (window.VTX_ASSETS_URL || '') + 'css/vendors/quill.snow.css';

    var _quillLoaded = false;
    var _cssLoaded   = false;
    var _queue       = [];

    function loadQuill(cb) {
        if (_quillLoaded) { cb(); return; }
        _queue.push(cb);
        if (_queue.length > 1) return;

        if (!_cssLoaded) {
            var link = document.createElement('link');
            link.rel  = 'stylesheet';
            link.href = QUILL_CSS;
            document.head.appendChild(link);
            _cssLoaded = true;
        }

        var s = document.createElement('script');
        s.src = QUILL_JS;
        s.onload = function () {
            _quillLoaded = true;
            _queue.forEach(function (fn) { fn(); });
            _queue = [];
        };
        document.head.appendChild(s);
    }

    var TOOLBAR = [
        [{ header: [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['blockquote', 'code-block'],
        ['link', 'image'],
        ['clean']
    ];

    function VtxEditor(opts) {
        this.container    = opts.container;
        this.textarea     = opts.textarea;
        this.onWordCount  = opts.onWordCount  || null;
        this._mediaPicker = opts.mediaPicker  || false;
        this._quill       = null;
        this._init();
    }

    VtxEditor.prototype._init = function () {
        var self = this;
        loadQuill(function () {
            self._quill = new Quill(self.container, {
                theme: 'snow',
                modules: {
                    toolbar: {
                        container: TOOLBAR,
                        handlers: {
                            image: function () {
                                var range = self._quill.getSelection(true);
                                var idx   = range ? range.index : self._quill.getLength();
                                function insertImage(url) {
                                    if (!url) return;
                                    self._quill.insertEmbed(idx, 'image', url, Quill.sources.USER);
                                    self._quill.setSelection(idx + 1, Quill.sources.SILENT);
                                }
                                if (self._mediaPicker && window.VtxMediaPicker && window.VtxMediaPicker.open) {
                                    window.VtxMediaPicker.open(function (url) { insertImage(url); });
                                } else {
                                    var url = window.prompt('Image URL:');
                                    insertImage(url);
                                }
                            }
                        }
                    }
                },
                placeholder: 'Start writing...'
            });

            self._quill.on('text-change', function () {
                if (self.textarea) {
                    self.textarea.value = self._quill.root.innerHTML;
                }
                if (self.onWordCount) {
                    var text  = self._quill.getText().trim();
                    var words = text.length ? text.split(/\s+/).length : 0;
                    self.onWordCount(words);
                }
            });

            var form = self.container.closest('form');
            if (form) {
                form.addEventListener('submit', function () {
                    if (self.textarea) {
                        self.textarea.value = self._quill.root.innerHTML;
                    }
                }, true);
            }

            if (window.Vtx) Vtx._register('editor', { el: self.container, instance: self });
        });
    };

    VtxEditor.prototype.setHTML = function (html) {
        var self = this;
        var apply = function () {
            if (!self._quill) return;
            self._quill.setContents([]);
            self._quill.clipboard.dangerouslyPasteHTML(0, html);
        };
        if (this._quill) {
            apply();
        } else {
            loadQuill(apply);
        }
    };

    VtxEditor.prototype.getHTML = function () {
        return this._quill ? this._quill.root.innerHTML : '';
    };

    root.VtxEditor = VtxEditor;

    document.querySelectorAll('[data-vtx-editor]').forEach(function (ta) {
        var wrap = document.createElement('div');
        wrap.className = 'vtx-editor-wrap';
        var container = document.createElement('div');
        container.style.cssText = 'min-height:220px;';
        wrap.appendChild(container);
        ta.parentNode.insertBefore(wrap, ta);
        ta.style.display = 'none';
        var editor = new VtxEditor({ container: container, textarea: ta });
        if (ta.value) {
            editor.setHTML(ta.value);
        }
    });

}(window));
