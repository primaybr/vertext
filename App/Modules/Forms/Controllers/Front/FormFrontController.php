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
        $form = $this->loadForm($slug);

        $flash = $this->session->flash('form_flash_' . $slug) ?: [];

        ThemeEngine::render('modules/forms/front/form', [
            'form'       => $form,
            'fields'     => json_decode($form['fields'] ?: '[]', true) ?: [],
            'flash'      => is_array($flash) ? $flash : [],
            'baseUrl'    => $this->baseUrl,
            'csrf_token' => $this->csrf->getToken(),
            'page_title' => $form['name'],
        ]);
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

        $fields = json_decode($form['fields'] ?: '[]', true) ?: [];
        $data   = [];
        $errors = [];

        foreach ($fields as $field) {
            $fid  = $field['id'];
            $type = $field['type'] ?? 'text';

            if ($type === 'checkbox') {
                $raw = $this->input->post($fid . '[]', false) ?? [];
                $val = is_array($raw) ? array_map('strval', $raw) : [];
            } else {
                $raw = $this->input->post($fid, false) ?? '';
                $val = substr(trim((string) $raw), 0, 5000);
            }

            if ($field['required'] && ($val === '' || $val === [])) {
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
