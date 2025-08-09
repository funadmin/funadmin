<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Content;

class ImageContent extends Content
{
    /**
     * Create a new ImageContent instance.
     *
     * @param  string  $data  Base64-encoded image data
     * @param  string  $mimeType  The MIME type of the image
     */
    public function __construct(
        public readonly string $data,
        public readonly string $mimeType
    ) {
    }

    /**
     * Create a new ImageContent instance.
     *
     * @param  string  $data  Base64-encoded image data
     * @param  string  $mimeType  The MIME type of the image
     */
    public static function make(string $data, string $mimeType): static
    {
        return new static($data, $mimeType);
    }

    /**
     * Convert the content to an array.
     *
     * @return array{type: string, data: string, mimeType: string}
     */
    public function toArray(): array
    {
        return [
            'type' => 'image',
            'data' => $this->data,
            'mimeType' => $this->mimeType,
        ];
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['data']) || !is_string($data['data'])) {
            throw new \InvalidArgumentException("Missing or invalid 'data' in ImageContent data.");
        }
        if (!isset($data['mimeType']) || !is_string($data['mimeType'])) {
            throw new \InvalidArgumentException("Missing or invalid 'mimeType' in ImageContent data.");
        }

        return new static($data['data'], $data['mimeType']);
    }

    /**
     * Create a new ImageContent from a file path.
     *
     * @param  string  $path  Path to the image file
     * @param  string|null  $mimeType  Optional MIME type override
     *
     * @throws \InvalidArgumentException If the file doesn't exist
     */
    public static function fromFile(string $path, ?string $mimeType = null): static
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("Image file not found: {$path}");
        }

        $data = base64_encode(file_get_contents($path));
        $detectedMime = $mimeType ?? mime_content_type($path) ?? 'image/png';

        return new static($data, $detectedMime);
    }

    public static function fromString(string $data, string $mimeType): static
    {
        return new static(base64_encode($data), $mimeType);
    }
}
