<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Result;

use PhpMcp\Schema\JsonRpc\Response;
use PhpMcp\Schema\Tool;
use PhpMcp\Schema\JsonRpc\Result;

/**
 * The server's response to a tools/list request from the client.
 */
class ListToolsResult extends Result
{
    /**
     * @param  array<Tool>  $tools  The list of tool definitions.
     * @param  string|null  $nextCursor  An opaque token representing the pagination position after the last returned result.
     *
     * If present, there may be more results available.
     */
    public function __construct(
        public readonly array $tools,
        public readonly ?string $nextCursor = null
    ) {}

    /**
     * Create a new ListToolsResult.
     *
     * @param  array<Tool>  $tools  The list of tool definitions.
     * @param  string|null  $nextCursor  The cursor for the next page, or null if this is the last page.
     */
    public static function make(array $tools, ?string $nextCursor = null): static
    {
        return new static($tools, $nextCursor);
    }

    public function toArray(): array
    {
        $result =  [
            'tools' => array_map(fn(Tool $t) => $t->toArray(), $this->tools),
        ];

        if ($this->nextCursor) {
            $result['nextCursor'] = $this->nextCursor;
        }

        return $result;
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['tools']) || !is_array($data['tools'])) {
            throw new \InvalidArgumentException("Missing or invalid 'tools' array in ListToolsResult data.");
        }

        return new static(
            array_map(fn(array $tool) => Tool::fromArray($tool), $data['tools']),
            $data['nextCursor'] ?? null
        );
    }

    public static function fromResponse(Response $response): static
    {
        return self::fromArray($response->result);
    }
}
