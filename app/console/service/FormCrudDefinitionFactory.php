<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\crud\CrudDefinition;
use InvalidArgumentException;

/** 将表单设计元数据确定性转换为统一 CRUD Definition。 */
final class FormCrudDefinitionFactory
{
    private const LAYOUT_TYPES = ['group', 'grid', 'divider', 'text', 'collapse', 'tabs'];

    private const COMPONENTS = [
        'input' => 'input', 'password' => 'password', 'textarea' => 'textarea', 'mention' => 'mention',
        'number' => 'inputNumber', 'select' => 'select', 'selectV2' => 'selectV2', 'treeSelect' => 'treeSelect',
        'cascader' => 'cascader', 'radio' => 'radio', 'checkbox' => 'checkbox', 'switch' => 'switch',
        'transfer' => 'transfer', 'date' => 'date', 'datetime' => 'datetime', 'daterange' => 'daterange',
        'datetimerange' => 'datetimerange', 'time' => 'time', 'timeSelect' => 'timeSelect',
        'slider' => 'slider', 'rate' => 'rate', 'color' => 'color', 'image' => 'image', 'images' => 'images',
        'file' => 'file', 'files' => 'files', 'dictionary' => 'dictionary', 'relation' => 'relation',
        'department' => 'department', 'user' => 'user', 'richtext' => 'richtext', 'json' => 'json',
        'hidden' => 'hidden', 'readonly' => 'readonly',
    ];

    public function create(array $form, array $publishConfig = [], array $schema = []): CrudDefinition
    {
        $key = $this->identifier((string) ($form['form_key'] ?? ''), '表单标识');
        $table = $this->identifier((string) ($form['table_name'] ?? ''), '绑定表');
        $entity = str_replace('_', '-', $key);
        $class = $this->studly($entity);
        $config = $this->config($form, $publishConfig, $entity);
        $adopted = (string) ($form['source_type'] ?? 'created') === 'adopted';
        $schemaColumns = array_column((array) ($schema['columns'] ?? []), null, 'name');
        $primaryKey = $adopted ? $this->adoptedPrimaryKey($schema) : 'id';
        $fields = $adopted
            ? [$this->schemaManagedField($schemaColumns[$primaryKey], true)]
            : [$this->managedField('id', 'bigint unsigned', true)];
        $relations = [];
        $optionSources = [];
        $layoutSchema = [];

        foreach ((array) ($form['fields'] ?? []) as $field) {
            if (!is_array($field)) continue;
            if ($adopted && (string) ($field['field_name'] ?? '') === $primaryKey) continue;
            $type = (string) ($field['type'] ?? 'input');
            if (in_array($type, self::LAYOUT_TYPES, true)) {
                $layoutSchema[] = $this->layoutNode($field);
                continue;
            }
            $fields[] = $this->field($field, $type, $relations, $optionSources);
        }
        foreach (['created_at', 'updated_at', 'deleted_at'] as $managed) {
            if (in_array($managed, array_column($fields, 'name'), true)) continue;
            if ($adopted) {
                if (isset($schemaColumns[$managed])) $fields[] = $this->schemaManagedField($schemaColumns[$managed], false);
                continue;
            }
            $fields[] = $this->managedField($managed, 'datetime', false);
        }
        if (count(array_filter($fields, static fn (array $field): bool => !($field['managed'] ?? false))) === 0) {
            throw new InvalidArgumentException('发布前至少添加一个数据字段');
        }

        return CrudDefinition::fromArray([
            'schemaVersion' => '1.0',
            'connection' => (string) ($form['connection'] ?? 'mysql'),
            'module' => (string) $config['module'],
            'entity' => $entity,
            'table' => $table,
            'title' => trim((string) ($form['name'] ?? '')),
            'description' => trim((string) ($form['remark'] ?? '')),
            'apiPrefix' => (string) $config['apiPrefix'],
            'routePath' => (string) $config['routePath'],
            'primaryKey' => $primaryKey,
            'timestamps' => !$adopted || (isset($schemaColumns['created_at']) && isset($schemaColumns['updated_at'])),
            'softDeletes' => (bool) $config['softDeletes'] && (!$adopted || isset($schemaColumns['deleted_at'])),
            'generationTargets' => $this->targets($entity, $class),
            'permissionPrefix' => 'generated:' . $entity,
            'fields' => $fields,
            'relations' => array_values($relations),
            'optionsSource' => array_values($optionSources),
            'templates' => $this->templates(),
            'capabilities' => [
                'list' => true, 'search' => true, 'form' => true, 'detail' => true,
                'create' => true, 'update' => true, 'delete' => true,
                'import' => (bool) $config['import'], 'export' => (bool) $config['export'],
            ],
            'features' => [
                'batchDelete' => (bool) $config['batchDelete'], 'status' => $this->hasStatus($fields),
                'detail' => true, 'import' => (bool) $config['import'], 'export' => (bool) $config['export'],
                'upload' => true, 'dictionary' => true, 'referenceProtection' => true,
                'formMode' => (string) $config['formMode'], 'importLimit' => 10000, 'exportLimit' => 10000,
            ],
            'dataScope' => ['enabled' => false, 'field' => ''],
            'menu' => [
                'enabled' => (bool) $config['menuEnabled'], 'parentId' => $config['parentId'],
                'parentSourceName' => (string) $config['parentSourceName'], 'name' => (string) $config['menuName'],
                'icon' => (string) $config['icon'], 'sortOrder' => (int) $config['sortOrder'],
                'hidden' => false, 'keepAlive' => true, 'affix' => false, 'target' => '_self',
            ],
            'permission' => ['enabled' => true, 'groupName' => (string) $config['menuName'], 'actions' => []],
            'layoutSchema' => $layoutSchema,
        ]);
    }

    public function defaultPublishConfig(array $form): array
    {
        $key = str_replace('_', '-', (string) ($form['form_key'] ?? 'form'));
        return $this->config($form, [], $key);
    }

    private function field(array $field, string $type, array &$relations, array &$optionSources): array
    {
        if (!isset(self::COMPONENTS[$type])) throw new InvalidArgumentException('控件无法生成静态代码：' . $type);
        $name = $this->identifier((string) ($field['field_name'] ?? ''), '字段名');
        $columnType = trim((string) ($field['column_type'] ?? ''));
        if ((int) ($field['unsigned'] ?? 0) === 1 && !str_contains(strtolower($columnType), 'unsigned')) $columnType .= ' unsigned';
        $row = [
            'name' => $name, 'label' => (string) ($field['label'] ?? $name), 'dbType' => $columnType,
            'nullable' => (int) ($field['nullable'] ?? 1) === 1, 'primary' => false,
            'comment' => (string) ($field['comment'] ?? ''), 'component' => self::COMPONENTS[$type],
            'valueType' => $this->valueType($columnType, $type), 'managed' => false,
            'writable' => (int) ($field['form_readonly'] ?? 0) !== 1 && $type !== 'readonly',
            'list' => (int) ($field['list_show'] ?? 1) === 1,
            'search' => (string) ($field['list_filter'] ?? '') !== '',
            'searchOperator' => (string) ($field['list_filter'] ?? '') ?: 'eq',
            'sortable' => (int) ($field['list_sort'] ?? 0) === 1,
            'form' => (int) ($field['form_show'] ?? 1) === 1 && $type !== 'hidden', 'detail' => true,
            'required' => (int) ($field['form_required'] ?? 0) === 1,
            'rules' => $this->rules((array) ($field['validate_rules'] ?? [])),
            'listFormatter' => (string) ($field['list_formatter'] ?? ''),
            'listWidth' => (int) ($field['list_width'] ?? 0),
            'placeholder' => (string) ($field['placeholder'] ?? ''),
            'controlProps' => (array) ($field['control_props'] ?? []),
        ];
        if (array_key_exists('default_value', $field) && $field['default_value'] !== '') $row['default'] = $field['default_value'];
        if ((string) ($field['index_type'] ?? '') === 'unique') $row['unique'] = true;
        if (in_array($type, ['checkbox', 'transfer', 'daterange', 'datetimerange', 'images', 'file', 'files', 'json'], true)) $row['cast'] = 'json';
        if (in_array($type, ['image', 'images', 'file', 'files'], true)) $row['upload'] = true;
        $this->options($field, $name, $row, $optionSources);
        $this->relation($field, $name, $row, $relations, $optionSources);
        return $row;
    }

    private function options(array $field, string $name, array &$row, array &$sources): void
    {
        $source = (array) ($field['options_source'] ?? []);
        $mode = (string) ($source['mode'] ?? '');
        if ($mode === 'static' && is_array($source['options'] ?? null)) {
            $row['options'] = $source['options'];
            return;
        }
        if (!in_array($mode, ['dictionary', 'dict', 'department', 'user', 'remote'], true)) return;
        $sourceName = $name . '_options';
        $row['optionsSource'] = $sourceName;
        $row['dictionary'] = in_array($mode, ['dictionary', 'dict'], true);
        $sources[$sourceName] = [
            'name' => $sourceName,
            'type' => $row['dictionary'] ? 'dictionary' : 'endpoint',
            'endpoint' => (string) ($source['endpoint'] ?? '/form/data/options/' . $name),
            'dictionary' => (string) ($source['dictionary'] ?? ''),
            'labelField' => (string) ($source['label_field'] ?? 'label'),
            'valueField' => (string) ($source['value_field'] ?? 'value'),
        ];
    }

    private function relation(array $field, string $name, array &$row, array &$relations, array &$sources): void
    {
        if ((string) ($field['relation_type'] ?? 'none') !== 'belongs_to') return;
        $table = $this->identifier((string) ($field['relation_table'] ?? ''), '关联表');
        $targetField = $this->identifier((string) ($field['relation_value_field'] ?? 'id'), '关联值字段');
        $relationName = preg_replace('/_id$/', '', $name) ?: $name . '_relation';
        $target = $this->studly(preg_replace('/^fun_/', '', $table) ?: $table);
        $sourceName = $name . '_options';
        $row['relation'] = $relationName;
        $row['references'] = $target . '.' . $targetField;
        $row['optionsSource'] = $sourceName;
        $relations[$relationName] = [
            'name' => $relationName, 'type' => 'belongsTo', 'field' => $name,
            'target' => $target, 'targetField' => $targetField, 'optionsSource' => $sourceName, 'with' => true,
        ];
        $sources[$sourceName] = [
            'name' => $sourceName, 'type' => 'relation',
            'labelField' => $this->identifier((string) ($field['relation_label_field'] ?? 'name'), '关联显示字段'),
            'valueField' => $targetField,
        ];
    }

    private function config(array $form, array $config, string $entity): array
    {
        $stored = is_array($form['publish_config'] ?? null) ? $form['publish_config'] : [];
        return array_replace([
            'module' => 'generated', 'apiPrefix' => '/generated/' . $entity, 'routePath' => '/generated/' . $entity,
            'menuEnabled' => true, 'parentId' => null, 'parentSourceName' => '',
            'menuName' => (string) ($form['name'] ?? $entity), 'icon' => 'i-ep-document', 'sortOrder' => 999,
            'softDeletes' => true, 'batchDelete' => true, 'import' => true, 'export' => true, 'formMode' => 'dialog',
        ], $stored, $config);
    }

    private function targets(string $entity, string $class): array
    {
        return [
            'migration' => "database/generated/{$entity}.sql",
            'model' => "app/console/model/{$class}.php",
            'validate' => "app/console/validate/{$class}Validate.php",
            'service' => "app/console/service/{$class}Service.php",
            'controller' => "app/console/controller/generated/{$class}Controller.php",
            'permissionMigration' => "database/generated/{$entity}_permissions.sql",
            'api' => "admin-web/src/api/generated/{$entity}.ts",
            'view' => "admin-web/src/views/generated/{$entity}/index.vue",
            'form' => "admin-web/src/views/generated/{$entity}/components/{$class}Form.vue",
            'detail' => "admin-web/src/views/generated/{$entity}/components/{$class}Detail.vue",
            'phpTest' => "tests/generated/{$class}GeneratedTest.php",
            'vitestTest' => "admin-web/tests/generated/{$entity}.spec.ts",
        ];
    }

    private function templates(): array
    {
        return [
            'migration' => 'database/migration.sql.tpl', 'model' => 'console/model.php.tpl',
            'validate' => 'console/validate.php.tpl', 'service' => 'console/service.php.tpl',
            'controller' => 'console/controller.php.tpl', 'permissionMigration' => 'database/permissions.sql.tpl',
            'api' => 'frontend/api.ts.tpl', 'view' => 'frontend/index.vue.tpl',
            'form' => 'frontend/form.vue.tpl', 'detail' => 'frontend/detail.vue.tpl',
            'phpTest' => 'tests/php-test.php.tpl', 'vitestTest' => 'tests/vitest-test.ts.tpl',
        ];
    }

    private function adoptedPrimaryKey(array $schema): string
    {
        $primary = array_values((array) ($schema['primaryKey'] ?? []));
        if (count($primary) !== 1) throw new InvalidArgumentException('采纳表必须且只能包含一个主键');
        $columns = array_column((array) ($schema['columns'] ?? []), null, 'name');
        $name = (string) $primary[0];
        if (!isset($columns[$name])) throw new InvalidArgumentException('采纳表主键字段不存在');
        foreach (['create_time', 'update_time', 'delete_time'] as $legacy) {
            if (isset($columns[$legacy])) throw new InvalidArgumentException('采纳表包含 legacy 时间字段，必须先迁移为 Laravel 时间字段');
        }
        return $this->identifier($name, '主键字段');
    }

    private function schemaManagedField(array $column, bool $primary): array
    {
        return $this->managedField((string) $column['name'], (string) $column['type'], $primary);
    }

    private function managedField(string $name, string $type, bool $primary): array
    {
        return [
            'name' => $name, 'label' => strtoupper($name), 'dbType' => $type, 'nullable' => !$primary,
            'primary' => $primary, 'managed' => true, 'writable' => false, 'list' => $primary,
            'search' => false, 'sortable' => $primary, 'form' => false, 'detail' => true,
            'component' => $primary ? 'inputNumber' : 'datetime', 'valueType' => $primary ? 'integer' : 'datetime',
        ];
    }

    private function layoutNode(array $field): array
    {
        return [
            'type' => (string) ($field['type'] ?? ''), 'fieldName' => (string) ($field['field_name'] ?? ''),
            'label' => (string) ($field['label'] ?? ''), 'span' => (int) ($field['form_span'] ?? 24),
            'props' => (array) ($field['control_props'] ?? []),
        ];
    }

    private function rules(array $rules): array
    {
        return array_values(array_filter(array_map('strval', $rules), static fn (string $rule): bool => trim($rule) !== ''));
    }

    private function valueType(string $columnType, string $control): string
    {
        if (in_array($control, ['checkbox', 'transfer', 'daterange', 'datetimerange', 'images', 'files'], true)) return 'array';
        if ($control === 'json') return 'object';
        if (in_array($control, ['number', 'slider', 'rate'], true) || preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)/i', $columnType)) return 'integer';
        if (preg_match('/^(?:decimal|float|double)/i', $columnType)) return 'decimal';
        if (in_array($control, ['date', 'datetime', 'time', 'timeSelect'], true)) return 'datetime';
        return 'string';
    }

    private function hasStatus(array $fields): bool
    {
        foreach ($fields as $field) if (($field['name'] ?? '') === 'status' && ($field['writable'] ?? false)) return true;
        return false;
    }

    private function identifier(string $value, string $label): string
    {
        $value = trim($value);
        if (!preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $value)) throw new InvalidArgumentException($label . '不合法');
        return $value;
    }

    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}
