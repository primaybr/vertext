<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\CMS\Auth;
use App\CMS\ModuleLoader;
use App\CMS\ModuleManager;

/**
 * Admin Module Manager Controller
 *
 * Handles: listing installed modules, toggling status,
 * installing new modules from App/Modules/, and uninstalling.
 */
class ModulesController extends BaseController
{
    protected string $module = 'module-manager';

    public function __construct()
    {
        parent::__construct();
    }

    // -- Index ------------------------------------------------------------------

    /** GET /admin/modules */
    public function index(): void
    {
        $this->requirePermission('modules.view');

        $modules   = $this->db('modules')->orderBy('is_core DESC, name', 'ASC')->get() ?: [];
        $available = ModuleManager::discover();

        // Annotate each available module with dependency status for the UI
        foreach ($available as &$avail) {
            $avail['deps'] = ModuleManager::getDependencyInfo($avail);
            $avail['deps_ok'] = empty($avail['deps']) ||
                count(array_filter($avail['deps'], fn($d) => !$d['installed'])) === 0;
        }
        unset($avail);

        // Read category + icon + version from manifest for installed non-core modules
        $modulesDir = defined('ROOT') ? ROOT . 'App' . DS . 'Modules' . DS : '';
        foreach ($modules as &$mod) {
            if (!empty($mod['is_core'])) {
                continue;
            }
            $dir = $mod['directory'] ?? '';
            $manifest = [];
            if ($dir && $modulesDir && file_exists($modulesDir . $dir . DS . 'module.json')) {
                $manifest = json_decode(file_get_contents($modulesDir . $dir . DS . 'module.json'), true) ?? [];
            }
            $mod['category'] = $manifest['category'] ?? 'Other';
            $mod['nav_icon'] = $manifest['nav']['icon'] ?? 'pi-layers';
            // Override version with the one from module.json if present
            if (!empty($manifest['version'])) {
                $mod['version'] = $manifest['version'];
            }
        }
        unset($mod);

        // Build core and category groups
        $coreModules = array_values(array_filter($modules, fn($m) => !empty($m['is_core'])));

        $categories = [];
        foreach ($modules as $mod) {
            if (!empty($mod['is_core'])) {
                continue;
            }
            $cat = $mod['category'] ?? 'Other';
            $categories[$cat]['installed'][] = $mod;
        }
        foreach ($available as $avail) {
            $cat = $avail['category'] ?? 'Other';
            $categories[$cat]['available'][] = $avail;
        }
        ksort($categories);

        $bundles = ModuleManager::getBundles();

        // Annotate bundle modules with install_settings from their module.json
        $installSettingsMap = [];
        foreach ($available as $avail) {
            if (!empty($avail['install_settings'])) {
                $installSettingsMap[$avail['slug']] = $avail['install_settings'];
            }
        }
        foreach ($bundles as &$bundle) {
            foreach ($bundle['modules'] as &$bmod) {
                if (isset($installSettingsMap[$bmod['slug']])) {
                    $bmod['install_settings'] = $installSettingsMap[$bmod['slug']];
                }
            }
        }
        unset($bundle, $bmod);

        // Also pass install_settings for a-la-carte available modules
        foreach ($available as &$avail) {
            if (!isset($avail['install_settings'])) {
                $avail['install_settings'] = [];
            }
        }
        unset($avail);

        $this->adminRender('admin/modules/index', [
            'modules'     => $modules,
            'available'   => $available,
            'coreModules' => $coreModules,
            'categories'  => $categories,
            'bundles'     => $bundles,
        ], 'Module Manager', 'modules');
    }

    // -- Toggle -----------------------------------------------------------------

    /** POST /admin/modules/([a-z0-9\-\_]+)/toggle */
    public function toggle(string $slug): void
    {
        $this->requirePermission('modules.toggle');

        $module = $this->db('modules')->where('slug', $slug)->get(1);

        if (!$module) {
            $this->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        if ($module['is_core']) {
            $this->json(['success' => false, 'message' => 'Core modules cannot be disabled'], 403);
        }

        $newStatus = $module['status'] === 'enabled' ? 'disabled' : 'enabled';
        $this->db('modules')->where('slug', $slug)->update(['status' => $newStatus]);

        Auth::audit("module.{$newStatus}", 'modules', (string) $module['id'], ['slug' => $slug]);

        ModuleLoader::refresh();

        $this->json(['success' => true, 'status' => $newStatus]);
    }

    // -- Install ----------------------------------------------------------------

    /** POST /admin/modules/([a-z0-9\-\_]+)/install */
    public function install(string $slug): void
    {
        $this->requirePermission('modules.install');
        $this->validateCsrf();

        $result = ModuleManager::install($slug);

        if ($result['success']) {
            $name = $result['name'] ?? $slug;
            Auth::audit('module.install', 'modules', '', ['slug' => $slug]);

            // Store any install_config settings the user provided
            $config = $this->input->post('install_config') ?? [];
            if (is_array($config)) {
                foreach ($config as $key => $value) {
                    $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key));
                    if (!$key) continue;
                    $existing = $this->db('settings')->where('key', $key)->get(1);
                    if ($existing) {
                        $this->db('settings')->where('key', $key)->update(['value' => (string) $value]);
                    } else {
                        $this->db('settings')->save(['group' => $slug, 'key' => $key, 'value' => (string) $value]);
                    }
                }
            }

            if ($this->isAjax()) {
                $response = ['success' => true, 'message' => "Module \"{$name}\" installed and enabled successfully."];
                if (!empty($result['setup_url'])) {
                    $response['setup_url'] = $result['setup_url'];
                }
                $this->json($response);
            }
            $this->flash('success', "Module \"{$name}\" installed and enabled successfully.");
        } else {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => $result['message'] ?? 'Installation failed.']);
            }
            $this->flash('error', $result['message'] ?? 'Installation failed.');
        }

        $this->redirect($this->baseUrl . '/admin/modules');
    }

    // -- Uninstall --------------------------------------------------------------

    /** POST /admin/modules/([a-z0-9\-\_]+)/uninstall */
    public function uninstall(string $slug): void
    {
        $this->requirePermission('modules.uninstall');
        $this->validateCsrf();

        // Fetch module info before uninstalling for guard check and flash message
        $mod = $this->db('modules')->where('slug', $slug)->get(1);

        if ($mod && (bool) $mod['is_core']) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => 'Core modules cannot be uninstalled.'], 403);
            }
            $this->flash('error', 'Core modules cannot be uninstalled.');
            $this->redirect($this->baseUrl . '/admin/modules');
        }

        $result = ModuleManager::uninstall($slug);

        if ($result['success']) {
            $name = $mod['name'] ?? $slug;
            Auth::audit('module.uninstall', 'modules', '', ['slug' => $slug]);
            if ($this->isAjax()) {
                $this->json(['success' => true, 'message' => "Module \"{$name}\" uninstalled successfully."]);
            }
            $this->flash('success', "Module \"{$name}\" uninstalled successfully.");
        } else {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => $result['message'] ?? 'Uninstall failed.']);
            }
            $this->flash('error', $result['message'] ?? 'Uninstall failed.');
        }

        $this->redirect($this->baseUrl . '/admin/modules');
    }

    // -- Sync Views ------------------------------------------------------------

    /** POST /admin/modules/([a-z0-9\-\_]+)/sync-views */
    public function syncViews(string $slug): void
    {
        $this->requirePermission('modules.install');
        $this->validateCsrf();

        $ok = ModuleManager::redeployViews($slug);

        if ($this->isAjax()) {
            if ($ok) {
                $this->json(['success' => true, 'message' => "Views for \"{$slug}\" redeployed."]);
            } else {
                $this->json(['success' => false, 'message' => 'Could not redeploy views. Module may not be installed.']);
            }
        }

        if ($ok) {
            $this->flash('success', "Views for \"{$slug}\" redeployed.");
        } else {
            $this->flash('error', 'Could not redeploy views.');
        }
        $this->redirect($this->baseUrl . '/admin/modules');
    }

    // -- Bundle CRUD -----------------------------------------------------------

    /** GET /admin/modules/bundles/create */
    public function bundleCreate(): void
    {
        $this->requirePermission('modules.install');
        $this->adminRender('admin/modules/bundle_form', [
            'bundle'     => null,
            'allModules' => $this->allKnownModules(),
            'action'     => $this->baseUrl . '/admin/modules/bundles/store',
        ], 'Create Bundle', 'modules');
    }

    /** POST /admin/modules/bundles/store */
    public function bundleStore(): void
    {
        $this->requirePermission('modules.install');
        $this->validateCsrf();

        $name = trim($this->input->post('name', false) ?? '');
        if (!$name) {
            $this->flash('error', 'Bundle name is required.');
            $this->redirect($this->baseUrl . '/admin/modules/bundles/create');
        }

        $rawSlug = trim($this->input->post('slug', false) ?? '');
        $slug    = preg_replace('/[^a-z0-9\-]/', '', strtolower($rawSlug ?: $name));
        $slug    = preg_replace('/\-+/', '-', trim($slug, '-'));

        if (!preg_match('/^[a-z][a-z0-9\-]*$/', $slug)) {
            $this->flash('error', 'Invalid bundle slug.');
            $this->redirect($this->baseUrl . '/admin/modules/bundles/create');
        }

        $bundleDir = ROOT . 'App' . DS . 'Bundles' . DS . $slug . DS;
        if (is_dir($bundleDir)) {
            $this->flash('error', "A bundle with slug \"{$slug}\" already exists.");
            $this->redirect($this->baseUrl . '/admin/modules/bundles/create');
        }

        $modules  = array_values(array_filter((array) ($this->input->post('bundle_modules') ?? []), fn($s) => is_string($s)));
        $required = (array) ($this->input->post('required') ?? []);
        $modsList = array_map(fn($s) => ['slug' => $s, 'required' => in_array($s, $required, true)], $modules);

        $data = [
            'name'        => $name,
            'slug'        => $slug,
            'version'     => '1.0.0',
            'description' => trim($this->input->post('description', false) ?? ''),
            'icon'        => trim($this->input->post('icon', false) ?? 'pi-layers'),
            'category'    => trim($this->input->post('category', false) ?? 'Custom'),
            'custom'      => true,
            'modules'     => $modsList,
        ];

        mkdir($bundleDir, 0755, true);
        file_put_contents($bundleDir . 'bundle.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Auth::audit('bundle.create', 'bundles', '', ['slug' => $slug]);
        $this->flash('success', "Bundle \"{$name}\" created.");
        $this->redirect($this->baseUrl . '/admin/modules');
    }

    /** GET /admin/modules/bundles/{slug}/edit */
    public function bundleEdit(string $slug): void
    {
        $this->requirePermission('modules.install');
        $bundle = $this->loadCustomBundle($slug);
        if (!$bundle) {
            $this->flash('error', 'Bundle not found or cannot be edited.');
            $this->redirect($this->baseUrl . '/admin/modules');
        }

        $this->adminRender('admin/modules/bundle_form', [
            'bundle'     => $bundle,
            'allModules' => $this->allKnownModules(),
            'action'     => $this->baseUrl . "/admin/modules/bundles/{$slug}/update",
        ], 'Edit Bundle - ' . ($bundle['name'] ?? $slug), 'modules');
    }

    /** POST /admin/modules/bundles/{slug}/update */
    public function bundleUpdate(string $slug): void
    {
        $this->requirePermission('modules.install');
        $this->validateCsrf();

        $bundle = $this->loadCustomBundle($slug);
        if (!$bundle) {
            $this->flash('error', 'Bundle not found or cannot be edited.');
            $this->redirect($this->baseUrl . '/admin/modules');
        }

        $name = trim($this->input->post('name', false) ?? '');
        if (!$name) {
            $this->flash('error', 'Bundle name is required.');
            $this->redirect($this->baseUrl . "/admin/modules/bundles/{$slug}/edit");
        }

        $modules  = array_values(array_filter((array) ($this->input->post('bundle_modules') ?? []), fn($s) => is_string($s)));
        $required = (array) ($this->input->post('required') ?? []);
        $modsList = array_map(fn($s) => ['slug' => $s, 'required' => in_array($s, $required, true)], $modules);

        $data = array_merge($bundle, [
            'name'        => $name,
            'description' => trim($this->input->post('description', false) ?? ''),
            'icon'        => trim($this->input->post('icon', false) ?? 'pi-layers'),
            'category'    => trim($this->input->post('category', false) ?? 'Custom'),
            'modules'     => $modsList,
        ]);

        $bundleFile = ROOT . 'App' . DS . 'Bundles' . DS . $slug . DS . 'bundle.json';
        file_put_contents($bundleFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Auth::audit('bundle.update', 'bundles', '', ['slug' => $slug]);
        $this->flash('success', "Bundle \"{$name}\" updated.");
        $this->redirect($this->baseUrl . '/admin/modules');
    }

    /** POST /admin/modules/bundles/{slug}/delete */
    public function bundleDelete(string $slug): void
    {
        $this->requirePermission('modules.install');
        $this->validateCsrf();

        $bundle = $this->loadCustomBundle($slug);
        if (!$bundle) {
            $this->json(['success' => false, 'message' => 'Bundle not found or cannot be deleted.'], 404);
        }

        $bundleDir = ROOT . 'App' . DS . 'Bundles' . DS . $slug . DS;
        @unlink($bundleDir . 'bundle.json');
        @rmdir($bundleDir);

        Auth::audit('bundle.delete', 'bundles', '', ['slug' => $slug]);
        $this->json(['success' => true, 'message' => "Bundle \"{$bundle['name']}\" deleted."]);
    }

    /** Load a bundle manifest only if it is custom (not builtin). Returns null otherwise. */
    private function loadCustomBundle(string $slug): ?array
    {
        if (!preg_match('/^[a-z][a-z0-9\-_]*$/', $slug)) {
            return null;
        }
        $file = ROOT . 'App' . DS . 'Bundles' . DS . $slug . DS . 'bundle.json';
        if (!file_exists($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !empty($data['builtin'])) {
            return null;
        }
        return $data;
    }

    /** Deduplicated list of all module slugs+names (installed + discoverable). */
    private function allKnownModules(): array
    {
        $installed = $this->db('modules')
            ->select('slug, name')
            ->whereQuery('is_core = FALSE')
            ->orderBy('name', 'ASC')
            ->get() ?: [];

        $available = ModuleManager::discover();
        $map = [];
        foreach ($installed as $m) {
            $map[$m['slug']] = $m['name'];
        }
        foreach ($available as $a) {
            if (!isset($map[$a['slug']])) {
                $map[$a['slug']] = $a['name'] ?? $a['slug'];
            }
        }
        ksort($map);

        return array_map(fn($slug, $name) => ['slug' => $slug, 'name' => $name], array_keys($map), $map);
    }

    // -- Install Bundle --------------------------------------------------------

    /** POST /admin/modules/install-bundle */
    public function installBundle(): void
    {
        $this->requirePermission('modules.install');
        $this->validateCsrf();

        $slugs = $this->input->post('modules') ?? [];
        if (!is_array($slugs) || empty($slugs)) {
            $this->json(['success' => false, 'message' => 'No modules selected.']);
        }

        // Sanitize: keep only valid slugs
        $slugs = array_values(array_filter($slugs, fn($s) => is_string($s) && preg_match('/^[a-z][a-z0-9\-_]*$/', $s)));
        if (empty($slugs)) {
            $this->json(['success' => false, 'message' => 'No valid module slugs provided.']);
        }

        $results       = ModuleManager::installBatch($slugs);
        $installConfig = $this->input->post('install_config') ?? [];

        $anySuccess = false;
        foreach ($results as $slug => $r) {
            if ($r['success'] && empty($r['skipped'])) {
                Auth::audit('module.install', 'modules', '', ['slug' => $slug, 'via' => 'bundle']);
                $anySuccess = true;

                // Store per-module install_config if provided
                $modConfig = $installConfig[$slug] ?? [];
                if (is_array($modConfig)) {
                    foreach ($modConfig as $key => $value) {
                        $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key));
                        if (!$key) continue;
                        $existing = $this->db('settings')->where('key', $key)->get(1);
                        if ($existing) {
                            $this->db('settings')->where('key', $key)->update(['value' => (string) $value]);
                        } else {
                            $this->db('settings')->save(['group' => $slug, 'key' => $key, 'value' => (string) $value]);
                        }
                    }
                }
            }
        }

        $this->json(['success' => true, 'results' => $results, 'any_installed' => $anySuccess]);
    }

    // -- Marketplace: Install from URL -----------------------------------------

    /**
     * POST /admin/modules/fetch-url
     * Download ZIP from URL, compute SHA-256, read module.json, store temp file.
     * Returns manifest info + hash for the user to verify before confirming install.
     */
    public function fetchUrl(): void
    {
        $this->requirePermission('modules.install');
        $this->validateCsrfJson();

        if (!class_exists(\ZipArchive::class)) {
            $this->json(['success' => false, 'message' => 'The PHP zip extension (ZipArchive) is required. Enable php_zip in your server\'s php.ini.']);
        }

        $url = trim($this->input->post('url', false) ?? '');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->json(['success' => false, 'message' => 'Invalid URL.']);
        }

        // Require HTTPS
        if (!str_starts_with(strtolower($url), 'https://')) {
            $this->json(['success' => false, 'message' => 'Only HTTPS URLs are allowed.']);
        }

        // SSRF prevention: resolve hostname and reject private/reserved ranges
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            $this->json(['success' => false, 'message' => 'Cannot parse URL hostname.']);
        }
        $ip = gethostbyname($host);
        if ($ip === $host || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $this->json(['success' => false, 'message' => 'URL resolves to a private or reserved IP address.']);
        }

        $cacheDir = ROOT . 'Cache' . DS . 'marketplace' . DS;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Download ZIP (max 50 MiB, 30s timeout)
        $ctx = stream_context_create([
            'http' => [
                'timeout'         => 30,
                'method'          => 'GET',
                'follow_location' => 1,
                'max_redirects'   => 3,
                'user_agent'      => 'Vertext-CMS/0.0.7 (+https://github.com/vertext-cms)',
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $bytes = @file_get_contents($url, false, $ctx, 0, 52428800);

        if ($bytes === false || strlen($bytes) < 22) {
            $this->json(['success' => false, 'message' => 'Could not download the URL. Check the address is reachable and returns a ZIP file.']);
        }

        // Verify ZIP magic bytes PK\x03\x04
        if (substr($bytes, 0, 4) !== "PK\x03\x04") {
            $this->json(['success' => false, 'message' => 'Downloaded file is not a valid ZIP archive.']);
        }

        $hash  = hash('sha256', $bytes);
        $token = bin2hex(random_bytes(16));

        $tmpFile = $cacheDir . $token . '.zip';
        file_put_contents($tmpFile, $bytes);
        unset($bytes);

        // Read module.json from ZIP without full extraction
        $zip = new \ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            @unlink($tmpFile);
            $this->json(['success' => false, 'message' => 'Could not open the ZIP archive.']);
        }

        $manifestContent = $zip->getFromName('module.json');
        if ($manifestContent === false) {
            // Try one directory deep (GitHub archive format)
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('/^[^\/]+\/module\.json$/', $name)) {
                    $manifestContent = $zip->getFromIndex($i);
                    break;
                }
            }
        }
        $zip->close();

        if ($manifestContent === false) {
            @unlink($tmpFile);
            $this->json(['success' => false, 'message' => 'No module.json found in the ZIP. This does not appear to be a Vertext module package.']);
        }

        $manifest = json_decode($manifestContent, true);
        if (!is_array($manifest) || empty($manifest['slug']) || empty($manifest['name'])) {
            @unlink($tmpFile);
            $this->json(['success' => false, 'message' => 'module.json is invalid or missing required fields (name, slug).']);
        }

        $slug = $manifest['slug'];
        if (!preg_match('/^[a-z][a-z0-9\-_]*$/', $slug)) {
            @unlink($tmpFile);
            $this->json(['success' => false, 'message' => "Invalid module slug \"{$slug}\" in module.json."]);
        }

        // Slug collision check
        if ((new \Core\Model('modules'))->select('id')->where('slug', $slug)->get(1)) {
            @unlink($tmpFile);
            $this->json(['success' => false, 'message' => "Module \"{$slug}\" is already installed."]);
        }

        $dirName = $this->slugToPascal($slug);
        if (is_dir(ROOT . 'App' . DS . 'Modules' . DS . $dirName)) {
            @unlink($tmpFile);
            $this->json(['success' => false, 'message' => "A module directory \"{$dirName}\" already exists in App/Modules/. Remove it first."]);
        }

        // Persist token in session for the confirm step
        $this->session->set('marketplace_token', [
            'token'    => $token,
            'slug'     => $slug,
            'dir_name' => $dirName,
            'hash'     => $hash,
        ]);

        $this->json([
            'success'     => true,
            'hash'        => $hash,
            'name'        => $manifest['name'],
            'slug'        => $slug,
            'version'     => $manifest['version']     ?? '?',
            'description' => $manifest['description'] ?? '',
            'author'      => $manifest['author']      ?? '',
        ]);
    }

    /**
     * POST /admin/modules/install-from-url
     * Extract the previously fetched ZIP and run ModuleManager::install().
     */
    public function installFromUrl(): void
    {
        $this->requirePermission('modules.install');
        $this->validateCsrfJson();

        if (!class_exists(\ZipArchive::class)) {
            $this->json(['success' => false, 'message' => 'The PHP zip extension (ZipArchive) is required.']);
        }

        $stored = $this->session->get('marketplace_token');
        if (!is_array($stored) || empty($stored['token'])) {
            $this->json(['success' => false, 'message' => 'Session expired. Please re-download the module.']);
        }

        $token        = $stored['token'];
        $slug         = $stored['slug'];
        $dirName      = $stored['dir_name'];
        $expectedHash = $stored['hash'];

        $this->session->delete('marketplace_token');

        $tmpFile = ROOT . 'Cache' . DS . 'marketplace' . DS . $token . '.zip';
        if (!file_exists($tmpFile)) {
            $this->json(['success' => false, 'message' => 'Temporary file not found. Please re-download the module.']);
        }

        // Re-verify integrity before extraction
        if (hash_file('sha256', $tmpFile) !== $expectedHash) {
            @unlink($tmpFile);
            $this->json(['success' => false, 'message' => 'Integrity check failed: file hash does not match.']);
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            @unlink($tmpFile);
            $this->json(['success' => false, 'message' => 'Could not open ZIP for extraction.']);
        }

        // Detect common prefix (GitHub archives include a top-level directory)
        $prefix       = null;
        $manifestName = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Path traversal check
            if (str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
                $zip->close();
                @unlink($tmpFile);
                $this->json(['success' => false, 'message' => "ZIP contains unsafe path \"{$name}\"."]);
            }
            if ($prefix === null) {
                $parts = explode('/', $name, 2);
                if (count($parts) > 1) {
                    $prefix = $parts[0] . '/';
                }
            }
            if ($name === 'module.json' || preg_match('/^[^\/]+\/module\.json$/', $name)) {
                $manifestName = $name;
            }
        }

        // If module.json is at root, no prefix stripping needed
        $stripPrefix = ($manifestName !== null && $manifestName !== 'module.json') ? $prefix : '';

        $modDir = ROOT . 'App' . DS . 'Modules' . DS . $dirName . DS;
        if (!mkdir($modDir, 0755, true) && !is_dir($modDir)) {
            $zip->close();
            @unlink($tmpFile);
            $this->json(['success' => false, 'message' => "Could not create module directory \"{$dirName}\"."]);
        }

        // Extract files one by one to apply prefix stripping and traversal guard
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            // Strip detected prefix
            if ($stripPrefix !== '' && str_starts_with($name, $stripPrefix)) {
                $relative = substr($name, strlen($stripPrefix));
            } else {
                $relative = ($stripPrefix === '') ? $name : null;
            }

            if ($relative === null || $relative === '' || str_ends_with($relative, '/')) {
                continue;
            }

            $destPath = $modDir . str_replace('/', DS, $relative);
            $destDir  = dirname($destPath);

            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            $content = $zip->getFromIndex($i);
            if ($content !== false) {
                file_put_contents($destPath, $content);
            }
        }

        $zip->close();
        @unlink($tmpFile);

        // Run ModuleManager install
        $result = ModuleManager::install($slug);

        if ($result['success']) {
            Auth::audit('module.install', 'modules', '', ['slug' => $slug, 'via' => 'marketplace-url']);
            $this->json(['success' => true, 'message' => "Module \"{$result['name']}\" installed successfully."]);
        } else {
            // Remove extracted directory on install failure to keep filesystem clean
            if (is_dir($modDir)) {
                ModuleManager::removeExtractedDir($modDir);
            }
            $this->json(['success' => false, 'message' => $result['message'] ?? 'Installation failed after extraction.']);
        }
    }

    // -- Helpers ----------------------------------------------------------------

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/modules');
        }
    }

    /** Like validateCsrf() but returns JSON 403 instead of redirect (for AJAX-only endpoints). */
    private function validateCsrfJson(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }

    /** Convert a kebab-slug to PascalCase directory name: 'my-module' -> 'MyModule'. */
    private function slugToPascal(string $slug): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug)));
    }
}
