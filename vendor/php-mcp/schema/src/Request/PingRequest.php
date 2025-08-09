<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Request;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Request;

/**
 * A ping, issued by either the server or the client, to check that the other party is still alive. The receiver must promptly respond, or else may be disconnected.
 */
class PingRequest extends Request
{
    public function __construct(
        string|int $id,
        public readonly ?array $_meta = null
    ) {
        $params = [];
        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, $id, 'ping', $params);
    }

    public static function make(string|int $id, ?array $_meta = null): static
    {
        return new static($id, $_meta);
    }

    public static function fromRequest(Request $request): static
    {
        if ($request->method !== 'ping') {
            throw new \InvalidArgumentException('Request is not a ping request');
        }

        return new static($request->id, $request->params['_meta'] ?? null);
    }
}
