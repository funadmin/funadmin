<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Content;

use JsonSerializable;

/**
 *  The contents of a specific resource or sub-resource.
 */
abstract class ResourceContents implements JsonSerializable
{
    /**
     * @param string $uri The URI of the resource or sub-resource.
     * @param string|null $mimeType The MIME type of the resource or sub-resource.
     */
    public function __construct(
        public readonly string $uri,
        public readonly ?string $mimeType = null
    ) {
    }

    public function toArray(): array
    {
        $data = ['uri' => $this->uri];
        if ($this->mimeType !== null) {
            $data['mimeType'] = $this->mimeType;
        }
        return $data;
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['uri']) || !is_string($data['uri'])) {
            throw new \InvalidArgumentException("Missing or invalid 'uri' in ResourceContents data.");
        }
        return new static($data['uri'], $data['mimeType'] ?? null);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
