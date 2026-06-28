<?php

declare(strict_types=1);

namespace App\CMS;

use Core\Http\Session;
use Core\Model;

/**
 * Vertext CMS Auth Helper
 * Static helper for checking authentication and permissions.
 */
class Auth
{
    private static ?Session $session = null;

    private static function session(): Session
    {
        if (!self::$session) {
            self::$session = new Session();
        }
        return self::$session;
    }

    /** Check if an admin user is currently logged in */
    public static function check(): bool
    {
        return self::session()->check('admin_user_id');
    }

    /** Get the currently logged-in user ID */
    public static function id(): ?string
    {
        $id = self::session()->get('admin_user_id');
        return $id ? (string) $id : null;
    }

    /** Get the currently logged-in user data (from session cache) */
    public static function user(): ?array
    {
        $userData = self::session()->get('admin_user');
        if (!$userData) return null;
        return is_array($userData) ? $userData : null;
    }

    /** Log a user in - saves session data */
    public static function login(array $user, array $roles = [], array $permissions = []): void
    {
        $session = self::session();
        $session->regenerateId(true);
        $session->set('admin_user_id', (string) $user['id']);
        $session->set('admin_user', [
            'id'     => (string) $user['id'],
            'name'   => (string) $user['name'],
            'email'  => (string) $user['email'],
            'status' => (string) $user['status'],
        ]);
        $session->set('admin_roles',       $roles);
        $session->set('admin_permissions', $permissions);
    }

    /** Log the current user out */
    public static function logout(): void
    {
        self::session()->destroy();
    }

    /** Check if current user has a specific permission slug */
    public static function can(string $permission): bool
    {
        // Administrator has all permissions
        $roles = self::session()->get('admin_roles') ?? [];
        if (in_array('administrator', $roles, true)) {
            return true;
        }

        $perms = self::session()->get('admin_permissions') ?? [];
        return in_array($permission, $perms, true);
    }

    /** Check if current user has a specific role slug */
    public static function hasRole(string $role): bool
    {
        $roles = self::session()->get('admin_roles') ?? [];
        return in_array($role, $roles, true);
    }

    /** Attempt login from database - returns user array or null on failure. */
    public static function attempt(string $email, string $password): ?array
    {
        try {
            $user = (new Model('users'))
                ->select('id, name, email, password, status')
                ->where('email', $email)
                ->whereNull('deleted_at')
                ->get(1);

            if (!$user || !password_verify($password, $user['password'])) {
                return null;
            }

            if ($user['status'] !== 'active') {
                return null;
            }

            (new Model('users'))->where('id', (string) $user['id'])->update(['last_login' => date('Y-m-d H:i:s')]);

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Load user roles and permissions from DB */
    public static function loadUserPermissions(string $userId): array
    {
        $roles = [];
        $permissions = [];

        try {
            $roleRows = (new Model('roles'))
                ->select('roles.slug')
                ->join('user_roles', 'user_roles.role_id = roles.id', 'INNER')
                ->where('user_roles.user_id', $userId)
                ->get() ?: [];
            $roles = array_column($roleRows, 'slug');

            $permRows = (new Model('permissions'))
                ->select('permissions.slug')
                ->distinct()
                ->join('role_permissions', 'role_permissions.permission_id = permissions.id', 'INNER')
                ->join('user_roles', 'user_roles.role_id = role_permissions.role_id', 'INNER')
                ->where('user_roles.user_id', $userId)
                ->get() ?: [];
            $permissions = array_column($permRows, 'slug');
        } catch (\Exception $e) {
            // Return empty if DB unavailable
        }

        return compact('roles', 'permissions');
    }

    /** Log an admin action to audit_logs */
    public static function audit(string $action, string $resourceType = '', string $resourceId = '', array $details = []): void
    {
        $userId = self::id();
        if (!$userId) return;

        try {
            // audit_logs uses created_at DEFAULT NOW() - withoutTimestamps() prevents the ORM
            // from injecting created_at/updated_at and colliding with the table schema.
            (new Model('audit_logs'))->withoutTimestamps()->save([
                'user_id'       => $userId,
                'action'        => $action,
                'resource_type' => $resourceType ?: null,
                'resource_id'   => $resourceId ?: null,
                'details'       => !empty($details) ? json_encode($details) : null,
                'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Exception $e) {
            // Non-fatal
        }
    }
}
