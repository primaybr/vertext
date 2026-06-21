# Blog Module

The Blog module (`slug: blog`, version 0.0.2) is a full-featured publishing system with posts, categories, tags, comment moderation, and a public frontend.

## Features

- Rich text editor (Quill) for post content
- SEO fields (meta title, meta description, reading time)
- Featured image integration with the Media module picker
- Publish / draft / archive workflow
- Category and tag taxonomy
- Public comment submission with moderation queue
- Analytics dashboard with 30-day publication chart
- Bulk operations on posts and comments
- Configurable blog settings

## Installation

Go to **Admin → Modules** and click **Install** next to Blog. This creates all required tables and seeds 17 permissions.

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

| Method | Path | Description |
|--------|------|-------------|
| GET | `/blog` | Blog home (paginated post list) |
| GET | `/blog/{slug}` | Single post view with comments |
| GET | `/blog/category/{slug}` | Posts filtered by category |
| POST | `/blog/{slug}/comment` | Submit a comment |

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
├── dashboard/index.php       — Analytics and stats
├── posts/index.php           — Post list with search and bulk actions
├── posts/_form.php           — Create/edit post (Quill editor, media picker, tag input)
├── categories/index.php      — Category list
├── categories/_form.php      — Category form
├── tags/index.php            — Tag list
├── tags/_form.php            — Tag form
├── comments/index.php        — Comment moderation
└── settings/index.php        — Blog settings
```

Public views are deployed to `App/Views/modules/blog/front/`:

```
front/
├── index.php     — Blog home (list with pagination)
├── post.php      — Single post with comment form
└── category.php  — Category filtered list
```
