<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

/**
 * GET  /admin/newsletter/settings      → index()
 * POST /admin/newsletter/settings/save → save()
 */
class NewsletterSettingsController extends BaseController
{
    protected string $module = 'newsletter';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('newsletter.manage');

        $rows = (new \Core\Model('settings'))->where('grp', 'newsletter')->get() ?: [];
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['key']] = $r['value'];
        }

        $this->adminRender('modules/newsletter/admin/settings', [
            'settings' => $settings,
        ], 'Newsletter Settings', 'newsletter');
    }

    public function save(): void
    {
        $this->requirePermission('newsletter.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid.');
            $this->redirect($this->baseUrl . '/admin/newsletter/settings');
        }

        $keys = [
            'newsletter_from_name', 'newsletter_from_email', 'newsletter_double_optin', 'newsletter_confirm_subject',
            'newsletter_welcome_enabled', 'newsletter_welcome_subject', 'newsletter_welcome_body',
        ];
        $model = new \Core\Model('settings');

        foreach ($keys as $key) {
            $val = $this->input->post($key, false) ?? '';
            if ($key === 'newsletter_double_optin' || $key === 'newsletter_welcome_enabled') {
                $val = $this->input->post($key) ? '1' : '0';
            }
            $existing = $model->where('key', $key)->where('grp', 'newsletter')->get(1);
            if ($existing) {
                $model->where('key', $key)->where('grp', 'newsletter')->update(['value' => $val]);
            } else {
                $model->save(['key' => $key, 'value' => $val, 'grp' => 'newsletter']);
            }
        }

        Auth::audit('newsletter.settings_saved', 'settings', '', []);
        $this->flash('success', 'Settings saved.');
        $this->redirect($this->baseUrl . '/admin/newsletter/settings');
    }
}
