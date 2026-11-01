<?php

declare(strict_types=1);

$private = getenv('AI_PRIVATE_PATH') ?: base_path('runtime/private/ai');
return [
    'storage' => [
        'private_path' => $private,
        'log_path' => $private . '/logs',
        'log_max_bytes' => 4096,
    ],
    'provider' => [
        'name' => getenv('AI_PROVIDER') ?: '',
        'provider' => getenv('AI_PROVIDER') ?: '',
        'protocol' => getenv('AI_PROTOCOL') ?: 'openai_chat_completions',
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
    'tools' => ['allowlist' => array_values(array_filter(array_map('trim', explode(',', getenv('AI_TOOLS_ALLOWLIST') ?: ''))))],
];
