<?php

declare(strict_types=1);

namespace App\CMS;

/**
 * ModuleManager - lifecycle manager for non-core installable modules.
 *
 * Responsibilities:
 *  - discover()      Scan App/Modules/ for packages not yet in the DB
 *  - install()       Run Module::install(), copy views, register in DB, clear route cache
 *  - uninstall()     Run Module::uninstall(), remove views, remove DB row, clear route cache
 *  - loadRoutes()    Register enabled module routes into the router (once per request)
 *  - clearRouteCache() Delete Cache/routes.cache so Routes.php is fully re-evaluated
 *
 * Security model:
 *  Modules are trusted developer code placed on the server filesystem.
 *  All slug and directory-name inputs are validated with strict regex before
 *  any filesystem or class-construction operation.
 */
class ModuleManager
{
    private const MODULES_DIR = ROOT . 'App'    . DS . 'Modules' . DS;
    private const BUNDLES_DIR = ROOT . 'App'    . DS . 'Bundles' . DS;
    private const VIEWS_OUT   = ROOT . 'App'    . DS . 'Views'   . DS . 'modules' . DS;
    private const ASSETS_OUT  = ROOT . 'Public' . DS . 'assets'  . DS . 'modules' . DS;
    private const ROUTE_CACHE = ROOT . 'Cache'  . DS . 'routes.cache';

    /** Prevent loadRoutes() from querying the DB more than once per request */
    private static bool $routesLoaded = false;

    // ── Discovery ──────────────────────────────────────────────────────────────

    /**
     * Return manifests for modules present in App/Modules/ but not yet installed.
     * Each entry includes a 'directory' key (the folder name, e.g. "Blog").
     */
    public static function discover(): array
    {
        if (!is_dir(self::MODULES_DIR)) {
            return [];
        }

        $rows      = (new \Core\Model('modules'))->get() ?: [];
        $installed = array_column($rows, 'slug');

        $available = [];
        foreach (glob(self::MODULES_DIR . '*', GLOB_ONLYDIR) as $modDir) {
            $manifestFile = $modDir . DS . 'module.json';
            if (!file_exists($manifestFile)) {
                continue;
            }

            $raw = file_get_contents($manifestFile);
            $manifest = json_decode($raw, true);
            if (!is_array($manifest) || empty($manifest['slug'])) {
                continue;
            }

            if (!self::validSlug($manifest['slug'])) {
                continue;
            }

            if (!in_array($manifest['slug'], $installed, true)) {
                $manifest['directory'] = basename($modDir);
                $available[] = $manifest;
            }
        }

        return $available;
    }

    // ── Bundles ────────────────────────────────────────────────────────────────

    /**
     * Return all bundle manifests from App/Bundles/, annotated with install status.
     * Each entry includes 'modules' with per-entry 'installed' flag and overall
     * 'status': 'installed' | 'partial' | 'none'.
     */
    public static function getBundles(): array
    {
        if (!is_dir(self::BUNDLES_DIR)) {
            return [];
        }

        $installed = array_column(
            (new \Core\Model('modules'))->select('slug')->where('status', 'enabled')->get() ?: [],
            'slug'
        );

        $bundles = [];
        foreach (glob(self::BUNDLES_DIR . '*', GLOB_ONLYDIR) as $bundleDir) {
            $file = $bundleDir . DS . 'bundle.json';
            if (!file_exists($file)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($file), true);
            if (!is_array($manifest) || empty($manifest['slug']) || empty($manifest['modules'])) {
                continue;
            }

            $requiredSlugs = array_column(
                array_filter($manifest['modules'], fn($m) => !empty($m['required'])),
                'slug'
            );

            $installedCount  = 0;
            $totalCount      = count($manifest['modules']);
            $allRequiredMet  = true;

            foreach ($manifest['modules'] as &$mod) {
                $mod['installed'] = in_array($mod['slug'], $installed, true);
                if ($mod['installed']) {
                    $installedCount++;
                }
                if (!empty($mod['required']) && !$mod['installed']) {
                    $allRequiredMet = false;
                }
            }
            unset($mod);

            if ($installedCount === $totalCount) {
                $status = 'installed';
            } elseif ($installedCount > 0) {
                $status = 'partial';
            } else {
                $status = 'none';
            }

            $manifest['status']          = $status;
            $manifest['installed_count'] = $installedCount;
            $manifest['total_count']     = $totalCount;
            $manifest['all_required_met'] = $allRequiredMet;
            $bundles[] = $manifest;
        }

        return $bundles;
    }

    /**
     * Install multiple modules in dependency-safe order.
     * Already-installed modules are skipped (not an error).
     * Returns a per-slug result array: ['slug' => ['success' => bool, 'name' => str, 'message' => str, 'skipped' => bool]].
     */
    public static function installBatch(array $slugs): array
    {
        $ordered = self::topologicalSort($slugs);
        $results = [];

        foreach ($ordered as $slug) {
            $existing = (new \Core\Model('modules'))->select('id, name')->where('slug', $slug)->get(1);
            if ($existing) {
                $results[$slug] = ['success' => true, 'skipped' => true, 'name' => $existing['name'] ?? $slug, 'message' => 'Already installed.'];
                continue;
            }

            $result = self::install($slug);
            $results[$slug] = array_merge(['skipped' => false], $result);
        }

        return $results;
    }

    /**
     * Order slugs so that each module is installed after its requires.modules dependencies.
     * Uses Kahn's topological sort. Slugs whose manifests cannot be found are placed at end.
     * Does not include already-installed modules in the ordering (they are not re-installed).
     */
    private static function topologicalSort(array $slugs): array
    {
        $slugSet  = array_flip($slugs);
        $manifests = [];
        $inDegree  = [];
        $adjList   = [];

        foreach ($slugs as $slug) {
            $inDegree[$slug] = 0;
            $adjList[$slug]  = [];
        }

        foreach ($slugs as $slug) {
            $info = self::findBySlug($slug);
            if (!$info) {
                $manifests[$slug] = null;
                continue;
            }
            $manifests[$slug] = $info[0];
            $deps = $info[0]['requires']['modules'] ?? [];
            foreach ($deps as $dep) {
                if (isset($slugSet[$dep])) {
                    $adjList[$dep][] = $slug;
                    $inDegree[$slug]++;
                }
            }
        }

        $queue  = [];
        foreach ($slugs as $slug) {
            if ($inDegree[$slug] === 0) {
                $queue[] = $slug;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $current  = array_shift($queue);
            $sorted[] = $current;
            foreach ($adjList[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        // Append any remaining (cycle or missing manifest) at end
        $remaining = array_diff($slugs, $sorted);
        return array_merge($sorted, array_values($remaining));
    }

    // ── Install ────────────────────────────────────────────────────────────────

    /**
     * Install a module by its slug:
     *  1. Validate slug and find the module directory
     *  2. Load App/Modules/{Dir}/Module.php and verify it implements ModuleInterface
     *  3. Run Module::install($db) inside a transaction
     *  4. Insert row in the modules table
     *  5. Deploy views from App/Modules/{Dir}/Views/ → App/Views/modules/{slug}/
     *  6. Refresh the module cache and clear the route cache
     */
    public static function install(string $slug): array
    {
        if (!self::validSlug($slug)) {
            return ['success' => false, 'message' => 'Invalid module slug.'];
        }

        // Check already installed via ORM
        if ((new \Core\Model('modules'))->select('id')->where('slug', $slug)->get(1)) {
            return ['success' => false, 'message' => "Module \"{$slug}\" is already installed."];
        }

        $info = self::findBySlug($slug);
        if (!$info) {
            return ['success' => false, 'message' => "No module with slug \"{$slug}\" found in App/Modules/."];
        }

        [$manifest, $modDir, $dirName] = $info;

        // Check module dependencies before attempting install
        $depCheck = self::checkModuleDeps($manifest);
        if (!$depCheck['ok']) {
            $missing = implode('", "', $depCheck['missing']);
            return ['success' => false, 'message' => "Cannot install: required module(s) are not installed: \"{$missing}\"."];
        }

        if (!self::validDirName($dirName)) {
            return ['success' => false, 'message' => 'Module directory name contains invalid characters.'];
        }

        $moduleFile = $modDir . DS . 'Module.php';
        if (!file_exists($moduleFile)) {
            return ['success' => false, 'message' => "Module.php is missing in \"{$dirName}\"."];
        }

        // Borrow one pool connection for the entire transaction so that the module's
        // DDL/DML and the modules-table insert all share the same connection handle.
        // $orm must stay in scope until the transaction is done - its destructor returns
        // the connection to the pool.
        $orm  = new \Core\Model('modules');
        $conn = $orm->db;

        $inTransaction = false;
        try {
            require_once $moduleFile;
            $className = "App\\Modules\\{$dirName}\\Module";

            if (!class_exists($className)) {
                return ['success' => false, 'message' => "Class {$className} not found after loading Module.php."];
            }
            if (!is_a($className, ModuleInterface::class, true)) {
                return ['success' => false, 'message' => "{$className} must implement App\\CMS\\ModuleInterface."];
            }

            $conn->beginTransaction();
            $inTransaction = true;

            (new $className())->install($conn);

            // Insert the module record on the same connection so it is part of the transaction.
            \Core\Model::on($conn, 'modules')->save([
                'name'        => $manifest['name']        ?? $dirName,
                'slug'        => $slug,
                'version'     => $manifest['version']     ?? '1.0.0',
                'description' => $manifest['description'] ?? '',
                'author'      => $manifest['author']      ?? '',
                'is_core'     => false,
                'status'      => 'enabled',
                'directory'   => $dirName,
            ]);

            $conn->endTransaction();
            $inTransaction = false;

        } catch (\Exception $e) {
            if ($inTransaction) {
                $conn->cancelTransaction();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }

        // Deploy views and assets (outside transaction - filesystem ops are not rolled back)
        self::deployViews($modDir, $slug);
        self::deployAssets($modDir, $slug);

        ModuleLoader::refresh();
        self::clearRouteCache();

        $result = ['success' => true, 'name' => $manifest['name'] ?? $dirName];
        if (!empty($manifest['setup'])) {
            $result['setup_url'] = $manifest['setup'];
        }
        return $result;
    }

    // ── Uninstall ──────────────────────────────────────────────────────────────

    /**
     * Uninstall a module by its slug:
     *  1. Guard: slug valid, module installed, not core
     *  2. Run Module::uninstall($db) inside a transaction
     *  3. Delete row from modules table
     *  4. Remove deployed views
     *  5. Refresh module cache and route cache
     */
    public static function uninstall(string $slug): array
    {
        if (!self::validSlug($slug)) {
            return ['success' => false, 'message' => 'Invalid module slug.'];
        }

        $row = (new \Core\Model('modules'))
            ->select('id, name, is_core, directory')
            ->where('slug', $slug)
            ->get(1);

        if (!$row) {
            return ['success' => false, 'message' => "Module \"{$slug}\" is not installed."];
        }

        if ((bool) $row['is_core']) {
            return ['success' => false, 'message' => 'Core modules cannot be uninstalled.'];
        }

        // Block uninstall if other installed modules depend on this one
        $dependents = self::checkDependents($slug);
        if (!empty($dependents)) {
            $names = implode('", "', $dependents);
            return ['success' => false, 'message' => "Cannot uninstall: \"{$names}\" depends on this module. Uninstall it first."];
        }

        $dirName = $row['directory'] ?? null;

        // Run Module::uninstall() if the class is available
        if ($dirName && self::validDirName($dirName)) {
            $moduleFile = self::MODULES_DIR . $dirName . DS . 'Module.php';
            if (file_exists($moduleFile)) {
                // Borrow one pool connection so the module's DROP/DELETE statements
                // all share the same transaction handle.
                $orm  = new \Core\Model('modules');
                $conn = $orm->db;

                $inTransaction = false;
                try {
                    require_once $moduleFile;
                    $className = "App\\Modules\\{$dirName}\\Module";

                    if (class_exists($className) && is_a($className, ModuleInterface::class, true)) {
                        $conn->beginTransaction();
                        $inTransaction = true;
                        (new $className())->uninstall($conn);
                        $conn->endTransaction();
                        $inTransaction = false;
                    }
                } catch (\Exception $e) {
                    if ($inTransaction) {
                        $conn->cancelTransaction();
                    }
                    return ['success' => false, 'message' => 'Uninstall script failed: ' . $e->getMessage()];
                }
            }
        }

        // Extra guard: is_core = FALSE in the WHERE prevents accidental core deletion
        (new \Core\Model('modules'))
            ->where('slug', $slug)
            ->whereQuery('is_core = FALSE')
            ->delete();

        if ($dirName) {
            self::removeViews($slug);
            self::removeAssets($slug);
        }

        ModuleLoader::refresh();
        self::clearRouteCache();

        return ['success' => true];
    }

    // ── Route loading ──────────────────────────────────────────────────────────

    /**
     * Register routes for all enabled non-core modules into $router.
     * Called from Config/Routes.php on every request.
     * Skipped if CMS is not installed yet (setup wizard state).
     * Uses a per-request static flag so the DB is queried at most once.
     */
    public static function loadRoutes(\Core\Router $router): void
    {
        if (self::$routesLoaded) {
            return;
        }
        self::$routesLoaded = true;

        if (!Installer::isInstalled()) {
            return;
        }

        try {
            $modules = (new \Core\Model('modules'))
                ->select('slug, directory')
                ->whereQuery('is_core = FALSE')
                ->where('status', 'enabled')
                ->whereNotNull('directory')
                ->orderBy('name', 'ASC')
                ->get() ?: [];
        } catch (\Exception $e) {
            return;
        }

        foreach ($modules as $mod) {
            $dirName = $mod['directory'];

            if (!self::validDirName($dirName)) {
                continue;
            }

            $moduleFile = self::MODULES_DIR . $dirName . DS . 'Module.php';
            if (!file_exists($moduleFile)) {
                continue;
            }

            try {
                require_once $moduleFile;
                $className = "App\\Modules\\{$dirName}\\Module";

                if (!class_exists($className) || !is_a($className, ModuleInterface::class, true)) {
                    continue;
                }

                (new $className())->registerRoutes($router);
            } catch (\Exception $e) {
                // Non-fatal: one broken module must not prevent others from loading
            }
        }
    }

    // ── Cache ──────────────────────────────────────────────────────────────────

    /**
     * Delete the route cache file.
     * The next request will fully re-evaluate Config/Routes.php,
     * re-registering all module routes including newly installed ones.
     */
    public static function clearRouteCache(): void
    {
        if (file_exists(self::ROUTE_CACHE)) {
            @unlink(self::ROUTE_CACHE);
        }

        // Also flush the ORM query cache so the next request reads the modules
        // table fresh from DB (the cached query would otherwise return stale results
        // for up to an hour after install/uninstall).
        $dbCacheDir = ROOT . 'Cache' . DS . 'database' . DS;
        if (is_dir($dbCacheDir)) {
            foreach (glob($dbCacheDir . '*.cache') ?: [] as $f) {
                @unlink($f);
            }
        }
    }

    // ── Dependency helpers ─────────────────────────────────────────────────────

    /**
     * Return per-slug install status for a module manifest's requires.modules list.
     * Used by the modules admin UI to show green/red dependency badges.
     *
     * @return array<int, array{slug: string, installed: bool}>
     */
    public static function getDependencyInfo(array $manifest): array
    {
        $required = $manifest['requires']['modules'] ?? [];
        if (empty($required)) {
            return [];
        }

        $installed = array_column(
            (new \Core\Model('modules'))->select('slug')->where('status', 'enabled')->get() ?: [],
            'slug'
        );

        $deps = [];
        foreach ($required as $slug) {
            $deps[] = ['slug' => $slug, 'installed' => in_array($slug, $installed, true)];
        }
        return $deps;
    }

    /**
     * Verify all module slugs listed in requires.modules are installed and enabled.
     * Returns ['ok' => bool, 'missing' => [slugs...]].
     */
    private static function checkModuleDeps(array $manifest): array
    {
        $required = $manifest['requires']['modules'] ?? [];
        if (empty($required)) {
            return ['ok' => true, 'missing' => []];
        }

        $installed = array_column(
            (new \Core\Model('modules'))->select('slug')->where('status', 'enabled')->get() ?: [],
            'slug'
        );

        $missing = array_filter($required, fn($dep) => !in_array($dep, $installed, true));
        return ['ok' => empty($missing), 'missing' => array_values($missing)];
    }

    /**
     * Return the names of installed modules whose requires.modules list includes $slug.
     * Used to block uninstalling a module that others depend on.
     */
    private static function checkDependents(string $slug): array
    {
        $rows = (new \Core\Model('modules'))
            ->select('name, directory')
            ->where('status', 'enabled')
            ->whereQuery('is_core = FALSE')
            ->get() ?: [];

        $dependents = [];
        foreach ($rows as $row) {
            $dirName = $row['directory'] ?? null;
            if (!$dirName || !self::validDirName($dirName)) {
                continue;
            }

            $file = self::MODULES_DIR . $dirName . DS . 'module.json';
            if (!file_exists($file)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($file), true);
            if (!is_array($manifest)) {
                continue;
            }

            if (in_array($slug, $manifest['requires']['modules'] ?? [], true)) {
                $dependents[] = $row['name'];
            }
        }

        return $dependents;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /** Slug: lowercase, starts with a letter, letters/digits/hyphens/underscores */
    private static function validSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9\-_]*$/', $slug);
    }

    /** Directory name: starts with a letter, alphanumeric + underscore only (no path traversal) */
    private static function validDirName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name);
    }

    /**
     * Scan App/Modules/ for the first directory whose module.json slug matches.
     * Returns [manifest, absoluteDirPath, dirName] or null.
     */
    private static function findBySlug(string $slug): ?array
    {
        if (!is_dir(self::MODULES_DIR)) {
            return null;
        }

        foreach (glob(self::MODULES_DIR . '*', GLOB_ONLYDIR) as $modDir) {
            $file = $modDir . DS . 'module.json';
            if (!file_exists($file)) {
                continue;
            }

            $manifest = json_decode(file_get_contents($file), true);
            if (!is_array($manifest)) {
                continue;
            }

            if (($manifest['slug'] ?? '') === $slug) {
                return [$manifest, $modDir, basename($modDir)];
            }
        }

        return null;
    }

    /**
     * Remove an extracted module directory left behind after a failed install.
     * Only accepts absolute paths that start with MODULES_DIR to prevent mishaps.
     */
    public static function removeExtractedDir(string $absPath): void
    {
        $real = realpath($absPath);
        $base = realpath(self::MODULES_DIR);
        if ($real && $base && str_starts_with($real . DS, $base . DS)) {
            self::removeDir($real . DS);
        }
    }

    /**
     * Re-deploy views and assets for an already-installed module.
     * Use this when source views or assets change without a full reinstall.
     */
    public static function redeployViews(string $slug): bool
    {
        if (!self::validSlug($slug)) {
            return false;
        }

        $row = (new \Core\Model('modules'))
            ->select('directory')
            ->where('slug', $slug)
            ->where('status', 'enabled')
            ->get(1);

        if (!$row || empty($row['directory'])) {
            return false;
        }

        $modDir = self::MODULES_DIR . $row['directory'];
        self::deployViews($modDir, $slug);
        self::deployAssets($modDir, $slug);
        return true;
    }

    /**
     * Re-deploy only assets for an already-installed module.
     * Mirrors redeployViews() for the Public/assets/modules/{slug}/ tree.
     */
    public static function redeployAssets(string $slug): bool
    {
        if (!self::validSlug($slug)) {
            return false;
        }

        $row = (new \Core\Model('modules'))
            ->select('directory')
            ->where('slug', $slug)
            ->where('status', 'enabled')
            ->get(1);

        if (!$row || empty($row['directory'])) {
            return false;
        }

        $modDir = self::MODULES_DIR . $row['directory'];
        self::deployAssets($modDir, $slug);
        return true;
    }

    /**
     * Copy App/Modules/{Dir}/Views/ → App/Views/modules/{slug}/
     * Only called for trusted developer-authored module packages.
     */
    private static function deployViews(string $modDir, string $slug): void
    {
        if (!preg_match('/^[a-z0-9_\-]+$/i', $slug)) {
            throw new \RuntimeException("Invalid module slug '{$slug}' - must be alphanumeric/hyphens/underscores only.");
        }

        $src = $modDir . DS . 'Views' . DS;
        if (!is_dir($src)) {
            return;
        }

        self::copyDir($src, self::VIEWS_OUT . $slug . DS);
    }

    /** Remove App/Views/modules/{slug}/ when a module is uninstalled */
    private static function removeViews(string $slug): void
    {
        $dest = self::VIEWS_OUT . $slug . DS;
        if (is_dir($dest)) {
            self::removeDir($dest);
        }
    }

    /**
     * Copy App/Modules/{Dir}/Assets/ → Public/assets/modules/{slug}/
     * Makes module CSS/JS web-accessible without touching the global admin bundles.
     */
    private static function deployAssets(string $modDir, string $slug): void
    {
        if (!preg_match('/^[a-z0-9_\-]+$/i', $slug)) {
            throw new \RuntimeException("Invalid module slug '{$slug}' - must be alphanumeric/hyphens/underscores only.");
        }

        $src = $modDir . DS . 'Assets' . DS;
        if (!is_dir($src)) {
            return;
        }

        self::copyDir($src, self::ASSETS_OUT . $slug . DS);
    }

    /** Remove Public/assets/modules/{slug}/ when a module is uninstalled */
    private static function removeAssets(string $slug): void
    {
        $dest = self::ASSETS_OUT . $slug . DS;
        if (is_dir($dest)) {
            self::removeDir($dest);
        }
    }

    private static function copyDir(string $src, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        // Resolve destination base for path traversal check (after directory exists)
        $destReal = realpath($dest);
        if ($destReal === false) {
            $destReal = $dest;
        }

        foreach (scandir($src) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            // Skip entries that contain path traversal sequences
            if (str_contains($item, '..') || str_contains($item, '/') || str_contains($item, '\\')) {
                continue;
            }

            $s = $src  . $item;
            $d = $dest . $item;

            // Verify resolved destination stays within expected base
            if (is_dir($s)) {
                self::copyDir($s . DS, $d . DS);
            } else {
                // Guard: resolved destination file must start with dest directory
                $dReal = realpath(dirname($d));
                if ($dReal !== false && !str_starts_with($dReal . DS, $destReal . DS)) {
                    continue; // Silently skip suspected traversal
                }
                copy($s, $d);
            }
        }
    }

    private static function removeDir(string $dir): void
    {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . $item;
            if (is_dir($path)) {
                self::removeDir($path . DS);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
