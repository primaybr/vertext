# JavaScript Components

Vertext ships a `vtx-*` component library for the admin panel. Components are activated by the `data-component` attribute on any HTML element. The library is in `Public/assets/js/` and loaded by the admin layout.

## Activation Pattern

All components use a consistent data-attribute API:

```html
<div data-component="vtx-component-name" data-option="value">
    <!-- component content -->
</div>
```

Components are auto-initialized when the DOM is ready, and also after AJAX modal injections.

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

Media library picker modal. See [Media Module docs](media-module.md) for full integration guide.

```html
<div
    data-component="vtx-media-picker"
    data-target="#image-id-input"
    data-preview="#image-preview"
    data-url="/admin/media/picker"
>
    <button type="button" class="btn btn-secondary">Choose Image</button>
</div>
```

| Attribute | Description |
|-----------|-------------|
| `data-target` | Hidden input to receive the selected media ID |
| `data-preview` | Container to show the selected image preview |
| `data-url` | URL of the media picker endpoint |

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

Enhanced `<select>` with search and keyboard navigation.

```html
<select data-component="vtx-select" name="category_id">
    <option value="">-- Select Category --</option>
    <?php foreach ($categories as $cat): ?>
        <option value="{{ $cat->id }}" <?= $post->category_id == $cat->id ? 'selected' : '' ?>>
            {{ $cat->name }}
        </option>
    <?php endforeach; ?>
</select>
```

No extra attributes required. The component enhances any `<select>` with `data-component="vtx-select"`.

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

---

## Modal Pattern (vtx-modal-trigger)

Any link or button with class `vtx-modal-trigger` opens its `href` in a modal overlay:

```html
<a href="/admin/users/form" class="btn btn-primary vtx-modal-trigger">Add User</a>
```

The response from that URL is rendered inside the modal. The modal closes after a successful form submission that returns a redirect or a `{ success: true }` JSON response.
