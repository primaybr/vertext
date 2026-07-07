<?php

declare(strict_types=1);

namespace App\CMS;

/**
 * Creates and restores a single-archive backup of the database (data only,
 * no shell dependency - streamed as JSON-lines via PDO) plus Public/uploads/
 * and the Storage/ config files.
 *
 * This is a data-only DB backup: restore assumes the target database already
 * has the right schema (a fresh install that has run `vertext migrate up`, or
 * the same Vertext version that made the backup) - it does not attempt to
 * recreate table structure. See docs/backup-restore.md.
 */
final class BackupManager
{
    /** Settings/config keys treated as secrets and redacted unless explicitly included. */
    private const SECRET_SUFFIXES = ['_password', '_secret', '_token'];

    public static function backup(array $dbConfig, bool $includeSecrets, ?string $outputPath = null): array
    {
        $dir = ROOT . 'Storage' . DS . 'backups' . DS;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['success' => false, 'message' => "Could not create directory: {$dir}"];
        }

        $outputPath = $outputPath ?: $dir . 'vertext-backup-' . date('Y-m-d_His') . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return ['success' => false, 'message' => "Could not create archive at {$outputPath}"];
        }

        $tmpFiles = [];

        try {
            $pdo = self::connect($dbConfig);

            foreach (self::listTables($pdo) as $table) {
                $tmp = tempnam(sys_get_temp_dir(), 'vtxbkp_');
                $tmpFiles[] = $tmp;
                self::dumpTable($pdo, $table, $tmp, $includeSecrets);
                $zip->addFile($tmp, "db/{$table}.jsonl");
            }

            self::addDirectoryToZip($zip, ROOT . 'Public' . DS . 'uploads', 'uploads');

            $zip->addFromString('storage/db.php', self::exportConfigPhp(self::loadConfigFile('db.php'), $includeSecrets));
            $zip->addFromString('storage/app.php', self::exportConfigPhp(self::loadConfigFile('app.php'), $includeSecrets));

            $zip->addFromString('manifest.json', json_encode([
                'vertext_version'  => Version::APP,
                'php_version'      => PHP_VERSION,
                'created_at'       => date('c'),
                'secrets_included' => $includeSecrets,
            ], JSON_PRETTY_PRINT));

            $zip->close();

            return ['success' => true, 'path' => $outputPath];
        } catch (\Throwable $e) {
            $zip->close();
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
            return ['success' => false, 'message' => $e->getMessage()];
        } finally {
            foreach ($tmpFiles as $tmp) {
                if (file_exists($tmp)) {
                    unlink($tmp);
                }
            }
        }
    }

    public static function restore(string $archivePath, array $dbConfig): array
    {
        if (!file_exists($archivePath)) {
            return ['success' => false, 'message' => "Archive not found: {$archivePath}"];
        }

        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            return ['success' => false, 'message' => "Could not open archive: {$archivePath}"];
        }

        $manifestJson = $zip->getFromName('manifest.json');
        $manifest = $manifestJson !== false ? json_decode($manifestJson, true) : null;
        if (!is_array($manifest)) {
            $zip->close();
            return ['success' => false, 'message' => 'Archive is missing manifest.json - not a valid Vertext backup.'];
        }

        $tmpDir = sys_get_temp_dir() . DS . 'vtx-restore-' . bin2hex(random_bytes(6));
        mkdir($tmpDir, 0755, true);

        try {
            $zip->extractTo($tmpDir);
            $zip->close();

            $pdo = self::connect($dbConfig);

            self::restoreAllTables($pdo, glob($tmpDir . DS . 'db' . DS . '*.jsonl') ?: []);

            $uploadsSrc = $tmpDir . DS . 'uploads';
            if (is_dir($uploadsSrc)) {
                self::copyDirectory($uploadsSrc, ROOT . 'Public' . DS . 'uploads');
            }

            $message = 'Restore complete.';
            if (empty($manifest['secrets_included'])) {
                $message .= ' Config was redacted in this backup; your current Storage/db.php and Storage/app.php were left unchanged.';
            } else {
                foreach (['db.php', 'app.php'] as $configFile) {
                    $src = $tmpDir . DS . 'storage' . DS . $configFile;
                    if (file_exists($src)) {
                        copy($src, ROOT . 'Storage' . DS . $configFile);
                    }
                }
            }

            return ['success' => true, 'message' => $message];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } finally {
            self::removeDirectory($tmpDir);
        }
    }

    // -- DB dump/restore ---------------------------------------------------

    private static function connect(array $dbConfig): \PDO
    {
        return new \PDO(
            "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}",
            $dbConfig['username'],
            $dbConfig['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
        );
    }

    private static function listTables(\PDO $pdo): array
    {
        return $pdo->query("
            SELECT table_name FROM information_schema.tables
            WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
            ORDER BY table_name
        ")->fetchAll(\PDO::FETCH_COLUMN);
    }

    private static function tableExists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?");
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    /** Streams a table's rows to a JSON-lines file, one row at a time (never holds the whole table in memory). */
    private static function dumpTable(\PDO $pdo, string $table, string $destPath, bool $includeSecrets): void
    {
        $fh = fopen($destPath, 'w');
        $stmt = $pdo->query('SELECT * FROM "' . $table . '"');
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!$includeSecrets && $table === 'settings' && self::isSecretKey((string) ($row['key'] ?? ''))) {
                $row['value'] = '__REDACTED__';
            }
            fwrite($fh, json_encode($row) . "\n");
        }
        fclose($fh);
    }

    /**
     * Restores every table's rows inside one transaction: truncate all tables
     * first (before any inserts, so a later truncate can never wipe out data
     * already inserted into a table it cascades to), then insert row data in
     * FK-dependency order (referenced tables before the tables that reference
     * them) so foreign key constraints are satisfied without needing
     * superuser-only settings like session_replication_role.
     */
    private static function restoreAllTables(\PDO $pdo, array $files): void
    {
        $tableFiles = [];
        foreach ($files as $file) {
            $table = basename($file, '.jsonl');
            if (self::tableExists($pdo, $table)) {
                $tableFiles[$table] = $file;
            }
        }

        if (empty($tableFiles)) {
            return;
        }

        $pdo->beginTransaction();
        try {
            foreach (array_keys($tableFiles) as $table) {
                $pdo->exec('TRUNCATE TABLE "' . $table . '" CASCADE');
            }

            foreach (self::orderByDependency($pdo, array_keys($tableFiles)) as $table) {
                self::insertRowsFromFile($pdo, $table, $tableFiles[$table]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Orders tables so a table only appears after every other table in the set it FK-references. */
    private static function orderByDependency(\PDO $pdo, array $tables): array
    {
        $remaining = array_flip($tables);
        $ordered = [];

        while ($remaining) {
            $progressed = false;

            foreach (array_keys($remaining) as $table) {
                $blockedBy = array_intersect(self::fkDependencies($pdo, $table), array_keys($remaining));
                $blockedBy = array_diff($blockedBy, [$table]); // self-referencing FKs don't block

                if (empty($blockedBy)) {
                    $ordered[] = $table;
                    unset($remaining[$table]);
                    $progressed = true;
                }
            }

            if (!$progressed) {
                // Circular dependency among the remaining tables (not expected in this schema) -
                // insert them in their existing order rather than looping forever.
                $ordered = array_merge($ordered, array_keys($remaining));
                break;
            }
        }

        return $ordered;
    }

    /** Table names this table's foreign keys reference (excluding itself). */
    private static function fkDependencies(\PDO $pdo, string $table): array
    {
        $stmt = $pdo->prepare("
            SELECT DISTINCT ccu.table_name AS ref
            FROM   information_schema.table_constraints tc
            JOIN   information_schema.constraint_column_usage ccu
                   ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
            WHERE  tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = 'public' AND tc.table_name = ?
        ");
        $stmt->execute([$table]);
        return array_diff($stmt->fetchAll(\PDO::FETCH_COLUMN), [$table]);
    }

    /** Streams rows from a JSON-lines file into an already-truncated table. */
    private static function insertRowsFromFile(\PDO $pdo, string $table, string $jsonlFile): void
    {
        $fh = fopen($jsonlFile, 'r');
        $insertStmt = null;
        $columns = null;

        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);

            if ($columns === null) {
                $columns = array_keys($row);
                $colList = implode(',', array_map(static fn($c) => '"' . $c . '"', $columns));
                $placeholders = implode(',', array_map(static fn($c) => ':' . $c, $columns));
                $insertStmt = $pdo->prepare("INSERT INTO \"{$table}\" ({$colList}) VALUES ({$placeholders})");
            }

            // PDO_pgsql stringifies PHP bool false to '' when bound via execute(array),
            // which Postgres rejects for real boolean columns - send 't'/'f' literals instead.
            foreach ($row as $col => $value) {
                if (is_bool($value)) {
                    $row[$col] = $value ? 't' : 'f';
                }
            }

            $insertStmt->execute($row);
        }
        fclose($fh);
    }

    // -- Config files -------------------------------------------------------

    private static function loadConfigFile(string $filename): array
    {
        $path = ROOT . 'Storage' . DS . $filename;
        return file_exists($path) ? (require $path) : [];
    }

    private static function exportConfigPhp(array $config, bool $includeSecrets): string
    {
        if (!$includeSecrets) {
            $config = self::redactSecrets($config);
        }
        return "<?php\n// Vertext backup archive\nreturn " . var_export($config, true) . ";\n";
    }

    private static function redactSecrets(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = self::redactSecrets($value);
            } elseif (is_string($key) && self::isSecretKey($key)) {
                $config[$key] = '__REDACTED__';
            }
        }
        return $config;
    }

    private static function isSecretKey(string $key): bool
    {
        if ($key === 'password') {
            return true;
        }
        foreach (self::SECRET_SUFFIXES as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return true;
            }
        }
        return false;
    }

    // -- Filesystem helpers ---------------------------------------------------

    private static function addDirectoryToZip(\ZipArchive $zip, string $srcDir, string $archivePrefix): void
    {
        if (!is_dir($srcDir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            $relative = $archivePrefix . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($srcDir) + 1));
            $zip->addFile($file->getPathname(), $relative);
        }
    }

    private static function copyDirectory(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $dst . DS . substr($item->getPathname(), strlen($src) + 1);
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
