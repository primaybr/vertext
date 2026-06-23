<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\CMS\Auth;
use App\Mail\Mailer;
use App\Mail\MailMessage;
use App\Mail\MailTemplate;

/**
 * Admin Users Controller — CRUD for CMS users
 */
class UsersController extends BaseController
{
    protected string $module = 'users';

    public function __construct()
    {
        parent::__construct();
    }

    /** GET /admin/users */
    public function index(): void
    {
        $this->requirePermission('users.view');
        $search  = trim($this->input->get('search') ?? '');
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $countModel = $this->db('users')->whereNull('deleted_at');
        $listModel  = $this->db('users')
            ->select('id, name, email, status, last_login, created_at')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->limitOffset($perPage, $offset);

        if ($search) {
            $searchBinds = [':s1' => "%{$search}%", ':s2' => "%{$search}%"];
            $countModel->whereRaw('(name ILIKE :s1 OR email ILIKE :s2)', $searchBinds);
            $listModel->whereRaw('(name ILIKE :s1 OR email ILIKE :s2)', $searchBinds);
        }

        $total = (int) ($countModel->totalRows() ?: 0);
        $users = $listModel->get() ?: [];

        $this->adminRender('admin/users/index', [
            'users'  => $users,
            'total'  => $total,
            'page'   => $page,
            'pages'  => max(1, (int) ceil($total / $perPage)),
            'search' => $search,
        ], 'Users', 'users');
    }

    /** GET /admin/users/form — AJAX: returns create form partial for modal */
    public function createForm(): void
    {
        $this->requirePermission('users.create');
        $roles = $this->db('roles')->orderBy('name', 'ASC')->get() ?: [];

        $this->renderPartial('admin/users/_form', [
            'user'      => null,
            'roles'     => $roles,
            'action'    => $this->baseUrl . '/admin/users/store',
        ]);
    }

    /** GET /admin/users/(\d+)/form — AJAX: returns edit form partial for modal */
    public function editForm(string $id): void
    {
        $this->requirePermission('users.update');
        $user = $this->db('users')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$user) { $this->json(['success' => false, 'message' => 'User not found.'], 404); }

        $rpRows      = $this->db('user_roles')->where('user_id', $id)->get() ?: [];
        $userRoleIds = array_column($rpRows, 'role_id');
        $roles       = $this->db('roles')->orderBy('name', 'ASC')->get() ?: [];

        $this->renderPartial('admin/users/_form', [
            'user'        => $user,
            'roles'       => $roles,
            'userRoleIds' => $userRoleIds,
            'action'      => $this->baseUrl . "/admin/users/{$id}/update",
        ]);
    }

    /** POST /admin/users/store */
    public function store(): void
    {
        $this->requirePermission('users.create');
        $this->validateCsrf();

        $name     = trim($this->input->post('name', false) ?? '');
        $email    = trim($this->input->post('email', false) ?? '');
        $password = $this->input->post('password', false) ?? '';
        $status   = $this->input->post('status') ?? 'active';
        $roleIds  = $this->input->post('roles') ?? [];

        $errors = $this->validateUser($name, $email, $password);
        if ($errors) {
            if ($this->isAjax()) { $this->json(['success' => false, 'message' => implode(' ', $errors)]); }
            $this->session->set('flash', ['type' => 'error', 'message' => implode(' ', $errors)]);
            $this->redirect($this->baseUrl . '/admin/users/create');
        }

        $hash   = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $userId = (string) $this->db('users')->save([
            'name'     => $name,
            'email'    => $email,
            'password' => $hash,
            'status'   => $status,
        ]);

        if ($roleIds && is_array($roleIds)) {
            foreach ($roleIds as $roleId) {
                $this->db('user_roles')->withoutTimestamps()->ignoreDuplicate()->save([
                    'user_id' => $userId,
                    'role_id' => (string) $roleId,
                ]);
            }
        }

        Auth::audit('user.create', 'users', $userId, ['email' => $email]);
        $this->sendWelcomeEmail($name, $email);
        if ($this->isAjax()) { $this->json(['success' => true, 'message' => "User \"{$name}\" created successfully."]); }
        $this->flash('success', "User \"{$name}\" created successfully.");
        $this->redirect($this->baseUrl . '/admin/users');
    }

    private function sendWelcomeEmail(string $name, string $email): void
    {
        try {
            $settings = array_column($this->db('settings')->get() ?: [], 'value', 'key');
            $html = MailTemplate::render('welcome', [
                'userName'  => $name,
                'userEmail' => $email,
                'loginUrl'  => rtrim($settings['site_url'] ?? $this->baseUrl, '/') . '/admin/login',
                'siteName'  => $settings['site_name'] ?? 'Vertext CMS',
                'siteUrl'   => $settings['site_url'] ?? $this->baseUrl,
            ]);

            $mailer  = Mailer::make();
            $message = (new MailMessage())
                ->to($email, $name)
                ->subject('Welcome to ' . ($settings['site_name'] ?? 'Vertext CMS'))
                ->htmlBody($html);

            $mailer->send($message);
        } catch (\Throwable) {
            // Email failure must not break user creation
        }
    }

    /** POST /admin/users/(\d+)/update */
    public function update(string $id): void
    {
        $this->requirePermission('users.update');
        $this->validateCsrf();

        if (!$this->db('users')->where('id', $id)->whereNull('deleted_at')->get(1)) {
            $this->flash('error', 'User not found.');
            $this->redirect($this->baseUrl . '/admin/users');
        }

        $name     = trim($this->input->post('name', false) ?? '');
        $email    = trim($this->input->post('email', false) ?? '');
        $password = $this->input->post('password', false) ?? '';
        $status   = $this->input->post('status') ?? 'active';
        $roleIds  = $this->input->post('roles') ?? [];

        if (!$name || !$email) {
            if ($this->isAjax()) { $this->json(['success' => false, 'message' => 'Name and email are required.']); }
            $this->flash('error', 'Name and email are required.');
            $this->redirect($this->baseUrl . "/admin/users/{$id}/edit");
        }

        $data = ['name' => $name, 'email' => $email, 'status' => $status];

        if ($password) {
            if (strlen($password) < 8) {
                if ($this->isAjax()) { $this->json(['success' => false, 'message' => 'Password must be at least 8 characters.']); }
                $this->flash('error', 'Password must be at least 8 characters.');
                $this->redirect($this->baseUrl . "/admin/users/{$id}/edit");
            }
            $data['password'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        $this->db('users')->where('id', $id)->update($data);

        // Update roles: delete existing, re-insert
        $this->db('user_roles')->where('user_id', $id)->delete();
        if ($roleIds && is_array($roleIds)) {
            foreach ($roleIds as $roleId) {
                $this->db('user_roles')->withoutTimestamps()->ignoreDuplicate()->save([
                    'user_id' => $id,
                    'role_id' => (string) $roleId,
                ]);
            }
        }

        Auth::audit('user.update', 'users', $id);
        if ($this->isAjax()) { $this->json(['success' => true, 'message' => 'User updated successfully.']); }
        $this->flash('success', "User updated successfully.");
        $this->redirect($this->baseUrl . '/admin/users');
    }

    /** POST /admin/users/(\d+)/delete */
    public function delete(string $id): void
    {
        $this->requirePermission('users.delete');
        $this->validateCsrf();

        if ($id === ($this->currentUser['id'] ?? '')) {
            if ($this->isAjax()) { $this->json(['success' => false, 'message' => 'You cannot delete your own account.']); }
            $this->flash('error', 'You cannot delete your own account.');
            $this->redirect($this->baseUrl . '/admin/users');
        }

        $this->db('users')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        Auth::audit('user.delete', 'users', $id);
        if ($this->isAjax()) { $this->json(['success' => true, 'message' => 'User deleted successfully.']); }
        $this->flash('success', 'User deleted successfully.');
        $this->redirect($this->baseUrl . '/admin/users');
    }

    private function validateUser(string $name, string $email, string $password): array
    {
        $errors = [];
        if (!$name)                     $errors[] = 'Name is required.';
        if (!$email)                    $errors[] = 'Email is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (!$password)                 $errors[] = 'Password is required.';
        if (strlen($password) < 8)      $errors[] = 'Password must be at least 8 characters.';
        return $errors;
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            if ($this->isAjax()) { $this->json(['success' => false, 'message' => 'Security token invalid.'], 403); }
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/users');
        }
    }
}
