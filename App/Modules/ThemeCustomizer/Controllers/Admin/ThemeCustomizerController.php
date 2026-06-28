<?php

declare(strict_types=1);

namespace App\Modules\ThemeCustomizer\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

class ThemeCustomizerController extends BaseController
{
    protected string $module = 'theme-customizer';

    private const GRP = 'theme-customizer';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('theme-customizer.manage');

        $rows     = $this->db('settings')->where('grp', self::GRP)->get() ?: [];
        $settings = array_column($rows, 'value', 'key');

        $this->adminRender('modules/theme-customizer/admin/theme-customizer/index', [
            'settings' => $settings,
        ], 'Theme Customizer', 'theme-customizer');
    }

    public function save(): void
    {
        $this->requirePermission('theme-customizer.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid.');
            $this->redirect('/admin/theme-customizer');
            return;
        }

        $primaryColor = trim($this->input->post('primary_color') ?? '#2563EB');
        $fontFamily   = trim($this->input->post('font_family')   ?? 'system');
        $logoUrl      = trim($this->input->post('logo_url')      ?? '');
        $customCss    = $this->input->post('custom_css') ?? '';

        // Validate hex color
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
            $primaryColor = '#2563EB';
        }

        // Strip logo URL to relative paths or https only
        if ($logoUrl !== '' && !preg_match('/^(https?:\/\/|\/)/i', $logoUrl)) {
            $logoUrl = '';
        }

        $updates = [
            'primary_color' => $primaryColor,
            'font_family'   => $fontFamily,
            'logo_url'      => $logoUrl,
            'custom_css'    => $customCss,
        ];

        foreach ($updates as $key => $val) {
            $row = $this->db('settings')
                ->select('id')->where('key', $key)->where('grp', self::GRP)->get(1);
            if ($row) {
                $this->db('settings')->where('id', $row['id'])->save(['value' => $val]);
            } else {
                $this->db('settings')->save(['key' => $key, 'value' => $val, 'grp' => self::GRP]);
            }
        }

        Auth::audit('theme-customizer.save', 'settings', '');
        $this->flash('success', 'Theme settings saved.');
        $this->redirect('/admin/theme-customizer');
    }
}
