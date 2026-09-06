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
        'permissionPrefix', 'fields', 'relations', 'optionsSource', 'templates', 'metadata',
        'capabilities', 'features', 'dataScope',
    ];
    private const ARTIFACT_KEYS = [
        'migration', 'model', 'validate', 'service', 'controller', 'permissionMigration',
        'phpTest', 'api', 'view', 'form', 'detail', 'vueTest',
    ];
    private const FIELD_KEYS = [
        'name', 'label', 'dbType', 'nullable', 'primary', 'comment', 'default', 'extra', 'component',
        'valueType', 'cast', 'managed', 'writable', 'options', 'optionsSource', 'relation', 'references',
        'inferredBy', 'legacy', 'list', 'search', 'searchOperator', 'sortable', 'form', 'detail', 'rules',
        'required', 'minLength', 'maxLength', 'min', 'max', 'enum', 'format', 'unique', 'dictionary',
        'upload', 'precision', 'scale',
    ];
    private const RELATION_KEYS = [
        'name', 'type', 'field', 'target', 'targetField', 'pivotTable', 'pivotLocalKey',
        'pivotTargetKey', 'optionsSource', 'with',
    ];
    private const OPTION_SOURCE_KEYS = ['name', 'type', 'endpoint', 'dictionary', 'labelField', 'valueField'];
    private const FEATURE_KEYS = [
        'softDelete', 'batchDelete', 'status', 'detail', 'import', 'export', 'upload', 'dictionary',
        'referenceProtection', 'formMode', 'importLimit', 'exportLimit',
    ];

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
        $this->identifier((string) ($data['table'] ?? ''), 'table', '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/');
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
        $fieldNames = $this->fields($data['fields'] ?? null);
        $optionSources = $this->optionsSources($data['optionsSource'] ?? []);
        $this->relations($data['relations'] ?? null, $fieldNames, $optionSources);
        $this->capabilities($data['capabilities'] ?? null);
        $this->features($data['features'] ?? null, $fieldNames);
        $this->dataScope($data['dataScope'] ?? null, $fieldNames);
    }

    private function paths(mixed $paths, string $projectRoot): void
    {
        if (!is_array($paths) || $paths === []) {
            throw new InvalidArgumentException('paths 必须为非空对象');
        }
        foreach ($paths as $type => $path) {
            if (!in_array($type, self::ARTIFACT_KEYS, true) || !is_string($path)) {
                throw new InvalidArgumentException('paths 包含非法目标');
            }
            PathGuard::resolve($projectRoot, $path, '项目目录');
        }
        foreach (['migration', 'model', 'validate', 'service', 'controller', 'permissionMigration', 'phpTest', 'api', 'view', 'form', 'vueTest'] as $required) {
            if (!isset($paths[$required])) {
                throw new InvalidArgumentException('paths 缺少生产制品：' . $required);
            }
        }
    }

    private function templates(mixed $templates): void
    {
        if (!is_array($templates) || $templates === []) {
            throw new InvalidArgumentException('templates 必须为非空对象');
        }
        foreach ($templates as $type => $path) {
            if (!in_array($type, self::ARTIFACT_KEYS, true) || !is_string($path)
                || !preg_match('#^(backend|frontend|database|tests)/[a-zA-Z0-9._/-]+\.tpl$#', $path)
                || str_contains($path, '..') || str_starts_with($path, '/')) {
                throw new InvalidArgumentException('模板路径不合法');
            }
        }
    }

    private function fields(mixed $fields): array
    {
        if (!is_array($fields) || $fields === []) {
            throw new InvalidArgumentException('fields 至少包含一个字段');
        }
        $names = [];
        $primary = 0;
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
            $name = (string) $field['name'];
            $this->identifier($name, '字段名', '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/');
            $this->text((string) $field['dbType'], '字段类型');
            if (!preg_match('/^[a-z]+(?:\([0-9]+(?:,[0-9]+)?\))?(?: unsigned)?$/i', (string) $field['dbType'])) {
                throw new InvalidArgumentException('字段类型不合法');
            }
            if (!is_bool($field['nullable'])) {
                throw new InvalidArgumentException('字段 nullable 必须是布尔值');
            }
            foreach (['primary', 'managed', 'writable', 'list', 'search', 'sortable', 'form', 'detail', 'required', 'unique', 'dictionary', 'upload'] as $flag) {
                if (isset($field[$flag]) && !is_bool($field[$flag])) {
                    throw new InvalidArgumentException('字段 ' . $flag . ' 必须是布尔值');
                }
            }
            foreach (['minLength', 'maxLength', 'precision', 'scale'] as $integer) {
                if (isset($field[$integer]) && (!is_int($field[$integer]) || $field[$integer] < 0)) {
                    throw new InvalidArgumentException('字段 ' . $integer . ' 必须是非负整数');
                }
            }
            if (isset($field['min'], $field['max']) && (float) $field['min'] > (float) $field['max']) {
                throw new InvalidArgumentException('字段范围 min 不能大于 max');
            }
            if (isset($field['enum']) && (!is_array($field['enum']) || $field['enum'] === [])) {
                throw new InvalidArgumentException('字段 enum 必须为非空数组');
            }
            if (isset($field['format']) && !in_array($field['format'], ['email', 'url', 'date', 'datetime', 'uuid', 'ip'], true)) {
                throw new InvalidArgumentException('字段 format 不合法');
            }
            if (isset($field['searchOperator']) && !in_array($field['searchOperator'], ['like', 'eq', 'in', 'range', 'gte', 'lte'], true)) {
                throw new InvalidArgumentException('字段 searchOperator 不合法');
            }
            if (isset($field['relation'])) {
                $this->identifier((string) $field['relation'], '字段 relation', '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/');
                if (!isset($field['references']) || !preg_match('/^[A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)*\.[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', (string) $field['references'])) {
                    throw new InvalidArgumentException('字段 references 不合法');
                }
            } elseif (isset($field['references'])) {
                throw new InvalidArgumentException('字段 references 必须与 relation 同时配置');
            }
            if (isset($field['optionsSource'])) {
                $this->identifier((string) $field['optionsSource'], '字段 optionsSource', '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/');
            }
            if (($field['legacy'] ?? false) === true || in_array($name, ['create_time', 'update_time', 'delete_time'], true)) {
                throw new InvalidArgumentException('发现 legacy 字段，必须先迁移为 Laravel 时间字段：' . $name);
            }
            if (isset($names[$name])) {
                throw new InvalidArgumentException('字段名重复：' . $name);
            }
            $primary += ($field['primary'] ?? false) ? 1 : 0;
            $names[$name] = $field;
        }
        if ($primary !== 1) {
            throw new InvalidArgumentException('必须且只能配置一个主键字段');
        }
        return $names;
    }

    private function relations(mixed $relations, array $fieldNames, array $optionSources): void
    {
        if (!is_array($relations)) {
            throw new InvalidArgumentException('relations 必须为数组');
        }
        $names = [];
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
            $this->identifier($relation['name'], '关系名', '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/');
            if (isset($names[$relation['name']])) {
                throw new InvalidArgumentException('关系名重复：' . $relation['name']);
            }
            if (!in_array($relation['type'], ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany'], true)) {
                throw new InvalidArgumentException('关系类型不合法');
            }
            if (!isset($fieldNames[$relation['field']])) {
                throw new InvalidArgumentException('关系字段不存在：' . $relation['field']);
            }
            $this->identifier($relation['targetField'], '目标字段', '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/');
            if (!preg_match('/^[A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)*$/', $relation['target'])) {
                throw new InvalidArgumentException('关系目标不合法');
            }
            if (isset($relation['optionsSource'])) {
                $this->identifier($relation['optionsSource'], '关系 optionsSource', '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/');
            }
            if (isset($relation['optionsSource']) && !isset($optionSources[$relation['optionsSource']])) {
                throw new InvalidArgumentException('关系 optionsSource 不存在');
            }
            if ($relation['type'] === 'belongsToMany') {
                foreach (['pivotTable', 'pivotLocalKey', 'pivotTargetKey'] as $pivotIdentifier) {
                    if (!isset($relation[$pivotIdentifier]) || !is_string($relation[$pivotIdentifier]) || $relation[$pivotIdentifier] === '') {
                        throw new InvalidArgumentException('belongsToMany 关系缺少 ' . $pivotIdentifier);
                    }
                    $this->identifier($relation[$pivotIdentifier], $pivotIdentifier, '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/');
                }
            } else {
                foreach (['pivotTable', 'pivotLocalKey', 'pivotTargetKey'] as $pivotIdentifier) {
                    if (isset($relation[$pivotIdentifier])) {
                        throw new InvalidArgumentException($pivotIdentifier . ' 仅允许 belongsToMany 关系配置');
                    }
                }
            }
            $names[$relation['name']] = true;
        }
    }

    private function optionsSources(mixed $sources): array
    {
        if (!is_array($sources)) {
            throw new InvalidArgumentException('optionsSource 必须为数组');
        }
        $names = [];
        foreach ($sources as $source) {
            if (!is_array($source) || array_diff(array_keys($source), self::OPTION_SOURCE_KEYS) !== []) {
                throw new InvalidArgumentException('optionsSource 包含未知属性');
            }
            foreach (['name', 'type', 'labelField', 'valueField'] as $required) {
                if (!isset($source[$required]) || !is_string($source[$required]) || $source[$required] === '') {
                    throw new InvalidArgumentException('optionsSource 缺少 ' . $required);
                }
            }
            if (!in_array($source['type'], ['relation', 'dictionary', 'endpoint'], true)) {
                throw new InvalidArgumentException('optionsSource type 不合法');
            }
            $this->identifier($source['name'], 'optionsSource 名称', '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/');
            $this->identifier($source['labelField'], 'optionsSource labelField', '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/');
            $this->identifier($source['valueField'], 'optionsSource valueField', '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/');
            if ($source['type'] === 'endpoint' && !preg_match('#^/[a-z0-9/_-]+$#', (string) ($source['endpoint'] ?? ''))) {
                throw new InvalidArgumentException('optionsSource endpoint 不合法');
            }
            if ($source['type'] === 'dictionary') {
                if (empty($source['dictionary'])) {
                    throw new InvalidArgumentException('字典 optionsSource 缺少 dictionary');
                }
                $this->identifier((string) $source['dictionary'], 'dictionary', '/^[a-z][a-z0-9_.-]*$/');
            }
            if (isset($names[$source['name']])) {
                throw new InvalidArgumentException('optionsSource 名称重复');
            }
            $names[$source['name']] = true;
        }
        return $names;
    }

    private function capabilities(mixed $capabilities): void
    {
        if ($capabilities === null) {
            return;
        }
        if (!is_array($capabilities) || $capabilities === []) {
            throw new InvalidArgumentException('capabilities 必须为非空对象');
        }
        foreach ($capabilities as $name => $enabled) {
            if (!in_array($name, ['list', 'search', 'form', 'detail', 'create', 'update', 'delete', 'import', 'export'], true)
                || !is_bool($enabled)) {
                throw new InvalidArgumentException('capabilities 包含非法能力');
            }
        }
    }

    private function features(mixed $features, array $fieldNames): void
    {
        if (!is_array($features) || array_diff(array_keys($features), self::FEATURE_KEYS) !== []) {
            throw new InvalidArgumentException('features 配置不完整或包含未知能力');
        }
        foreach (['softDelete', 'batchDelete', 'status', 'detail', 'import', 'export', 'upload', 'dictionary', 'referenceProtection'] as $flag) {
            if (!isset($features[$flag]) || !is_bool($features[$flag])) {
                throw new InvalidArgumentException('features 缺少布尔能力：' . $flag);
            }
        }
        if ($features['status']) {
            $status = $fieldNames['status'] ?? null;
            if ($status === null) {
                throw new InvalidArgumentException('features.status 启用时必须存在 status 字段');
            }
            if (($status['primary'] ?? false) || ($status['writable'] ?? true) === false) {
                throw new InvalidArgumentException('features.status 要求 status 字段可写');
            }
            if (preg_match('/^(?:tinyint|smallint|mediumint|int|bigint|bool|boolean)(?:\([0-9]+\))?(?: unsigned)?$/i', (string) $status['dbType']) !== 1) {
                throw new InvalidArgumentException('features.status 要求 status 字段为整数或布尔兼容类型');
            }
        }
        if (!in_array($features['formMode'] ?? null, ['dialog', 'drawer'], true)) {
            throw new InvalidArgumentException('features.formMode 必须是 dialog 或 drawer');
        }
        foreach (['importLimit', 'exportLimit'] as $limit) {
            if (!isset($features[$limit]) || !is_int($features[$limit]) || $features[$limit] < 1 || $features[$limit] > 10000) {
                throw new InvalidArgumentException('features.' . $limit . ' 必须在 1..10000');
            }
        }
    }

    private function dataScope(mixed $scope, array $fieldNames): void
    {
        if (!is_array($scope) || !isset($scope['enabled']) || !is_bool($scope['enabled'])
            || array_diff(array_keys($scope), ['enabled', 'field', 'resolver']) !== []) {
            throw new InvalidArgumentException('dataScope 配置不完整');
        }
        if ($scope['enabled']) {
            if (!isset($scope['field'], $fieldNames[$scope['field']])) {
                throw new InvalidArgumentException('dataScope.field 必须引用已定义字段');
            }
            $field = $fieldNames[$scope['field']];
            if (($field['primary'] ?? false) || ($field['writable'] ?? true) === false) {
                throw new InvalidArgumentException('dataScope.field 必须可写');
            }
            if (($field['required'] ?? !$field['nullable']) !== true) {
                throw new InvalidArgumentException('dataScope.field 必须必填');
            }
            if (($scope['resolver'] ?? '') !== 'adminDepartmentIds') {
                throw new InvalidArgumentException('dataScope.resolver 必须明确配置 adminDepartmentIds');
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
