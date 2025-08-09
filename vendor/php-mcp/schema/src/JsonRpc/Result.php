<?php

declare(strict_types=1);

namespace PhpMcp\Schema\JsonRpc;

use JsonSerializable;

/**
 * Base class for all specific MCP result objects (the value of the 'result' field).
 */
abstract class Result implements JsonSerializable
{
    /**
     * Converts the result object to its array representation.
     *
     * It should include the _meta field if present.
     */
    abstract public function toArray(): array;

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
