# Videos Module

The Videos module (`slug: videos`, version 0.0.1) manages YouTube and Vimeo embeds in Vertext. Videos are stored by URL; poster thumbnails are fetched and cached locally on first save.

## Features

- Store YouTube, Vimeo, or generic embed URLs
- Video ID extraction and local poster thumbnail caching (YouTube `maxresdefault.jpg`; Vimeo via public API)
- Slug auto-generation from title (`vtx-slug`)
- Drag-sortable video grid in admin
- Status workflow (`draft` / `published`)
- SEO meta fields
- Public video grid with thumbnail previews and play icon
- Single video page with lazy iframe player - poster shown first, iframe injected on click
- `sort_order` for manual listing control

## Installation

Go to **Admin → Modules** and click **Install** next to Videos.

## Database Table

**`videos`**:

| Column | Type | Description |
| ------ | ---- | ----------- |
| `id` | UUID | Primary key |
| `title` | VARCHAR(255) | Video title |
| `slug` | VARCHAR(255) | Unique URL slug |
| `provider` | VARCHAR(20) | `youtube`, `vimeo`, or `other` |
| `embed_url` | VARCHAR(500) | Original URL (watch, share, or embed format) |
| `video_id` | VARCHAR(100) | Extracted provider video ID |
| `thumbnail_path` | VARCHAR(500) | Absolute path to cached poster image |
| `description` | TEXT | Description (plain text or HTML) |
| `status` | VARCHAR(20) | `draft` or `published` |
| `meta_title` | VARCHAR(255) | SEO title |
| `meta_description` | TEXT | SEO description |
| `sort_order` | INTEGER | Display order (ascending) |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |
| `created_by` | UUID | FK → users.id |
| `updated_by` | UUID | FK → users.id |

## Admin Routes

| Method | Path | Description |
| ------ | ---- | ----------- |
| GET | `/admin/videos` | Video grid list |
| GET | `/admin/videos/form` | Create video form (AJAX modal) |
| POST | `/admin/videos/store` | Create video |
| GET | `/admin/videos/{id}/form` | Edit form (AJAX modal) |
| POST | `/admin/videos/{id}/update` | Update video |
| POST | `/admin/videos/{id}/delete` | Delete video (removes cached thumbnail) |

## Front-End Routes

| Method | Path | Description |
| ------ | ---- | ----------- |
| GET | `/videos` | Public video grid |
| GET | `/videos/{slug}` | Single video page |

## Permissions

| Slug | Description |
| ---- | ----------- |
| `videos.view` | Browse video admin |
| `videos.create` | Add videos |
| `videos.edit` | Edit videos |
| `videos.delete` | Delete videos |
| `videos.publish` | Change video status |

## Supported URL Formats

**YouTube**:

- `https://www.youtube.com/watch?v=dQw4w9WgXcQ`
- `https://youtu.be/dQw4w9WgXcQ`
- `https://www.youtube.com/embed/dQw4w9WgXcQ`

**Vimeo**:

- `https://vimeo.com/123456789`
- `https://vimeo.com/video/123456789`

**Other**: paste the full `<iframe src="...">` URL directly.

## Poster Thumbnail Caching

When a video is saved, the controller:

1. Extracts the video ID from the URL
2. Fetches the poster image from `img.youtube.com/vi/{id}/maxresdefault.jpg` (YouTube) or the Vimeo public API
3. Saves the image to `Public/uploads/video-thumbs/{provider}_{id}.jpg`

If the fetch fails, the video is still saved - thumbnail is non-critical. On the public grid, a YouTube fallback URL (`hqdefault.jpg`) is used when no local thumbnail exists.

## Lazy Iframe Player

On the single video page, the full `<iframe>` is not loaded on page load - only the poster image is shown. When the user clicks the poster, the iframe is injected and playback starts immediately (`autoplay=1`). This avoids unnecessary network requests and improves page performance.
