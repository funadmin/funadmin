<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Result;

use PhpMcp\Schema\Prompt;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\JsonRpc\Response;

/**
 * The server's response to a prompts/list request from the client.
 */
class ListPromptsResult extends Result
{
    /**
     * @param  array<Prompt>  $prompts  The list of prompt definitions.
     * @param  string|null  $nextCursor  An opaque token representing the pagination position after the last returned result.
     *
     * If present, there may be more results available.
     */
    public function __construct(
        public readonly array $prompts,
        public readonly ?string $nextCursor = null
    ) {}

    /**
     * Create a new ListPromptsResult.
     *
     * @param  array<Prompt>  $prompts  The list of prompt definitions.
     * @param  string|null  $nextCursor  The cursor for the next page, or null if this is the last page.
     */
    public static function make(array $prompts, ?string $nextCursor = null): static
    {
        return new static($prompts, $nextCursor);
    }

    public function toArray(): array
    {
        $result = [
            'prompts' => array_map(fn(Prompt $p) => $p->toArray(), $this->prompts),
        ];

        if ($this->nextCursor) {
            $result['nextCursor'] = $this->nextCursor;
        }

        return $result;
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['prompts']) || !is_array($data['prompts'])) {
            throw new \InvalidArgumentException("Missing or invalid 'prompts' array in ListPromptsResult data.");
        }

        return new static(
            array_map(fn(array $prompt) => Prompt::fromArray($prompt), $data['prompts']),
            $data['nextCursor'] ?? null
        );
    }

    public static function fromResponse(Response $response): static
    {
        return self::fromArray($response->result);
    }
}
