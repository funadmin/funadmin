<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\common\crud\AtomicWriter;
use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\crud\DefinitionValidator;
use app\common\crud\PluginCrudDefinitionFactory;
use app\common\crud\TemplateRenderer;
use fun\command\PluginCrudGenerate;
use fun\command\PluginCrudPreview;
use fun\command\PluginMakeCrud;
use fun\plugins\Manifest;
use fun\plugins\PluginScaffolder;

function pluginCrudExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function pluginCrudReject(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        pluginCrudExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $contains);
}

function pluginCrudRemove(string $path): void
{
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($path);
}

function pluginDefinition(string $scope, array $overrides = []): CrudDefinition
{
    $data = [
        'schemaVersion' => '1.0', 'connection' => 'mysql', 'module' => 'catalog', 'entity' => 'product-item',
        'table' => 'shop_product_item', 'title' => '商品', 'apiPrefix' => '/catalog/product-item',
        'routePath' => '/catalog/product-item', 'primaryKey' => 'id', 'timestamps' => true, 'softDeletes' => true,
        'target' => ['type' => 'plugin', 'plugin' => 'shop', 'scope' => $scope],
        'permissionPrefix' => 'shop:product-item',
        'fields' => [
            ['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true, 'list' => true],
            ['name' => 'name', 'dbType' => 'varchar(80)', 'nullable' => false, 'required' => true, 'list' => true, 'form' => true, 'detail' => true],
        ],
        'relations' => [], 'optionsSource' => [],
        'templates' => [
            'migration' => 'database/migration.sql.tpl', 'model' => 'console/model.php.tpl',
            'validate' => 'console/validate.php.tpl', 'service' => 'console/service.php.tpl',
            'controller' => 'console/controller.php.tpl', 'permissionMigration' => 'database/permissions.sql.tpl',
            'api' => 'frontend/api.ts.tpl', 'view' => 'frontend/index.vue.tpl',
            'form' => 'frontend/form.vue.tpl', 'detail' => 'frontend/detail.vue.tpl',
            'phpTest' => 'tests/php-test.php.tpl', 'vitestTest' => 'tests/vitest-test.ts.tpl',
        ],
        'capabilities' => ['list' => true, 'search' => true, 'form' => true, 'detail' => true, 'create' => true, 'update' => true, 'delete' => true, 'import' => false, 'export' => false],
        'features' => ['batchDelete' => true, 'status' => false, 'detail' => true, 'import' => false, 'export' => false, 'upload' => false, 'dictionary' => false, 'referenceProtection' => false, 'formMode' => 'dialog', 'importLimit' => 100, 'exportLimit' => 100],
        'dataScope' => ['enabled' => false, 'field' => ''],
        'menu' => ['enabled' => true, 'parentId' => null, 'parentSourceName' => '', 'name' => '商品', 'icon' => 'i-ep-document', 'sortOrder' => 20, 'hidden' => false, 'keepAlive' => true, 'affix' => false, 'target' => '_self'],
        'permission' => ['enabled' => true, 'groupName' => '商品', 'actions' => []],
    ];
    return CrudDefinition::fromArray(array_replace_recursive($data, $overrides));
}

$repository = dirname(__DIR__, 2);
$root = sys_get_temp_dir() . '/funadmin-plugin-crud-' . bin2hex(random_bytes(5));
mkdir($root . '/plugins', 0755, true);
(new PluginScaffolder($root . '/plugins'))->scaffold('shop', '商城插件');
$manifestPath = $root . '/plugins/shop/plugin.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
Manifest::fromDirectory($root . '/plugins/shop');

try {
    foreach (['application', 'console', 'both'] as $scope) {
        $definition = pluginDefinition($scope);
        (new DefinitionValidator())->validate($definition, $root);
        $plan = (new CrudGenerator($root, $repository . '/app/common/crud/templates/v1', new ConfirmationToken($root, 'plugin-crud-secret')))->plan($definition);
        $files = array_column($plan['files'], 'content', 'path');
        pluginCrudExpect(isset($files['plugins/shop/database/migrations/001_create_product_item.sql']), $scope . ' 缺少唯一 migration');
        pluginCrudExpect(!isset($files['database/generated/product-item_permissions.sql']), '插件不得生成核心权限 SQL');
        $applicationModel = 'plugins/shop/app/shop/model/ProductItem.php';
        $consoleModel = 'plugins/shop/app/console/model/ProductItem.php';
        pluginCrudExpect(isset($files[$applicationModel]) === in_array($scope, ['application', 'both'], true), $scope . ' application 制品不准确');
        pluginCrudExpect(isset($files[$consoleModel]) === in_array($scope, ['console', 'both'], true), $scope . ' console 制品不准确');
        pluginCrudExpect(isset($files['plugins/shop/admin-web/product-item/index.vue']) === in_array($scope, ['console', 'both'], true), $scope . ' AdminWeb 制品不准确');
        if (isset($files[$applicationModel])) {
            pluginCrudExpect(str_contains($files[$applicationModel], 'namespace app\\shop\\model;'), 'application namespace 错误');
            pluginCrudExpect(str_contains($files[$applicationModel], 'extends Model'), 'application model 必须使用原生 ThinkPHP Model');
            $applicationController = $files['plugins/shop/app/shop/controller/ProductItemController.php'];
            pluginCrudExpect(str_contains($applicationController, 'extends BaseController'), 'application controller 必须使用原生应用基类');
            pluginCrudExpect(!str_contains($applicationController, 'AdminApiController'), 'application controller 不得依赖 Console 基类');
            pluginCrudExpect(!str_contains($applicationController, 'CheckAdminApiRole'), 'application controller 不得携带 Console 中间件');
            pluginCrudExpect(str_contains($applicationController, 'use app\\common\\middleware\\MApi;'), 'application controller 必须使用会员 API 认证链');
            pluginCrudExpect(str_contains($applicationController, 'protected array $middleware = [MApi::class];'), 'application CRUD 默认必须统一认证');
            pluginCrudExpect(str_contains($applicationController, "#[Group('product-item')]"), 'application 必须使用原生 Attribute 路由');
        }
        if (isset($files[$consoleModel])) {
            pluginCrudExpect(str_contains($files[$consoleModel], 'namespace app\\console\\model\\plugin\\shop;'), 'console namespace 错误');
            $controller = $files['plugins/shop/app/console/controller/ProductItemController.php'];
            pluginCrudExpect(str_contains($controller, "#[Group('plugin/shop/product-item')]"), 'Console Group 前缀错误');
            pluginCrudExpect(str_contains($controller, 'extends AdminApiController'), 'Console controller 基类错误');
            $api = $files['plugins/shop/admin-web/product-item/api.ts'];
            pluginCrudExpect(str_contains($api, '/console/plugin/shop/product-item'), '插件 API URL 错误');
            pluginCrudExpect(str_contains($files['plugins/shop/admin-web/product-item/index.vue'], "from './api'"), '根 view 必须从 ./api 导入');
            pluginCrudExpect(str_contains($files['plugins/shop/admin-web/product-item/components/ProductItemForm.vue'], "from '../api'"), 'Form 必须从 ../api 导入');
            pluginCrudExpect(str_contains($files['plugins/shop/admin-web/product-item/components/ProductItemDetail.vue'], "from '../api'"), 'Detail 必须从 ../api 导入');
        }
        $mergedManifest = $files['plugins/shop/plugin.json'];
        $merged = json_decode($mergedManifest, true, 512, JSON_THROW_ON_ERROR);
        pluginCrudExpect(str_contains($mergedManifest, '"plugins": {}'), '空插件依赖必须编码为 JSON object');
        $secondPlan = (new CrudGenerator($root, $repository . '/app/common/crud/templates/v1', new ConfirmationToken($root, 'plugin-crud-idempotent')))->plan($definition);
        $secondFiles = array_column($secondPlan['files'], 'content', 'path');
        pluginCrudExpect($secondFiles['plugins/shop/plugin.json'] === $mergedManifest, 'Manifest 重复规划必须幂等');
        if ($scope === 'application') {
            pluginCrudExpect(($merged['adminWeb']['components'] ?? []) === $manifest['adminWeb']['components'], 'application 不应改变 AdminWeb 声明');
        } else {
            pluginCrudExpect(($merged['adminWeb']['components']['ProductItem'] ?? '') === 'product-item/index.vue', 'Manifest component 未结构化合并');
            $permissionCodes = array_column($merged['adminWeb']['permissions'], 'code');
            pluginCrudExpect(in_array('shop:product-item:list', $permissionCodes, true), 'Manifest list permission 未声明');
            pluginCrudExpect(in_array('shop:product-item:create', $permissionCodes, true), 'Manifest create permission 未声明');
            pluginCrudExpect(in_array('shop:product-item:delete', $permissionCodes, true), 'Manifest delete permission 未声明');
        }
    }

    $permissionDisabledPlan = (new CrudGenerator(
        $root,
        $repository . '/app/common/crud/templates/v1',
        new ConfirmationToken($root, 'plugin-crud-permission-disabled')
    ))->plan(pluginDefinition('console', ['permission' => ['enabled' => false]]));
    $permissionDisabledFiles = array_column($permissionDisabledPlan['files'], 'content', 'path');
    $permissionDisabledManifest = json_decode(
        $permissionDisabledFiles['plugins/shop/plugin.json'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $permissionDisabledRoute = array_values(array_filter(
        $permissionDisabledManifest['adminWeb']['routes'],
        static fn (array $route): bool => ($route['path'] ?? '') === '/plugin/shop/product-item'
    ))[0];
    pluginCrudExpect(
        !array_key_exists('permission', $permissionDisabledRoute['meta']),
        '禁用权限时 Manifest route 不得引用未声明权限'
    );
    $permissionDisabledMenu = array_values(array_filter(
        $permissionDisabledManifest['adminWeb']['menu'],
        static fn (array $menu): bool => ($menu['path'] ?? '') === '/plugin/shop/product-item'
    ))[0];
    pluginCrudExpect(
        !array_key_exists('permission', $permissionDisabledMenu),
        '禁用权限时 Manifest menu 不得引用未声明权限'
    );

    foreach (['../shop', 'console', 'api'] as $plugin) {
        pluginCrudReject(static fn () => (new DefinitionValidator())->validate(pluginDefinition('console', ['target' => ['plugin' => $plugin]]), $root), '插件');
    }
    symlink($root . '/plugins/shop', $root . '/plugins/store');
    pluginCrudReject(
        static fn () => (new DefinitionValidator())->validate(
            pluginDefinition('console', ['target' => ['plugin' => 'store']]),
            $root
        ),
        '符号链接'
    );
    unlink($root . '/plugins/store');
    $reorderedTarget = pluginDefinition('console');
    $reorderedTargetData = $reorderedTarget->toArray();
    $reorderedTargetData['target'] = ['plugin' => 'shop', 'scope' => 'console', 'type' => 'plugin'];
    (new DefinitionValidator())->validate(CrudDefinition::fromArray($reorderedTargetData), $root);
    pluginCrudReject(static fn () => (new DefinitionValidator())->validate(pluginDefinition('console', ['generationTargets' => ['model' => 'app/console/model/Escape.php']]), $root), 'generationTargets');
    pluginCrudReject(static fn () => (new DefinitionValidator())->validate(pluginDefinition('console', ['entity' => '../escape']), $root), 'entity');
    pluginCrudReject(static fn () => (new DefinitionValidator())->validate(pluginDefinition('console', ['table' => 'shop_product;drop']), $root), 'table');
    pluginCrudReject(static fn () => (new DefinitionValidator())->validate(pluginDefinition('console', ['permissionPrefix' => 'system:plugin:list']), $root), '插件权限');

    $tokens = new ConfirmationToken($root, 'plugin-crud-drift');
    $generator = new CrudGenerator($root, $repository . '/app/common/crud/templates/v1', $tokens);
    $definition = pluginDefinition('both');
    $plan = $generator->plan($definition);
    $changed = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $changed['description'] = 'parallel manifest edit';
    file_put_contents($manifestPath, json_encode($changed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    pluginCrudReject(static fn () => $generator->generate($definition, $plan['confirmToken']), 'planDigest');
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

    $applicationGenerator = new CrudGenerator($root, $repository . '/app/common/crud/templates/v1', new ConfirmationToken($root, 'plugin-crud-application-write'));
    $applicationDefinition = pluginDefinition('application');
    $applicationPlan = $applicationGenerator->plan($applicationDefinition);
    $applicationResult = $applicationGenerator->generate($applicationDefinition, $applicationPlan['confirmToken']);
    pluginCrudExpect(($applicationResult['write']['status'] ?? '') === 'written', 'application generate 不应要求覆盖未变化 Manifest');
    $consoleGenerator = new CrudGenerator($root, $repository . '/app/common/crud/templates/v1', new ConfirmationToken($root, 'plugin-crud-console-write'));
    $consoleDefinition = pluginDefinition('console');
    $consolePlan = $consoleGenerator->plan($consoleDefinition);
    $consoleMigrationPaths = array_values(array_filter(
        array_column($consolePlan['files'], 'path'),
        static fn (string $path): bool => str_ends_with($path, '_create_product_item.sql')
    ));
    pluginCrudExpect(
        $consoleMigrationPaths === ['plugins/shop/database/migrations/001_create_product_item.sql'],
        '同插件同实体跨 scope 重复生成不得新增 migration'
    );
    $consoleResult = $consoleGenerator->generate($consoleDefinition, $consolePlan['confirmToken']);
    pluginCrudExpect(($consoleResult['write']['status'] ?? '') === 'written', 'console generate 应通过 Manifest CAS 合并');
    pluginCrudExpect(!str_contains((string) file_get_contents($repository . '/app/common/crud/CrudGenerator.php'), 'pluginManifestOverwrite'), 'CrudGenerator 不得通用豁免 Manifest 覆盖');
    $sameSchemaPlan = $consoleGenerator->plan($consoleDefinition);
    $sameMigration = array_values(array_filter($sameSchemaPlan['files'], static fn (array $file): bool => str_ends_with((string) $file['path'], '.sql')))[0];
    pluginCrudExpect($sameMigration['status'] === 'unchanged', '相同 schema migration 必须复用且 unchanged');
    $addedFieldDefinition = pluginDefinition('console', ['fields' => [
        ['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true, 'list' => true],
        ['name' => 'name', 'dbType' => 'varchar(80)', 'nullable' => false, 'required' => true, 'list' => true, 'form' => true, 'detail' => true],
        ['name' => 'sku', 'dbType' => 'varchar(64)', 'nullable' => true, 'unique' => true, 'list' => true],
    ]]);
    $forwardPlan = $consoleGenerator->plan($addedFieldDefinition);
    $forwardMigration = array_values(array_filter($forwardPlan['files'], static fn (array $file): bool => str_ends_with((string) $file['path'], '.sql')))[0];
    pluginCrudExpect(str_starts_with(basename($forwardMigration['path']), '002_'), '结构新增必须生成下一个三位 forward migration');
    pluginCrudExpect(str_contains($forwardMigration['content'], 'ALTER TABLE `shop_product_item`') && str_contains($forwardMigration['content'], 'ADD COLUMN `sku`'), '新增字段必须生成 ALTER ADD');
    pluginCrudReject(static fn () => $consoleGenerator->plan(pluginDefinition('console', ['fields' => [
        ['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true, 'list' => true],
        ['name' => 'name', 'dbType' => 'varchar(120)', 'nullable' => false, 'required' => true, 'list' => true, 'form' => true, 'detail' => true],
    ]])), '删除或修改字段');
    $generatedManifest = Manifest::fromDirectory($root . '/plugins/shop');
    pluginCrudExpect(
        isset($generatedManifest->toArray()['adminWeb']['components']['ProductItem']),
        '事务提交后的插件必须通过完整 Manifest v2 校验'
    );
    pluginCrudRemove($root . '/plugins/shop/app/console/model');
    pluginCrudRemove($root . '/plugins/shop/app/console/validate');
    pluginCrudRemove($root . '/plugins/shop/app/console/service');
    pluginCrudRemove($root . '/plugins/shop/app/console/controller');
    pluginCrudRemove($root . '/plugins/shop/admin-web/product-item');
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    pluginCrudRemove($root . '/plugins/shop/app/shop/model');
    pluginCrudRemove($root . '/plugins/shop/app/shop/validate');
    pluginCrudRemove($root . '/plugins/shop/app/shop/service');
    pluginCrudRemove($root . '/plugins/shop/app/shop/controller');
    foreach (glob($root . '/plugins/shop/database/migrations/*_create_product_item.sql') ?: [] as $migrationFile) {
        unlink($migrationFile);
    }

    $plan = $generator->plan($definition);
    $beforeManifest = (string) file_get_contents($manifestPath);
    $calls = 0;
    $writer = new AtomicWriter($root, static function (array $file) use (&$calls): void {
        $calls++;
        if (($file['path'] ?? '') === 'plugins/shop/plugin.json') throw new RuntimeException('manifest transaction failure');
    }, $tokens);
    pluginCrudReject(static fn () => $writer->write($plan, $plan['confirmToken']), 'manifest transaction failure');
    pluginCrudExpect(file_get_contents($manifestPath) === $beforeManifest, '原子失败必须回滚 Manifest');
    pluginCrudExpect($calls > 1, '回滚测试必须在写入多个文件后失败');
    pluginCrudExpect(!is_file($root . '/plugins/shop/app/shop/model/ProductItem.php'), '原子失败必须回滚源码');

    $lintPlan = $generator->plan(pluginDefinition('both'));
    foreach ($lintPlan['files'] as $file) {
        if (!str_ends_with((string) $file['path'], '.php')) continue;
        $temporaryPhp = $root . '/lint-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($temporaryPhp, (string) $file['content']);
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($temporaryPhp) . ' 2>&1', $lintOutput, $lintCode);
        unlink($temporaryPhp);
        pluginCrudExpect($lintCode === 0, '生成 PHP lint 失败：' . implode("\n", $lintOutput));
        $lintOutput = [];
    }

    $conflict = $manifest;
    $conflict['adminWeb']['components']['ProductItem'] = 'other.vue';
    file_put_contents($root . '/plugins/shop/admin-web/other.vue', '<template />');
    file_put_contents($manifestPath, json_encode($conflict, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    pluginCrudReject(static fn () => $generator->plan(pluginDefinition('console')), '冲突');
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

    $factory = new PluginCrudDefinitionFactory($root);
    $inferred = $factory->fromInspection('shop', 'stock-item', 'shop_stock_item', 'both', [
        'schema' => ['table' => 'shop_stock_item', 'comment' => '库存', 'primaryKey' => ['id']],
        'fields' => [['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true]],
    ]);
    pluginCrudExpect($inferred->get('target')['plugin'] === 'shop' && $inferred->get('fields')[0]['name'] === 'id', 'make-crud infer Definition 错误');
    $inferredPlan = (new CrudGenerator(
        $root,
        $repository . '/app/common/crud/templates/v1',
        new ConfirmationToken($root, 'plugin-crud-inferred')
    ))->plan($inferred);
    $inferredFiles = array_column($inferredPlan['files'], 'content', 'path');
    $inferredManifest = json_decode(
        $inferredFiles['plugins/shop/plugin.json'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $inferredPermissionCodes = array_column($inferredManifest['adminWeb']['permissions'], 'code');
    pluginCrudExpect(
        in_array('shop:stock-item:list', $inferredPermissionCodes, true),
        'make-crud infer 必须声明 route/menu 引用的 list 权限'
    );

    $console = require $repository . '/config/console.php';
    foreach (['plugin:make-crud', 'plugin:crud-preview', 'plugin:crud-generate'] as $command) {
        pluginCrudExpect(isset($console['commands'][$command]), '缺少命令注册：' . $command);
    }
    $makeCommand = (string) file_get_contents($repository . '/extend/fun/command/PluginMakeCrud.php');
    pluginCrudExpect(str_contains($makeCommand, "addOption('table'") && str_contains($makeCommand, '->infer('), 'plugin:make-crud 必须支持 table inspect/infer');
    $commandContracts = [
        new PluginMakeCrud(),
        new PluginCrudPreview(),
        new PluginCrudGenerate(),
    ];
    pluginCrudExpect($commandContracts[0]->getName() === 'plugin:make-crud', 'plugin:make-crud 命令名错误');
    pluginCrudExpect($commandContracts[0]->getDefinition()->getArgument('plugin')->isRequired(), 'plugin:make-crud 缺少必填 plugin');
    pluginCrudExpect($commandContracts[0]->getDefinition()->getArgument('entity')->isRequired(), 'plugin:make-crud 缺少必填 entity');
    pluginCrudExpect($commandContracts[0]->getDefinition()->getOption('table')->acceptValue(), 'plugin:make-crud --table 必须接收值');
    pluginCrudExpect($commandContracts[0]->getDefinition()->getOption('target')->getDefault() === 'both', 'plugin:make-crud --target 默认值必须为 both');
    pluginCrudExpect($commandContracts[1]->getName() === 'plugin:crud-preview' && $commandContracts[1]->getDefinition()->getArgument('definition')->isRequired(), 'plugin:crud-preview contract 错误');
    pluginCrudExpect($commandContracts[1]->getDefinition()->getOption('token-output')->acceptValue(), 'plugin:crud-preview 必须提供 0600 token 输出文件');
    pluginCrudExpect($commandContracts[2]->getName() === 'plugin:crud-generate' && $commandContracts[2]->getDefinition()->getOption('confirm-token-file')->acceptValue(), 'plugin:crud-generate contract 错误');
    pluginCrudExpect(!str_contains((string) file_get_contents($repository . '/extend/fun/command/PluginMakeCrud.php'), 'confirmToken'), 'plugin:make-crud 不得生成或输出 token');
    pluginCrudExpect(!str_contains((string) file_get_contents($repository . '/extend/fun/command/PluginCrudPreview.php'), "'sensitive'"), 'plugin:crud-preview stdout 不得包含 token');

    $schema = json_decode((string) file_get_contents($repository . '/app/common/crud/schema/crud-definition-v1.schema.json'), true, 512, JSON_THROW_ON_ERROR);
    pluginCrudExpect(isset($schema['properties']['target']) && in_array('target', $schema['required'], true), 'Schema 未同步 plugin target');
    $factoryTemplates = $inferred->get('templates');
    foreach (['permissionMigration', 'phpTest', 'vitestTest'] as $unusedArtifact) {
        pluginCrudExpect(!array_key_exists($unusedArtifact, $factoryTemplates), '插件 templates 不得声明永不生成制品：' . $unusedArtifact);
    }
    $types = (string) file_get_contents($repository . '/admin-web/src/types/development/crud.ts');
    pluginCrudExpect(str_contains($types, "type: 'plugin'") && str_contains($types, "scope: 'application' | 'console' | 'both'"), 'TS 类型未同步 plugin target');

    mkdir($root . '/templates', 0755, true);
    file_put_contents($root . '/templates/invalid.tpl', '{{unsafe.path}}');
    pluginCrudReject(
        static fn () => (new TemplateRenderer($root . '/templates'))->render('invalid.tpl', []),
        '不受支持的占位符'
    );

    $concurrentTokens = new ConfirmationToken($root, 'plugin-crud-concurrent');
    $concurrentGenerator = new CrudGenerator($root, $repository . '/app/common/crud/templates/v1', $concurrentTokens);
    $firstConcurrent = pluginDefinition('application', [
        'entity' => 'warehouse-one', 'table' => 'shop_warehouse_one', 'permissionPrefix' => 'shop:warehouse-one',
    ]);
    $secondConcurrent = pluginDefinition('application', [
        'entity' => 'warehouse-two', 'table' => 'shop_warehouse_two', 'permissionPrefix' => 'shop:warehouse-two',
    ]);
    $firstConcurrentPlan = $concurrentGenerator->plan($firstConcurrent);
    $secondConcurrentPlan = $concurrentGenerator->plan($secondConcurrent);
    $firstMigration = array_values(array_filter(array_column($firstConcurrentPlan['files'], 'path'), static fn (string $path): bool => str_ends_with($path, '.sql')));
    $secondMigration = array_values(array_filter(array_column($secondConcurrentPlan['files'], 'path'), static fn (string $path): bool => str_ends_with($path, '.sql')));
    pluginCrudExpect(str_starts_with(basename($firstMigration[0]), '001_') && str_starts_with(basename($secondMigration[0]), '001_'), '并发预览夹具必须竞争同一 migration 序号');
    $writer = new AtomicWriter($root, null, $concurrentTokens);
    $writer->write($firstConcurrentPlan, $firstConcurrentPlan['confirmToken']);
    pluginCrudReject(
        static fn () => $writer->write($secondConcurrentPlan, $secondConcurrentPlan['confirmToken']),
        '已变化'
    );

    echo "Plugin CRUD target tests: PASS\n";
} finally {
    pluginCrudRemove($root);
}
