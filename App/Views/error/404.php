<?php
declare(strict_types=1);

/**
 * Core\Exception\Error::show() include()s this file directly, outside any
 * request-routing context - there's no Controller instance here, so $baseUrl
 * has to be derived independently rather than read from one. Renders through
 * ThemeEngine so this page gets the real active theme's header/nav/footer,
 * generated CSS, and SEO meta - the same as every other front-end page -
 * instead of a hand-duplicated copy that drifts out of sync with it.
 */
$baseUrl = '';
try {
    $row = (new \Core\Model('settings'))->select('value')->where('key', 'site_url')->get(1);
    $baseUrl = rtrim($row['value'] ?? '', '/');
} catch (\Throwable) {
}

if ($baseUrl === '' && isset($_SERVER['HTTP_HOST'])) {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
}

\App\Theme\ThemeEngine::render('error/_404-content', [
    'baseUrl'    => $baseUrl,
    'page_title' => 'Page Not Found',
]);
