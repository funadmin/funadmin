<?php

declare(strict_types=1);

return [
    'default' => getenv('DB_CONNECTION') ?: 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: 'webman_admin',
            'username' => getenv('DB_USERNAME') ?: 'webman_admin',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => getenv('DB_PREFIX') !== false ? getenv('DB_PREFIX') : 'fun_',
            'strict' => true,
            'options' => [PDO::ATTR_EMULATE_PREPARES => false],
            'pool' => [
                'max_connections' => 5,
                'min_connections' => 0,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 5432),
            'database' => getenv('DB_DATABASE') ?: 'webman_admin',
            'username' => getenv('DB_USERNAME') ?: 'webman_admin',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8',
            'prefix' => getenv('DB_PREFIX') !== false ? getenv('DB_PREFIX') : 'fun_',
            'search_path' => getenv('DB_SCHEMA') ?: 'public',
            'sslmode' => getenv('DB_SSLMODE') ?: 'prefer',
            'options' => [PDO::ATTR_EMULATE_PREPARES => false],
            'pool' => [
                'max_connections' => 5,
                'min_connections' => 0,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => getenv('DB_DATABASE') ?: ':memory:',
            'prefix' => getenv('DB_PREFIX') !== false ? getenv('DB_PREFIX') : 'fun_',
            'foreign_key_constraints' => true,
        ],
    ],
];
