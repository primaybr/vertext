<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use Core\Controller;
use Core\Model;

/**
 * Base controller for the public JSON API (v1).
 *
 * - Envelope: {"data": ..., "meta": {current_page, per_page, total, last_page}}
 * - Auth: optional Bearer key (api_keys table, SHA-256 of the plaintext key).
 *   GET endpoints are public; a valid key raises the rate limit.
 * - Rate limit: fixed 60-second window per key (or per IP when anonymous),
 *   tracked in the self-creating api_rate_windows table. 429 + Retry-After.
 */
abstract class ApiController extends Controller
{
    protected const RATE_LIMIT_KEYED = 100; // requests/minute with a valid key
    protected const RATE_LIMIT_ANON  = 30;  // requests/minute per IP without

    protected ?array $apiKey = null;

    public function __construct()
    {
        parent::__construct();
        self::ensureTables();
        $this->apiKey = $this->resolveApiKey();
        $this->enforceRateLimit();
    }

    public static function ensureTables(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            $db = (new Model('api_keys'))->db;
            foreach ([
                "CREATE TABLE IF NOT EXISTS api_keys (
                    id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
                    name         VARCHAR(150) NOT NULL,
                    key_hash     VARCHAR(64)  UNIQUE NOT NULL,
                    user_id      UUID,
                    last_used_at TIMESTAMP,
                    revoked_at   TIMESTAMP,
                    created_at   TIMESTAMP    DEFAULT NOW(),
                    updated_at   TIMESTAMP    DEFAULT NOW(),
                    deleted_at   TIMESTAMP,
                    created_by   UUID,
                    updated_by   UUID,
                    deleted_by   UUID
                )",
                "CREATE TABLE IF NOT EXISTS api_rate_windows (
                    id           UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
                    bucket       VARCHAR(80) NOT NULL,
                    window_start TIMESTAMP   NOT NULL,
                    count        INT         NOT NULL DEFAULT 1,
                    UNIQUE (bucket, window_start)
                )",
            ] as $ddl) {
                $db->query($ddl);
                $db->execute();
            }
        } catch (\Throwable $e) {
            // Degrades to unauthenticated + unlimited (endpoints still work)
        }
    }

    /** Resolve Authorization: Bearer <key> to an api_keys row. */
    private function resolveApiKey(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+([A-Za-z0-9_\-\.]{20,128})$/', trim((string) $header), $m)) {
            return null;
        }

        try {
            $key = (new Model('api_keys'))
                ->where('key_hash', hash('sha256', $m[1]))
                ->whereNull('revoked_at')
                ->whereNull('deleted_at')
                ->get(1);

            if (!$key) {
                $this->fail(401, 'Invalid or revoked API key.');
            }

            // Throttled last_used_at update (once/minute is plenty)
            $lastUsed = strtotime((string) ($key['last_used_at'] ?? '')) ?: 0;
            if (time() - $lastUsed > 60) {
                (new Model('api_keys'))->where('id', (string) $key['id'])
                    ->update(['last_used_at' => date('Y-m-d H:i:s')]);
            }

            return $key;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Fixed-window rate limiting; sends 429 + Retry-After when exceeded. */
    private function enforceRateLimit(): void
    {
        $limit  = $this->apiKey ? self::RATE_LIMIT_KEYED : self::RATE_LIMIT_ANON;
        $ip     = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''))[0]);
        $bucket = $this->apiKey ? ('key:' . $this->apiKey['id']) : ('ip:' . hash('sha256', $ip));
        $window = date('Y-m-d H:i:00');

        try {
            // DML must go through Model (raw Connection DML does not commit in
            // this framework - Model wraps writes in begin/commit).
            $bucket = substr($bucket, 0, 80);
            $row = (new Model('api_rate_windows'))->withoutTimestamps()
                ->where('bucket', $bucket)
                ->where('window_start', $window)
                ->get(1);

            if ($row) {
                $count = (int) $row['count'] + 1;
                (new Model('api_rate_windows'))->withoutTimestamps()
                    ->where('id', (string) $row['id'])
                    ->update(['count' => $count]);
            } else {
                $count = 1;
                (new Model('api_rate_windows'))->withoutTimestamps()->ignoreDuplicate()->save([
                    'bucket'       => $bucket,
                    'window_start' => $window,
                    'count'        => 1,
                ]);
                // Opportunistic prune of stale windows (older than 5 minutes)
                (new Model('api_rate_windows'))->withoutTimestamps()
                    ->whereRaw('window_start < :cutoff', [':cutoff' => date('Y-m-d H:i:s', time() - 300)])
                    ->delete();
            }

            if ($count > $limit) {
                header('Retry-After: ' . (60 - (int) date('s')));
                $this->fail(429, 'Rate limit exceeded. Try again shortly.');
            }
        } catch (\Throwable $e) {
            // Rate limiter must never take the API down
        }
    }

    // -- Response helpers -------------------------------------------------------

    /** @return array{0:int,1:int} [page, perPage] */
    protected function pageParams(int $defaultPerPage = 10, int $maxPerPage = 50): array
    {
        // Input::get() returns '' for missing params, so ?: (not ??) for defaults
        $page    = max(1, (int) (($this->input->get('page') ?: 1)));
        $perPage = (int) (($this->input->get('per_page') ?: $defaultPerPage));
        $perPage = max(1, min($maxPerPage, $perPage));
        return [$page, $perPage];
    }

    protected function respond(array $data, ?array $meta = null, int $status = 200): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Vertext-Api-Version: 1');
        http_response_code($status);
        $body = ['data' => $data];
        if ($meta !== null) {
            $body['meta'] = $meta;
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function paginated(array $items, int $page, int $perPage, int $total): never
    {
        $this->respond($items, [
            'current_page' => $page,
            'per_page'     => $perPage,
            'total'        => $total,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    protected function fail(int $status, string $message): never
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode(['error' => ['status' => $status, 'message' => $message]]);
        exit;
    }
}
