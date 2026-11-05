<?php

declare(strict_types=1);

// 队列进程独立启用，未部署 Redis 时不影响 HTTP 服务启动。
return filter_var(getenv('AI_QUEUE_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL) ? [
    'ai-agent' => [
        'handler' => Webman\RedisQueue\Process\Consumer::class,
        'count' => max(1, (int) (getenv('AI_QUEUE_WORKERS') ?: 1)),
        'constructor' => ['consumer_dir' => app_path('admin/ai/queue')],
    ],
] : [];
