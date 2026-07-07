<?php

declare(strict_types=1);

namespace Tests\App\Support;

use PHPUnit\Framework\TestCase;
use Core\Model;
use Core\Security\Password;
use App\CMS\MigrationRunner;

/**
 * Base class for App-level integration tests that need a real database.
 *
 * Connects via whatever Storage/db.php currently points at - tests/bootstrap.php
 * swaps that to a dedicated test database for the whole run (and restores the
 * original file on shutdown), so this never touches a real site's data. Unlike
 * tests/Core/'s markTestSkipped-on-failure pattern (appropriate for a framework
 * test that might run without any DB configured), a failure to connect here is
 * a hard failure - by this point in the test run a database is expected to exist.
 */
abstract class DatabaseTestCase extends TestCase
{
    private static bool $schemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureSchema();
    }

    /**
     * Bootstraps the full schema this test class might need, self-contained
     * and order-independent - PHPUnit does not guarantee test classes run in
     * any particular order, so this can't assume some other test class (e.g.
     * one that happens to call Installer::runMigrations()) already ran first.
     * Runs once per process.
     */
    private static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        self::$schemaReady = true;

        // Fail loudly (not skip) if the test database is unreachable.
        $pdo = new \PDO(
            'pgsql:host=' . TEST_DB_HOST . ';port=' . TEST_DB_PORT . ';dbname=' . TEST_DB_NAME,
            TEST_DB_USER,
            TEST_DB_PASS,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Core tables (users, roles, permissions, settings, ...)
        $migrate = (new MigrationRunner($pdo))->up();
        if (!$migrate['success']) {
            throw new \RuntimeException('Test schema migration failed: ' . ($migrate['message'] ?? 'unknown error'));
        }

        // Module tables aren't covered by the core migrations - install() them directly.
        $db = (new Model('settings'))->db;
        (new \App\Modules\Blog\Module())->install($db);
        (new \App\Modules\Forms\Module())->install($db);
        \App\Controllers\Api\V1\ApiController::ensureTables();
    }

    /** Truncate the given tables (FK-safe via CASCADE) so each test starts clean. */
    protected function truncate(array $tables): void
    {
        $db = (new Model('settings'))->db;
        foreach ($tables as $table) {
            $db->query('TRUNCATE TABLE "' . $table . '" CASCADE');
            $db->execute();
        }
    }

    /** Seed a minimal active admin user with the Administrator role; returns the user id. */
    protected function seedAdminUser(string $email = 'admin-test@example.com', string $password = 'Sup3rSecret!1'): string
    {
        (new Model('users'))->save([
            'name'     => 'Test Admin',
            'email'    => $email,
            'password' => Password::hash($password),
            'status'   => 'active',
        ]);

        $userId = (string) (new Model('users'))->where('email', $email)->get(1)['id'];

        $roleId = (new Model('roles'))->where('slug', 'administrator')->get(1)['id'] ?? null;
        if ($roleId) {
            (new Model('user_roles'))->withoutTimestamps()->save([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }

        return $userId;
    }

    /** Insert an active API key row; returns the plaintext key (only ever available here). */
    protected function seedApiKey(string $name = 'Test Key'): string
    {
        $plaintext = bin2hex(random_bytes(24));

        (new Model('api_keys'))->save([
            'name'     => $name,
            'key_hash' => hash('sha256', $plaintext),
        ]);

        return $plaintext;
    }
}
