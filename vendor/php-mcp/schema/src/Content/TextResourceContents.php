<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Content;

/**
 * Represents text resource contents in MCP.
 */
class TextResourceContents extends ResourceContents
{
    /**
     * @param string $uri The URI of the resource or sub-resource.
     * @param string|null $mimeType The MIME type of the resource or sub-resource.
     * @param string $text The text of the item. This must only be set if the item can actually be represented as text (not binary data).
     */
    public function __construct(
        string $uri,
        ?string $mimeType,
        public readonly string $text
    ) {
        parent::__construct($uri, $mimeType);
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            ...parent::toArray(),
        ];
    }

    public static function make(string $uri, ?string $mimeType, string $text): static
    {
        return new static($uri, $mimeType, $text);
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['uri']) || !is_string($data['uri'])) {
            throw new \InvalidArgumentException("Missing or invalid uri for TextResourceContents");
        }
        if (!isset($data['text']) || !is_string($data['text'])) {
            throw new \InvalidArgumentException("Missing or invalid text for TextResourceContents");
        }

        return new static($data['uri'], $data['mimeType'] ?? null, $data['text']);
    }
}
