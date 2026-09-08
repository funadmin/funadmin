<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function lifecycleActivationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$service = (string) file_get_contents($root . '/app/console/service/PluginService.php');
$support = (string) file_get_contents($root . '/app/console/service/concern/PluginServiceSupport.php');
$config = (string) file_get_contents($root . '/config/console.php');

lifecycleActivationExpect(str_contains($support, 'PluginActivationCompiler'), '生命周期控制面必须使用 PluginActivationCompiler');
lifecycleActivationExpect(str_contains($support, 'refreshActivationCache'), 'PluginService 必须提供 activation 手动重建入口');
lifecycleActivationExpect(str_contains($service, 'rebuildActivationCache()'), '生命周期状态变化必须触发 activation 重建');
$transitionBody = substr($service, strpos($service, 'private function transition'), strpos($service, 'private function operate') - strpos($service, 'private function transition'));
lifecycleActivationExpect(str_contains($transitionBody, 'rebuildActivationCache()'), '每次 lifecycle transition 保存后必须立即重建 activation');
lifecycleActivationExpect(substr_count($service, 'rebuildActivationCache()') >= 3, 'transition、operate finally 与禁用快速阻断都必须重建 activation');
lifecycleActivationExpect(str_contains($config, "'plugin:activation-cache'"), '必须注册 plugin:activation-cache 命令');
lifecycleActivationExpect(is_file($root . '/extend/fun/command/PluginActivationCacheRebuild.php'), '必须实现 activation 手动重建命令');

$disableBranch = strstr($service, "if (\$enabled) {");
lifecycleActivationExpect(is_string($disableBranch), '必须存在启停分支');
$disableTransition = strpos($disableBranch, "transition(\$record, 'disabling')");
$disableCompile = strpos($disableBranch, 'rebuildActivationCache()');
$disableHook = strpos($disableBranch, '$plugin->disabled()');
lifecycleActivationExpect($disableTransition !== false && $disableCompile !== false && $disableHook !== false, '禁用必须包含 transition、activation 重建与 disabled hook');
lifecycleActivationExpect($disableTransition < $disableCompile && $disableCompile < $disableHook, '禁用必须先离开 enabled 并发布清单，再执行 disabled hook/菜单权限');

$reflection = new ReflectionClass(\app\console\service\PluginService::class);
$pluginService = $reflection->newInstanceWithoutConstructor();
$sequence = $reflection->getMethod('runDisableSequence');
$activationAvailable = true;
$hookCalled = false;
$sequence->invoke(
    $pluginService,
    static function () use (&$activationAvailable): void { $activationAvailable = false; },
    static function () use (&$activationAvailable, &$hookCalled): bool {
        $hookCalled = true;
        lifecycleActivationExpect(!$activationAvailable, 'disabled hook 调用时 activation 必须已不可用');
        return true;
    }
);
lifecycleActivationExpect($hookCalled, '禁用顺序协调器必须调用 disabled hook');

$command = (string) file_get_contents($root . '/extend/fun/command/PluginActivationCacheRebuild.php');
lifecycleActivationExpect(str_contains($command, 'refreshActivationCache()'), 'CLI 必须调用 activation 重建入口');

echo "plugin activation lifecycle tests passed\n";
