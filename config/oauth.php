<?php

declare(strict_types=1);

return [
    // Phase 0 仅冻结协议基线，不配置密钥材料或启用授权能力。
    'authorization_code_ttl' => 'PT10M',
    'access_token_ttl' => 'PT1H',
    'refresh_token_ttl' => 'P1M',
    'pkce' => [
        'required_for_public_clients' => true,
        'allowed_methods' => ['S256'],
    ],
    'private_key_path' => env('oauth.private_key_path', ''),
    'encryption_key' => env('oauth.encryption_key', ''),
];