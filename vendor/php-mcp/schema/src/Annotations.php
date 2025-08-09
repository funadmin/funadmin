<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use PhpMcp\Schema\Enum\Role;
use JsonSerializable;

/**
 * Optional annotations for the client. The client can use annotations
 * to inform how objects are used or displayed.
 */
class Annotations implements JsonSerializable
{
    /**
     * @param Role[]|null $audience Describes who the intended customer of this object or data is.
     *
     *  It can include multiple entries to indicate content useful for multiple audiences (e.g., `[Role::User, Role::Assistant]`).
     *
     * @param float|null $priority  Describes how important this data is for operating the server.
     *
     * A value of 1 means "most important," and indicates that the data is
     * effectively required, while 0 means "least important," and indicates that
     * the data is entirely optional.
     */
    public function __construct(
        public readonly ?array $audience = null,
        public readonly ?float $priority = null
    ) {
        if ($this->priority !== null && ($this->priority < 0 || $this->priority > 1)) {
            throw new \InvalidArgumentException("Annotation priority must be between 0 and 1.");
        }
        if ($this->audience !== null) {
            foreach ($this->audience as $role) {
                if (!($role instanceof Role)) {
                    throw new \InvalidArgumentException("All audience members must be instances of Role enum.");
                }
            }
        }
    }

    /**
     * @param Role[]|null $audience Describes who the intended customer of this object or data is.
     *
     *  It can include multiple entries to indicate content useful for multiple audiences (e.g., `[Role::User, Role::Assistant]`).
     *
     * @param float|null $priority  Describes how important this data is for operating the server.
     *
     * A value of 1 means "most important," and indicates that the data is
     * effectively required, while 0 means "least important," and indicates that
     * the data is entirely optional.
     */
    public static function make(array $audience = null, float $priority = null): static
    {
        return new static($audience, $priority);
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->audience !== null) {
            $data['audience'] = array_map(fn (Role $r) => $r->value, $this->audience);
        }
        if ($this->priority !== null) {
            $data['priority'] = $this->priority;
        }
        return $data;
    }

    public static function fromArray(array $data): static
    {
        $audience = null;
        if (isset($data['audience']) && is_array($data['audience'])) {
            $audience = array_map(fn (string $r) => Role::from($r), $data['audience']);
        }
        return new static(
            $audience,
            isset($data['priority']) ? (float)$data['priority'] : null
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
