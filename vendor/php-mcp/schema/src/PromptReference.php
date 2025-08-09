<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * Identifies a prompt.
 */
class PromptReference implements JsonSerializable
{
    public string $type = 'ref/prompt';

    /**
     * @param  string  $name  The name of the prompt or prompt template
     */
    public function __construct(
        public readonly string $name,
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
