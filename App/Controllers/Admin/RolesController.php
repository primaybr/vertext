<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\CMS\Auth;

/**
 * Admin Roles & Permissions Controller
 */
class RolesController extends BaseController
{
    protected string $module = 'roles';

    public function __construct()
    {
        parent::__construct();
    }

    /** GET /admin/roles */
    public function index(): void
    {
        $this->requirePermission('roles.view');

        $roles = $this->db('roles')
            ->select('roles.*, COUNT(role_permissions.permission_id) AS perm_count')
            ->join('role_permissions', 'role_permissions.role_id = roles.id', 'LEFT')
            ->groupBy('roles.id')
            ->orderBy('roles.is_system', 'DESC')
            ->orderBy('roles.name', 'ASC')
            ->get() ?: [];

        $this->adminRender('admin/roles/index', ['roles' => $roles], 'Roles & Permissions', 'roles');
    }

    /** GET /admin/roles/form - AJAX: returns create form partial for modal */
    public function createForm(): void
    {
        $this->requirePermission('roles.manage');
        $perms = $this->groupedPermissions();

        $this->renderPartial('admin/roles/_form', [
            'role'      => null,
            'perms'     => $perms,
            'rolePerms' => [],
            'action'    => $this->baseUrl . '/admin/roles/store',
        ]);
    }

    /** GET /admin/roles/([a-zA-Z0-9\-]+)/form - AJAX: returns edit form partial for modal */
    public function editForm(string $id): void
    {
        $this->requirePermission('roles.manage');
        $role = $this->db('roles')->where('id', $id)->get(1);
        if (!$role) { $this->json(['success' => false, 'message' => 'Role not found.'], 404); }

        $rpRows    = $this->db('role_permissions')->where('role_id', $id)->get() ?: [];
        $rolePerms = array_column($rpRows, 'permission_id');
        $perms     = $this->groupedPermissions();

        $this->renderPartial('admin/roles/_form', [
            'role'      => $role,
            'perms'     => $perms,
            'rolePerms' => $rolePerms,
            'action'    => $this->baseUrl . "/admin/roles/{$id}/update",
        ]);
    }

    /** POST /admin/roles/store */
    public function store(): void
    {
        $this->requirePermission('roles.manage');
        $this->validateCsrf();

        $name       = trim($this->input->post('name', false) ?? '');
        $desc       = trim($this->input->post('description', false) ?? '');
        $permIds    = $this->input->post('permissions') ?? [];

        if (!$name) {
            if ($this->isAjax()) { $this->json(['success' => false, 'message' => 'Role name is required.']); }
            $this->flash('error', 'Role name is required.');
            $this->redirect($this->baseUrl . '/admin/roles/create');
        }

        $slug   = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $roleId = (string) $this->db('roles')->save([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc,
        ]);

        if ($permIds && is_array($permIds)) {
            foreach ($permIds as $pid) {
                $this->db('role_permissions')->withoutTimestamps()->ignoreDuplicate()->save([
                    'role_id'       => $roleId,
                    'permission_id' => (string) $pid,
                ]);
            }
        }

        Auth::audit('role.create', 'roles', $roleId, ['name' => $name]);
        if ($this->isAjax()) { $this->json(['success' => true, 'message' => "Role \"{$name}\" created successfully."]); }
        $this->flash('success', "Role \"{$name}\" created successfully.");
        $this->redirect($this->baseUrl . '/admin/roles');
    }

    /** POST /admin/roles/([a-zA-Z0-9\-]+)/update */
    public function update(string $id): void
    {
        $this->requirePermission('roles.manage');
        $this->validateCsrf();

        if (!$this->db('roles')->where('id', $id)->get(1)) {
            $this->flash('error', 'Role not found.');
            $this->redirect($this->baseUrl . '/admin/roles');
        }

        $name    = trim($this->input->post('name', false) ?? '');
        $desc    = trim($this->input->post('description', false) ?? '');
        $permIds = $this->input->post('permissions') ?? [];

        if (!$name) {
            if ($this->isAjax()) { $this->json(['success' => false, 'message' => 'Role name is required.']); }
            $this->flash('error', 'Role name is required.');
            $this->redirect($this->baseUrl . "/admin/roles/{$id}/edit");
        }

        $this->db('roles')->where('id', $id)->update(['name' => $name, 'description' => $desc]);

        // Update permissions: delete existing, re-insert
        $this->db('role_permissions')->where('role_id', $id)->delete();
        if ($permIds && is_array($permIds)) {
            foreach ($permIds as $pid) {
                $this->db('role_permissions')->withoutTimestamps()->ignoreDuplicate()->save([
                    'role_id'       => $id,
                    'permission_id' => (string) $pid,
                ]);
            }
        }

        Auth::audit('role.update', 'roles', $id);
        if ($this->isAjax()) { $this->json(['success' => true, 'message' => 'Role updated successfully.']); }
        $this->flash('success', "Role updated successfully.");
        $this->redirect($this->baseUrl . '/admin/roles');
    }

    /** POST /admin/roles/([a-zA-Z0-9\-]+)/delete */
    public function delete(string $id): void
    {
        $this->requirePermission('roles.manage');
        $this->validateCsrf();

        $role = $this->db('roles')->where('id', $id)->get(1);

        if (!$role) {
            $this->flash('error', 'Role not found.');
            $this->redirect($this->baseUrl . '/admin/roles');
        }

        if ($role['is_system']) {
            if ($this->isAjax()) { $this->json(['success' => false, 'message' => 'System roles cannot be deleted.']); }
            $this->flash('error', 'System roles cannot be deleted.');
            $this->redirect($this->baseUrl . '/admin/roles');
        }

        $this->db('roles')->where('id', $id)->delete();

        Auth::audit('role.delete', 'roles', $id, ['name' => $role['name']]);
        if ($this->isAjax()) { $this->json(['success' => true, 'message' => "Role \"{$role['name']}\" deleted."]); }
        $this->flash('success', "Role \"{$role['name']}\" deleted.");
        $this->redirect($this->baseUrl . '/admin/roles');
    }

    private function groupedPermissions(): array
    {
        $rows = $this->db('permissions')->orderBy('module', 'ASC')->orderBy('name', 'ASC')->get() ?: [];
        $grouped = [];
        foreach ($rows as $p) {
            $grouped[$p['module']][] = $p;
        }
        return $grouped;
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            if ($this->isAjax()) { $this->json(['success' => false, 'message' => 'Security token invalid.'], 403); }
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/roles');
        }
    }
}

