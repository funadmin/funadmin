<?php

declare(strict_types=1);

return [
    'jwt_secret' => getenv('MEMBER_JWT_SECRET') ?: '',
    'refresh_jwt_secret' => getenv('MEMBER_REFRESH_JWT_SECRET') ?: '',
    'access_token_ttl' => (int) (getenv('MEMBER_ACCESS_TOKEN_TTL') ?: 7200),
    'refresh_token_ttl' => (int) (getenv('MEMBER_REFRESH_TOKEN_TTL') ?: 604800),
    // Existing /api/v3/auth opaque-member protocol, independent of online market accounts.
    'opaque_access_token_ttl' => (int) (getenv('MEMBER_OPAQUE_ACCESS_TOKEN_TTL') ?: 7200),
    'opaque_refresh_token_ttl' => (int) (getenv('MEMBER_OPAQUE_REFRESH_TOKEN_TTL') ?: 2592000),
    'issuer' => getenv('MEMBER_JWT_ISSUER') ?: 'funadmin.com',
    'audience' => getenv('MEMBER_JWT_AUDIENCE') ?: 'funadmin',
];
