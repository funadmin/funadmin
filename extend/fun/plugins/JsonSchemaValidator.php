<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/** 校验 plugin manifest 所需的 JSON Schema 2020-12 子集。 */
final class JsonSchemaValidator
{
    public function __construct(private readonly array $rootSchema)
    {
    }

    public static function fromFile(string $file): self
    {
        $schema = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        return new self($schema);
    }

    public function validate(mixed $value): void
    {
        $this->validateNode($value, $this->rootSchema, '$');
    }

    private function validateNode(mixed $value, array $schema, string $path): void
    {
        if (isset($schema['$ref'])) {
            $schema = $this->resolve((string) $schema['$ref']);
        }
        foreach ($schema['allOf'] ?? [] as $part) {
            $this->validateNode($value, $part, $path);
        }
        if (array_key_exists('const', $schema) && $value !== $schema['const']) {
            $this->invalid($path, '值不符合 const');
        }
        $type = $schema['type'] ?? null;
        if ($type === 'object') {
            $this->validateObject($value, $schema, $path);
        } elseif ($type === 'array') {
            if (!is_array($value) || !array_is_list($value)) {
                $this->invalid($path, '必须是数组');
            }
            foreach ($value as $index => $item) {
                $this->validateNode($item, $schema['items'] ?? [], $path . '[' . $index . ']');
            }
        } elseif ($type === 'string') {
            $this->validateString($value, $schema, $path);
        } elseif ($type === 'boolean' && !is_bool($value)) {
            $this->invalid($path, '必须是布尔值');
        }
        if (isset($schema['pattern'])) {
            if (!is_string($value) || preg_match('~' . $schema['pattern'] . '~uD', $value) !== 1) {
                $this->invalid($path, '格式无效');
            }
        }
    }

    private function validateObject(mixed $value, array $schema, string $path): void
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            $this->invalid($path, '必须是对象');
        }
        foreach ($schema['required'] ?? [] as $required) {
            if (!array_key_exists($required, $value)) {
                $this->invalid($path, '缺少字段 ' . $required);
            }
        }
        $properties = $schema['properties'] ?? [];
        foreach ($value as $key => $item) {
            if (isset($properties[$key])) {
                $this->validateNode($item, $properties[$key], $path . '.' . $key);
                continue;
            }
            $additional = $schema['additionalProperties'] ?? true;
            if ($additional === false) {
                $this->invalid($path, '未知字段 ' . $key);
            }
            if (is_array($additional)) {
                $this->validateNode($item, $additional, $path . '.' . $key);
            }
        }
        if (isset($schema['propertyNames']['pattern'])) {
            foreach (array_keys($value) as $key) {
                if (preg_match('~' . $schema['propertyNames']['pattern'] . '~uD', (string) $key) !== 1) {
                    $this->invalid($path, '属性名无效');
                }
            }
        }
    }

    private function validateString(mixed $value, array $schema, string $path): void
    {
        if (!is_string($value)) {
            $this->invalid($path, '必须是字符串');
        }
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if (isset($schema['minLength']) && $length < $schema['minLength']) {
            $this->invalid($path, '长度不足');
        }
        if (isset($schema['maxLength']) && $length > $schema['maxLength']) {
            $this->invalid($path, '长度超限');
        }
    }

    private function resolve(string $reference): array
    {
        if (!str_starts_with($reference, '#/')) {
            throw new RuntimeException('仅支持本地 JSON Schema $ref');
        }
        $node = $this->rootSchema;
        foreach (explode('/', substr($reference, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (!isset($node[$segment]) || !is_array($node[$segment])) {
                throw new RuntimeException('JSON Schema $ref 不存在：' . $reference);
            }
            $node = $node[$segment];
        }
        return $node;
    }

    private function invalid(string $path, string $reason): never
    {
        throw new RuntimeException('plugin.json schema 校验失败：' . $path . ' ' . $reason);
    }
}
