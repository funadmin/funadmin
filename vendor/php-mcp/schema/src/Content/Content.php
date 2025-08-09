<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Content;

use JsonSerializable;

/**
 * Base class for all content types in MCP.
 */
abstract class Content implements JsonSerializable
{
    public function __construct(
        public readonly string $type,
    ) {
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
