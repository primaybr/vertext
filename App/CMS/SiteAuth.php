<?php

declare(strict_types=1);

namespace App\CMS;

use Core\Http\Session;
use Core\Model;
use Core\Security\Password;

/**
 * Front-end visitor authentication (Members module).
 *
 * Mirrors App\CMS\Auth but operates on the site_users table with its own
 * session namespace (site_user_*), so a site member session and an admin
 * session can coexist without touching each other.
 */
class SiteAuth
{
    private static ?Session $session = null;

    private static function session(): Session
    {
        if (!self::$session) {
            self::$session = new Session();
        }
        return self::$session;
    }

    /** Is a site member currently logged in? */
    public static function check(): bool
    {
        return self::session()->check('site_user_id');
    }

    /** Logged-in member id */
    public static function id(): ?string
    {
        $id = self::session()->get('site_user_id');
        return $id ? (string) $id : null;
    }

    /** Logged-in member data (session cache: id, name, email) */
    public static function user(): ?array
    {
        $data = self::session()->get('site_user');
        return is_array($data) ? $data : null;
    }

    /** Start a member session */
    public static function login(array $user): void
    {
        $session = self::session();
        $session->regenerateId(true);
        $session->set('site_user_id', (string) $user['id']);
        $session->set('site_user', [
            'id'    => (string) $user['id'],
            'name'  => (string) $user['name'],
            'email' => (string) $user['email'],
        ]);
    }

    /** End the member session without touching other session data */
    public static function logout(): void
    {
        $session = self::session();
        $session->set('site_user_id', null);
        $session->set('site_user', null);
        $session->regenerateId(true);
    }

    /** Attempt login. Returns user array on success, null on failure. */
    public static function attempt(string $email, string $password): ?array
    {
        try {
            $user = (new Model('site_users'))
                ->select('id, name, email, password, status, verified_at')
                ->where('email', strtolower(trim($email)))
                ->whereNull('deleted_at')
                ->get(1);

            if (!$user || !Password::verify($password, $user['password'])) {
                return null;
            }

            if ($user['status'] !== 'active') {
                return null;
            }

            $update = ['last_login' => date('Y-m-d H:i:s')];
            if (Password::needsRehash($user['password'])) {
                $update['password'] = Password::hash($password);
            }
            (new Model('site_users'))->where('id', (string) $user['id'])->update($update);

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Refresh the cached session block after a profile update */
    public static function refresh(array $fields): void
    {
        $user = self::user();
        if ($user) {
            self::session()->set('site_user', array_merge($user, $fields));
        }
    }
}
