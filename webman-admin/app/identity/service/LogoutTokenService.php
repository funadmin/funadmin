<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\OidcSigningKey;
use app\common\service\identity\SigningKeyService;
use Firebase\JWT\JWT;
use RuntimeException;

/** 生成符合 Back-Channel Logout 规范且不包含 nonce 的 RS256 logout_token。 */
final class LogoutTokenService
{
    public function issue(int $tenantId, string $audience, string $sid, string $jti): string
    {
        $key = OidcSigningKey::forTenant($tenantId)->where('status', 'active')->find();
        if (!$key) throw new RuntimeException('OIDC active key 不可用');
        $claims = [
            'iss' => (new IssuerService((string) config('identity.issuer', '')))->getIssuer(),
            'aud' => $audience,
            'iat' => time(),
            'jti' => $jti,
            'events' => ['http://schemas.openid.net/event/backchannel-logout' => new \stdClass()],
            'sid' => $sid,
        ];
        $privateKey = (new SigningKeyService())->readPrivateKey((string) $key->getData('private_key_ref'));
        try {
            return JWT::encode($claims, $privateKey, 'RS256', (string) $key->kid);
        } finally {
            if (function_exists('sodium_memzero')) sodium_memzero($privateKey);
        }
    }
}
