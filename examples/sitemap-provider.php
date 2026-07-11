<?php
/**
 * Implementing SitemapProvider in a module
 *
 * The SitemapProvider interface lets your module contribute URLs to the
 * auto-generated /sitemap.xml. Implement the interface, then register the
 * provider in your Module::registerRoutes() or a service boot hook.
 *
 * Requires: Sitemap module installed and enabled.
 *
 * Events, Gallery, and Videos now ship real implementations of this pattern -
 * see App/Modules/{Events,Gallery,Videos}/SitemapProvider.php for working
 * reference code wired into SitemapController's provider map.
 */

use App\Modules\Sitemap\SitemapProvider;

// ------------------------------------------------------------------
// 1. Implement the SitemapProvider interface
// ------------------------------------------------------------------
class EventsSitemapProvider implements SitemapProvider
{
    /**
     * Return URLs this module contributes to the sitemap.
     *
     * @param  string $siteUrl  Canonical base URL (e.g. "https://example.com")
     * @return array<int, array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    public function getSitemapUrls(string $siteUrl): array
    {
        // Query your published content
        $db     = \Core\Database::connection();
        $events = $db->table('events')
            ->where('status', 'published')
            ->orderBy('updated_at', 'DESC')
            ->get();

        $urls = [];

        // Events listing page
        $urls[] = [
            'loc'        => $siteUrl . '/events',
            'lastmod'    => date('Y-m-d'),
            'changefreq' => 'daily',
            'priority'   => '0.8',
        ];

        // Individual event pages
        foreach ($events as $event) {
            $urls[] = [
                'loc'        => $siteUrl . '/events/' . $event['slug'],
                'lastmod'    => substr($event['updated_at'] ?? $event['created_at'], 0, 10),
                'changefreq' => 'weekly',
                'priority'   => '0.6',
            ];
        }

        return $urls;
    }
}

// ------------------------------------------------------------------
// 2. Priority guidelines
// ------------------------------------------------------------------
// 1.0  - Home page (reserved for SitemapController)
// 0.9  - Blog/content index pages
// 0.8  - Static pages (About, Contact, etc.)
// 0.7  - Blog posts
// 0.6  - Sub-pages, archive items
// 0.5  - Taxonomy pages (categories, tags)
// 0.3  - Low-value pages (pagination pages, old content)
//
// Do not assign 1.0 to everything - search engines may de-prioritize
// sitemaps where all URLs share the same priority.

// ------------------------------------------------------------------
// 3. changefreq values
// ------------------------------------------------------------------
// always   - changes on every access (not recommended)
// hourly   - rapidly changing content
// daily    - news articles, event listings
// weekly   - blog posts, product pages
// monthly  - static pages, documentation
// yearly   - archived content, legal pages
// never    - permalinks that will never change

// ------------------------------------------------------------------
// 4. Return array shape
// ------------------------------------------------------------------
// Each entry must have:
//   'loc'        (string) - full absolute URL including scheme
//   'lastmod'    (string) - ISO date YYYY-MM-DD
//   'changefreq' (string) - see above
//   'priority'   (string) - "0.0" through "1.0"
//
// 'lastmod' and 'changefreq' and 'priority' are technically optional per
// the sitemap spec, but including them helps search engines crawl efficiently.
