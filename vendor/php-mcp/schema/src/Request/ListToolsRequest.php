<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Request;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Request;

/**
 * Sent from the client to request a list of tools the server has.
 */
class ListToolsRequest extends Request
{
    /**
     * @param string|null $cursor An opaque token representing the current pagination position.
     *
     * If provided, the server should return results starting after this cursor.
     *
     * @param array|null $_meta Optional metadata to include in the request.
     */
    public function __construct(
        string|int $id,
        public readonly ?string $cursor = null,
        public readonly ?array $_meta = null
    ) {
        $params = [];
        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }
        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, $id, 'tools/list', $params);
    }

    /**
     * @param string|int $id  The ID of the request to cancel.
     * @param string|null $cursor An opaque token representing the current pagination position.
     *
     * If provided, the server should return results starting after this cursor.
     * @param array|null $_meta  Optional metadata to include in the request.
     */
    public static function make(string|int $id, ?string $cursor = null, ?array $_meta = null): static
    {
        return new static($id, $cursor, $_meta);
    }

    public static function fromRequest(Request $request): static
    {
        if ($request->method !== 'tools/list') {
            throw new \InvalidArgumentException('Request is not a list tools request');
        }

        return new static($request->id, $request->params['cursor'] ?? null, $request->params['_meta'] ?? null);
    }
}
