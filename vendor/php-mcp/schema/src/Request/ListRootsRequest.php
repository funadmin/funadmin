<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Request;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Request;

/**
 * Sent from the server to request a list of root URIs from the client. Roots allow
 * servers to ask for specific directories or files to operate on. A common example
 * for roots is providing a set of repositories or directories a server should operate
 * on.
 *
 * This request is typically used when the server needs to understand the file system
 * structure or access specific locations that the client has permission to read from.
 */
class ListRootsRequest extends Request
{
    public function __construct(
        string|int $id,
        public readonly ?array $_meta = null
    ) {
        $params = [];
        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, $id, 'roots/list', $params);
    }

    /**
     * @param string|int $id  The ID of the request to cancel.
     * @param array|null $_meta  Optional metadata to include in the request.
     */
    public static function make(string|int $id, ?array $_meta = null): static
    {
        return new static($id, $_meta);
    }
}
