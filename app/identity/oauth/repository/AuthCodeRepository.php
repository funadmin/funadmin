<?php

declare(strict_types=1);

namespace app\identity\oauth\repository;

use app\common\model\identity\OAuthAuthorizationCode;
use app\identity\oauth\entity\AuthCodeEntity;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

final class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function getNewAuthCode(): AuthCodeEntity
    {
        return new AuthCodeEntity();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        // 授权事务服务负责附带 tenant、PKCE 与 nonce 的原子持久化。
    }

    public function revokeAuthCode($codeId): void
    {
        (new OAuthAuthorizationCode())->where('code_hash', hash('sha256', (string) $codeId))->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function isAuthCodeRevoked($codeId): bool
    {
        $code = (new OAuthAuthorizationCode())->where('code_hash', hash('sha256', (string) $codeId))->find();
        return !$code || $code->revoked_at !== null || $code->consumed_at !== null || strtotime((string) $code->expires_at) <= time();
    }
}
