<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Core\Controller;
use App\CMS\Auth;
use App\CMS\Installer;
use App\CMS\LoginRateLimiter;

/**
 * Admin Authentication Controller
 * Handles login, logout, and session management.
 */
class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /** GET /admin/login */
    public function login(): void
    {
        // Redirect to setup if not installed
        if (!Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/setup');
        }

        // Already logged in → dashboard
        if (Auth::check()) {
            $this->redirect($this->baseUrl . '/admin/dashboard');
        }

        $flash = $this->session->flash('flash');
        $token = $this->csrf->getToken();

        $content = $this->render('admin/auth/login', [
            'csrf_token' => $token,
            'flash'      => is_array($flash) ? $flash : [],
        ], true);

        $this->render('admin/_layouts/auth', [
            'content'   => $content,
            'pageTitle' => 'Login',
        ]);
    }

    /** POST /admin/login */
    public function processLogin(): void
    {
        if (!Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/setup');
        }

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->session->set('flash', ['type' => 'error', 'message' => 'Invalid security token. Please try again.']);
            $this->redirect($this->baseUrl . '/admin/login');
        }

        $email    = trim($this->input->post('email', false) ?? '');
        $password = $this->input->post('password', false) ?? '';

        if (empty($email) || empty($password)) {
            $this->session->set('flash', ['type' => 'error', 'message' => 'Email and password are required.']);
            $this->redirect($this->baseUrl . '/admin/login');
        }

        // Rate-limit check before touching the DB with credentials
        $limiter = LoginRateLimiter::make($email);
        if ($limiter->isBlocked()) {
            $wait = $limiter->secondsUntilUnblock();
            $mins = (int) ceil($wait / 60);
            $this->session->set('flash', [
                'type'    => 'error',
                'message' => "Too many failed attempts. Please wait {$mins} minute(s) before trying again.",
            ]);
            Auth::audit('login_blocked', 'auth', 0, ['email' => $email]);
            $this->redirect($this->baseUrl . '/admin/login');
        }

        $user = Auth::attempt($email, $password);

        if (!$user) {
            $limiter->recordFailure();
            $this->session->set('flash', ['type' => 'error', 'message' => 'Invalid credentials. Please try again.']);
            Auth::audit('login_failed', 'auth', 0, ['email' => $email]);
            $this->redirect($this->baseUrl . '/admin/login');
        }

        $limiter->clearAttempts();
        $permsData = Auth::loadUserPermissions((int) $user['id']);
        Auth::login($user, $permsData['roles'], $permsData['permissions']);
        Auth::audit('login', 'auth', (int)$user['id']);

        $this->redirect($this->baseUrl . '/admin/dashboard');
    }

    /** GET /admin/logout */
    public function logout(): void
    {
        Auth::audit('logout', 'auth');
        Auth::logout();
        $this->redirect($this->baseUrl . '/admin/login');
    }

}
