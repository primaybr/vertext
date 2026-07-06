<?php

declare(strict_types=1);

namespace App\CMS\Cli;

/**
 * Scaffold a new Vertext bundle manifest.
 *
 * Usage: php vertext make:bundle <bundle-slug>
 *
 * Generates:
 *   App/Bundles/{slug}/bundle.json
 */
final class MakeBundle
{
    public static function run(string $slug): never
    {
        // -- Validate ----------------------------------------------------------
        if (!preg_match('/^[a-z][a-z0-9\-]+$/', $slug)) {
            self::error(
                "Bundle slug must be lowercase kebab-case and at least 2 characters.\n" .
                "  Valid:   marketing-suite, events-portal, my-bundle\n" .
                "  Invalid: MarketingSuite, marketing_suite, -bundle"
            );
        }

        $destDir = BASE_PATH . "/App/Bundles/{$slug}";

        if (is_dir($destDir)) {
            self::error("Bundle already exists at App/Bundles/{$slug}/");
        }

        if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            self::error("Failed to create directory: {$destDir}");
        }

        self::write("{$destDir}/bundle.json", self::bundleJson($slug));

        self::out("\033[32mBundle scaffolded:\033[0m {$slug}");
        self::out("  \033[36mcreated\033[0m  App/Bundles/{$slug}/bundle.json");
        self::out('');
        self::out("Next steps:");
        self::out("  1. Edit \033[33mbundle.json\033[0m - fill in name, description, and modules array");
        self::out("  2. Each module entry needs: { \"slug\": \"blog\", \"required\": true }");
        self::out("  3. The bundle will appear in the Module Manager Packages tab automatically");
        self::out('');
        exit(0);
    }

    // -- Template --------------------------------------------------------------

    private static function bundleJson(string $slug): string
    {
        $name = self::toTitle($slug);
        return json_encode([
            'name'        => $name,
            'slug'        => $slug,
            'version'     => '1.0.0',
            'description' => '',
            'icon'        => 'pi-grid',
            'category'    => 'General',
            'modules'     => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    // -- Helpers ---------------------------------------------------------------

    /** kebab-slug → Title Case: marketing-suite → Marketing Suite */
    private static function toTitle(string $slug): string
    {
        return implode(' ', array_map('ucfirst', explode('-', $slug)));
    }

    private static function write(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            self::error("Failed to write file: {$path}");
        }
    }

    private static function out(string $msg): void
    {
        echo $msg . "\n";
    }

    private static function error(string $msg): never
    {
        echo "\033[31mError:\033[0m {$msg}\n";
        exit(1);
    }
}
