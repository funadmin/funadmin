<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function runtimeHardCutExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$factoryFile = $root . '/extend/fun/plugins/PluginEntryFactory.php';
runtimeHardCutExpect(is_file($factoryFile), '必须新增 PluginEntryFactory');
$factory = (string) file_get_contents($factoryFile);
runtimeHardCutExpect(str_contains($factory, 'final class PluginEntryFactory'), 'PluginEntryFactory 必须是终态生命周期入口工厂');
runtimeHardCutExpect(str_contains($factory, 'function create('), 'PluginEntryFactory 必须提供 create');
runtimeHardCutExpect(str_contains($factory, 'vendor') && str_contains($factory, 'entry'), '入口工厂必须加载 vendor 与 Manifest entry');

$support = (string) file_get_contents($root . '/app/console/service/concern/PluginServiceSupport.php');
runtimeHardCutExpect(str_contains($support, 'PluginEntryFactory'), 'PluginServiceSupport 必须改用 PluginEntryFactory');
runtimeHardCutExpect(!str_contains($support, 'RuntimeLoader'), 'PluginServiceSupport 不得使用 RuntimeLoader');
runtimeHardCutExpect(!str_contains($support, 'PluginRuntimeCache'), 'PluginServiceSupport 必须移除 runtime cache');

$functions = (string) file_get_contents($root . '/extend/fun/functions/plugin.php');
foreach (['PluginEntryFactory', 'PluginActivationReader', 'ActivationGate'] as $required) {
    runtimeHardCutExpect(str_contains($functions, $required), '插件 functions 必须使用 ' . $required);
}
foreach (['Registry', 'RuntimeLoader', 'app\\common\\model\\Plugin'] as $forbidden) {
    runtimeHardCutExpect(!str_contains($functions, $forbidden), '插件实例热路径不得使用 ' . $forbidden);
}

$service = (string) file_get_contents($root . '/extend/fun/plugins/Service.php');
foreach (['app\\common\\model', 'Manifest', 'Registry', 'RuntimeLoader', 'PluginRuntime', 'RuntimeLoadFailureRecorder'] as $forbidden) {
    runtimeHardCutExpect(!str_contains($service, $forbidden), 'Service 必须瘦身并移除 ' . $forbidden);
}
runtimeHardCutExpect(!str_contains($service, 'function boot('), 'Service 不再承担 runtime 或路由加载');
$manifest = (string) file_get_contents($root . '/extend/fun/plugins/Manifest.php');
runtimeHardCutExpect(!str_contains($manifest, 'function loadPath('), 'Manifest 必须删除旧 runtime loadPath 边界');

foreach (['RuntimeLoader.php', 'PluginRuntimeBooter.php', 'PluginRuntimeCache.php', 'RuntimeLoadFailureRecorder.php'] as $legacy) {
    runtimeHardCutExpect(!is_file($root . '/extend/fun/plugins/' . $legacy), '必须删除旧 runtime 类：' . $legacy);
}
runtimeHardCutExpect(!is_file($root . '/extend/fun/command/PluginRuntimeCacheRebuild.php'), '必须删除 runtime cache 命令');
$config = (string) file_get_contents($root . '/config/console.php');
runtimeHardCutExpect(!str_contains($config, 'plugin:runtime-cache'), 'console 不得注册 runtime cache 命令');

foreach (['plugin_runtime_cache_test.php', 'plugin_full_runtime_test.php', 'crud/plugin_published_autoload_test.php'] as $legacyTest) {
    runtimeHardCutExpect(!is_file($root . '/tests/' . $legacyTest), '必须删除旧 runtime 契约测试：' . $legacyTest);
}

echo "plugin runtime hard cut tests passed\n";
