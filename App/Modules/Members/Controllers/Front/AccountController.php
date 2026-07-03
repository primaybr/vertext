<?php

declare(strict_types=1);

namespace App\Modules\Members\Controllers\Front;

use Core\Controller;
use Core\Model;
use App\CMS\LoginRateLimiter;
use App\CMS\SiteAuth;
use App\Mail\Mailer;
use App\Mail\MailMessage;
use App\Mail\MailTemplate;
use App\Theme\ThemeEngine;

/**
 * Public member account flows: register, verify, login, logout, profile.
 */
class AccountController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    // ── Registration ───────────────────────────────────────────────────────────

    /** GET /account/register */
    public function registerForm(): void
    {
        if (SiteAuth::check()) {
            $this->redirect($this->baseUrl . '/account');
        }

        $flash = $this->session->flash('member_flash') ?: [];
        ThemeEngine::render('modules/members/front/register', [
            'flash'      => is_array($flash) ? $flash : [],
            'old'        => $this->session->flash('member_old') ?: [],
            'baseUrl'    => $this->baseUrl,
            'csrf_token' => $this->csrf->getToken(),
            'page_title' => 'Create Account',
        ]);
    }

    /** POST /account/register */
    public function register(): void
    {
        if (SiteAuth::check()) {
            $this->redirect($this->baseUrl . '/account');
        }

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->session->set('member_flash', ['type' => 'error', 'message' => 'Security token invalid. Please try again.']);
            $this->redirect($this->baseUrl . '/account/register');
        }

        // Honeypot: hidden "website" field must stay empty (bots fill it)
        if (trim($this->input->post('website', false) ?? '') !== '') {
            $this->session->set('member_flash', ['type' => 'success', 'message' => 'Account created. Please check your email.']);
            $this->redirect($this->baseUrl . '/account/register');
        }

        $name     = trim($this->input->post('name', false) ?? '');
        $email    = strtolower(trim($this->input->post('email', false) ?? ''));
        $password = $this->input->post('password', false) ?? '';
        $confirm  = $this->input->post('password_confirm', false) ?? '';

        $error = null;
        if ($name === '' || mb_strlen($name) > 120)              $error = 'Please enter your name (max 120 characters).';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))       $error = 'Please enter a valid email address.';
        elseif (strlen($password) < 8)                            $error = 'Password must be at least 8 characters.';
        elseif ($password !== $confirm)                           $error = 'Passwords do not match.';

        if ($error !== null) {
            $this->session->set('member_flash', ['type' => 'error', 'message' => $error]);
            $this->session->set('member_old', ['name' => $name, 'email' => $email]);
            $this->redirect($this->baseUrl . '/account/register');
        }

        try {
            $existing = (new Model('site_users'))->where('email', $email)->whereNull('deleted_at')->get(1);
            if ($existing) {
                $this->session->set('member_flash', ['type' => 'error', 'message' => 'An account with that email already exists. Try signing in instead.']);
                $this->session->set('member_old', ['name' => $name]);
                $this->redirect($this->baseUrl . '/account/register');
            }

            $requireVerify = $this->requireVerification();
            $userId = (string) (new Model('site_users'))->save([
                'name'     => $name,
                'email'    => $email,
                'password' => \Core\Security\Password::hash($password),
                'status'   => $requireVerify ? 'pending' : 'active',
            ]);

            $user = (new Model('site_users'))->where('id', $userId)->get(1);

            if ($requireVerify) {
                $this->sendVerificationEmail($user);
                $this->session->set('member_flash', ['type' => 'success', 'message' => 'Account created. Please check your email for a verification link.']);
                $this->redirect($this->baseUrl . '/account/login');
            }

            (new Model('site_users'))->where('id', $userId)->update(['verified_at' => date('Y-m-d H:i:s')]);
            $this->dispatchRegisteredWebhook($user);
            SiteAuth::login($user);
            $this->session->set('member_flash', ['type' => 'success', 'message' => 'Welcome, ' . $name . '! Your account is ready.']);
            $this->redirect($this->baseUrl . '/account');
        } catch (\Throwable $e) {
            $this->session->set('member_flash', ['type' => 'error', 'message' => 'Could not create the account. Please try again.']);
            $this->redirect($this->baseUrl . '/account/register');
        }
    }

    /** GET /account/verify?token= */
    public function verify(): void
    {
        $token = trim((string) ($this->input->get('token') ?? ''));

        if ($token === '' || !preg_match('/^[a-f0-9\-]{36}$/', $token)) {
            $this->session->set('member_flash', ['type' => 'error', 'message' => 'Invalid verification link.']);
            $this->redirect($this->baseUrl . '/account/login');
        }

        try {
            $user = (new Model('site_users'))
                ->where('verify_token', $token)
                ->where('status', 'pending')
                ->whereNull('deleted_at')
                ->get(1);

            if (!$user) {
                $this->session->set('member_flash', ['type' => 'error', 'message' => 'That verification link is invalid or was already used.']);
                $this->redirect($this->baseUrl . '/account/login');
            }

            (new Model('site_users'))->where('id', (string) $user['id'])->update([
                'status'      => 'active',
                'verified_at' => date('Y-m-d H:i:s'),
            ]);

            $this->dispatchRegisteredWebhook($user);
            $this->session->set('member_flash', ['type' => 'success', 'message' => 'Email verified! You can now sign in.']);
        } catch (\Throwable $e) {
            $this->session->set('member_flash', ['type' => 'error', 'message' => 'Verification failed. Please try again.']);
        }

        $this->redirect($this->baseUrl . '/account/login');
    }

    // ── Login / logout ─────────────────────────────────────────────────────────

    /** GET /account/login */
    public function loginForm(): void
    {
        if (SiteAuth::check()) {
            $this->redirect($this->baseUrl . '/account');
        }

        $flash = $this->session->flash('member_flash') ?: [];
        ThemeEngine::render('modules/members/front/login', [
            'flash'      => is_array($flash) ? $flash : [],
            'baseUrl'    => $this->baseUrl,
            'csrf_token' => $this->csrf->getToken(),
            'page_title' => 'Sign In',
        ]);
    }

    /** POST /account/login */
    public function login(): void
    {
        if (SiteAuth::check()) {
            $this->redirect($this->baseUrl . '/account');
        }

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->session->set('member_flash', ['type' => 'error', 'message' => 'Security token invalid. Please try again.']);
            $this->redirect($this->baseUrl . '/account/login');
        }

        $email    = strtolower(trim($this->input->post('email', false) ?? ''));
        $password = $this->input->post('password', false) ?? '';

        if ($email === '' || $password === '') {
            $this->session->set('member_flash', ['type' => 'error', 'message' => 'Email and password are required.']);
            $this->redirect($this->baseUrl . '/account/login');
        }

        // Separate rate-limit scope from the admin login
        $limiter = LoginRateLimiter::make('member:' . $email);
        if ($limiter->isBlocked()) {
            $mins = (int) ceil($limiter->secondsUntilUnblock() / 60);
            $this->session->set('member_flash', ['type' => 'error', 'message' => "Too many failed attempts. Please wait {$mins} minute(s)."]);
            $this->redirect($this->baseUrl . '/account/login');
        }

        $user = SiteAuth::attempt($email, $password);

        if (!$user) {
            $limiter->recordFailure();

            // Distinguish the unverified case for a friendlier message
            try {
                $pending = (new Model('site_users'))
                    ->where('email', $email)->where('status', 'pending')->whereNull('deleted_at')->get(1);
                if ($pending && \Core\Security\Password::verify($password, $pending['password'])) {
                    $this->session->set('member_flash', ['type' => 'warning', 'message' => 'Please verify your email address first. Check your inbox for the verification link.']);
                    $this->redirect($this->baseUrl . '/account/login');
                }
            } catch (\Throwable $e) {
            }

            $this->session->set('member_flash', ['type' => 'error', 'message' => 'Invalid credentials. Please try again.']);
            $this->redirect($this->baseUrl . '/account/login');
        }

        $limiter->clearAttempts();
        SiteAuth::login($user);
        $this->redirect($this->baseUrl . '/account');
    }

    /** GET /account/logout */
    public function logout(): void
    {
        SiteAuth::logout();
        $this->session->set('member_flash', ['type' => 'success', 'message' => 'You have been signed out.']);
        $this->redirect($this->baseUrl . '/account/login');
    }

    // ── Profile ────────────────────────────────────────────────────────────────

    /** GET /account */
    public function profile(): void
    {
        if (!SiteAuth::check()) {
            $this->redirect($this->baseUrl . '/account/login');
        }

        $user = (new Model('site_users'))
            ->where('id', SiteAuth::id())
            ->whereNull('deleted_at')
            ->get(1);

        if (!$user) {
            SiteAuth::logout();
            $this->redirect($this->baseUrl . '/account/login');
        }

        $flash = $this->session->flash('member_flash') ?: [];
        ThemeEngine::render('modules/members/front/account', [
            'user'       => $user,
            'flash'      => is_array($flash) ? $flash : [],
            'baseUrl'    => $this->baseUrl,
            'csrf_token' => $this->csrf->getToken(),
            'page_title' => 'My Account',
        ]);
    }

    /** POST /account/update */
    public function update(): void
    {
        if (!SiteAuth::check()) {
            $this->redirect($this->baseUrl . '/account/login');
        }

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->session->set('member_flash', ['type' => 'error', 'message' => 'Security token invalid. Please try again.']);
            $this->redirect($this->baseUrl . '/account');
        }

        $name     = trim($this->input->post('name', false) ?? '');
        $current  = $this->input->post('current_password', false) ?? '';
        $password = $this->input->post('password', false) ?? '';
        $confirm  = $this->input->post('password_confirm', false) ?? '';

        if ($name === '' || mb_strlen($name) > 120) {
            $this->session->set('member_flash', ['type' => 'error', 'message' => 'Please enter your name (max 120 characters).']);
            $this->redirect($this->baseUrl . '/account');
        }

        $data = ['name' => $name];

        if ($password !== '') {
            $user = (new Model('site_users'))->where('id', SiteAuth::id())->get(1);
            if (!$user || !\Core\Security\Password::verify($current, $user['password'])) {
                $this->session->set('member_flash', ['type' => 'error', 'message' => 'Your current password is incorrect.']);
                $this->redirect($this->baseUrl . '/account');
            }
            if (strlen($password) < 8) {
                $this->session->set('member_flash', ['type' => 'error', 'message' => 'New password must be at least 8 characters.']);
                $this->redirect($this->baseUrl . '/account');
            }
            if ($password !== $confirm) {
                $this->session->set('member_flash', ['type' => 'error', 'message' => 'New passwords do not match.']);
                $this->redirect($this->baseUrl . '/account');
            }
            $data['password'] = \Core\Security\Password::hash($password);
        }

        (new Model('site_users'))->where('id', SiteAuth::id())->update($data);
        SiteAuth::refresh(['name' => $name]);

        $this->session->set('member_flash', ['type' => 'success', 'message' => 'Account updated.']);
        $this->redirect($this->baseUrl . '/account');
    }

    // ── Internal ───────────────────────────────────────────────────────────────

    private function requireVerification(): bool
    {
        try {
            $row = (new Model('settings'))->where('key', 'members_require_verification')->get(1);
            return ($row['value'] ?? '1') === '1';
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function sendVerificationEmail(array $user): void
    {
        try {
            $settings  = array_column((new Model('settings'))->get() ?: [], 'value', 'key');
            $siteUrl   = rtrim($settings['site_url'] ?? $this->baseUrl, '/');
            $verifyUrl = $siteUrl . '/account/verify?token=' . urlencode((string) $user['verify_token']);

            $html = MailTemplate::render('member_verify', [
                'userName'  => (string) $user['name'],
                'verifyUrl' => $verifyUrl,
                'siteName'  => $settings['site_name'] ?? 'Our site',
            ]);

            $message = (new MailMessage())
                ->to((string) $user['email'], (string) $user['name'])
                ->subject('Verify your email - ' . ($settings['site_name'] ?? 'New account'))
                ->htmlBody($html);

            Mailer::make()->send($message);
        } catch (\Throwable $e) {
            // Non-fatal: the admin can resend from the Members screen
        }
    }

    private function dispatchRegisteredWebhook(array $user): void
    {
        if (\App\CMS\ModuleLoader::isEnabled('webhooks')) {
            try {
                \App\Modules\Webhooks\WebhookDispatcher::dispatch('user.registered', [
                    'user_id' => (string) $user['id'],
                    'name'    => (string) $user['name'],
                    'email'   => (string) $user['email'],
                ]);
            } catch (\Throwable $e) {
            }
        }
    }
}
