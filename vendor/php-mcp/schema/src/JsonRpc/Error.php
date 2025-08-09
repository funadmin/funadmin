<?php

declare(strict_types=1);

namespace PhpMcp\Schema\JsonRpc;

use PhpMcp\Schema\Constants;

/**
 * A response to a request that indicates an error occurred.
 */
class Error extends Message
{
    /**
     * @param int $code The error type that occurred.
     * @param string $message A short description of the error.
     * @param mixed|null $data Additional information about the error.
     */
    public function __construct(
        string $jsonrpc,
        public readonly string|int $id,
        public readonly int $code,
        public readonly string $message,
        public readonly mixed $data = null
    ) {
        parent::__construct($jsonrpc);
    }

    public function getId(): string|int|null
    {
        return $this->id;
    }

    /**
     * @param string|int $id The request id that this error is associated with.
     * @param int $code The error type that occurred.
     * @param string $message A short description of the error.
     * @param mixed|null $data Additional information about the error.
     */
    public static function make(string|int $id, int $code, string $message, mixed $data = null): static
    {
        return new static(Constants::JSONRPC_VERSION, $id, $code, $message, $data);
    }

    public function toArray(): array
    {
        $error = [
            'code' => $this->code,
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $error['data'] = $this->data;
        }

        return [
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id,
            'error' => $error,
        ];
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== Constants::JSONRPC_VERSION) {
            throw new \InvalidArgumentException('Invalid or missing "jsonrpc" in Error data.');
        }
        if (!isset($data['id']) || !is_string($data['id'])) {
            throw new \InvalidArgumentException('Invalid or missing "id" in Error data.');
        }
        if (!isset($data['code']) || !is_int($data['code'])) {
            throw new \InvalidArgumentException('Invalid or missing "code" in Error data.');
        }
        if (!isset($data['message']) || !is_string($data['message'])) {
            throw new \InvalidArgumentException('Invalid or missing "message" in Error data.');
        }
        return new static(
            $data['jsonrpc'],
            $data['id'],
            $data['code'],
            $data['message'],
            $data['data'] ?? null
        );
    }

    public static function forParseError(string $message, string|int $id = ''): static
    {
        return new static(Constants::JSONRPC_VERSION, $id, Constants::PARSE_ERROR, $message);
    }

    public static function forInvalidRequest(string $message, string|int $id = ''): static
    {
        return new static(Constants::JSONRPC_VERSION, $id, Constants::INVALID_REQUEST, $message);
    }

    public static function forMethodNotFound(string $message, string|int $id = ''): static
    {
        return new static(Constants::JSONRPC_VERSION, $id, Constants::METHOD_NOT_FOUND, $message);
    }

    public static function forInvalidParams(string $message, string|int $id = ''): static
    {
        return new static(Constants::JSONRPC_VERSION, $id, Constants::INVALID_PARAMS, $message);
    }

    public static function forInternalError(string $message, string|int $id = ''): static
    {
        return new static(Constants::JSONRPC_VERSION, $id, Constants::INTERNAL_ERROR, $message);
    }

    public static function forServerError(string $message, string|int $id = ''): static
    {
        return new static(Constants::JSONRPC_VERSION, $id, Constants::SERVER_ERROR, $message);
    }
}
