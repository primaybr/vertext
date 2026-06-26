<?php

declare(strict_types=1);

namespace App\Modules\Sitemap\Controllers;

use Core\Model;
use App\CMS\ModuleLoader;

class SitemapController extends \Core\Controller
{
    public function index(): void
    {
        // Resolve absolute site URL
        $siteUrl = $this->resolveSiteUrl();

        // Load sitemap settings
        try {
            $rows = (new Model('settings'))
                ->select('key, value')
                ->whereRaw("grp = 'sitemap'", [])
                ->get() ?: [];
            $cfg = array_column($rows, 'value', 'key');
        } catch (\Throwable) {
            $cfg = [];
        }

        $includePages = ($cfg['sitemap_include_pages'] ?? '1') !== '0';
        $includeBlog  = ($cfg['sitemap_include_blog']  ?? '1') !== '0';

        $urls = [];

        // Home page
        $urls[] = [
            'loc'        => rtrim($siteUrl, '/') . '/',
            'changefreq' => 'daily',
            'priority'   => '1.0',
        ];

        // Published pages
        if ($includePages) {
            try {
                $pages = (new Model('pages'))
                    ->select('slug, updated_at')
                    ->where('status', 'published')
                    ->get() ?: [];
                foreach ($pages as $page) {
                    $urls[] = [
                        'loc'        => rtrim($siteUrl, '/') . '/' . ltrim($page['slug'], '/'),
                        'lastmod'    => substr($page['updated_at'] ?? '', 0, 10),
                        'changefreq' => 'monthly',
                        'priority'   => '0.8',
                    ];
                }
            } catch (\Throwable) {}
        }

        // Blog posts
        if ($includeBlog && ModuleLoader::isEnabled('blog')) {
            try {
                $bpRow = (new Model('settings'))
                    ->select('value')
                    ->where('key', 'blog_base_path')
                    ->where('grp', 'blog')
                    ->get(1);
                $rawBase  = trim($bpRow['value'] ?? 'blog', '/');
                $blogBase = $rawBase === '' ? '' : '/' . $rawBase;

                // Blog index page
                if ($blogBase) {
                    $urls[] = [
                        'loc'        => rtrim($siteUrl, '/') . $blogBase,
                        'changefreq' => 'daily',
                        'priority'   => '0.9',
                    ];
                }

                $posts = (new Model('blog_posts'))
                    ->select('slug, updated_at, published_at')
                    ->where('status', 'published')
                    ->whereNull('deleted_at')
                    ->orderBy('published_at', 'DESC')
                    ->get() ?: [];

                foreach ($posts as $post) {
                    $lastmod = substr($post['updated_at'] ?? $post['published_at'] ?? '', 0, 10);
                    $urls[]  = [
                        'loc'        => rtrim($siteUrl, '/') . $blogBase . '/' . $post['slug'],
                        'lastmod'    => $lastmod,
                        'changefreq' => 'weekly',
                        'priority'   => '0.7',
                    ];
                }
            } catch (\Throwable) {}
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $entry) {
            echo "  <url>\n";
            echo '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                echo '    <lastmod>' . htmlspecialchars($entry['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
            }
            if (!empty($entry['changefreq'])) {
                echo '    <changefreq>' . $entry['changefreq'] . "</changefreq>\n";
            }
            if (!empty($entry['priority'])) {
                echo '    <priority>' . $entry['priority'] . "</priority>\n";
            }
            echo "  </url>\n";
        }

        echo '</urlset>';
        exit;
    }

    private function resolveSiteUrl(): string
    {
        try {
            $row = (new Model('settings'))->select('value')->where('key', 'site_url')->get(1);
            $url = rtrim($row['value'] ?? '', '/');
            if ($url) {
                return $url;
            }
        } catch (\Throwable) {}

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $this->baseUrl;
    }
}
