<?php

declare(strict_types=1);

namespace App\CMS;

use Core\Model;

/**
 * Brute-force protection for the admin login page.
 *
 * Uses a `login_attempts` table (auto-created on first use).
 * Blocks after MAX_ATTEMPTS within WINDOW_SECONDS.
 */
class LoginRateLimiter
{
    private const MAX_ATTEMPTS  = 5;
    private const WINDOW_SECONDS = 900; // 15 min

    private string $ip;
    private string $email;

    public function __construct(string $ip, string $email)
    {
        $this->ip    = $ip;
        $this->email = strtolower(trim($email));
        $this->ensureTable();
    }

    public static function make(string $email): self
    {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
        // Take first IP when behind a proxy chain
        $ip = trim(explode(',', $ip)[0]);
        return new self($ip, $email);
    }

    /** Returns true if this IP+email is blocked. */
    public function isBlocked(): bool
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $count  = (int) ((new Model('login_attempts'))
            ->select('COUNT(*) AS n')
            ->where('ip_address', $this->ip)
            ->where('email', $this->email)
            ->whereRaw('attempted_at > :c', [':c' => $cutoff])
            ->get(1)['n'] ?? 0);

        return $count >= self::MAX_ATTEMPTS;
    }

    /** Record a failed attempt. */
    public function recordFailure(): void
    {
        (new Model('login_attempts'))->withoutTimestamps()->save([
            'ip_address'  => $this->ip,
            'email'       => $this->email,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);

        // Prune old rows to keep the table small
        $prune = date('Y-m-d H:i:s', time() - (self::WINDOW_SECONDS * 4));
        (new Model('login_attempts'))
            ->whereRaw('attempted_at < :p', [':p' => $prune])
            ->delete();
    }

    /** Clear attempts for this IP+email after a successful login. */
    public function clearAttempts(): void
    {
        (new Model('login_attempts'))
            ->where('ip_address', $this->ip)
            ->where('email', $this->email)
            ->delete();
    }

    /** Returns seconds until the block expires, or 0 if not blocked. */
    public function secondsUntilUnblock(): int
    {
        $cutoff  = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $oldest  = (new Model('login_attempts'))
            ->select('attempted_at')
            ->where('ip_address', $this->ip)
            ->where('email', $this->email)
            ->whereRaw('attempted_at > :c', [':c' => $cutoff])
            ->orderBy('attempted_at', 'ASC')
            ->get(1);

        if (!$oldest) {
            return 0;
        }

        $unblockAt = strtotime($oldest['attempted_at']) + self::WINDOW_SECONDS;
        return max(0, $unblockAt - time());
    }

    private function ensureTable(): void
    {
        try {
            (new Model('login_attempts'))->get(1);
        } catch (\Throwable) {
            $this->createTable();
        }
    }

    private function createTable(): void
    {
        $conn = (new Model('login_attempts'))->db;
        $conn->query('
            CREATE TABLE IF NOT EXISTS login_attempts (
                id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
                ip_address   VARCHAR(45)  NOT NULL,
                email        VARCHAR(255) NOT NULL,
                attempted_at TIMESTAMP    NOT NULL DEFAULT NOW()
            )
        ');
        $conn->execute();
        $conn->query('
            CREATE INDEX IF NOT EXISTS idx_login_attempts_lookup
            ON login_attempts (ip_address, email, attempted_at)
        ');
        $conn->execute();
    }
}
