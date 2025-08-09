<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Content;

use PhpMcp\Schema\Annotations;

/**
 * Represents audio content in MCP.
 */
class AudioContent extends Content
{
    public function __construct(
        public readonly string $data,
        public readonly string $mimeType,
        public readonly ?Annotations $annotations = null
    ) {
        parent::__construct('audio');
    }

    public static function make(string $data, string $mimeType, ?Annotations $annotations = null): static
    {
        return new static($data, $mimeType, $annotations);
    }

    /**
     * Convert the content to an array.
     *
     * @return array{type: string, data: string, mimeType: string, annotations?: array}
     */
    public function toArray(): array
    {
        $result = [
            'type' => 'audio',
            'data' => $this->data,
            'mimeType' => $this->mimeType,
        ];

        if ($this->annotations !== null) {
            $result['annotations'] = $this->annotations->toArray();
        }

        return $result;
    }

    public static function fromArray(array $data): static
    {
        if (! isset($data['data']) || ! isset($data['mimeType'])) {
            throw new \InvalidArgumentException("Invalid or missing 'data' or 'mimeType' in AudioContent data.");
        }

        return new static($data['data'], $data['mimeType'], $data['annotations'] ?? null);
    }

    /**
     * Create a new AudioContent from a file path.
     *
     * @param  string  $path  Path to the audio file
     * @param  string|null  $mimeType  Optional MIME type override
     * @param  ?Annotations  $annotations  Optional annotations describing the content
     *
     * @throws \InvalidArgumentException If the file doesn't exist
     */
    public static function fromFile(string $path, ?string $mimeType = null, ?Annotations $annotations = null): static
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("Audio file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Could not read audio file: {$path}");
        }
        $data = base64_encode($content);
        $detectedMime = $mimeType ?? mime_content_type($path) ?: 'application/octet-stream';

        return new static($data, $detectedMime, $annotations);
    }

    /**
     * Create a new AudioContent from a string.
     *
     * @param  string  $data  The audio data
     * @param  string  $mimeType  MIME type of the audio
     * @param  ?Annotations  $annotations  Optional annotations describing the content
     */
    public static function fromString(string $data, string $mimeType, ?Annotations $annotations = null): static
    {
        return new static(base64_encode($data), $mimeType, $annotations);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
