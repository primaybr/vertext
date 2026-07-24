<?php

declare(strict_types=1);

namespace App\CMS;

/**
 * Vertext CMS Installer
 * Handles setup wizard logic: requirement checks, DB setup, and installation.
 */
class Installer
{
    private const LOCK_FILE = ROOT . 'Storage' . DS . 'installed.lock';
    private const DB_FILE   = ROOT . 'Storage' . DS . 'db.php';
    private const APP_FILE  = ROOT . 'Storage' . DS . 'app.php';

    /**
     * Check if CMS is already installed. `APP_INSTALLED=1` lets a container
     * deployment with no persisted Storage/ (DB config delivered via env vars
     * instead - see Config\Database) skip the wizard-redirect after the first
     * real install, without needing installed.lock to survive a pod restart.
     */
    public static function isInstalled(): bool
    {
        return file_exists(self::LOCK_FILE) || getenv('APP_INSTALLED') === '1';
    }

    /** Ensure Storage directory is writable */
    public static function ensureStorage(): bool
    {
        $dir = ROOT . 'Storage';
        if (!is_dir($dir)) {
            return mkdir($dir, 0755, true);
        }
        return is_writable($dir);
    }

    /** Run system requirements check */
    public static function checkRequirements(): array
    {
        $results = [];

        $results['php_version'] = [
            'label'  => 'PHP Version (≥ 8.2)',
            'pass'   => version_compare(PHP_VERSION, '8.2.0', '>='),
            'value'  => PHP_VERSION,
        ];

        $results['pdo_pgsql'] = [
            'label'  => 'PDO PostgreSQL Extension',
            'pass'   => extension_loaded('pdo_pgsql'),
            'value'  => extension_loaded('pdo_pgsql') ? 'Available' : 'Missing',
        ];

        $results['pdo'] = [
            'label'  => 'PDO Extension',
            'pass'   => extension_loaded('pdo'),
            'value'  => extension_loaded('pdo') ? 'Available' : 'Missing',
        ];

        $results['json'] = [
            'label'  => 'JSON Extension',
            'pass'   => extension_loaded('json'),
            'value'  => extension_loaded('json') ? 'Available' : 'Missing',
        ];

        $results['mbstring'] = [
            'label'  => 'Mbstring Extension',
            'pass'   => extension_loaded('mbstring'),
            'value'  => extension_loaded('mbstring') ? 'Available' : 'Missing',
        ];

        $results['gd'] = [
            'label'  => 'GD Extension (image resizing)',
            'pass'   => extension_loaded('gd'),
            'value'  => extension_loaded('gd') ? 'Available' : 'Missing',
        ];

        $results['fileinfo'] = [
            'label'  => 'Fileinfo Extension (upload validation)',
            'pass'   => extension_loaded('fileinfo'),
            'value'  => extension_loaded('fileinfo') ? 'Available' : 'Missing',
        ];

        $results['intl'] = [
            'label'  => 'Intl Extension (localized dates, optional)',
            'pass'   => extension_loaded('intl'),
            'value'  => extension_loaded('intl') ? 'Available' : 'Missing',
        ];

        $results['zip'] = [
            'label'  => 'Zip Extension (backup/restore CLI, optional)',
            'pass'   => extension_loaded('zip'),
            'value'  => extension_loaded('zip') ? 'Available' : 'Missing',
        ];

        $results['storage_writable'] = [
            'label'  => 'Storage Directory Writable',
            'pass'   => self::ensureStorage(),
            'value'  => self::ensureStorage() ? 'Writable' : 'Not writable',
        ];

        $results['cache_writable'] = [
            'label'  => 'Cache Directory Writable',
            'pass'   => self::checkDir(ROOT . 'Cache'),
            'value'  => self::checkDir(ROOT . 'Cache') ? 'Writable' : 'Not writable',
        ];

        return $results;
    }

    /** Check/create a writable directory */
    private static function checkDir(string $path): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }
        return is_writable($path);
    }

    /**
     * Connect to the PostgreSQL server using a system database (postgres or template1).
     * Used for server-level operations like CREATE DATABASE.
     */
    private static function serverPdo(string $host, string $port, string $user, string $pass): \PDO
    {
        foreach (['postgres', 'template1'] as $sysDb) {
            try {
                $dsn = "pgsql:host={$host};port={$port};dbname={$sysDb}";
                return new \PDO($dsn, $user, $pass, [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);
            } catch (\PDOException $e) {
                // Try next system DB
            }
        }
        throw new \PDOException("Cannot connect to PostgreSQL server at {$host}:{$port}. Check host, port, and credentials.");
    }

    /**
     * Test server connection and report whether the target database exists.
     * Returns: { success, exists, message }
     */
    public static function testConnection(string $host, string $port, string $db, string $user, string $pass): array
    {
        try {
            $serverPdo = self::serverPdo($host, $port, $user, $pass);

            $stmt = $serverPdo->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
            $stmt->execute([$db]);
            $exists = (bool) $stmt->fetchColumn();

            if ($exists) {
                // Verify we can actually open the target DB
                $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
                $pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                $pdo->query('SELECT 1');
                return ['success' => true, 'exists' => true,  'message' => "Connection successful - database \"{$db}\" exists"];
            }

            return ['success' => true, 'exists' => false, 'message' => "Connection successful - database \"{$db}\" will be created automatically"];
        } catch (\PDOException $e) {
            return ['success' => false, 'exists' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create the target database on the PostgreSQL server if it does not already exist.
     * Connects via a system database (postgres/template1) to issue CREATE DATABASE.
     */
    public static function createDatabaseIfNeeded(array $dbConfig): array
    {
        $host = $dbConfig['host'];
        $port = $dbConfig['port'];
        $db   = $dbConfig['database'];
        $user = $dbConfig['username'];
        $pass = $dbConfig['password'];

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $db)) {
            return ['success' => false, 'message' => 'Database name must start with a letter or underscore and contain only letters, numbers, and underscores.'];
        }

        try {
            $serverPdo = self::serverPdo($host, $port, $user, $pass);

            $stmt = $serverPdo->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
            $stmt->execute([$db]);

            if (!$stmt->fetchColumn()) {
                // CREATE DATABASE cannot run inside a transaction; exec() is fine here
                $serverPdo->exec("CREATE DATABASE \"{$db}\"");
            }

            return ['success' => true];
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Write database config to Storage/db.php */
    public static function saveDbConfig(array $config): bool
    {
        $content = "<?php\n// Auto-generated by Vertext Setup Wizard\nreturn " . var_export($config, true) . ";\n";
        return (bool) file_put_contents(self::DB_FILE, $content);
    }

    /** Write app config to Storage/app.php */
    public static function saveAppConfig(array $config): bool
    {
        $content = "<?php\n// Auto-generated by Vertext Setup Wizard\nreturn " . var_export($config, true) . ";\n";
        return (bool) file_put_contents(self::APP_FILE, $content);
    }

    /** Run all pending migrations (core tables, UUID conversion, and any added since) */
    public static function runMigrations(array $dbConfig): array
    {
        try {
            $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}";
            $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            return (new MigrationRunner($pdo))->up();
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Create admin user in DB */
    public static function createAdminUser(array $dbConfig, string $name, string $email, string $password): array
    {
        try {
            $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}";
            $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $hash = \Core\Security\Password::hash($password);

            // Insert user
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, status)
                 VALUES (?, ?, ?, 'active')
                 ON CONFLICT (email) DO UPDATE SET name=EXCLUDED.name, password=EXCLUDED.password
                 RETURNING id"
            );
            $stmt->execute([$name, $email, $hash]);
            $userId = $stmt->fetchColumn();

            // Assign Administrator role
            $pdo->prepare(
                "INSERT INTO user_roles (user_id, role_id)
                 SELECT ?, id FROM roles WHERE slug = 'administrator'
                 ON CONFLICT DO NOTHING"
            )->execute([$userId]);

            // Save admin email to settings
            $pdo->prepare(
                "UPDATE settings SET value=?, updated_at=NOW() WHERE key='admin_email'"
            )->execute([$email]);

            return ['success' => true, 'user_id' => $userId];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Update site settings in DB */
    public static function updateSiteSettings(array $dbConfig, array $settings): void
    {
        try {
            $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}";
            $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $stmt = $pdo->prepare(
                "UPDATE settings SET value=?, updated_at=NOW() WHERE key=?"
            );

            foreach ($settings as $key => $value) {
                $stmt->execute([$value, $key]);
            }
        } catch (\Exception $e) {
            // Non-fatal: settings can be updated later
        }
    }

    /** Write installed.lock to mark CMS as ready */
    public static function markInstalled(): bool
    {
        return (bool) file_put_contents(
            self::LOCK_FILE,
            json_encode(['installed_at' => date('Y-m-d H:i:s'), 'version' => '1.0.0'])
        );
    }
}
