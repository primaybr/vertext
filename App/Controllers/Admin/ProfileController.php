<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\CMS\Auth;
use App\CMS\AvatarHelper;
use App\CMS\SessionTracker;
use App\CMS\TotpHelper;

/**
 * Admin Profile Controller - lets any logged-in user update their own account
 * and manage their Two-Factor Authentication settings.
 */
class ProfileController extends BaseController
{
    protected string $module = '';

    public function __construct()
    {
        parent::__construct();
    }

    // -- Account ---------------------------------------------------------------

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

        $twofa_enabled = TotpHelper::isEnabled($this->currentUser['id']);

        $this->adminRender('admin/profile/index', [
            'user'          => $user,
            'twofa_enabled' => $twofa_enabled,
            'avatar_url'    => AvatarHelper::url($this->currentUser['id'], $this->baseUrl),
            'sessions'      => SessionTracker::listForUser($this->currentUser['id']),
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
            $data['password'] = \App\Models\UserModel::hashPassword($password);
        }

        // Optional avatar upload alongside the profile fields
        if (!empty($_FILES['avatar']['name'])) {
            $error = AvatarHelper::store($_FILES['avatar'], $this->currentUser['id']);
            if ($error !== null) {
                $this->flash('error', $error);
                $this->redirect($this->baseUrl . '/admin/profile');
            }
        }

        $this->db('users')->where('id', $this->currentUser['id'])->update($data);

        // Keep the session's cached user block in sync so the sidebar updates
        $this->session->set('admin_user', array_merge(Auth::user() ?? [], [
            'name'  => $name,
            'email' => $email,
        ]));

        Auth::audit('profile.update', 'users', $this->currentUser['id']);
        $this->flash('success', 'Profile updated successfully.');
        $this->redirect($this->baseUrl . '/admin/profile');
    }

    /** POST /admin/profile/avatar/remove */
    public function removeAvatar(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/profile');
        }

        AvatarHelper::remove($this->currentUser['id']);
        Auth::audit('profile.avatar_removed', 'users', $this->currentUser['id']);
        $this->flash('success', 'Avatar removed.');
        $this->redirect($this->baseUrl . '/admin/profile');
    }

    // -- Active sessions --------------------------------------------------------

    /** POST /admin/profile/sessions/{id}/revoke - revoke ONE of my own sessions */
    public function revokeSession(string $id): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/profile');
        }

        if (SessionTracker::revoke($id, $this->currentUser['id'])) {
            Auth::audit('session.revoked', 'users', $this->currentUser['id'], ['session' => $id]);
            $this->flash('success', 'Session revoked. That device will be signed out on its next request.');
        } else {
            $this->flash('error', 'Session not found.');
        }
        $this->redirect($this->baseUrl . '/admin/profile');
    }

    /** POST /admin/profile/sessions/revoke-others - sign out everywhere else */
    public function revokeOtherSessions(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/profile');
        }

        $count = SessionTracker::revokeAllForUser($this->currentUser['id'], true);
        Auth::audit('session.revoked_others', 'users', $this->currentUser['id'], ['count' => $count]);
        $this->flash('success', $count > 0
            ? "Signed out of {$count} other session(s)."
            : 'No other active sessions found.');
        $this->redirect($this->baseUrl . '/admin/profile');
    }

    // -- Two-Factor Authentication ----------------------------------------------

    /** GET /admin/profile/2fa */
    public function twofa(): void
    {
        $userId  = $this->currentUser['id'];
        $enabled = TotpHelper::isEnabled($userId);

        // Retrieve any in-progress setup secret from the session
        $setupSecret = $this->session->get('auth_2fa_setup_secret');
        $setupUri    = null;

        if ($setupSecret) {
            $user    = $this->db('users')->where('id', $userId)->get(1);
            $email   = $user['email'] ?? $this->currentUser['email'] ?? '';
            $setupUri = TotpHelper::buildUri($setupSecret, $email);
        }

        $flash = $this->session->flash('flash');

        $this->adminRender('admin/profile/2fa', [
            'twofa_enabled' => $enabled,
            'setup_pending' => (bool) $setupSecret,
            'setup_secret'  => $setupSecret ? TotpHelper::formatSecret($setupSecret) : null,
            'setup_raw'     => $setupSecret,
            'setup_uri'     => $setupUri,
            'flash'         => is_array($flash) ? $flash : [],
        ], 'Two-Factor Authentication', 'profile');
    }

    /** POST /admin/profile/2fa/setup - generate a new secret and store in session */
    public function setup2fa(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/profile/2fa');
        }

        if (TotpHelper::isEnabled($this->currentUser['id'])) {
            $this->flash('error', '2FA is already enabled on this account.');
            $this->redirect($this->baseUrl . '/admin/profile/2fa');
        }

        $secret = TotpHelper::generateSecret();
        $this->session->set('auth_2fa_setup_secret', $secret);

        $this->redirect($this->baseUrl . '/admin/profile/2fa');
    }

    /** POST /admin/profile/2fa/confirm - verify code and save 2FA to DB */
    public function confirm2fa(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/profile/2fa');
        }

        $secret = $this->session->get('auth_2fa_setup_secret');
        if (!$secret) {
            $this->flash('error', 'Setup session expired. Please start again.');
            $this->redirect($this->baseUrl . '/admin/profile/2fa');
        }

        $code = trim($this->input->post('code', false) ?? '');

        if (!TotpHelper::verify($secret, $code)) {
            $this->flash('error', 'Invalid code. Please check your authenticator app and try again.');
            $this->redirect($this->baseUrl . '/admin/profile/2fa');
        }

        // Code verified - persist to DB
        $plain  = TotpHelper::generateBackupCodes();
        $hashed = TotpHelper::hashBackupCodes($plain);
        TotpHelper::saveRecord($this->currentUser['id'], $secret, $hashed);

        // Stash plain codes in session for one-time display
        $this->session->set('auth_2fa_setup_secret',  null);
        $this->session->set('auth_2fa_backup_codes',  $plain);

        Auth::audit('2fa.enabled', 'users', $this->currentUser['id']);
        $this->flash('success', '2FA enabled successfully. Save your backup codes now.');
        $this->redirect($this->baseUrl . '/admin/profile/2fa/backup-codes');
    }

    /** GET /admin/profile/2fa/backup-codes - one-time display of plain backup codes */
    public function backupCodes(): void
    {
        $codes = $this->session->get('auth_2fa_backup_codes');

        if (empty($codes)) {
            // Codes already shown or user navigated here directly
            $this->redirect($this->baseUrl . '/admin/profile/2fa');
        }

        // Clear from session - shown only once
        $this->session->set('auth_2fa_backup_codes', null);

        $this->adminRender('admin/profile/2fa_backup_codes', [
            'backup_codes' => $codes,
        ], 'Backup Codes', 'profile');
    }

    /** POST /admin/profile/2fa/disable - verify code/password, then disable 2FA */
    public function disableTwofa(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/profile/2fa');
        }

        $userId = $this->currentUser['id'];

        if (!TotpHelper::isEnabled($userId)) {
            $this->flash('error', '2FA is not enabled on this account.');
            $this->redirect($this->baseUrl . '/admin/profile/2fa');
        }

        $password = $this->input->post('password', false) ?? '';
        $code     = trim($this->input->post('code', false) ?? '');

        // Re-verify current password
        $user = $this->db('users')->where('id', $userId)->get(1);
        if (!$user || !password_verify($password, $user['password'])) {
            $this->flash('error', 'Incorrect password.');
            $this->redirect($this->baseUrl . '/admin/profile/2fa');
        }

        // Require a valid TOTP code or backup code
        $record  = TotpHelper::getRecord($userId);
        $allowed = false;

        if ($record) {
            if (TotpHelper::verify($record['secret'], $code)) {
                $allowed = true;
            } else {
                $hashes = json_decode($record['backup_codes'] ?? '[]', true) ?? [];
                $idx    = TotpHelper::matchBackupCode($code, $hashes);
                if ($idx !== -1) {
                    $allowed = true;
                }
            }
        }

        if (!$allowed) {
            $this->flash('error', 'Invalid 2FA code. Enter a 6-digit TOTP code or one of your backup codes.');
            $this->redirect($this->baseUrl . '/admin/profile/2fa');
        }

        TotpHelper::deleteRecord($userId);
        Auth::audit('2fa.disabled', 'users', $userId);
        $this->flash('success', 'Two-factor authentication has been disabled.');
        $this->redirect($this->baseUrl . '/admin/profile/2fa');
    }
}
