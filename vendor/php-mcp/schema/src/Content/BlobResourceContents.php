<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Content;

/**
 * Represents blob resource contents in MCP.
 */
class BlobResourceContents extends ResourceContents
{
    /**
     * @param string $uri The URI of the resource or sub-resource.
     * @param string|null $mimeType The MIME type of the resource or sub-resource.
     * @param string $blob A base64-encoded string representing the binary data of the item.
     */
    public function __construct(
        string $uri,
        ?string $mimeType,
        public readonly string $blob
    ) {
        parent::__construct($uri, $mimeType);
    }

    public static function make(string $uri, ?string $mimeType, string $blob): static
    {
        return new static($uri, $mimeType, $blob);
    }

    public function toArray(): array
    {
        return [
            'blob' => $this->blob,
            ...parent::toArray(),
        ];
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['uri']) || !is_string($data['uri'])) {
            throw new \InvalidArgumentException("Missing or invalid uri for BlobResourceContents");
        }
        if (!isset($data['blob']) || !is_string($data['blob'])) {
            throw new \InvalidArgumentException("Missing or invalid blob for BlobResourceContents");
        }

        return new static($data['uri'], $data['mimeType'] ?? null, $data['blob']);
    }

    public static function fromStream(string $uri, $stream, string $mimeType): static
    {
        $blob = stream_get_contents($stream);
        return new static($uri, $mimeType, base64_encode($blob));
    }

    public static function fromSplFileInfo(string $uri, \SplFileInfo $file, ?string $explicitMimeType = null): static
    {
        $mimeType = $explicitMimeType ?? mime_content_type($file->getPathname());
        $blob = file_get_contents($file->getPathname());
        return new static($uri, $mimeType, base64_encode($blob));
    }
}
