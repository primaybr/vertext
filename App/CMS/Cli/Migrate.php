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
