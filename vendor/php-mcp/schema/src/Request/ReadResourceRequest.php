<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Request;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Request;

/**
 * Sent from the client to the server, to read a specific resource URI.
 */
class ReadResourceRequest extends Request
{
    /**
     * @param  string  $uri  The URI of the resource to read.
     * @param  array|null  $_meta  Optional metadata to include in the request.
     */
    public function __construct(
        string|int $id,
        public readonly string $uri,
        public readonly ?array $_meta = null
    ) {
        $params = [
            'uri' => $uri,
        ];

        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, $id, 'resources/read', $params);
    }

    /**
     * @param string|int $id  The ID of the request to cancel.
     * @param string $uri  The URI of the resource to read.
     * @param array|null $_meta  Optional metadata to include in the request.
     */
    public static function make(string|int $id, string $uri, ?array $_meta = null): static
    {
        return new static($id, $uri, $_meta);
    }

    public static function fromRequest(Request $request): static
    {
        if ($request->method !== 'resources/read') {
            throw new \InvalidArgumentException('Request is not a read resource request');
        }

        $params = $request->params;

        if (! isset($params['uri']) || ! is_string($params['uri']) || empty($params['uri'])) {
            throw new \InvalidArgumentException("Missing or invalid 'uri' parameter for resources/read.");
        }

        return new static($request->id, $params['uri'], $params['_meta'] ?? null);
    }
}
