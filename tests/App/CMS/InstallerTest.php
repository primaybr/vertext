<?php

declare(strict_types=1);

namespace Tests\App\CMS;

use PHPUnit\Framework\TestCase;
use App\CMS\Installer;

/**
 * Covers the setup wizard's underlying install flow (checkRequirements(),
 * runMigrations(), and the config/lock-file write path) by calling Installer's
 * static methods directly rather than driving WizardController's HTTP layer -
 * this codebase has no HTTP test client, and WizardController's own flow
 * terminates via redirect() on several branches, which would abort the whole
 * PHPUnit process if called in-process.
 *
 * Storage/db.php, Storage/app.php, and Storage/installed.lock are real,
 * hardcoded paths inside Installer - this test backs each up before running
 * and restores it in tearDown() so it never permanently alters a real install
 * (this dev machine already has one).
 */
final class InstallerTest extends TestCase
{
    private array $backups = [];

    protected function setUp(): void
    {
        foreach (['db.php', 'app.php', 'installed.lock'] as $file) {
            $path = ROOT . 'Storage' . DS . $file;
            $this->backups[$file] = file_exists($path) ? file_get_contents($path) : null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->backups as $file => $content) {
            $path = ROOT . 'Storage' . DS . $file;
            if ($content !== null) {
                file_put_contents($path, $content);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testCheckRequirementsReportsCoreAndOptionalExtensions(): void
    {
        $reqs = Installer::checkRequirements();

        foreach (['php_version', 'pdo_pgsql', 'pdo', 'json', 'mbstring', 'gd', 'fileinfo', 'intl', 'zip'] as $key) {
            $this->assertArrayHasKey($key, $reqs, "checkRequirements() should report on '{$key}'");
        }

        $this->assertTrue($reqs['php_version']['pass'], 'This test suite requires PHP >= 8.2, so the check should pass here.');
    }

    public function testFullInstallFlowAgainstTestDatabase(): void
    {
        $dbConfig = [
            'driver'   => 'pgsql',
            'host'     => TEST_DB_HOST,
            'port'     => TEST_DB_PORT,
            'database' => TEST_DB_NAME,
            'username' => TEST_DB_USER,
            'password' => TEST_DB_PASS,
            'charset'  => 'utf8',
            'prefix'   => '',
        ];

        $migrate = Installer::runMigrations($dbConfig);
        $this->assertTrue($migrate['success'], $migrate['message'] ?? 'runMigrations() failed');

        Installer::saveDbConfig($dbConfig);
        Installer::saveAppConfig(['env' => 'production', 'site' => ['title' => 'Install Test Site', 'baseUrl' => 'https://install-test.example']]);
        Installer::markInstalled();

        $this->assertTrue(Installer::isInstalled());

        $savedDb = require ROOT . 'Storage' . DS . 'db.php';
        $this->assertSame(TEST_DB_NAME, $savedDb['database']);

        $savedApp = require ROOT . 'Storage' . DS . 'app.php';
        $this->assertSame('production', $savedApp['env']);

        $create = Installer::createAdminUser($dbConfig, 'Install Test Admin', 'install-test-admin@example.com', 'Sup3rSecret!1');
        $this->assertTrue($create['success'], $create['message'] ?? 'createAdminUser() failed');
    }

    public function testRunMigrationsIsIdempotent(): void
    {
        $dbConfig = [
            'driver'   => 'pgsql',
            'host'     => TEST_DB_HOST,
            'port'     => TEST_DB_PORT,
            'database' => TEST_DB_NAME,
            'username' => TEST_DB_USER,
            'password' => TEST_DB_PASS,
            'charset'  => 'utf8',
            'prefix'   => '',
        ];

        $first  = Installer::runMigrations($dbConfig);
        $second = Installer::runMigrations($dbConfig);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
    }
}
