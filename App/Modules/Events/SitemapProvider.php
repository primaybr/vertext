<?php

declare(strict_types=1);

namespace App\Modules\Events;

use App\Modules\Sitemap\SitemapProvider as SitemapProviderInterface;
use Core\Model;

class SitemapProvider implements SitemapProviderInterface
{
    public function getSitemapUrls(string $siteUrl): array
    {
        $urls = [];

        $urls[] = [
            'loc'        => rtrim($siteUrl, '/') . '/events',
            'changefreq' => 'daily',
            'priority'   => '0.8',
        ];

        $events = (new Model('events'))
            ->select('slug, updated_at')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->get() ?: [];

        foreach ($events as $event) {
            $urls[] = [
                'loc'        => rtrim($siteUrl, '/') . '/events/' . $event['slug'],
                'lastmod'    => substr($event['updated_at'] ?? '', 0, 10),
                'changefreq' => 'weekly',
                'priority'   => '0.6',
            ];
        }

        return $urls;
    }
}
