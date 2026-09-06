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
        return new self(self::normalize($data));
    }

    private static function normalize(array $data): array
    {
        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];
        $fieldNames = array_column(array_filter($fields, 'is_array'), 'name');
        $primary = array_values(array_filter(
            $fields,
            static fn (mixed $field): bool => is_array($field) && ($field['primary'] ?? false) === true
        ));
        $data['connection'] ??= (string) (($data['metadata']['connection'] ?? ''));
        $data['module'] ??= 'generated';
        $data['entity'] ??= (string) ($data['name'] ?? '');
        $data['routePath'] ??= (string) ($data['apiPrefix'] ?? '');
        $data['primaryKey'] ??= (string) (($primary[0]['name'] ?? ''));
        $data['timestamps'] ??= in_array('created_at', $fieldNames, true) && in_array('updated_at', $fieldNames, true);
        $data['softDeletes'] ??= in_array('deleted_at', $fieldNames, true)
            || (($data['features']['softDelete'] ?? false) === true);
        $data['generationTargets'] ??= is_array($data['paths'] ?? null) ? $data['paths'] : [];
        unset($data['name'], $data['paths'], $data['apiPrefix'], $data['metadata']);
        return $data;
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
