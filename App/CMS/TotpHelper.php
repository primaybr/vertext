<?php

declare(strict_types=1);

namespace App\CMS;

use Core\Model;

/**
 * TOTP 2FA helper - RFC 6238 compliant, no external dependencies.
 *
 * Covers: secret generation, code verification (±1 window), backup code
 * management, DB record CRUD, and lazy table creation.
 */
final class TotpHelper
{
    private const ISSUER = 'Vertext CMS';
    private const DIGITS = 6;
    private const PERIOD = 30;
    private const WINDOW = 1;
    private const ALPHA  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const BCODES = 8;

    // ── Public API ────────────────────────────────────────────────────────────

    /** Generate a 20-byte (160-bit) base32-encoded TOTP secret. */
    public static function generateSecret(): string
    {
        return self::b32Encode(random_bytes(20));
    }

    /** Build the otpauth:// URI for authenticator apps. */
    public static function buildUri(string $secret, string $email): string
    {
        $label  = rawurlencode(self::ISSUER . ':' . $email);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => self::ISSUER,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Verify a TOTP code against a secret.
     * Accepts codes for ±WINDOW time steps to account for clock drift.
     */
    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/[\s\-]/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $raw  = self::b32Decode($secret);
        $step = (int) floor(time() / self::PERIOD);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (self::hotp($raw, $step + $i) === $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate 8 plain-text backup codes (XXXXX-XXXXX format).
     * Returns the plain codes — show once, then discard.
     */
    public static function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::BCODES; $i++) {
            $codes[] = sprintf('%05d-%05d', random_int(0, 99999), random_int(0, 99999));
        }
        return $codes;
    }

    /**
     * Hash all backup codes for storage.
     * Returns an array of bcrypt hashes.
     */
    public static function hashBackupCodes(array $plain): array
    {
        return array_map(
            static fn(string $c) => password_hash($c, PASSWORD_BCRYPT, ['cost' => 10]),
            $plain
        );
    }

    /**
     * Test a backup code against stored hashes.
     * Returns the matched index (0-7), or -1 if none match.
     */
    public static function matchBackupCode(string $input, array $hashes): int
    {
        $input = preg_replace('/[\s\-]/', '', $input);
        foreach ($hashes as $idx => $hash) {
            if ($hash !== null && password_verify($input, $hash)) {
                return (int) $idx;
            }
        }
        return -1;
    }

    /** Format a base32 secret into readable groups of 4 for manual entry. */
    public static function formatSecret(string $secret): string
    {
        return implode(' ', str_split(rtrim($secret, '='), 4));
    }

    // ── DB helpers ────────────────────────────────────────────────────────────

    /** Fetch the 2FA record for a user, or null if 2FA is not enabled. */
    public static function getRecord(string $userId): ?array
    {
        self::ensureTable();
        try {
            $row = (new Model('user_2fa_secrets'))
                ->withoutTimestamps()
                ->where('user_id', $userId)
                ->get(1);
            return $row ?: null;
        } catch (\Exception) {
            return null;
        }
    }

    /** True if the user has 2FA enabled. */
    public static function isEnabled(string $userId): bool
    {
        return self::getRecord($userId) !== null;
    }

    /**
     * Save a new 2FA record.
     * Replaces any existing record for the user (e.g. when re-enabling).
     */
    public static function saveRecord(string $userId, string $secret, array $hashedCodes): void
    {
        self::ensureTable();

        // Remove any prior record — UNIQUE constraint on user_id
        self::deleteRecord($userId);

        (new Model('user_2fa_secrets'))->withoutTimestamps()->save([
            'user_id'      => $userId,
            'secret'       => $secret,
            'backup_codes' => json_encode($hashedCodes),
            'enabled_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /** Remove a user's 2FA record (disable 2FA). */
    public static function deleteRecord(string $userId): void
    {
        try {
            (new Model('user_2fa_secrets'))
                ->withoutTimestamps()
                ->where('user_id', $userId)
                ->delete();
        } catch (\Exception) {}
    }

    /** Persist updated backup_codes after a code has been consumed. */
    public static function updateBackupCodes(string $userId, array $hashes): void
    {
        try {
            (new Model('user_2fa_secrets'))
                ->withoutTimestamps()
                ->where('user_id', $userId)
                ->update([
                    'backup_codes' => json_encode($hashes),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);
        } catch (\Exception) {}
    }

    /** CREATE TABLE IF NOT EXISTS user_2fa_secrets. Called lazily before any DB op. */
    public static function ensureTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            $m = new Model('user_2fa_secrets');
            $m->db->query("
                CREATE TABLE IF NOT EXISTS user_2fa_secrets (
                    id           UUID        NOT NULL DEFAULT gen_random_uuid(),
                    user_id      UUID        NOT NULL,
                    secret       VARCHAR(64) NOT NULL,
                    backup_codes TEXT        NOT NULL DEFAULT '[]',
                    enabled_at   TIMESTAMP   NOT NULL DEFAULT NOW(),
                    updated_at   TIMESTAMP   NOT NULL DEFAULT NOW(),
                    PRIMARY KEY (id),
                    UNIQUE (user_id),
                    CONSTRAINT fk_2fa_user
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            $m->db->execute();
        } catch (\Exception) {}
    }

    // ── HOTP/TOTP core ────────────────────────────────────────────────────────

    private static function hotp(string $key, int $counter): string
    {
        $msg  = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $msg, $key, true);
        $off  = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$off])     & 0x7F) << 24) |
            ((ord($hash[$off + 1]) & 0xFF) << 16) |
            ((ord($hash[$off + 2]) & 0xFF) << 8)  |
             (ord($hash[$off + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);
        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    // ── Base32 ────────────────────────────────────────────────────────────────

    private static function b32Encode(string $bytes): string
    {
        $alpha = self::ALPHA;
        $bits  = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $pad  = (5 - (strlen($bits) % 5)) % 5;
        $bits .= str_repeat('0', $pad);
        $out  = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= $alpha[bindec($chunk)];
        }
        $padLen = (8 - (strlen($out) % 8)) % 8;
        return $out . str_repeat('=', $padLen);
    }

    private static function b32Decode(string $input): string
    {
        $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input));
        $alpha = self::ALPHA;
        $bits  = '';
        foreach (str_split($input) as $c) {
            $val = strpos($alpha, $c);
            if ($val === false) {
                continue;
            }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }
        return $out;
    }
}
