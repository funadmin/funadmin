<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Result;

use PhpMcp\Schema\Resource;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\JsonRpc\Response;

/**
 * The server's response to a resources/list request from the client.
 */
class ListResourcesResult extends Result
{
    /**
     * @param  array<Resource>  $resources  The list of resource definitions.
     * @param  string|null  $nextCursor  An opaque token representing the pagination position after the last returned result.
     *
     * If present, there may be more results available.
     */
    public function __construct(
        public readonly array $resources,
        public readonly ?string $nextCursor = null
    ) {}

    /**
     * Create a new ListResourcesResult.
     *
     * @param  array<Resource>  $resources  The list of resource definitions.
     * @param  string|null  $nextCursor  The cursor for the next page, or null if this is the last page.
     */
    public static function make(array $resources, ?string $nextCursor = null): static
    {
        return new static($resources, $nextCursor);
    }

    /**
     * Convert the result to an array.
     */
    public function toArray(): array
    {
        $result = [
            'resources' => array_map(fn(Resource $r) => $r->toArray(), $this->resources),
        ];

        if ($this->nextCursor !== null) {
            $result['nextCursor'] = $this->nextCursor;
        }

        return $result;
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['resources']) || !is_array($data['resources'])) {
            throw new \InvalidArgumentException("Missing or invalid 'resources' array in ListResourcesResult data.");
        }

        return new static(
            array_map(fn(array $resource) => Resource::fromArray($resource), $data['resources']),
            $data['nextCursor'] ?? null
        );
    }

    public static function fromResponse(Response $response): static
    {
        return self::fromArray($response->result);
    }
}
