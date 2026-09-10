<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

define('FUNADMIN_CRUD_HELPER_TESTING', true);

use app\common\crud\ConfirmationToken;
use app\console\service\BusinessOperationException;
use app\console\service\GeneratedFileBaselineRepository;
use app\console\service\GenerationTransactionService;
final class ApiRecoveryState
{
    public array $record = [
        'id' => 41,
        'business_module_id' => 7,
        'status' => 'failed',
        'recovery_status' => 'recovery_required',
        'transaction_id' => null,
    ];
    public array $transitions = [];
    public bool $committed = false;

    public function loadBaselines(int $moduleId): array { return []; }
    public function transaction(callable $operation): mixed { return $operation(); }
    public function isGenerationCommitted(int $moduleId, int $generationId, string $transactionId, string $planDigest): bool { return $this->committed; }
    public function generationForRecovery(int $id): ?array { return $id === 41 ? $this->record : null; }
    public function bindGenerationTransaction(int $moduleId, int $generationId, string $transactionId): void
    {
        if ($moduleId !== 7 || $generationId !== 41 || $this->record['transaction_id'] !== null) throw new RuntimeException('binding conflict');
        $this->record['transaction_id'] = $transactionId;
    }
    public function claimRecovery(int $id, string $expected, string $actor): bool
    {
        if ($id !== 41 || $this->record['recovery_status'] !== $expected) return false;
        $this->record['status'] = 'running';
        $this->record['recovery_status'] = 'recovering';
        $this->transitions[] = 'recovering';
        return true;
    }
    public function markRolledBack(int $id, string $actor): void
    {
        $this->record['status'] = 'failed';
        $this->record['recovery_status'] = 'rolled_back';
        $this->transitions[] = 'rolled_back';
    }
    public function markRecoveredCompleted(int $id, string $actor): void
    {
        $this->record['status'] = 'completed';
        $this->record['recovery_status'] = 'recovered_completed';
        $this->transitions[] = 'recovered_completed';
    }
}

final class ApiRecoveryResources
{
    public function rollback(): void {}
}

function apiRecoveryExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function apiRecoveryReject(callable $operation, string $code): void
{
    try {
        $operation();
    } catch (BusinessOperationException $exception) {
        apiRecoveryExpect($exception->errorCode() === $code, '错误码不匹配：' . $exception->errorCode());
        return;
    }
    throw new RuntimeException('预期错误：' . $code);
}

function apiRecoveryRemoveTree(string $path): void
{
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($path);
}

$root = sys_get_temp_dir() . '/funadmin-api-recovery-' . bin2hex(random_bytes(5));
mkdir($root, 0755, true);
try {
    $state = new ApiRecoveryState();
    $tokens = new ConfirmationToken($root, 'api-recovery-secret');
    $transactions = new GenerationTransactionService(
        $root,
        $tokens,
        new GeneratedFileBaselineRepository($root, $state),
        $state,
        new ApiRecoveryResources(),
        static fn (): array => []
    );

    $journalFactory = new ReflectionMethod($transactions, 'writeJournal');
    $journalFactory->setAccessible(true);
    $transactionId = str_repeat('a', 32);
    $journalFactory->invoke($transactions, [
        'schema_version' => 1,
        'transaction_id' => $transactionId,
        'module_id' => 7,
        'generation_id' => 41,
        'state' => 'prepared',
        'history' => ['prepared'],
        'bundle_digest' => str_repeat('b', 64),
        'plan_digest' => str_repeat('c', 64),
        'files' => [],
        'applied' => [],
        'prepared_blobs' => [],
        'updated_at' => gmdate(DATE_ATOM),
    ]);
    $state->bindGenerationTransaction(7, 41, $transactionId);

    $recover = new ReflectionMethod(GenerationTransactionService::class, 'recoverGeneration');
    apiRecoveryExpect($recover->isPublic(), 'recoverGeneration 必须是公开编排入口');
    $result = $transactions->recoverGeneration(41, 'recovery_required', 'tester');
    apiRecoveryExpect($result['state'] === 'rolled_back', 'prepared WAL 应确定性回滚');
    apiRecoveryExpect($state->transitions === ['recovering', 'rolled_back'], '恢复状态必须 CAS recovering 后同步 rolled_back');

    $state->record['status'] = 'failed';
    $state->record['recovery_status'] = 'recovery_required';
    $state->record['transaction_id'] = $transactionId;
    $state->transitions = [];
    $state->committed = true;
    $journalFactory->invoke($transactions, [
        'schema_version' => 1,
        'transaction_id' => $transactionId,
        'module_id' => 7,
        'generation_id' => 41,
        'state' => 'files_written',
        'history' => ['prepared', 'files_written'],
        'bundle_digest' => str_repeat('b', 64),
        'plan_digest' => str_repeat('c', 64),
        'files' => [],
        'applied' => [],
        'prepared_blobs' => [],
        'updated_at' => gmdate(DATE_ATOM),
    ]);
    $completed = $transactions->recoverGeneration(41, 'recovery_required', 'tester');
    apiRecoveryExpect($completed['state'] === 'completed', '已提交 WAL 必须收敛 completed');
    apiRecoveryExpect($state->transitions === ['recovering', 'recovered_completed'], '恢复完成必须同步 completed/recovered_completed');

    $state->record['status'] = 'failed';
    $state->record['recovery_status'] = 'recovery_required';
    $state->committed = false;
    apiRecoveryReject(static fn () => $transactions->recoverGeneration(41, 'none', 'tester'), 'GENERATION_RECOVERY_STATUS_CONFLICT');

    $state->record['transaction_id'] = str_repeat('d', 32);
    apiRecoveryReject(static fn () => $transactions->recoverGeneration(41, 'recovery_required', 'tester'), 'GENERATION_BINDING_CONFLICT');

    echo "business generation API recovery tests: PASS\n";
} finally {
    apiRecoveryRemoveTree($root);
}
