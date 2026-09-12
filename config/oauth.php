<?php

declare(strict_types=1);

use app\identity\service\NativeIdentitySessionResolver;

return [
    // Phase 4 协议核心使用短期 code、opaque token 与全客户端 PKCE S256。
    'authorization_code_ttl' => 'PT5M',
    'authorization_transaction_ttl' => 'PT10M',
    'access_token_ttl' => 'PT1H',
    'refresh_token_ttl' => 'P30D',
    'pkce' => [
        'required_for_all_clients' => true,
        'allowed_methods' => ['S256'],
    ],
    'private_key_path' => env('oauth.private_key_path', ''),
    'encryption_key' => env('oauth.encryption_key', ''),
    // pairwise subject pepper 只能通过环境变量或 secret provider 注入，禁止持久化或记录。
    'subject_pepper' => env('oidc.subject_pepper', ''),
    'identity_session_resolver' => env('oauth.identity_session_resolver', NativeIdentitySessionResolver::class),
];