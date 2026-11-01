<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\OidcSigningKey;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Throwable;

/** 校验本 OP 签发的 id_token_hint；允许过期 token 仅作为已签名会话提示。 */
final class IdTokenHintService
{
    public function verify(int $tenantId, string $jwt): ?array
    {
        if ($jwt === '' || substr_count($jwt, '.') !== 2) return null;
        try {
            $parts = explode('.', $jwt);
            $untrusted = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($untrusted) || !isset($untrusted['exp'])) return null;
            $keys = [];
            foreach (OidcSigningKey::forTenant($tenantId)->whereIn('status', ['active', 'retiring'])->select() as $key) {
                $jwk = $key->public_jwk;
                $jwk = is_array($jwk) ? $jwk : json_decode((string) $jwk, true);
                if (is_array($jwk)) $keys[] = $jwk;
            }
            if ($keys === []) return null;
            $previous = JWT::$timestamp;
            JWT::$timestamp = min(time(), max(1, (int) $untrusted['exp'] - 1));
            try {
                $claims = (array) JWT::decode($jwt, JWK::parseKeySet(['keys' => $keys]));
            } finally {
                JWT::$timestamp = $previous;
            }
            $issuer = (new IssuerService((string) config('identity.issuer', '')))->getIssuer();
            $audience = $claims['aud'] ?? '';
            if (($claims['iss'] ?? '') !== $issuer || !is_string($audience) || $audience === '' || empty($claims['sid'])) return null;
            if ((int) ($claims['iat'] ?? 0) > time() + 60 || (int) $claims['exp'] < time() - 2592000) return null;
            return $claims;
        } catch (Throwable) {
            return null;
        }
    }
}
