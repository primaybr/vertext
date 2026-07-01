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
