<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\CMS\Auth;
use Core\Folder\Path;

/**
 * Admin Settings Controller
 */
class SettingsController extends BaseController
{
    protected string $module = 'cms-settings';

    public function __construct()
    {
        parent::__construct();
    }

    /** GET /admin/settings */
    public function index(): void
    {
        $this->requirePermission('settings.view');

        $rows = $this->db('settings')->orderBy('grp', 'ASC')->orderBy('key', 'ASC')->get() ?: [];
        $settings = [];
        $grouped  = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
            $grouped[$row['grp']][] = $row;
        }

        $this->adminRender('admin/settings/index', [
            'settings'       => $settings,
            'grouped'        => $grouped,
            'cacheFileCount' => $this->countCacheFiles(),
        ], 'Settings', 'settings');
    }

    /** POST /admin/settings/save */
    public function save(): void
    {
        $this->requirePermission('settings.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid.');
            $this->redirect($this->baseUrl . '/admin/settings');
        }

        $allowed = ['site_name', 'site_url', 'site_description', 'admin_email', 'default_language', 'timezone', 'date_format', 'time_format', 'maintenance_mode'];

        foreach ($allowed as $key) {
            $value = $this->input->post($key, false) ?? '';
            $this->db('settings')->where('key', $key)->update(['value' => $value]);
        }

        Auth::audit('settings.save', 'settings');
        if ($this->isAjax()) { $this->json(['success' => true, 'message' => 'Settings saved successfully.']); }
        $this->flash('success', 'Settings saved successfully.');
        $this->redirect($this->baseUrl . '/admin/settings');
    }

    /** POST /admin/settings/clear-cache */
    public function clearCache(): void
    {
        $this->requirePermission('settings.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid.');
            $this->redirect($this->baseUrl . '/admin/settings');
        }

        $deleted = $this->deleteCacheFiles(rtrim(Path::CACHE, DS));

        Auth::audit('settings.clear_cache', 'settings');
        $this->flash('success', "Cache cleared — {$deleted} file(s) removed.");
        $this->redirect($this->baseUrl . '/admin/settings');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function deleteCacheFiles(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $deleted = 0;
        $items   = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isFile()) {
                @unlink($item->getPathname()) && $deleted++;
            }
        }

        return $deleted;
    }

    private function countCacheFiles(): int
    {
        $dir = rtrim(Path::CACHE, DS);
        if (!is_dir($dir)) {
            return 0;
        }

        $count = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($items as $item) {
            if ($item->isFile()) {
                $count++;
            }
        }

        return $count;
    }
}
