<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * Represents a root directory or file that the server can operate on.
 */
class Root implements JsonSerializable
{
    private const URI_PATTERN = '/^file:\/\/.*$/';

    /**
     * @param string $uri  The URI identifying the root. This *must* start with file:// for now.
     *
     *  This restriction may be relaxed in future versions of the protocol to allow other URI schemes.
     *
     * @param string|null $name An optional name for the root.
     *
     * This can be used to provide a human-readable identifier for the root, which may be useful for
     * display purposes or for referencing the root in other parts of the application.
     */
    public function __construct(
        public readonly string $uri,
        public readonly ?string $name = null
    ) {
        if (!preg_match(self::URI_PATTERN, $this->uri)) {
            throw new \InvalidArgumentException("Root URI must start with 'file://'. Given: " . $this->uri);
        }
    }

    /**
     * @param string $uri  The URI identifying the root. This *must* start with file:// for now.
     *
     *  This restriction may be relaxed in future versions of the protocol to allow other URI schemes.
     *
     * @param string|null $name An optional name for the root.
     *
     * This can be used to provide a human-readable identifier for the root, which may be useful for
     * display purposes or for referencing the root in other parts of the application.
     */
    public static function make(string $uri, ?string $name = null): static
    {
        return new static($uri, $name);
    }

    public function toArray(): array
    {
        $data = ['uri' => $this->uri];
        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        return $data;
    }

    public static function fromArray(array $data): static
    {
        if (empty($data['uri']) || !is_string($data['uri'])) {
            throw new \InvalidArgumentException("Invalid or missing 'uri' in Root data.");
        }

        return new static(
            uri: $data['uri'],
            name: $data['name'] ?? null
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
