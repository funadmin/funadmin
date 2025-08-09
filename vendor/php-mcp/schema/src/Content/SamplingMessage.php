<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Content;

use PhpMcp\Schema\Enum\Role;

/**
 * Describes a message issued to or received from an LLM API during sampling.
 */
class SamplingMessage extends Content
{
    /**
     * @param Role $role
     * @param TextContent|ImageContent|AudioContent $content
     */
    public function __construct(
        public readonly Role $role,
        public readonly TextContent|ImageContent|AudioContent $content
    ) {
        parent::__construct('sampling');
    }

    public static function make(Role $role, TextContent|ImageContent|AudioContent $content): static
    {
        return new static($role, $content);
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content->toArray(),
        ];
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['role']) || !is_string($data['role'])) {
            throw new \InvalidArgumentException("Missing or invalid 'role' in SamplingMessage data.");
        }
        if (!isset($data['content']) || !is_array($data['content'])) {
            throw new \InvalidArgumentException("Missing or invalid 'content' in SamplingMessage data.");
        }

        $role = Role::from($data['role']);
        $contentData = $data['content'];
        $contentType = $contentData['type'] ?? null;

        $contentInstance = match ($contentType) {
            'text' => TextContent::fromArray($contentData),
            'image' => ImageContent::fromArray($contentData),
            'audio' => AudioContent::fromArray($contentData),
            default => throw new \InvalidArgumentException("Invalid content type '{$contentType}' for SamplingMessage.")
        };

        return new static($role, $contentInstance);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
