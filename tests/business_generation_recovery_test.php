<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

define('FUNADMIN_CRUD_HELPER_TESTING', true);

use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\console\service\DatabaseGenerationStateRepository;
use app\console\service\GeneratedFileBaselineRepository;
use app\console\service\GenerationInterruptionException;
use app\console\service\GenerationResourceTransaction;
use app\console\service\GenerationTransactionService;
use think\App;

final class RecoveryStateRepository
{
    public array $baselines = [];
    public array $generations = [];
    public array $modules = [];
    public bool $failCommit = false;

    public function transaction(callable $operation): mixed
    {
        $snapshot = [$this->baselines, $this->generations, $this->modules];
        try {
            $result = $operation();
            if ($this->failCommit) {
                throw new RuntimeException('db failure');
            }
            return $result;
        } catch (Throwable $exception) {
            [$this->baselines, $this->generations, $this->modules] = $snapshot;
            throw $exception;
        }
    }

    public function loadBaselines(int $moduleId): array
    {
        return $this->baselines;
    }

    public function commitGeneration(int $moduleId, int $generationId, array $records, string $transactionId, string $planDigest): void
    {
        $this->baselines = $records;
        $this->generations[$generationId] = [
            'business_module_id' => $moduleId,
            'status' => 'completed',
            'transaction_id' => $transactionId,
            'plan_digest' => $planDigest,
            'recovery_status' => 'none',
        ];
        $this->modules[$moduleId] = ['generation_status' => 'generated'];
    }

    public function isGenerationCommitted(int $moduleId, int $generationId, string $transactionId, string $planDigest): bool
    {
        $generation = $this->generations[$generationId] ?? null;
        return is_array($generation)
            && ($generation['business_module_id'] ?? null) === $moduleId
            && ($generation['status'] ?? null) === 'completed'
            && ($generation['transaction_id'] ?? null) === $transactionId
            && ($generation['plan_digest'] ?? null) === $planDigest
            && ($generation['recovery_status'] ?? null) === 'none';
    }
}

final class RecoveryResources
{
    public array $state = ['old'];
    private array $before = [];
    public bool $fail = false;

    public function begin(): void
    {
        $this->before = $this->state;
    }

    public function apply(array $resources): void
    {
        if ($this->fail) {
            throw new RuntimeException('resource failure');
        }
        $this->state = $resources;
    }

    public function commit(): void
    {
    }

    public function rollback(): void
    {
        $this->state = $this->before;
    }
}

function recoveryExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function recoveryReject(callable $operation, string $contains): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        recoveryExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $contains);
}

function recoveryRemoveTree(string $path): void
{
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function recoveryBundle(string $root): array
{
    $files = [];
    $remote = [];
    foreach (['first', 'middle', 'last'] as $name) {
        $path = 'managed/' . $name . '.txt';
        $old = 'old-' . $name;
        $new = 'new-' . $name;
        $files[] = [
            'path' => $path, 'status' => 'update', 'artifactType' => 'service',
            'baseHash' => hash('sha256', $old), 'localHash' => hash('sha256', $old),
            'remoteHash' => hash('sha256', $new),
            'mergedHash' => hash('sha256', $new), 'nextBaseHash' => hash('sha256', $new), 'contentKind' => 'text',
        ];
        $remote[$path] = $new;
    }
    $createdPath = 'managed/created.txt';
    $created = 'new-created';
    $files[] = [
        'path' => $createdPath, 'status' => 'create', 'artifactType' => 'service',
        'baseHash' => null, 'localHash' => null, 'remoteHash' => hash('sha256', $created),
        'mergedHash' => hash('sha256', $created), 'nextBaseHash' => hash('sha256', $created), 'contentKind' => 'text',
    ];
    $remote[$createdPath] = $created;
    $plan = ['dryRun' => true, 'managed' => true, 'blocked' => false, 'definitionHash' => str_repeat('a', 64), 'files' => $files];
    $plan['planDigest'] = hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
    $resources = [['resourceKey' => 'permission|sample|sample:list']];
    return [
        'plan' => $plan, 'remoteContents' => $remote, 'mergedTextContents' => $remote,
        'definitionHash' => str_repeat('a', 64), 'schemaHash' => str_repeat('b', 64),
        'registryHash' => str_repeat('c', 64), 'templateVersion' => 'm5-production-v1',
        'migrationHash' => str_repeat('d', 64), 'resourcesHash' => hash('sha256', CrudDefinition::canonicalJson($resources)),
        'resources' => $resources,
    ];
}

$root = sys_get_temp_dir() . '/funadmin-generation-recovery-' . bin2hex(random_bytes(5));
mkdir($root . '/managed', 0755, true);
foreach (['first', 'middle', 'last'] as $name) file_put_contents($root . '/managed/' . $name . '.txt', 'old-' . $name);

try {
    $state = new RecoveryStateRepository();
    $resources = new RecoveryResources();
    $blobs = new GeneratedFileBaselineRepository($root, $state);
    $tokens = new ConfirmationToken($root, 'recovery-secret');
    $bundle = recoveryBundle($root);
    $current = static fn (): array => [
        'definitionHash' => str_repeat('a', 64), 'schemaHash' => str_repeat('b', 64),
        'registryHash' => str_repeat('c', 64), 'templateVersion' => 'm5-production-v1',
        'migrationHash' => str_repeat('d', 64), 'resourcesHash' => $bundle['resourcesHash'],
    ];

    foreach ([0, 1, 2] as $faultIndex) {
        foreach (['first', 'middle', 'last'] as $name) file_put_contents($root . '/managed/' . $name . '.txt', 'old-' . $name);
        $fault = static function (string $point, int $index) use ($faultIndex): void {
            if ($point === 'after_file_rename' && $index === $faultIndex) throw new RuntimeException('rename fault');
        };
        $service = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current, $fault);
        recoveryReject(static fn () => $service->execute(1, 20 + $faultIndex, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle))), 'rename fault');
        foreach (['first', 'middle', 'last'] as $name) recoveryExpect(file_get_contents($root . '/managed/' . $name . '.txt') === 'old-' . $name, 'first/middle/last rename 失败必须逆序回滚');
    }

    $uncertainCrash = static function (string $point, int $index): void {
        if ($point === 'after_safe_commit' && $index === 1) throw new GenerationInterruptionException('pre-checkpoint crash');
    };
    $service = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current, $uncertainCrash);
    recoveryReject(static fn () => $service->execute(1, 29, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle))), 'pre-checkpoint crash');
    $uncertainId = $service->stale()[0]['transaction_id'];
    (new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current))->recover($uncertainId);
    foreach (['first', 'middle', 'last'] as $name) recoveryExpect(file_get_contents($root . '/managed/' . $name . '.txt') === 'old-' . $name, 'rename 后 checkpoint 前崩溃必须从 target/backup hash 推断并回滚');

    $resources->fail = true;
    $service = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current);
    recoveryReject(static fn () => $service->execute(1, 30, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle))), 'resource failure');
    recoveryExpect(file_get_contents($root . '/managed/first.txt') === 'old-first', 'resource 失败必须回滚文件');
    $resources->fail = false;

    $sharedBlob = $blobs->prepare('new-first');
    $middleBlob = $blobs->inspectExpected(hash('sha256', 'new-middle'));
    foreach (['after_blob_materialized', 'before_blob_checkpoint'] as $faultPoint) {
        foreach (['first', 'middle', 'last'] as $name) {
            file_put_contents($root . '/managed/' . $name . '.txt', 'old-' . $name);
        }
        @unlink($root . '/managed/created.txt');
        $resources->state = ['old'];
        $blobCrash = static function (string $point, int $index) use ($faultPoint): void {
            if ($point === $faultPoint && $index === 1) {
                throw new GenerationInterruptionException($faultPoint . ' crash');
            }
        };
        $service = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current, $blobCrash);
        recoveryReject(
            static fn () => $service->execute(1, 30, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle))),
            $faultPoint . ' crash'
        );
        recoveryExpect(is_file($root . '/runtime/private/business-development/' . $middleBlob['path']), '故障点必须位于 blob 物化后');
        $stale = $service->stale();
        $transactionId = $stale[0]['transaction_id'];
        $journalBlobs = $service->inspect($transactionId)['prepared_blobs'];
        recoveryExpect(count($journalBlobs) === 4, '任何 blob 物化前 WAL 必须预登记全部 expected blob');
        recoveryExpect(($journalBlobs[0]['preexisting'] ?? null) === true, '共享既有 blob 必须在物化前 checkpoint preexisting=true');
        recoveryExpect(($journalBlobs[1]['preexisting'] ?? null) === false, '事务新 blob 必须在物化前 checkpoint preexisting=false');
        (new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current))->recover($transactionId);
        recoveryExpect(!is_file($root . '/runtime/private/business-development/' . $middleBlob['path']), '新实例必须根据 WAL 删除本事务新建且未被 DB 引用的 blob');
        recoveryExpect(is_file($root . '/runtime/private/business-development/' . $sharedBlob['path']), '共享既有 blob 绝不能被恢复删除');
    }

    $ordinaryFailureBlob = $blobs->inspectExpected(hash('sha256', 'new-middle'));
    recoveryExpect(!is_file($root . '/runtime/private/business-development/' . $ordinaryFailureBlob['path']), '普通异常测试前 blob 必须不存在');
    $blobFault = static function (string $point, int $index): void {
        if ($point === 'after_blob_prepare' && $index === 1) throw new RuntimeException('blob failure');
    };
    $service = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current, $blobFault);
    recoveryReject(static fn () => $service->execute(1, 30, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle))), 'blob failure');
    recoveryExpect(file_get_contents($root . '/managed/first.txt') === 'old-first', 'blob 失败必须回滚文件');
    recoveryExpect($resources->state === ['old'], 'blob 失败必须回滚资源');
    recoveryExpect(!is_file($root . '/runtime/private/business-development/' . $ordinaryFailureBlob['path']), '普通 Throwable 必须从最新 WAL 回滚已物化 blob');

    $state->failCommit = true;
    $service = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current);
    recoveryReject(static fn () => $service->execute(1, 30, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle))), 'db failure');
    recoveryExpect(file_get_contents($root . '/managed/first.txt') === 'old-first' && $resources->state === ['old'], 'DB 失败必须回滚文件、资源和 blob');
    $state->failCommit = false;

    foreach (['first', 'middle', 'last'] as $name) {
        file_put_contents($root . '/managed/' . $name . '.txt', 'old-' . $name);
    }
    @unlink($root . '/managed/created.txt');
    $committedCrash = static function (string $point): void {
        if ($point === 'after_resource_commit') {
            throw new GenerationInterruptionException('resource committed crash');
        }
    };
    $service = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current, $committedCrash);
    recoveryReject(static fn () => $service->execute(1, 34, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle))), 'resource committed crash');
    $committedJournal = $service->stale()[0];
    $committedId = $committedJournal['transaction_id'];
    $transactionDirectory = $root . '/runtime/private/business-development/transactions/' . $committedId;
    recoveryExpect(($state->generations[34]['status'] ?? null) === 'completed'
        && ($state->generations[34]['transaction_id'] ?? null) === $committedId,
        '故障时数据库必须已精确记录 completed generation 与 transaction_id');
    recoveryExpect(is_dir($transactionDirectory), 'WAL completed 前崩溃必须保留事务临时目录');
    $committedBlobPaths = array_column($state->baselines, 'base_storage_path');
    foreach ($committedBlobPaths as $blobPath) {
        recoveryExpect(is_file($root . '/runtime/private/business-development/' . $blobPath), '故障时新 baseline blob 必须已提交');
    }
    $restarted = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current);
    $recovered = $restarted->recover($committedId);
    recoveryExpect($recovered['state'] === 'completed' && $restarted->inspect($committedId)['state'] === 'completed', '已提交证据必须 finalize WAL completed');
    foreach (['first', 'middle', 'last'] as $name) {
        recoveryExpect(file_get_contents($root . '/managed/' . $name . '.txt') === 'new-' . $name, '已提交恢复不得回滚目标文件');
    }
    recoveryExpect(file_get_contents($root . '/managed/created.txt') === 'new-created', '已提交恢复必须保留新文件');
    recoveryExpect($resources->state === $bundle['resources'], '已提交恢复必须保留资源');
    recoveryExpect(count($state->baselines) === 4, '已提交恢复必须保留新 baseline DB 记录');
    foreach ($committedBlobPaths as $blobPath) {
        recoveryExpect(is_file($root . '/runtime/private/business-development/' . $blobPath), '已提交恢复不得删除已引用 baseline blob');
    }
    recoveryExpect(!is_dir($transactionDirectory), 'finalize 必须清理 transaction 临时目录');

    foreach (['first', 'middle', 'last'] as $name) {
        file_put_contents($root . '/managed/' . $name . '.txt', 'old-' . $name);
    }
    @unlink($root . '/managed/created.txt');
    $resources->state = ['old'];
    $checkpointFailure = static function (string $point): void {
        if ($point === 'after_resource_commit') {
            throw new RuntimeException('completed checkpoint failure');
        }
    };
    $service = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current, $checkpointFailure);
    recoveryReject(static fn () => $service->execute(1, 35, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle))), 'completed checkpoint failure');
    $checkpointJournal = $service->stale()[0];
    $checkpointId = $checkpointJournal['transaction_id'];
    recoveryExpect(file_get_contents($root . '/managed/created.txt') === 'new-created'
        && ($state->generations[35]['transaction_id'] ?? null) === $checkpointId,
        'DB 提交后的 checkpoint 异常不得触发 rollback');
    foreach (array_column($state->baselines, 'base_storage_path') as $blobPath) {
        recoveryExpect(is_file($root . '/runtime/private/business-development/' . $blobPath), 'checkpoint 异常不得删除已引用 blob');
    }
    $recovered = (new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current))->recover($checkpointId);
    recoveryExpect($recovered['state'] === 'completed', 'checkpoint 失败必须可由下一实例 finalize');

    foreach (['first', 'middle', 'last'] as $name) {
        file_put_contents($root . '/managed/' . $name . '.txt', 'old-' . $name);
    }
    @unlink($root . '/managed/created.txt');
    $resources->state = ['old'];
    $crash = static function (string $point, int $index): void {
        if ($point === 'after_file_rename' && $index === 1) throw new GenerationInterruptionException('simulated crash');
    };
    $service = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current, $crash);
    recoveryReject(static fn () => $service->execute(1, 31, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle))), 'simulated crash');
    $stale = $service->stale();
    recoveryExpect(count($stale) === 1 && in_array($stale[0]['state'], ['writing', 'files_written'], true), '崩溃必须保留可恢复 WAL');
    $transactionId = $stale[0]['transaction_id'];
    $restarted = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current);
    $restarted->recover($transactionId);
    foreach (['first', 'middle', 'last'] as $name) recoveryExpect(file_get_contents($root . '/managed/' . $name . '.txt') === 'old-' . $name, '新实例必须无需外部 bundle，按同一状态仓储、hash 和 backup 恢复旧文件');
    recoveryExpect($restarted->inspect($transactionId)['state'] === 'rolled_back', '恢复后必须 checkpoint rolled_back');

    $crashLast = static function (string $point): void {
        if ($point === 'after_files_written') throw new GenerationInterruptionException('files written crash');
    };
    $service = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current, $crashLast);
    recoveryReject(static fn () => $service->execute(1, 32, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle))), 'files written crash');
    $transactionId = $service->stale()[0]['transaction_id'];
    $journal = $service->inspect($transactionId);
    $backup = $root . '/runtime/private/business-development/transactions/' . $transactionId . '/backup/managed/first.txt';
    unlink($backup);
    recoveryReject(static fn () => (new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current))->recover($transactionId), '人工恢复');
    recoveryExpect((new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current))->inspect($transactionId)['state'] === 'recovery_required', 'backup 丢失且不可证明时必须 recovery_required');
    recoveryReject(static fn () => (new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current))->recover($transactionId), '人工恢复');

    foreach (['first', 'middle', 'last'] as $name) {
        file_put_contents($root . '/managed/' . $name . '.txt', 'old-' . $name);
    }
    @unlink($root . '/managed/created.txt');
    $resources->state = ['old'];
    $service = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current, $committedCrash);
    recoveryReject(static fn () => $service->execute(1, 36, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle))), 'resource committed crash');
    $staleByGeneration = array_column($service->stale(), null, 'generation_id');
    $mismatchId = $staleByGeneration[36]['transaction_id'];
    file_put_contents($root . '/managed/created.txt', 'tampered');
    $mismatchRecovery = new GenerationTransactionService($root, $tokens, $blobs, $state, $resources, $current);
    recoveryReject(static fn () => $mismatchRecovery->recover($mismatchId), '需要人工恢复');
    recoveryExpect($mismatchRecovery->inspect($mismatchId)['state'] === 'recovery_required', '已提交 target hash 不一致时不得猜测或回滚');
    foreach (array_column($state->baselines, 'base_storage_path') as $blobPath) {
        recoveryExpect(is_file($root . '/runtime/private/business-development/' . $blobPath), 'target 不一致时不得删除已引用 blob');
    }

    $lock = fopen($root . '/runtime/cache/business-development-write.lock', 'c+');
    recoveryExpect($lock !== false && flock($lock, LOCK_EX | LOCK_NB), '测试必须持有统一写锁');
    recoveryReject(static fn () => $service->execute(1, 33, $bundle, $tokens->issue(GenerationTransactionService::bundleDigest($bundle))), '排他锁');
    flock($lock, LOCK_UN);
    fclose($lock);

    $command = (string) file_get_contents(dirname(__DIR__) . '/extend/fun/command/BusinessGenerationRecover.php');
    $console = (string) file_get_contents(dirname(__DIR__) . '/config/console.php');
    recoveryExpect(str_contains($command, 'all-stale') && str_contains($command, 'recovery_required'), '恢复命令必须支持 transaction/--all-stale 且拒绝自动猜测');
    recoveryExpect(substr_count($console, "'business:generation-recover'") === 1, '恢复命令必须且只能注册一次');
    recoveryExpect(!str_contains($command, 'app(GenerationTransactionService::class)') && str_contains($command, 'GenerationTransactionService::production('), '恢复命令必须显式调用可实例化 production factory');
    recoveryExpect(class_exists(DatabaseGenerationStateRepository::class), '必须提供数据库 generation state repository');
    recoveryExpect(class_exists(GenerationResourceTransaction::class), '必须提供真实资源事务适配器');

    $app = new App(dirname(__DIR__) . '/');
    $app->initialize();
    $production = GenerationTransactionService::production($root);
    recoveryExpect($production instanceof GenerationTransactionService, 'ThinkPHP 初始化后 production factory 必须可构造');
    recoveryExpect(is_array($production->stale()), 'production factory 的 stale 必须无需外部 bundle 即可使用');
    recoveryReject(static fn () => $production->inspect(str_repeat('0', 32)), '不存在');

    echo "Business generation recovery tests: PASS\n";
} finally {
    recoveryRemoveTree($root);
}
