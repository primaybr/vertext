<?php

declare(strict_types=1);

// Set up testing environment
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__DIR__) . DS);

// Set testing environment
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

if (!defined('TEST_DB_HOST')) {
    define('TEST_DB_HOST', getenv('TEST_DB_HOST') ?: '127.0.0.1');
    define('TEST_DB_PORT', getenv('TEST_DB_PORT') ?: '5432');
    define('TEST_DB_NAME', getenv('TEST_DB_NAME') ?: 'vertext_test');
    define('TEST_DB_USER', getenv('TEST_DB_USER') ?: 'postgres');
    define('TEST_DB_PASS', getenv('TEST_DB_PASS') ?: 'postgres');
}

// Point Config\Database at the test database for the duration of this run, the
// same way the setup wizard points it at a real database - by writing
// Storage/db.php. If a real Storage/db.php already exists (a developer's local
// install), back it up first and restore it on shutdown so running the test
// suite locally never permanently overwrites a real site's DB config.
$dbConfigFile = ROOT . 'Storage' . DS . 'db.php';
$dbConfigBackup = null;

if (file_exists($dbConfigFile)) {
    $dbConfigBackup = file_get_contents($dbConfigFile);
}

$testDbConfig = [
    'driver'   => 'pgsql',
    'host'     => TEST_DB_HOST,
    'port'     => TEST_DB_PORT,
    'database' => TEST_DB_NAME,
    'username' => TEST_DB_USER,
    'password' => TEST_DB_PASS,
    'charset'  => 'utf8',
    'prefix'   => '',
];
file_put_contents($dbConfigFile, "<?php\n// Written by tests/bootstrap.php for this test run\nreturn " . var_export($testDbConfig, true) . ";\n");

register_shutdown_function(static function () use ($dbConfigFile, $dbConfigBackup): void {
    if ($dbConfigBackup !== null) {
        file_put_contents($dbConfigFile, $dbConfigBackup);
    } elseif (file_exists($dbConfigFile)) {
        unlink($dbConfigFile);
    }
});

// Start output buffering to capture any unexpected output
ob_start();

// Include the framework bootstrap
require_once ROOT . 'Core' . DS . 'Boot.php';

// Clean any output from bootstrap
ob_end_clean();

/** Truncates every table in the test database - call from a test's setUp()/tearDown(). */
function resetTestDatabase(): void
{
    $pdo = new \PDO(
        'pgsql:host=' . TEST_DB_HOST . ';port=' . TEST_DB_PORT . ';dbname=' . TEST_DB_NAME,
        TEST_DB_USER,
        TEST_DB_PASS,
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );
    $tables = $pdo->query("
        SELECT table_name FROM information_schema.tables
        WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
    ")->fetchAll(\PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $pdo->exec('TRUNCATE TABLE "' . $table . '" CASCADE');
    }
}
