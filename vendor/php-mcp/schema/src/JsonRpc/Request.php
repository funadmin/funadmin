<?php

declare(strict_types=1);

namespace PhpMcp\Schema\JsonRpc;

use PhpMcp\Schema\Constants;

class Request extends Message
{
    /**
     * @param string|int $id A unique identifier for the request.
     * @param string $method The name of the method to be invoked.
     * @param array<string, mixed>|null $params Parameters for the method.
     */
    public function __construct(
        string $jsonrpc,
        public readonly string|int $id,
        public readonly string $method,
        public readonly ?array $params = null
    ) {
        parent::__construct($jsonrpc);
        if ($this->id === null) {
            throw new \InvalidArgumentException("JSON-RPC Request ID MUST NOT be null for MCP.");
        }
    }

    public function getId(): string|int|null
    {
        return $this->id;
    }

    public static function fromArray(array $data): static
    {
        if (($data['jsonrpc'] ?? null) !== Constants::JSONRPC_VERSION) {
            throw new \InvalidArgumentException('Invalid or missing "jsonrpc" version for Request.');
        }
        if (!isset($data['id']) || !is_string($data['id']) && !is_int($data['id'])) {
            throw new \InvalidArgumentException('Invalid or missing "id" for Request.');
        }
        if (!isset($data['method']) || !is_string($data['method'])) {
            throw new \InvalidArgumentException('Invalid or missing "method" for Request.');
        }
        $params = $data['params'] ?? null;
        if ($params instanceof \stdClass) {
            $params = (array) $params;
        }
        if ($params !== null && !is_array($params)) {
            throw new \InvalidArgumentException('"params" for Request must be an array/object or null.');
        }
        return new static(
            $data['jsonrpc'],
            $data['id'],
            $data['method'],
            $params
        );
    }

    public function toArray(): array
    {
        $array = [
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id,
            'method' => $this->method,
        ];
        if ($this->params !== null) {
            $array['params'] = $this->params;
        }
        return $array;
    }
}
