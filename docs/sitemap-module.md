# Sitemap Module

The Sitemap module (`slug: sitemap`, version 0.0.1) automatically generates a `/sitemap.xml` from published pages and blog posts, with no admin UI required.

## Features

- Automatic XML sitemap at `/sitemap.xml` from published Pages and Blog posts
- Home page and blog index included with appropriate priorities
- `SitemapProvider` interface for future modules to contribute their own URLs
- Site URL resolved from settings, falls back to the current HTTP host
- 1-hour `Cache-Control` header to avoid hammering the database on every crawl
- Wraps all DB queries in try-catch so a missing table never returns a 500

## Installation

Go to **Admin → Modules** and click **Install** next to Sitemap. No database tables are created; the module seeds two settings keys:

| Setting key | Default | Description |
|-------------|---------|-------------|
| `sitemap_include_pages` | `1` | Include published Pages in the sitemap |
| `sitemap_include_blog` | `1` | Include published Blog posts when Blog is enabled |

## Public Route

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sitemap.xml` | XML sitemap (no auth required) |

## URL Priorities

| URL | Priority | Change frequency |
|-----|----------|-----------------|
| Home page (`/`) | 1.0 | daily |
| Blog index (`/{blog_base}`) | 0.9 | daily |
| Published pages | 0.8 | monthly |
| Published blog posts | 0.7 | weekly |

## Sample Output

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

Any module can implement `App\Modules\Sitemap\SitemapProvider` to contribute its own URLs to the sitemap in future releases:

```php
use App\Modules\Sitemap\SitemapProvider;

class MyModuleSitemapProvider implements SitemapProvider
{
    public function getSitemapUrls(string $siteUrl): array
    {
        $items = $this->db->table('my_items')
            ->where('status', 'published')
            ->get();

        return array_map(fn($item) => [
            'loc'        => $siteUrl . '/items/' . $item['slug'],
            'lastmod'    => substr($item['updated_at'], 0, 10),
            'changefreq' => 'weekly',
            'priority'   => '0.6',
        ], $items);
    }
}
```

See [examples/sitemap-provider.php](../examples/sitemap-provider.php) for a complete implementation example.

## Permissions

No permissions. `/sitemap.xml` is a public route accessible to all visitors and search engine crawlers.

## Notes

- The `sitemap.xml` path is registered as a standard Vertext route, so it respects the `baseUrl` setting.
- Submit your sitemap to search engines via Google Search Console or Bing Webmaster Tools at `https://yourdomain.com/sitemap.xml`.
- If your site is behind a load balancer or reverse proxy, ensure `settings.site_url` is set to the correct canonical URL so `<loc>` entries are correct.
