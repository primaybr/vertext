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

    // ── Index ──────────────────────────────────────────────────────────────────

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

        // Read category + icon from manifest for installed non-core modules
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

        $this->adminRender('admin/modules/index', [
            'modules'     => $modules,
            'available'   => $available,
            'coreModules' => $coreModules,
            'categories'  => $categories,
            'bundles'     => $bundles,
        ], 'Module Manager', 'modules');
    }

    // ── Toggle ─────────────────────────────────────────────────────────────────

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

    // ── Install ────────────────────────────────────────────────────────────────

    /** POST /admin/modules/([a-z0-9\-\_]+)/install */
    public function install(string $slug): void
    {
        $this->requirePermission('modules.install');
        $this->validateCsrf();

        $result = ModuleManager::install($slug);

        if ($result['success']) {
            $name     = $result['name'] ?? $slug;
            Auth::audit('module.install', 'modules', '', ['slug' => $slug]);
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

    // ── Uninstall ──────────────────────────────────────────────────────────────

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

    // ── Sync Views ────────────────────────────────────────────────────────────

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

    // ── Install Bundle ────────────────────────────────────────────────────────

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

        $results = ModuleManager::installBatch($slugs);

        $anySuccess = false;
        foreach ($results as $slug => $r) {
            if ($r['success'] && empty($r['skipped'])) {
                Auth::audit('module.install', 'modules', '', ['slug' => $slug, 'via' => 'bundle']);
                $anySuccess = true;
            }
        }

        $this->json(['success' => true, 'results' => $results, 'any_installed' => $anySuccess]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/modules');
        }
    }
}
