<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Content;

use PhpMcp\Schema\Enum\Role;

/**
 * Describes a message returned as part of a prompt.
 */
class PromptMessage extends Content
{
    /**
     * Create a new PromptMessage instance.
     *
     * @param  Role  $role  The role of the message
     * @param  TextContent|ImageContent|AudioContent|EmbeddedResource  $content  The content of the message
     */
    public function __construct(
        public readonly Role $role,
        public readonly TextContent|ImageContent|AudioContent|EmbeddedResource $content
    ) {
        parent::__construct('prompt');
    }

    public static function make(Role $role, TextContent|ImageContent|AudioContent|EmbeddedResource $content): static
    {
        return new static($role, $content);
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['role']) || !is_string($data['role'])) {
            throw new \InvalidArgumentException("Missing or invalid 'role' in PromptMessage data.");
        }
        if (!isset($data['content']) || !is_array($data['content'])) {
            throw new \InvalidArgumentException("Missing or invalid 'content' in PromptMessage data.");
        }

        $contentData = $data['content'];
        $contentType = $contentData['type'] ?? null;

        $content = match ($contentType) {
            'text' => TextContent::fromArray($contentData),
            'image' => ImageContent::fromArray($contentData),
            'audio' => AudioContent::fromArray($contentData),
            'resource' => EmbeddedResource::fromArray($contentData),
            default => throw new \InvalidArgumentException("Invalid content type '{$contentType}' for PromptMessage.")
        };

        return new static(
            role: Role::from($data['role']),
            content: $content
        );
    }

    /**
     * Convert the message to an array.
     *
     * @return array{role: Role, content: array}
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content->toArray(),
        ];
    }
}
