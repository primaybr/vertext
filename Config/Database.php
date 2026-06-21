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
