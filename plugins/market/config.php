<?php

declare(strict_types=1);

return [
    'public_url' => [
        'title' => '市场对外地址',
        'type' => 'text',
        'value' => '',
        'tip' => '客户端 PLUGIN_MARKETPLACE_URL 应填写的地址，例如 https://market.example.com/market。留空时按当前请求域名推导；生产环境必须是 HTTPS 公网地址，否则客户端会拒绝下载。',
    ],
    'access_token_ttl' => [
        'title' => '访问令牌有效期（秒）',
        'type' => 'number',
        'value' => 7200,
    ],
    'refresh_token_ttl' => [
        'title' => '刷新令牌有效期（秒）',
        'type' => 'number',
        'value' => 2592000,
    ],
    'download_ttl' => [
        'title' => '下载链接有效期（秒）',
        'type' => 'number',
        'value' => 600,
    ],
];
