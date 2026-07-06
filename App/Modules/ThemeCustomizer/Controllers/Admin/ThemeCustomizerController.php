<?php

declare(strict_types=1);

namespace App\Modules\ThemeCustomizer\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use App\Modules\ThemeCustomizer\ThemeCustomizerHelper;
use App\Theme\ThemeEngine;

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

        $primaryColor = trim($this->input->post('primary_color') ?? '#1E3A5F');
        $fontFamily   = trim($this->input->post('font_family')   ?? 'system');
        $cornerStyle  = trim($this->input->post('corner_style')  ?? 'subtle');
        $logoUrl      = trim($this->input->post('logo_url')      ?? '');
        $customCss    = $this->input->post('custom_css') ?? '';

        // Validate hex color
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
            $primaryColor = '#1E3A5F';
        }

        if (!in_array($cornerStyle, ['sharp', 'subtle', 'rounded'], true)) {
            $cornerStyle = 'subtle';
        }

        // Strip logo URL to relative paths or https only
        if ($logoUrl !== '' && !preg_match('/^(https?:\/\/|\/)/i', $logoUrl)) {
            $logoUrl = '';
        }

        $updates = [
            'primary_color' => $primaryColor,
            'font_family'   => $fontFamily,
            'corner_style'  => $cornerStyle,
            'logo_url'      => $logoUrl,
            'custom_css'    => $customCss,
        ];

        foreach ($updates as $key => $val) {
            $row = $this->db('settings')
                ->select('id')->where('key', $key)->where('grp', self::GRP)->get(1);
            if ($row) {
                $this->db('settings')->where('id', $row['id'])->save(['value' => $val], true);
            } else {
                $this->db('settings')->save(['key' => $key, 'value' => $val, 'grp' => self::GRP]);
            }
        }

        Auth::audit('theme-customizer.save', 'settings', '');
        $this->flash('success', 'Theme settings saved.');
        $this->redirect('/admin/theme-customizer');
    }

    /**
     * GET /admin/theme-customizer/preview?primary_color=&font_family=&corner_style=
     * Renders the active theme with pending (unsaved) settings applied, for the
     * live-preview iframe. Nothing is persisted here.
     */
    public function preview(): void
    {
        $this->requirePermission('theme-customizer.manage');

        $overrides = [];

        $primaryColor = $this->input->get('primary_color');
        if ($primaryColor !== null && preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
            $overrides['primary_color'] = $primaryColor;
        }

        $fontFamily = $this->input->get('font_family');
        if ($fontFamily !== null) {
            $overrides['font_family'] = $fontFamily;
        }

        $cornerStyle = $this->input->get('corner_style');
        if ($cornerStyle !== null && in_array($cornerStyle, ['sharp', 'subtle', 'rounded'], true)) {
            $overrides['corner_style'] = $cornerStyle;
        }

        ThemeCustomizerHelper::setPreviewOverrides($overrides);

        ThemeEngine::render('modules/theme-customizer/front/preview-demo', [
            'baseUrl'    => $this->baseUrl,
            'page_title' => 'Live Preview',
        ]);
    }
}
