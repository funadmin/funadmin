<?php

declare(strict_types=1);

namespace app\admin\development\service;

use app\common\crud\CrudDefinition;
use app\common\form\schema\FormFieldView;
use app\common\form\schema\FormSchema;
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

    public function createFromSchema(FormSchema $formSchema, array $form, array $publishConfig = [], array $schema = []): CrudDefinition
    {
        $form['name'] = (string) ($formSchema->document()['title'] ?? $form['name'] ?? '');
        $database = (array) ($formSchema->document()['database'] ?? []);
        $form['connection'] = (string) ($database['connection'] ?? $form['connection'] ?? 'mysql');
        $key = $this->identifier($formSchema->key(), '表单标识');
        $table = $this->identifier((string) ($database['table'] ?? ''), '绑定表');
        $entity = str_replace('_', '-', $key);
        $class = $this->studly($entity);
        $config = $this->config($form, $publishConfig, $entity);
        $memberApi = (bool) $config['memberApiEnabled'];
        $adopted = (string) ($database['source'] ?? 'created') === 'adopted';
        $schemaColumns = array_column((array) ($schema['columns'] ?? []), null, 'name');
        $primaryKey = $adopted ? $this->adoptedPrimaryKey($schema) : 'id';
        $connectionSettings = \app\common\service\database\DatabaseConnections::settings($form['connection']);
        $fields = $adopted
            ? [$this->schemaManagedField($schemaColumns[$primaryKey], true)]
            : [$this->managedField('id', 'bigint unsigned', true)];
        $prefix = (string) ($connectionSettings['prefix'] ?? '');
        $relations = [];
        $optionSources = [];

        foreach ($formSchema->fields() as $field) {
            if ($adopted && $field->name() === $primaryKey) continue;
            if (in_array($field->type(), self::LAYOUT_TYPES, true)) continue;
            if ($field->relationType() === 'has_many') {
                $relations[] = [
                    'name' => $field->name(), 'type' => 'hasMany',
                    'field' => $primaryKey, 'target' => $this->relationClass($field->relationTable(), $prefix),
                    'targetField' => $field->relationValueField(), 'with' => true,
                    'targetTable' => $field->relationTable(),
                ];
                continue;
            }
            $fields[] = $this->field($field, $relations, $optionSources, $prefix);
        }
        foreach ((array) ($formSchema->document()['relations'] ?? []) as $relation) {
            $relations[] = $this->schemaRelation((array) $relation, $prefix);
        }
        [$relations, $relationModels] = $this->relationModels(array_values($relations), $table, $entity, $class, $prefix);
        $customActions = ($formSchema->document()['list']['customActions'] ?? []) !== [];
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
            'generationTargets' => $this->targets($entity, $class, $memberApi, $relationModels, $customActions),
            'permissionPrefix' => 'business:' . $entity,
            'fields' => $fields,
            'relations' => $relations,
            'optionsSource' => array_values($optionSources),
            'templates' => $this->templates($memberApi, $relationModels !== [], $customActions),
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
            ] + $this->formSize($config),
            'dataScope' => [
                'enabled' => (bool) $config['dataScopeEnabled'],
                'field' => (string) $config['dataScopeField'],
                'resolver' => 'adminDepartmentIds',
            ],
            'menu' => [
                'enabled' => (bool) $config['menuEnabled'], 'parentId' => $config['parentId'],
                'parentSourceName' => (string) $config['parentSourceName'], 'name' => (string) $config['menuName'],
                'icon' => (string) $config['icon'], 'sortOrder' => (int) $config['sortOrder'],
                'hidden' => false, 'keepAlive' => true, 'affix' => false, 'target' => '_self',
            ],
            'permission' => ['enabled' => true, 'groupName' => (string) $config['menuName'], 'actions' => []],
            'list' => (array) ($formSchema->document()['list'] ?? []),
            'formSchemaVersion' => $formSchema->version(),
            'formSchemaHash' => $formSchema->hash(),
            'formSchema' => $formSchema->document(),
        ] + ($memberApi ? ['memberApi' => ['enabled' => true, 'ownerField' => (string) $config['memberApiOwnerField']]] : []));
    }

    /** 在表单定义完成后适配目标，保留布局、规则和选项源，不重新推断字段。 */
    public function forBusinessTarget(CrudDefinition $definition, array $target): CrudDefinition
    {
        if (($target['type'] ?? 'core') !== 'plugin') return $definition;
        $data = $definition->toArray();
        $plugin = (string) $target['pluginCode'];
        $entity = (string) $data['entity'];
        $data['target'] = ['type' => 'plugin', 'plugin' => $plugin, 'scope' => 'admin'];
        $data['module'] = $plugin;
        $data['apiPrefix'] = '/' . $entity;
        $data['routePath'] = '/plugins/' . $plugin . '/' . $entity;
        $data['permissionPrefix'] = $plugin . ':' . $entity;
        // 关联模型随模型层迁入插件命名空间。
        $coreModels = 'app\\admin\\business\\' . str_replace('-', '_', $entity) . '\\model\\';
        foreach ($data['relations'] as &$relation) {
            if (str_starts_with((string) ($relation['modelClass'] ?? ''), $coreModels)) {
                $relation['modelClass'] = 'app\\admin\\plugins\\' . $plugin . '\\model\\' . substr($relation['modelClass'], strlen($coreModels));
            }
        }
        unset($relation);
        unset($data['generationTargets'], $data['templates']['permissionMigration'], $data['templates']['phpTest'], $data['templates']['vitestTest'], $data['templates']['langMigration'], $data['templates']['langZh'], $data['templates']['langEn']);
        return CrudDefinition::fromArray($data);
    }

    public function defaultPublishConfig(array $form): array
    {
        $key = str_replace('_', '-', (string) ($form['form_key'] ?? 'form'));
        return $this->config($form, [], $key);
    }

    private function field(FormFieldView $field, array &$relations, array &$optionSources, string $prefix): array
    {
        $type = $field->type();
        if (!isset(self::COMPONENTS[$type])) throw new InvalidArgumentException('控件无法生成静态代码：' . $type);
        $name = $this->identifier($field->name(), '字段名');
        $columnType = trim($field->columnType());
        if ($field->unsigned() && !str_contains(strtolower($columnType), 'unsigned')) $columnType .= ' unsigned';
        $hidden = $field->hidden() || $type === 'hidden';
        $row = [
            'name' => $name, 'label' => $field->title(), 'dbType' => $columnType,
            'nullable' => $field->nullable(), 'primary' => false,
            'comment' => $field->comment(), 'component' => self::COMPONENTS[$type],
            'valueType' => $this->valueType($columnType, $type), 'managed' => false,
            'writable' => !$field->disabled() && $type !== 'readonly',
            'list' => $field->listShow(),
            'search' => $field->listFilter() !== '',
            'searchOperator' => $field->listFilter() ?: 'eq',
            'sortable' => $field->listSort(),
            'form' => true, 'detail' => !$hidden,
            'required' => $field->required(),
            'rules' => $this->rules($field->rules()),
            'listFormatter' => $field->listFormatter(),
            'listWidth' => $field->listWidth(),
            // 占位文案随 formSchema 节点 props 渲染；生成器行保持空串，生成结果不变。
            'placeholder' => '',
            // schemaAccess 供生成器把受访问控制的字段视为敏感字段（ProductionTemplateContext::sensitiveField）。
            'controlProps' => array_replace($field->props(), ['schemaAccess' => $field->access()]),
        ];
        $default = $field->defaultValue();
        if ($default !== null && $default !== '') $row['default'] = $default;
        if ($field->index() === 'unique') $row['unique'] = true;
        if (in_array($type, ['checkbox', 'transfer', 'daterange', 'datetimerange', 'images', 'file', 'files', 'json'], true)) $row['cast'] = 'json';
        if (in_array($type, ['image', 'images', 'file', 'files'], true)) $row['upload'] = true;
        $this->options($field, $name, $row, $optionSources);
        $this->relation($field, $name, $row, $relations, $optionSources, $prefix);
        return $row;
    }

    private function options(FormFieldView $field, string $name, array &$row, array &$sources): void
    {
        $source = $field->dataSource() ?? [];
        $mode = $field->dataSourceKind();
        if ($mode === 'static' && is_array($source['options'] ?? null)) {
            $row['options'] = $source['options'];
            return;
        }
        if (!in_array($mode, ['dictionary', 'dict', 'department', 'user', 'remote'], true)) return;
        if (in_array($mode, ['dictionary', 'dict'], true) && !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,59}$/', (string) ($source['dictionary'] ?? ''))) {
            throw new InvalidArgumentException('字段 ' . $name . ' 的字典数据源未选择字典，请在业务设计器中选择字典并重新保存');
        }
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

    private function relation(FormFieldView $field, string $name, array &$row, array &$relations, array &$sources, string $prefix): void
    {
        if ($field->relationType() !== 'belongs_to') return;
        $table = $this->identifier($field->relationTable(), '关联表');
        $targetField = $this->identifier($field->relationValueField(), '关联值字段');
        $relationName = preg_replace('/_id$/', '', $name) ?: $name . '_relation';
        $target = $this->relationClass($table, $prefix);
        $sourceName = $name . '_options';
        $row['relation'] = $relationName;
        $row['references'] = $target . '.' . $targetField;
        $row['optionsSource'] = $sourceName;
        $relations[$relationName] = [
            'name' => $relationName, 'type' => 'belongsTo', 'field' => $name,
            'target' => $target, 'targetField' => $targetField, 'optionsSource' => $sourceName, 'with' => true,
            'targetTable' => $table,
        ];
        $sources[$sourceName] = [
            'name' => $sourceName, 'type' => 'relation',
            'labelField' => $this->identifier($field->relationLabelField(), '关联显示字段'),
            'valueField' => $targetField,
        ];
    }

    /** Schema 顶层 relations 只生成模型关联方法，不产生选项源。 */
    private function schemaRelation(array $relation, string $prefix): array
    {
        $table = $this->identifier((string) ($relation['table'] ?? ''), '关联表');
        $row = [
            'name' => (string) ($relation['name'] ?? ''), 'type' => (string) ($relation['type'] ?? ''),
            'field' => (string) ($relation['localField'] ?? ''), 'target' => $this->relationClass($table, $prefix),
            'targetField' => (string) ($relation['foreignField'] ?? ''),
        ];
        if (is_array($relation['pivot'] ?? null)) {
            $row += [
                'pivotTable' => (string) ($relation['pivot']['table'] ?? ''),
                'pivotLocalKey' => (string) ($relation['pivot']['localKey'] ?? ''),
                'pivotTargetKey' => (string) ($relation['pivot']['foreignKey'] ?? ''),
            ];
        }
        return $row + ['modelOnly' => true, 'targetTable' => $table];
    }

    /**
     * 每个非自关联目标在本模块 model/relation 下生成轻量模型；自关联直接引用本模块模型。
     *
     * @return array{0: list<array>, 1: list<string>}
     */
    private function relationModels(array $relations, string $table, string $entity, string $class, string $prefix): array
    {
        $namespace = 'app\\admin\\business\\' . str_replace('-', '_', $entity) . '\\model';
        $models = [];
        foreach ($relations as &$relation) {
            if ($this->logicalTable((string) $relation['targetTable'], $prefix) === $this->logicalTable($table, $prefix)) {
                unset($relation['targetTable']);
                $relation['modelClass'] = $namespace . '\\' . $class;
                continue;
            }
            $relation['modelClass'] = $namespace . '\\relation\\' . $relation['target'];
            $models[$relation['target']] = $relation['target'];
        }
        unset($relation);
        return [$relations, array_values($models)];
    }

    private function config(array $form, array $config, string $entity): array
    {
        $stored = is_array($form['publish_config'] ?? null) ? $form['publish_config'] : [];
        $resolved = array_replace([
            'module' => 'business', 'apiPrefix' => '/business/' . $entity, 'routePath' => '/business/' . $entity,
            'menuEnabled' => true, 'parentId' => null, 'parentSourceName' => '',
            'menuName' => (string) ($form['name'] ?? $entity), 'icon' => 'i-ep-document', 'sortOrder' => 999,
            'softDeletes' => true, 'batchDelete' => true, 'import' => true, 'export' => true, 'formMode' => 'dialog',
            'dataScopeEnabled' => false, 'dataScopeField' => '',
            'memberApiEnabled' => false, 'memberApiOwnerField' => 'member_id',
        ], $stored, $config);
        // 动态发布允许保留空配置；正式生成须恢复派生默认值，不放宽非空值校验。
        foreach (['apiPrefix' => '/business/' . $entity, 'routePath' => '/business/' . $entity, 'menuName' => (string) ($form['name'] ?? $entity)] as $key => $default) {
            if ($resolved[$key] === '') $resolved[$key] = $default;
        }
        return $resolved;
    }

    /**
     * 表单宽度与全屏为可选特性：未配置（null、空串、false）时不写入 features，保持定义哈希与生成文件不变。
     * 取值范围由 DefinitionValidator 校验，这里仅把数字字符串规整为整数。
     */
    private function formSize(array $config): array
    {
        $size = [];
        $width = $config['formWidth'] ?? null;
        if ($width !== null && $width !== '') {
            $size['formWidth'] = is_string($width) && preg_match('/^[0-9]+$/D', $width) === 1 ? (int) $width : $width;
        }
        $fullscreen = $config['formFullscreen'] ?? null;
        if ($fullscreen !== null && $fullscreen !== false) $size['formFullscreen'] = $fullscreen;
        return $size;
    }

    private function targets(string $entity, string $class, bool $memberApi = false, array $relationModels = [], bool $customActions = false): array
    {
        $relationTargets = [];
        foreach ($relationModels as $model) {
            $relationTargets['relationModel:' . $model] = "app/admin/business/" . str_replace('-', '_', $entity) . "/model/relation/{$model}.php";
        }
        // 自定义动作：处理方法类与注册清单作为普通生成文件参与预览、三方合并与基线跟踪。
        if ($customActions) {
            $relationTargets['customActions'] = "app/admin/business/" . str_replace('-', '_', $entity) . "/action/{$class}Actions.php";
            $relationTargets['customActionsConfig'] = "app/admin/business/" . str_replace('-', '_', $entity) . "/config/actions.php";
        }
        return ($memberApi ? ['memberApiController' => "app/api/controller/business/{$class}Controller.php"] : []) + [
            'migration' => "database/business/{$entity}.sql",
            'model' => "app/admin/business/" . str_replace('-', '_', $entity) . "/model/{$class}.php",
            'validate' => "app/admin/business/" . str_replace('-', '_', $entity) . "/validate/{$class}Validate.php",
            'service' => "app/admin/business/" . str_replace('-', '_', $entity) . "/service/{$class}Service.php",
            'controller' => "app/admin/business/" . str_replace('-', '_', $entity) . "/controller/{$class}Controller.php",
            'permissionMigration' => "database/business/{$entity}_permissions.sql",
            'api' => "web/src/admin/api/business/{$entity}.ts",
            'view' => "web/src/admin/views/business/{$entity}/index.vue",
            'form' => "web/src/admin/views/business/{$entity}/components/{$class}Form.vue",
            'detail' => "web/src/admin/views/business/{$entity}/components/{$class}Detail.vue",
            'phpTest' => "tests/business/{$class}GeneratedTest.php",
            'vitestTest' => "web/tests/business/{$entity}.spec.ts",
            'langMigration' => "database/business/{$entity}_lang.sql",
            'langZh' => "app/admin/business/" . str_replace('-', '_', $entity) . "/lang/zh-cn.php",
            'langEn' => "app/admin/business/" . str_replace('-', '_', $entity) . "/lang/en-us.php",
        ] + $relationTargets;
    }

    private function templates(bool $memberApi = false, bool $relationModels = false, bool $customActions = false): array
    {
        return ($memberApi ? ['memberApiController' => 'admin/member-controller.php.tpl'] : []) + [
            'migration' => 'database/migration.sql.tpl', 'model' => 'admin/model.php.tpl',
            'validate' => 'admin/validate.php.tpl', 'service' => 'admin/service.php.tpl',
            'controller' => 'admin/controller.php.tpl', 'permissionMigration' => 'database/permissions.sql.tpl',
            'api' => 'frontend/api.ts.tpl', 'view' => 'frontend/index.vue.tpl',
            'form' => 'frontend/form.vue.tpl', 'detail' => 'frontend/detail.vue.tpl',
            'phpTest' => 'tests/php-test.php.tpl', 'vitestTest' => 'tests/vitest-test.ts.tpl',
            'langMigration' => 'database/lang.sql.tpl', 'langZh' => 'admin/lang-zh.php.tpl', 'langEn' => 'admin/lang-en.php.tpl',
        ] + ($relationModels ? ['relationModel' => 'admin/relation-model.php.tpl'] : [])
            + ($customActions ? ['customActions' => 'admin/custom-actions.php.tpl', 'customActionsConfig' => 'admin/custom-actions-config.php.tpl'] : []);
    }

    private function adoptedPrimaryKey(array $schema): string
    {
        $primary = array_values((array) ($schema['primaryKey'] ?? []));
        if (count($primary) !== 1) throw new InvalidArgumentException('采纳表必须且只能包含一个主键');
        $columns = array_column((array) ($schema['columns'] ?? []), null, 'name');
        $name = (string) $primary[0];
        if (!isset($columns[$name])) throw new InvalidArgumentException('采纳表主键字段不存在');
        return $this->identifier($name, '主键字段');
    }

    private function schemaManagedField(array $column, bool $primary): array
    {
        $field = $this->managedField((string) $column['name'], (string) $column['type'], $primary);
        $field['nullable'] = (bool) $column['nullable'];
        return $field;
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

    private function relationClass(string $table, string $prefix): string
    {
        return $this->studly($this->logicalTable($table, $prefix));
    }

    private function logicalTable(string $table, string $prefix): string
    {
        return $prefix !== '' && str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;
    }

    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}
