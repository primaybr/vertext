<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\CMS\Auth;
use Core\Folder\Path;
use App\Mail\Mailer;
use App\Mail\MailerConfig;
use App\Mail\MailMessage;
use App\Mail\MailTemplate;

/**
 * Admin Settings Controller
 */
class SettingsController extends BaseController
{
    protected string $module = 'cms-settings';

    private const MAIL_KEYS = [
        'mail_driver'       => 'mail',
        'mail_host'         => '',
        'mail_port'         => '587',
        'mail_username'     => '',
        'mail_password'     => '',
        'mail_encryption'   => 'tls',
        'mail_from_address' => '',
        'mail_from_name'    => '',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /** GET /admin/settings */
    public function index(): void
    {
        $this->requirePermission('settings.view');

        $this->ensureMailSettings();

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

        $allowed = ['site_name', 'site_url', 'site_description', 'admin_email', 'default_language', 'timezone', 'date_format', 'time_format'];

        foreach ($allowed as $key) {
            $value = $this->input->post($key, false) ?? '';
            $this->db('settings')->where('key', $key)->update(['value' => $value]);
        }

        Auth::audit('settings.save', 'settings');
        if ($this->isAjax()) { $this->json(['success' => true, 'message' => 'Settings saved successfully.']); }
        $this->flash('success', 'Settings saved successfully.');
        $this->redirect($this->baseUrl . '/admin/settings');
    }

    /** POST /admin/settings/save-mail */
    public function saveMail(): void
    {
        $this->requirePermission('settings.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }

        foreach (array_keys(self::MAIL_KEYS) as $key) {
            $value = $this->input->post($key, false) ?? '';
            $this->upsertSetting($key, $value, 'mail');
        }

        Auth::audit('settings.save_mail', 'settings');
        $this->json(['success' => true, 'message' => 'Mail settings saved.']);
    }

    /** POST /admin/settings/test-mail */
    public function testMail(): void
    {
        $this->requirePermission('settings.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }

        $toEmail = $this->currentUser['email'] ?? '';
        if (!$toEmail) {
            $this->json(['success' => false, 'message' => 'No email address found for your account.']);
        }

        $rows     = $this->db('settings')->get() ?: [];
        $settings = array_column($rows, 'value', 'key');

        $mailer  = new Mailer(MailerConfig::fromSettings($settings));
        $html    = '<p>This is a test email from Vertext CMS to confirm your mail settings are working correctly.</p>';
        $message = (new MailMessage())
            ->to($toEmail, $this->currentUser['name'] ?? '')
            ->subject('Test email from Vertext CMS')
            ->htmlBody($html);

        if ($mailer->send($message)) {
            $this->json(['success' => true, 'message' => "Test email sent to {$toEmail}."]);
        } else {
            $this->json(['success' => false, 'message' => 'Send failed: ' . $mailer->getLastError()]);
        }
    }

    /** POST /admin/settings/run-migration */
    public function runMigration(): void
    {
        $this->requirePermission('settings.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }

        try {
            $dbConfig = require ROOT . 'Storage' . DS . 'db.php';
            $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}";
            $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            require_once ROOT . 'Migrations' . DS . '002_uuid_migration.php';
            (new \Migration_002_UuidMigration($pdo))->up();

            \App\CMS\I18n::migrate();

            Auth::audit('settings.run_migration', 'settings');
            $this->json(['success' => true, 'message' => 'Migrations completed: UUID upgrade and i18n schema updates applied.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()]);
        }
    }

    /** POST /admin/settings/set-locale */
    public function setLocale(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
            }
            $this->flash('error', 'Security token invalid.');
            $this->redirect($this->baseUrl . '/admin/settings');
        }

        $locale = $this->input->post('locale') ?? 'en';
        \App\CMS\I18n::setLocale($locale);

        if ($this->isAjax()) {
            $this->json(['success' => true, 'locale' => \App\CMS\I18n::getLocale()]);
        }

        $back = $_SERVER['HTTP_REFERER'] ?? '';
        if (!$back || !str_starts_with($back, $this->baseUrl)) {
            $back = $this->baseUrl . '/admin/dashboard';
        }
        $this->redirect($back);
    }

    /** POST /admin/settings/toggle-maintenance */
    public function toggleMaintenance(): void
    {
        $this->requirePermission('settings.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }

        $row     = $this->db('settings')->where('key', 'maintenance_mode')->get(1);
        $current = $row['value'] ?? '0';
        $new     = $current === '1' ? '0' : '1';

        $this->db('settings')->where('key', 'maintenance_mode')->update(['value' => $new]);
        $this->deleteCacheFiles(rtrim(Path::CACHE, DS));
        Auth::audit('settings.maintenance_toggle', 'settings');

        $status = $new === '1' ? 'on' : 'off';
        $this->json([
            'success' => true,
            'enabled' => $new === '1',
            'message' => "Maintenance mode turned {$status}.",
        ]);
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
        $this->flash('success', "Cache cleared - {$deleted} file(s) removed.");
        $this->redirect($this->baseUrl . '/admin/settings');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function ensureMailSettings(): void
    {
        foreach (self::MAIL_KEYS as $key => $default) {
            $exists = $this->db('settings')->where('key', $key)->get(1);
            if (!$exists) {
                $this->db('settings')
                    ->withoutTimestamps()
                    ->save(['key' => $key, 'value' => $default, 'type' => 'string', 'grp' => 'mail', 'label' => $key, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
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
