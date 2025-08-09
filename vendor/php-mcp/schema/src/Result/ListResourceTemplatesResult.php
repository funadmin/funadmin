<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Result;

use PhpMcp\Schema\ResourceTemplate;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\JsonRpc\Response;

/**
 * The server's response to a resources/templates/list request from the client.
 */
class ListResourceTemplatesResult extends Result
{
    /**
     * @param  array<ResourceTemplate>  $resourceTemplates  The list of resource template definitions.
     * @param  string|null  $nextCursor  An opaque token representing the pagination position after the last returned result.
     *
     * If present, there may be more results available.
     */
    public function __construct(
        public readonly array $resourceTemplates,
        public readonly ?string $nextCursor = null
    ) {}

    /**
     * Create a new ListResourceTemplatesResult.
     *
     * @param  array<ResourceTemplate>  $resourceTemplates  The list of resource template definitions.
     * @param  string|null  $nextCursor  The cursor for the next page, or null if this is the last page.
     */
    public static function make(array $resourceTemplates, ?string $nextCursor = null): static
    {
        return new static($resourceTemplates, $nextCursor);
    }

    /**
     * Convert the result to an array.
     */
    public function toArray(): array
    {
        $result = [
            'resourceTemplates' => array_map(fn(ResourceTemplate $t) => $t->toArray(), $this->resourceTemplates),
        ];

        if ($this->nextCursor) {
            $result['nextCursor'] = $this->nextCursor;
        }

        return $result;
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['resourceTemplates']) || !is_array($data['resourceTemplates'])) {
            throw new \InvalidArgumentException("Missing or invalid 'resourceTemplates' array in ListResourceTemplatesResult data.");
        }

        return new static(
            array_map(fn(array $resourceTemplate) => ResourceTemplate::fromArray($resourceTemplate), $data['resourceTemplates']),
            $data['nextCursor'] ?? null
        );
    }

    public static function fromResponse(Response $response): static
    {
        return self::fromArray($response->result);
    }
}
