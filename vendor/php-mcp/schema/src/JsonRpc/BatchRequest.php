<?php

declare(strict_types=1);

namespace PhpMcp\Schema\JsonRpc;

use Countable;

/**
 * A JSON-RPC batch request, as described in https://www.jsonrpc.org/specification#batch.
 */
class BatchRequest extends Message implements Countable
{
    /**
     * Create a new JSON-RPC 2.0 batch of requests/notifications.
     * @param array<Request|Notification> $requests The individual requests/notifications in this batch.
     */
    public function __construct(public readonly array $items)
    {
        foreach ($items as $item) {
            if (!($item instanceof Request || $item instanceof Notification)) {
                throw new \InvalidArgumentException("All items in BatchRequest must be instances of Request or Notification.");
            }
        }
    }

    public function getId(): string|int|null
    {
        foreach ($this->items as $item) {
            if ($item instanceof Request) {
                return $item->getId();
            }
        }
        return "";
    }

    /**
     * Create a new JSON-RPC 2.0 batch of requests/notifications.
     * @param array<Request|Notification> $requests The individual requests/notifications in this batch.
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
            throw new \InvalidArgumentException("BatchRequest data array must not be empty.");
        }

        $items = [];
        foreach ($data as $itemData) {
            if (!is_array($itemData)) {
                throw new \InvalidArgumentException("BatchRequest item data must be an array.");
            }
            if (isset($itemData['id']) && $itemData['id'] !== null) {
                $items[] = Request::fromArray($itemData);
            } elseif (isset($itemData['method'])) {
                $items[] = Notification::fromArray($itemData);
            } else {
                throw new \InvalidArgumentException("Invalid item in BatchRequest data: missing 'method' or 'id'.");
            }
        }

        return new static($items);
    }

    /**
     * Check if this batch has any requests.
     */
    public function hasRequests(): bool
    {
        $hasRequests = false;
        foreach ($this->items as $item) {
            if ($item instanceof Request) {
                $hasRequests = true;
                break;
            }
        }

        return $hasRequests;
    }

    /**
     * Check if this batch has any notifications.
     */
    public function hasNotifications(): bool
    {
        $hasNotifications = false;
        foreach ($this->items as $item) {
            if ($item instanceof Notification) {
                $hasNotifications = true;
                break;
            }
        }
        return $hasNotifications;
    }

    /**
     * Get all requests in this batch.
     *
     * @return array<Request>
     */
    public function getRequests(): array
    {
        return array_filter($this->items, fn($item) => $item instanceof Request);
    }

    /**
     * Get all notifications in this batch.
     *
     * @return array<Notification>
     */
    public function getNotifications(): array
    {
        return array_filter($this->items, fn($item) => $item instanceof Notification);
    }

    /**
     * Get all elements in this batch.
     *
     * @return array<Request|Notification>
     */
    public function getAll(): array
    {
        return $this->items;
    }

    /**
     * Count the total number of elements in this batch.
     */
    public function count(): int
    {
        return count($this->items);
    }

    public function nRequests(): int
    {
        return count($this->getRequests());
    }

    public function nNotifications(): int
    {
        return count($this->getNotifications());
    }
}
