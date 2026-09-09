<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\form\registry\FieldCapabilityRegistry;
use app\common\form\registry\FormRegistryFactory;
use app\common\form\schema\FormSchemaValidator;
use app\console\service\FormCrudDefinitionFactory;

function fieldCapabilityExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$registry = new FieldCapabilityRegistry();
$requiredKeys = [
    'type', 'namespace', 'label', 'kind', 'group', 'valueType', 'defaultValue', 'defaultColumnType',
    'allowedColumnTypes', 'defaultProps', 'propertySchema', 'optionsCapability', 'validationRules',
    'listFormatters', 'listFilters', 'runtimeRenderer', 'templateRenderer', 'codec', 'allowedAttrs',
    'allowedEvents', 'supportsDynamic', 'supportsFormalGeneration', 'supportsIndex', 'supportsRelation',
    'contentKind',
];

$definitions = $registry->definitions();
fieldCapabilityExpect(array_keys($definitions) === array_values(array_unique(array_keys($definitions))), '核心能力类型不得重复');
fieldCapabilityExpect(array_keys($definitions) === $registry->types(), '类型列表必须保持确定顺序');
foreach ($definitions as $type => $definition) {
    fieldCapabilityExpect($type === ($definition['type'] ?? null), '能力定义键必须与 type 一致：' . $type);
    fieldCapabilityExpect(array_diff($requiredKeys, array_keys($definition)) === [], '能力定义字段不完整：' . $type);
    fieldCapabilityExpect(($definition['namespace'] ?? null) === 'core', '核心能力必须属于 core：' . $type);
    $propertySchema = $definition['propertySchema'] ?? null;
    $properties = is_array($propertySchema) ? ($propertySchema['properties'] ?? null) : null;
    fieldCapabilityExpect(is_array($properties), 'propertySchema.properties 必须为对象定义：' . $type);
    fieldCapabilityExpect(($propertySchema['type'] ?? null) === 'object', 'propertySchema 必须声明 object：' . $type);
    fieldCapabilityExpect(($propertySchema['additionalProperties'] ?? null) === false, 'propertySchema 必须禁止额外属性：' . $type);
    fieldCapabilityExpect(is_array($definition['defaultProps'] ?? null), 'defaultProps 必须为数组：' . $type);
    fieldCapabilityExpect(is_array($definition['allowedAttrs'] ?? null), 'allowedAttrs 必须为数组：' . $type);
    foreach (array_keys($definition['defaultProps']) as $property) {
        fieldCapabilityExpect(isset($properties[$property]), '默认属性必须有真实 schema：' . $type . '.' . $property);
        fieldCapabilityExpect(in_array($property, $definition['allowedAttrs'], true), '默认属性必须进入白名单：' . $type . '.' . $property);
    }
    fieldCapabilityExpect(array_keys($properties) === array_values($definition['allowedAttrs']), 'allowedAttrs 必须与 propertySchema 一致：' . $type);
    if (($definition['defaultProps'] ?? []) !== []) {
        fieldCapabilityExpect($properties !== [], '有默认属性的组件 propertySchema 不得为空：' . $type);
    }
}

$propertyExpectations = [
    'input' => ['clearable' => 'boolean'],
    'select' => ['clearable' => 'boolean', 'multiple' => 'boolean', 'filterable' => 'boolean'],
    'date' => ['valueFormat' => 'string'],
    'file' => ['maxCount' => 'integer', 'bizType' => 'string'],
    'grid' => ['columns' => 'integer', 'gutter' => 'integer'],
    'relation' => ['clearable' => 'boolean', 'filterable' => 'boolean', 'multiple' => 'boolean'],
    'repeatable' => ['minRows' => 'integer', 'maxRows' => 'integer', 'primaryKey' => 'string', 'columns' => 'array'],
];
foreach ($propertyExpectations as $type => $expectedProperties) {
    $properties = $definitions[$type]['propertySchema']['properties'];
    foreach ($expectedProperties as $property => $expectedType) {
        fieldCapabilityExpect(($properties[$property]['type'] ?? null) === $expectedType, '属性约束类型不真实：' . $type . '.' . $property);
    }
}
fieldCapabilityExpect(($definitions['number']['propertySchema']['properties']['min']['type'] ?? null) === 'number', '数字最小值必须为 number');
fieldCapabilityExpect(($definitions['slider']['propertySchema']['properties']['max']['maximum'] ?? null) === null, '滑块 max 不应伪造最大边界');
fieldCapabilityExpect(($definitions['grid']['propertySchema']['properties']['columns']['minimum'] ?? null) === 1, '栅格列数必须限制为正数');
fieldCapabilityExpect(($definitions['grid']['propertySchema']['properties']['columns']['maximum'] ?? null) === 24, '栅格列数不得超过 24');

$columnCategoryExpectations = [
    'switch' => [['tinyint(1)'], ['varchar(255)', 'json', 'date']],
    'number' => [['int', 'decimal(10,2)', 'double'], ['varchar(255)', 'json', 'date']],
    'date' => [['date'], ['int', 'json', 'time']],
    'datetime' => [['datetime', 'timestamp'], ['int', 'json', 'date']],
    'time' => [['time'], ['int', 'json', 'datetime']],
    'checkbox' => [['json'], ['int', 'date', 'varchar(255)']],
    'input' => [['varchar(255)', 'text'], ['int', 'json', 'date']],
    'relation' => [['bigint', 'int'], ['json', 'text', 'date']],
];
foreach ($columnCategoryExpectations as $type => [$allowed, $forbidden]) {
    $actual = $definitions[$type]['allowedColumnTypes'];
    foreach ($allowed as $columnType) {
        fieldCapabilityExpect(in_array($columnType, $actual, true), '列类型能力缺失：' . $type . ' => ' . $columnType);
    }
    foreach ($forbidden as $columnType) {
        fieldCapabilityExpect(!in_array($columnType, $actual, true), '列类型能力过宽：' . $type . ' => ' . $columnType);
    }
}
fieldCapabilityExpect($definitions['group']['allowedColumnTypes'] === [], '布局容器不得声明列类型');
fieldCapabilityExpect($definitions['repeatable']['allowedColumnTypes'] === [], '关系容器不得声明列类型');
fieldCapabilityExpect($definitions['input']['allowedColumnTypes'] !== $definitions['number']['allowedColumnTypes'], '控件不得共享总列类型白名单');

$valueSemantics = [
    'input' => ['string', '', 'core:identity'],
    'number' => ['number', 0, 'core:identity'],
    'switch' => ['boolean', false, 'core:switch-integer'],
    'checkbox' => ['array', [], 'core:json'],
    'file' => ['array', [], 'core:json'],
    'files' => ['array', [], 'core:json'],
];
foreach ($valueSemantics as $type => [$valueType, $defaultValue, $codec]) {
    fieldCapabilityExpect($definitions[$type]['valueType'] === $valueType, 'valueType 不符合运行时语义：' . $type);
    fieldCapabilityExpect($definitions[$type]['defaultValue'] === $defaultValue, 'defaultValue 不符合运行时语义：' . $type);
    fieldCapabilityExpect($definitions[$type]['codec'] === $codec, 'codec 不符合存储语义：' . $type);
}
fieldCapabilityExpect($definitions['json']['valueType'] === 'object', 'JSON valueType 必须为 object');
fieldCapabilityExpect(is_object($definitions['json']['defaultValue']), 'JSON defaultValue 必须序列化为对象而非数组');
fieldCapabilityExpect($definitions['json']['codec'] === 'core:json', 'JSON 必须使用 JSON codec');
fieldCapabilityExpect(($definitions['switch']['defaultProps']['activeValue'] ?? null) === 1, 'switch activeValue 必须与整数存储一致');
fieldCapabilityExpect(($definitions['switch']['defaultProps']['inactiveValue'] ?? null) === 0, 'switch inactiveValue 必须与整数存储一致');

foreach (FormSchemaValidator::COMPONENTS as $type) {
    fieldCapabilityExpect($registry->has($type), 'Validator 组件未进入字段能力注册表：' . $type);
    fieldCapabilityExpect(($registry->get($type)['supportsDynamic'] ?? false) === true, 'Validator 组件必须支持动态运行：' . $type);
}

$crudComponents = (new ReflectionClass(FormCrudDefinitionFactory::class))->getReflectionConstant('COMPONENTS')?->getValue();
fieldCapabilityExpect(is_array($crudComponents), '无法读取 CRUD 组件映射');
foreach ($definitions as $type => $definition) {
    $formal = (bool) ($definition['supportsFormalGeneration'] ?? false);
    $expectedRenderer = $crudComponents[$type] ?? '';
    fieldCapabilityExpect($formal === ($expectedRenderer !== ''), '正式生成能力必须与 CRUD Factory 一致：' . $type);
    fieldCapabilityExpect(($definition['templateRenderer'] ?? '') === $expectedRenderer, 'templateRenderer 必须对应真实模板分支：' . $type);
}

foreach (['group', 'grid', 'divider', 'text', 'collapse', 'tabs'] as $type) {
    $capability = $registry->get($type);
    fieldCapabilityExpect(($capability['kind'] ?? '') === 'layout', '布局组件 kind 必须为 layout：' . $type);
    fieldCapabilityExpect(($capability['defaultColumnType'] ?? null) === '', '布局组件不得生成数据库字段：' . $type);
    fieldCapabilityExpect(($capability['supportsFormalGeneration'] ?? true) === false, '布局组件不得作为字段正式生成：' . $type);
    fieldCapabilityExpect(($capability['supportsIndex'] ?? true) === false, '布局组件不得创建索引：' . $type);
}
foreach (['repeatable', 'subform'] as $type) {
    fieldCapabilityExpect(($registry->get($type)['supportsDynamic'] ?? false) === true, '关系容器必须支持动态运行：' . $type);
    fieldCapabilityExpect(($registry->get($type)['supportsFormalGeneration'] ?? true) === false, '关系容器本阶段不得声明正式字段生成：' . $type);
}

fieldCapabilityExpect($registry->version() !== '', '能力注册表必须提供稳定版本');
fieldCapabilityExpect($registry->canonical() === (new FieldCapabilityRegistry())->canonical(), '相同定义 canonical 必须稳定');
fieldCapabilityExpect($registry->hash() === (new FieldCapabilityRegistry())->hash(), '相同定义 hash 必须稳定');
fieldCapabilityExpect(hash('sha256', $registry->canonical()) === $registry->hash(), 'hash 必须来自 canonical');
$catalog = $registry->catalog();
fieldCapabilityExpect(($catalog['schemaVersion'] ?? null) === 2, '目录必须声明 FormSchema v2');
fieldCapabilityExpect(($catalog['version'] ?? null) === $registry->version(), '目录必须声明能力版本');
fieldCapabilityExpect(($catalog['hash'] ?? null) === $registry->hash(), '目录必须声明能力 hash');
fieldCapabilityExpect(($catalog['components'] ?? null) === array_values($definitions), '目录组件必须来自权威定义');

$plugin = $definitions['input'];
$plugin['type'] = 'demo:rating';
$plugin['namespace'] = 'demo';
$plugin['label'] = '插件评分';
$pluginRegistry = new FieldCapabilityRegistry([$plugin]);
fieldCapabilityExpect($pluginRegistry->has('demo:rating'), '构造注入插件能力必须可查询');
$minimalPluginRegistry = new FieldCapabilityRegistry([[
    'type' => 'demo:stars',
    'namespace' => 'demo',
    'component' => 'Stars',
    'label' => '星级评分',
    'valueType' => 'number',
    'defaultProps' => ['max' => 5],
    'propertySchema' => ['type' => 'object', 'additionalProperties' => false, 'properties' => ['max' => ['type' => 'integer']]],
    'allowedEvents' => ['change'],
    'codec' => 'demo:stars',
]]);
$minimalPlugin = $minimalPluginRegistry->get('demo:stars');
fieldCapabilityExpect($minimalPlugin['supportsDynamic'] === true, '当前 Manifest 精简插件组件必须默认支持动态运行');
fieldCapabilityExpect($minimalPlugin['supportsFormalGeneration'] === false, '精简插件组件不得默认开启正式生成');
fieldCapabilityExpect($minimalPlugin['supportsIndex'] === false && $minimalPlugin['supportsRelation'] === false, '精简插件组件敏感能力必须默认关闭');
fieldCapabilityExpect($pluginRegistry->get('demo:rating')['namespace'] === 'demo', '插件 namespace 必须保留');

$overrideRejected = false;
try {
    $override = $plugin;
    $override['type'] = 'input';
    new FieldCapabilityRegistry([$override]);
} catch (\InvalidArgumentException) {
    $overrideRejected = true;
}
fieldCapabilityExpect($overrideRejected, '插件不得覆盖核心能力');

$rejectPlugin = static function (array $definition, string $message): void {
    $rejected = false;
    try {
        new FieldCapabilityRegistry([$definition]);
    } catch (\InvalidArgumentException) {
        $rejected = true;
    }
    fieldCapabilityExpect($rejected, $message);
};
$missingRequired = $plugin;
unset($missingRequired['component']);
$rejectPlugin($missingRequired, '插件缺少 Manifest required 字段必须拒绝');
$invalidBoolean = $plugin;
$invalidBoolean['supportsDynamic'] = 'true';
$rejectPlugin($invalidBoolean, '插件布尔能力字段类型错误必须拒绝');
$invalidSchema = $plugin;
$invalidSchema['propertySchema'] = ['type' => 'object', 'properties' => ['clearable' => ['type' => 'boolean']]];
$invalidSchema['allowedAttrs'] = ['clearable', 'onclick'];
$rejectPlugin($invalidSchema, '插件属性 schema 与白名单不一致必须拒绝');
$maliciousSchema = $plugin;
$maliciousSchema['propertySchema']['properties']['onclick'] = ['type' => 'string'];
$maliciousSchema['allowedAttrs'][] = 'onclick';
$rejectPlugin($maliciousSchema, '插件危险属性必须拒绝');
$missingRenderer = $plugin;
$missingRenderer['supportsFormalGeneration'] = true;
$missingRenderer['templateRenderer'] = '';
$rejectPlugin($missingRenderer, '插件声明正式生成时必须提供 templateRenderer');
$invalidColumnTypes = $plugin;
$invalidColumnTypes['allowedColumnTypes'] = ['varchar(255)', 123];
$rejectPlugin($invalidColumnTypes, '插件列类型必须全部为非空字符串');

$unknownRejected = false;
try {
    $registry->get('unknown');
} catch (\InvalidArgumentException) {
    $unknownRejected = true;
}
fieldCapabilityExpect(!$registry->has('unknown') && $unknownRejected, '未知组件必须 fail closed');

$factory = new FormRegistryFactory([], static fn (): array => []);
fieldCapabilityExpect($factory->fieldCapabilities() instanceof FieldCapabilityRegistry, '统一 Factory 必须提供字段能力注册表');

$frontSource = (string) file_get_contents(dirname(__DIR__) . '/admin-web/src/views/form/registry.ts');
preg_match_all("/\\bcontrol\\(\s*['\"]([^'\"]+)['\"]/", $frontSource, $matches);
$frontTypes = array_values(array_unique($matches[1] ?? []));
$backendTypes = $registry->types();
sort($frontTypes);
sort($backendTypes);
fieldCapabilityExpect($frontTypes === $backendTypes, '前端 registry 核心 type 与后端差异必须为 0');

echo "field capability registry tests passed\n";
