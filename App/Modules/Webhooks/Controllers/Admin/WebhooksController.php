<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use App\Modules\Webhooks\WebhookDispatcher;

class WebhooksController extends BaseController
{
    protected string $module = 'webhooks';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('webhooks.view');

        $endpoints = $this->db('webhook_endpoints')
            ->select('id, name, url, events, enabled, created_at')
            ->orderBy('created_at', 'DESC')
            ->get() ?: [];

        // Attach last delivery stats per endpoint
        foreach ($endpoints as &$ep) {
            $ep['events_arr'] = json_decode($ep['events'] ?? '[]', true) ?: [];
            try {
                $last = $this->db('webhook_logs')
                    ->select('success, created_at')
                    ->where('endpoint_id', $ep['id'])
                    ->orderBy('created_at', 'DESC')
                    ->get(1);
                $ep['last_delivery']         = $last['created_at'] ?? null;
                $ep['last_delivery_success']  = isset($last['success']) ? (bool) $last['success'] : null;
            } catch (\Throwable) {
                $ep['last_delivery']          = null;
                $ep['last_delivery_success']  = null;
            }
        }
        unset($ep);

        $this->adminRender('modules/webhooks/admin/webhooks/index', [
            'endpoints'      => $endpoints,
            'availableEvents' => WebhookDispatcher::EVENTS,
        ], 'Webhooks', 'webhooks');
    }

    public function create(): void
    {
        $this->requirePermission('webhooks.manage');

        $vars = [
            'endpoint'        => null,
            'action'          => '{{baseUrl}}/admin/webhooks/store',
            'availableEvents' => WebhookDispatcher::EVENTS,
            'generatedSecret' => bin2hex(random_bytes(20)),
            'isModal'         => $this->input->isAjax(),
        ];
        if ($this->input->isAjax()) {
            $this->renderPartial('modules/webhooks/admin/webhooks/form', $vars);
            return;
        }
        $this->adminRender('modules/webhooks/admin/webhooks/form', $vars, 'New Webhook', 'webhooks');
    }

    public function store(): void
    {
        $this->requirePermission('webhooks.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            if ($this->input->isAjax()) {
                $this->json(['success' => false, 'message' => 'Invalid security token. Please try again.'], 403);
                return;
            }
            $this->flash('error', 'Invalid security token. Please try again.');
            $this->redirect('/admin/webhooks/create');
            return;
        }

        $errors = $this->validateInput();
        if ($errors) {
            if ($this->input->isAjax()) {
                $this->json(['success' => false, 'message' => implode(' ', $errors)]);
                return;
            }
            $this->flash('error', implode(' ', $errors));
            $this->redirect('/admin/webhooks/create');
            return;
        }

        $id = (string) $this->db('webhook_endpoints')->save([
            'name'    => trim($this->input->post('name') ?? ''),
            'url'     => trim($this->input->post('url') ?? ''),
            'secret'  => trim($this->input->post('secret') ?? ''),
            'events'  => json_encode(array_values(array_filter((array)($this->input->post('events', false) ?? [])))),
            'enabled' => (bool)$this->input->post('enabled'),
        ]);

        Auth::audit('webhook.create', 'webhook_endpoints', $id);

        if ($this->input->isAjax()) {
            $this->json(['success' => true, 'message' => 'Webhook endpoint created.']);
            return;
        }
        $this->flash('success', 'Webhook endpoint created.');
        $this->redirect('/admin/webhooks');
    }

    public function edit(string $id): void
    {
        $this->requirePermission('webhooks.manage');

        $endpoint = $this->db('webhook_endpoints')->where('id', $id)->get(1);
        if (!$endpoint) {
            if ($this->input->isAjax()) {
                $this->json(['success' => false, 'message' => 'Endpoint not found.'], 404);
                return;
            }
            $this->flash('error', 'Endpoint not found.');
            $this->redirect('/admin/webhooks');
            return;
        }

        $vars = [
            'endpoint'        => $endpoint,
            'action'          => '{{baseUrl}}/admin/webhooks/' . $id . '/update',
            'availableEvents' => WebhookDispatcher::EVENTS,
            'generatedSecret' => null,
            'isModal'         => $this->input->isAjax(),
        ];
        if ($this->input->isAjax()) {
            $this->renderPartial('modules/webhooks/admin/webhooks/form', $vars);
            return;
        }
        $this->adminRender('modules/webhooks/admin/webhooks/form', $vars, 'Edit Webhook', 'webhooks');
    }

    public function update(string $id): void
    {
        $this->requirePermission('webhooks.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            if ($this->input->isAjax()) {
                $this->json(['success' => false, 'message' => 'Invalid security token. Please try again.'], 403);
                return;
            }
            $this->flash('error', 'Invalid security token. Please try again.');
            $this->redirect('/admin/webhooks/' . $id . '/edit');
            return;
        }

        $endpoint = $this->db('webhook_endpoints')->where('id', $id)->get(1);
        if (!$endpoint) {
            if ($this->input->isAjax()) {
                $this->json(['success' => false, 'message' => 'Endpoint not found.'], 404);
                return;
            }
            $this->flash('error', 'Endpoint not found.');
            $this->redirect('/admin/webhooks');
            return;
        }

        $errors = $this->validateInput();
        if ($errors) {
            if ($this->input->isAjax()) {
                $this->json(['success' => false, 'message' => implode(' ', $errors)]);
                return;
            }
            $this->flash('error', implode(' ', $errors));
            $this->redirect('/admin/webhooks/' . $id . '/edit');
            return;
        }

        $this->db('webhook_endpoints')->where('id', $id)->update([
            'name'    => trim($this->input->post('name') ?? ''),
            'url'     => trim($this->input->post('url') ?? ''),
            'secret'  => trim($this->input->post('secret') ?? ''),
            'events'  => json_encode(array_values(array_filter((array)($this->input->post('events', false) ?? [])))),
            'enabled' => (bool)$this->input->post('enabled') ? 'true' : 'false',
        ]);

        Auth::audit('webhook.update', 'webhook_endpoints', $id);

        if ($this->input->isAjax()) {
            $this->json(['success' => true, 'message' => 'Webhook endpoint updated.']);
            return;
        }
        $this->flash('success', 'Webhook endpoint updated.');
        $this->redirect('/admin/webhooks');
    }

    public function delete(string $id): void
    {
        $this->requirePermission('webhooks.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
            return;
        }

        $this->db('webhook_endpoints')->where('id', $id)->delete();

        Auth::audit('webhook.delete', 'webhook_endpoints', $id);
        $this->json(['success' => true, 'message' => 'Endpoint deleted.']);
    }

    public function logs(string $id): void
    {
        $this->requirePermission('webhooks.view');

        $endpoint = $this->db('webhook_endpoints')->where('id', $id)->get(1);
        if (!$endpoint) {
            $this->flash('error', 'Endpoint not found.');
            $this->redirect('/admin/webhooks');
            return;
        }

        $logs = $this->db('webhook_logs')
            ->select('id, event, response_code, response_body, duration_ms, success, created_at')
            ->where('endpoint_id', $id)
            ->orderBy('created_at', 'DESC')
            ->limitOffset(50, 0)
            ->get() ?: [];

        $this->adminRender('modules/webhooks/admin/webhooks/logs', [
            'endpoint' => $endpoint,
            'logs'     => $logs,
        ], 'Webhook Logs', 'webhooks');
    }

    public function test(string $id): void
    {
        $this->requirePermission('webhooks.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
            return;
        }

        $result = WebhookDispatcher::dispatchToEndpoint($id, 'ping', [
            'test'      => true,
            'message'   => 'Test delivery from Vertext Webhooks',
            'timestamp' => date('c'),
        ]);

        if ($result['success']) {
            $this->json(['success' => true, 'message' => 'Test delivered successfully (HTTP ' . $result['response_code'] . ').']);
        } else {
            $code = $result['response_code'] ?? 0;
            $msg  = $code > 0 ? "Endpoint returned HTTP {$code}." : 'Delivery failed - check logs for details.';
            $this->json(['success' => false, 'message' => $msg]);
        }
    }

    private function validateInput(): array
    {
        $errors = [];
        $name   = trim($this->input->post('name') ?? '');
        $url    = trim($this->input->post('url')  ?? '');
        $secret = trim($this->input->post('secret') ?? '');
        $events = (array)($this->input->post('events', false) ?? []);

        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if ($url === '') {
            $errors[] = 'URL is required.';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $errors[] = 'URL must be a valid http or https URL.';
        }
        if ($secret === '') {
            $errors[] = 'Secret is required.';
        }
        if (empty($events)) {
            $errors[] = 'Select at least one event.';
        }

        return $errors;
    }
}
