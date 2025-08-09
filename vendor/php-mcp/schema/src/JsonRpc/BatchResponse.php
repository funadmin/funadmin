<?php

declare(strict_types=1);

namespace PhpMcp\Schema\JsonRpc;

use Countable;
use JsonSerializable;

/**
 * A JSON-RPC batch response, as described in https://www.jsonrpc.org/specification#batch.
 */
class BatchResponse extends Message implements Countable
{
    /**
     * @param array<Response|Error> $items The individual responses/errors in this batch.
     */
    public function __construct(public array $items)
    {
        foreach ($items as $item) {
            if (!($item instanceof Response || $item instanceof Error)) {
                throw new \InvalidArgumentException("All items in BatchResponse must be instances of Response or Error.");
            }
        }
    }

    public function getId(): string|int|null
    {
        foreach ($this->items as $item) {
            if ($item instanceof Response) {
                return $item->getId();
            }
        }
        return "";
    }

    /**
     * @param array<Response|Error> $items The individual responses/errors in this batch.
     */
    public static function make(array $items): static
    {
        return new static($items);
    }

    public function toArray(): array
    {
        return array_map(fn($item) => $item->toArray(), $this->items);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromArray(array $data): static
    {
        if (empty($data)) {
            throw new \InvalidArgumentException("BatchResponse data array must not be empty.");
        }

        $items = [];
        foreach ($data as $itemData) {
            if (!is_array($itemData)) {
                throw new \InvalidArgumentException("BatchResponse item data must be an array.");
            }
            if (isset($itemData['id']) && $itemData['id'] !== null) {
                $items[] = Response::fromArray($itemData);
            } elseif (isset($itemData['error'])) {
                $items[] = Error::fromArray($itemData);
            } else {
                throw new \InvalidArgumentException("Invalid item in BatchResponse data: missing 'id' or 'error'.");
            }
        }

        return new static($items);
    }

    public function hasResponses(): bool
    {
        $hasResponses = false;
        foreach ($this->items as $item) {
            if ($item instanceof Response) {
                $hasResponses = true;
                break;
            }
        }

        return $hasResponses;
    }

    public function hasErrors(): bool
    {
        $hasErrors = false;
        foreach ($this->items as $item) {
            if ($item instanceof Error) {
                $hasErrors = true;
                break;
            }
        }
        return $hasErrors;
    }

    public function getResponses(): array
    {
        return array_filter($this->items, fn($item) => $item instanceof Response);
    }

    public function getErrors(): array
    {
        return array_filter($this->items, fn($item) => $item instanceof Error);
    }

    public function getAll(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
