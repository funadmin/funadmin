<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Result;

use PhpMcp\Schema\JsonRpc\Result;

/**
 * A generic empty result that indicates success but carries no data.
 */
class EmptyResult extends Result
{
    /**
     * Create a new EmptyResult.
     */
    public function __construct() {}

    /**
     * Convert the result to an array.
     */
    public function toArray(): array
    {
        return []; // Empty result object
    }

    public static function make(): static
    {
        return new static();
    }

    public static function fromArray(array $data): static
    {
        return new static();
    }

    public function jsonSerialize(): mixed
    {
        return new \stdClass();
    }
}
