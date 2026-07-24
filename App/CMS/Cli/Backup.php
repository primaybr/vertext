<?php

declare(strict_types=1);

namespace App\CMS\Cli;

use App\CMS\BackupManager;

/**
 * Create a backup archive (database data + Public/uploads/ + Storage/ config).
 *
 * Usage:
 *   php vertext backup [--include-secrets] [--output=path]
 */
final class Backup
{
    public static function run(array $args): never
    {
        $includeSecrets = in_array('--include-secrets', $args, true);
        $outputPath = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--output=')) {
                $outputPath = substr($arg, strlen('--output='));
            }
        }

        $dbConfig = self::loadDbConfig();
        $result = BackupManager::backup($dbConfig, $includeSecrets, $outputPath);

        if (!$result['success']) {
            self::error($result['message'] ?? 'Backup failed.');
        }

        self::out("\033[32mBackup created:\033[0m {$result['path']}");
        if (!$includeSecrets) {
            self::out('Secrets were redacted. Use --include-secrets to include plaintext credentials.');
        }
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
