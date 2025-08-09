<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * Additional properties describing a Tool to clients.
 * NOTE: all properties in ToolAnnotations are hints.
 */
class ToolAnnotations implements JsonSerializable
{
    /**
     * @param  ?string  $title  A human-readable title for the tool.
     * @param  ?bool  $readOnlyHint  If true, the tool does not modify its environment.
     * @param  ?bool  $destructiveHint  If true, the tool may perform destructive updates to its environment. If false, the tool performs only additive updates.
     * @param  ?bool  $idempotentHint  If true, calling the tool repeatedly with the same arguments will have no additional effect on the its environment. (This property is meaningful only when `readOnlyHint == false`)
     * @param  ?bool  $openWorldHint  If true, this tool may interact with an "open world" of external entities. If false, the tool's domain of interaction is closed. For example, the world of a web search tool is open, whereas that of a memory tool is not.
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?bool $readOnlyHint = null,
        public readonly ?bool $destructiveHint = null,
        public readonly ?bool $idempotentHint = null,
        public readonly ?bool $openWorldHint = null
    ) {
    }

    /**
     * Create a new ToolAnnotations.
     *
     * @param  ?string  $title  A human-readable title for the tool.
     * @param  ?bool  $readOnlyHint  If true, the tool does not modify its environment.
     * @param  ?bool  $destructiveHint  If true, the tool may perform destructive updates to its environment. If false, the tool performs only additive updates.
     * @param  ?bool  $idempotentHint  If true, calling the tool repeatedly with the same arguments will have no additional effect on the its environment. (This property is meaningful only when `readOnlyHint == false`)
     * @param  ?bool  $openWorldHint  If true, this tool may interact with an "open world" of external entities. If false, the tool's domain of interaction is closed. For example, the world of a web search tool is open, whereas that of a memory tool is not.
     */
    public static function make(?string $title = null, ?bool $readOnlyHint = null, ?bool $destructiveHint = null, ?bool $idempotentHint = null, ?bool $openWorldHint = null): static
    {
        return new static($title, $readOnlyHint, $destructiveHint, $idempotentHint, $openWorldHint);
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->title !== null) {
            $data['title'] = $this->title;
        }
        if ($this->readOnlyHint !== null) {
            $data['readOnlyHint'] = $this->readOnlyHint;
        }
        if ($this->destructiveHint !== null) {
            $data['destructiveHint'] = $this->destructiveHint;
        }
        if ($this->idempotentHint !== null) {
            $data['idempotentHint'] = $this->idempotentHint;
        }
        if ($this->openWorldHint !== null) {
            $data['openWorldHint'] = $this->openWorldHint;
        }
        return $data;
    }

    public static function fromArray(array $data): static
    {
        return new static(
            $data['title'] ?? null,
            $data['readOnlyHint'] ?? null,
            $data['destructiveHint'] ?? null,
            $data['idempotentHint'] ?? null,
            $data['openWorldHint'] ?? null
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
