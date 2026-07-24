<?php

declare(strict_types=1);

namespace App\CMS\Cli;

use App\CMS\MigrationRunner;

/**
 * Run/inspect the core migration system.
 *
 * Usage:
 *   php vertext migrate up
 *   php vertext migrate status
 */
final class Migrate
{
    public static function run(?string $subcommand): never
    {
        if (!in_array($subcommand, ['up', 'status'], true)) {
            self::error("Usage: php vertext migrate <up|status>");
        }

        $dbConfig = self::loadDbConfig();

        try {
            $pdo = new \PDO(
                "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}",
                $dbConfig['username'],
                $dbConfig['password'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\PDOException $e) {
            self::error("Could not connect to the database: {$e->getMessage()}");
        }

        $runner = new MigrationRunner($pdo);

        match ($subcommand) {
            'up'     => self::runUp($runner),
            'status' => self::runStatus($runner),
        };
    }

    private static function runUp(MigrationRunner $runner): never
    {
        $result = $runner->up();

        if (!$result['success']) {
            self::error($result['message'] ?? 'Migration failed.');
        }

        self::out("\033[32mMigrations applied.\033[0m");
        exit(0);
    }

    private static function runStatus(MigrationRunner $runner): never
    {
        $rows = $runner->status();

        if (empty($rows)) {
            self::out('No migrations found.');
            exit(0);
        }

        foreach ($rows as $migration) {
            $mark = $migration['applied']
                ? "\033[32m[applied]\033[0m "
                : "\033[33m[pending]\033[0m ";
            self::out("  {$mark}{$migration['filename']}");
        }
        exit(0);
    }

    private static function loadDbConfig(): array
    {
        // Config\Database is a standalone class (no Composer autoload available
        // here - see the require list in the `vertext` CLI entrypoint), so it
        // already knows about the DB_HOST/etc. env-var override the rest of the
        // app uses, falling back to Storage/db.php for traditional/wizard-based
        // installs - this used to check Storage/db.php directly and never
        // considered env vars at all, so `php vertext migrate up` always failed
        // that way in a container even when the app itself connected fine.
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
