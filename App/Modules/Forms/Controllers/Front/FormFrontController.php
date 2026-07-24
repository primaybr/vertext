<?php

declare(strict_types=1);

namespace App\Modules\Forms\Controllers\Front;

use Core\Controller;
use App\Theme\ThemeEngine;

/**
 * Public-facing form renderer and submission handler.
 *
 * GET  /forms/{slug}        → show($slug)
 * POST /forms/{slug}/submit → submit($slug)
 */
class FormFrontController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function show(string $slug): void
    {
        \App\CMS\PageCache::serve();

        $form = $this->loadForm($slug);

        $rawFlash = $this->session->flash('form_flash_' . $slug);
        $flash    = $rawFlash ?: [];

        // Pre-fill for logged-in site members
        $member = null;
        if (\App\CMS\ModuleLoader::isEnabled('members') && \App\CMS\SiteAuth::check()) {
            $member = \App\CMS\SiteAuth::user();
        }

        // Math anti-spam challenge (per-form setting)
        $settings     = json_decode($form['settings'] ?: '{}', true) ?: [];
        $mathQuestion = null;
        if (!empty($settings['math_challenge'])) {
            $a = random_int(2, 9);
            $b = random_int(2, 9);
            $this->session->set('form_math_' . $form['id'], (string) ($a + $b));
            $mathQuestion = "{$a} + {$b}";
        }

        $vars = [
            'form'         => $form,
            'fields'       => json_decode($form['fields'] ?: '[]', true) ?: [],
            'flash'        => is_array($flash) ? $flash : [],
            'baseUrl'      => $this->baseUrl,
            'csrf_token'   => $this->csrf->getToken(),
            'page_title'   => $form['name'],
            'member'       => $member,
            'mathQuestion' => $mathQuestion,
        ];

        // A just-consumed success flash hides the form (and its CSRF token) in
        // favor of a one-time "thank you" message - capture()'s CSRF-based guard
        // can't see that, so skip caching this render outright.
        if ($rawFlash) {
            ThemeEngine::render('modules/forms/front/form', $vars);
            return;
        }

        \App\CMS\PageCache::capture(static function () use ($vars) {
            ThemeEngine::render('modules/forms/front/form', $vars);
        });
    }

    public function submit(string $slug): void
    {
        $form = $this->loadForm($slug);

        // CSRF check
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->setFlash($slug, 'error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/forms/' . $slug);
        }

        // Honeypot spam trap (hidden field must remain empty)
        if (($this->input->post('website', false) ?? '') !== '') {
            $this->setFlash($slug, 'success', 'Thank you! Your response has been submitted.');
            $this->redirect($this->baseUrl . '/forms/' . $slug);
        }

        // Rate limit: 1 submission per IP per 5 minutes per form
        $ip    = $this->getIp();
        $ipHash = hash('sha256', $ip . $form['id']);
        $since = date('Y-m-d H:i:s', time() - 300);
        $recent = (new \Core\Model('form_submissions'))
            ->where('form_id', $form['id'])
            ->where('ip_hash', $ipHash)
            ->whereRaw('submitted_at > :since', [':since' => $since])
            ->whereNull('deleted_at')
            ->get(1);

        if ($recent) {
            $this->setFlash($slug, 'error', 'Please wait a few minutes before submitting again.');
            $this->redirect($this->baseUrl . '/forms/' . $slug);
        }

        $settings = json_decode($form['settings'] ?: '{}', true) ?: [];

        // Math challenge (when enabled the answer was stored at render time)
        if (!empty($settings['math_challenge'])) {
            $expected = (string) ($this->session->get('form_math_' . $form['id']) ?? '');
            $answer   = trim((string) ($this->input->post('math_answer', false) ?? ''));
            $this->session->set('form_math_' . $form['id'], null);
            if ($expected === '' || $answer !== $expected) {
                $this->setFlash($slug, 'error', 'The spam-check answer was incorrect. Please try again.');
                $this->redirect($this->baseUrl . '/forms/' . $slug);
            }
        }

        // reCAPTCHA v3 (only when both keys configured)
        if (!empty($settings['recaptcha_site_key']) && !empty($settings['recaptcha_secret_key'])) {
            $rcToken = (string) ($this->input->post('recaptcha_token', false) ?? '');
            if (!$this->verifyRecaptcha($settings['recaptcha_secret_key'], $rcToken)) {
                $this->setFlash($slug, 'error', 'Spam verification failed. Please try again.');
                $this->redirect($this->baseUrl . '/forms/' . $slug);
            }
        }

        $fields = json_decode($form['fields'] ?: '[]', true) ?: [];

        // Pass 1: collect raw values (needed to evaluate visibility conditions)
        $rawValues = [];
        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'step') continue;
            $fid  = $field['id'];
            $type = $field['type'] ?? 'text';
            if ($type === 'checkbox') {
                $raw = $this->input->post($fid . '[]', false) ?? [];
                $rawValues[$fid] = is_array($raw) ? array_map('strval', $raw) : [];
            } elseif ($type === 'file') {
                $rawValues[$fid] = ''; // handled separately below
            } else {
                $raw = $this->input->post($fid, false) ?? '';
                $rawValues[$fid] = substr(trim((string) $raw), 0, 5000);
            }
        }

        // Pass 2: validate only VISIBLE fields (mirrors the client-side logic -
        // a required field hidden by conditions must not fail validation)
        $data   = [];
        $errors = [];
        foreach ($fields as $field) {
            $type = $field['type'] ?? 'text';
            if ($type === 'step') continue;

            $fid = $field['id'];
            if (!$this->fieldVisible($field, $rawValues)) {
                continue; // hidden by conditional logic - skip entirely
            }

            if ($type === 'file') {
                $result = $this->handleFileField($field, $form);
                if ($result['error'] !== null) {
                    $errors[] = $result['error'];
                    continue;
                }
                if ($result['path'] !== null) {
                    $data[$fid] = $result['path'];
                } elseif (!empty($field['required'])) {
                    $errors[] = ($field['label'] ?? $fid) . ' is required.';
                }
                continue;
            }

            $val = $rawValues[$fid];

            if (!empty($field['required']) && ($val === '' || $val === [])) {
                $errors[] = ($field['label'] ?? $fid) . ' is required.';
                continue;
            }

            if ($type === 'email' && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ($field['label'] ?? $fid) . ' must be a valid email address.';
                continue;
            }

            $data[$fid] = $val;
        }

        if ($errors) {
            $this->setFlash($slug, 'error', implode(' ', $errors));
            $this->redirect($this->baseUrl . '/forms/' . $slug);
        }

        // Save submission
        (new \Core\Model('form_submissions'))->save([
            'form_id'      => $form['id'],
            'data'         => json_encode($data),
            'ip_hash'      => $ipHash,
            'status'       => 'unread',
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);

        // Per-form email notification
        if (!empty($settings['notification_email'])) {
            $this->sendNotification($form, $settings['notification_email'], $data, $fields);
        }

        // Fire webhook if module enabled
        if (\App\CMS\ModuleLoader::isEnabled('webhooks')) {
            try {
                \App\Modules\Webhooks\WebhookDispatcher::dispatch('form.submitted', [
                    'form_id'      => $form['id'],
                    'form_slug'    => $form['slug'],
                    'form_name'    => $form['name'],
                    'submitted_at' => date('c'),
                    'data'         => $data,
                ]);
            } catch (\Throwable) {}
        }

        $successMsg = json_decode($form['settings'] ?: '{}', true)['success_message'] ?? 'Thank you! Your response has been submitted.';
        $this->setFlash($slug, 'success', $successMsg);
        $this->redirect($this->baseUrl . '/forms/' . $slug);
    }

    private function loadForm(string $slug): array
    {
        $form = (new \Core\Model('form_definitions'))
            ->where('slug', $slug)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get(1);

        if (!$form) {
            http_response_code(404);
            ThemeEngine::render('errors/404', ['page_title' => 'Not Found']);
            exit;
        }

        return $form;
    }

    private function setFlash(string $slug, string $type, string $message): void
    {
        $this->session->set('form_flash_' . $slug, ['type' => $type, 'message' => $message]);
    }

    /**
     * Server-side mirror of the client conditional-logic evaluation.
     * A field with no conditions is always visible.
     */
    private function fieldVisible(array $field, array $rawValues): bool
    {
        $conditions = $field['conditions'] ?? [];
        if (empty($conditions) || !is_array($conditions)) {
            return true;
        }

        $rule   = $conditions[0];
        $target = $rawValues[$rule['field'] ?? ''] ?? '';
        $value  = is_array($target) ? strtolower(implode(',', $target)) : strtolower((string) $target);
        $expect = strtolower((string) ($rule['value'] ?? ''));

        $matched = match ($rule['operator'] ?? 'equals') {
            'equals'     => $value === $expect,
            'not_equals' => $value !== $expect,
            'contains'   => $expect !== '' && str_contains($value, $expect),
            'empty'      => $value === '',
            'not_empty'  => $value !== '',
            default      => false,
        };

        return ($rule['action'] ?? 'show') === 'hide' ? !$matched : $matched;
    }

    /**
     * Persist an uploaded file field to Public/uploads/forms/{form_id}/.
     * Returns ['path' => string|null, 'error' => string|null]; path is null
     * when no file was uploaded (caller decides whether that violates required).
     */
    private function handleFileField(array $field, array $form): array
    {
        $fid   = $field['id'];
        $label = $field['label'] ?? $fid;
        $file  = $_FILES[$fid] ?? null;

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => "{$label}: upload failed, please try again."];
        }
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            return ['path' => null, 'error' => "{$label}: file must be 10 MB or smaller."];
        }

        $allowed = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            return ['path' => null, 'error' => "{$label}: file type .{$ext} is not allowed."];
        }

        $mime = mime_content_type((string) $file['tmp_name']) ?: '';
        // text/plain shows up for txt; office docs sometimes report zip-family MIMEs
        $mimeOk = ($mime === $allowed[$ext])
            || ($ext === 'docx' && in_array($mime, ['application/zip', $allowed['docx']], true))
            || ($ext === 'txt' && str_starts_with($mime, 'text/'));
        if (!$mimeOk) {
            return ['path' => null, 'error' => "{$label}: file content does not match its extension."];
        }

        $dir = ROOT . 'Public' . DS . 'uploads' . DS . 'forms' . DS . $form['id'] . DS;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['path' => null, 'error' => "{$label}: could not store the file."];
        }

        $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . $stored)) {
            return ['path' => null, 'error' => "{$label}: could not store the file."];
        }

        return ['path' => 'uploads/forms/' . $form['id'] . '/' . $stored, 'error' => null];
    }

    /** Verify a reCAPTCHA v3 token server-side. Fails closed on low scores. */
    private function verifyRecaptcha(string $secret, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $this->getIp(),
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
            ]);
            $body = curl_exec($ch);

            if (!is_string($body)) {
                return false;
            }
            $result = json_decode($body, true);
            return !empty($result['success']) && (float) ($result['score'] ?? 0) >= 0.5;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Email the submission to the per-form notification address. */
    private function sendNotification(array $form, string $to, array $data, array $fields): void
    {
        try {
            $labels = [];
            foreach ($fields as $f) {
                if (($f['type'] ?? '') !== 'step') {
                    $labels[$f['id']] = $f['label'] ?? $f['id'];
                }
            }

            $settings = array_column((new \Core\Model('settings'))->get() ?: [], 'value', 'key');

            $html = \App\Mail\MailTemplate::render('form_notification', [
                'formName' => (string) $form['name'],
                'labels'   => $labels,
                'data'     => $data,
                'siteName' => $settings['site_name'] ?? 'Vertext CMS',
                'inboxUrl' => rtrim($settings['site_url'] ?? $this->baseUrl, '/') . '/admin/forms/' . $form['id'] . '/submissions',
            ]);

            \App\Mail\Mailer::make()->send(
                (new \App\Mail\MailMessage())
                    ->to($to)
                    ->subject('New submission: ' . $form['name'])
                    ->htmlBody($html)
            );
        } catch (\Throwable $e) {
            // Notification failure must never lose the submission
        }
    }

    private function getIp(): string
    {
        return substr(
            $_SERVER['HTTP_CF_CONNECTING_IP']
                ?? $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['REMOTE_ADDR']
                ?? '',
            0,
            45
        );
    }
}
