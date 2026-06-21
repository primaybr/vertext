<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use App\CMS\ModuleManager;

/**
 * Blog-wide settings.
 *
 * GET  /admin/blog/settings
 * POST /admin/blog/settings/save
 */
class BlogSettingsController extends BaseController
{
    protected string $module = 'blog';

    private const SETTINGS_GROUP = 'blog';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('blog.settings');

        $rows = $this->db('settings')
            ->select('key, value')
            ->where('grp', self::SETTINGS_GROUP)
            ->get() ?: [];

        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['key']] = $r['value'];
        }

        $defaults = [
            'blog_title'                => 'Blog',
            'blog_description'          => '',
            'posts_per_page'            => '10',
            'comments_enabled'          => '1',
            'comments_require_approval' => '1',
            'og_default_image'          => '',
            'blog_base_path'            => 'blog',
        ];

        $settings = array_merge($defaults, $settings);

        $this->adminRender('modules/blog/admin/settings/index', [
            'settings' => $settings,
        ], 'Blog Settings', 'blog.settings');
    }

    public function save(): void
    {
        $this->requirePermission('blog.settings');
        $this->validateCsrf();

        // Capture current path before saving so we can detect a change
        $oldPathRow = $this->db('settings')
            ->select('value')
            ->where('key', 'blog_base_path')
            ->where('grp', self::SETTINGS_GROUP)
            ->get(1);
        $oldPath = $oldPathRow ? $oldPathRow['value'] : 'blog';

        // Sanitise and normalise the new path
        $rawNewPath = trim($this->input->post('blog_base_path', false) ?? 'blog');
        $rawNewPath = strtolower(preg_replace('/[^a-z0-9\-_\/]/i', '', $rawNewPath));
        $newPath    = trim($rawNewPath, '/');

        $fields = [
            'blog_title', 'blog_description', 'posts_per_page',
            'comments_enabled', 'comments_require_approval', 'og_default_image',
        ];

        foreach ($fields as $key) {
            $value = trim($this->input->post($key, false) ?? '');
            $this->upsertSetting($key, $value);
        }

        // Always persist the normalised path
        $this->upsertSetting('blog_base_path', $newPath);

        // Handle path change
        if ($newPath !== $oldPath) {
            $mode = $this->input->post('path_change_mode', false) ?? 'permanent';

            if ($mode === 'redirect') {
                $redirectRow = $this->db('settings')
                    ->select('value')
                    ->where('key', 'blog_redirect_paths')
                    ->where('grp', self::SETTINGS_GROUP)
                    ->get(1);
                $existing = json_decode($redirectRow['value'] ?? '[]', true) ?: [];

                // Add old path, remove any entry that matches the new path
                if ($oldPath !== '' && !in_array($oldPath, $existing, true)) {
                    $existing[] = $oldPath;
                }
                $existing = array_values(array_filter($existing, fn($p) => $p !== $newPath));

                $this->upsertSetting('blog_redirect_paths', json_encode($existing));
            }

            // New routes → clear cache so they take effect immediately
            ModuleManager::clearRouteCache();
        }

        Auth::audit('blog.settings.save', 'settings', 0);
        $this->json(['success' => true, 'message' => 'Blog settings saved.']);
    }

    private function upsertSetting(string $key, string $value): void
    {
        $existing = $this->db('settings')
            ->select('id')
            ->where('key', $key)
            ->where('grp', self::SETTINGS_GROUP)
            ->get(1);

        if ($existing) {
            $this->db('settings')
                ->where('key', $key)
                ->where('grp', self::SETTINGS_GROUP)
                ->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $this->db('settings')->save([
                'key'   => $key,
                'value' => $value,
                'grp'   => self::SETTINGS_GROUP,
                'type'  => 'string',
                'label' => ucwords(str_replace('_', ' ', $key)),
            ]);
        }
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }
}
