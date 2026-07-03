<?php

declare(strict_types=1);

namespace App\Modules\Forms\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use Core\Utilities\Text\Str;

/**
 * Forms CRUD and field builder.
 *
 * GET  /admin/forms                  → index()
 * GET  /admin/forms/create           → createForm()
 * POST /admin/forms/store            → store()
 * GET  /admin/forms/{id}/edit        → editForm($id)
 * POST /admin/forms/{id}/update      → update($id)
 * POST /admin/forms/{id}/delete      → delete($id)
 * GET  /admin/forms/{id}/builder     → builder($id)
 * POST /admin/forms/{id}/save-fields → saveFields($id)
 */
class FormsController extends BaseController
{
    protected string $module = 'forms';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('forms.view');

        $search  = trim($this->input->get('search') ?? '');
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $q  = $this->db('form_definitions')
            ->select('id, name, slug, status, created_at, updated_at')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->limitOffset($perPage, $offset);
        $qc = $this->db('form_definitions')->whereNull('deleted_at');

        if ($search) {
            $q->whereRaw('name ILIKE :s', [':s' => "%{$search}%"]);
            $qc->whereRaw('name ILIKE :s', [':s' => "%{$search}%"]);
        }

        $total = (int) ($qc->totalRows() ?: 0);
        $forms = $q->get() ?: [];

        foreach ($forms as &$form) {
            $form['submission_count'] = (int) ($this->db('form_submissions')
                ->where('form_id', $form['id'])
                ->whereNull('deleted_at')
                ->totalRows() ?: 0);
            $form['unread_count'] = (int) ($this->db('form_submissions')
                ->where('form_id', $form['id'])
                ->where('status', 'unread')
                ->whereNull('deleted_at')
                ->totalRows() ?: 0);
        }
        unset($form);

        $this->adminRender('modules/forms/admin/index', [
            'forms'   => $forms,
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int) ceil($total / $perPage)),
            'search'  => $search,
        ], 'Forms', 'forms');
    }

    public function createForm(): void
    {
        $this->requirePermission('forms.manage');
        $this->renderPartial('modules/forms/admin/_form_meta', [
            'form'   => null,
            'action' => $this->baseUrl . '/admin/forms/store',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('forms.manage');
        $this->validateCsrf();

        $name = trim($this->input->post('name', false) ?? '');
        if (!$name) {
            $this->json(['success' => false, 'message' => 'Form name is required.']);
        }

        $rawSlug = trim($this->input->post('slug', false) ?? '');
        $slug    = $rawSlug ? Str::slug($rawSlug) : Str::slug($name);
        $slug    = $this->uniqueSlug($slug);

        $desc = trim($this->input->post('description', false) ?? '');

        $id = (string) $this->db('form_definitions')->save([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc,
            'fields'      => '[]',
            'settings'    => '{}',
            'status'      => 'active',
            'created_by'  => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('form.create', 'form_definitions', $id, ['name' => $name]);
        $this->json(['success' => true, 'message' => "Form \"{$name}\" created.", 'redirect' => $this->baseUrl . "/admin/forms/{$id}/builder"]);
    }

    public function editForm(string $id): void
    {
        $this->requirePermission('forms.manage');
        $form = $this->db('form_definitions')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$form) {
            $this->json(['success' => false, 'message' => 'Form not found.'], 404);
        }

        $this->renderPartial('modules/forms/admin/_form_meta', [
            'form'   => $form,
            'action' => $this->baseUrl . "/admin/forms/{$id}/update",
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('forms.manage');
        $this->validateCsrf();

        $form = $this->db('form_definitions')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$form) {
            $this->json(['success' => false, 'message' => 'Form not found.'], 404);
        }

        $name    = trim($this->input->post('name', false) ?? '');
        $desc    = trim($this->input->post('description', false) ?? '');
        $status  = in_array($this->input->post('status'), ['active', 'inactive'], true)
            ? $this->input->post('status')
            : 'active';

        if (!$name) {
            $this->json(['success' => false, 'message' => 'Form name is required.']);
        }

        $rawSlug = trim($this->input->post('slug', false) ?? '');
        $newSlug = $rawSlug ? Str::slug($rawSlug) : null;
        if ($newSlug && $newSlug !== $form['slug']) {
            $newSlug = $this->uniqueSlug($newSlug, $id);
        }

        $data = [
            'name'        => $name,
            'description' => $desc,
            'status'      => $status,
            'updated_at'  => date('Y-m-d H:i:s'),
            'updated_by'  => $this->currentUser['id'] ?? null,
        ];
        if ($newSlug) {
            $data['slug'] = $newSlug;
        }

        $this->db('form_definitions')->where('id', $id)->update($data);
        Auth::audit('form.update', 'form_definitions', $id, ['name' => $name]);
        $this->json(['success' => true, 'message' => 'Form updated.']);
    }

    public function delete(string $id): void
    {
        $this->requirePermission('forms.manage');
        $this->validateCsrf();

        $form = $this->db('form_definitions')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$form) {
            $this->json(['success' => false, 'message' => 'Form not found.'], 404);
        }

        $this->db('form_definitions')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('form.delete', 'form_definitions', $id, ['name' => $form['name']]);
        $this->json(['success' => true, 'message' => "Form \"{$form['name']}\" deleted."]);
    }

    public function builder(string $id): void
    {
        $this->requirePermission('forms.manage');

        $form = $this->db('form_definitions')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$form) {
            $this->flash('error', 'Form not found.');
            $this->redirect($this->baseUrl . '/admin/forms');
        }

        $form['fields'] = json_decode($form['fields'] ?: '[]', true) ?: [];

        $this->adminRender('modules/forms/admin/builder', [
            'form'   => $form,
            'formId' => $id,
        ], 'Form Builder - ' . $form['name'], 'forms');
    }

    public function saveFields(string $id): void
    {
        $this->requirePermission('forms.manage');
        $this->validateCsrf();

        $form = $this->db('form_definitions')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$form) {
            $this->json(['success' => false, 'message' => 'Form not found.'], 404);
        }

        $rawFields = $this->input->post('fields', false) ?? '[]';
        $fields    = json_decode($rawFields, true);
        if (!is_array($fields)) {
            $this->json(['success' => false, 'message' => 'Invalid fields data.']);
        }

        $fields = $this->sanitizeFields($fields);

        // Form settings travel with the same save (was previously collected in
        // the builder UI but never persisted).
        $rawSettings = $this->input->post('settings', false) ?? '{}';
        $settings    = json_decode($rawSettings, true);
        $settings    = is_array($settings) ? $this->sanitizeSettings($settings) : [];

        $this->db('form_definitions')->where('id', $id)->update([
            'fields'     => json_encode($fields),
            'settings'   => json_encode($settings),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('form.fields_saved', 'form_definitions', $id, ['field_count' => count($fields)]);
        $this->json(['success' => true, 'message' => 'Fields saved.']);
    }

    private function sanitizeFields(array $fields): array
    {
        // 'step' is a page-break marker for multi-step forms, not an input
        $allowed_types = ['text', 'email', 'textarea', 'select', 'checkbox', 'radio', 'number', 'date', 'file', 'step'];
        $clean = [];
        foreach ($fields as $f) {
            if (!is_array($f)) continue;
            $type = in_array($f['type'] ?? '', $allowed_types, true) ? $f['type'] : 'text';
            $clean[] = [
                'id'          => substr(preg_replace('/[^a-z0-9_]/', '', strtolower($f['id'] ?? '')), 0, 64) ?: ('field_' . count($clean)),
                'type'        => $type,
                'label'       => substr(trim($f['label'] ?? ''), 0, 200) ?: 'Field',
                'placeholder' => substr(trim($f['placeholder'] ?? ''), 0, 200),
                'required'    => (bool) ($f['required'] ?? false),
                'options'     => in_array($type, ['select', 'radio', 'checkbox'])
                    ? array_map(fn($o) => substr(trim((string) $o), 0, 200), array_values(array_filter((array) ($f['options'] ?? []), 'is_string')))
                    : [],
                'width'       => in_array($f['width'] ?? '', ['full', 'half'], true) ? $f['width'] : 'full',
                'conditions'  => $this->sanitizeConditions((array) ($f['conditions'] ?? [])),
            ];
        }
        return $clean;
    }

    /** Conditional visibility rules: [{field, operator, value, action}] */
    private function sanitizeConditions(array $conditions): array
    {
        $operators = ['equals', 'not_equals', 'contains', 'empty', 'not_empty'];
        $actions   = ['show', 'hide'];
        $clean     = [];
        foreach ($conditions as $c) {
            if (!is_array($c)) continue;
            $target = substr(preg_replace('/[^a-z0-9_]/', '', strtolower($c['field'] ?? '')), 0, 64);
            if ($target === '') continue;
            $clean[] = [
                'field'    => $target,
                'operator' => in_array($c['operator'] ?? '', $operators, true) ? $c['operator'] : 'equals',
                'value'    => substr(trim((string) ($c['value'] ?? '')), 0, 200),
                'action'   => in_array($c['action'] ?? '', $actions, true) ? $c['action'] : 'show',
            ];
            if (count($clean) >= 5) break; // sane cap per field
        }
        return $clean;
    }

    /** Whitelist + normalize the settings JSON persisted by saveFields() */
    private function sanitizeSettings(array $settings): array
    {
        $email = trim((string) ($settings['notification_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }
        return [
            'success_message'      => substr(trim((string) ($settings['success_message'] ?? '')), 0, 500),
            'notification_email'   => substr($email, 0, 180),
            'math_challenge'       => !empty($settings['math_challenge']),
            'recaptcha_site_key'   => substr(trim((string) ($settings['recaptcha_site_key'] ?? '')), 0, 100),
            'recaptcha_secret_key' => substr(trim((string) ($settings['recaptcha_secret_key'] ?? '')), 0, 100),
        ];
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }

    private function uniqueSlug(string $base, string $excludeId = ''): string
    {
        $slug   = $base;
        $suffix = 2;
        while (true) {
            $q = $this->db('form_definitions')->select('id')->where('slug', $slug)->whereNull('deleted_at');
            if ($excludeId) {
                $q->whereRaw('id != ?', [$excludeId]);
            }
            if (!$q->get(1)) break;
            $slug = $base . '-' . $suffix++;
        }
        return $slug;
    }
}
