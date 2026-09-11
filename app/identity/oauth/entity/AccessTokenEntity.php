<?php

declare(strict_types=1);

namespace app\identity\oauth\entity;

use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * League 生命周期实体的字符串值保持 opaque，不生成 JWT access token。
 */
final class AccessTokenEntity implements AccessTokenEntityInterface
{
    use EntityTrait;
    use TokenEntityTrait;

    public function setPrivateKey(CryptKey $privateKey): void
    {
        // Opaque access token 不使用签名私钥；方法仅满足公开接口契约。
    }

    public function __toString(): string
    {
        return (string) $this->getIdentifier();
    }
}
