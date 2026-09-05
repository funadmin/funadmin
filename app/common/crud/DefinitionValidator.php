<?php

declare(strict_types=1);

namespace app\common\crud;

use InvalidArgumentException;

/**
 * CRUD 定义的严格边界验证器。
 */
final class DefinitionValidator
{
    private const ROOT_KEYS = [
        'schemaVersion', 'name', 'table', 'title', 'description', 'paths', 'apiPrefix',
        'permissionPrefix', 'fields', 'relations', 'templates', 'metadata',
    ];
    private const FIELD_KEYS = [
        'name', 'dbType', 'nullable', 'primary', 'comment', 'default', 'extra', 'component',
        'valueType', 'managed', 'writable', 'options', 'relation', 'references', 'inferredBy',
    ];
    private const RELATION_KEYS = ['name', 'type', 'field', 'target', 'targetField', 'pivotTable'];

    public function validate(CrudDefinition $definition, string $projectRoot): void
    {
        $data = $definition->toArray();
        $unknown = array_diff(array_keys($data), self::ROOT_KEYS);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Definition 包含未知字段：' . implode(', ', $unknown));
        }
        if ($definition->schemaVersion() !== '1.0') {
            throw new InvalidArgumentException('不支持的 schemaVersion');
        }
        $this->identifier((string) ($data['name'] ?? ''), 'name', '/^[a-z][a-z0-9-]*$/');
        $this->identifier((string) ($data['table'] ?? ''), 'table', '/^[a-z_][a-z0-9_]*$/');
        $this->text((string) ($data['title'] ?? ''), 'title');
        if (isset($data['description'])) {
            $this->text((string) $data['description'], 'description');
        }
        if (!preg_match('#^/[a-z][a-z0-9-]*(?:/[a-z][a-z0-9-]*)*$#', (string) ($data['apiPrefix'] ?? ''))) {
            throw new InvalidArgumentException('API 前缀不合法');
        }
        if (!preg_match('/^[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)+$/', (string) ($data['permissionPrefix'] ?? ''))) {
            throw new InvalidArgumentException('权限前缀不合法');
        }
        $this->paths($data['paths'] ?? null, $projectRoot);
        $this->templates($data['templates'] ?? null);
        $this->fields($data['fields'] ?? null);
        $this->relations($data['relations'] ?? null);
    }

    private function paths(mixed $paths, string $projectRoot): void
    {
        if (!is_array($paths) || $paths === []) {
            throw new InvalidArgumentException('paths 必须为非空对象');
        }
        foreach ($paths as $type => $path) {
            if (!in_array($type, ['backend', 'frontend', 'database'], true) || !is_string($path)) {
                throw new InvalidArgumentException('paths 包含非法目标');
            }
            PathGuard::resolve($projectRoot, $path, '项目目录');
        }
    }

    private function templates(mixed $templates): void
    {
        if (!is_array($templates) || $templates === []) {
            throw new InvalidArgumentException('templates 必须为非空对象');
        }
        foreach ($templates as $type => $path) {
            if (!in_array($type, ['backend', 'frontend', 'database'], true) || !is_string($path)
                || !preg_match('#^(backend|frontend|database)/[a-zA-Z0-9._/-]+\.tpl$#', $path)
                || str_contains($path, '..') || str_starts_with($path, '/')) {
                throw new InvalidArgumentException('模板路径不合法');
            }
        }
    }

    private function fields(mixed $fields): void
    {
        if (!is_array($fields) || $fields === []) {
            throw new InvalidArgumentException('fields 至少包含一个字段');
        }
        $names = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                throw new InvalidArgumentException('字段定义必须为对象');
            }
            $unknown = array_diff(array_keys($field), self::FIELD_KEYS);
            if ($unknown !== []) {
                throw new InvalidArgumentException('字段包含未知属性：' . implode(', ', $unknown));
            }
            foreach (['name', 'dbType', 'nullable'] as $required) {
                if (!array_key_exists($required, $field)) {
                    throw new InvalidArgumentException('字段缺少 ' . $required);
                }
            }
            $this->identifier((string) $field['name'], '字段名', '/^[a-z_][a-z0-9_]*$/');
            $this->text((string) $field['dbType'], '字段类型');
            if (!is_bool($field['nullable'])) {
                throw new InvalidArgumentException('字段 nullable 必须是布尔值');
            }
            if (isset($field['comment'])) {
                $this->text((string) $field['comment'], '字段注释');
            }
            if (isset($names[$field['name']])) {
                throw new InvalidArgumentException('字段名重复：' . $field['name']);
            }
            $names[$field['name']] = true;
        }
    }

    private function relations(mixed $relations): void
    {
        if (!is_array($relations)) {
            throw new InvalidArgumentException('relations 必须为数组');
        }
        foreach ($relations as $relation) {
            if (!is_array($relation)) {
                throw new InvalidArgumentException('关系定义必须为对象');
            }
            $unknown = array_diff(array_keys($relation), self::RELATION_KEYS);
            if ($unknown !== []) {
                throw new InvalidArgumentException('关系包含未知属性：' . implode(', ', $unknown));
            }
            foreach (['name', 'type', 'field', 'target', 'targetField'] as $required) {
                if (!isset($relation[$required]) || !is_string($relation[$required]) || $relation[$required] === '') {
                    throw new InvalidArgumentException('关系缺少 ' . $required);
                }
            }
            $this->identifier($relation['name'], '关系名', '/^[a-z][a-z0-9_]*$/');
            if (!in_array($relation['type'], ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany'], true)) {
                throw new InvalidArgumentException('关系类型不合法');
            }
            $this->identifier($relation['field'], '关系字段', '/^[a-z_][a-z0-9_]*$/');
            $this->identifier($relation['targetField'], '目标字段', '/^[a-z_][a-z0-9_]*$/');
            if (!preg_match('/^[A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)*$/', $relation['target'])) {
                throw new InvalidArgumentException('关系目标不合法');
            }
        }
    }

    private function identifier(string $value, string $label, string $pattern): void
    {
        if (!preg_match($pattern, $value)) {
            throw new InvalidArgumentException($label . ' 不合法');
        }
    }

    private function text(string $value, string $label): void
    {
        if ($value === '' || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException($label . ' 文本不安全');
        }
    }
}
