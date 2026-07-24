<?php

declare(strict_types=1);

namespace App\CMS\Cli;

use App\CMS\BackupManager;

/**
 * Restore a backup archive created by `vertext backup`.
 *
 * Usage:
 *   php vertext restore <archive-path> [--force]
 *
 * Data-only restore: assumes the target database already has the right
 * schema (a fresh install that has run `vertext migrate up`, or the same
 * Vertext version that made the backup).
 */
final class Restore
{
    public static function run(array $args): never
    {
        $path = $args[0] ?? null;

        if (!$path || str_starts_with($path, '--')) {
            self::error('Usage: php vertext restore <archive-path> [--force]');
        }

        if (!file_exists($path)) {
            self::error("Archive not found: {$path}");
        }

        $force = in_array('--force', $args, true);

        if (!$force) {
            self::out("This will overwrite database tables and Public/uploads/ with the contents of:");
            self::out("  {$path}");
            self::out('');
            echo 'Type "yes" to continue: ';
            $confirm = trim((string) fgets(STDIN));
            if (strtolower($confirm) !== 'yes') {
                self::out('Restore cancelled.');
                exit(0);
            }
        }

        $dbConfig = self::loadDbConfig();
        $result = BackupManager::restore($path, $dbConfig);

        if (!$result['success']) {
            self::error($result['message'] ?? 'Restore failed.');
        }

        self::out("\033[32m{$result['message']}\033[0m");
        exit(0);
    }

    private static function loadDbConfig(): array
    {
        // Config\Database is a standalone class (no Composer autoload available
        // here - see the require list in the `vertext` CLI entrypoint), so it
        // already knows about the DB_HOST/etc. env-var override, falling back
        // to Storage/db.php for traditional/wizard-based installs.
        $config = (new \Config\Database())->getConnectionConfig();

        if ($config['database'] === '') {
            self::error('No database configured - set DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD env vars, or run the setup wizard first.');
        }

        return $config;
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
