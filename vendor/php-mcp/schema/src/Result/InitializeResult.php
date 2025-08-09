<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Result;

use PhpMcp\Schema\Implementation;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\JsonRpc\Response;
use PhpMcp\Schema\ServerCapabilities;

/**
 * After receiving an initialize request from the client, the server sends this response.
 */
class InitializeResult extends Result
{
    /**
     * Create a new InitializeResult.
     *
     * @param  string  $protocolVersion  The version of the Model Context Protocol that the server wants to use. This may not match the version that the client requested. If the client cannot support this version, it MUST disconnect.
     * @param  ServerCapabilities  $capabilities  The capabilities of the server.
     * @param  Implementation  $serverInfo  Information about the server.
     * @param  string|null  $instructions  Instructions describing how to use the server and its features. This can be used by clients to improve the LLM's understanding of available tools, resources, etc. It can be thought of like a "hint" to the model. For example, this information MAY be added to the system prompt.
     * @param array<string,mixed>|null $_meta Optional _meta field.
     */
    public function __construct(
        public readonly string $protocolVersion,
        public readonly ServerCapabilities $capabilities,
        public readonly Implementation $serverInfo,
        public readonly ?string $instructions = null,
        public readonly ?array $_meta = null
    ) {}

    /**
     * Convert the result to an array.
     *
     * @return array{protocolVersion: string, capabilities: array, serverInfo: array, instructions?: string, _meta?: array}
     */
    public function toArray(): array
    {
        $data = [
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => $this->capabilities->toArray(),
            'serverInfo' => $this->serverInfo->toArray(),
        ];
        if ($this->instructions !== null) {
            $data['instructions'] = $this->instructions;
        }
        if ($this->_meta !== null) {
            $data['_meta'] = $this->_meta;
        }
        return $data;
    }

    /**
     * Create a new InitializeResult.
     *
     * @param  string  $protocolVersion  The version of the Model Context Protocol that the server wants to use. This may not match the version that the client requested. If the client cannot support this version, it MUST disconnect.
     * @param  ServerCapabilities  $capabilities  The capabilities of the server.
     * @param  Implementation  $serverInfo  Information about the server.
     * @param  string|null  $instructions  Instructions describing how to use the server and its features. This can be used by clients to improve the LLM's understanding of available tools, resources, etc. It can be thought of like a "hint" to the model. For example, this information MAY be added to the system prompt.
     * @param array<string,mixed>|null $_meta Optional _meta field.
     */
    public static function make(string $protocolVersion, ServerCapabilities $capabilities, Implementation $serverInfo, ?string $instructions = null, ?array $_meta = null): static
    {
        return new static($protocolVersion, $capabilities, $serverInfo, $instructions, $_meta);
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['protocolVersion']) || !is_string($data['protocolVersion'])) {
            throw new \InvalidArgumentException("Missing or invalid 'protocolVersion'");
        }
        if (!isset($data['capabilities']) || !is_array($data['capabilities'])) {
            throw new \InvalidArgumentException("Missing or invalid 'capabilities'");
        }
        if (!isset($data['serverInfo']) || !is_array($data['serverInfo'])) {
            throw new \InvalidArgumentException("Missing or invalid 'serverInfo'");
        }

        return new static(
            $data['protocolVersion'],
            ServerCapabilities::fromArray($data['capabilities']),
            Implementation::fromArray($data['serverInfo']),
            $data['instructions'] ?? null,
            $data['_meta'] ?? null
        );
    }

    public static function fromResponse(Response $response): static
    {
        return self::fromArray($response->result);
    }
}
