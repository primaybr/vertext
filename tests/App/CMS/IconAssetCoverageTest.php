<?php

declare(strict_types=1);

namespace Tests\App\CMS;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class IconAssetCoverageTest extends TestCase
{
    private const EXCLUDED = [
        'xs', 'sm', 'lg', 'xl', '1x', '2x', '3x', '4x', 'spin',
        'arrow-', // Incomplete dynamic class prefix, such as pi-arrow-{$direction}.
        'name',  // Explanatory placeholder used by the icon-system documentation.
    ];

    public function testConcreteIconReferencesHaveShippedGlyphs(): void
    {
        $defined = $this->definedIconSlugs();
        $referenced = $this->referencedIconSlugs();
        $missing = array_values(array_diff($referenced, $defined));
        sort($missing);

        self::assertSame(
            [],
            $missing,
            'Concrete pi-* references without a glyph in Public/assets/css/icons.css: '
                . implode(', ', $missing)
        );
    }

    /** @return list<string> */
    private function definedIconSlugs(): array
    {
        $css = file_get_contents(ROOT . 'Public' . DS . 'assets' . DS . 'css' . DS . 'icons.css');
        self::assertIsString($css);

        preg_match_all('/^\.pi-([a-z0-9-]+)\s*\{/m', $css, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /** @return list<string> */
    private function referencedIconSlugs(): array
    {
        $referenced = [];

        foreach (['App', 'Core', 'Public'] as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(ROOT . $directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js'], true)) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                if (!is_string($contents)) {
                    continue;
                }

                preg_match_all('/\bpi-([a-z0-9][a-z0-9-]*)(?![a-z0-9-])/', $contents, $matches);
                foreach ($matches[1] ?? [] as $slug) {
                    if (!in_array($slug, self::EXCLUDED, true)) {
                        $referenced[] = $slug;
                    }
                }
            }
        }

        $referenced = array_values(array_unique($referenced));
        sort($referenced);

        return $referenced;
    }
}
