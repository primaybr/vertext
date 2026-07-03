<?php

declare(strict_types=1);

namespace App\CMS;

use Core\Model;

/**
 * Tracks admin login sessions in the user_sessions table so users can see
 * their active devices and revoke them individually or all at once.
 *
 * A session is identified by sha256(session_id()). Rows carry a revoked_at
 * timestamp instead of being deleted on revoke: the victim session discovers
 * the revocation on its next request (BaseController -> validate()) and is
 * logged out, at which point the row is removed.
 */
class SessionTracker
{
    private const TOUCH_INTERVAL = 60; // seconds between last_active writes

    private static bool $tableChecked = false;

    private static function ensureTable(): void
    {
        if (self::$tableChecked) return;
        self::$tableChecked = true;

        try {
            (new Model('user_sessions'))->withoutTimestamps()->get(1);
        } catch (\Throwable) {
            try {
                $conn = (new Model('user_sessions'))->db;
                $conn->query('
                    CREATE TABLE IF NOT EXISTS user_sessions (
                        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                        user_id UUID NOT NULL,
                        token_hash VARCHAR(64) UNIQUE NOT NULL,
                        ip VARCHAR(45),
                        user_agent VARCHAR(255),
                        last_active TIMESTAMP NOT NULL DEFAULT NOW(),
                        revoked_at TIMESTAMP,
                        created_at TIMESTAMP NOT NULL DEFAULT NOW()
                    )
                ');
                $conn->execute();
                $conn->query('CREATE INDEX IF NOT EXISTS idx_user_sessions_user ON user_sessions (user_id)');
                $conn->execute();
            } catch (\Throwable $e) {
                // Table creation failure is non-fatal; feature degrades gracefully.
            }
        }
    }

    /** Hash of the current PHP session id */
    public static function currentTokenHash(): string
    {
        return hash('sha256', session_id() ?: '');
    }

    /** Record (or refresh) the current session for a user - called on login */
    public static function record(string $userId): void
    {
        self::ensureTable();

        try {
            $hash = self::currentTokenHash();
            $model = (new Model('user_sessions'))->withoutTimestamps();

            $existing = $model->where('token_hash', $hash)->get(1);
            $data = [
                'user_id'     => $userId,
                'ip'          => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                'user_agent'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'last_active' => date('Y-m-d H:i:s'),
                'revoked_at'  => null,
            ];

            if ($existing) {
                (new Model('user_sessions'))->withoutTimestamps()->where('token_hash', $hash)->update($data);
            } else {
                $data['token_hash'] = $hash;
                (new Model('user_sessions'))->withoutTimestamps()->save($data);
            }
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }

    /**
     * Validate the current session against the tracker.
     * Returns false when this session has been revoked (caller should log out).
     * Missing rows are self-healed (sessions created before this feature).
     */
    public static function validate(string $userId): bool
    {
        self::ensureTable();

        try {
            $hash = self::currentTokenHash();
            $row = (new Model('user_sessions'))->withoutTimestamps()
                ->where('token_hash', $hash)
                ->get(1);

            if (!$row) {
                // Pre-upgrade session: register it now
                self::record($userId);
                return true;
            }

            if (!empty($row['revoked_at'])) {
                (new Model('user_sessions'))->withoutTimestamps()->where('token_hash', $hash)->delete();
                return false;
            }

            // Throttled last_active touch
            $last = strtotime((string) ($row['last_active'] ?? '')) ?: 0;
            if (time() - $last > self::TOUCH_INTERVAL) {
                (new Model('user_sessions'))->withoutTimestamps()
                    ->where('token_hash', $hash)
                    ->update(['last_active' => date('Y-m-d H:i:s')]);
            }

            return true;
        } catch (\Throwable $e) {
            return true; // fail open - tracker must never lock admins out
        }
    }

    /** Remove the current session's row - called on logout */
    public static function forget(): void
    {
        self::ensureTable();

        try {
            (new Model('user_sessions'))->withoutTimestamps()
                ->where('token_hash', self::currentTokenHash())
                ->delete();
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }

    /** All sessions for a user, newest activity first. Flags the current one. */
    public static function listForUser(string $userId): array
    {
        self::ensureTable();

        try {
            $rows = (new Model('user_sessions'))->withoutTimestamps()
                ->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->orderBy('last_active', 'DESC')
                ->get() ?: [];

            $current = self::currentTokenHash();
            foreach ($rows as &$row) {
                $row['is_current'] = ($row['token_hash'] === $current);
                $row['device']     = self::describeAgent((string) ($row['user_agent'] ?? ''));
            }
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Count active (non-revoked) sessions per user - for the Users admin table */
    public static function countByUser(): array
    {
        self::ensureTable();

        try {
            $rows = (new Model('user_sessions'))->withoutTimestamps()
                ->select('user_id, COUNT(*) AS cnt')
                ->whereNull('revoked_at')
                ->groupBy('user_id')
                ->get() ?: [];
            $map = [];
            foreach ($rows as $row) {
                $map[(string) $row['user_id']] = (int) $row['cnt'];
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Revoke a single session by row id. When $userId is given, the row must
     * belong to that user (self-service path); admins pass null.
     * Returns true when a row was marked revoked.
     */
    public static function revoke(string $sessionRowId, ?string $userId = null): bool
    {
        self::ensureTable();

        try {
            $model = (new Model('user_sessions'))->withoutTimestamps()->where('id', $sessionRowId);
            if ($userId !== null) {
                $model->where('user_id', $userId);
            }
            $row = $model->get(1);
            if (!$row) return false;

            (new Model('user_sessions'))->withoutTimestamps()
                ->where('id', $sessionRowId)
                ->update(['revoked_at' => date('Y-m-d H:i:s')]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Revoke every session for a user except (optionally) the current one.
     * Returns the number of sessions revoked.
     */
    public static function revokeAllForUser(string $userId, bool $keepCurrent = true): int
    {
        self::ensureTable();

        try {
            $model = (new Model('user_sessions'))->withoutTimestamps()
                ->where('user_id', $userId)
                ->whereNull('revoked_at');
            if ($keepCurrent) {
                $model->where('token_hash', self::currentTokenHash(), '!=');
            }
            $rows = $model->get() ?: [];
            foreach ($rows as $row) {
                (new Model('user_sessions'))->withoutTimestamps()
                    ->where('id', (string) $row['id'])
                    ->update(['revoked_at' => date('Y-m-d H:i:s')]);
            }
            return count($rows);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Human-readable browser/OS summary from a user-agent string */
    public static function describeAgent(string $ua): string
    {
        $browser = 'Unknown browser';
        if (stripos($ua, 'Edg/') !== false)          $browser = 'Edge';
        elseif (stripos($ua, 'OPR/') !== false)      $browser = 'Opera';
        elseif (stripos($ua, 'Chrome/') !== false)   $browser = 'Chrome';
        elseif (stripos($ua, 'Firefox/') !== false)  $browser = 'Firefox';
        elseif (stripos($ua, 'Safari/') !== false)   $browser = 'Safari';

        $os = '';
        if (stripos($ua, 'Windows') !== false)       $os = 'Windows';
        elseif (stripos($ua, 'Android') !== false)   $os = 'Android';
        elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) $os = 'iOS';
        elseif (stripos($ua, 'Mac OS') !== false)    $os = 'macOS';
        elseif (stripos($ua, 'Linux') !== false)     $os = 'Linux';

        return $os !== '' ? "{$browser} on {$os}" : $browser;
    }
}
