<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Content;

use PhpMcp\Schema\Annotations;

/**
 * Represents embedded resource content within a message.
 */
class EmbeddedResource extends Content
{
    public function __construct(
        public readonly TextResourceContents|BlobResourceContents $resource,
        public readonly ?Annotations $annotations = null
    ) {
        parent::__construct('resource');
    }

    public static function make(TextResourceContents|BlobResourceContents $resource, ?Annotations $annotations = null): static
    {
        return new static($resource, $annotations);
    }

    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'resource' => $this->resource->toArray(),
        ];
        if ($this->annotations !== null) {
            $data['annotations'] = $this->annotations->toArray();
        }
        return $data;
    }

    public static function fromArray(array $data): static
    {
        if (($data['type'] ?? null) !== 'resource') {
            throw new \InvalidArgumentException("Invalid type for EmbeddedResource.");
        }
        if (!isset($data['resource']) || !is_array($data['resource'])) {
            throw new \InvalidArgumentException("Missing or invalid 'resource' field for EmbeddedResource.");
        }

        $resourceData = $data['resource'];
        $resourceInstance = null;
        if (isset($resourceData['text'])) {
            $resourceInstance = TextResourceContents::fromArray($resourceData);
        } elseif (isset($resourceData['blob'])) {
            $resourceInstance = BlobResourceContents::fromArray($resourceData);
        } else {
            throw new \InvalidArgumentException("EmbeddedResource 'resource' field must contain 'text' or 'blob'.");
        }

        return new static(
            $resourceInstance,
            isset($data['annotations']) ? Annotations::fromArray($data['annotations']) : null
        );
    }

    public static function fromText(string $uri, string $text, ?string $mimeType = 'text/plain', ?Annotations $annotations = null): static
    {
        $textContent = new TextResourceContents($uri, $mimeType, $text);
        return new static($textContent, $annotations);
    }

    public static function fromBlob(string $uri, string $base64Blob, string $mimeType, ?Annotations $annotations = null): static
    {
        $blobContent = new BlobResourceContents($uri, $mimeType, $base64Blob);
        return new static($blobContent, $annotations);
    }

    public static function fromFile(string $uri, string $path, ?string $explicitMimeType = null, ?Annotations $annotations = null): static
    {
        if (!file_exists($path) || !is_readable($path)) {
            throw new \InvalidArgumentException("File not found or not readable: {$path}");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Could not read file: {$path}");
        }

        $guessedMimeType = $explicitMimeType ?? mime_content_type($path) ?: 'application/octet-stream';

        if (self::isTextMimeTypeHeuristic($guessedMimeType) && mb_check_encoding($content, 'UTF-8')) {
            $resourceContent = new TextResourceContents($uri, $guessedMimeType, $content);
        } else {
            $resourceContent = new BlobResourceContents($uri, $guessedMimeType, base64_encode($content));
        }

        return new static($resourceContent, $annotations);
    }

    public static function fromStream(string $uri, $stream, string $mimeType, ?Annotations $annotations = null): static
    {
        $content = stream_get_contents($stream);
        if ($content === false) {
            throw new \RuntimeException("Could not read stream.");
        }
        return new static(new BlobResourceContents($uri, $mimeType, base64_encode($content)), $annotations);
    }

    public static function fromSplFileInfo(string $uri, \SplFileInfo $file, ?string $explicitMimeType = null, ?Annotations $annotations = null): static
    {
        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            throw new \RuntimeException("Could not read file: {$file->getPathname()}");
        }
        return new static(new BlobResourceContents($uri, $explicitMimeType ?? mime_content_type($file->getPathname()), base64_encode($content)), $annotations);
    }

    private static function isTextMimeTypeHeuristic(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'text/') ||
            in_array(strtolower($mimeType), ['application/json', 'application/xml', 'application/javascript', 'application/yaml']);
    }
}
