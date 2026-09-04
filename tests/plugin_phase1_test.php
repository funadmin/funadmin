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
    'load' => [
        'services' => 'config/service.php',
        'events' => 'config/event.php',
        'routes' => 'config/route.php',
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

$states = LifecycleState::all();
expect($states === ['discovered', 'installing', 'disabled', 'enabling', 'enabled', 'disabling', 'uninstalling', 'failed'], '状态集合必须显式固定');
expect(LifecycleState::canTransition('discovered', 'installing'), '发现态应可进入安装中');
expect(LifecycleState::canTransition('installing', 'disabled'), '安装完成应保持禁用');
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
    $data['load']['routes'] = '../outside.php';
    file_put_contents($root . '/demo/plugin.json', json_encode($data));
    Manifest::fromDirectory($root . '/demo');
}, '加载路径');

$routeSource = file_get_contents(dirname(__DIR__) . '/extend/fun/plugins/Route.php');
expect(!str_contains((string) $routeSource, '$pluginsRouteConfig'), 'Route 不得引用未定义的 pluginsRouteConfig');
$serviceSource = file_get_contents(dirname(__DIR__) . '/extend/fun/plugins/Service.php');
expect(!str_contains((string) $serviceSource, "'plugin.ini'"), '运行时服务不得读取 plugin.ini');
expect(!str_contains((string) $serviceSource, "'service.ini'"), '运行时服务不得读取 service.ini');
expect(!str_contains((string) $serviceSource, "config('plugins.route'"), '运行时服务不得加载旧的全局路由配置');
expect(str_contains((string) $serviceSource, 'RuntimeLoader'), '运行时服务必须通过显式加载器加载边界');
$pluginServiceSource = file_get_contents(dirname(__DIR__) . '/app/backend/service/PluginService.php');
expect(str_contains((string) $pluginServiceSource, 'LifecycleLock'), '生命周期服务必须使用互斥锁');
expect(str_contains((string) $pluginServiceSource, "'lifecycle_state'"), '生命周期状态必须持久化到注册表');
$migrationSource = file_get_contents(dirname(__DIR__) . '/database/migrations/007_plugin_registry_state.sql');
expect(str_contains((string) $migrationSource, '`manifest`'), '007 migration 必须包含 manifest 快照字段');
expect(str_contains((string) $migrationSource, '`lifecycle_state`'), '007 migration 必须包含显式状态字段');

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($root);

echo "plugin phase1 tests: PASS\n";
