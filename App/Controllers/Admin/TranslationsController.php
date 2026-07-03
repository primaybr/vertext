<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\CMS\Auth;
use App\CMS\I18n;

/**
 * Translation management UI (i18n v0.0.2).
 *
 * Edits the PHP array files under App/Lang/{locale}/{group}.php. English (en)
 * is the reference locale; other locales are edited against it. Writes are
 * atomic: the new file is validated by re-including it before replacing.
 *
 * GET  /admin/translations                → index()
 * POST /admin/translations/save          → save()
 * GET  /admin/translations/add-locale    → addLocaleForm()
 * POST /admin/translations/add-locale    → addLocale()
 */
class TranslationsController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->ensurePermission();
    }

    private function ensurePermission(): void
    {
        try {
            $db = $this->db('permissions')->db;
            $db->query("INSERT INTO permissions (name, slug, description, module)
                        VALUES ('Translations - Manage', 'translations.manage', 'Edit interface translations', 'core')
                        ON CONFLICT (slug) DO NOTHING");
            $db->execute();
            $db->query("INSERT INTO role_permissions (role_id, permission_id)
                        SELECT r.id, p.id FROM roles r, permissions p
                        WHERE r.slug = 'administrator' AND p.slug = 'translations.manage'
                        ON CONFLICT DO NOTHING");
            $db->execute();
        } catch (\Throwable) {
        }
    }

    private function langDir(): string
    {
        return ROOT . 'App' . DS . 'Lang' . DS;
    }

    /** @return string[] group names = base .php filenames of the en locale */
    private function groups(): array
    {
        $groups = [];
        foreach (glob($this->langDir() . 'en' . DS . '*.php') ?: [] as $file) {
            $groups[] = basename($file, '.php');
        }
        sort($groups);
        return $groups;
    }

    private function loadGroup(string $locale, string $group): array
    {
        $file = $this->langDir() . $locale . DS . $group . '.php';
        if (!is_file($file)) return [];
        $data = include $file;
        return is_array($data) ? $data : [];
    }

    public function index(): void
    {
        $this->requirePermission('translations.manage');

        $locales = I18n::getSupportedLocales();
        $groups  = $this->groups();

        $locale = $this->input->get('locale') ?: 'id';
        if (!in_array($locale, $locales, true)) {
            $locale = $locales[0] ?? 'en';
        }
        $group = $this->input->get('group') ?: ($groups[0] ?? 'app');
        if (!in_array($group, $groups, true)) {
            $group = $groups[0] ?? 'app';
        }

        $reference = $this->loadGroup('en', $group);
        $target    = $this->loadGroup($locale, $group);

        // Union of keys so missing target strings show up (and vice versa)
        $keys = array_unique(array_merge(array_keys($reference), array_keys($target)));
        sort($keys);

        $missing = 0;
        foreach ($keys as $key) {
            if (trim((string) ($target[$key] ?? '')) === '') $missing++;
        }

        if ($this->isAjax()) {
            // For AJAX requests, return only the content for modal display
            $this->renderPartial('admin/translations/index', [
                'locales'   => $locales,
                'groups'    => $groups,
                'locale'    => $locale,
                'group'     => $group,
                'reference' => $reference,
                'target'    => $target,
                'keys'      => $keys,
                'missing'   => $missing,
                'baseUrl'   => $this->baseUrl,
                'isAjax'    => true,
                'csrfToken' => $this->csrf->getToken(),
            ]);
        } else {
            $this->adminRender('admin/translations/index', [
                'locales'   => $locales,
                'groups'    => $groups,
                'locale'    => $locale,
                'group'     => $group,
                'reference' => $reference,
                'target'    => $target,
                'keys'      => $keys,
                'missing'   => $missing,
            ], 'Translations', 'translations');
        }
    }

    /** POST /admin/translations/save - AJAX */
    public function save(): void
    {
        $this->requirePermission('translations.manage');
        $this->validateCsrf();

        $locale = (string) ($this->input->post('locale') ?? '');
        $group  = (string) ($this->input->post('group') ?? '');

        if (!in_array($locale, I18n::getSupportedLocales(), true)) {
            $this->json(['success' => false, 'message' => 'Unknown locale.']);
        }
        if (!in_array($group, $this->groups(), true)) {
            $this->json(['success' => false, 'message' => 'Unknown translation group.']);
        }

        $keys   = (array) ($this->input->post('t_key', false) ?? []);
        $values = (array) ($this->input->post('t_value', false) ?? []);

        $data = [];
        foreach ($keys as $i => $key) {
            $key = trim((string) $key);
            if ($key === '' || !preg_match('/^[a-z0-9_\.\-]+$/i', $key)) continue;
            $data[$key] = (string) ($values[$i] ?? '');
        }

        if (!$this->writeGroup($locale, $group, $data)) {
            $this->json(['success' => false, 'message' => 'Could not write the language file. Check filesystem permissions.']);
        }

        Auth::audit('translations.saved', 'lang', '', ['locale' => $locale, 'group' => $group, 'keys' => count($data)]);
        $this->json(['success' => true, 'message' => ucfirst($group) . " translations for \"{$locale}\" saved."]);
    }

    /** GET /admin/translations/add-locale - AJAX: returns add locale form for modal */
    public function addLocaleForm(): void
    {
        $this->requirePermission('translations.manage');

        $locales = I18n::getSupportedLocales();

        $this->renderPartial('admin/translations/_add_locale_form', [
            'locales' => $locales,
        ]);
    }

    /** POST /admin/translations/add-locale - AJAX; scaffolds from en */
    public function addLocale(): void
    {
        $this->requirePermission('translations.manage');
        $this->validateCsrf();

        $code = strtolower(trim($this->input->post('code', false) ?? ''));
        if (!preg_match('/^[a-z]{2}(-[a-z0-9]{2,8})?$/', $code)) {
            $this->json(['success' => false, 'message' => 'Locale code must look like "fr" or "pt-br".']);
        }
        if (in_array($code, I18n::getSupportedLocales(), true)) {
            $this->json(['success' => false, 'message' => "Locale \"{$code}\" already exists."]);
        }

        $dir = $this->langDir() . $code;
        if (!@mkdir($dir, 0755, true)) {
            $this->json(['success' => false, 'message' => 'Could not create the locale directory.']);
        }

        // Scaffold every group with empty values (keys copied from en)
        foreach ($this->groups() as $group) {
            $empty = array_fill_keys(array_keys($this->loadGroup('en', $group)), '');
            $this->writeGroup($code, $group, $empty);
        }

        Auth::audit('translations.locale_added', 'lang', '', ['locale' => $code]);
        $this->json(['success' => true, 'message' => "Locale \"{$code}\" created. Fill in its translations below.", 'locale' => $code]);
    }

    /** Atomic, validated write of a language group file. */
    private function writeGroup(string $locale, string $group, array $data): bool
    {
        $dir = $this->langDir() . $locale;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }

        ksort($data);
        $php = "<?php\n\ndeclare(strict_types=1);\n\n// Generated by Admin > Translations - edit through the UI when possible.\nreturn " . var_export($data, true) . ";\n";

        $file = $dir . DS . $group . '.php';
        $tmp  = $file . '.tmp';

        if (@file_put_contents($tmp, $php, LOCK_EX) === false) {
            return false;
        }

        // Validate the file round-trips before replacing the live one
        $check = @include $tmp;
        if (!is_array($check)) {
            @unlink($tmp);
            return false;
        }

        return @rename($tmp, $file);
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }
}
