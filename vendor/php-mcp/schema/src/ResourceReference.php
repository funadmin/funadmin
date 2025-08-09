<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * A reference to a resource or resource template definition.
 */
class ResourceReference implements JsonSerializable
{
    public string $type = 'ref/resource';

    /**
     * @param  string $uri  The URI or URI template of the resource.
     */
    public function __construct(
        public readonly string $uri,
    ) {}

    public static function make(string $uri): static
    {
        return new static($uri);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'uri' => $this->uri,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
