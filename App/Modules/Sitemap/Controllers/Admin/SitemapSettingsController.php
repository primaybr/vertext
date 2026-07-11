<?php

declare(strict_types=1);

namespace App\Modules\Sitemap\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use App\CMS\ModuleLoader;

/**
 * Sitemap / robots.txt settings.
 *
 * GET  /admin/sitemap/settings
 * POST /admin/sitemap/settings/save
 */
class SitemapSettingsController extends BaseController
{
    protected string $module = 'sitemap';

    private const SETTINGS_GROUP = 'sitemap';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('sitemap.settings');

        $rows = $this->db('settings')
            ->select('key, value')
            ->where('grp', self::SETTINGS_GROUP)
            ->get() ?: [];

        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['key']] = $r['value'];
        }

        $defaults = [
            'sitemap_include_pages'   => '1',
            'sitemap_include_blog'    => '1',
            'sitemap_include_events'  => '1',
            'sitemap_include_gallery' => '1',
            'sitemap_include_videos'  => '1',
            'robots_extra_disallow'   => '',
        ];

        $settings = array_merge($defaults, $settings);

        $this->adminRender('modules/sitemap/admin/settings/index', [
            'settings'      => $settings,
            'eventsEnabled' => ModuleLoader::isEnabled('events'),
            'galleryEnabled'=> ModuleLoader::isEnabled('gallery'),
            'videosEnabled' => ModuleLoader::isEnabled('videos'),
        ], 'Sitemap Settings', 'sitemap');
    }

    public function save(): void
    {
        $this->requirePermission('sitemap.settings');
        $this->validateCsrf();

        foreach (['sitemap_include_pages', 'sitemap_include_blog', 'sitemap_include_events', 'sitemap_include_gallery', 'sitemap_include_videos'] as $key) {
            $value = $this->input->post($key, false) ? '1' : '0';
            $this->upsertSetting($key, $value, 'bool');
        }

        $extra = trim($this->input->post('robots_extra_disallow', false) ?? '');
        $this->upsertSetting('robots_extra_disallow', $extra, 'text');

        Auth::audit('sitemap.settings.save', 'settings');
        $this->json(['success' => true, 'message' => 'Sitemap settings saved.']);
    }

    private function upsertSetting(string $key, string $value, string $type): void
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
                ->update(['value' => $value]);
        } else {
            $this->db('settings')->save([
                'key'   => $key,
                'value' => $value,
                'grp'   => self::SETTINGS_GROUP,
                'type'  => $type,
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
