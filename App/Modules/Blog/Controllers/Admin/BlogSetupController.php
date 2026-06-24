<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\ModuleManager;

/**
 * One-time setup wizard for the Blog module.
 *
 * GET  /admin/blog/setup
 * POST /admin/blog/setup/complete
 *
 * Fires immediately after install (via setup_url in install response).
 * Can be revisited at any time - it pre-fills current values.
 */
class BlogSetupController extends BaseController
{
    protected string $module = 'blog';

    private const GRP = 'blog';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('blog.settings');

        $rows = $this->db('settings')
            ->select('key, value')
            ->where('grp', self::GRP)
            ->get() ?: [];

        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['key']] = $r['value'];
        }

        $defaults = [
            'blog_title'       => 'Blog',
            'blog_description' => '',
            'posts_per_page'   => '10',
            'comments_enabled' => '1',
            'blog_base_path'   => 'blog',
        ];
        $settings = array_merge($defaults, $settings);

        $this->adminRender('modules/blog/admin/setup/index', [
            'settings' => $settings,
        ], 'Blog Setup', 'blog.settings');
    }

    public function complete(): void
    {
        $this->requirePermission('blog.settings');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/blog/setup');
        }

        $rawPath = trim($this->input->post('blog_base_path', false) ?? 'blog');
        $rawPath = strtolower(preg_replace('/[^a-z0-9\-_\/]/i', '', $rawPath));
        $rawPath = trim($rawPath, '/');

        $fields = [
            'blog_title'       => trim($this->input->post('blog_title',       false) ?? 'Blog'),
            'blog_description' => trim($this->input->post('blog_description', false) ?? ''),
            'posts_per_page'   => (string) max(1, (int) ($this->input->post('posts_per_page', false) ?? 10)),
            'comments_enabled' => $this->input->post('comments_enabled', false) ? '1' : '0',
            'blog_base_path'   => $rawPath,
        ];

        foreach ($fields as $key => $value) {
            $existing = $this->db('settings')
                ->select('id')
                ->where('key', $key)
                ->where('grp', self::GRP)
                ->get(1);

            if ($existing) {
                $this->db('settings')
                    ->where('key', $key)
                    ->where('grp', self::GRP)
                    ->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
            } else {
                $this->db('settings')->save([
                    'key'   => $key,
                    'value' => $value,
                    'grp'   => self::GRP,
                    'type'  => 'string',
                    'label' => ucwords(str_replace('_', ' ', $key)),
                ]);
            }
        }

        ModuleManager::clearRouteCache();

        $this->flash('success', 'Blog is set up and ready. Welcome!');
        $this->redirect($this->baseUrl . '/admin/blog');
    }
}
