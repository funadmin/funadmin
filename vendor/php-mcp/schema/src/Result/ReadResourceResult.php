<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Result;

use PhpMcp\Schema\Content\BlobResourceContents;
use PhpMcp\Schema\Content\TextResourceContents;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\JsonRpc\Response;

/**
 * The server's response to a resources/read request from the client.
 */
class ReadResourceResult extends Result
{
    /**
     * Create a new ReadResourceResult.
     *
     * @param  array<TextResourceContents | BlobResourceContents> $contents  The contents of the resource
     */
    public function __construct(
        public readonly array $contents
    ) {}

    /**
     * @param  array<TextResourceContents | BlobResourceContents> $contents  The contents of the resource
     */
    public static function make(array $contents): static
    {
        return new static($contents);
    }


    /**
     * Convert the result to an array.
     */
    public function toArray(): array
    {
        return [
            'contents' => array_map(fn($resource) => $resource->toArray(), $this->contents),
        ];
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['contents']) || !is_array($data['contents'])) {
            throw new \InvalidArgumentException("Missing or invalid 'contents' array in ReadResourceResult data.");
        }

        $contents = [];
        foreach ($data['contents'] as $content) {
            if (isset($content['text'])) {
                $contents[] = TextResourceContents::fromArray($content);
            } else if (isset($content['blob'])) {
                $contents[] = BlobResourceContents::fromArray($content);
            } else {
                throw new \InvalidArgumentException("Invalid content type in ReadResourceResult data: " . json_encode($content));
            }
        }

        return new static(
            $contents,
        );
    }

    public static function fromResponse(Response $response): static
    {
        return self::fromArray($response->result);
    }
}
