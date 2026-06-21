<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class UserModel extends Model
{
    public function __construct()
    {
        parent::__construct('users');
    }

    /** Get paginated users with role info */
    public function listUsers(int $page = 1, int $perPage = 20, string $search = ''): array
    {
        $query = $this->select(['users.id', 'users.name', 'users.email', 'users.status', 'users.last_login', 'users.created_at']);

        if ($search) {
            $query->where('users.name', "%{$search}%", 'LIKE');
        }

        $all  = $query->get() ?: [];
        $total = count($all);
        $offset = ($page - 1) * $perPage;
        return [
            'items' => array_slice($all, $offset, $perPage),
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** Find user by email */
    public function findByEmail(string $email): ?array
    {
        $result = $this->where('email', $email)->get(1);
        return $result ?: null;
    }

    /** Get user's role slugs */
    public function getUserRoles(int $userId): array
    {
        // Use raw query via DB for join
        return [];
    }

    /** Hash password */
    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /** Verify password */
    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
