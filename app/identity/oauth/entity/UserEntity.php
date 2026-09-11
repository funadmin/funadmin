<?php

declare(strict_types=1);

namespace app\identity\oauth\entity;

use League\OAuth2\Server\Entities\UserEntityInterface;

final class UserEntity implements UserEntityInterface
{
    public function __construct(private readonly string $identifier)
    {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
