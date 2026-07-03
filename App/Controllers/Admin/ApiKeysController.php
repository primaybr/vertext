<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\CMS\Auth;
use App\Controllers\Api\V1\ApiController;

/**
 * Admin CRUD for REST API keys.
 *
 * GET  /admin/api-keys              → index()
 * POST /admin/api-keys/store        → store()   (returns the plaintext key ONCE)
 * POST /admin/api-keys/{id}/revoke  → revoke()
 * POST /admin/api-keys/{id}/delete  → delete()
 */
class ApiKeysController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        ApiController::ensureTables();
        $this->ensurePermission();
    }

    /** Seed the api.manage permission on first use (idempotent). */
    private function ensurePermission(): void
    {
        try {
            $db = $this->db('permissions')->db;
            $db->query("INSERT INTO permissions (name, slug, description, module)
                        VALUES ('API - Manage Keys', 'api.manage', 'Create and revoke REST API keys', 'core')
                        ON CONFLICT (slug) DO NOTHING");
            $db->execute();
            $db->query("INSERT INTO role_permissions (role_id, permission_id)
                        SELECT r.id, p.id FROM roles r, permissions p
                        WHERE r.slug = 'administrator' AND p.slug = 'api.manage'
                        ON CONFLICT DO NOTHING");
            $db->execute();
        } catch (\Throwable) {
        }
    }

    public function index(): void
    {
        $this->requirePermission('api.manage');

        $keys = $this->db('api_keys')
            ->select('id, name, last_used_at, revoked_at, created_at')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->get() ?: [];

        $this->adminRender('admin/api_keys/index', [
            'keys'    => $keys,
            'siteUrl' => $this->siteUrl(),
        ], 'API Keys', 'api-keys');
    }

    /** POST /admin/api-keys/store - AJAX; plaintext key is shown exactly once */
    public function store(): void
    {
        $this->requirePermission('api.manage');
        $this->validateCsrf();

        $name = trim($this->input->post('name', false) ?? '');
        if ($name === '' || mb_strlen($name) > 150) {
            $this->json(['success' => false, 'message' => 'A key name (max 150 characters) is required.']);
        }

        $plaintext = 'vtx_' . bin2hex(random_bytes(24));

        $id = (string) $this->db('api_keys')->save([
            'name'       => $name,
            'key_hash'   => hash('sha256', $plaintext),
            'user_id'    => $this->currentUser['id'] ?? null,
            'created_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('api.key_created', 'api_keys', $id, ['name' => $name]);

        $this->json([
            'success' => true,
            'message' => 'API key created. Copy it now - it will not be shown again.',
            'key'     => $plaintext,
            'id'      => $id,
            'name'    => $name,
        ]);
    }

    /** POST /admin/api-keys/{id}/revoke - AJAX */
    public function revoke(string $id): void
    {
        $this->requirePermission('api.manage');
        $this->validateCsrf();

        $key = $this->db('api_keys')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$key) {
            $this->json(['success' => false, 'message' => 'Key not found.'], 404);
        }
        if (!empty($key['revoked_at'])) {
            $this->json(['success' => false, 'message' => 'Key is already revoked.']);
        }

        $this->db('api_keys')->where('id', $id)->update([
            'revoked_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('api.key_revoked', 'api_keys', $id, ['name' => $key['name']]);
        $this->json(['success' => true, 'message' => "Key \"{$key['name']}\" revoked."]);
    }

    /** POST /admin/api-keys/{id}/delete - AJAX */
    public function delete(string $id): void
    {
        $this->requirePermission('api.manage');
        $this->validateCsrf();

        $key = $this->db('api_keys')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$key) {
            $this->json(['success' => false, 'message' => 'Key not found.'], 404);
        }

        $this->db('api_keys')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('api.key_deleted', 'api_keys', $id, ['name' => $key['name']]);
        $this->json(['success' => true, 'message' => "Key \"{$key['name']}\" deleted."]);
    }

    private function siteUrl(): string
    {
        try {
            $row = $this->db('settings')->select('value')->where('key', 'site_url')->get(1);
            $url = trim((string) ($row['value'] ?? ''));
            if ($url !== '') return rtrim($url, '/');
        } catch (\Throwable) {
        }
        return $this->baseUrl;
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }
}
