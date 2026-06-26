<?php

declare(strict_types=1);

namespace App\Modules\Sitemap;

/**
 * Implement this interface in any module that wants to contribute URLs to the sitemap.
 *
 * Return an array of URL entries; each entry is an associative array with keys:
 *   - loc        (required) — absolute URL string
 *   - lastmod    (optional) — Y-m-d date string
 *   - changefreq (optional) — always|hourly|daily|weekly|monthly|yearly|never
 *   - priority   (optional) — 0.0 to 1.0 as a string
 */
interface SitemapProvider
{
    public function getSitemapUrls(string $siteUrl): array;
}
