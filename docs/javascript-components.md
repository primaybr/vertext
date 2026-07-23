# JavaScript Components

Vertext ships a `vtx-*` component library for the admin panel. The library is in `Public/assets/js/` and loaded by the admin layout.

## Activation Pattern

Most components are activated by the `data-component` attribute on any HTML element:

```html
<div data-component="vtx-component-name" data-option="value">
    <!-- component content -->
</div>
```

**Two components are the exception** and use their own dedicated boolean attribute instead of `data-component` - `vtx-select` (`data-vtx-select`) and `vtx-media-picker` (`data-vtx-media-picker`). See each component's own section below for its real attribute set; do not assume `data-component="vtx-select"` or `data-component="vtx-media-picker"` work - they do not activate anything.

Components are auto-initialized when the DOM is ready, and also after AJAX modal injections.

> **CSS architecture:** All component styles live in CSS files - no component injects inline `style` attributes or `cssText`. Core components (vtx-slug, vtx-upload) use `admin.css`; module-scoped components (vtx-tags, vtx-media-picker) use their module's CSS file so they can be tree-shaken when the module is disabled.

---

## vtx-chart

Bar/line chart powered by Chart.js.

```html
<div
    data-component="vtx-chart"
    data-type="bar"
    data-labels='["Jan","Feb","Mar","Apr","May"]'
    data-values='[10,25,15,30,20]'
    data-label="Posts Published"
    style="max-width: 600px;"
>
    <canvas></canvas>
</div>
```

| Attribute | Description |
|-----------|-------------|
| `data-type` | `bar`, `line`, or `doughnut` |
| `data-labels` | JSON array of axis labels |
| `data-values` | JSON array of numeric values |
| `data-label` | Dataset label |

---

## vtx-datatable

Client-side sortable, filterable table.

```html
<table data-component="vtx-datatable" data-per-page="25">
    <thead>
        <tr>
            <th data-sortable>Title</th>
            <th data-sortable>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <!-- rows -->
    </tbody>
</table>
```

| Attribute | Description |
|-----------|-------------|
| `data-per-page` | Rows per page (default: 10) |
| `data-sortable` on `<th>` | Makes the column sortable |

---

## vtx-editor

Rich text editor powered by Quill.

```html
<div data-component="vtx-editor" data-target="#post-body" data-theme="snow">
    <!-- editor mounts here -->
</div>
<textarea id="post-body" name="body" hidden>{{ $post->body ?? '' }}</textarea>
```

The editor writes its HTML output to the hidden `<textarea>` specified by `data-target` on form submit.

| Attribute | Description |
|-----------|-------------|
| `data-target` | CSS selector for the hidden textarea to sync |
| `data-theme` | `snow` (toolbar) or `bubble` (inline) |

---

## vtx-media-picker

Media library picker modal. Activated by `data-vtx-media-picker` (NOT `data-component`) on the trigger `<button>` itself - not a wrapping `<div>`. See [Media Module docs](media-module.md) for full integration guide.

```html
<button type="button" class="btn btn-secondary"
        data-vtx-media-picker
        data-target-id-input="image-id-input"
        data-target-url-input="image-url-input"
        data-target-preview="image-preview"
        data-target-preview-wrap="image-preview-wrap">
    Choose Image
</button>
<input type="hidden" id="image-id-input" name="image_id">
<input type="hidden" id="image-url-input" name="image_url">
<div id="image-preview-wrap" hidden>
    <img id="image-preview" src="" alt="">
</div>
```

| Attribute | Description |
|-----------|-------------|
| `data-target-id-input` | Element id of the hidden input to receive the selected media's id |
| `data-target-url-input` | Element id of the hidden input to receive the selected media's url |
| `data-target-preview` | Element id of an `<img>` to update with the selected image (optional) |
| `data-target-preview-wrap` | Element id of a wrapper to un-hide once an image is selected (optional) |

Also exposes a static, buttonless API for programmatic use (e.g. building a custom multi-image grid): `VtxMediaPicker.open(function (url, id) { ... })` opens the same picker and invokes the callback with the selected image's URL/id on selection.

**CSS classes** (in `media.css`):

| Class | Purpose |
|-------|---------|
| `.vtx-media-picker-panel` | Floating picker panel (fixed overlay) |
| `.vtx-media-picker-header` | Panel header bar |
| `.vtx-media-picker-title` | Title text inside header |
| `.vtx-media-picker-close` | Close button |
| `.vtx-picker-loading` | Loading spinner state |
| `.vtx-picker-error` | Error message state |

---

## vtx-search

Live AJAX search input for filtering tables/lists.

```html
<input
    data-component="vtx-search"
    data-url="/admin/blog/tags/search"
    data-results="#search-results"
    placeholder="Search tags..."
    type="text"
>
<div id="search-results"></div>
```

| Attribute | Description |
|-----------|-------------|
| `data-url` | AJAX endpoint; receives `q` query param |
| `data-results` | Container element to render results into |
| `data-debounce` | Debounce delay in ms (default: 300) |

---

## vtx-select

Enhanced `<select>` with search, keyboard navigation, and optional AJAX-loaded options. Activated by `data-vtx-select` (NOT `data-component`). The native `<select>` stays in the DOM (hidden) so normal form submission is unaffected; the dropdown itself renders as a body-level portal so it's never clipped by an ancestor `overflow:hidden` panel or modal.

```html
<select data-vtx-select data-searchable data-placeholder="Select category..." name="category_id">
    <option value="">-- Select Category --</option>
    <?php foreach ($categories as $cat): ?>
        <option value="{{ $cat->id }}" <?= $post->category_id == $cat->id ? 'selected' : '' ?>>
            {{ $cat->name }}
        </option>
    <?php endforeach; ?>
</select>
```

| Attribute | Description |
|-----------|-------------|
| `data-vtx-select` | Required - activates the component on this `<select>` |
| `data-searchable` | Adds a search box inside the dropdown; add this once the option list can exceed a screenful (brand/category/user pickers) - omit for small fixed enums (status, language) |
| `data-placeholder` | Placeholder text shown when nothing is selected (defaults to the first empty-value `<option>`'s text, or "Select…") |
| `data-ajax-url` | Lazy-loads options from this URL on first open instead of reading static `<option>`s - expects a JSON array of `{value, label, disabled}` |
| `multiple` (native) | Renders as a multi-select with removable tag chips |

Imperative API (rarely needed - the declarative attributes above cover normal use): `new Vtx.Select({ el: selectEl, searchable: true, placeholder: 'Choose…', ajaxUrl: '/admin/...', onChange: fn })`, with instance methods `getValue()`, `setValue(val)`, `setOptions([{value,label,disabled}])`, `destroy()` (accessible on an enhanced element via `selectEl._vtxSelect`).

---

## vtx-tags

Tag input field with autocomplete.

```html
<div
    data-component="vtx-tags"
    data-target="#tags-input"
    data-search-url="/admin/blog/tags/search"
    data-initial='[{"id":1,"name":"PHP"},{"id":3,"name":"Laravel"}]'
>
    <!-- tag chips render here -->
</div>
<input type="hidden" id="tags-input" name="tag_ids" value="">
```

| Attribute | Description |
|-----------|-------------|
| `data-target` | Hidden input that receives a comma-separated list of tag IDs |
| `data-search-url` | AJAX endpoint for autocomplete suggestions |
| `data-initial` | JSON array of pre-selected tags `[{id, name}]` |

**CSS classes** (in `blog.css`):

| Class | Purpose |
|-------|---------|
| `.vtx-tags-wrap` | Outer wrapper (flex row, border, focus ring) |
| `.vtx-tags-dropdown` | Autocomplete suggestion dropdown |
| `.vtx-tags-option` | Individual suggestion item |
| `.vtx-tags-option--added` | Already-selected option (dimmed) |
| `.vtx-tags-option-badge` | "Added" badge on option |
| `.vtx-tag-chip-remove` | Remove button inside a tag chip |

---

## vtx-slug

Auto-generates a URL slug from a title input field. Loaded on demand via `Vtx.load(['slug'], fn)`.

```html
<input type="text"  name="title" data-vtx-slug-source placeholder="Post title">
<input type="text"  name="slug"  data-vtx-slug-target placeholder="auto-generated">
```

```js
Vtx.load(['slug'], function () {
    Vtx.slug.init(
        document.querySelector('[data-vtx-slug-source]'),
        document.querySelector('[data-vtx-slug-target]')
    );
});
```

**Behaviour:**

- Watches the source input (debounced 300 ms) and writes a slug to the target
- Slug logic mirrors `Str::slug()`: lowercase, non-alphanumeric → hyphen, collapse, trim
- Once the user manually edits the target field, auto-generation stops and a **Reset** link appears to re-enable it
- Has no effect when the target already has a value on page load (edit forms)

| Attribute | Element | Description |
| --------- | ------- | ----------- |
| `data-vtx-slug-source` | `<input>` | The field whose value is slugified |
| `data-vtx-slug-target` | `<input>` | The slug field that receives the generated value |

**CSS classes** (in `admin.css`):

| Class | Purpose |
|-------|---------|
| `.vtx-slug-hint` | The hint text shown below the slug field |
| `.vtx-slug-reset` | The "Reset to auto" link (styled as a muted inline link) |

---

## vtx-tooltip

Small hover/focus tooltip for any element - primarily used to label icon-only
buttons. Auto-loaded whenever `[data-vtx-tooltip]` is present on the page (see
`Vtx.autoInit()`); also loaded on demand when AJAX modal content introduces the
first one on a page that had none at initial load.

```html
<button type="button" class="btn btn-sm btn-outline-danger" data-vtx-tooltip="Uninstall">
  <i class="pi pi-trash"></i>
</button>
```

No JS call needed - just add the attribute. The tooltip text is the attribute's
value.

**Behaviour:**

- Listeners are delegated on `document` (mouseover/mouseout, focusin/focusout),
  not bound per-element - elements added to the DOM later (AJAX modal content)
  work immediately, no re-init call required
- Shows after a short delay (300 ms) on hover or keyboard focus, hides on
  mouseleave/blur/`Escape`
- Auto-flips below the trigger if there isn't enough room above
- If the trigger has no other accessible name (no visible text, no existing
  `aria-label`), sets `aria-label` from the same attribute value - icon-only
  buttons get a real screen-reader label, not just a hover-only tooltip. If the
  trigger already has visible text or its own `aria-label`, it's left alone.

| Attribute | Element | Description |
| --------- | ------- | ----------- |
| `data-vtx-tooltip` | any | The tooltip text |

**CSS classes** (in `admin.css`):

| Class | Purpose |
|-------|---------|
| `.vtx-tooltip` | The tooltip bubble (single shared node, repositioned per trigger) |
| `.vtx-tooltip.is-visible` | Applied while shown |
| `.vtx-tooltip.is-below` | Applied when flipped to render below the trigger instead of above |

---

## vtx-upload

Drag-and-drop file uploader with progress bar.

```html
<div
    data-component="vtx-upload"
    data-url="/admin/media/upload"
    data-accept="image/*,application/pdf"
    data-max-size="10485760"
>
    <div class="upload-zone">
        <p>Drag files here or click to browse</p>
    </div>
    <div class="upload-progress" hidden></div>
</div>
```

| Attribute | Description |
|-----------|-------------|
| `data-url` | POST endpoint for uploads |
| `data-accept` | MIME type allowlist (same as `<input accept>`) |
| `data-max-size` | Max file size in bytes |

The component POSTs files via AJAX with `multipart/form-data` and fires a `vtx:upload:complete` custom event on success.

**CSS classes** (in `admin.css`):

| Class | Purpose |
|-------|---------|
| `.vtx-upload-overlay` | Full-area drag-over overlay (absolute, semi-transparent) |
| `.vtx-upload-overlay-text` | Text shown inside the drag-over overlay |
| `.vtx-upload-bar` | Progress bar fill (width set via JS `style.width`) |

---

## Modal Pattern (vtx-modal-trigger)

Any link or button with class `vtx-modal-trigger` opens its `href` in a modal overlay:

```html
<a href="/admin/users/form" class="btn btn-primary vtx-modal-trigger">Add User</a>
```

The response from that URL is rendered inside the modal. The modal closes after a successful form submission that returns a redirect or a `{ success: true }` JSON response.
