<?php

declare(strict_types=1);

namespace App\CMS;

/**
 * Discovers Migrations/*.php files in filename order and runs the ones not
 * yet recorded in schema_migrations, one transaction per file.
 */
class MigrationRunner
{
    private \PDO $pdo;
    private string $migrationsDir;

    public function __construct(\PDO $pdo, ?string $migrationsDir = null)
    {
        $this->pdo = $pdo;
        $this->migrationsDir = $migrationsDir ?? ROOT . 'Migrations' . DS;
    }

    /** Run every unapplied migration, in filename order. Returns {success, message?}. */
    public function up(): array
    {
        $this->ensureTrackingTable();

        try {
            $applied = $this->appliedMigrations();

            foreach ($this->discover() as $migration) {
                if (in_array($migration['filename'], $applied, true)) {
                    continue;
                }

                $this->runOne($migration);
            }

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Per-file applied/pending status, in filename order. */
    public function status(): array
    {
        $this->ensureTrackingTable();
        $applied = $this->appliedMigrations();

        $status = [];
        foreach ($this->discover() as $migration) {
            $status[] = [
                'filename' => $migration['filename'],
                'applied'  => in_array($migration['filename'], $applied, true),
            ];
        }
        return $status;
    }

    /** Bootstraps the tracking table itself - not a discoverable/tracked migration. */
    private function ensureTrackingTable(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS schema_migrations (
                id         UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
                filename   VARCHAR(255) UNIQUE NOT NULL,
                applied_at TIMESTAMP   NOT NULL DEFAULT NOW()
            )
        ');
    }

    private function appliedMigrations(): array
    {
        return $this->pdo->query('SELECT filename FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Discover Migrations/{number}_{snake_name}.php sorted by filename, mapped to Migration_{number}_{PascalName}. */
    private function discover(): array
    {
        $files = glob($this->migrationsDir . '*.php') ?: [];
        sort($files, SORT_STRING);

        $migrations = [];
        foreach ($files as $path) {
            $filename = basename($path);
            if (!preg_match('/^(\d+)_([a-z0-9_]+)\.php$/', $filename, $m)) {
                continue;
            }
            $migrations[] = [
                'filename' => $filename,
                'path'     => $path,
                'class'    => 'Migration_' . $m[1] . '_' . $this->pascalCase($m[2]),
            ];
        }
        return $migrations;
    }

    private function pascalCase(string $snake): string
    {
        return implode('', array_map('ucfirst', explode('_', $snake)));
    }

    private function runOne(array $migration): void
    {
        require_once $migration['path'];

        $class = $migration['class'];

        $this->pdo->beginTransaction();
        try {
            $instance = new $class($this->pdo);
            $instance->up();

            if (method_exists($instance, 'seed')) {
                $instance->seed();
            }

            $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
            $stmt->execute([$migration['filename']]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
