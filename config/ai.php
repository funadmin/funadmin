<?php

declare(strict_types=1);

use think\facade\Env;

$csv = static function (string $value): array {
    return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));
};

return [
    'approval_mode' => (string) Env::get('AI_APPROVAL_MODE', 'request_approval'),
    'provider' => [
        'name' => (string) Env::get('AI_PROVIDER', ''),
        'api_key' => (string) Env::get('AI_API_KEY', ''),
        'base_url' => (string) Env::get('AI_BASE_URL', ''),
        'model' => (string) Env::get('AI_MODEL', ''),
        'connect_timeout' => (int) Env::get('AI_CONNECT_TIMEOUT', 5),
        'request_timeout' => (int) Env::get('AI_REQUEST_TIMEOUT', 60),
    ],
    'limits' => [
        'max_input_tokens' => (int) Env::get('AI_MAX_INPUT_TOKENS', 0),
        'max_output_tokens' => (int) Env::get('AI_MAX_OUTPUT_TOKENS', 0),
        'max_total_cost' => (float) Env::get('AI_MAX_TOTAL_COST', 0),
        'max_rounds' => (int) Env::get('AI_MAX_ROUNDS', 0),
    ],
    'sandbox' => [
        'image' => (string) Env::get('AI_SANDBOX_IMAGE', ''),
        'cpu' => (float) Env::get('AI_SANDBOX_CPU', 0),
        'memory_mb' => (int) Env::get('AI_SANDBOX_MEMORY_MB', 0),
        'disk_mb' => (int) Env::get('AI_SANDBOX_DISK_MB', 1024),
        'pids' => (int) Env::get('AI_SANDBOX_PIDS', 0),
        'task_timeout' => (int) Env::get('AI_SANDBOX_TASK_TIMEOUT', 300),
        'network_enabled' => Env::get('AI_SANDBOX_NETWORK_ENABLED', false) === true,
        'network_allowlist' => $csv((string) Env::get('AI_SANDBOX_NETWORK_ALLOWLIST', '')),
    ],
    'tools' => [
        'default' => 'deny',
        'allowlist' => $csv((string) Env::get('AI_TOOL_ALLOWLIST', '')),
    ],
    'storage' => [
        'private_path' => (string) Env::get('AI_PRIVATE_STORAGE_PATH', root_path() . 'runtime/ai/private'),
        'log_path' => (string) Env::get('AI_LOG_PATH', root_path() . 'runtime/ai/log'),
        'log_max_bytes' => (int) Env::get('AI_LOG_MAX_BYTES', 10485760),
        'log_retention_days' => (int) Env::get('AI_LOG_RETENTION_DAYS', 30),
    ],
];
