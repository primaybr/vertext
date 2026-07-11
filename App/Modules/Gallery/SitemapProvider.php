<?php

declare(strict_types=1);

namespace App\Modules\Gallery;

use App\Modules\Sitemap\SitemapProvider as SitemapProviderInterface;
use Core\Model;

class SitemapProvider implements SitemapProviderInterface
{
    public function getSitemapUrls(string $siteUrl): array
    {
        $urls = [];

        $urls[] = [
            'loc'        => rtrim($siteUrl, '/') . '/gallery',
            'changefreq' => 'weekly',
            'priority'   => '0.6',
        ];

        $galleries = (new Model('galleries'))
            ->select('slug, updated_at')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->get() ?: [];

        foreach ($galleries as $gallery) {
            $urls[] = [
                'loc'        => rtrim($siteUrl, '/') . '/gallery/' . $gallery['slug'],
                'lastmod'    => substr($gallery['updated_at'] ?? '', 0, 10),
                'changefreq' => 'monthly',
                'priority'   => '0.5',
            ];
        }

        return $urls;
    }
}
