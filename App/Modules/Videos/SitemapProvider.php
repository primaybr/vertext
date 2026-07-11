<?php

declare(strict_types=1);

namespace App\Modules\Videos;

use App\Modules\Sitemap\SitemapProvider as SitemapProviderInterface;
use Core\Model;

class SitemapProvider implements SitemapProviderInterface
{
    public function getSitemapUrls(string $siteUrl): array
    {
        $urls = [];

        $urls[] = [
            'loc'        => rtrim($siteUrl, '/') . '/videos',
            'changefreq' => 'weekly',
            'priority'   => '0.6',
        ];

        $videos = (new Model('videos'))
            ->select('slug, updated_at')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->get() ?: [];

        foreach ($videos as $video) {
            $urls[] = [
                'loc'        => rtrim($siteUrl, '/') . '/videos/' . $video['slug'],
                'lastmod'    => substr($video['updated_at'] ?? '', 0, 10),
                'changefreq' => 'monthly',
                'priority'   => '0.5',
            ];
        }

        return $urls;
    }
}
