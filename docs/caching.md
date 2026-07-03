# Caching

Vertext layers four caches. All cached artifacts live under `Cache/` and are
wiped by **Admin > Settings > Clear All Cache**.

## 1. Full-page cache (v0.0.8, opt-in)

`App\CMS\PageCache` stores complete rendered HTML of public Pages and Blog
requests in `Cache/pages/` for **10 minutes**. Enable it with the
**Full-page cache** toggle in Settings > Cache.

A request is served from (or stored to) the cache only when ALL of these hold:

- The request is a `GET` with no query string (a single `?lang=` is allowed)
- No admin is logged in and no site member (Members module) is logged in
- No flash message is pending in the session
- The rendered HTML does not embed a CSRF token - pages containing forms
  (contact, embedded `[form]` shortcodes, comment forms) are never cached, so
  per-visitor tokens are never shared

Cache hits carry an `X-Vertext-Cache: hit` response header.

Invalidation: creating, updating, or deleting a post or page, saving Settings,
and any Navigation change flush the whole page cache (`PageCache::flushPages()`).
Call it from your own module whenever you change publicly rendered data.

## 2. Fragment cache (v0.0.8)

`PageCache::remember($name, $producer, $ttl)` is a small read-through JSON
cache in `Cache/fragments/`. The navigation menu (`NavHelper::getMenu()`) uses
it with a 5-minute TTL; any Navigation change invalidates it.

```php
$rows = \App\CMS\PageCache::remember('my_expensive_list', function () {
    return (new \Core\Model('big_table'))->get() ?: [];
}, 300);
\App\CMS\PageCache::forgetFragment('my_expensive_list'); // targeted invalidation
```

## 3. Query & template cache (framework)

The Phuse layer caches SELECT results and compiled templates under `Cache/`
(configured in `Config/Database.php`). This is transparent to app code.

## 4. Asset fingerprinting (v0.0.8)

Layout views append `?v=<hash of Version::APP>` to `styles.css`, `admin.css`,
`admin.js`, `scripts.js`, and theme assets. Every release changes the hash, so
browsers refetch changed assets automatically - no more hand-bumped `?v=142`.

In your own views use the helper:

```php
<link rel="stylesheet" href="<?php echo asset_url('css/admin.css', $baseUrl); ?>">
```

## Stats

Settings > Cache shows live counts of cached pages, fragments, and other files
plus the total size (`PageCache::stats()`).

## Rule of thumb

After changing any view, layout, CSS, or JS during development, clear `Cache/`
(or use the admin button) - compiled templates persist otherwise.
