<?php

declare(strict_types=1);

return [
    'token_ttl' => (int) (getenv('AUTH_TOKEN_TTL') ?: 7200),
    // 授权 worker 缓存兜底 TTL（秒）：即使未观察到授权版本变化，一代缓存最多存活这么久；<=0 关闭 worker 缓存。
    'authz_cache_ttl' => (float) (getenv('AUTH_AUTHZ_CACHE_TTL') !== false && getenv('AUTH_AUTHZ_CACHE_TTL') !== '' ? getenv('AUTH_AUTHZ_CACHE_TTL') : 60),
];
