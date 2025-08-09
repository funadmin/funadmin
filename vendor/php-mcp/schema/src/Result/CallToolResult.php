<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Result;

use PhpMcp\Schema\Content\AudioContent;
use PhpMcp\Schema\Content\Content;
use PhpMcp\Schema\Content\EmbeddedResource;
use PhpMcp\Schema\Content\ImageContent;
use PhpMcp\Schema\Content\TextContent;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\JsonRpc\Response;

/**
 * The server's response to a tool call.
 *
 * Any errors that originate from the tool SHOULD be reported inside the result
 * object, with `isError` set to true, _not_ as an MCP protocol-level error
 * response. Otherwise, the LLM would not be able to see that an error occurred
 * and self-correct.
 *
 * However, any errors in _finding_ the tool, an error indicating that the
 * server does not support tool calls, or any other exceptional conditions,
 * should be reported as an MCP error response.
 */
class CallToolResult extends Result
{
    /**
     * Create a new CallToolResult.
     *
     * @param  array<TextContent | ImageContent | AudioContent | EmbeddedResource>  $content  The content of the tool result
     * @param  bool  $isError  Whether the tool execution resulted in an error.  If not set, this is assumed to be false (the call was successful).
     */
    public function __construct(
        public readonly array $content,
        public readonly bool $isError = false
    ) {
        foreach ($this->content as $item) {
            if (!$item instanceof Content) {
                throw new \InvalidArgumentException('Content must be an array of Content objects.');
            }
        }
    }

    /**
     * Create a new CallToolResult.
     *
     * @param  array<TextContent | ImageContent | AudioContent | EmbeddedResource>  $content  The content of the tool result
     * @param  bool  $isError  Whether the tool execution resulted in an error
     */
    public static function make(array $content, bool $isError = false): static
    {
        return new static($content, $isError);
    }

    /**
     * Create a new CallToolResult with success status.
     *
     * @param  array<TextContent | ImageContent | AudioContent | EmbeddedResource>  $content  The content of the tool result
     */
    public static function success(array $content): static
    {
        return new static($content, false);
    }

    /**
     * Create a new CallToolResult with error status.
     *
     * @param  array<TextContent | ImageContent | AudioContent | EmbeddedResource>  $content  The content of the tool result
     */
    public static function error(array $content): static
    {
        return new static($content, true);
    }

    /**
     * Convert the result to an array.
     */
    public function toArray(): array
    {
        return [
            'content' => array_map(fn($item) => $item->toArray(), $this->content),
            'isError' => $this->isError,
        ];
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['content']) || !is_array($data['content'])) {
            throw new \InvalidArgumentException("Missing or invalid 'content' array in CallToolResult data.");
        }

        $contents = [];

        foreach ($data['content'] as $item) {
            $contents[] = match ($item['type'] ?? null) {
                'text' => TextContent::fromArray($item),
                'image' => ImageContent::fromArray($item),
                'audio' => AudioContent::fromArray($item),
                'resource' => EmbeddedResource::fromArray($item),
                null => throw new \InvalidArgumentException("Missing 'type' in CallToolResult content item."),
                default => throw new \InvalidArgumentException("Invalid content type in CallToolResult data: {$item['type']}"),
            };
        }

        return new static(
            $contents,
            $data['isError'] ?? false
        );
    }

    public static function fromResponse(Response $response): static
    {
        return self::fromArray($response->result);
    }
}
