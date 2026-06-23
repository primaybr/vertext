# Gallery Module

The Gallery module (`slug: gallery`, version 0.0.1) adds public photo albums to Vertext. Albums are backed by the Media library — images are selected from files already uploaded to Media, not uploaded separately.

## Features

- Album CRUD with cover image, description, and status
- Slug auto-generation from title (`vtx-slug`)
- Images added from the Media library via the vtx-media-picker iframe
- Drag-to-reorder images within an album (persisted via AJAX)
- Caption per image
- Public album grid and single-album lightbox (pure CSS + vanilla JS, no external libs)
- SEO meta fields per album
- Requires Media module to be installed

## Installation

Go to **Admin → Modules** and click **Install** next to Gallery. The Media module must be installed first.

## Database Tables

**`galleries`**:

| Column | Type | Description |
| ------ | ---- | ----------- |
| `id` | UUID | Primary key |
| `title` | VARCHAR(255) | Album title |
| `slug` | VARCHAR(255) | Unique URL slug |
| `description` | TEXT | Album description |
| `cover_image_id` | UUID | FK → media_files.id (optional cover photo) |
| `status` | VARCHAR(20) | `draft` or `published` |
| `meta_title` | VARCHAR(255) | SEO title |
| `meta_description` | TEXT | SEO description |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |
| `created_by` | UUID | FK → users.id |
| `updated_by` | UUID | FK → users.id |

**`gallery_items`**:

| Column | Type | Description |
| ------ | ---- | ----------- |
| `id` | UUID | Primary key |
| `gallery_id` | UUID | FK → galleries.id (CASCADE DELETE) |
| `media_file_id` | UUID | FK → media_files.id (CASCADE DELETE) |
| `caption` | TEXT | Optional caption for this photo |
| `sort_order` | INTEGER | Display order (ascending) |
| `created_at` | TIMESTAMP | |

## Admin Routes

| Method | Path | Description |
| ------ | ---- | ----------- |
| GET | `/admin/gallery` | Albums list |
| GET | `/admin/gallery/form` | Create album form (AJAX modal) |
| POST | `/admin/gallery/store` | Create album |
| GET | `/admin/gallery/{id}/form` | Edit album form (AJAX modal) |
| POST | `/admin/gallery/{id}/update` | Update album |
| POST | `/admin/gallery/{id}/delete` | Delete album + all items |
| GET | `/admin/gallery/{id}/items` | Manage images in album (full page) |
| POST | `/admin/gallery/{id}/items/add` | Add a media file to the album |
| POST | `/admin/gallery/{id}/items/reorder` | Save drag-reorder (JSON body + X-CSRF-Token header) |
| POST | `/admin/gallery/{id}/items/{itemId}/remove` | Remove an image from the album |

## Front-End Routes

| Method | Path | Description |
| ------ | ---- | ----------- |
| GET | `/gallery` | Public album grid |
| GET | `/gallery/{slug}` | Single album with lightbox |

## Permissions

| Slug | Description |
| ---- | ----------- |
| `gallery.view` | Browse album admin |
| `gallery.create` | Create albums |
| `gallery.edit` | Edit albums and manage images |
| `gallery.delete` | Delete albums |
| `gallery.publish` | Change album status |

## Adding Images

From the album items page (`/admin/gallery/{id}/items`), click **Add Image** to open the Media library picker in a full-screen iframe. Selecting a file sends it to the `items/add` endpoint via AJAX; the item appears immediately without a page reload.

## Drag-to-Reorder

Images can be reordered by dragging. On drop, the new order is POST-ed as JSON to `/items/reorder`:

```json
{ "order": ["uuid-1", "uuid-2", "uuid-3"], "csrf_token": "..." }
```

The CSRF token is also accepted via the `X-CSRF-Token` request header for programmatic use.

## Lightbox

The public album view includes a pure CSS + vanilla JS lightbox with:

- Click a photo to open
- Left/right arrow navigation
- Keyboard `←` / `→` / `Escape` support
- Click outside to close
- Photo counter and caption display
