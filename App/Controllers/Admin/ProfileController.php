<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\CMS\Auth;

/**
 * Admin Profile Controller - lets any logged-in user update their own account
 */
class ProfileController extends BaseController
{
    protected string $module = '';

    public function __construct()
    {
        parent::__construct();
    }

    /** GET /admin/profile */
    public function index(): void
    {
        $user = $this->db('users')
            ->where('id', $this->currentUser['id'])
            ->get(1);

        if (!$user) {
            $this->flash('error', 'User not found.');
            $this->redirect($this->baseUrl . '/admin/dashboard');
        }

        $this->adminRender('admin/profile/index', [
            'user' => $user,
        ], 'My Profile', 'profile');
    }

    /** POST /admin/profile/update */
    public function update(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/profile');
        }

        $name     = trim($this->input->post('name', false) ?? '');
        $email    = trim($this->input->post('email', false) ?? '');
        $password = $this->input->post('password', false) ?? '';
        $confirm  = $this->input->post('password_confirm', false) ?? '';

        if (!$name || !$email) {
            $this->flash('error', 'Name and email are required.');
            $this->redirect($this->baseUrl . '/admin/profile');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Invalid email address.');
            $this->redirect($this->baseUrl . '/admin/profile');
        }

        $existing = $this->db('users')
            ->where('email', $email)
            ->where('id', $this->currentUser['id'], '!=')
            ->whereNull('deleted_at')
            ->get(1);

        if ($existing) {
            $this->flash('error', 'That email address is already in use.');
            $this->redirect($this->baseUrl . '/admin/profile');
        }

        $data = ['name' => $name, 'email' => $email];

        if ($password !== '') {
            if (strlen($password) < 8) {
                $this->flash('error', 'Password must be at least 8 characters.');
                $this->redirect($this->baseUrl . '/admin/profile');
            }
            if ($password !== $confirm) {
                $this->flash('error', 'Passwords do not match.');
                $this->redirect($this->baseUrl . '/admin/profile');
            }
            $data['password'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        $this->db('users')->where('id', $this->currentUser['id'])->update($data);

        Auth::audit('profile.update', 'users', $this->currentUser['id']);
        $this->flash('success', 'Profile updated successfully.');
        $this->redirect($this->baseUrl . '/admin/profile');
    }
}
