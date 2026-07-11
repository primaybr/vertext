<?php

declare(strict_types=1);

namespace App\Modules\ThemeCustomizer;

class ThemeCustomizerHelper
{
    private static ?array $settings = null;

    /** Pending, unsaved settings for the live-preview iframe (see preview()
     *  action) - picked up automatically by getCss() so theme layout.php
     *  files don't need to know about preview mode at all. */
    private static array $previewOverrides = [];

    public static function setPreviewOverrides(array $overrides): void
    {
        self::$previewOverrides = $overrides;
    }

    private static function load(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }
        try {
            $rows = (new \Core\Model('settings'))
                ->select('key, value')
                ->where('grp', 'theme-customizer')
                ->get() ?: [];
            self::$settings = array_column($rows, 'value', 'key');
        } catch (\Exception) {
            self::$settings = [];
        }
        return self::$settings;
    }

    public static function getLogoUrl(): string
    {
        $s = self::load();
        return trim($s['logo_url'] ?? '');
    }

    private const RADIUS_SCALES = [
        'sharp'   => ['sm' => '0px',    'md' => '0px'],
        'subtle'  => null, // theme's own defaults - no override emitted
        'rounded' => ['sm' => '0.625rem', 'md' => '0.875rem'],
    ];

    /** Directory (relative to Public/) where generated CSS files are written. */
    private const GENERATED_REL = 'assets/generated/';

    /**
     * Builds the raw CSS text (no wrapping tags) for the current settings, merged
     * with any active preview overrides.
     */
    private static function buildCss(): string
    {
        $s = array_merge(self::load(), self::$previewOverrides);

        $primary     = self::sanitizeColor($s['primary_color'] ?? '') ?: '#1E3A5F';
        $fontSans    = self::sanitizeFont($s['font_family'] ?? '');
        $cornerStyle = self::sanitizeCornerStyle($s['corner_style'] ?? '');
        $customCss   = $s['custom_css'] ?? '';

        $vars = '';
        $body = '';
        if ($primary !== '#1E3A5F') {
            $hover  = self::darkenHex($primary, 15);
            $light  = self::hexWithAlpha($primary, 0.1);
            $rgb    = self::hexToRgbList($primary);
            $vars  .= "--ps-primary:{$primary};";
            $vars  .= "--ps-primary-hover:{$hover};";
            $vars  .= "--ps-primary-light:{$light};";
            $vars  .= "--ps-primary-rgb:{$rgb};";
            // Front-end themes (App/Themes/*) read --clr-* tokens, not --ps-* -
            // bridge them here so the accent picker actually affects the public site.
            // --clr-accent-fill/-rgb are included too: the theme's own DEFAULT accent
            // deliberately differs between --clr-accent (flips for dark-mode contrast)
            // and --clr-accent-fill (stays navy, for fills with white text), but once
            // an admin picks a custom color it should apply uniformly everywhere -
            // same reasoning as the light/dark split, just moot once it's a fixed choice.
            $vars  .= "--clr-accent:{$primary};";
            $vars  .= "--clr-accent-fill:{$primary};";
            $vars  .= "--clr-accent-rgb:{$rgb};";
            $vars  .= "--clr-link:{$primary};";
        }
        if ($fontSans !== '') {
            $vars .= "--ps-font-sans:{$fontSans};";
            // Front themes hardcode font-family on body rather than a variable.
            $body .= "body{font-family:{$fontSans};}";
        }
        $radiusScale = self::RADIUS_SCALES[$cornerStyle] ?? null;
        if ($radiusScale !== null) {
            $vars .= "--radius-sm:{$radiusScale['sm']};--radius-md:{$radiusScale['md']};";
        }

        $out = ($vars !== '' ? ":root{{$vars}}" : '') . $body;
        if (trim($customCss) !== '') {
            $out .= $customCss;
        }
        return $out;
    }

    /**
     * Returns the <link>-able URL for the current theme CSS.
     *
     * Writes to a real file rather than echoing an inline <style> block, because
     * the site's CSP sends `style-src 'self'` with no `unsafe-inline` - an inline
     * <style> tag is silently ignored by the browser under that policy (this was a
     * real bug: color/font/corner-style/custom-CSS settings looked like they saved
     * but never visibly applied, on both the live site and the customizer preview).
     * A same-origin external stylesheet is allowed under `style-src 'self'` with no
     * CSP changes needed.
     *
     * While a live-preview override is active (setPreviewOverrides() was called
     * earlier in this request), writes to a separate, always-fresh preview file
     * instead of the live one, so editing the customizer never touches what real
     * visitors see.
     */
    public static function cssUrl(string $baseUrl): string
    {
        $dir = ROOT . 'Public' . DS . 'assets' . DS . 'generated' . DS;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ''; // Can't write - layout simply omits the <link>; base theme.css still applies.
        }

        if (self::$previewOverrides !== []) {
            $file = $dir . 'theme-preview.css';
            if (@file_put_contents($file, self::buildCss(), LOCK_EX) === false) {
                return '';
            }
            // Cache-bust on every call - the filename never changes but the content
            // does on every debounced edit, and browsers cache <link> hrefs by URL.
            return rtrim($baseUrl, '/') . '/' . self::GENERATED_REL . 'theme-preview.css?t=' . time();
        }

        $file = $dir . 'theme-custom.css';
        if (!is_file($file)) {
            self::regenerateCustomCssFile();
        }
        if (!is_file($file)) {
            return '';
        }
        return rtrim($baseUrl, '/') . '/' . self::GENERATED_REL . 'theme-custom.css?v=' . filemtime($file);
    }

    /** Regenerates the live (saved-settings) CSS file. Call after Save. */
    public static function regenerateCustomCssFile(): void
    {
        $dir = ROOT . 'Public' . DS . 'assets' . DS . 'generated' . DS;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return;
        }
        @file_put_contents($dir . 'theme-custom.css', self::buildCss(), LOCK_EX);
    }

    private static function sanitizeColor(string $val): string
    {
        $val = trim($val);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $val)) {
            return strtoupper($val);
        }
        return '';
    }

    private static function sanitizeCornerStyle(string $val): string
    {
        $val = trim($val);
        return isset(self::RADIUS_SCALES[$val]) ? $val : 'subtle';
    }

    private static function sanitizeFont(string $val): string
    {
        $val = trim($val);
        if ($val === '' || $val === 'system') {
            return '';
        }
        // Strip any characters that could break out of a CSS string context
        return preg_replace('/[^a-zA-Z0-9\s,\'\-]/', '', $val);
    }

    private static function darkenHex(string $hex, int $amount): string
    {
        $hex = ltrim($hex, '#');
        $r   = max(0, hexdec(substr($hex, 0, 2)) - $amount);
        $g   = max(0, hexdec(substr($hex, 2, 2)) - $amount);
        $b   = max(0, hexdec(substr($hex, 4, 2)) - $amount);
        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    private static function hexWithAlpha(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        $r   = hexdec(substr($hex, 0, 2));
        $g   = hexdec(substr($hex, 2, 2));
        $b   = hexdec(substr($hex, 4, 2));
        return "rgba({$r},{$g},{$b},{$alpha})";
    }

    private static function hexToRgbList(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $r   = hexdec(substr($hex, 0, 2));
        $g   = hexdec(substr($hex, 2, 2));
        $b   = hexdec(substr($hex, 4, 2));
        return "{$r}, {$g}, {$b}";
    }
}
