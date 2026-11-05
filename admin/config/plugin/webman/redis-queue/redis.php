<?php

declare(strict_types=1);

return [
    'ai-agent' => [
        'host' => getenv('AI_QUEUE_REDIS_URL') ?: 'redis://127.0.0.1:6379',
        'options' => [
            'auth' => getenv('AI_QUEUE_REDIS_PASSWORD') ?: null,
            'db' => (int) (getenv('AI_QUEUE_REDIS_DATABASE') ?: 0),
            'prefix' => getenv('AI_QUEUE_REDIS_PREFIX') ?: 'funadmin-ai:',
            'max_attempts' => 5,
            'retry_seconds' => 5,
        ],
        'pool' => ['max_connections' => 5, 'min_connections' => 0, 'wait_timeout' => 3, 'idle_timeout' => 60, 'heartbeat_interval' => 50],
    ],
];
