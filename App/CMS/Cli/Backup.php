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
        $file = BASE_PATH . '/Storage/db.php';
        if (!file_exists($file)) {
            self::error('No database configured - Storage/db.php not found. Run the setup wizard first.');
        }
        return require $file;
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
