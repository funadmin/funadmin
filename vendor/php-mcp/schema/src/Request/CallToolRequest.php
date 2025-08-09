<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Request;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Request;

/**
 * Used by the client to invoke a tool provided by the server.
 */
class CallToolRequest extends Request
{
    /**
     * @param  string  $name  The name of the tool to invoke.
     * @param  array<string, mixed>  $arguments  The arguments to pass to the tool.
     * @param  array|null  $_meta  Optional metadata to include in the request.
     */
    public function __construct(
        string|int $id,
        public readonly string $name,
        public readonly array $arguments,
        public readonly ?array $_meta = null
    ) {
        $params = [
            'name' => $name,
            'arguments' => (object) $arguments,
        ];

        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, $id, 'tools/call', $params);
    }

    /**
     * @param string|int $id  The ID of the request to cancel.
     * @param string $name  The name of the tool to invoke.
     * @param array<string, mixed> $arguments  The arguments to pass to the tool.
     * @param array|null $_meta  Optional metadata to include in the request.
     */
    public static function make(string|int $id, string $name, array $arguments, ?array $_meta = null): static
    {
        return new static($id, $name, $arguments, $_meta);
    }

    public static function fromRequest(Request $request): static
    {
        if ($request->method !== 'tools/call') {
            throw new \InvalidArgumentException('Request is not a call tool request');
        }

        $params = $request->params ?? [];

        if (!isset($params['name']) || !is_string($params['name'])) {
            throw new \InvalidArgumentException("Missing or invalid 'name' parameter for tools/call.");
        }

        $arguments = $params['arguments'] ?? [];

        if ($arguments instanceof \stdClass) {
            $arguments = (array) $arguments;
        }

        if (!is_array($arguments)) {
            throw new \InvalidArgumentException("Parameter 'arguments' must be an array.");
        }

        return new static(
            $request->id,
            $params['name'],
            $arguments,
            $params['_meta'] ?? null
        );
    }
}
