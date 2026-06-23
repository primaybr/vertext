<?php

declare(strict_types=1);

namespace App\Modules\Contact\Controllers\Admin;

use App\Controllers\Admin\BaseController;

/**
 * Contact form settings.
 *
 * GET  /admin/contact/settings       → index()
 * POST /admin/contact/settings/save  → save()
 */
class ContactSettingsController extends BaseController
{
    protected string $module = 'contact';

    private const KEYS = [
        'contact_path'           => 'contact',
        'contact_admin_email'    => '',
        'contact_auto_reply'     => '0',
        'contact_auto_reply_msg' => '',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('contact.settings');

        $rows = $this->db('settings')->where('grp', 'contact')->get() ?: [];
        $s    = [];
        foreach ($rows as $r) {
            $s[$r['key']] = $r['value'];
        }
        // Ensure all keys exist
        foreach (self::KEYS as $k => $default) {
            if (!array_key_exists($k, $s)) {
                $s[$k] = $default;
            }
        }

        $this->adminRender('modules/contact/admin/contact/settings', [
            'settings' => $s,
        ], 'Contact Settings', 'contact');
    }

    public function save(): void
    {
        $this->requirePermission('contact.settings');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid.');
            $this->redirect($this->baseUrl . '/admin/contact/settings');
        }

        foreach (self::KEYS as $key => $default) {
            $val = trim($this->input->post($key, false) ?? $default);
            $this->upsertSetting($key, $val);
        }

        $this->flash('success', 'Contact settings saved.');
        $this->redirect($this->baseUrl . '/admin/contact/settings');
    }

    private function upsertSetting(string $key, string $value): void
    {
        $existing = $this->db('settings')->where('key', $key)->where('grp', 'contact')->get(1);
        if ($existing) {
            $this->db('settings')->where('key', $key)->where('grp', 'contact')->update(['value' => $value]);
        } else {
            $this->db('settings')->withoutTimestamps()->save([
                'key' => $key, 'value' => $value, 'type' => 'text', 'grp' => 'contact',
            ]);
        }
    }
}
