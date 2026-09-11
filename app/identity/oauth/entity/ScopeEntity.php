<?php

declare(strict_types=1);

namespace app\identity\oauth\entity;

use JsonSerializable;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

final class ScopeEntity implements ScopeEntityInterface, JsonSerializable
{
    public function __construct(private readonly string $identifier, public readonly int $databaseId = 0)
    {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function jsonSerialize(): string
    {
        return $this->identifier;
    }
}
