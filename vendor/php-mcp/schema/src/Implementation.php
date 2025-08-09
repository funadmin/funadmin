<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * Describes the name and version of an MCP implementation.
 */
class Implementation implements JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly string $version
    ) {
    }

    public static function make(string $name, string $version): static
    {
        return new static($name, $version);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
        ];
    }

    public static function fromArray(array $data): static
    {
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException("Invalid or missing 'name' in Implementation data.");
        }
        if (empty($data['version']) || !is_string($data['version'])) {
            throw new \InvalidArgumentException("Invalid or missing 'version' in Implementation data.");
        }
        return new static($data['name'], $data['version']);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
