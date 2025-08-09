<?php

declare(strict_types=1);

namespace PhpMcp\Schema\JsonRpc;

use PhpMcp\Schema\Constants;

class Notification extends Message
{
    /**
     * @param string $method The name of the method to be invoked.
     * @param array<string, mixed>|null $params Parameters for the method.
     */
    public function __construct(
        string $jsonrpc,
        public readonly string $method,
        public readonly ?array $params = null
    ) {
        parent::__construct($jsonrpc);
    }

    public function getId(): string|int|null
    {
        return null;
    }

    public function toArray(): array
    {
        $array = [
            'jsonrpc' => $this->jsonrpc,
            'method' => $this->method,
        ];
        if ($this->params !== null) {
            $array['params'] = $this->params;
        }
        return $array;
    }

    public static function fromArray(array $data): static
    {
        if (($data['jsonrpc'] ?? null) !== Constants::JSONRPC_VERSION) {
            throw new \InvalidArgumentException('Invalid or missing "jsonrpc" version for Notification.');
        }
        if (isset($data['id'])) {
            throw new \InvalidArgumentException('Notification MUST NOT contain an "id" field.');
        }
        if (!isset($data['method']) || !is_string($data['method'])) {
            throw new \InvalidArgumentException('Invalid or missing "method" for Notification.');
        }
        $params = $data['params'] ?? null;
        if ($params !== null && !is_array($params)) {
            throw new \InvalidArgumentException('"params" for Notification must be an array/object or null.');
        }
        return new static(
            $data['jsonrpc'],
            $data['method'],
            $params
        );
    }
}
