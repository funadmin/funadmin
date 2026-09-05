<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use fun\plugins\DependencyValidator;
use fun\plugins\LifecycleLock;
use fun\plugins\LifecycleState;
use fun\plugins\Manifest;
use fun\plugins\Registry;
use fun\plugins\RuntimeLoader;

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectException(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        expect(str_contains($exception->getMessage(), $contains), '异常信息不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期抛出异常：' . $contains);
}

$root = sys_get_temp_dir() . '/funadmin-plugin-phase1-' . bin2hex(random_bytes(4));
mkdir($root . '/demo/config', 0755, true);
mkdir($root . '/demo/resources/admin', 0755, true);
file_put_contents($root . '/demo/resources/admin/entry.js', 'export const register = () => ({ components: {} });');
file_put_contents($root . '/demo/Plugin.php', '<?php namespace plugins\\demo; final class Plugin {}');
file_put_contents($root . '/demo/config/service.php', '<?php return ["DemoService"];');
file_put_contents($root . '/demo/config/event.php', '<?php return ["listen" => []];');
file_put_contents($root . '/demo/config/route.php', '<?php return static function ($route): void {};');
file_put_contents($root . '/demo/plugin.json', json_encode([
    'schema_version' => 1,
    'name' => 'demo',
    'title' => '演示插件',
    'version' => '1.2.0',
    'requires' => [
        'php' => '>=8.1',
        'funadmin' => '>=1.0.0',
        'plugins' => ['base' => '^2.0'],
    ],
    'entry' => ['class' => 'plugins\\demo\\Plugin', 'file' => 'Plugin.php'],
    'load' => [
        'services' => 'config/service.php',
        'events' => 'config/event.php',
        'routes' => 'config/route.php',
    ],
    'admin_web' => [
        'entry' => 'entry.js',
        'routes' => [],
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$manifest = Manifest::fromDirectory($root . '/demo');
expect($manifest->name() === 'demo', '应读取 plugin.json');
expect($manifest->version() === '1.2.0', '应读取版本号');
expect($manifest->dependencies() === ['base' => '^2.0'], '应读取带版本约束的依赖');
expect($manifest->loadPath('services') === $root . '/demo/config/service.php', '应解析显式加载路径');
expectException(static fn () => Manifest::fromDirectory($root . '/missing'), 'plugin.json');
file_put_contents($root . '/demo/plugin.ini', 'name=legacy');
expect(Manifest::fromDirectory($root . '/demo')->name() === 'demo', '不得读取 plugin.ini');
$invalidAdminManifest = json_decode((string) file_get_contents($root . '/demo/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$invalidAdminManifest['admin_web']['entry'] = 'resources/admin/entry.js';
file_put_contents($root . '/demo/plugin.json', json_encode($invalidAdminManifest, JSON_UNESCAPED_UNICODE));
expectException(static fn () => Manifest::fromDirectory($root . '/demo'), 'admin_web.entry');
$invalidAdminManifest['admin_web']['entry'] = '../entry.js';
file_put_contents($root . '/demo/plugin.json', json_encode($invalidAdminManifest, JSON_UNESCAPED_UNICODE));
expectException(static fn () => Manifest::fromDirectory($root . '/demo'), 'admin_web.entry');
$invalidAdminManifest['admin_web']['entry'] = 'entry.js';
file_put_contents($root . '/demo/plugin.json', json_encode($invalidAdminManifest, JSON_UNESCAPED_UNICODE));
$manifest = Manifest::fromDirectory($root . '/demo');

$states = LifecycleState::all();
expect($states === ['discovered', 'installing', 'disabled', 'updating', 'enabling', 'enabled', 'disabling', 'uninstalling', 'failed'], '状态集合必须显式固定');
expect(LifecycleState::canTransition('discovered', 'installing'), '发现态应可进入安装中');
expect(LifecycleState::canTransition('discovered', 'failed'), '发现态预校验失败必须可落 failed');
expect(LifecycleState::canTransition('installing', 'disabled'), '安装完成应保持禁用');
expect(LifecycleState::canTransition('disabled', 'failed'), '禁用态操作失败必须可落 failed');
expect(LifecycleState::canTransition('enabled', 'failed'), '启用态操作失败必须可落 failed');
expect(!LifecycleState::canTransition('discovered', 'enabled'), '不得跳过安装直接启用');
expectException(static fn () => LifecycleState::assertTransition('enabled', 'installing'), '非法插件状态迁移');

$registry = new Registry($root, static fn (): array => [
    'demo' => ['name' => 'demo', 'version' => '1.2.0', 'lifecycle_state' => 'enabled'],
]);
$entries = $registry->discover();
expect(array_keys($entries) === ['demo'], '注册表只应发现具有 plugin.json 和 Plugin.php 的插件');
expect($registry->enabled()['demo']->name() === 'demo', '仅数据库 enabled 状态可进入运行时注册表');
mkdir($root . '/legacy', 0755, true);
file_put_contents($root . '/legacy/plugin.ini', 'name=legacy');
file_put_contents($root . '/legacy/Plugin.php', '<?php');
expect(!isset($registry->discover()['legacy']), '不得兼容仅含 plugin.ini 的旧插件');
mkdir($root . '/broken', 0755, true);
file_put_contents($root . '/broken/Plugin.php', '<?php throw new RuntimeException("不应执行插件入口");');
file_put_contents($root . '/broken/plugin.json', '{invalid');
expect(!isset($registry->discover()['broken']), '管理发现必须隔离不兼容 manifest，不能拖垮列表');

$validator = new DependencyValidator('1.5.0', PHP_VERSION);
$validator->assertSatisfied($manifest, [
    'base' => ['version' => '2.3.0', 'lifecycle_state' => 'enabled'],
]);
expectException(static fn () => $validator->assertSatisfied($manifest, []), '依赖插件未安装');
expectException(static fn () => $validator->assertSatisfied($manifest, [
    'base' => ['version' => '1.9.0', 'lifecycle_state' => 'enabled'],
]), '版本不满足');
expectException(static fn () => $validator->assertSatisfied($manifest, [
    'base' => ['version' => '2.3.0', 'lifecycle_state' => 'disabled'],
]), '未启用');
mkdir($root . '/base', 0755, true);
file_put_contents($root . '/base/Plugin.php', '<?php namespace plugins\\base; final class Plugin {}');
file_put_contents($root . '/base/plugin.json', json_encode([
    'schema_version' => 1,
    'name' => 'base',
    'title' => '基础插件',
    'version' => '2.3.0',
    'requires' => ['plugins' => ['demo' => '^1.0']],
    'entry' => ['class' => 'plugins\\base\\Plugin', 'file' => 'Plugin.php'],
], JSON_UNESCAPED_UNICODE));
$baseManifest = Manifest::fromDirectory($root . '/base');
expectException(static fn () => $validator->assertAcyclic([
    'demo' => $manifest,
    'base' => $baseManifest,
]), '循环依赖');
expectException(static fn () => $validator->assertNoEnabledDependents('base', [
    'demo' => $manifest,
    'base' => $baseManifest,
], [
    'demo' => ['lifecycle_state' => 'enabled'],
    'base' => ['lifecycle_state' => 'enabled'],
]), '反向依赖');

$lockDirectory = $root . '/locks';
$lock = new LifecycleLock($lockDirectory);
$first = $lock->acquire('demo');
expectException(static fn () => (new LifecycleLock($lockDirectory))->acquire('demo'), '正在执行生命周期操作');
$first->release();
$second = $lock->acquire('demo');
$second->release();

$loader = new RuntimeLoader();
$boundaries = $loader->boundaries($manifest);
expect(array_keys($boundaries) === ['services', 'events', 'routes'], '仅允许显式 service/event/route 边界');
expect(!in_array($root . '/demo/config.php', $boundaries, true), '不得隐式加载插件文件');
expectException(static function () use ($root): void {
    $data = json_decode((string) file_get_contents($root . '/demo/plugin.json'), true);
    $data['routes'] = '../outside.php';
    file_put_contents($root . '/demo/plugin.json', json_encode($data));
    Manifest::fromDirectory($root . '/demo');
}, '$ 未知字段 routes');

$serviceSource = file_get_contents(dirname(__DIR__) . '/extend/fun/plugins/Service.php');
expect(!str_contains((string) $serviceSource, 'error_reporting('), '运行时服务不得抑制 PHP 错误');
expect(!str_contains((string) $serviceSource, "'plugin.ini'"), '运行时服务不得读取 plugin.ini');
expect(!str_contains((string) $serviceSource, "'service.ini'"), '运行时服务不得读取 service.ini');
expect(!str_contains((string) $serviceSource, "config('plugins.route'"), '运行时服务不得加载旧的全局路由配置');
expect(str_contains((string) $serviceSource, "whereNull('deleted_at')"), 'Registry 查询必须只读取未软删除记录');
expect(str_contains((string) $serviceSource, 'RuntimeLoader'), '运行时服务必须通过显式加载器加载边界');
expect(!str_contains((string) $serviceSource, '$this->autoload()'), '运行时服务不得保留旧 autoload 状态旁路');
expect(!str_contains((string) $serviceSource, 'plugins_vendor_autoload'), '运行时服务不得隐式加载插件 vendor');
expect(!str_contains((string) $serviceSource, 'middleware.php'), '运行时服务不得导入插件全局中间件');
expect(!str_contains((string) $serviceSource, 'Route::execute'), '运行时服务不得注册旧通配控制器路由');
expect(!str_contains((string) $serviceSource, 'Cache::'), '运行时服务不得建立旧缓存状态旁路');
$functionsSource = file_get_contents(dirname(__DIR__) . '/extend/fun/functions/plugin.php');
expect(!str_contains((string) $functionsSource, 'get_class_methods('), '不得扫描插件 public methods 自动生成 hooks');
expect(!preg_match('/function refreshplugins\(\)[\s\S]*config[^;]*plugins\.php/', (string) $functionsSource), 'refreshplugins 不得回写 config/plugins.php');
expect(!preg_match('/function get_plugin_info\([^)]*\)[\s\S]{0,300}get_plugin_instance/', (string) $functionsSource), '旧信息读取路径不得实例化插件');
foreach (['refreshplugins', 'plugins_vendor_autoload', 'set_app_route', 'set_plugin_config', 'set_plugin_info', 'get_plugin_class', 'get_plugin_menu'] as $legacyFunction) {
    expect(!preg_match('/function\\s+' . preg_quote($legacyFunction, '/') . '\\s*\\(/', (string) $functionsSource), 'plugin.php 不得保留运行时旁路：' . $legacyFunction);
}
$repositoryRoot = dirname(__DIR__);
$runtimePhpFiles = array_filter(array_merge(
    glob($repositoryRoot . '/app/**/*.php') ?: [],
    glob($repositoryRoot . '/app/*/**/*.php') ?: [],
    glob($repositoryRoot . '/app/*/*/**/*.php') ?: []
));
foreach ($runtimePhpFiles as $runtimePhpFile) {
    $runtimeSource = (string) file_get_contents($runtimePhpFile);
    expect(!preg_match('/\\bhook(?:_one)?\\s*\\(/', $runtimeSource), '应用运行时代码不得调用已删除的旧 hook：' . $runtimePhpFile);
}
expect(!is_file($repositoryRoot . '/extend/fun/plugins/Controller.php'), '旧通配插件 Controller 必须删除');
$pluginsConfig = require $repositoryRoot . '/config/plugins.php';
foreach (['autoload', 'hooks', 'route', 'service'] as $legacyConfigKey) {
    expect(!array_key_exists($legacyConfigKey, $pluginsConfig), 'plugins 配置不得保留旧 runtime 键：' . $legacyConfigKey);
}
expect(preg_match('/function\s+get_plugin_instance\s*\(/', (string) $functionsSource) === 1, 'plugin.php 必须保留 Registry 约束的实例获取薄门面');
expect(preg_match('/function\s+run_plugin_migrations\s*\(/', (string) $functionsSource) === 1, 'plugin.php 必须保留正式 MigrationService 薄门面');
expect(str_contains((string) $functionsSource, 'needs_reinstall'), '实例获取必须排除 needs_reinstall 插件');
expect(!str_contains((string) $functionsSource, 'spl_autoload_register'), 'plugin.php 不得注册旧插件 autoload');
expect(!is_file(dirname(__DIR__) . '/extend/fun/plugins/Route.php'), '旧插件通配路由执行器必须移除');
expect(!is_file(dirname(__DIR__) . '/extend/fun/plugins/middleware/Plugins.php'), '旧插件全局 hook 中间件必须移除');
$pluginServiceSource = file_get_contents(dirname(__DIR__) . '/app/backend/service/PluginService.php');
expect(str_contains((string) $pluginServiceSource, 'LifecycleLock'), '生命周期服务必须使用互斥锁');
expect(str_contains((string) $pluginServiceSource, 'finally'), '生命周期服务必须统一 finally 释放锁并清缓存');
expect(str_contains((string) $pluginServiceSource, 'operation_token'), '生命周期操作必须持久化 operation_token');
expect(str_contains((string) $pluginServiceSource, "'operation_token' => null"), '生命周期完成或失败必须清空 operation_token');
expect(!str_contains((string) $pluginServiceSource, '->status'), '插件生命周期逻辑不得读取旧 status');
$beforeUpdatePosition = strpos((string) $pluginServiceSource, '$plugin->beforeUpdate(');
$rollbackBoundaryPosition = strpos((string) $pluginServiceSource, '$this->deploymentRollbackAllowed = false;', $beforeUpdatePosition);
expect($beforeUpdatePosition !== false && $rollbackBoundaryPosition !== false && $beforeUpdatePosition < $rollbackBoundaryPosition, 'beforeUpdate 成功前必须保持部署可回滚');
expect(str_contains((string) $pluginServiceSource, 'captureDeploymentState'), '包部署必须捕获 Plugin 数据库旧快照');
expect(str_contains((string) $pluginServiceSource, 'suppressFailureRecording'), '恢复旧快照后不得再覆盖为 failed');
expect(!preg_match('/->save\(\[[^]]*[\'\"]lifecycle_state[\'\"]/', (string) $pluginServiceSource), '生命周期状态写入不得绕过 LifecycleState');
expect(str_contains((string) $pluginServiceSource, 'assertNoEnabledDependents'), '禁用和卸载必须检查反向依赖');
expect(substr_count((string) $pluginServiceSource, 'validatedManifest(') >= 4, '安装、更新和启用均必须重检 manifest 与依赖');
expect(!str_contains((string) $serviceSource, 'updatePluginsInfo'), '不得保留可绕过 LifecycleState 的状态缓存写入口');
$migrationSource = file_get_contents(dirname(__DIR__) . '/database/migrations/007_plugin_registry_state.sql');
expect(str_contains((string) $migrationSource, '`manifest`'), '007 migration 必须包含 manifest 快照字段');
expect(str_contains((string) $migrationSource, '`lifecycle_state`'), '007 migration 必须包含显式状态字段');
$firstAlter = strpos((string) $migrationSource, "CONCAT('ALTER TABLE `', @table_name");
$pluginDetection = strpos((string) $migrationSource, "TABLE_NAME = @legacy_table_name");
expect($pluginDetection !== false && $firstAlter !== false && $pluginDetection < $firstAlter, '007 必须在任何 fun_plugin ALTER 前检测 fun_addon 与 fun_plugin');
expect(str_contains((string) $migrationSource, "CONCAT('RE', 'NAME TABLE `', @legacy_table_name"), '仅旧 fun_addon 存在时必须安全重命名为 fun_plugin');
expect(str_contains((string) $migrationSource, "INSERT INTO `', @table_name") && str_contains((string) $migrationSource, 'WHERE NOT EXISTS') && str_contains((string) $migrationSource, 'target.`name` = legacy.`name`'), '两张插件表并存时必须按 name 去重合并旧数据');
expect(str_contains((string) $migrationSource, "CREATE TABLE IF NOT EXISTS `', @table_name"), '插件表都不存在时必须创建后续 ALTER 所需基础表');
expect(str_contains((string) $migrationSource, '@lifecycle_state_exists'), '生命周期回填必须有重复执行保护');
expect(!preg_match("/lifecycle_state`\\s*=\\s*''enabled''/i", (string) $migrationSource), '旧插件迁移后绝不能自动进入 enabled');
expect(str_contains((string) $migrationSource, "THEN ''failed''") && str_contains((string) $migrationSource, "ELSE ''disabled''"), '旧插件必须依据 status/delete_time 安全回填为 disabled 或 failed');
expect(str_contains((string) $migrationSource, "COLUMN_NAME = 'addons'") && str_contains((string) $migrationSource, "COLUMN_NAME = 'plugins'"), '007 必须检测 admin_log 的 addons/plugins 列');
expect(str_contains((string) $migrationSource, "CHANGE COLUMN `addons` `plugins`") && str_contains((string) $migrationSource, "COALESCE(NULLIF(`plugins`, ''''), `addons`)") , 'admin_log 必须按列存在性重命名或合并回填');
expect(str_contains((string) $migrationSource, "REPLACE(`code`, ''backend/addon'', ''backend/plugin'')"), '必须迁移旧插件权限 code 命名');
expect(str_contains((string) $migrationSource, "REPLACE(`href`, ''backend/addon'', ''backend/plugin'')"), '必须迁移旧插件菜单 href 命名');
expect(substr_count((string) $migrationSource, 'information_schema.TABLES') >= 4 && substr_count((string) $migrationSource, 'information_schema.COLUMNS') >= 10, '兼容分支必须通过结构存在性检查保持幂等');

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($root);

echo "plugin phase1 tests: PASS\n";
