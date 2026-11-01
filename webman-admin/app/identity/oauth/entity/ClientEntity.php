<?php

declare(strict_types=1);

namespace app\identity\oauth\entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;

final class ClientEntity implements ClientEntityInterface
{
    public function __construct(
        private readonly string $identifier,
        private readonly string $name,
        private readonly array $redirectUris,
        private readonly bool $confidential,
        public readonly int $tenantId,
        public readonly int $databaseId,
        public readonly string $clientType,
        public readonly array $grants = [],
        public readonly array $allowedScopes = []
    ) {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRedirectUri(): array
    {
        return $this->redirectUris;
    }

    public function isConfidential(): bool
    {
        return $this->confidential;
    }
}
