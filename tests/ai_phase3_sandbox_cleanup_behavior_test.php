<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\ai\contract\DockerProcessRunner;
use app\console\ai\infrastructure\ProcessResult;
use app\console\ai\service\AgentSandboxManager;

function cleanupBehaviorExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

final class CleanupFailureRunner implements DockerProcessRunner
{
    public function run(array $argv, int $timeoutSeconds): ProcessResult
    {
        if (($argv[1] ?? '') === 'inspect') {
            return new ProcessResult(0, '{"com.funadmin.ai-agent":"true","com.funadmin.ai-task":"11","com.funadmin.ai-session":"22","com.funadmin.ai-volume":"volume-11"}', '');
        }
        if (($argv[1] ?? '') === 'rm') return new ProcessResult(1, '', 'remove failed');
        return new ProcessResult(0, '', '');
    }
}

final class CleanupMissingContainerRunner implements DockerProcessRunner
{
    public array $calls = [];

    public function run(array $argv, int $timeoutSeconds): ProcessResult
    {
        $this->calls[] = $argv;
        if (($argv[1] ?? '') === 'inspect') return new ProcessResult(1, '', 'No such container');
        if (($argv[1] ?? '') === 'volume' && ($argv[2] ?? '') === 'inspect') {
            $taskId = str_ends_with((string) ($argv[5] ?? ''), '-13') ? '13' : '12';
            return new ProcessResult(0, json_encode(['com.funadmin.ai-agent'=>'true','com.funadmin.ai-task'=>$taskId,'com.funadmin.ai-session'=>'22'], JSON_THROW_ON_ERROR), '');
        }
        if (($argv[1] ?? '') === 'volume') return new ProcessResult(0, '', '');
        return new ProcessResult(1, '', 'No such container');
    }
}

$root = dirname(__DIR__);
$controller = (string) file_get_contents($root . '/app/console/controller/ai/Ai.php');
$commandPath = $root . '/app/console/command/AiSandboxCleanup.php';
cleanupBehaviorExpect(is_file($commandPath), '缺少全局 CLI sandbox 清理命令');
$command = (string) file_get_contents($commandPath);
$console = (string) file_get_contents($root . '/config/console.php');
cleanupBehaviorExpect(str_contains($controller, "where('admin_id', \$adminId)"), '普通 API 必须按当前管理员查询任务');
cleanupBehaviorExpect(str_contains($controller, "whereIn('status', ['failed', 'cancelled', 'succeeded'])"), '普通 API 只允许查询终态任务');
cleanupBehaviorExpect(str_contains($controller, "where('completed_at', '<=', \$cutoff)"), '普通 API 只查询 retention 已过期任务');
cleanupBehaviorExpect(str_contains($command, 'AiTask::whereIn') && str_contains($command, 'cleanupOrphans'), 'CLI 必须查询全局候选并交给 cleanupOrphans');
cleanupBehaviorExpect(str_contains($controller, "['created', 'running', 'exported', 'failed', 'cleaning']"), '普通 API 必须允许重新 claim 已过期的 cleaning lease');
cleanupBehaviorExpect(str_contains($command, "['created', 'running', 'exported', 'failed', 'cleaning']"), 'CLI 必须允许重新 claim 已过期的 cleaning lease');
cleanupBehaviorExpect(substr_count($controller, "where('cleanup_lease_owner', \$owner)") >= 1, '普通 API 完成 cleanup 必须校验 lease owner');
cleanupBehaviorExpect(substr_count($command, "where('cleanup_lease_owner', \$owner)") >= 1, 'CLI 完成 cleanup 必须校验 lease owner');
cleanupBehaviorExpect(!str_contains($command, "where('admin_id'"), 'CLI 不得限制单个管理员');
cleanupBehaviorExpect(str_contains($console, "'ai:sandbox-cleanup'"), 'CLI 清理命令必须注册');
$migrationPath = $root . '/database/migrations/103_ai_sandbox_cleanup_leases.sql';
cleanupBehaviorExpect(is_file($migrationPath), '缺少当前最大编号加一的 103 cleanup lease migration');
$migration = (string) file_get_contents($migrationPath);
foreach (['heartbeat_at', 'sandbox_retained'] as $column) cleanupBehaviorExpect(str_contains($migration, "`{$column}`"), "103 缺少 {$column}");

$private = sys_get_temp_dir() . '/funadmin-ai-cleanup-' . bin2hex(random_bytes(4));
$workspace = $private . '/sandboxes/task-11';
mkdir($workspace, 0700, true);
$manager = new AgentSandboxManager(new CleanupFailureRunner(), $root, $private, []);
$updates = [];
$audits = [];
$cleaned = $manager->cleanupOrphans([
    ['id'=>11, 'conversation_id'=>22, 'status'=>'failed', 'sandbox_status'=>'exported', 'sandbox_retained'=>1,
        'container_task_id'=>'container-11', 'workspace_path'=>$workspace, 'completed_at'=>'2020-01-01 00:00:00', 'heartbeat_at'=>'2020-01-01 00:00:00'],
], 86400, 300,
    static function (int $taskId, array $data) use (&$updates): void { $updates[$taskId] = $data; },
    static function (string $event, array $data) use (&$audits): void { $audits[] = [$event, $data]; },
    strtotime('2020-01-03'),
    static fn (): bool => true,
    'worker-failure'
);
cleanupBehaviorExpect($cleaned === 0, 'cleanup 失败不得计为成功清理');
cleanupBehaviorExpect(($updates[11]['sandbox_status'] ?? '') === 'failed', 'cleanup 失败必须保留 failed 状态');
cleanupBehaviorExpect(($updates[11]['sandbox_retained'] ?? 0) === 1 && ($updates[11]['recovery_status'] ?? '') === 'recovery_required', 'cleanup 失败必须保留 retained/recovery 状态');
cleanupBehaviorExpect(($audits[0][0] ?? '') === 'ai.sandbox.cleanup_failed', 'cleanup 失败必须审计');
cleanupBehaviorExpect(is_dir($workspace), 'cleanup 失败必须保留 workspace 供恢复');

$ownerWorkspace = $private . '/sandboxes/task-owner';
mkdir($ownerWorkspace, 0700, true);
$ownerUpdates = [];
$ownerManager = new AgentSandboxManager(new CleanupMissingContainerRunner(), $root, $private, []);
$ownerCleaned = $ownerManager->cleanupOrphans([
    ['id'=>13, 'conversation_id'=>22, 'status'=>'failed', 'sandbox_status'=>'cleaning', 'sandbox_retained'=>1,
        'container_task_id'=>'container-13', 'sandbox_volume'=>'volume-13', 'workspace_path'=>$ownerWorkspace,
        'completed_at'=>'2020-01-01 00:00:00', 'heartbeat_at'=>'2020-01-01 00:00:00',
        'cleanup_lease_owner'=>'worker-old', 'cleanup_lease_expires_at'=>'2020-01-02 00:00:00'],
], 86400, 300,
    static function (int $taskId, array $data, string $owner) use (&$ownerUpdates): bool {
        $ownerUpdates[] = [$taskId, $data, $owner];
        return $owner === 'worker-new';
    },
    null,
    strtotime('2020-01-03'),
    static fn (array $task, string $owner): bool => $task['sandbox_status'] === 'cleaning'
        && $task['cleanup_lease_owner'] === 'worker-old'
        && $owner === 'worker-new',
    'worker-new'
);
cleanupBehaviorExpect($ownerCleaned === 1, '过期 cleaning lease 必须可由新 owner 接管并完成');
cleanupBehaviorExpect(($ownerUpdates[0][2] ?? '') === 'worker-new', '完成更新必须携带当前 lease owner，防止旧 owner 覆盖');

$missingWorkspace = $private . '/sandboxes/task-12';
mkdir($missingWorkspace, 0700, true);
$missingRunner = new CleanupMissingContainerRunner();
$missingManager = new AgentSandboxManager($missingRunner, $root, $private, []);
$missingManager->cleanup('container-12', $missingWorkspace, 12, 22, 'volume-12');
cleanupBehaviorExpect(!is_dir($missingWorkspace), '容器已删除时仍必须继续删除 workspace');
cleanupBehaviorExpect(in_array(['docker', 'volume', 'rm', 'volume-12'], $missingRunner->calls, true), '容器已删除时仍必须按 checkpoint 删除 volume');

echo "AI phase 3 sandbox cleanup behavior tests: PASS\n";
