<?php

declare(strict_types=1);

namespace App\CMS;

/**
 * Contract every installable (non-core) module must satisfy.
 *
 * The CMS only calls these three methods - everything else is
 * the module developer's responsibility.
 *
 * Modules MAY additionally implement an optional, duck-typed upgrade hook:
 *
 *     public function upgrade(\Core\Database\Connection $db, string $fromVersion): void
 *
 * ModuleManager::upgrade() calls it (via method_exists(), not an instanceof check)
 * whenever an admin applies a version bump detected between the installed DB
 * version and the on-disk module.json version. It is not part of this interface
 * so existing modules require no changes; only modules that need to run a real
 * migration when their version bumps need to add it. Like install()/uninstall(),
 * it must be idempotent - safe to re-run if a previous attempt failed partway.
 */
interface ModuleInterface
{
    /**
     * Run database migrations and seed initial data.
     * Called once when the admin clicks "Install".
     */
    public function install(\Core\Database\Connection $db): void;

    /**
     * Tear down all module data (drop tables, delete permissions, etc.).
     * Called once when the admin clicks "Uninstall".
     * Must be idempotent - safe to call even if install was partial.
     */
    public function uninstall(\Core\Database\Connection $db): void;

    /**
     * Register this module's admin (and optional front-end) routes.
     * Called on every request where the module is enabled and installed,
     * before the router dispatches - but after the route cache is loaded,
     * so add() is a no-op for already-cached routes.
     */
    public function registerRoutes(\Core\Router $router): void;
}
