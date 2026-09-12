<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\plugin\sdk\PluginActivationCompiler;
use app\common\plugin\sdk\PluginActivationReader;
use app\common\plugin\sdk\PluginExecutionGate;
use app\common\plugin\sdk\PluginNotActiveException;

function executionGateExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

final class ExecutionProbe
{
    public function command(array $payload): string { return 'command:' . $payload['value']; }
    public function listener(string $event): string { return 'listener:' . $event; }
    public function job(array $payload): string { return 'job:' . $payload['value']; }
    public function provider(object $application): string { return 'provider:' . $application->name; }
}
class_alias(ExecutionProbe::class, 'app\\demo\\ExecutionProbe');

$root = sys_get_temp_dir() . '/funadmin-execution-gate-' . bin2hex(random_bytes(5));
$runtime = $root . '/runtime';
$plugins = $root . '/plugins';
mkdir($plugins . '/demo/app/demo', 0755, true);
$records = ['demo' => ['code' => 'demo', 'lifecycle_state' => 'disabled', 'status' => 0, 'needs_reinstall' => 0, 'operation_token' => null, 'version' => '1.0.0']];
$manifests = ['demo' => ['requires' => ['plugins' => []]]];
$compiler = new PluginActivationCompiler($runtime, $plugins);
$compiler->compile($records, $manifests);
$reader = new PluginActivationReader($runtime);
$gate = new PluginExecutionGate($reader);
$autoloadAttempted = false;
spl_autoload_register(static function (string $class) use (&$autoloadAttempted): void {
    if ($class === 'DisabledPluginProbe') $autoloadAttempted = true;
});
try {
    $gate->executeCommand(['plugin_code' => 'demo', 'application' => 'app', 'class' => 'DisabledPluginProbe']);
    throw new RuntimeException('禁用插件执行必须被拒绝');
} catch (PluginNotActiveException) {
    executionGateExpect(!$autoloadAttempted, 'ActivationGate 必须在插件类 autoload 前执行');
}

$records['demo']['lifecycle_state'] = 'enabled';
$records['demo']['status'] = 1;
$payload = $compiler->compile($records, $manifests);
$descriptor = ['plugin_code' => 'demo', 'application' => 'app', 'generation' => $payload['generation'], 'class' => 'app\\demo\\ExecutionProbe'];
executionGateExpect($gate->executeCommand($descriptor, ['value' => 'ok']) === 'command:ok', 'CLI 代理执行失败');
executionGateExpect($gate->dispatchListener($descriptor, 'paid') === 'listener:paid', 'Event 代理执行失败');
executionGateExpect($gate->runJob($descriptor, ['value' => 'queued']) === 'job:queued', 'Queue 代理执行失败');
executionGateExpect($gate->registerProvider($descriptor, (object) ['name' => 'app']) === 'provider:app', 'Provider 代理执行失败');

$compiler->compile($records, $manifests);
try {
    $gate->executeCommand($descriptor, ['value' => 'stale']);
    throw new RuntimeException('旧 generation 必须被拒绝');
} catch (RuntimeException $exception) {
    executionGateExpect(str_contains($exception->getMessage(), 'generation'), '旧 generation 异常不明确');
}

echo "plugin execution gate tests: PASS\n";
