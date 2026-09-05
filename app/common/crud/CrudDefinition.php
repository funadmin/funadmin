<?php

declare(strict_types=1);

namespace app\common\crud;

use JsonSerializable;

/**
 * 与入口无关的不可变 CRUD 定义。
 */
final class CrudDefinition implements JsonSerializable
{
    private function __construct(private readonly array $data)
    {
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function schemaVersion(): string
    {
        return (string) ($this->data['schemaVersion'] ?? '');
    }

    public function fields(): array
    {
        return is_array($this->data['fields'] ?? null) ? $this->data['fields'] : [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function hash(): string
    {
        return hash('sha256', self::canonicalJson($this->data));
    }

    public static function canonicalJson(array $data): string
    {
        $normalize = static function (mixed $value) use (&$normalize): mixed {
            if (!is_array($value)) {
                return $value;
            }
            if (!array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            return array_map($normalize, $value);
        };
        return json_encode($normalize($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
