<?php

declare(strict_types=1);

namespace app\common\form\registry;

use InvalidArgumentException;
use JsonException;

/** 表单字段、布局与关系容器的权威能力注册表。 */
final class FieldCapabilityRegistry
{
    private const VERSION = '2.0.0';
    private const LAYOUT_TYPES = ['group', 'grid', 'divider', 'text', 'collapse', 'tabs'];
    private const RELATION_CONTAINER_TYPES = ['repeatable', 'subform'];
    private const FORMAL_TYPES = [
        'input', 'password', 'textarea', 'mention', 'number', 'select', 'selectV2', 'treeSelect', 'cascader',
        'radio', 'checkbox', 'switch', 'transfer', 'date', 'datetime', 'daterange', 'datetimerange', 'time',
        'timeSelect', 'slider', 'rate', 'color', 'image', 'images', 'file', 'files', 'dictionary', 'relation',
        'department', 'user', 'richtext', 'json', 'hidden', 'readonly',
    ];
    private const TYPES = [
        'input', 'password', 'textarea', 'mention', 'number', 'select', 'selectV2', 'treeSelect', 'cascader',
        'radio', 'checkbox', 'switch', 'transfer', 'date', 'datetime', 'daterange', 'datetimerange', 'time',
        'timeSelect', 'slider', 'rate', 'color', 'image', 'images', 'file', 'files', 'dictionary', 'relation',
        'department', 'user', 'richtext', 'json', 'hidden', 'readonly', 'repeatable', 'subform', 'group', 'grid',
        'divider', 'text', 'collapse', 'tabs',
    ];
    private const LABELS = [
        'input' => '单行输入', 'password' => '密码输入', 'textarea' => '多行输入', 'mention' => '提及输入',
        'number' => '数字输入', 'select' => '下拉选择', 'selectV2' => '虚拟化选择', 'treeSelect' => '树形选择',
        'cascader' => '级联选择', 'radio' => '单选框组', 'checkbox' => '复选框组', 'switch' => '开关',
        'transfer' => '穿梭框', 'date' => '日期', 'datetime' => '日期时间', 'daterange' => '日期范围',
        'datetimerange' => '日期时间范围', 'time' => '时间', 'timeSelect' => '时间选择', 'slider' => '滑块',
        'rate' => '评分', 'color' => '颜色', 'image' => '单图上传', 'images' => '多图上传',
        'file' => '单文件上传', 'files' => '多文件上传', 'dictionary' => '字典选择', 'relation' => '关联数据',
        'department' => '部门选择', 'user' => '用户选择', 'richtext' => '富文本', 'json' => 'JSON 编辑器',
        'hidden' => '隐藏字段', 'readonly' => '只读文本', 'repeatable' => '重复行', 'subform' => '子表单',
        'group' => '分组', 'grid' => '栅格', 'divider' => '分割线', 'text' => '说明文字',
        'collapse' => '折叠面板', 'tabs' => '标签页',
    ];
    private const COLUMN_TYPES = [
        'input' => 'varchar(255)', 'password' => 'varchar(255)', 'textarea' => 'text', 'mention' => 'varchar(500)',
        'number' => 'int', 'select' => 'varchar(100)', 'selectV2' => 'varchar(100)', 'treeSelect' => 'bigint',
        'cascader' => 'varchar(255)', 'radio' => 'varchar(100)', 'checkbox' => 'json', 'switch' => 'tinyint(1)',
        'transfer' => 'json', 'date' => 'date', 'datetime' => 'datetime', 'daterange' => 'json',
        'datetimerange' => 'json', 'time' => 'time', 'timeSelect' => 'time', 'slider' => 'int', 'rate' => 'tinyint',
        'color' => 'varchar(20)', 'image' => 'varchar(500)', 'images' => 'json', 'file' => 'json', 'files' => 'json',
        'dictionary' => 'varchar(100)', 'relation' => 'bigint', 'department' => 'bigint', 'user' => 'bigint',
        'richtext' => 'longtext', 'json' => 'json', 'hidden' => 'varchar(255)', 'readonly' => 'varchar(255)',
    ];
    private const ALLOWED_COLUMN_TYPES = [
        'tinyint(1)', 'tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal(10,2)', 'decimal(12,2)',
        'float', 'double', 'char(1)', 'char(10)', 'varchar(50)', 'varchar(100)', 'varchar(255)', 'varchar(500)',
        'text', 'mediumtext', 'longtext', 'date', 'datetime', 'timestamp', 'time', 'year', 'json', 'binary(16)',
        'varbinary(255)', 'blob', 'mediumblob', 'longblob',
    ];
    private const VALUE_TYPES = [
        'number' => 'number', 'switch' => 'boolean', 'slider' => 'number', 'rate' => 'number',
        'checkbox' => 'array', 'transfer' => 'array', 'daterange' => 'array', 'datetimerange' => 'array',
        'cascader' => 'array', 'images' => 'array', 'file' => 'array', 'files' => 'array', 'json' => 'object',
        'repeatable' => 'array', 'subform' => 'array',
    ];
    private const ALLOWED_ATTRS = [
        'input' => ['placeholder', 'clearable', 'disabled', 'readonly', 'maxlength'],
        'password' => ['placeholder', 'clearable', 'disabled', 'readonly', 'maxlength', 'showPassword'],
        'textarea' => ['placeholder', 'clearable', 'disabled', 'readonly', 'maxlength', 'rows', 'autosize'],
        'mention' => ['placeholder', 'disabled', 'readonly', 'rows', 'type'],
        'number' => ['disabled', 'readonly', 'min', 'max', 'step', 'precision', 'controlsPosition'],
        'select' => ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable'],
        'selectV2' => ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable'],
        'treeSelect' => ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable', 'checkStrictly'],
        'cascader' => ['placeholder', 'clearable', 'disabled', 'filterable', 'props'],
        'radio' => ['disabled', 'size', 'textColor', 'fill'],
        'checkbox' => ['disabled', 'min', 'max', 'size', 'fill', 'textColor'],
        'switch' => ['disabled', 'loading', 'activeValue', 'inactiveValue', 'activeText', 'inactiveText'],
        'transfer' => ['disabled', 'filterable', 'filterPlaceholder', 'titles', 'buttonTexts'],
        'date' => ['placeholder', 'disabled', 'readonly', 'clearable', 'format', 'valueFormat', 'rangeSeparator', 'startPlaceholder', 'endPlaceholder'],
        'datetime' => ['placeholder', 'disabled', 'readonly', 'clearable', 'format', 'valueFormat', 'rangeSeparator', 'startPlaceholder', 'endPlaceholder'],
        'daterange' => ['placeholder', 'disabled', 'readonly', 'clearable', 'format', 'valueFormat', 'rangeSeparator', 'startPlaceholder', 'endPlaceholder'],
        'datetimerange' => ['placeholder', 'disabled', 'readonly', 'clearable', 'format', 'valueFormat', 'rangeSeparator', 'startPlaceholder', 'endPlaceholder'],
        'time' => ['placeholder', 'disabled', 'readonly', 'clearable', 'format', 'valueFormat'],
        'timeSelect' => ['placeholder', 'disabled', 'clearable', 'start', 'end', 'step', 'minTime', 'maxTime'],
        'slider' => ['disabled', 'min', 'max', 'step', 'showStops', 'range'],
        'rate' => ['disabled', 'max', 'allowHalf', 'showText', 'showScore'],
        'color' => ['disabled', 'showAlpha', 'colorFormat', 'predefine'],
        'image' => ['disabled', 'maxSize', 'bizType', 'accept'],
        'images' => ['disabled', 'maxSize', 'maxCount', 'bizType', 'accept'],
        'file' => ['disabled', 'maxSize', 'maxCount', 'bizType', 'accept'],
        'files' => ['disabled', 'maxSize', 'maxCount', 'bizType', 'accept'],
        'dictionary' => ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable'],
        'relation' => ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable'],
        'department' => ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable', 'checkStrictly'],
        'user' => ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable'],
        'richtext' => ['placeholder', 'disabled', 'readonly', 'maxlength', 'rows'],
        'json' => ['placeholder', 'disabled', 'readonly', 'rows', 'autosize'],
        'hidden' => ['name'], 'readonly' => ['type', 'size', 'truncated'],
        'repeatable' => ['disabled', 'minRows', 'maxRows', 'primaryKey', 'columns'],
        'subform' => ['disabled', 'minRows', 'maxRows', 'primaryKey', 'columns'],
        'group' => ['title', 'shadow'], 'grid' => ['columns', 'gutter', 'justify', 'align'],
        'divider' => ['contentPosition', 'direction', 'borderStyle'],
        'text' => ['content', 'type', 'size', 'truncated'], 'collapse' => ['title', 'accordion'],
        'tabs' => ['tabs', 'type', 'tabPosition', 'stretch'],
    ];
    private const DEFAULT_PROPS = [
        'input' => ['clearable' => true], 'password' => ['showPassword' => true], 'textarea' => ['rows' => 4],
        'mention' => ['type' => 'textarea', 'rows' => 3], 'number' => ['controlsPosition' => 'right'],
        'select' => ['clearable' => true], 'selectV2' => ['clearable' => true],
        'treeSelect' => ['clearable' => true, 'checkStrictly' => true], 'cascader' => ['clearable' => true],
        'switch' => ['activeValue' => 1, 'inactiveValue' => 0], 'transfer' => ['filterable' => true],
        'date' => ['valueFormat' => 'YYYY-MM-DD'], 'datetime' => ['valueFormat' => 'YYYY-MM-DD HH:mm:ss'],
        'daterange' => ['valueFormat' => 'YYYY-MM-DD', 'rangeSeparator' => '至'],
        'datetimerange' => ['valueFormat' => 'YYYY-MM-DD HH:mm:ss', 'rangeSeparator' => '至'],
        'time' => ['valueFormat' => 'HH:mm:ss'], 'timeSelect' => ['start' => '00:00', 'step' => '00:30', 'end' => '23:30'],
        'slider' => ['min' => 0, 'max' => 100], 'rate' => ['max' => 5], 'color' => ['showAlpha' => true],
        'image' => ['maxSize' => 5, 'bizType' => 'image'],
        'images' => ['maxSize' => 5, 'maxCount' => 9, 'bizType' => 'image'],
        'file' => ['maxCount' => 1, 'bizType' => 'file'], 'files' => ['maxCount' => 0, 'bizType' => 'file'],
        'dictionary' => ['clearable' => true], 'relation' => ['clearable' => true, 'filterable' => true],
        'department' => ['clearable' => true, 'checkStrictly' => true],
        'user' => ['clearable' => true, 'filterable' => true], 'richtext' => ['rows' => 8], 'json' => ['rows' => 8],
        'repeatable' => ['minRows' => 0, 'maxRows' => 0, 'primaryKey' => 'id', 'columns' => []],
        'subform' => ['minRows' => 0, 'maxRows' => 0, 'primaryKey' => 'id', 'columns' => []],
        'group' => ['title' => '字段分组'], 'grid' => ['columns' => 2, 'gutter' => 16],
        'divider' => ['contentPosition' => 'left'], 'text' => ['content' => '说明文字'],
        'collapse' => ['title' => '折叠区域'], 'tabs' => ['tabs' => ['标签一', '标签二']],
    ];
    private const OPTION_TYPES = [
        'mention', 'select', 'selectV2', 'treeSelect', 'cascader', 'radio', 'checkbox', 'transfer',
        'dictionary', 'relation', 'department', 'user',
    ];
    private const LIST_FORMATTERS = [
        'tag', 'image', 'images', 'date', 'datetime', 'time', 'money', 'number', 'percent', 'switch',
        'boolean', 'link', 'email', 'phone', 'json',
    ];
    private const LIST_FILTERS = [
        'eq', 'ne', 'like', 'not_like', 'starts_with', 'ends_with', 'gt', 'gte', 'lt', 'lte', 'range',
        'date', 'in', 'not_in', 'is_null', 'not_null',
    ];
    private const VALIDATION_RULES = [
        'required', 'min', 'max', 'minlen', 'maxlen', 'length', 'pattern', 'email', 'url', 'number',
        'integer', 'array', 'object', 'async',
    ];
    private const ALLOWED_EVENTS = ['change', 'blur', 'focus', 'click', 'clear', 'select', 'submit', 'reset', 'mounted'];
    private const COLUMN_TYPE_GROUPS = [
        'boolean' => ['tinyint(1)'],
        'numeric' => ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal(10,2)', 'decimal(12,2)', 'float', 'double'],
        'text' => ['char(1)', 'char(10)', 'varchar(50)', 'varchar(100)', 'varchar(255)', 'varchar(500)', 'text', 'mediumtext', 'longtext'],
        'date' => ['date'], 'datetime' => ['datetime', 'timestamp'], 'time' => ['time'], 'year' => ['year'],
        'json' => ['json'], 'binary' => ['binary(16)', 'varbinary(255)', 'blob', 'mediumblob', 'longblob'],
        'relation' => ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'char(10)', 'varchar(50)', 'varchar(100)', 'binary(16)'],
    ];
    private const COLUMN_CATEGORIES = [
        'input' => ['text'], 'password' => ['text'], 'textarea' => ['text'], 'mention' => ['text'],
        'number' => ['numeric'], 'select' => ['text', 'numeric'], 'selectV2' => ['text', 'numeric'],
        'treeSelect' => ['relation'], 'cascader' => ['text', 'json'], 'radio' => ['text', 'numeric'],
        'checkbox' => ['json'], 'switch' => ['boolean'], 'transfer' => ['json'],
        'date' => ['date'], 'datetime' => ['datetime'], 'daterange' => ['json'], 'datetimerange' => ['json'],
        'time' => ['time'], 'timeSelect' => ['time'], 'slider' => ['numeric'], 'rate' => ['numeric'],
        'color' => ['text'], 'image' => ['text'], 'images' => ['json'], 'file' => ['json'], 'files' => ['json'],
        'dictionary' => ['text', 'numeric'], 'relation' => ['relation'], 'department' => ['relation'], 'user' => ['relation'],
        'richtext' => ['text'], 'json' => ['json'], 'hidden' => ['text'], 'readonly' => ['text', 'numeric', 'date', 'datetime', 'time', 'json'],
    ];
    private const BOOLEAN_PROPERTIES = [
        'clearable', 'disabled', 'readonly', 'showPassword', 'autosize', 'multiple', 'filterable', 'checkStrictly',
        'loading', 'showStops', 'range', 'allowHalf', 'showText', 'showScore', 'showAlpha', 'truncated', 'accordion', 'stretch',
    ];
    private const INTEGER_PROPERTIES = ['maxlength', 'rows', 'precision', 'maxCount', 'minRows', 'maxRows', 'columns', 'gutter', 'activeValue', 'inactiveValue'];
    private const NUMBER_PROPERTIES = ['min', 'max', 'step', 'maxSize'];
    private const ARRAY_PROPERTIES = ['titles', 'buttonTexts', 'predefine', 'columns', 'tabs'];
    private const OBJECT_PROPERTIES = ['props'];
    private const DANGEROUS_ATTRIBUTES = ['onclick', 'onerror', 'onload', 'innerHTML', 'outerHTML', 'srcdoc', 'style'];

    /** @var array<string, array<string, mixed>> */
    private array $definitions;

    public function __construct(array $pluginDefinitions = [])
    {
        $this->definitions = $this->coreDefinitions();
        foreach ($pluginDefinitions as $definition) {
            $this->registerPlugin($definition);
        }
        ksort($this->definitions, SORT_STRING);
    }

    public function definitions(): array
    {
        return $this->definitions;
    }

    public function types(): array
    {
        return array_keys($this->definitions);
    }

    public function has(string $type): bool
    {
        return isset($this->definitions[$type]);
    }

    public function get(string $type): array
    {
        if (!$this->has($type)) {
            throw new InvalidArgumentException('组件能力未注册：' . $type);
        }
        return $this->definitions[$type];
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function canonical(): string
    {
        try {
            return json_encode($this->canonicalize($this->definitions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('字段能力无法规范化：' . $exception->getMessage(), 0, $exception);
        }
    }

    public function hash(): string
    {
        return hash('sha256', $this->canonical());
    }

    public function catalog(): array
    {
        return [
            'schemaVersion' => 2,
            'version' => $this->version(),
            'hash' => $this->hash(),
            'components' => array_values($this->definitions),
        ];
    }

    private function coreDefinitions(): array
    {
        $definitions = [];
        foreach (self::TYPES as $type) {
            $layout = in_array($type, self::LAYOUT_TYPES, true);
            $relationContainer = in_array($type, self::RELATION_CONTAINER_TYPES, true);
            $business = in_array($type, ['dictionary', 'relation', 'department', 'user', 'richtext', 'json'], true);
            $kind = $layout ? 'layout' : ($relationContainer ? 'relation-container' : ($business ? 'business' : 'field'));
            $definitions[$type] = [
                'type' => $type,
                'namespace' => 'core',
                'component' => $type,
                'label' => self::LABELS[$type],
                'kind' => $kind,
                'group' => $this->group($type, $kind),
                'valueType' => self::VALUE_TYPES[$type] ?? ($layout ? 'object' : 'string'),
                'defaultValue' => $this->defaultValue($type, $layout),
                'defaultColumnType' => self::COLUMN_TYPES[$type] ?? '',
                'allowedColumnTypes' => $layout || $relationContainer ? [] : $this->allowedColumnTypes($type),
                'defaultProps' => self::DEFAULT_PROPS[$type] ?? [],
                'propertySchema' => $this->propertySchema($type),
                'optionsCapability' => in_array($type, self::OPTION_TYPES, true) ? 'supported' : 'none',
                'validationRules' => $layout ? [] : self::VALIDATION_RULES,
                'listFormatters' => $layout || $relationContainer ? [] : self::LIST_FORMATTERS,
                'listFilters' => $layout || $relationContainer ? [] : self::LIST_FILTERS,
                'runtimeRenderer' => 'core:registry',
                'renderer' => 'core:registry',
                'templateRenderer' => in_array($type, self::FORMAL_TYPES, true) ? ($type === 'number' ? 'inputNumber' : $type) : '',
                'codec' => $type === 'switch' ? 'core:switch-integer' : (in_array($type, ['checkbox', 'transfer', 'daterange', 'datetimerange', 'images', 'file', 'files', 'json'], true) ? 'core:json' : 'core:identity'),
                'allowedAttrs' => self::ALLOWED_ATTRS[$type],
                'allowedEvents' => self::ALLOWED_EVENTS,
                'supportsDynamic' => true,
                'supportsFormalGeneration' => in_array($type, self::FORMAL_TYPES, true),
                'supportsIndex' => !$layout && !$relationContainer,
                'supportsRelation' => in_array($type, ['relation', 'repeatable', 'subform'], true),
                'contentKind' => $layout ? 'layout' : ($relationContainer ? 'relation' : 'data'),
            ];
        }
        return $definitions;
    }

    private function registerPlugin(mixed $definition): void
    {
        if (!is_array($definition)) {
            throw new InvalidArgumentException('插件字段能力定义必须为数组');
        }
        $type = trim((string) ($definition['type'] ?? ''));
        $namespace = trim((string) ($definition['namespace'] ?? ''));
        if (isset($this->definitions[$type]) || in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('插件不得覆盖已注册字段能力：' . $type);
        }
        if ($type === '' || $namespace === '' || $namespace === 'core' || !str_starts_with($type, $namespace . ':')) {
            throw new InvalidArgumentException('插件字段能力 type 与 namespace 不合法');
        }
        $definition = array_replace($this->pluginDefaults($type, $namespace), $definition, [
            'type' => $type,
            'namespace' => $namespace,
            'runtimeRenderer' => 'plugin:module',
            'renderer' => 'plugin:module',
        ]);
        if (is_array($definition['propertySchema']) && !array_key_exists('additionalProperties', $definition['propertySchema'])) {
            $definition['propertySchema']['additionalProperties'] = false;
        }
        if (!array_key_exists('allowedAttrs', $definition) || $definition['allowedAttrs'] === []) {
            $definition['allowedAttrs'] = array_keys((array) ($definition['propertySchema']['properties'] ?? []));
        }
        $this->validatePluginDefinition($definition);
        $this->definitions[$type] = $definition;
    }

    private function pluginDefaults(string $type, string $namespace): array
    {
        return [
            'type' => $type,
            'namespace' => $namespace,
            'component' => '',
            'label' => $type,
            'kind' => 'field',
            'group' => '业务控件',
            'valueType' => 'string',
            'defaultValue' => '',
            'defaultColumnType' => '',
            'allowedColumnTypes' => [],
            'defaultProps' => [],
            'propertySchema' => ['type' => 'object', 'additionalProperties' => false, 'properties' => []],
            'optionsCapability' => 'none',
            'validationRules' => [],
            'listFormatters' => [],
            'listFilters' => [],
            'runtimeRenderer' => 'plugin:module',
            'renderer' => 'plugin:module',
            'templateRenderer' => '',
            'codec' => '',
            'allowedAttrs' => [],
            'allowedEvents' => [],
            'supportsDynamic' => true,
            'supportsFormalGeneration' => false,
            'supportsIndex' => false,
            'supportsRelation' => false,
            'contentKind' => 'data',
        ];
    }

    private function defaultValue(string $type, bool $layout): mixed
    {
        if (in_array($type, ['checkbox', 'transfer', 'daterange', 'datetimerange', 'cascader', 'images', 'file', 'files', 'repeatable', 'subform'], true)) return [];
        if ($type === 'json') return new \stdClass();
        if (in_array($type, ['number', 'slider', 'rate'], true)) return 0;
        if ($type === 'switch') return false;
        return $layout ? null : '';
    }

    private function allowedColumnTypes(string $type): array
    {
        $types = [];
        foreach (self::COLUMN_CATEGORIES[$type] ?? [] as $category) {
            $types = array_merge($types, self::COLUMN_TYPE_GROUPS[$category]);
        }
        return array_values(array_unique($types));
    }

    private function propertySchema(string $type): array
    {
        $properties = [];
        foreach (self::ALLOWED_ATTRS[$type] as $property) {
            $properties[$property] = $this->propertyDefinition($type, $property);
        }
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => $properties];
    }

    private function propertyDefinition(string $type, string $property): array
    {
        $definition = ['type' => 'string', 'title' => $property];
        if (in_array($property, self::BOOLEAN_PROPERTIES, true)) $definition['type'] = 'boolean';
        if (in_array($property, self::INTEGER_PROPERTIES, true)) $definition['type'] = 'integer';
        if (in_array($property, self::NUMBER_PROPERTIES, true)) $definition['type'] = 'number';
        if (in_array($property, self::ARRAY_PROPERTIES, true)) $definition['type'] = 'array';
        if (in_array($property, self::OBJECT_PROPERTIES, true)) $definition['type'] = 'object';
        if ($property === 'columns' && $type === 'grid') $definition = ['type' => 'integer', 'title' => $property, 'minimum' => 1, 'maximum' => 24];
        if ($property === 'gutter') $definition['minimum'] = 0;
        if (in_array($property, ['maxCount', 'minRows', 'maxRows'], true)) $definition['minimum'] = 0;
        if (array_key_exists($property, self::DEFAULT_PROPS[$type] ?? [])) $definition['default'] = self::DEFAULT_PROPS[$type][$property];
        return $definition;
    }

    private function validatePluginDefinition(array $definition): void
    {
        $required = ['type', 'namespace', 'component', 'label', 'kind', 'group', 'valueType', 'defaultValue', 'defaultColumnType',
            'allowedColumnTypes', 'defaultProps', 'propertySchema', 'optionsCapability', 'validationRules', 'listFormatters',
            'listFilters', 'runtimeRenderer', 'renderer', 'templateRenderer', 'codec', 'allowedAttrs', 'allowedEvents',
            'supportsDynamic', 'supportsFormalGeneration', 'supportsIndex', 'supportsRelation', 'contentKind'];
        if (array_diff($required, array_keys($definition)) !== []) throw new InvalidArgumentException('插件字段能力定义字段不完整');
        foreach (['type', 'namespace', 'component', 'label', 'kind', 'group', 'valueType', 'defaultColumnType', 'optionsCapability', 'runtimeRenderer', 'renderer', 'templateRenderer', 'codec', 'contentKind'] as $key) {
            if (!is_string($definition[$key])) throw new InvalidArgumentException('插件字段能力字段类型错误：' . $key);
        }
        foreach (['type', 'namespace', 'component', 'label', 'kind', 'group', 'valueType', 'optionsCapability', 'codec', 'contentKind'] as $key) {
            if (trim($definition[$key]) === '') throw new InvalidArgumentException('插件字段能力 required 字段不得为空：' . $key);
        }
        if (!in_array($definition['kind'], ['field', 'business', 'layout', 'relation-container'], true)) throw new InvalidArgumentException('插件 kind 不合法');
        if (!in_array($definition['valueType'], ['string', 'number', 'boolean', 'array', 'object'], true)) throw new InvalidArgumentException('插件 valueType 不合法');
        foreach (['allowedColumnTypes', 'defaultProps', 'propertySchema', 'validationRules', 'listFormatters', 'listFilters', 'allowedAttrs', 'allowedEvents'] as $key) {
            if (!is_array($definition[$key])) throw new InvalidArgumentException('插件字段能力字段类型错误：' . $key);
        }
        foreach (['supportsDynamic', 'supportsFormalGeneration', 'supportsIndex', 'supportsRelation'] as $key) {
            if (!is_bool($definition[$key])) throw new InvalidArgumentException('插件字段能力字段类型错误：' . $key);
        }
        foreach (['allowedColumnTypes', 'allowedAttrs', 'allowedEvents'] as $key) {
            foreach ($definition[$key] as $value) if (!is_string($value) || trim($value) === '') throw new InvalidArgumentException('插件字段能力列表项不合法：' . $key);
            if (count($definition[$key]) !== count(array_unique($definition[$key]))) throw new InvalidArgumentException('插件字段能力列表项重复：' . $key);
        }
        $schema = $definition['propertySchema'];
        $properties = $schema['properties'] ?? null;
        if (($schema['type'] ?? null) !== 'object' || ($schema['additionalProperties'] ?? null) !== false || !is_array($properties)) {
            throw new InvalidArgumentException('插件 propertySchema 不合法');
        }
        if (array_keys($properties) !== array_values($definition['allowedAttrs'])) throw new InvalidArgumentException('插件 propertySchema 与 allowedAttrs 不一致');
        foreach ($properties as $property => $propertyDefinition) {
            if (!is_string($property) || $property === '' || !is_array($propertyDefinition)) throw new InvalidArgumentException('插件属性定义不合法');
            if (!in_array($propertyDefinition['type'] ?? null, ['string', 'number', 'integer', 'boolean', 'array', 'object'], true)) throw new InvalidArgumentException('插件属性类型不合法');
        }
        foreach (array_keys($definition['defaultProps']) as $property) if (!array_key_exists($property, $properties)) throw new InvalidArgumentException('插件默认属性未声明 schema');
        foreach ($definition['allowedAttrs'] as $attribute) {
            if (in_array($attribute, self::DANGEROUS_ATTRIBUTES, true) || str_starts_with(strtolower($attribute), 'on')) throw new InvalidArgumentException('插件包含危险属性');
        }
        if ($definition['supportsFormalGeneration'] && trim($definition['templateRenderer']) === '') throw new InvalidArgumentException('正式生成插件必须提供 templateRenderer');
    }

    private function group(string $type, string $kind): string
    {
        if ($kind === 'layout') return '布局控件';
        if ($kind === 'business' || $kind === 'relation-container') return '业务控件';
        if (in_array($type, ['select', 'selectV2', 'treeSelect', 'cascader', 'radio', 'checkbox', 'switch', 'transfer'], true)) return '选择控件';
        if (in_array($type, ['date', 'datetime', 'daterange', 'datetimerange', 'time', 'timeSelect'], true)) return '日期时间';
        if (in_array($type, ['image', 'images', 'file', 'files'], true)) return '上传控件';
        return '基础控件';
    }

    private function canonicalize(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }
        return $value;
    }
}
