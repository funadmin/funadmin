<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Result;

use PhpMcp\Schema\Root;
use PhpMcp\Schema\JsonRpc\Result;

/**
 * The client's response to a roots/list request from the server.
 * This result contains an array of Root objects, each representing a root directory
 * or file that the server can operate on.
 */
class ListRootsResult extends Result
{
    /**
     * @param Root[] $roots An array of root URIs.
     */
    public function __construct(
        public readonly array $roots,
        public readonly ?array $_meta = null
    ) {
    }

    /**
     * Create a new ListRootsResult.
     *
     * @param  Root[]  $roots  The list of root URIs.
     * @param  array|null  $_meta  Optional metadata to include in the result.
     */
    public static function make(array $roots, ?array $_meta = null): static
    {
        return new static($roots, $_meta);
    }

    public function toArray(): array
    {
        $result = [
            'roots' => $this->roots,
        ];

        if ($this->_meta !== null) {
            $result['_meta'] = $this->_meta;
        }

        return $result;
    }
}
