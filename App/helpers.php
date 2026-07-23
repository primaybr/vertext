<?php

declare(strict_types=1);

if (!function_exists('__')) {
    /**
     * Translate a key using the current locale.
     *
     * Keys use dot-notation: "admin.save" loads App/Lang/{locale}/admin.php
     * and looks up key "save". Keys without a dot use app.php.
     *
     * @param string $key          e.g. "admin.save_changes" or "app.no_results"
     * @param array  $replacements map of :placeholder => value
     */
    function __(string $key, array $replacements = []): string
    {
        return \App\CMS\I18n::get($key, $replacements);
    }
}

if (!function_exists('site_path')) {
    /**
     * Same-site page URL carrying the current visitor's locale prefix (bare
     * path for the default locale, /xx/ for everything else). Use for every
     * internal page link (nav, footer, product links, form actions, ...) -
     * never for asset URLs (CSS/JS/images), which must stay unprefixed.
     *
     * @param string $baseUrl The site's base URL (Controller::$baseUrl, or an
     *                         absolute domain e.g. from settings.site_url)
     * @param string $path    e.g. "/product/some-slug" or "/search"
     */
    function site_path(string $baseUrl, string $path): string
    {
        return \App\CMS\I18n::path($baseUrl, $path);
    }
}

if (!function_exists('asset_url')) {
    /**
     * Version-fingerprinted asset URL for HTTP cache busting.
     *
     * asset_url('css/admin.css') -> "/assets/css/admin.css?v=<hash>"
     * The hash is derived from Version::APP, so every release invalidates
     * browser caches without hand-bumping ?v= numbers in views.
     *
     * @param string $path    Path relative to /assets/
     * @param string $baseUrl Optional absolute base URL prefix
     */
    function asset_url(string $path, string $baseUrl = ''): string
    {
        static $v = null;
        if ($v === null) {
            $v = substr(hash('crc32b', \App\CMS\Version::APP), 0, 8);
        }
        return $baseUrl . '/assets/' . ltrim($path, '/') . '?v=' . $v;
    }
}
