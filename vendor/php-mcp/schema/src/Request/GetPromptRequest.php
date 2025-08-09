<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Request;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Request;

/**
 * Used by the client to get a prompt provided by the server.
 */
class GetPromptRequest extends Request
{
    /**
     * @param string|int $id  The ID of the request to cancel.
     * @param string $name  The name of the prompt to get.
     * @param array<string, mixed>|null $arguments  The arguments to pass to the prompt.
     * @param array|null $_meta  Optional metadata to include in the request.
     */
    public function __construct(
        string|int $id,
        public readonly string $name,
        public readonly ?array $arguments = null,
        public readonly ?array $_meta = null
    ) {
        $params = ['name' => $name];

        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        if ($arguments !== null) {
            $params['arguments'] = (object) $arguments;
        }

        parent::__construct(Constants::JSONRPC_VERSION, $id, 'prompts/get', $params);
    }

    /**
     * @param string|int $id  The ID of the request to cancel.
     * @param string $name  The name of the prompt to get.
     * @param array<string, mixed>|null $arguments  The arguments to pass to the prompt.
     * @param array|null $_meta  Optional metadata to include in the request.
     */
    public static function make(string|int $id, string $name, ?array $arguments = null, ?array $_meta = null): static
    {
        return new static($id, $name, $arguments, $_meta);
    }

    public static function fromRequest(Request $request): static
    {
        if ($request->method !== 'prompts/get') {
            throw new \InvalidArgumentException('Request is not a prompts/get request');
        }

        $params = $request->params;

        if (! isset($params['name']) || ! is_string($params['name']) || empty($params['name'])) {
            throw new \InvalidArgumentException("Missing or invalid 'name' parameter for prompts/get.");
        }

        $arguments = $params['arguments'] ?? new \stdClass();
        if (! is_array($arguments) && ! $arguments instanceof \stdClass) {
            throw new \InvalidArgumentException("Parameter 'arguments' must be an object/array for prompts/get.");
        }


        return new static($request->id, $params['name'], $arguments, $params['_meta'] ?? null);
    }
}
