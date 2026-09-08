<?php

declare(strict_types=1);

namespace app\common\form\schema;

final class FormSchema
{
    public function __construct(
        private readonly array $document,
        private readonly string $canonicalJson,
        private readonly array $fieldProjection
    ) {
    }

    public function version(): int
    {
        return (int) $this->document['schemaVersion'];
    }

    public function key(): string
    {
        return (string) $this->document['key'];
    }

    public function document(): array
    {
        return $this->document;
    }

    public function canonicalJson(): string
    {
        return $this->canonicalJson;
    }

    public function hash(): string
    {
        return hash('sha256', $this->canonicalJson);
    }

    public function fieldProjection(): array
    {
        return $this->fieldProjection;
    }
}
