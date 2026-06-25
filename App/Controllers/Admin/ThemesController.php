<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\CMS\Auth;
use App\Theme\ThemeEngine;

class ThemesController extends BaseController
{
    protected string $module = 'theme-manager';

    /** GET /admin/themes */
    public function index(): void
    {
        $this->requirePermission('settings.view');

        $this->adminRender('admin/themes/index', [
            'themes' => ThemeEngine::discover(),
        ], 'Themes', 'themes');
    }

    /** POST /admin/themes/set-theme */
    public function setTheme(): void
    {
        $this->requirePermission('settings.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }

        $slug = trim($this->input->post('slug', false) ?? '');

        $valid = array_column(ThemeEngine::discover(), 'slug');
        if (!in_array($slug, $valid, true)) {
            $this->json(['success' => false, 'message' => 'Theme not found.']);
        }

        $this->upsertSetting('active_theme', $slug, 'general');
        ThemeEngine::deploy($slug);

        Auth::audit('settings.set_theme', 'settings', '', ['theme' => $slug]);
        $this->json(['success' => true, 'message' => "Theme \"{$slug}\" activated."]);
    }

    private function upsertSetting(string $key, string $value, string $grp = 'general'): void
    {
        $exists = $this->db('settings')->where('key', $key)->get(1);
        if ($exists) {
            $this->db('settings')->where('key', $key)->update(['value' => $value]);
        } else {
            $this->db('settings')
                ->withoutTimestamps()
                ->save(['key' => $key, 'value' => $value, 'type' => 'string', 'grp' => $grp, 'label' => $key, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }
}
