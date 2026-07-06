<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Core\Controller;
use App\CMS\Auth;
use App\CMS\Installer;
use App\CMS\LoginRateLimiter;
use App\CMS\PasswordResetHelper;
use App\CMS\TotpHelper;
use App\Mail\Mailer;
use App\Mail\MailMessage;
use App\Mail\MailTemplate;

/**
 * Admin Authentication Controller
 * Handles login, logout, 2FA verification, and session management.
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
        if (!Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/setup');
        }

        if (Auth::check()) {
            $this->redirect($this->baseUrl . '/admin/dashboard');
        }

        // Mid-2FA flow: send back to the verification step
        if ($this->session->check('auth_2fa_pending_id')) {
            $this->redirect($this->baseUrl . '/admin/login/2fa');
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

        $limiter = LoginRateLimiter::make($email);
        if ($limiter->isBlocked()) {
            $wait = $limiter->secondsUntilUnblock();
            $mins = (int) ceil($wait / 60);
            $this->session->set('flash', [
                'type'    => 'error',
                'message' => "Too many failed attempts. Please wait {$mins} minute(s) before trying again.",
            ]);
            Auth::audit('login_blocked', 'auth', '', ['email' => $email]);
            $this->redirect($this->baseUrl . '/admin/login');
        }

        $user = Auth::attempt($email, $password);

        if (!$user) {
            $limiter->recordFailure();
            $this->session->set('flash', ['type' => 'error', 'message' => 'Invalid credentials. Please try again.']);
            Auth::audit('login_failed', 'auth', '', ['email' => $email]);
            $this->redirect($this->baseUrl . '/admin/login');
        }

        $limiter->clearAttempts();
        $permsData = Auth::loadUserPermissions((string) $user['id']);

        // If 2FA is enabled for this user, enter the pending-2FA state
        // instead of completing the full login.
        if (TotpHelper::isEnabled((string) $user['id'])) {
            $this->session->set('auth_2fa_pending_id',    (string) $user['id']);
            $this->session->set('auth_2fa_pending_user',  $user);
            $this->session->set('auth_2fa_pending_roles', $permsData['roles']);
            $this->session->set('auth_2fa_pending_perms', $permsData['permissions']);
            $this->redirect($this->baseUrl . '/admin/login/2fa');
        }

        Auth::login($user, $permsData['roles'], $permsData['permissions']);
        Auth::audit('login', 'auth', (string) $user['id']);

        $this->redirect($this->baseUrl . '/admin/dashboard');
    }

    /** GET /admin/logout */
    public function logout(): void
    {
        Auth::audit('logout', 'auth');
        Auth::logout();
        $this->redirect($this->baseUrl . '/admin/login');
    }

    // -- Password reset ---------------------------------------------------------

    /** GET /admin/forgot-password */
    public function forgotPassword(): void
    {
        if (!Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/setup');
        }
        if (Auth::check()) {
            $this->redirect($this->baseUrl . '/admin/dashboard');
        }

        $flash = $this->session->flash('flash');
        $content = $this->render('admin/auth/forgot_password', [
            'csrf_token' => $this->csrf->getToken(),
            'flash'      => is_array($flash) ? $flash : [],
        ], true);

        $this->render('admin/_layouts/auth', [
            'content'   => $content,
            'pageTitle' => 'Forgot Password',
        ]);
    }

    /** POST /admin/forgot-password */
    public function processForgotPassword(): void
    {
        if (!Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/setup');
        }

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->session->set('flash', ['type' => 'error', 'message' => 'Invalid security token. Please try again.']);
            $this->redirect($this->baseUrl . '/admin/forgot-password');
        }

        $email = strtolower(trim($this->input->post('email', false) ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->set('flash', ['type' => 'error', 'message' => 'Please enter a valid email address.']);
            $this->redirect($this->baseUrl . '/admin/forgot-password');
        }

        // Rate limit reset requests per IP+email (reuses login_attempts window)
        $limiter = LoginRateLimiter::make('pwreset:' . $email);
        if ($limiter->isBlocked()) {
            $this->session->set('flash', ['type' => 'error', 'message' => 'Too many reset requests. Please wait a while before trying again.']);
            $this->redirect($this->baseUrl . '/admin/forgot-password');
        }
        $limiter->recordFailure();

        // Only send when the account exists, but never reveal whether it does.
        try {
            $user = (new \Core\Model('users'))
                ->select('id, name, email')
                ->where('email', $email)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->get(1);

            if ($user) {
                $plain = PasswordResetHelper::createToken($email);
                if ($plain !== null) {
                    $this->sendResetEmail($user, $plain);
                    $this->logDirectly('password.reset_requested', 'users', (string) $user['id'], ['email' => $email]);
                }
            }
        } catch (\Throwable) {
            // Swallow - response below is identical either way
        }

        $this->session->set('flash', ['type' => 'success', 'message' => 'If an account exists for that address, a reset link has been sent. The link expires in 24 hours.']);
        $this->redirect($this->baseUrl . '/admin/forgot-password');
    }

    /** GET /admin/reset-password?token= */
    public function resetPassword(): void
    {
        if (!Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/setup');
        }

        $token = trim((string) ($this->input->get('token') ?? ''));
        $email = PasswordResetHelper::validateToken($token);

        if ($email === null) {
            $this->session->set('flash', ['type' => 'error', 'message' => 'That reset link is invalid or has expired. Please request a new one.']);
            $this->redirect($this->baseUrl . '/admin/forgot-password');
        }

        $flash = $this->session->flash('flash');
        $content = $this->render('admin/auth/reset_password', [
            'csrf_token'  => $this->csrf->getToken(),
            'reset_token' => $token,
            'flash'       => is_array($flash) ? $flash : [],
        ], true);

        $this->render('admin/_layouts/auth', [
            'content'   => $content,
            'pageTitle' => 'Reset Password',
        ]);
    }

    /** POST /admin/reset-password */
    public function processResetPassword(): void
    {
        if (!Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/setup');
        }

        $csrf = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($csrf)) {
            $this->session->set('flash', ['type' => 'error', 'message' => 'Invalid security token. Please try again.']);
            $this->redirect($this->baseUrl . '/admin/forgot-password');
        }

        $token    = trim((string) ($this->input->post('reset_token', false) ?? ''));
        $password = $this->input->post('password', false) ?? '';
        $confirm  = $this->input->post('password_confirm', false) ?? '';

        $email = PasswordResetHelper::validateToken($token);
        if ($email === null) {
            $this->session->set('flash', ['type' => 'error', 'message' => 'That reset link is invalid or has expired. Please request a new one.']);
            $this->redirect($this->baseUrl . '/admin/forgot-password');
        }

        if (strlen($password) < 8) {
            $this->session->set('flash', ['type' => 'error', 'message' => 'Password must be at least 8 characters.']);
            $this->redirect($this->baseUrl . '/admin/reset-password?token=' . urlencode($token));
        }
        if ($password !== $confirm) {
            $this->session->set('flash', ['type' => 'error', 'message' => 'Passwords do not match.']);
            $this->redirect($this->baseUrl . '/admin/reset-password?token=' . urlencode($token));
        }

        try {
            $user = (new \Core\Model('users'))
                ->select('id, email')
                ->where('email', $email)
                ->whereNull('deleted_at')
                ->get(1);

            if (!$user) {
                $this->session->set('flash', ['type' => 'error', 'message' => 'Account not found.']);
                $this->redirect($this->baseUrl . '/admin/forgot-password');
            }

            (new \Core\Model('users'))->where('id', (string) $user['id'])->update([
                'password' => \App\Models\UserModel::hashPassword($password),
            ]);

            PasswordResetHelper::consumeToken($token);

            // A password reset signs out every existing session for the account
            \App\CMS\SessionTracker::revokeAllForUser((string) $user['id'], false);

            $this->logDirectly('password.reset_completed', 'users', (string) $user['id'], ['email' => $email]);
        } catch (\Throwable) {
            $this->session->set('flash', ['type' => 'error', 'message' => 'Could not reset the password. Please try again.']);
            $this->redirect($this->baseUrl . '/admin/forgot-password');
        }

        $this->session->set('flash', ['type' => 'success', 'message' => 'Password updated. You can now sign in with your new password.']);
        $this->redirect($this->baseUrl . '/admin/login');
    }

    /** Build and send the reset email */
    private function sendResetEmail(array $user, string $plainToken): void
    {
        try {
            $settings = array_column((new \Core\Model('settings'))->get() ?: [], 'value', 'key');
            $siteUrl  = rtrim($settings['site_url'] ?? $this->baseUrl, '/');
            $resetUrl = $siteUrl . '/admin/reset-password?token=' . urlencode($plainToken);

            $html = MailTemplate::render('password_reset', [
                'userName'    => (string) ($user['name'] ?? ''),
                'resetUrl'    => $resetUrl,
                'siteName'    => $settings['site_name'] ?? 'Vertext CMS',
                'expiryHours' => (int) (PasswordResetHelper::TTL_SECONDS / 3600),
            ]);

            $message = (new MailMessage())
                ->to((string) $user['email'], (string) ($user['name'] ?? ''))
                ->subject('Reset your password - ' . ($settings['site_name'] ?? 'Vertext CMS'))
                ->htmlBody($html);

            Mailer::make()->send($message);
        } catch (\Throwable) {
            // Mail failure must not reveal anything to the requester
        }
    }

    // -- 2FA verification ------------------------------------------------------

    /** GET /admin/login/2fa */
    public function verify2fa(): void
    {
        if (!Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/setup');
        }

        // Must have a pending auth state; otherwise send to login
        if (!$this->session->check('auth_2fa_pending_id')) {
            $this->redirect($this->baseUrl . '/admin/login');
        }

        $flash = $this->session->flash('flash');
        $token = $this->csrf->getToken();

        $content = $this->render('admin/auth/verify_2fa', [
            'csrf_token' => $token,
            'flash'      => is_array($flash) ? $flash : [],
        ], true);

        $this->render('admin/_layouts/auth', [
            'content'   => $content,
            'pageTitle' => 'Two-Factor Authentication',
        ]);
    }

    /** POST /admin/login/2fa */
    public function process2fa(): void
    {
        if (!Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/setup');
        }

        $pendingId = $this->session->get('auth_2fa_pending_id');
        if (!$pendingId) {
            $this->redirect($this->baseUrl . '/admin/login');
        }

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->session->set('flash', ['type' => 'error', 'message' => 'Invalid security token. Please try again.']);
            $this->redirect($this->baseUrl . '/admin/login/2fa');
        }

        $code   = trim($this->input->post('code', false) ?? '');
        $record = TotpHelper::getRecord($pendingId);

        if (!$record) {
            // 2FA record disappeared (e.g. disabled by admin) - complete login normally
            $this->completePendingLogin();
            return;
        }

        $verified     = false;
        $usedBackup   = false;
        $backupIndex  = -1;

        // Try TOTP first
        if (TotpHelper::verify($record['secret'], $code)) {
            $verified = true;
        } else {
            // Try backup codes
            $hashes = json_decode($record['backup_codes'] ?? '[]', true) ?? [];
            $backupIndex = TotpHelper::matchBackupCode($code, $hashes);
            if ($backupIndex !== -1) {
                $verified   = true;
                $usedBackup = true;
                // Consume the used backup code
                $hashes[$backupIndex] = null;
                TotpHelper::updateBackupCodes($pendingId, $hashes);
            }
        }

        if (!$verified) {
            $this->logDirectly('2fa.failed', 'auth', $pendingId, ['user_id' => $pendingId]);
            $this->session->set('flash', ['type' => 'error', 'message' => 'Invalid code. Please try again.']);
            $this->redirect($this->baseUrl . '/admin/login/2fa');
        }

        if ($usedBackup) {
            $this->logDirectly('2fa.backup_used', 'auth', $pendingId, ['user_id' => $pendingId, 'index' => $backupIndex]);
        }

        $this->completePendingLogin();
    }

    // -- Internal --------------------------------------------------------------

    /**
     * Finish the 2FA flow: call Auth::login() with the pending state, then clear it.
     * Also used as a direct path when 2FA record disappears after the password step.
     */
    private function completePendingLogin(): void
    {
        $user  = $this->session->get('auth_2fa_pending_user');
        $roles = $this->session->get('auth_2fa_pending_roles') ?? [];
        $perms = $this->session->get('auth_2fa_pending_perms') ?? [];

        // Clear pending state before calling Auth::login() so the
        // regenerated session doesn't inherit stale keys.
        $this->session->set('auth_2fa_pending_id',    null);
        $this->session->set('auth_2fa_pending_user',  null);
        $this->session->set('auth_2fa_pending_roles', null);
        $this->session->set('auth_2fa_pending_perms', null);

        Auth::login($user, $roles, $perms);
        Auth::audit('login', 'auth', (string) $user['id']);

        $this->redirect($this->baseUrl . '/admin/dashboard');
    }

    /**
     * Write directly to audit_logs when the user isn't yet authenticated
     * (session has no admin_user_id, so Auth::audit() would be a no-op).
     */
    private function logDirectly(string $action, string $type, string $resourceId, array $details = []): void
    {
        try {
            (new \Core\Model('audit_logs'))->withoutTimestamps()->save([
                'user_id'       => null,
                'action'        => $action,
                'resource_type' => $type,
                'resource_id'   => $resourceId,
                'details'       => !empty($details) ? json_encode($details) : null,
                'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Exception) {}
    }
}
