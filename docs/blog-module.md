# Blog Module

The Blog module (`slug: blog`, version 0.0.4) is a full-featured publishing system with posts, categories, tags, comment moderation, RSS feed, and a public frontend.

## Features

- Rich text editor (Quill) for post content
- SEO fields (meta title, meta description, reading time)
- Featured image integration with the Media module picker
- Publish / draft / archive workflow
- Category and tag taxonomy
- Public comment submission with moderation queue
- Analytics dashboard with 30-day publication chart
- Bulk operations on posts and comments
- **RSS 2.0 feed** - auto-linked in theme `<head>` when Blog is enabled
- Configurable blog settings with dynamic URL path and SEO redirect support

## Installation

Go to **Admin → Modules** and click **Install** next to Blog. This creates all required tables and seeds 17 permissions. A setup wizard runs after install to configure the URL path and blog identity.

## Database Tables

| Table | Description |
|-------|-------------|
| `posts` | Core post data (title, slug, body, status, SEO fields, author) |
| `post_categories` | Category master (name, slug) |
| `post_category_pivot` | Post ↔ Category (many-to-many) |
| `post_tags` | Tag master (name, slug) |
| `post_tag_pivot` | Post ↔ Tag (many-to-many) |
| `blog_comments` | User comments (author, email, body, status) |

## Admin Routes

### Dashboard

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/blog` | Analytics dashboard |

### Posts

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/blog/posts` | List all posts (paginated, searchable) |
| GET | `/admin/blog/posts/form` | Create post form |
| POST | `/admin/blog/posts/store` | Save new post |
| GET | `/admin/blog/posts/{id}/form` | Edit post form |
| POST | `/admin/blog/posts/{id}/update` | Update post |
| POST | `/admin/blog/posts/{id}/delete` | Delete post |
| POST | `/admin/blog/posts/bulk` | Bulk action (publish/archive/delete) |

### Categories

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/blog/categories` | List categories |
| GET | `/admin/blog/categories/form` | Create category form |
| POST | `/admin/blog/categories/store` | Save category |
| GET | `/admin/blog/categories/{id}/form` | Edit category |
| POST | `/admin/blog/categories/{id}/update` | Update category |
| POST | `/admin/blog/categories/{id}/delete` | Delete category |

### Tags

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/blog/tags` | List tags |
| POST | `/admin/blog/tags/store` | Save tag |
| GET | `/admin/blog/tags/{id}/form` | Edit tag form |
| POST | `/admin/blog/tags/{id}/update` | Update tag |
| POST | `/admin/blog/tags/{id}/delete` | Delete tag |
| GET | `/admin/blog/tags/search` | AJAX tag search (for tag input) |

### Comments

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/blog/comments` | Comment moderation queue |
| POST | `/admin/blog/comments/{id}/approve` | Approve a comment |
| POST | `/admin/blog/comments/{id}/spam` | Mark as spam |
| POST | `/admin/blog/comments/{id}/delete` | Delete a comment |
| POST | `/admin/blog/comments/bulk` | Bulk moderate |

### Settings

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/blog/settings` | Blog settings form |
| POST | `/admin/blog/settings/save` | Save blog settings |

## Public Routes

The base path (`/blog` by default) is configurable from Blog Settings. All routes below use `{base}` to represent it.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/{base}` | Blog home (paginated post list) |
| GET | `/{base}/{slug}` | Single post view with comments |
| GET | `/{base}/category/{slug}` | Posts filtered by category |
| POST | `/{base}/{slug}/comment` | Submit a comment |
| GET | `/{base}/feed.rss` | RSS 2.0 feed of recent published posts |

### Changing the Base Path

Changing `blog_base_path` from Blog Settings automatically:

- Clears the route cache and compiled template cache so the new routes and any nav links take effect on the next request.
- Updates Blog's own item in the primary navigation menu to point at the new path.
- Removes Blog's nav item entirely if the new path is the site root (`/`), since Blog then serves the homepage and a separate "Blog" link would be redundant. Moving off the root later re-creates the nav item.
- Optionally keeps the old path working as a permanent redirect (`path_change_mode = redirect`), or drops it immediately (`permanent`).

When the base path is `/`, Blog's post route is registered centrally in `Config/Routes.php` (after all other module routes) instead of inside `Blog/Module.php::registerRoutes()`, so it can't shadow other modules' front-end routes (e.g. `/contact`, `/events`) the way a module-local catch-all would if module load order changed.

## RSS Feed

The feed at `/{base}/feed.rss` is an RSS 2.0 document containing the 20 most recent published posts. It includes:

- `atom:link` self-reference
- `content:encoded` with the full post body (CDATA)
- `<enclosure>` tag for posts with a featured image

The theme `<head>` automatically includes:

```html
<link rel="alternate" type="application/rss+xml" title="Blog Feed" href="/blog/feed.rss">
```

This is computed in `ThemeEngine` and injected into both bundled themes whenever the Blog module is enabled.

## Permissions

| Permission slug | Description |
|----------------|-------------|
| `posts.view` | See post list and post detail |
| `posts.create` | Write new posts |
| `posts.edit` | Edit existing posts |
| `posts.publish` | Change status to published |
| `posts.delete` | Delete posts |
| `categories.view` | See categories |
| `categories.create` | Add categories |
| `categories.edit` | Edit categories |
| `categories.delete` | Remove categories |
| `tags.view` | See tags |
| `tags.create` | Add tags |
| `tags.edit` | Edit tags |
| `tags.delete` | Remove tags |
| `comments.view` | See comment queue |
| `comments.moderate` | Approve or spam comments |
| `comments.delete` | Delete comments |
| `blog.settings` | Access blog settings |

## Post Statuses

| Status | Meaning |
|--------|---------|
| `draft` | Not public; only visible to admins |
| `published` | Visible on the public frontend |
| `archived` | Hidden from public and admin lists; not deleted |

## Comment Statuses

| Status | Meaning |
|--------|---------|
| `pending` | Awaiting moderation |
| `approved` | Visible on the public post page |
| `spam` | Hidden; kept for review |

## Views

Admin views are deployed to `App/Views/modules/blog/admin/`:

```
admin/
├── dashboard/index.php       - Analytics and stats
├── posts/index.php           - Post list with search and bulk actions
├── posts/_form.php           - Create/edit post (Quill editor, media picker, tag input)
├── categories/index.php      - Category list
├── categories/_form.php      - Category form
├── tags/index.php            - Tag list
├── tags/_form.php            - Tag form
├── comments/index.php        - Comment moderation
└── settings/index.php        - Blog settings
```

Public views are deployed to `App/Views/modules/blog/front/`:

```
front/
├── index.php     - Blog home (list with pagination)
├── post.php      - Single post with comment form
└── category.php  - Category filtered list
```
