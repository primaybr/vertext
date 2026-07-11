<?php

declare(strict_types=1);

namespace App\Modules\ThemeCustomizer;

/**
 * Loads each theme's default landing-block content from its own
 * App/Themes/{slug}/landing-seed.json fixture (co-located with theme.json,
 * theme.css, etc - the existing "everything about a theme lives in its own
 * folder" convention).
 */
class LandingBlocksSeeder
{
    public static function forTheme(string $slug): array
    {
        $file = ROOT . 'App' . DS . 'Themes' . DS . $slug . DS . 'landing-seed.json';

        if (file_exists($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Defensive fallback - never a hard failure if a theme has no fixture yet.
        return [
            [
                'type'         => 'hero',
                'headline'     => 'Welcome',
                'subheadline'  => '',
                'cta_text'     => '',
                'cta_link'     => '',
                'image'        => '',
            ],
        ];
    }
}
