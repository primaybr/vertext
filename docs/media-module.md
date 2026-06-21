# Media Module

The Media module (`slug: media`, version 0.0.1) provides a file upload library, a browsable grid interface, and a reusable media picker modal for use in other modules.

## Features

- Drag-and-drop and click-to-upload file uploads
- Grid browser with pagination (24 files per page)
- Image dimension storage (width, height)
- Alt text and caption metadata editing
- Organized storage by year/month (`uploads/2024/06/`)
- PHP execution blocked in upload directory via `.htaccess`
- Reusable media picker modal (`vtx-media-picker`) for selecting images in other modules

## Installation

Go to **Admin → Modules** and click **Install** next to Media. Creates the `media_files` table and seeds 4 permissions.

## Database Table

**`media_files`**:

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGSERIAL | Primary key |
| `filename` | VARCHAR | Stored filename (randomized) |
| `original_name` | VARCHAR | Original upload filename |
| `path` | VARCHAR | Relative path to file |
| `url` | VARCHAR | Public URL |
| `mime_type` | VARCHAR | Detected MIME type |
| `size` | INTEGER | File size in bytes |
| `width` | INTEGER | Image width (null for non-images) |
| `height` | INTEGER | Image height (null for non-images) |
| `alt` | TEXT | Alt text |
| `caption` | TEXT | Caption |
| `uploaded_by` | BIGINT | FK → users.id |
| `created_at` | TIMESTAMP | Upload time |

## Admin Routes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/media` | Media grid |
| POST | `/admin/media/upload` | Upload a file (AJAX) |
| GET | `/admin/media/{id}/edit-form` | Edit metadata form (AJAX modal) |
| POST | `/admin/media/{id}/update` | Save metadata |
| POST | `/admin/media/{id}/delete` | Delete file + record |
| GET | `/admin/media/picker` | Media picker modal (used by vtx-media-picker) |

## Permissions

| Permission slug | Description |
|----------------|-------------|
| `media.view` | Browse the media library |
| `media.upload` | Upload new files |
| `media.edit` | Edit alt text and caption |
| `media.delete` | Delete files |

## Upload Security

- MIME type validated via `finfo` — not just extension
- Extension validated against a configurable allowlist
- Uploaded files stored as `timestamp_hexrandom.ext` to prevent predictable paths
- Files organized under `Public/uploads/YYYY/MM/`
- `.htaccess` in the upload directory blocks direct PHP execution:
  ```apache
  php_flag engine off
  Options -ExecCGI
  AddType text/plain .php .php3 .phtml
  ```

## Using the Media Picker in a Module

Add a media picker field to any module form:

```html
<!-- Hidden input to store selected media ID -->
<input type="hidden" name="featured_image_id" id="featured-image-id" value="{{ $post->featured_image_id ?? '' }}">

<!-- Preview of the currently selected image -->
<div id="featured-image-preview">
    <?php if (!empty($post->featured_image_url)): ?>
        <img src="{{ $post->featured_image_url }}" style="max-width: 200px;">
    <?php endif; ?>
</div>

<!-- The picker component -->
<div
    data-component="vtx-media-picker"
    data-target="#featured-image-id"
    data-preview="#featured-image-preview"
    data-url="/admin/media/picker"
>
    <button type="button" class="btn btn-secondary">Choose Image</button>
    <button type="button" class="btn btn-outline-danger" data-clear>Remove</button>
</div>
```

The `vtx-media-picker` JS component opens the picker modal, lets the user browse/upload, and on selection sets the hidden input value and updates the preview.

## Views

Admin views are deployed to `App/Views/modules/media/admin/media/`:

```
admin/media/
├── index.php         — Grid browser with upload zone
├── picker.php        — Modal picker interface (loaded in iframe/ajax)
└── _edit_form.php    — Metadata edit form (alt text, caption)
```

## Linking Media in Your Module

After the user selects a media item, store the `media_files.id`. To display the image, join with `media_files`:

```php
$post = $this->db
    ->table('posts')
    ->select(['posts.*', 'mf.url AS featured_image_url', 'mf.alt AS featured_image_alt'])
    ->join('media_files mf', 'posts.featured_image_id = mf.id', 'LEFT')
    ->where('posts.id', $id)
    ->first();
```
