<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * Hints to use for model selection.
 *
 * Keys not declared here are currently left unspecified by the spec and are up to the client to interpret.
 */
class ModelHint implements JsonSerializable
{
    /**
     * @param  string|null  $name  A hint for a model name.
     *
     * The client SHOULD treat this as a substring of a model name; for example:
     *  - `claude-3-5-sonnet` should match `claude-3-5-sonnet-20241022`
     *  - `sonnet` should match `claude-3-5-sonnet-20241022`, `claude-3-sonnet-20240229`, etc.
     *  - `claude` should match any Claude model
     *
     * The client MAY also map the string to a different provider's model name or a different model family, as long as it fills a similar niche; for example:
     *  - `gemini-1.5-flash` could match `claude-3-haiku-20240307`
     */
    public function __construct(
        public readonly ?string $name = null,
    ) {
    }

    /**
     * @param string|null $name A hint for a model name.
     */
    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    public function toArray(): array
    {
        if ($this->name === null) {
            return [];
        }

        return ['name' => $this->name];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
