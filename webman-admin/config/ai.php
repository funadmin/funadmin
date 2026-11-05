<?php

declare(strict_types=1);

$private = getenv('AI_PRIVATE_PATH') ?: base_path('runtime/private/ai');
return [
    'encryption_key' => getenv('OAUTH_ENCRYPTION_KEY') ?: '',
    'storage' => [
        'private_path' => $private,
        'log_path' => $private . '/logs',
        'log_max_bytes' => 4096,
    ],
    'provider' => [
        'name' => getenv('AI_PROVIDER') ?: '',
        'provider' => getenv('AI_PROVIDER') ?: '',
        'protocol' => getenv('AI_PROTOCOL') ?: 'openai-chat',
        'base_url' => getenv('AI_BASE_URL') ?: '',
        'api_key' => getenv('AI_API_KEY') ?: '',
        'model' => getenv('AI_MODEL') ?: '',
        'connect_timeout' => 5,
        'request_timeout' => 60,
        'max_retries' => 2,
    ],
    'sandbox' => [
        'image' => getenv('AI_SANDBOX_IMAGE') ?: '',
        'cpu' => 0.5,
        'memory_mb' => 256,
        'pids' => 64,
        'retention_seconds' => 86400,
        'heartbeat_timeout_seconds' => 300,
    ],
    // SSE 票据的 HMAC 密钥：优先使用 AI_STREAM_TICKET_SECRET；未配置时由数据库密码等本机配置派生，
    // 保证 AI 会话接口可用。正式部署请显式设置至少 32 字节的随机值。
    'stream' => [
        'ticket_secret' => getenv('AI_STREAM_TICKET_SECRET') ?: hash('sha256', 'funadmin-ai-stream|' . (getenv('OAUTH_ENCRYPTION_KEY') ?: '') . '|' . (getenv('DB_PASSWORD') ?: '') . '|' . base_path()),
        'ticket_ttl' => (int) (getenv('AI_STREAM_TICKET_TTL') ?: 60),
    ],
    'tools' => ['allowlist' => array_values(array_filter(array_map('trim', explode(',', getenv('AI_TOOLS_ALLOWLIST') ?: ''))))],
];
