<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Result;

use PhpMcp\Schema\JsonRpc\Result;

/**
 * The server's response to a completion/complete request
 */
class CompletionCompleteResult extends Result
{
    /**
     * @param string[] $values An array of completion values. Must not exceed 100 items.
     * @param int|null $total The total number of completion options available. This can exceed the number of values actually sent in the response.
     * @param bool|null $hasMore Indicates whether there are additional completion options beyond those provided in the current response, even if the exact total is unknown.
     */
    public function __construct(
        public readonly array $values,
        public readonly ?int $total = null,
        public readonly ?bool $hasMore = null
    ) {
        if (count($this->values) > 100) {
            throw new \InvalidArgumentException('Values must not exceed 100 items');
        }
    }

    public function toArray(): array
    {
        $completion = [
            'values' => $this->values,
        ];

        if ($this->total !== null) {
            $completion['total'] = $this->total;
        }
        if ($this->hasMore !== null) {
            $completion['hasMore'] = $this->hasMore;
        }

        return ['completion' => $completion];
    }

    /**
     * Create a new CompletionCompleteResult.
     *
     * @param  string[]  $values  An array of completion values. Must not exceed 100 items.
     * @param  int|null  $total  The total number of completion options available. This can exceed the number of values actually sent in the response.
     * @param  bool|null  $hasMore  Indicates whether there are additional completion options beyond those provided in the current response, even if the exact total is unknown.
     */
    public static function make(array $values, ?int $total = null, ?bool $hasMore = null): static
    {
        return new static($values, $total, $hasMore);
    }
}
