<?php

declare(strict_types=1);

namespace App\Modules\Analytics;

/**
 * Tracker - records a front-end page view into analytics_pageviews.
 *
 * Called from ThemeEngine::render() after the layout renders.
 * Fails silently - analytics must never break a page load.
 *
 * Privacy:
 *   - IP is SHA-256 hashed with a daily salt (not reversible, rotates daily)
 *   - Only the hostname of the referrer is stored (no path/query)
 *   - Common bots and crawlers are excluded via user-agent check
 */
class Tracker
{
    private const BOT_PATTERNS = [
        'bot', 'crawl', 'spider', 'slurp', 'baidu', 'yandex', 'sogou',
        'facebot', 'ia_archiver', 'semrush', 'ahrefs', 'screaming',
        'chrome-lighthouse', 'headlesschrome', 'phantomjs', 'python-requests',
        'go-http-client', 'curl/', 'wget/', 'libwww',
    ];

    public static function record(string $urlPath, ?string $pageTitle, ?string $referrerHost): void
    {
        try {
            // Check module setting
            $setting = (new \Core\Model('settings'))
                ->select('value')
                ->where('key', 'analytics_enabled')
                ->get(1);
            if ($setting && !(bool) $setting['value']) {
                return;
            }

            // Bot filter
            $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
            if ($ua) {
                foreach (self::BOT_PATTERNS as $pattern) {
                    if (str_contains($ua, $pattern)) {
                        return;
                    }
                }
            }

            // Device detection (mobile keyword heuristic, privacy-safe)
            $deviceType = ($ua && preg_match('/(android|iphone|ipad|ipod|mobile|blackberry|windows phone)/i', $ua))
                ? 'mobile'
                : 'desktop';

            $ip   = $_SERVER['HTTP_CF_CONNECTING_IP']
                 ?? $_SERVER['HTTP_X_FORWARDED_FOR']
                 ?? $_SERVER['REMOTE_ADDR']
                 ?? '';
            // Use only first IP if comma-separated (proxy chain)
            $ip = explode(',', $ip)[0];
            $ipHash = $ip ? hash('sha256', trim($ip) . date('Y-m-d')) : null;

            (new \Core\Model('analytics_pageviews'))->withoutTimestamps()->save([
                'url_path'      => substr($urlPath, 0, 500),
                'page_title'    => $pageTitle ? substr($pageTitle, 0, 255) : null,
                'referrer_host' => $referrerHost ? substr($referrerHost, 0, 255) : null,
                'ip_hash'       => $ipHash,
                'device_type'   => $deviceType,
                'viewed_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Never surface analytics errors to visitors
        }
    }

    /**
     * Record a search query from the Search module.
     * Silently skipped if the analytics_search_queries table does not yet exist.
     */
    public static function recordSearch(string $query, int $resultCount): void
    {
        if (strlen(trim($query)) < 2) {
            return;
        }
        try {
            $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
            if ($ua) {
                foreach (self::BOT_PATTERNS as $pattern) {
                    if (str_contains($ua, $pattern)) {
                        return;
                    }
                }
            }

            $ip = explode(',', $_SERVER['REMOTE_ADDR'] ?? '')[0];
            $ipHash = $ip ? hash('sha256', trim($ip) . date('Y-m-d')) : null;

            (new \Core\Model('analytics_search_queries'))->withoutTimestamps()->save([
                'query'        => substr(trim($query), 0, 500),
                'result_count' => $resultCount,
                'ip_hash'      => $ipHash,
                'searched_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Never surface analytics errors
        }
    }

    /** Extract hostname from an HTTP_REFERER value. Returns null on failure. */
    public static function referrerHost(): ?string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (!$referer) {
            return null;
        }
        $host = parse_url($referer, PHP_URL_HOST);
        return $host ?: null;
    }
}
