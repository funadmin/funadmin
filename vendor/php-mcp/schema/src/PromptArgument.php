<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * Describes an argument that a prompt can accept.
 */
class PromptArgument implements JsonSerializable
{
    /**
     * @param string $name The name of the argument.
     * @param string|null $description A human-readable description of the argument.
     * @param bool|null $required Whether this argument must be provided. Defaults to false per MCP spec if omitted.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?bool $required = null
    ) {
    }

    /**
     * @param string $name The name of the argument.
     * @param string|null $description A human-readable description of the argument.
     * @param bool|null $required Whether this argument must be provided. Defaults to false per MCP spec if omitted.
     */
    public static function make(string $name, ?string $description = null, ?bool $required = null): static
    {
        return new static($name, $description, $required);
    }

    public function toArray(): array
    {
        $data = ['name' => $this->name];
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->required !== null) {
            $data['required'] = $this->required;
        } // Only include if explicitly set
        return $data;
    }

    public static function fromArray(array $data): static
    {
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException("Invalid or missing 'name' in PromptArgument data.");
        }
        return new static(
            name: $data['name'],
            description: $data['description'] ?? null,
            required: $data['required'] ?? null // Keep null if not present, MCP implies default false
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
