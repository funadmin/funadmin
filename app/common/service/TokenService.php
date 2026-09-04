<?php

declare(strict_types=1);

namespace app\common\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

class TokenService extends AbstractService
{
    public const TYPE_ACCESS = 'access';
    public const TYPE_REFRESH = 'refresh';

    /**
     * 生成 JWT 令牌
     *
     * @param array $payload
     * @param int $ttl 令牌有效期（秒）
     * @param string $type 令牌类型 ('access' 或 'refresh')
     * @return string
     */
    public function build(array $payload, string $type = self::TYPE_ACCESS): string
    {
        $this->assertType($type);
        $issuedAt = time();
        $tokenPayload = [
            'iat' => $issuedAt,
            'exp' => $issuedAt + $this->ttl($type),
            'data' => $payload,
            'iss' => (string) config('api.issuer', 'funadmin.com'),
            'aud' => (string) config('api.audience', 'funadmin'),
            'type' => $type,
        ];

        return JWT::encode($tokenPayload, $this->secret($type), 'HS256');
    }

    /**
     * 验证 JWT 令牌
     *
     * @param string $token
     * @param string $type 令牌类型 ('access' 或 'refresh')
     * @return array|false
     */
    public function validateToken(string $token, string $type = self::TYPE_ACCESS): array|false
    {
        try {
            $this->assertType($type);
            JWT::$leeway = 30;
            $decoded = JWT::decode($token, new Key($this->secret($type), 'HS256'));
            if (($decoded->type ?? null) !== $type
                || ($decoded->iss ?? null) !== (string) config('api.issuer', 'funadmin.com')
                || ($decoded->aud ?? null) !== (string) config('api.audience', 'funadmin')
                || !isset($decoded->data)) {
                return false;
            }

            return (array) $decoded->data;
        } catch (Throwable) {
            return false;
        }
    }

    private function secret(string $type): string
    {
        $configKey = $type === self::TYPE_ACCESS ? 'jwt_secret' : 'refresh_jwt_secret';
        $secret = (string) config('api.' . $configKey, '');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT 密钥长度不能少于 32 个字符');
        }

        return $secret;
    }

    private function ttl(string $type): int
    {
        $configKey = $type === self::TYPE_ACCESS ? 'access_token_ttl' : 'refresh_token_ttl';
        $ttl = (int) config('api.' . $configKey, 0);
        if ($ttl <= 0) {
            throw new \RuntimeException('Token 有效期配置无效');
        }

        return $ttl;
    }

    private function assertType(string $type): void
    {
        if (!in_array($type, [self::TYPE_ACCESS, self::TYPE_REFRESH], true)) {
            throw new \InvalidArgumentException('不支持的 Token 类型');
        }
    }
}
