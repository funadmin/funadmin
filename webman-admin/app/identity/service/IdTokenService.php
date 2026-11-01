<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\IdentityUser;
use app\common\model\identity\OidcSigningKey;
use app\common\service\identity\SigningKeyService;
use Firebase\JWT\JWT;
use RuntimeException;

final class IdTokenService
{
    public function issue(int $tenantId, int $userId, int $clientId, string $audience, array $scopes, int $authTime, ?string $nonce, string $sessionId): string
    {
        $key = OidcSigningKey::forTenant($tenantId)->where('status', 'active')->find();
        $user = IdentityUser::forTenant($tenantId)->where('id', $userId)->where('status', 1)->find();
        if (!$key || !$user) throw new RuntimeException('OIDC active key 或用户不可用');
        $now = time();
        $claims = ['iss' => (new IssuerService((string) config('identity.issuer', '')))->getIssuer(), 'aud' => $audience, 'iat' => $now, 'exp' => $now + 300, 'auth_time' => $authTime, 'sid' => $sessionId]
            + (new OidcClaimService())->claims($tenantId, $userId, $clientId, $scopes);
        if ($nonce !== null && $nonce !== '') $claims['nonce'] = $nonce;
        $privateKey = (new SigningKeyService())->readPrivateKey((string) $key->getData('private_key_ref'));
        try {
            return JWT::encode($claims, $privateKey, 'RS256', (string) $key->kid);
        } finally {
            if (function_exists('sodium_memzero')) sodium_memzero($privateKey);
        }
    }
}
