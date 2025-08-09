<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * Definition for a tool the client can call.
 */
class Tool implements JsonSerializable
{
    /**
     * @param string $name The name of the tool.
     * @param string|null $description A human-readable description of the tool.
     * This can be used by clients to improve the LLM's understanding of available tools. It can be thought of like a "hint" to the model.
     * @param array{ type: 'object', properties: array<string, mixed>, required: string[]|null } $inputSchema A JSON Schema object (as a PHP array) defining the expected 'arguments' for the tool.
     * @param ToolAnnotations|null $annotations Optional additional tool information.
     */
    public function __construct(
        public readonly string $name,
        public readonly array $inputSchema,
        public readonly ?string $description,
        public readonly ?ToolAnnotations $annotations
    ) {
        if (!isset($inputSchema['type']) || $inputSchema['type'] !== 'object') {
            throw new \InvalidArgumentException("Tool inputSchema must be a JSON Schema of type 'object'.");
        }
    }

    /**
     * @param string $name The name of the tool.
     * @param string|null $description A human-readable description of the tool.
     * This can be used by clients to improve the LLM's understanding of available tools. It can be thought of like a "hint" to the model.
     * @param array{ type: 'object', properties: array<string, mixed>, required: string[]|null } $inputSchema A JSON Schema object (as a PHP array) defining the expected 'arguments' for the tool.
     * @param ToolAnnotations|null $annotations Optional additional tool information.
     */
    public static function make(string $name, array $inputSchema, ?string $description = null,  ?ToolAnnotations $annotations = null): static
    {
        return new static($name, $inputSchema, $description, $annotations);
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'inputSchema' => $this->inputSchema,
        ];
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->annotations !== null) {
            $data['annotations'] = $this->annotations->toArray();
        }
        return $data;
    }

    public static function fromArray(array $data): static
    {
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException("Invalid or missing 'name' in Tool data.");
        }
        if (!isset($data['inputSchema']) || !is_array($data['inputSchema'])) {
            throw new \InvalidArgumentException("Invalid or missing 'inputSchema' in Tool data.");
        }
        if (!isset($data['inputSchema']['type']) || $data['inputSchema']['type'] !== 'object') {
            throw new \InvalidArgumentException("Tool inputSchema must be of type 'object'.");
        }
        if (isset($data['inputSchema']['properties']) && is_array($data['inputSchema']['properties']) && empty($data['inputSchema']['properties'])) {
            $data['inputSchema']['properties'] = new \stdClass();
        }

        return new static(
            name: $data['name'],
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : null,
            inputSchema: $data['inputSchema'],
            annotations: isset($data['annotations']) && is_array($data['annotations']) ? ToolAnnotations::fromArray($data['annotations']) : null
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
