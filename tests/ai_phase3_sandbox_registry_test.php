<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\ai\contract\DockerProcessRunner;
use app\console\ai\infrastructure\ProcessResult;
use app\console\ai\service\AgentSandboxManager;
use app\console\ai\service\AgentToolRegistry;
use app\console\ai\service\AiChangeSetService;

function phase3SandboxExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class FakeDockerRunner implements DockerProcessRunner
{
    /** @var list<array{argv: array, timeout: int}> */
    public array $calls = [];
    public bool $available = true;
    public bool $failInitialization = false;
    public ?string $rollbackContainerFailure = null;

    public function run(array $argv, int $timeoutSeconds): ProcessResult
    {
        $this->calls[] = ['argv' => $argv, 'timeout' => $timeoutSeconds];
        if (!$this->available) {
            throw new RuntimeException('docker unavailable');
        }
        if ($this->failInitialization && ($argv[1] ?? '') === 'cp' && !str_contains((string) ($argv[2] ?? ''), ':/workspace/.')) {
            return new ProcessResult(1, '', 'cp failed');
        }
        if ($this->rollbackContainerFailure === 'inspect' && ($argv[1] ?? '') === 'inspect') {
            return new ProcessResult(1, '', 'inspect failed');
        }
        if ($this->rollbackContainerFailure === 'rm' && ($argv[1] ?? '') === 'rm') {
            return new ProcessResult(1, '', 'rm failed');
        }
        if (($argv[1] ?? '') === 'create') {
            return new ProcessResult(0, "container-id\n", '');
        }
        if (($argv[1] ?? '') === 'inspect') {
            return new ProcessResult(0, '{"com.funadmin.ai-agent":"true","com.funadmin.ai-task":"101","com.funadmin.ai-session":"77","com.funadmin.ai-volume":"funadmin-ai-101-test"}', '');
        }
        if (($argv[1] ?? '') === 'volume' && ($argv[2] ?? '') === 'inspect') {
            return new ProcessResult(0, '{"com.funadmin.ai-agent":"true","com.funadmin.ai-task":"101","com.funadmin.ai-session":"77"}', '');
        }
        if (($argv[1] ?? '') === 'exec' && in_array('git', $argv, true)) {
            return new ProcessResult(0, '', '');
        }
        if (($argv[1] ?? '') === 'cp' && str_contains((string) ($argv[2] ?? ''), ':/workspace/.')) {
            $destination = (string) ($argv[3] ?? '');
            if (!is_dir($destination)) mkdir($destination, 0700, true);
            file_put_contents($destination . '/baseline-proof.txt', 'after execution');
        }
        return new ProcessResult(0, '', '');
    }
}

$root = dirname(__DIR__);
$expectedTools = ['read', 'list', 'search', 'git_status', 'git_diff', 'write', 'create', 'move', 'delete', 'shell', 'test', 'build', 'migration', 'git_write', 'crud_proposal'];
$registry = new AgentToolRegistry($expectedTools);
phase3SandboxExpect($registry->names() === $expectedTools, 'Registry 工具集合必须严格限于生产 allowlist 与镜像可用工具交集');
phase3SandboxExpect((new AgentToolRegistry())->names() === [], '未配置 AI_TOOL_ALLOWLIST 必须默认 deny');
phase3SandboxExpect((new AgentToolRegistry(['read', 'dependency', 'unknown']))->names() === ['read'], 'Registry 不得暴露镜像不支持或未知工具');
foreach ($expectedTools as $name) {
    $definition = $registry->get($name);
    phase3SandboxExpect(($definition['schema']['type'] ?? null) === 'object', "{$name} 缺少 JSON Schema");
    foreach (['operation', 'risk', 'timeout', 'sideEffects'] as $field) {
        phase3SandboxExpect(array_key_exists($field, $definition), "{$name} 缺少元数据 {$field}");
    }
}
foreach (['host_sensitive', 'deploy', 'push', 'host_credentials'] as $forbidden) {
    phase3SandboxExpect($registry->decision($forbidden) === 'deny', "{$forbidden} 必须永久拒绝");
}
$registry->validate('read', ['path' => 'app/AppService.php']);
foreach ([['read', []], ['shell', ['argv' => 'id']], ['write', ['path' => '../.env', 'content' => 'x']]] as [$tool, $arguments]) {
    $rejected = false;
    try {
        $registry->validate($tool, $arguments);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    phase3SandboxExpect($rejected, "{$tool} 参数 Schema 必须拒绝无效输入");
}

$runner = new FakeDockerRunner();
$private = sys_get_temp_dir() . '/funadmin-ai-p3-' . bin2hex(random_bytes(4));
$manager = new AgentSandboxManager($runner, $root, $private, [
    'image' => 'funadmin/ai-agent@sha256:' . str_repeat('a', 64),
    'cpu' => 0.5,
    'memory_mb' => 256,
    'pids' => 64,
    'task_timeout' => 30,
    'network_enabled' => false,
]);
$proofPath = $root . '/baseline-proof.txt';
file_put_contents($proofPath, 'before execution');
$sandbox = $manager->create(101, 77);
unlink($proofPath);
phase3SandboxExpect($sandbox['containerId'] === 'container-id', 'create 必须返回容器 ID');
$createCall = current(array_filter($runner->calls, static fn (array $call): bool => ($call['argv'][1] ?? '') === 'create'));
$create = $createCall['argv'];
foreach (['docker', 'create', '--read-only', '--security-opt', 'no-new-privileges', '--cap-drop', 'ALL', '--cpus', '0.5', '--memory', '256m', '--pids-limit', '64', '--network', 'none', '--user', '10001:10001'] as $required) {
    phase3SandboxExpect(in_array($required, $create, true), "docker create 缺少安全参数 {$required}");
}
phase3SandboxExpect(!in_array('/var/run/docker.sock', $create, true), '禁止挂载 docker.sock');
phase3SandboxExpect(count(array_filter($create, static fn (string $arg): bool => str_contains($arg, 'type=bind'))) === 0, '不得将任何宿主目录 bind 到容器');
phase3SandboxExpect(count(array_filter($create, static fn (string $arg): bool => str_contains($arg, 'type=volume') && str_contains($arg, 'dst=/workspace'))) === 1, 'workspace 必须使用命名 volume');
phase3SandboxExpect(($create[array_search('--network', $create, true) + 1] ?? '') === 'none', '容器必须始终使用 --network none');
$copyRoot = $sandbox['workspace'];
phase3SandboxExpect(is_file($copyRoot . '/baseline-manifest.json'), '私有目录必须保存完整 baseline manifest');
$manifest = json_decode((string) file_get_contents($copyRoot . '/baseline-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
phase3SandboxExpect(isset($manifest['files']['app/AppService.php']['sha256'], $manifest['digest']), 'baseline 必须包含未提交源码逐文件 hash 与总 hash');
phase3SandboxExpect(!isset($manifest['files']['.env']) && !array_filter(array_keys($manifest['files']), static fn (string $path): bool => str_starts_with($path, 'runtime/')), 'baseline 必须排除环境文件与 runtime 私有数据');
$createIndex = array_search($createCall, $runner->calls, true);
$cpIndexes = array_keys(array_filter($runner->calls, static fn (array $call): bool => ($call['argv'][1] ?? '') === 'cp'));
phase3SandboxExpect($cpIndexes !== [] && min($cpIndexes) > $createIndex, '必须先 docker create 再 docker cp 可信快照');
$chownCall = ['docker', 'run', '--rm', '--network', 'none', '--volumes-from', 'container-id', '--user', '0:0', 'funadmin/ai-agent@sha256:' . str_repeat('a', 64), 'chown', '-R', '10001:10001', '/workspace'];
phase3SandboxExpect(in_array($chownCall, array_column($runner->calls, 'argv'), true), 'docker cp 后必须在断网辅助容器中修正 workspace 所有权');

$manager->start('container-id');
$manager->exec('container-id', ['php', '-v'], 5);
$manager->export('container-id', $private . '/export');
$artifact = $manager->exportChanges('container-id', $copyRoot);
phase3SandboxExpect(is_file($artifact['patchPath']) && is_file($artifact['bundlePath']) && is_file($artifact['manifestPath']), 'cleanup 前必须导出 patch、binary-safe bundle 与 manifest');
phase3SandboxExpect(is_file($artifact['baselineManifestPath']) && is_file($artifact['baselineBundlePath']), '导出必须持久化 cleanup 后仍可用的 baseline manifest 与 binary-safe bundle');
phase3SandboxExpect(hash_file('sha256', $artifact['baselineManifestPath']) === $artifact['baselineManifestSha256'], 'baseline manifest hash 必须可验证');
phase3SandboxExpect(hash_file('sha256', $artifact['baselineBundlePath']) === $artifact['baselineBundleSha256'], 'baseline bundle hash 必须可验证');
phase3SandboxExpect(hash_file('sha256', $artifact['bundlePath']) === $artifact['bundleSha256'], '导出 bundle hash 必须可验证');
$bundleLines = file($artifact['bundlePath'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
phase3SandboxExpect(is_array($bundleLines) && isset($bundleLines[0]) && str_contains($bundleLines[0], 'ndjson'), '完整快照 bundle 必须使用流式 NDJSON 格式，禁止 files 全量 JSON 对象');
phase3SandboxExpect(!str_contains((string) file_get_contents($artifact['bundlePath']), '"files":{'), '完整快照 bundle 禁止构造全量 files JSON 对象');
foreach (['node_modules', 'vendor', 'dist', 'build', '.cache', 'coverage'] as $excludedRoot) {
    phase3SandboxExpect(!array_filter(array_keys($artifact['remoteManifest']['files']), static fn (string $path): bool => $path === $excludedRoot || str_starts_with($path, $excludedRoot . '/')), "导出 manifest 必须排除 {$excludedRoot}");
}
phase3SandboxExpect(($artifact['baselineManifest']['files']['baseline-proof.txt']['sha256'] ?? '') === hash('sha256', 'before execution'), 'base hash 必须来自执行前源码');
phase3SandboxExpect(($artifact['remoteManifest']['files']['baseline-proof.txt']['sha256'] ?? '') === hash('sha256', 'after execution'), 'remote manifest 必须独立记录执行后源码');
phase3SandboxExpect($artifact['baselineDigest'] === $artifact['baselineManifest']['digest'], 'base digest 必须来自执行前 baseline');
$task = ['id'=>101, 'conversation_id'=>77];
$firstChangeSet = AiChangeSetService::attributes($task, $artifact, 7);
$secondArtifact = array_replace($artifact, ['patchSha256'=>hash('sha256', 'different patch')]);
$secondChangeSet = AiChangeSetService::attributes($task, $secondArtifact, 7);
$duplicateChangeSet = AiChangeSetService::attributes($task, $artifact, 7);
phase3SandboxExpect($firstChangeSet['base_digest'] === $artifact['baselineDigest'], 'base digest 必须独立保存');
phase3SandboxExpect(($firstChangeSet['manifest']['artifact'] ?? null) === ['admin_id'=>7,'conversation_id'=>77,'task_id'=>101], 'ChangeSet 私有制品必须强制绑定管理员、会话与任务');
phase3SandboxExpect($firstChangeSet['digest'] !== $secondChangeSet['digest'], '同 baseline 的不同 patch 必须生成不同 change-set digest');
phase3SandboxExpect($firstChangeSet['idempotency_key'] !== $secondChangeSet['idempotency_key'], '同 baseline 的不同 patch 必须可并存');
phase3SandboxExpect($firstChangeSet['digest'] === $duplicateChangeSet['digest'] && $firstChangeSet['idempotency_key'] === $duplicateChangeSet['idempotency_key'], '完全相同 artifact 必须保持幂等');
phase3SandboxExpect($artifact['remoteManifestSha256'] !== $artifact['patchSha256'], 'remote manifest hash 与 patch hash 必须独立');
$patchArgv = array_column($runner->calls, 'argv');
phase3SandboxExpect(in_array(['docker','exec','container-id','git','add','-N','--all'], $patchArgv, true), 'binary patch 必须先 intent-to-add 纳入未跟踪文件');
phase3SandboxExpect(!array_filter(array_keys($artifact['manifest']['files']), static fn (string $path): bool => $path === '.git' || str_starts_with($path, '.git/')), '导出 artifact 必须排除 sandbox 临时 .git');
$manager->cleanup('container-id', $copyRoot, 101, 77);
phase3SandboxExpect(in_array(['docker', 'start', 'container-id'], array_column($runner->calls, 'argv'), true), '必须支持 start');
phase3SandboxExpect(in_array(['docker', 'exec', 'container-id', 'php', '-v'], array_column($runner->calls, 'argv'), true), 'exec 必须保持 argv 边界，禁止 shell 拼接');
phase3SandboxExpect(!is_dir($copyRoot), 'cleanup 必须删除私有工作副本');
$inspectIndex = array_search(['docker', 'inspect', '--format', '{{json .Config.Labels}}', 'container-id'], array_column($runner->calls, 'argv'), true);
$rmIndex = array_search(['docker', 'rm', '-f', 'container-id'], array_column($runner->calls, 'argv'), true);
phase3SandboxExpect($inspectIndex !== false && $rmIndex !== false && $inspectIndex < $rmIndex, 'cleanup 必须先 inspect 校验标签再删除');
phase3SandboxExpect(in_array(['docker','volume','rm','funadmin-ai-101-test'], array_column($runner->calls, 'argv'), true), 'cleanup 必须删除 inspect 标签绑定的命名 volume');
$orphanWorkspace = $private . '/sandboxes/orphan-101'; mkdir($orphanWorkspace, 0700, true);
$updates = []; $audits = [];
$cleaned = $manager->cleanupOrphans([
    ['id'=>101,'conversation_id'=>77,'status'=>'failed','sandbox_status'=>'running','sandbox_retained'=>1,'container_task_id'=>'container-id','workspace_path'=>$orphanWorkspace,'completed_at'=>'2020-01-01 00:00:00','heartbeat_at'=>'2020-01-01 00:00:00'],
    ['id'=>102,'conversation_id'=>77,'status'=>'running','sandbox_status'=>'running','sandbox_retained'=>0,'container_task_id'=>'running-id','workspace_path'=>$private.'/sandboxes/running','completed_at'=>'2020-01-01 00:00:00','heartbeat_at'=>'2020-01-01 00:00:00'],
    ['id'=>103,'conversation_id'=>77,'status'=>'paused','sandbox_status'=>'running','sandbox_retained'=>1,'container_task_id'=>'paused-id','workspace_path'=>$private.'/sandboxes/paused','completed_at'=>'2020-01-01 00:00:00','heartbeat_at'=>'2020-01-01 00:00:00'],
], 86400, 300, static function (int $taskId, array $data) use (&$updates): void { $updates[$taskId] = $data; }, static function (string $event, array $data) use (&$audits): void { $audits[] = [$event,$data]; }, strtotime('2020-01-03'), static fn (): bool => true, 'worker-main');
phase3SandboxExpect($cleaned === 1 && isset($updates[101]) && !isset($updates[102], $updates[103]), 'orphan cleanup 只能清理超过 retention 的终态租约任务');
phase3SandboxExpect(($updates[101]['sandbox_status'] ?? '') === 'cleaned' && !empty($updates[101]['cleanup_at']), 'cleanup 必须更新 sandbox_status/cleanup_at');
phase3SandboxExpect(count($audits) === 1 && $audits[0][0] === 'ai.sandbox.cleanup', 'cleanup 必须审计');
phase3SandboxExpect(!in_array(['docker','ps','-aq','--filter','label=com.funadmin.ai-agent=true'], array_column($runner->calls, 'argv'), true), 'cleanupOrphans 禁止全局扫描 Docker');
$concurrentWorkspace = $private . '/sandboxes/concurrent-104'; mkdir($concurrentWorkspace, 0700, true);
$claimed = false;
$claim = static function (array $task, string $owner, int $leaseExpiresAt) use (&$claimed): bool {
    if ($claimed) return false;
    $claimed = true;
    return true;
};
$concurrentTask = [['id'=>101,'conversation_id'=>77,'status'=>'failed','sandbox_status'=>'failed','sandbox_retained'=>1,'container_task_id'=>'container-id','workspace_path'=>$concurrentWorkspace,'completed_at'=>'2020-01-01 00:00:00','heartbeat_at'=>'2020-01-01 00:00:00']];
$firstClaimedCleanup = $manager->cleanupOrphans($concurrentTask, 86400, 300, static function (): void {}, null, strtotime('2020-01-03'), $claim, 'worker-a');
$secondClaimedCleanup = $manager->cleanupOrphans($concurrentTask, 86400, 300, static function (): void {}, null, strtotime('2020-01-03'), $claim, 'worker-b');
phase3SandboxExpect($firstClaimedCleanup === 1 && $secondClaimedCleanup === 0, '并发 cleanup 只有 CAS claim 成功方可执行，第二方必须跳过');

foreach (['inspect', 'rm'] as $rollbackContainerFailure) {
    $rollbackRunner = new FakeDockerRunner();
    $rollbackRunner->failInitialization = true;
    $rollbackRunner->rollbackContainerFailure = $rollbackContainerFailure;
    try {
        (new AgentSandboxManager($rollbackRunner, $root, $private, [
            'image' => 'funadmin/ai-agent@sha256:' . str_repeat('a', 64),
        ]))->create(101, 77);
        throw new RuntimeException('docker cp 初始化失败未抛出');
    } catch (RuntimeException) {
    }
    $rollbackCalls = array_column($rollbackRunner->calls, 'argv');
    phase3SandboxExpect(
        count(array_filter($rollbackCalls, static fn (array $argv): bool => ($argv[1] ?? '') === 'volume' && ($argv[2] ?? '') === 'rm')) === 1,
        "容器 {$rollbackContainerFailure} 回滚失败时仍必须独立校验并删除标签匹配的 volume"
    );
}

$outside = sys_get_temp_dir() . '/outside-' . bin2hex(random_bytes(3)); mkdir($outside); $blocked = false;
try { $manager->cleanup('', $outside, 101, 77); } catch (RuntimeException) { $blocked = true; }
phase3SandboxExpect($blocked && is_dir($outside), 'deleteTree 必须拒绝 sandbox root 外 canonical path'); rmdir($outside);

$runner->available = false;
$closed = false;
try {
    $manager->create(102);
} catch (RuntimeException $exception) {
    $closed = str_contains($exception->getMessage(), 'Docker');
}
phase3SandboxExpect($closed, 'Docker 不可用必须 fail-closed，禁止回退宿主执行');

$dockerfile = (string) file_get_contents($root . '/docker/ai-agent/Dockerfile');
$entrypoint = (string) file_get_contents($root . '/docker/ai-agent/entrypoint.sh');
phase3SandboxExpect(str_contains($dockerfile, 'FROM ') && preg_match('/FROM\s+[^\s]+@sha256:[a-f0-9]{64}/', $dockerfile) === 1, '基础镜像必须固定 digest');
phase3SandboxExpect(str_contains($dockerfile, 'USER 10001:10001'), '镜像必须使用固定非 root 用户');
foreach (['php', 'composer', 'node', 'npm', 'npx', 'git', 'ripgrep'] as $binary) phase3SandboxExpect(str_contains(strtolower($dockerfile), $binary), "镜像必须固定安装 {$binary}");
phase3SandboxExpect(str_contains($dockerfile, '--version'), '镜像构建必须验证工具版本');
phase3SandboxExpect(preg_match('/ARG COMPOSER_VERSION=\d+\.\d+\.\d+/', $dockerfile) === 1, 'Composer 必须固定 PHAR 版本');
phase3SandboxExpect(preg_match('/ARG COMPOSER_SHA256=[a-f0-9]{64}/', $dockerfile) === 1, 'Composer PHAR 必须固定 SHA-256');
phase3SandboxExpect(str_contains($dockerfile, 'sha256sum -c -'), 'Composer PHAR 必须在安装前校验完整性');
phase3SandboxExpect(preg_match('/apt-get install[^\n]*composer=/', $dockerfile) !== 1, '不得安装与官方 PHP 镜像冲突的 Debian Composer 包');
phase3SandboxExpect(str_contains($entrypoint, 'exec "$@"'), '入口必须使用 exec 保留 argv');

echo "AI phase 3 sandbox and registry tests: PASS\n";
