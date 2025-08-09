<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Content;

use PhpMcp\Schema\Annotations;

/**
 * Represents text content in MCP.
 */
class TextContent extends Content
{
    /**
     * Create a new TextContent instance.
     *
     * @param  string  $text  The text content
     * @param  ?Annotations  $annotations  Optional annotations describing the content
     */
    public function __construct(
        public readonly string $text,
        public readonly ?Annotations $annotations = null
    ) {
        parent::__construct('text');
    }

    /**
     * Convert the content to an array.
     *
     * @return array{type: string, text: string, annotations?: array}
     */
    public function toArray(): array
    {
        $result = [
            'type' => 'text',
            'text' => $this->text,
        ];

        if ($this->annotations !== null) {
            $result['annotations'] = $this->annotations->toArray();
        }

        return $result;
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['text']) || !is_string($data['text'])) {
            throw new \InvalidArgumentException("Missing or invalid 'text' in TextContent data.");
        }

        return new static($data['text'], $data['annotations'] ?? null);
    }

    /**
     * Create a new TextContent from any simple value.
     *
     * @param  mixed  $value  The value to convert to text
     */
    public static function make(mixed $value, ?Annotations $annotations = null): static
    {
        if (is_array($value) || is_object($value)) {
            $text = json_encode($value, JSON_PRETTY_PRINT);

            return new static($text, $annotations);
        }

        return new static((string) $value, $annotations);
    }

    /**
     * Create a new TextContent with markdown formatted code.
     *
     * @param  string  $code  The code to format
     * @param  string  $language  The language for syntax highlighting
     */
    public static function code(string $code, string $language = '', ?Annotations $annotations = null): static
    {
        return new static("```{$language}\n{$code}\n```", $annotations);
    }
}
