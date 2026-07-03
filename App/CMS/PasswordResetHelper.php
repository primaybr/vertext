<?php

declare(strict_types=1);

namespace App\CMS;

use Core\Model;

/**
 * Password reset token lifecycle for admin users.
 *
 * Tokens are single-use, expire after 24 hours, and only their SHA-256 hash
 * is stored (the plaintext token exists only inside the emailed link).
 * The password_resets table is auto-created on first use, matching the
 * LoginRateLimiter / TotpHelper pattern.
 */
class PasswordResetHelper
{
    public const TTL_SECONDS = 86400; // 24 hours

    private static bool $tableChecked = false;

    private static function ensureTable(): void
    {
        if (self::$tableChecked) return;
        self::$tableChecked = true;

        try {
            (new Model('password_resets'))->withoutTimestamps()->get(1);
        } catch (\Throwable) {
            try {
                $conn = (new Model('password_resets'))->db;
                $conn->query('
                    CREATE TABLE IF NOT EXISTS password_resets (
                        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                        email VARCHAR(180) NOT NULL,
                        token_hash VARCHAR(64) UNIQUE NOT NULL,
                        expires_at TIMESTAMP NOT NULL,
                        used_at TIMESTAMP,
                        created_at TIMESTAMP NOT NULL DEFAULT NOW()
                    )
                ');
                $conn->execute();
                $conn->query('CREATE INDEX IF NOT EXISTS idx_password_resets_email ON password_resets (email)');
                $conn->execute();
            } catch (\Throwable $e) {
                // Non-fatal; the request flow reports a generic failure.
            }
        }
    }

    /**
     * Create a reset token for an email. Invalidates any previous tokens for
     * the same address. Returns the PLAINTEXT token for the email link.
     */
    public static function createToken(string $email): ?string
    {
        self::ensureTable();

        try {
            $email = strtolower(trim($email));

            // One live token per address: drop older requests
            (new Model('password_resets'))->withoutTimestamps()
                ->where('email', $email)
                ->delete();

            $token = bin2hex(random_bytes(32));

            (new Model('password_resets'))->withoutTimestamps()->save([
                'email'      => $email,
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_SECONDS),
            ]);

            return $token;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Validate a plaintext token. Returns the associated email when the token
     * exists, is unused, and has not expired; null otherwise.
     */
    public static function validateToken(string $token): ?string
    {
        self::ensureTable();

        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        try {
            $row = (new Model('password_resets'))->withoutTimestamps()
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('used_at')
                ->get(1);

            if (!$row) return null;
            if (strtotime((string) $row['expires_at']) < time()) return null;

            return (string) $row['email'];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Burn a token after a successful reset (single use). */
    public static function consumeToken(string $token): void
    {
        self::ensureTable();

        try {
            (new Model('password_resets'))->withoutTimestamps()
                ->where('token_hash', hash('sha256', $token))
                ->update(['used_at' => date('Y-m-d H:i:s')]);

            // Opportunistic prune of expired rows
            (new Model('password_resets'))->withoutTimestamps()
                ->whereRaw('expires_at < :now', [':now' => date('Y-m-d H:i:s')])
                ->delete();
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }
}
