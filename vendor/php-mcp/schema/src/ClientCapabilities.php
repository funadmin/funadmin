<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * Capabilities a client may support. Known capabilities are defined here, in this schema, but this is not a closed set: any client can define its own, additional capabilities.
 */
class ClientCapabilities implements JsonSerializable
{
    public function __construct(
        public readonly ?bool $roots = false,
        public readonly ?bool $rootsListChanged = null,
        public readonly ?bool $sampling = null,
        public readonly ?array $experimental = null
    ) {}

    public static function make(?bool $roots = false, ?bool $rootsListChanged = null, ?bool $sampling = null, ?array $experimental = null): static
    {
        return new static($roots, $rootsListChanged, $sampling, $experimental);
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->roots || $this->rootsListChanged) {
            $data['roots'] = new \stdClass();
            if ($this->rootsListChanged !== null) {
                $data['roots']->listChanged = $this->rootsListChanged;
            }
        }

        if ($this->sampling) {
            $data['sampling'] = new \stdClass();
        }

        if ($this->experimental) {
            $data['experimental'] = (object) $this->experimental;
        }

        return $data;
    }

    public static function fromArray(array $data): static
    {
        $rootsEnabled = isset($data['roots']);
        $rootsListChanged = null;
        if ($rootsEnabled) {
            if (is_array($data['roots']) && array_key_exists('listChanged', $data['roots'])) {
                $rootsListChanged = (bool) $data['roots']['listChanged'];
            } elseif (is_object($data['roots']) && property_exists($data['roots'], 'listChanged')) {
                $rootsListChanged = (bool) $data['roots']->listChanged;
            }
        }

        $sampling = null;
        if (isset($data['sampling'])) {
            $sampling = true;
        }

        return new static(
            $rootsEnabled,
            $rootsListChanged,
            $sampling,
            $data['experimental'] ?? null
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
