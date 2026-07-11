# Sitemap Module

The Sitemap module (`slug: sitemap`, version 0.0.2) automatically generates `/sitemap.xml` and `/robots.txt` from your published content, with a small settings screen to control what's included.

## Features

- Automatic XML sitemap at `/sitemap.xml` from published Pages, Blog posts, Events, Gallery albums, and Videos
- `/robots.txt` with a default `Disallow: /admin/` plus any admin-configured extra paths, and a `Sitemap:` line pointing at `/sitemap.xml`
- Home page and blog index included with appropriate priorities
- `SitemapProvider` interface, implemented by Events, Gallery, and Videos - any other module can contribute its own URLs the same way
- Site URL resolved from settings, falls back to the current HTTP host
- 1-hour `Cache-Control` header on the sitemap to avoid hammering the database on every crawl
- Wraps all DB queries in try-catch so one broken module/table never returns a 500 for the whole sitemap

## Installation

Go to **Admin → Modules** and click **Install** next to Sitemap (or **Update** if you already have an older version installed). No database tables are created; the module seeds these settings keys:

| Setting key | Default | Description |
|-------------|---------|--------------|
| `sitemap_include_pages` | `1` | Include published Pages in the sitemap |
| `sitemap_include_blog` | `1` | Include published Blog posts when Blog is enabled |
| `sitemap_include_events` | `1` | Include published Events when Events is enabled |
| `sitemap_include_gallery` | `1` | Include published Gallery albums when Gallery is enabled |
| `sitemap_include_videos` | `1` | Include published Videos when Videos is enabled |
| `robots_extra_disallow` | `` (empty) | Newline-separated extra `Disallow:` paths for `/robots.txt` |

Edit these from **Admin → Sitemap** (requires the `sitemap.settings` permission).

## Routes

| Method | Path | Description |
|--------|------|--------------|
| GET | `/sitemap.xml` | XML sitemap (no auth required) |
| GET | `/robots.txt` | Plain-text robots file (no auth required) |
| GET | `/admin/sitemap/settings` | Settings screen |
| POST | `/admin/sitemap/settings/save` | Save settings |

## URL Priorities

| URL | Priority | Change frequency |
|-----|----------|-------------------|
| Home page (`/`) | 1.0 | daily |
| Blog index (`/{blog_base}`) | 0.9 | daily |
| Published pages | 0.8 | monthly |
| Events index (`/events`) | 0.8 | daily |
| Published events | 0.6 | weekly |
| Published blog posts | 0.7 | weekly |
| Gallery / Videos index | 0.6 | weekly |
| Published gallery albums / videos | 0.5 | monthly |

## Sample robots.txt

```text
User-agent: *
Disallow: /admin/
Disallow: /private/

Sitemap: https://example.com/sitemap.xml
```

## Sample sitemap.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://example.com/</loc>
    <lastmod>2026-06-25</lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://example.com/about</loc>
    <lastmod>2026-06-20</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://example.com/blog/my-first-post</loc>
    <lastmod>2026-06-18</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
</urlset>
```

## SitemapProvider Interface

Any module can implement `App\Modules\Sitemap\SitemapProvider` to contribute its own URLs to the sitemap. Events, Gallery, and Videos ship real implementations today (`App/Modules/{Events,Gallery,Videos}/SitemapProvider.php`) - use them as a reference:

```php
use App\Modules\Sitemap\SitemapProvider;
use Core\Model;

class MyModuleSitemapProvider implements SitemapProvider
{
    public function getSitemapUrls(string $siteUrl): array
    {
        $items = (new Model('my_items'))
            ->select('slug, updated_at')
            ->where('status', 'published')
            ->get() ?: [];

        return array_map(fn($item) => [
            'loc'        => rtrim($siteUrl, '/') . '/items/' . $item['slug'],
            'lastmod'    => substr($item['updated_at'], 0, 10),
            'changefreq' => 'weekly',
            'priority'   => '0.6',
        ], $items);
    }
}
```

`SitemapController` only instantiates a provider when both the owning module is enabled and its own sitemap-include setting is on, so adding a provider never breaks a site that doesn't have that module installed. See [examples/sitemap-provider.php](../examples/sitemap-provider.php) for a full walkthrough.

## Permissions

`sitemap.settings` - required to view/edit the settings screen. `/sitemap.xml` and `/robots.txt` remain public routes with no permission check, accessible to all visitors and search engine crawlers.

## Notes

- Both `sitemap.xml` and `robots.txt` are registered as standard Vertext routes, so they respect the `baseUrl` setting.
- Submit your sitemap to search engines via Google Search Console or Bing Webmaster Tools at `https://yourdomain.com/sitemap.xml`.
- If your site is behind a load balancer or reverse proxy, ensure `settings.site_url` is set to the correct canonical URL so `<loc>` entries (and the `<link rel="canonical">` tag on every page) are correct.
