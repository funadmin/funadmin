<?php

declare(strict_types=1);

/**
 * 跨域（CORS）只开放给会员/开放 API（默认 /api/*，Bearer 令牌鉴权，不依赖 Cookie）。
 *
 * - CORS_ALLOWED_ORIGINS：逗号分隔的精确来源（scheme://host[:port]，不含路径），为空表示不允许任何跨域来源；不支持通配符。
 * - 永不发送 Access-Control-Allow-Credentials；后台 /admin 路由即使写进 paths 也永不返回 CORS 头。
 * - 后台必须与前端同源部署（反向代理到同一域名），不能依赖跨域 Cookie。
 */
$origins = [];
foreach (explode(',', (string) getenv('CORS_ALLOWED_ORIGINS')) as $origin) {
    $origin = strtolower(rtrim(trim($origin), '/'));
    if (preg_match('#^https?://[a-z0-9.-]+(:\d{1,5})?$#', $origin) === 1 && !in_array($origin, $origins, true)) {
        $origins[] = $origin;
    }
}

return [
    'allowed_origins' => $origins,
    'paths' => ['/api/'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Locale', 'X-Requested-With'],
    'max_age' => 600,
];
