<?php

declare(strict_types=1);

namespace Config;

class Database
{
    public array $connections = [
        'default' => [
            'driver'    => 'pgsql',
            'host'      => 'localhost',
            'port'      => '5432',
            'database'  => '',
            'username'  => '',
            'password'  => '',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ]
    ];

    public array $cache = [
        'enabled'                    => true,
        'lifetime'                   => 3600,
        'directory'                  => 'database',
        'ignore_on_calc_found_rows'  => true,
        'exclude_tables'             => ['sessions', 'cache', 'migrations', 'audit_logs'],
        'cacheable_queries'          => ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'],
    ];

    public function __construct()
    {
        // Env vars take priority (Kubernetes/container deployments - credentials
        // delivered via a ConfigMap/Secret-backed env, no setup wizard involved).
        // Falls back to the setup-wizard-written Storage/db.php override otherwise,
        // so local/traditional installs are unaffected.
        $dbHost = getenv('DB_HOST');
        if ($dbHost !== false && $dbHost !== '') {
            $this->connections['default'] = array_merge($this->connections['default'], [
                'host'     => $dbHost,
                'port'     => getenv('DB_PORT') ?: $this->connections['default']['port'],
                'database' => getenv('DB_DATABASE') ?: $this->connections['default']['database'],
                'username' => getenv('DB_USERNAME') ?: $this->connections['default']['username'],
                'password' => getenv('DB_PASSWORD') ?: $this->connections['default']['password'],
            ]);
            return;
        }

        // Override with storage config written by setup wizard
        $storageFile = defined('ROOT') ? ROOT . 'Storage' . DIRECTORY_SEPARATOR . 'db.php' : '';
        if ($storageFile && file_exists($storageFile)) {
            $override = require $storageFile;
            if (is_array($override)) {
                $this->connections['default'] = array_merge($this->connections['default'], $override);
            }
        }
    }

    public function getConnectionConfig(string $connection = 'default'): array
    {
        return $this->connections[$connection] ?? $this->connections['default'];
    }

    public function getCacheConfig(): array
    {
        return $this->cache;
    }
}
