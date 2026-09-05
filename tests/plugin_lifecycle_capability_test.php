<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use fun\plugins\DatabaseCapabilityGuard;
use fun\plugins\LifecycleProgress;
use fun\plugins\LifecycleState;

function lifecycleExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function lifecycleReject(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        lifecycleExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $contains);
}

lifecycleExpect(in_array('updating', LifecycleState::all(), true), '生命周期必须包含 updating');
lifecycleExpect(LifecycleState::canTransition('disabled', 'updating'), '禁用插件应可进入 updating');
lifecycleExpect(LifecycleState::canTransition('failed', 'updating'), '失败插件修复更新应可进入 updating');
lifecycleExpect(LifecycleState::canTransition('updating', 'disabled'), '更新完成应回到 disabled');
lifecycleExpect(LifecycleState::canTransition('updating', 'failed'), '更新失败应进入 failed');
lifecycleExpect(!LifecycleState::canTransition('enabled', 'updating'), '启用状态不得直接更新');

$events = [];
$progress = new LifecycleProgress(static function (string $stage, int $percent, ?string $recoveryPath) use (&$events): void {
    $events[] = [$stage, $percent, $recoveryPath];
});
foreach (['validate', 'deploy', 'hooks', 'migration', 'resources', 'permissions', 'complete'] as $stage) {
    $progress->advance($stage);
}
lifecycleExpect(array_column($events, 0) === ['validate', 'deploy', 'hooks', 'migration', 'resources', 'permissions', 'complete'], '必须逐阶段记录完整生命周期');
lifecycleExpect(array_column($events, 1) === [5, 20, 35, 55, 75, 90, 100], '阶段进度必须单调且 complete 为 100');
lifecycleReject(static fn () => $progress->advance('migration'), '阶段顺序');

$failed = [];
$failureProgress = new LifecycleProgress(static function (string $stage, int $percent, ?string $recoveryPath) use (&$failed): void {
    $failed[] = [$stage, $percent, $recoveryPath];
});
$failureProgress->advance('validate');
$failureProgress->fail('deploy', '/safe/backup/demo');
lifecycleExpect($failed[1] === ['deploy', 5, '/safe/backup/demo'], '失败阶段必须保留当前进度与恢复路径');

$guard = new DatabaseCapabilityGuard(static fn (string $plugin, string $version): ?string => match ($version) {
    '3.0.0' => '006_latest.sql',
    '2.0.0' => '005_expand_schema.sql',
    '1.0.0' => '002_initial.sql',
    default => null,
});
$guard->assertDeployable('demo', '2.0.0', '004_data.sql');
$guard->assertDeployable('demo', '3.0.0', '004_data.sql');
lifecycleReject(static fn () => $guard->assertDeployable('demo', '1.0.0', '004_data.sql'), '数据库能力');
lifecycleReject(static fn () => $guard->assertDeployable('demo', '0.9.0', '004_data.sql'), '版本历史');

$pluginServiceSource = (string) file_get_contents(dirname(__DIR__) . '/app/backend/service/PluginService.php');
$auditServiceSource = (string) file_get_contents(dirname(__DIR__) . '/app/backend/service/PluginOperationAuditService.php');
lifecycleExpect(str_contains($auditServiceSource, "'result' => 'failed'"), '生命周期失败必须写入 operation history');
foreach (['PluginOperation::create', 'PluginResource::', 'MigrationService::', 'AdminMenu::', 'ResourceRegistryService::'] as $infrastructureCall) {
    lifecycleExpect(!str_contains($pluginServiceSource, $infrastructureCall), '生命周期编排不得直接访问基础设施：' . $infrastructureCall);
}
lifecycleExpect(substr_count($pluginServiceSource, "\n") < 560, 'PluginService 必须拆分为聚焦的生命周期编排服务');
lifecycleExpect(str_contains($pluginServiceSource, '$this->operationProgress[$name]'), '最外层操作必须跟踪并清理阶段进度');
$runPackageStart = strpos($pluginServiceSource, 'public function runPackageOperation');
$recordFailureStart = strpos($pluginServiceSource, 'public function recordFailure');
$runPackageSource = substr($pluginServiceSource, $runPackageStart, $recordFailureStart - $runPackageStart);
lifecycleExpect(!str_contains($runPackageSource, 'unset($this->packageOperationTokens'), 'package 回调不得在失败审计前清空阶段上下文');

echo "plugin lifecycle capability tests: PASS\n";
