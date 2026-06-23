# Pages Module

The Pages module (`slug: pages`, version 0.0.1) adds static page management to Vertext. Pages have their own URL slugs, are rendered through ThemeEngine, and support SEO meta fields.

## Features

- Create, edit, publish, and delete static pages
- Slug auto-generation from title (`vtx-slug`)
- Quill rich text editor for content
- Excerpt and SEO meta fields (title, description)
- `sort_order` to control page listing order
- Front-end rendering via ThemeEngine (uses the active public theme)
- Wildcard front-end route — any single-segment URL that isn't claimed by another module resolves to a page

## Installation

Go to **Admin → Modules** and click **Install** next to Pages.

## Database Table

**`pages`**:

| Column | Type | Description |
| ------ | ---- | ----------- |
| `id` | UUID | Primary key |
| `title` | VARCHAR(255) | Page title |
| `slug` | VARCHAR(255) | Unique URL slug |
| `content` | TEXT | HTML content (Quill output) |
| `excerpt` | TEXT | Short summary |
| `status` | VARCHAR(20) | `draft` or `published` |
| `template` | VARCHAR(100) | Theme template name (default: `default`) |
| `meta_title` | VARCHAR(255) | SEO title (overrides `title` in `<title>`) |
| `meta_description` | TEXT | SEO meta description |
| `sort_order` | INTEGER | Display order (ascending) |
| `created_at` | TIMESTAMP | Creation timestamp |
| `updated_at` | TIMESTAMP | Last edit timestamp |
| `created_by` | UUID | FK → users.id |
| `updated_by` | UUID | FK → users.id |

## Admin Routes

| Method | Path | Description |
| ------ | ---- | ----------- |
| GET | `/admin/pages` | Pages list |
| GET | `/admin/pages/form` | Create form (AJAX modal) |
| POST | `/admin/pages/store` | Create a page |
| GET | `/admin/pages/{id}/form` | Edit form (AJAX modal) |
| POST | `/admin/pages/{id}/update` | Update a page |
| POST | `/admin/pages/{id}/delete` | Delete a page |

## Front-End Routes

| Method | Path | Description |
| ------ | ---- | ----------- |
| GET | `/{slug}` | Render a published page |

The route pattern `([a-z0-9][a-z0-9\-]*)` matches any single-segment lowercase slug. If no published page is found for the slug, a 404 is returned. This route is registered last so it never shadows more-specific module routes (e.g. `/blog`, `/gallery`).

## Permissions

| Slug | Description |
| ---- | ----------- |
| `pages.view` | Browse pages list |
| `pages.create` | Create new pages |
| `pages.edit` | Edit existing pages |
| `pages.delete` | Delete pages |
| `pages.publish` | Change page status |

## Front-End View

The view `App/Modules/Pages/Views/front/page.php` is content-only (no `<html>/<head>/<body>`). It receives:

| Variable | Description |
| -------- | ----------- |
| `$page` | Page row from DB |
| `$baseUrl` | Site base URL |
| `$page_title` | Used by ThemeEngine for `<title>` |
| `$page_description` | Used by ThemeEngine for meta description |
