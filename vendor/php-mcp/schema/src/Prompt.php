<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * A prompt or prompt template that the server offers.
 */
class Prompt implements JsonSerializable
{
    /**
     * @param string $name The name of the prompt or prompt template.
     * @param string|null $description An optional description of what this prompt provides.
     * @param PromptArgument[]|null $arguments A list of arguments for templating. Null if not a template.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?array $arguments = null
    ) {
        if ($this->arguments !== null) {
            foreach ($this->arguments as $arg) {
                if (!($arg instanceof PromptArgument)) {
                    throw new \InvalidArgumentException("All items in Prompt 'arguments' must be PromptArgument instances.");
                }
            }
        }
    }

    /**
     * @param string $name The name of the prompt or prompt template.
     * @param string|null $description An optional description of what this prompt provides.
     * @param PromptArgument[]|null $arguments A list of arguments for templating. Null if not a template.
     */
    public static function make(string $name, ?string $description = null, ?array $arguments = null): static
    {
        return new static($name, $description, $arguments);
    }

    public function toArray(): array
    {
        $data = ['name' => $this->name];
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->arguments !== null) {
            $data['arguments'] = array_map(fn (PromptArgument $arg) => $arg->toArray(), $this->arguments);
        }
        return $data;
    }

    public static function fromArray(array $data): static
    {
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException("Invalid or missing 'name' in Prompt data.");
        }
        $arguments = null;
        if (isset($data['arguments']) && is_array($data['arguments'])) {
            $arguments = array_map(fn (array $argData) => PromptArgument::fromArray($argData), $data['arguments']);
        }
        return new static(
            name: $data['name'],
            description: $data['description'] ?? null,
            arguments: $arguments
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
