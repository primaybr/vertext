<?php

declare(strict_types=1);

namespace App\Modules\ThemeCustomizer;

class ThemeCustomizerHelper
{
    private static ?array $settings = null;

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

    public static function getCss(): string
    {
        $s = self::load();

        $primary   = self::sanitizeColor($s['primary_color'] ?? '') ?: '#2563EB';
        $fontSans  = self::sanitizeFont($s['font_family'] ?? '');
        $customCss = $s['custom_css'] ?? '';

        $vars = '';
        if ($primary !== '#2563EB') {
            $hover  = self::darkenHex($primary, 15);
            $light  = self::hexWithAlpha($primary, 0.1);
            $vars  .= "--ps-primary:{$primary};";
            $vars  .= "--ps-primary-hover:{$hover};";
            $vars  .= "--ps-primary-light:{$light};";
        }
        if ($fontSans !== '') {
            $vars .= "--ps-font-sans:{$fontSans};";
        }

        $out = '';
        if ($vars !== '') {
            $out .= "<style>:root{{$vars}}</style>\n";
        }
        if (trim($customCss) !== '') {
            $out .= '<style>' . $customCss . '</style>' . "\n";
        }
        return $out;
    }

    private static function sanitizeColor(string $val): string
    {
        $val = trim($val);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $val)) {
            return strtoupper($val);
        }
        return '';
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
}
