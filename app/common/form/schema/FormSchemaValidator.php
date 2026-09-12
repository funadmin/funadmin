<?php

declare(strict_types=1);

namespace app\common\form\schema;

use app\common\form\component\PluginFormComponentRegistry;
use app\common\form\registry\FieldCapabilityRegistry;
use app\common\form\validation\FormSchemaDataValidator;

final class FormSchemaValidator
{
    public const COMPONENTS = [
        'input', 'password', 'textarea', 'mention', 'number', 'select', 'selectV2', 'treeSelect', 'cascader',
        'radio', 'checkbox', 'switch', 'transfer', 'date', 'datetime', 'daterange', 'datetimerange', 'time',
        'timeSelect', 'slider', 'rate', 'color', 'image', 'images', 'file', 'files', 'dictionary', 'relation',
        'department', 'user', 'richtext', 'json', 'hidden', 'readonly', 'group', 'grid', 'divider', 'text',
        'collapse', 'tabs', 'repeatable', 'subform',
    ];

    private const TOP_LEVEL_KEYS = [
        'schemaVersion', 'key', 'title', 'model', 'layout', 'nodes', 'dataSources', 'actions', 'form', 'submit',
        'list', 'database', 'extensions',
    ];
    private const ACTIONS = [
        'setValue', 'copyValue', 'clearValue', 'show', 'hide', 'enable', 'disable', 'setRequired',
        'validate', 'request', 'notify', 'openDialog', 'navigate', 'submit', 'reset',
    ];
    private const ACTION_PARAMETERS = [
        'setValue' => [['target'], ['target', 'value']],
        'copyValue' => [['source', 'target'], ['source', 'target']],
        'clearValue' => [['target'], ['target']],
        'show' => [['target'], ['target']],
        'hide' => [['target'], ['target']],
        'enable' => [['target'], ['target']],
        'disable' => [['target'], ['target']],
        'setRequired' => [['target'], ['target', 'value']],
        'validate' => [[], ['target']],
        'request' => [['key'], ['key', 'concurrency', 'params', 'parameters', 'permission', 'capabilityVersion']],
        'notify' => [[], ['message', 'level', 'tone']],
        'openDialog' => [['key'], ['key', 'params']],
        'navigate' => [['route'], ['route', 'params', 'query']],
        'submit' => [[], []],
        'reset' => [[], []],
    ];
    private const EVENTS = ['change', 'blur', 'focus', 'click', 'clear', 'select', 'submit', 'reset', 'mounted'];
    private const VALUE_TYPES = ['string', 'number', 'boolean', 'array', 'object'];
    private const DATA_SOURCE_KINDS = ['static', 'dictionary', 'department', 'user', 'relation', 'endpoint', 'computed'];
    private const CONDITION_OPERATORS = [
        'eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'notIn', 'contains', 'startsWith', 'endsWith', 'empty',
        'notEmpty', 'matches', 'and', 'or', 'not',
    ];
    private const VALIDATION_TYPES = [
        'required', 'type', 'min', 'max', 'minLength', 'maxLength', 'minlen', 'maxlen', 'length', 'enum', 'pattern',
        'format', 'email', 'url', 'number', 'integer', 'same', 'different', 'before', 'after', 'precision', 'file',
        'array', 'object', 'items', 'properties', 'async',
    ];
    private const RULE_KEYS = ['type', 'value', 'message', 'trigger', 'validator', 'when', 'severity', 'bail'];
    private const MAX_SCHEMA_BYTES = 1048576;
    private const MAX_DEPTH = 20;
    private const MAX_NODES = 1000;
    private const MAX_CHILDREN = 100;
    private const MAX_RULES = 50;
    private const MAX_ACTIONS = 50;
    private const MAX_DATA_SOURCES = 100;
    private const MAX_OPTIONS = 1000;

    private readonly FieldCapabilityRegistry $fieldCapabilities;

    public function __construct(
        private readonly ?PluginFormComponentRegistry $pluginComponents = null,
        ?FieldCapabilityRegistry $fieldCapabilities = null
    ) {
        $this->fieldCapabilities = $fieldCapabilities ?? new FieldCapabilityRegistry();
    }

    public function normalize(array $schema): array
    {
        if (is_array($schema['dataSources'] ?? null)) {
            foreach ($schema['dataSources'] as &$dataSource) {
                if (!is_array($dataSource)) {
                    continue;
                }
                $this->normalizeDataSource($dataSource);
            }
            unset($dataSource);
        }
        if (is_array($schema['nodes'] ?? null)) {
            $this->normalizeNodeDataSources($schema['nodes']);
        }
        return $schema;
    }

    public function validate(array $schema): void
    {
        $encoded = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || strlen($encoded) > self::MAX_SCHEMA_BYTES) {
            throw new FormSchemaException('Schema 字节数超过限制', '/', 'FORM_SCHEMA_LIMIT_EXCEEDED');
        }
        foreach (array_keys($schema) as $key) {
            if (!in_array($key, self::TOP_LEVEL_KEYS, true)) {
                throw new FormSchemaException('未知顶层字段：' . $key, '/' . $this->escapePointer((string) $key));
            }
        }
        if (($schema['schemaVersion'] ?? null) !== 2) {
            throw new FormSchemaException('仅支持 FormSchema v2', '/schemaVersion', 'FORM_SCHEMA_VERSION_UNSUPPORTED');
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,60}$/', (string) ($schema['key'] ?? ''))) {
            throw new FormSchemaException('表单标识不合法', '/key');
        }
        if (array_key_exists('title', $schema) && (!is_string($schema['title']) || trim($schema['title']) === '')) {
            throw new FormSchemaException('title 必须为非空字符串', '/title');
        }
        foreach (['model', 'layout', 'form', 'submit', 'list', 'database', 'extensions'] as $key) {
            if (array_key_exists($key, $schema) && (!is_array($schema[$key]) || array_is_list($schema[$key]) && $schema[$key] !== [])) {
                throw new FormSchemaException($key . ' 必须为对象', '/' . $key);
            }
            $this->validateSafeValue($schema[$key] ?? [], '/' . $key);
        }
        if (!is_array($schema['nodes'] ?? null)) {
            throw new FormSchemaException('nodes 必须为数组', '/nodes');
        }
        if (array_key_exists('dataSources', $schema) && (!is_array($schema['dataSources']) || !array_is_list($schema['dataSources']))) {
            throw new FormSchemaException('dataSources 必须为数组', '/dataSources');
        }
        if (array_key_exists('actions', $schema) && (!is_array($schema['actions']) || !array_is_list($schema['actions']))) {
            throw new FormSchemaException('actions 必须为数组', '/actions');
        }

        $dataSources = (array) ($schema['dataSources'] ?? []);
        $dataSourceIds = $this->validateDataSources($dataSources);
        $dataSourceCount = count($dataSources);
        $this->validateGlobalActions((array) ($schema['actions'] ?? []), '/actions');
        $ids = [];
        $fields = [];
        $nodeCount = 0;
        $edges = [];
        $this->validateNodes($schema['nodes'], '/nodes', 1, $ids, $fields, $nodeCount, $edges, $dataSourceIds, $dataSourceCount);
        $this->validateFieldReferences($schema['nodes'], '/nodes', $fields);
        $this->validateTopLevelDataSourceReferences((array) ($schema['dataSources'] ?? []), '/dataSources', $fields);
        $this->validateGlobalActionReferences((array) ($schema['actions'] ?? []), '/actions', $fields);
        $this->validateConditionCycles($edges);
        $this->validateListConfiguration((array) ($schema['list'] ?? []), $schema['nodes']);
    }

    /** 列表只能引用本表可读标量字段，分类源限定静态选项或字典。 */
    public function validateListConfiguration(array $list, array $nodes): void
    {
        $fields = [];
        $collect = function (array $items) use (&$collect, &$fields): void {
            foreach ($items as $node) {
                if (($node['kind'] ?? 'field') === 'field') $fields[$node['field'] ?? ''] = $node;
                if (!in_array($node['type'] ?? '', ['repeatable', 'subform'], true)) $collect($node['children'] ?? []);
            }
        };
        $collect($nodes);
        foreach (['category' => 'field', 'tree' => 'parentField'] as $kind => $binding) {
            if (!array_key_exists($kind, $list)) continue;
            $config = $list[$kind];
            if (!is_array($config) || ($config !== [] && array_is_list($config)) || array_diff(array_keys($config), ['enabled', $binding]) !== []) {
                throw new FormSchemaException('列表配置不合法', '/list/' . $kind);
            }
            if (isset($config['enabled']) && !is_bool($config['enabled'])) throw new FormSchemaException('enabled 必须为布尔值', '/list/' . $kind . '/enabled');
            if (($config['enabled'] ?? false) !== true) continue;
            $name = $config[$binding] ?? '';
            $node = is_string($name) ? ($fields[$name] ?? null) : null;
            if (!$node || empty($node['database']['columnType']) || in_array($node['type'] ?? '', ['password', 'repeatable', 'subform', 'json', 'checkbox', 'transfer'], true)
                || in_array($node['valueType'] ?? '', ['array', 'object'], true)
                || ($node['props']['multiple'] ?? false) || ($node['props']['sensitive'] ?? false) || ($node['props']['writeOnly'] ?? false)
                || ($node['access'] ?? []) !== [] || ($node['database']['relation']['type'] ?? 'none') !== 'none') {
                throw new FormSchemaException('必须选择当前表可读的非敏感标量字段', '/list/' . $kind . '/' . $binding);
            }
            if ($kind === 'category') {
                $source = $node['dataSource'] ?? [];
                $mode = $source['kind'] ?? $source['mode'] ?? '';
                if (!in_array($mode, ['static', 'dictionary'], true)) throw new FormSchemaException('分类仅支持字段静态选项或字典', '/list/category/field');
                if ($mode === 'dictionary' && !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,59}$/', (string) ($source['dictionary'] ?? ''))) throw new FormSchemaException('分类字典标识不合法', '/list/category/field');
                if ($mode === 'static') {
                    foreach ($source['options'] ?? [] as $option) {
                        if (!is_array($option) || !is_string($option['label'] ?? null) || (!is_string($option['value'] ?? null) && !is_int($option['value'] ?? null))) throw new FormSchemaException('分类选项必须具有文字标签和标量值', '/list/category/field');
                    }
                }
            }
        }
    }

    private function validateNodes(array $nodes, string $path, int $depth, array &$ids, array &$fields, int &$count, array &$edges, array $dataSourceIds, int &$dataSourceCount): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new FormSchemaException('节点嵌套超过限制', $path, 'FORM_SCHEMA_LIMIT_EXCEEDED');
        }
        foreach ($nodes as $index => $node) {
            $nodePath = $path . '/' . $index;
            if (!is_array($node) || array_is_list($node)) {
                throw new FormSchemaException('节点必须为对象', $nodePath);
            }
            if (++$count > self::MAX_NODES) {
                throw new FormSchemaException('节点数量超过限制', $nodePath, 'FORM_SCHEMA_LIMIT_EXCEEDED');
            }
            $id = (string) ($node['id'] ?? '');
            if ($id === '' || isset($ids[$id])) {
                throw new FormSchemaException('节点 ID 为空或重复', $nodePath . '/id');
            }
            $ids[$id] = true;
            $kind = $node['kind'] ?? 'field';
            if (!is_string($kind) || !in_array($kind, ['field', 'layout'], true)) {
                throw new FormSchemaException('节点 kind 不合法', $nodePath . '/kind');
            }
            if (array_key_exists('title', $node) && !is_string($node['title'])) {
                throw new FormSchemaException('节点 title 必须为字符串', $nodePath . '/title');
            }
            if (array_key_exists('valueType', $node) && !in_array($node['valueType'], self::VALUE_TYPES, true)) {
                throw new FormSchemaException('节点 valueType 不合法', $nodePath . '/valueType');
            }
            if (array_key_exists('slot', $node) && $node['slot'] !== null && !is_string($node['slot'])) {
                $this->validateSafeValue($node['slot'], $nodePath . '/slot');
                throw new FormSchemaException('节点 slot 必须为字符串或 null', $nodePath . '/slot');
            }
            foreach (['access', 'database', 'list'] as $objectKey) {
                if (array_key_exists($objectKey, $node) && (!is_array($node[$objectKey]) || array_is_list($node[$objectKey]) && $node[$objectKey] !== [])) {
                    throw new FormSchemaException('节点 ' . $objectKey . ' 必须为对象', $nodePath . '/' . $objectKey);
                }
            }
            $this->validateAccess((array) ($node['access'] ?? []), $nodePath . '/access');
            $this->validateDatabase((array) ($node['database'] ?? []), $nodePath . '/database');
            $this->validateList((array) ($node['list'] ?? []), $nodePath . '/list');

            $type = (string) ($node['type'] ?? '');
            $pluginComponent = str_contains($type, ':') ? $this->pluginComponents?->definition($type) : null;
            if (!$this->fieldCapabilities->has($type) && $pluginComponent === null) {
                throw new FormSchemaException('组件未注册：' . $type, $nodePath . '/type', 'FORM_COMPONENT_NOT_REGISTERED');
            }
            if ($pluginComponent !== null) {
                $this->validatePluginProps((array) ($node['props'] ?? []), $pluginComponent, $nodePath . '/props');
                $this->validatePluginEvents((array) ($node['events'] ?? []), $pluginComponent, $nodePath . '/events');
            } else {
                $capability = $this->fieldCapabilities->get($type);
                $this->validateComponentProps((array) ($node['props'] ?? []), $capability, $nodePath . '/props');
                $this->validateComponentAttrs((array) ($node['attrs'] ?? []), $nodePath . '/attrs');
            }
            $field = (string) ($node['field'] ?? '');
            if ($kind === 'field') {
                if (!preg_match('/^[a-z][a-z0-9_]{0,60}$/', $field) || isset($fields[$field])) {
                    throw new FormSchemaException('字段为空、不合法或重复', $nodePath . '/field');
                }
                $fields[$field] = true;
            }
            if (in_array($type, ['repeatable', 'subform'], true)) {
                $relation = (array) (($node['database']['relation'] ?? []));
                if (($relation['type'] ?? '') !== 'has_many') {
                    throw new FormSchemaException('重复行或子表单必须绑定 has_many', $nodePath . '/database/relation/type');
                }
                foreach (['table', 'valueField'] as $required) {
                    if (!preg_match('/^[a-z_][a-z0-9_]*$/', (string) ($relation[$required] ?? ''))) {
                        throw new FormSchemaException('子表关系配置不完整', $nodePath . '/database/relation/' . $required);
                    }
                }
            }
            foreach (['props', 'attrs', 'style', 'className', 'slot'] as $attribute) {
                $this->validateSafeValue($node[$attribute] ?? [], $nodePath . '/' . $attribute);
            }
            $this->validateRules($node['validation'] ?? [], $nodePath . '/validation');
            $this->validateEvents($node['events'] ?? [], $nodePath . '/events');
            $this->validateConditions($node['conditions'] ?? [], $field, $nodePath . '/conditions', $edges);
            if (array_key_exists('dataSource', $node) && $node['dataSource'] !== null) {
                if (++$dataSourceCount > self::MAX_DATA_SOURCES) {
                    throw new FormSchemaException('数据源数量超过限制', $nodePath . '/dataSource', 'FORM_SCHEMA_LIMIT_EXCEEDED');
                }
                $this->validateNodeDataSource($node['dataSource'], $nodePath . '/dataSource', $dataSourceIds);
            }
            $children = $node['children'] ?? [];
            if (!is_array($children) || !array_is_list($children)) {
                throw new FormSchemaException('children 必须为数组', $nodePath . '/children');
            }
            if (count($children) > self::MAX_CHILDREN) {
                throw new FormSchemaException('每节点 children 数量超过限制', $nodePath . '/children', 'FORM_SCHEMA_LIMIT_EXCEEDED');
            }
            if (in_array($type, ['repeatable', 'subform'], true)) {
                $childFields = [];
                $this->validateNodes($children, $nodePath . '/children', $depth + 1, $ids, $childFields, $count, $edges, $dataSourceIds, $dataSourceCount);
            } else {
                $this->validateNodes($children, $nodePath . '/children', $depth + 1, $ids, $fields, $count, $edges, $dataSourceIds, $dataSourceCount);
            }
        }
    }

    private function validateDataSources(array $dataSources): array
    {
        if (count($dataSources) > self::MAX_DATA_SOURCES) {
            throw new FormSchemaException('数据源数量超过限制', '/dataSources', 'FORM_SCHEMA_LIMIT_EXCEEDED');
        }
        $ids = [];
        foreach ($dataSources as $index => $dataSource) {
            $path = '/dataSources/' . $index;
            if (!is_array($dataSource) || array_is_list($dataSource)) {
                throw new FormSchemaException('数据源必须为对象', $path);
            }
            $id = (string) ($dataSource['id'] ?? '');
            if (!preg_match('/^[a-z][a-z0-9_.-]{0,60}$/', $id) || isset($ids[$id])) {
                throw new FormSchemaException('数据源 ID 为空、不合法或重复', $path . '/id');
            }
            $ids[$id] = true;
            $this->validateDataSourceDefinition($dataSource, $path);
        }
        return $ids;
    }

    private function validateNodeDataSource(mixed $dataSource, string $path, array $ids): void
    {
        if (!is_array($dataSource) || array_is_list($dataSource)) {
            throw new FormSchemaException('节点数据源必须为对象', $path);
        }
        if (array_key_exists('ref', $dataSource)) {
            $ref = (string) $dataSource['ref'];
            if ($ref === '' || !isset($ids[$ref])) {
                throw new FormSchemaException('引用的数据源不存在', $path . '/ref', 'FORM_DATA_SOURCE_REFERENCE_INVALID');
            }
            return;
        }
        $this->validateDataSourceDefinition($dataSource, $path);
    }

    private function validateDataSourceDefinition(array $dataSource, string $path): void
    {
        if (isset($dataSource['options']) && (!is_array($dataSource['options']) || count($dataSource['options']) > self::MAX_OPTIONS)) {
            throw new FormSchemaException('数据源 options 不合法或超过限制', $path . '/options', 'FORM_SCHEMA_LIMIT_EXCEEDED');
        }
        $kind = (string) ($dataSource['kind'] ?? '');
        if (!in_array($kind, self::DATA_SOURCE_KINDS, true)) {
            throw new FormSchemaException('数据源 kind 未注册：' . $kind, $path . '/kind', 'FORM_DATA_SOURCE_NOT_REGISTERED');
        }
        if (isset($dataSource['dependsOn'])) {
            if (!is_array($dataSource['dependsOn']) || !array_is_list($dataSource['dependsOn'])) {
                throw new FormSchemaException('dependsOn 必须为数组', $path . '/dependsOn');
            }
            foreach ($dataSource['dependsOn'] as $index => $field) {
                if (!is_string($field) || !preg_match('/^[a-z][a-z0-9_.]{0,100}$/', $field)) {
                    throw new FormSchemaException('dependsOn 字段不合法', $path . '/dependsOn/' . $index);
                }
            }
        }
        $this->validateSafeValue($dataSource, $path);
    }

    private function validateRules(mixed $rules, string $path): void
    {
        if (!is_array($rules) || !array_is_list($rules)) {
            throw new FormSchemaException('validation 必须为数组', $path);
        }
        if (count($rules) > self::MAX_RULES) {
            throw new FormSchemaException('每节点规则数量超过限制', $path, 'FORM_SCHEMA_LIMIT_EXCEEDED');
        }
        foreach ($rules as $index => $rule) {
            $rulePath = $path . '/' . $index;
            if (!is_array($rule) || array_is_list($rule) || !in_array($rule['type'] ?? null, self::VALIDATION_TYPES, true)) {
                throw new FormSchemaException('验证规则类型未注册', $rulePath . '/type');
            }
            foreach (array_keys($rule) as $key) {
                if (!in_array($key, self::RULE_KEYS, true)) {
                    throw new FormSchemaException('验证规则参数未授权', $rulePath . '/' . $key);
                }
            }
            if (isset($rule['severity']) && !in_array($rule['severity'], ['error', 'warning'], true)) {
                throw new FormSchemaException('验证严重级别不合法', $rulePath . '/severity');
            }
            if (isset($rule['bail']) && !is_bool($rule['bail'])) {
                throw new FormSchemaException('验证 bail 必须为布尔值', $rulePath . '/bail');
            }
            if (isset($rule['when'])) {
                $this->validateValidationCondition($rule['when'], $rulePath . '/when');
            }
            $type = (string) $rule['type'];
            if ($type === 'pattern' && (!is_string($rule['value'] ?? null) || !FormSchemaDataValidator::isPatternSafe($rule['value']))) {
                throw new FormSchemaException('正则不安全或不兼容', $rulePath . '/value', 'FORM_SCHEMA_PATTERN_UNSAFE');
            }
            if ($type === 'async') {
                $validator = $rule['validator'] ?? null;
                if (!is_array($validator) || array_is_list($validator) || !is_string($validator['key'] ?? null) || trim($validator['key']) === '') {
                    throw new FormSchemaException('异步验证器 key 不能为空', $rulePath . '/validator/key');
                }
            }
            if ($type === 'items') {
                if (!is_array($rule['value'] ?? null) || !array_is_list($rule['value'])) {
                    throw new FormSchemaException('items.value 必须为规则数组', $rulePath . '/value');
                }
                $this->validateRules($rule['value'], $rulePath . '/value');
            }
            if ($type === 'properties') {
                $properties = $rule['value'] ?? null;
                if (!is_array($properties) || array_is_list($properties)) {
                    throw new FormSchemaException('properties.value 必须为规则对象', $rulePath . '/value');
                }
                foreach ($properties as $property => $propertyRules) {
                    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $property)) {
                        throw new FormSchemaException('属性名称不合法', $rulePath . '/value/' . $this->escapePointer((string) $property));
                    }
                    $this->validateRules($propertyRules, $rulePath . '/value/' . $this->escapePointer((string) $property));
                }
            }
            $this->validateSafeValue($rule, $rulePath);
        }
    }

    private function validateValidationCondition(mixed $condition, string $path): void
    {
        if (!is_array($condition) || array_is_list($condition)) {
            throw new FormSchemaException('验证条件必须为对象', $path);
        }
        $operator = (string) ($condition['op'] ?? '');
        if (!in_array($operator, self::CONDITION_OPERATORS, true)) {
            throw new FormSchemaException('验证条件操作符未注册', $path . '/op');
        }
        if (in_array($operator, ['and', 'or'], true)) {
            $conditions = $condition['conditions'] ?? null;
            if (!is_array($conditions) || !array_is_list($conditions) || $conditions === []) {
                throw new FormSchemaException('验证条件组不能为空', $path . '/conditions');
            }
            foreach ($conditions as $index => $nested) $this->validateValidationCondition($nested, $path . '/conditions/' . $index);
            return;
        }
        if ($operator === 'not') {
            $this->validateValidationCondition($condition['condition'] ?? null, $path . '/condition');
            return;
        }
        if (!preg_match('/^[a-z][a-z0-9_.]{0,100}$/', (string) ($condition['field'] ?? ''))) {
            throw new FormSchemaException('验证条件字段不合法', $path . '/field');
        }
        if ($operator === 'matches' && (!is_string($condition['value'] ?? null) || !FormSchemaDataValidator::isPatternSafe($condition['value']))) {
            throw new FormSchemaException('验证条件正则不安全或不兼容', $path . '/value', 'FORM_SCHEMA_PATTERN_UNSAFE');
        }
    }

    private function validateEvents(mixed $events, string $path): void
    {
        if (!is_array($events) || array_is_list($events) && $events !== []) {
            throw new FormSchemaException('events 必须为对象', $path);
        }
        foreach ($events as $event => $actions) {
            if (!in_array($event, self::EVENTS, true)) {
                throw new FormSchemaException('事件未注册', $path . '/' . $event);
            }
            $this->validateActionChain($actions, $path . '/' . $event);
        }
    }

    private function validateGlobalActions(array $actions, string $path): void
    {
        if (count($actions) > self::MAX_ACTIONS) {
            throw new FormSchemaException('全局动作数量超过限制', $path, 'FORM_SCHEMA_LIMIT_EXCEEDED');
        }
        foreach ($actions as $index => $action) {
            if (!is_array($action)) {
                throw new FormSchemaException('全局动作必须为对象', $path . '/' . $index);
            }
            if (array_key_exists('type', $action)) {
                $this->validateActionChain([$action], $path);
                continue;
            }
            $actionPath = $path . '/' . $index;
            if (!is_string($action['id'] ?? null) || trim($action['id']) === '') {
                throw new FormSchemaException('全局动作 ID 不能为空', $actionPath . '/id');
            }
            if (!in_array($action['event'] ?? null, self::EVENTS, true)) {
                throw new FormSchemaException('全局动作事件未注册', $actionPath . '/event');
            }
            $this->validateActionChain($action['steps'] ?? null, $actionPath . '/steps');
        }
    }

    private function validateActionChain(mixed $actions, string $path): void
    {
        if (!is_array($actions) || !array_is_list($actions)) {
            throw new FormSchemaException('动作链必须为数组', $path);
        }
        if (count($actions) > self::MAX_ACTIONS) {
            throw new FormSchemaException('动作链数量超过限制', $path, 'FORM_SCHEMA_LIMIT_EXCEEDED');
        }
        foreach ($actions as $index => $action) {
            $actionPath = $path . '/' . $index;
            $type = is_array($action) ? (string) ($action['type'] ?? '') : '';
            if (!in_array($type, self::ACTIONS, true)) {
                throw new FormSchemaException('动作未注册：' . $type, $actionPath . '/type', 'FORM_ACTION_NOT_REGISTERED');
            }
            [$required, $allowed] = self::ACTION_PARAMETERS[$type];
            foreach ($required as $parameter) {
                if (!array_key_exists($parameter, $action) || $action[$parameter] === '') {
                    throw new FormSchemaException('动作缺少参数：' . $parameter, $actionPath . '/' . $parameter);
                }
            }
            foreach (array_keys($action) as $parameter) {
                if ($parameter !== 'type' && !in_array($parameter, $allowed, true)) {
                    throw new FormSchemaException('动作参数未授权：' . $parameter, $actionPath . '/' . $parameter, 'FORM_ACTION_PARAMETER_NOT_ALLOWED');
                }
            }
            if ($type === 'request' && isset($action['concurrency']) && !in_array($action['concurrency'], ['parallel', 'latest', 'queue', 'drop'], true)) {
                throw new FormSchemaException('request 并发策略不合法', $actionPath . '/concurrency');
            }
            $this->validateSafeValue($action, $actionPath);
        }
    }

    private function validateConditions(mixed $conditions, string $field, string $path, array &$edges): void
    {
        if (!is_array($conditions) || !array_is_list($conditions)) {
            throw new FormSchemaException('conditions 必须为数组', $path);
        }
        foreach ($conditions as $index => $condition) {
            $conditionPath = $path . '/' . $index;
            if (!is_array($condition) || !is_array($condition['when'] ?? null) || !is_array($condition['then'] ?? null)) {
                throw new FormSchemaException('条件结构不合法', $conditionPath);
            }
            $this->validateValidationCondition($condition['when'], $conditionPath . '/when');
            $action = (string) ($condition['then']['action'] ?? '');
            if (!in_array($action, self::ACTIONS, true)) {
                throw new FormSchemaException('条件动作未注册', $conditionPath . '/then/action');
            }
            if ($action !== 'setValue') {
                continue;
            }
            $target = (string) ($condition['then']['target'] ?? $field);
            $sources = $this->conditionFields($condition['when']);
            if ($sources === [] || $target === '') {
                throw new FormSchemaException('条件字段不能为空', $conditionPath . '/then/target');
            }
            foreach ($sources as $source) {
                $edges[$source][] = ['target' => $target, 'path' => $conditionPath . '/then/target'];
            }
        }
    }

    private function conditionFields(array $condition): array
    {
        $op = (string) ($condition['op'] ?? '');
        if (in_array($op, ['and', 'or'], true)) {
            return array_values(array_unique(array_merge(...array_map(
                fn (array $item): array => $this->conditionFields($item),
                array_values(array_filter((array) ($condition['conditions'] ?? []), 'is_array'))
            ))));
        }
        if ($op === 'not' && is_array($condition['condition'] ?? null)) {
            return $this->conditionFields($condition['condition']);
        }
        $field = trim((string) ($condition['field'] ?? ''));
        return $field === '' ? [] : [$field];
    }

    private function validateFieldReferences(array $nodes, string $path, array $fields): void
    {
        foreach ($nodes as $index => $node) {
            if (!is_array($node)) continue;
            $nodePath = $path . '/' . $index;
            $source = (array) ($node['dataSource'] ?? []);
            foreach ((array) ($source['dependsOn'] ?? []) as $dependencyIndex => $dependency) {
                $root = explode('.', (string) $dependency)[0];
                if (!isset($fields[$root])) throw new FormSchemaException('数据源依赖字段不存在', $nodePath . '/dataSource/dependsOn/' . $dependencyIndex, 'FORM_FIELD_REFERENCE_INVALID');
            }
            foreach ((array) ($node['validation'] ?? []) as $ruleIndex => $rule) {
                if (is_array($rule['when'] ?? null)) $this->assertConditionReferences($rule['when'], $nodePath . '/validation/' . $ruleIndex . '/when', $fields);
            }
            foreach ((array) ($node['conditions'] ?? []) as $conditionIndex => $condition) {
                if (!is_array($condition)) continue;
                if (is_array($condition['when'] ?? null)) $this->assertConditionReferences($condition['when'], $nodePath . '/conditions/' . $conditionIndex . '/when', $fields);
                $target = (string) ($condition['then']['target'] ?? '');
                if ($target !== '' && !isset($fields[$target])) throw new FormSchemaException('条件目标字段不存在', $nodePath . '/conditions/' . $conditionIndex . '/then/target', 'FORM_FIELD_REFERENCE_INVALID');
            }
            foreach ((array) ($node['events'] ?? []) as $event => $actions) {
                $this->assertActionReferences((array) $actions, $nodePath . '/events/' . $event, $fields);
            }
            $this->validateFieldReferences((array) ($node['children'] ?? []), $nodePath . '/children', $fields);
        }
    }

    private function validateTopLevelDataSourceReferences(array $sources, string $path, array $fields): void
    {
        foreach ($sources as $index => $source) {
            if (!is_array($source)) continue;
            foreach ((array) ($source['dependsOn'] ?? []) as $dependencyIndex => $dependency) {
                $root = explode('.', (string) $dependency)[0];
                if (!isset($fields[$root])) throw new FormSchemaException('数据源依赖字段不存在', $path . '/' . $index . '/dependsOn/' . $dependencyIndex, 'FORM_FIELD_REFERENCE_INVALID');
            }
        }
    }

    private function validateGlobalActionReferences(array $actions, string $path, array $fields): void
    {
        foreach ($actions as $index => $action) {
            if (!is_array($action)) continue;
            if (isset($action['type'])) $this->assertActionReferences([$action], $path . '/' . $index, $fields);
            $this->assertActionReferences((array) ($action['steps'] ?? []), $path . '/' . $index . '/steps', $fields);
        }
    }

    private function assertConditionReferences(array $condition, string $path, array $fields): void
    {
        $operator = (string) ($condition['op'] ?? '');
        if (in_array($operator, ['and', 'or'], true)) {
            foreach ((array) ($condition['conditions'] ?? []) as $index => $nested) if (is_array($nested)) $this->assertConditionReferences($nested, $path . '/conditions/' . $index, $fields);
            return;
        }
        if ($operator === 'not' && is_array($condition['condition'] ?? null)) {
            $this->assertConditionReferences($condition['condition'], $path . '/condition', $fields);
            return;
        }
        $field = explode('.', (string) ($condition['field'] ?? ''))[0];
        if (!isset($fields[$field])) throw new FormSchemaException('条件引用字段不存在', $path . '/field', 'FORM_FIELD_REFERENCE_INVALID');
    }

    private function assertActionReferences(array $actions, string $path, array $fields): void
    {
        foreach ($actions as $index => $action) {
            if (!is_array($action)) continue;
            foreach (['source', 'target'] as $parameter) {
                $field = (string) ($action[$parameter] ?? '');
                if ($field !== '' && !isset($fields[$field])) throw new FormSchemaException('动作引用字段不存在', $path . '/' . $index . '/' . $parameter, 'FORM_FIELD_REFERENCE_INVALID');
            }
        }
    }

    private function validateConditionCycles(array $edges): void
    {
        $state = [];
        $visit = function (string $field) use (&$visit, &$state, $edges): void {
            $state[$field] = 1;
            foreach ($edges[$field] ?? [] as $edge) {
                $target = $edge['target'];
                if (($state[$target] ?? 0) === 1) {
                    throw new FormSchemaException('条件存在循环依赖', $edge['path'], 'FORM_CONDITION_CYCLE');
                }
                if (($state[$target] ?? 0) === 0) {
                    $visit($target);
                }
            }
            $state[$field] = 2;
        };
        foreach (array_keys($edges) as $field) {
            if (($state[$field] ?? 0) === 0) {
                $visit($field);
            }
        }
    }

    private function validateAccess(array $access, string $path): void
    {
        foreach ($access as $operation => $permissions) {
            if ($operation === 'include') {
                if (!in_array($permissions, ['auto', 'always', 'never'], true)) {
                    throw new FormSchemaException('access include 配置不合法', $path . '/include');
                }
                continue;
            }
            if (!in_array($operation, ['read', 'write'], true) || !is_array($permissions) || !array_is_list($permissions)) {
                throw new FormSchemaException('access 配置不合法', $path . '/' . $operation);
            }
            foreach ($permissions as $index => $permission) {
                if (!is_string($permission) || trim($permission) === '') {
                    throw new FormSchemaException('access 权限标识不合法', $path . '/' . $operation . '/' . $index);
                }
            }
        }
    }

    private function validateDatabase(array $database, string $path): void
    {
        foreach (['columnType', 'comment'] as $key) {
            if (isset($database[$key]) && !is_string($database[$key])) {
                throw new FormSchemaException('database.' . $key . ' 必须为字符串', $path . '/' . $key);
            }
        }
        foreach (['nullable', 'unsigned'] as $key) {
            if (isset($database[$key]) && !is_bool($database[$key])) {
                throw new FormSchemaException('database.' . $key . ' 必须为布尔值', $path . '/' . $key);
            }
        }
        if (isset($database['index']) && !in_array($database['index'], ['none', 'index', 'unique'], true)) {
            throw new FormSchemaException('database.index 不合法', $path . '/index');
        }
    }

    private function validateList(array $list, string $path): void
    {
        foreach (['show', 'sort'] as $key) {
            if (isset($list[$key]) && !is_bool($list[$key])) {
                throw new FormSchemaException('list.' . $key . ' 必须为布尔值', $path . '/' . $key);
            }
        }
        if (isset($list['width']) && (!is_int($list['width']) || $list['width'] < 0)) {
            throw new FormSchemaException('list.width 必须为非负整数', $path . '/width');
        }
    }

    private function validateSafeValue(mixed $value, string $path): void
    {
        if (is_string($value) && preg_match('/(?:javascript\s*:|url\s*\(|expression\s*\()/i', $value)) {
            throw new FormSchemaException('危险属性值不允许使用', $path, 'FORM_SCHEMA_UNSAFE');
        }
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $name => $nested) {
            $normalized = strtolower((string) $name);
            $nestedPath = $path . '/' . $this->escapePointer((string) $name);
            if (str_starts_with($normalized, 'on') || in_array($normalized, ['innerhtml', 'is'], true)) {
                throw new FormSchemaException('危险属性不允许使用', $nestedPath, 'FORM_SCHEMA_UNSAFE');
            }
            $this->validateSafeValue($nested, $nestedPath);
        }
    }

    private function validateComponentProps(array $props, array $component, string $path): void
    {
        $allowed = array_fill_keys((array) ($component['allowedAttrs'] ?? []), true);
        foreach (array_keys($props) as $property) {
            if (!isset($allowed[$property])) {
                $this->validateSafeValue($props[$property], $path . '/' . $property);
                throw new FormSchemaException('核心组件属性未授权：' . $property, $path . '/' . $property, 'FORM_SCHEMA_UNSAFE');
            }
        }
    }

    private function validateComponentAttrs(array $attrs, string $path): void
    {
        $allowed = array_fill_keys(['autocomplete', 'aria-label', 'aria-describedby', 'name', 'id'], true);
        foreach (array_keys($attrs) as $attribute) {
            if (!isset($allowed[$attribute])) {
                throw new FormSchemaException('组件原生属性未授权：' . $attribute, $path . '/' . $attribute, 'FORM_SCHEMA_UNSAFE');
            }
        }
    }

    private function validatePluginProps(array $props, array $component, string $path): void
    {
        $allowed = array_fill_keys(array_keys((array) ($component['propertySchema']['properties'] ?? [])), true);
        foreach (array_keys($props) as $property) {
            if (!isset($allowed[$property])) {
                throw new FormSchemaException('插件组件属性未授权：' . $property, $path . '/' . $property, 'FORM_SCHEMA_UNSAFE');
            }
        }
    }

    private function validatePluginEvents(array $events, array $component, string $path): void
    {
        $allowed = (array) ($component['allowedEvents'] ?? []);
        foreach (array_keys($events) as $event) {
            if (!in_array($event, $allowed, true)) {
                throw new FormSchemaException('插件组件事件未授权：' . $event, $path . '/' . $event, 'FORM_SCHEMA_UNSAFE');
            }
        }
    }

    private function normalizeNodeDataSources(array &$nodes): void
    {
        foreach ($nodes as &$node) {
            if (!is_array($node)) {
                continue;
            }
            if (is_array($node['dataSource'] ?? null)) {
                $this->normalizeDataSource($node['dataSource']);
            }
            if (is_array($node['children'] ?? null)) {
                $this->normalizeNodeDataSources($node['children']);
            }
        }
        unset($node);
    }

    private function normalizeDataSource(array &$dataSource): void
    {
        if (!isset($dataSource['kind']) && is_string($dataSource['mode'] ?? null)) {
            $dataSource['kind'] = $dataSource['mode'];
        }
    }

    private function escapePointer(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
