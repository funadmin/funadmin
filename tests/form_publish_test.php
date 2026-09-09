<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\form\dependency\FormSchemaDependencyChecker;
use app\common\form\registry\FormRegistryFactory;

function publishExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$form = [
    'form_key' => 'activity_form', 'name' => '活动报名', 'table_name' => 'fun_activity_form',
    'connection' => 'mysql', 'source_type' => 'created', 'remark' => '发布转换测试',
    'fields' => [
        ['field_name' => 'title', 'label' => '标题', 'type' => 'input', 'column_type' => 'varchar(255)', 'nullable' => 0, 'list_show' => 1, 'list_filter' => 'like', 'list_formatter' => 'tag', 'list_width' => 180, 'form_show' => 1, 'relation_type' => 'none'],
        ['field_name' => 'amount', 'label' => '金额', 'type' => 'number', 'column_type' => 'decimal(10,2)', 'nullable' => 0, 'list_show' => 1, 'list_filter' => 'gte', 'list_formatter' => 'money', 'form_show' => 1, 'relation_type' => 'none'],
        ['field_name' => 'owner_id', 'label' => '负责人', 'type' => 'relation', 'column_type' => 'bigint', 'unsigned' => 1, 'nullable' => 1, 'list_show' => 1, 'form_show' => 1, 'relation_type' => 'belongs_to', 'relation_table' => 'fun_admin', 'relation_label_field' => 'username', 'relation_value_field' => 'id'],
        ['field_name' => 'layout_group', 'label' => '基本信息', 'type' => 'group', 'column_type' => '', 'form_span' => 24, 'control_props' => ['title' => '基本信息']],
    ],
];

$compiled = (new \app\common\form\schema\FormSchemaCompiler(new \app\common\form\schema\FormSchemaValidator()))
    ->compile((new \app\common\form\schema\FormSchemaMigrator())->fromV1($form));
publishExpect($compiled->version() === 2, '发布必须编译 canonical FormSchema v2');
publishExpect(
    count(array_filter($compiled->fieldProjection(), static fn (array $field): bool => in_array(
        (string) ($field['field_name'] ?? ''),
        ['title', 'amount', 'owner_id'],
        true
    ))) === 3,
    '动态发布字段投影必须保留三个业务字段'
);
publishExpect($compiled->key() === 'activity_form', '动态运行时路由必须由 canonical key 派生');

$serviceSource = (string) file_get_contents(dirname(__DIR__) . '/app/console/service/FormPublishService.php');
publishExpect(str_contains($serviceSource, 'previewDynamic(') && str_contains($serviceSource, 'publishDynamic('), '必须保留纯动态 API');
publishExpect(str_contains($serviceSource, 'FormSchemaRepository'), '动态发布必须接入 FormSchema v2 仓库');
publishExpect(str_contains($serviceSource, "'formSchemaHash'"), '动态发布预览必须返回 canonical FormSchema hash');
publishExpect(str_contains($serviceSource, "'published_schema_hash'"), '动态发布成功必须绑定已发布 FormSchema hash');
foreach (['FormCrudDefinitionFactory', 'DevCrudService', 'CrudGenerator', 'AtomicWriter', 'preflightGeneration(', '->generate(', 'generation(', 'retryResources('] as $forbidden) {
    publishExpect(!str_contains($serviceSource, $forbidden), '纯动态发布不得包含源码生成能力：' . $forbidden);
}
publishExpect(str_contains($serviceSource, 'return $this->previewDynamic($payload);'), '旧 preview 必须只委托动态预览');
publishExpect(str_contains($serviceSource, 'return $this->publishDynamic($payload, $operator);'), '旧 publish 必须只委托动态发布');
publishExpect(str_contains($serviceSource, 'applyDynamicDdl($payload)'), '动态发布必须使用 forward-only DDL API');
publishExpect(!str_contains($serviceSource, 'applyMigration($payload)'), '动态发布不得登记历史 migration 文件');

$fullPublishSource = (string) file_get_contents(dirname(__DIR__) . '/app/console/service/FormFullPublishService.php');
publishExpect(str_contains($fullPublishSource, 'ManagedGenerationService'), '完整发布必须显式接入 ManagedGenerationService');
publishExpect(str_contains($fullPublishSource, '->preview(') && str_contains($fullPublishSource, '->execute('), '完整发布必须使用 managed preview/execute');
foreach (['DevCrudService', 'preflightGeneration(', '->generate('] as $legacyManagedPath) {
    publishExpect(!str_contains($fullPublishSource, $legacyManagedPath), '完整发布不得调用旧 DevCrud generate 路径：' . $legacyManagedPath);
}
publishExpect(
    str_contains($fullPublishSource, 'if (!$canApplyResources)'),
    '完整发布执行 managed 资源事务前必须显式校验资源权限'
);
publishExpect(
    str_contains($fullPublishSource, "'published_definition_hash' => (string) \$generated['definitionHash']"),
    '发布元数据必须绑定实际 managed Definition hash'
);
$fullPublishController = (string) file_get_contents(dirname(__DIR__) . '/app/console/controller/form/FullPublish.php');
publishExpect(!str_contains($fullPublishController, "post('definition'"), '完整发布 Controller 不得接收完整 Definition');
publishExpect(str_contains($fullPublishController, "post('formId'"), '完整发布 Controller 必须仅以 formId 定位服务端已发布 Schema');

$registryHandler = static fn (array $parameters = []): array => $parameters;
$registryConfig = [
    'data_sources' => ['member.options' => ['permission' => 'member:list', 'parameters' => ['keyword'], 'capabilityVersion' => '2', 'handler' => $registryHandler]],
    'validators' => ['member.unique' => ['capabilityVersion' => '3', 'handler' => static fn (): bool => true]],
    'actions' => ['member.refresh' => ['permission' => 'member:update', 'parameters' => ['member_id'], 'capabilityVersion' => '4', 'url' => 'https://forbidden.example', 'handler' => $registryHandler]],
];
$registries = new FormRegistryFactory($registryConfig, static fn (): array => []);
publishExpect($registries->dataSources()->definitions()['member.options'] === ['permission' => 'member:list', 'parameters' => ['keyword'], 'capabilityVersion' => '2'], '生产 registry 必须来自统一配置且仅暴露受控端点元数据');
publishExpect($registries->asyncValidators()->definitions()['member.unique'] === ['capabilityVersion' => '3'], '异步验证 registry 必须暴露版本元数据');
publishExpect(!isset($registries->actions()->definitions()['member.refresh']['url']), 'action registry 禁止暴露 URL/JS/handler');
$checker = new FormSchemaDependencyChecker($registries->actions(), $registries->dataSources(), $registries->asyncValidators(), $registries->pluginComponents());
$registeredValidatorReport = $checker->check([
    'nodes' => [[
        'type' => 'input',
        'validation' => [['type' => 'async', 'validator' => ['key' => 'member.unique', 'capabilityVersion' => '3']]],
        'events' => [], 'children' => [],
    ]],
    'dataSources' => [], 'actions' => [],
]);
publishExpect($registeredValidatorReport['diagnostics'] === [], '发布依赖检查必须识别 validation.validator.key 规范结构');
$dependencySchema = [
    'nodes' => [[
        'type' => 'demo:rating', 'capabilityVersion' => '1',
        'validation' => [['type' => 'async', 'key' => 'member.missing', 'capabilityVersion' => '1']],
        'events' => [], 'children' => [],
    ]],
    'dataSources' => [['kind' => 'endpoint', 'endpoint' => 'member.options', 'permission' => 'member:delete', 'params' => ['admin' => true], 'capabilityVersion' => '1']],
    'actions' => [['type' => 'request', 'key' => 'member.refresh', 'permission' => 'member:delete', 'parameters' => ['admin' => true], 'capabilityVersion' => '3']],
];
$dependencyReport = $checker->check($dependencySchema);
$enabledManifest = \fun\plugins\Manifest::fromCompiled('/tmp/demo', [
    'code' => 'demo', 'version' => '1.0.0',
    'formComponents' => [[
        'type' => 'demo:rating', 'component' => 'Rating', 'propertySchema' => ['type' => 'object'],
        'valueType' => 'number', 'defaultProps' => [], 'allowedEvents' => [],
        'validator' => 'demo:rating', 'codec' => 'demo:rating', 'capabilityVersion' => 2,
    ]],
]);
$enabledRegistries = new FormRegistryFactory($registryConfig, static fn (): array => ['demo' => $enabledManifest]);
$enabledChecker = new FormSchemaDependencyChecker(
    $enabledRegistries->actions(),
    $enabledRegistries->dataSources(),
    $enabledRegistries->asyncValidators(),
    $enabledRegistries->pluginComponents()
);
$enabledSchema = $dependencySchema;
$enabledSchema['nodes'][0]['capabilityVersion'] = '2';
$enabledSchema['nodes'][0]['validation'] = [];
$enabledSchema['dataSources'] = [];
$enabledSchema['actions'] = [];
$enabledReport = $enabledChecker->check($enabledSchema);
$versionChangedManifest = \fun\plugins\Manifest::fromCompiled('/tmp/demo', array_replace(
    $enabledManifest->toArray(),
    ['formComponents' => [array_replace($enabledManifest->toArray()['formComponents'][0], ['capabilityVersion' => 3])]]
));
$versionChangedRegistries = new FormRegistryFactory($registryConfig, static fn (): array => ['demo' => $versionChangedManifest]);
$versionChangedChecker = new FormSchemaDependencyChecker(
    $versionChangedRegistries->actions(),
    $versionChangedRegistries->dataSources(),
    $versionChangedRegistries->asyncValidators(),
    $versionChangedRegistries->pluginComponents()
);
publishExpect($enabledReport['diagnostics'] === [], '发布预览必须接受已启用且 capabilityVersion 匹配的插件组件');
publishExpect(
    $enabledReport['dependencyHash'] !== $versionChangedChecker->check($enabledSchema)['dependencyHash'],
    '插件组件依赖版本必须进入 dependencyHash'
);
$diagnostics = array_column($dependencyReport['diagnostics'], null, 'path');
foreach ($dependencyReport['diagnostics'] as $diagnostic) publishExpect(array_keys($diagnostic) === ['path', 'code', 'message'], 'diagnostics 必须严格返回 path/code/message');
publishExpect(($diagnostics['/nodes/0/type']['code'] ?? '') === 'FORM_COMPONENT_NOT_ENABLED', '必须检查插件组件启用状态');
publishExpect(($diagnostics['/nodes/0/validation/0/key']['code'] ?? '') === 'FORM_ASYNC_VALIDATOR_NOT_REGISTERED', '必须检查 async validator key');
publishExpect(($diagnostics['/dataSources/0/permission']['code'] ?? '') === 'FORM_DATA_SOURCE_PERMISSION_MISMATCH', '必须检查 endpoint 权限字段');
publishExpect(($diagnostics['/dataSources/0/params/admin']['code'] ?? '') === 'FORM_DATA_SOURCE_PARAMETER_NOT_ALLOWED', '必须检查 endpoint 参数');
publishExpect(($diagnostics['/dataSources/0/capabilityVersion']['code'] ?? '') === 'FORM_DATA_SOURCE_VERSION_MISMATCH', '必须检查 endpoint 版本');
publishExpect(($diagnostics['/actions/0/permission']['code'] ?? '') === 'FORM_ACTION_PERMISSION_MISMATCH', '必须检查 request action 权限');
publishExpect(($diagnostics['/actions/0/parameters/admin']['code'] ?? '') === 'FORM_ACTION_PARAMETER_NOT_ALLOWED', '必须检查 request action 参数');
publishExpect(($diagnostics['/actions/0/capabilityVersion']['code'] ?? '') === 'FORM_ACTION_VERSION_MISMATCH', '必须检查 request action 版本');
publishExpect(strpos($serviceSource, 'checkDependencies(') < strpos($serviceSource, 'previewMigration('), '动态发布 preview 必须在 DDL 前检查依赖');
publishExpect(str_contains($serviceSource, "'diagnostics'"), '动态 preview 必须返回 diagnostics');
publishExpect(str_contains($serviceSource, "'formDependencyHash'"), '动态发布预览必须返回依赖版本哈希');

echo "form publish conversion tests: PASS\n";
