# Template System

Vertext uses a custom template engine with double-brace syntax, filters, and raw output support.

## Variable Output

### Escaped Output (default - safe for HTML)

```html
<h1>{{ $post->title }}</h1>
<p>{{ $user->name }}</p>
```

Variables are passed through `htmlspecialchars()` before output. Always use `{{ }}` for user-provided content.

### Raw (Unescaped) Output

```html
{!! $post->body !!}
```

Only use `{!! !!}` for content you control or have already sanitized (e.g. Quill rich text stored as HTML).

### Template Comments

```html
{# This is a comment - not rendered in HTML output #}
```

## Conditionals and Loops

The template engine supports standard PHP conditionals and loops embedded naturally:

```html
<?php if ($post->status === 'published'): ?>
    <span class="badge badge-success">Published</span>
<?php else: ?>
    <span class="badge badge-warning">Draft</span>
<?php endif; ?>

<?php foreach ($posts as $post): ?>
    <article>
        <h2>{{ $post->title }}</h2>
        <p>{{ $post->excerpt }}</p>
    </article>
<?php endforeach; ?>
```

## Rendering Views from Controllers

### With Admin Layout

```php
// Renders the view wrapped in the admin base layout
$this->adminRender(
    'admin/posts/index',     // view path (relative to App/Views/)
    ['posts' => $posts],     // data passed to the view
    'All Posts',             // page title
    'blog'                   // active menu key
);
```

### Partial (No Layout)

```php
// Returns rendered HTML string - used for AJAX modal responses
$html = $this->renderPartial('admin/posts/_form', ['post' => null]);
echo $html;
```

### Public / Web Views

```php
// Renders using the default public layout
$this->render('default/home', ['featured' => $posts]);
```

## View File Locations

| Type | Location |
|------|----------|
| Admin views | `App/Views/admin/` |
| Public views | `App/Views/default/` |
| Error pages | `App/Views/error/` |
| Setup wizard | `App/Views/setup/` |
| Module views | `App/Views/modules/{slug}/` (deployed by install) |
| Admin layouts | `App/Views/admin/_layouts/base.php`, `auth.php` |

## Admin Layout Variables

The base admin layout (`admin/_layouts/base.php`) expects these variables passed from `adminRender()`:

| Variable | Source | Description |
|----------|--------|-------------|
| `$title` | 3rd arg | Page `<title>` and heading |
| `$activeMenu` | 4th arg | Highlights the correct nav item |
| Flash messages | Session | Auto-rendered from `$this->flash()` calls |

## Flash Messages

Set a flash message from any controller:

```php
$this->flash('success', 'Post published.');
$this->flash('error', 'Something went wrong.');
$this->flash('warning', 'Check your settings.');
$this->flash('info', 'Draft saved.');
```

The base layout renders these automatically as dismissable banners at the top of the page.

## CSRF Token in Forms

Use either:

```html
<?= csrf_field() ?>
```

or:

```html
<?= \Core\Security\CSRF::getTokenInput() ?>
```

This outputs a hidden `<input type="hidden" name="_token" value="...">`.

## Asset URLs

Reference assets with the configured asset base URL:

```html
<link rel="stylesheet" href="/assets/css/styles.css?v=142">
<script src="/assets/js/admin.js?v=1"></script>
```

Use cache-busting version query parameters (`?v=N`) to force browsers to re-fetch after changes.

## Module View Example

A module view at `App/Modules/Blog/Views/admin/posts/index.php` is deployed to `App/Views/modules/blog/admin/posts/index.php` on install, and referenced as:

```php
$this->adminRender('modules/blog/admin/posts/index', $data, 'Posts', 'blog');
```
