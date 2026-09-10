<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

define('FUNADMIN_CRUD_HELPER_TESTING', true);

use app\common\crud\ConfirmationToken;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaMigrator;
use app\common\form\schema\FormSchemaValidator;
use app\console\service\FormCrudDefinitionFactory;
use app\console\service\GeneratedFileBaselineRepository;
use app\console\service\ManagedGenerationService;

final class IdempotencyGenerationStateRepository
{
    public array $generations = [];
    public array $events = [];
    public bool $allowClaim = true;
    private int $nextId = 100;

    public function loadBaselines(int $moduleId): array
    {
        return [];
    }

    public function createOrReuseGeneration(array $row, string $actor): array
    {
        foreach ($this->generations as $generation) {
            if (($generation['operation_key'] ?? null) === $row['operation_key']) {
                $this->events[] = ['reuse', $generation['id']];
                return $generation;
            }
        }
        $id = $this->nextId++;
        $created = $row + ['id' => $id];
        $this->generations[$id] = $created;
        if (($created['status'] ?? '') === 'planned') {
            foreach ($this->generations as $candidateId => &$candidate) {
                if ($candidateId === $id || ($candidate['business_module_id'] ?? null) !== $created['business_module_id']
                    || ($candidate['status'] ?? '') !== 'planned') {
                    continue;
                }
                $candidate['status'] = 'superseded';
                $candidate['superseded_by_id'] = $id;
                $this->events[] = ['superseded', $candidateId, $id];
            }
            unset($candidate);
        }
        $this->events[] = ['created', $id];
        return $created;
    }

    public function claimGeneration(int $moduleId, int $generationId, string $actor): bool
    {
        $this->events[] = ['claim', $generationId];
        if (!$this->allowClaim || ($this->generations[$generationId]['status'] ?? null) !== 'planned'
            || ($this->generations[$generationId]['business_module_id'] ?? null) !== $moduleId) {
            return false;
        }
        $this->generations[$generationId]['status'] = 'running';
        return true;
    }

    public function releaseGenerationClaim(int $moduleId, int $generationId, string $actor): void
    {
        $this->events[] = ['release', $generationId];
        if (($this->generations[$generationId]['business_module_id'] ?? null) === $moduleId
            && ($this->generations[$generationId]['status'] ?? null) === 'running') {
            $this->generations[$generationId]['status'] = 'planned';
        }
    }

    public function completedResult(int $generationId): ?array
    {
        $generation = $this->generations[$generationId] ?? null;
        return ($generation['status'] ?? null) === 'completed' ? ($generation['result'] ?? null) : null;
    }

    public function transaction(callable $operation): mixed
    {
        return $operation();
    }

    public function isGenerationCommitted(int $moduleId, int $generationId, string $transactionId, string $planDigest): bool
    {
        return false;
    }
}

function idempotencyExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function idempotencyReject(callable $operation, string $contains): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        idempotencyExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $contains);
}

function idempotencyRemoveTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function idempotencyFixture(string $root, IdempotencyGenerationStateRepository $state): array
{
    $schema = (new FormSchemaCompiler(new FormSchemaValidator()))->compile((new FormSchemaMigrator())->fromV1([
        'form_key' => 'idempotent_sample', 'name' => '幂等生成', 'table_name' => 'fun_idempotent_sample',
        'connection' => 'mysql', 'source_type' => 'created',
        'fields' => [[
            'field_name' => 'title', 'label' => '标题', 'type' => 'input', 'column_type' => 'varchar(255)',
            'nullable' => 0, 'list_show' => 1, 'form_show' => 1, 'relation_type' => 'none',
        ]],
    ]));
    $form = [
        'id' => 31, 'form_key' => 'idempotent_sample', 'name' => '幂等生成',
        'table_name' => 'fun_idempotent_sample', 'connection' => 'mysql',
        'source_type' => 'created', 'publish_config' => [],
    ];
    $tokens = new ConfirmationToken($root, 'idempotency-secret');
    $service = new ManagedGenerationService(
        $root,
        baselines: new GeneratedFileBaselineRepository($root, $state),
        stateRepository: $state,
        tokens: $tokens,
        moduleReader: static fn (int $id): array => ['id' => $id, 'form_id' => 31, 'code' => 'idempotent_sample'],
        formReader: static fn (int $id): array => $form,
        schemaReader: static fn (int $id) => $schema,
        generationReader: static fn (int $id): ?array => $state->generations[$id] ?? null
    );
    return [$service, $tokens, $schema, $form];
}

$root = sys_get_temp_dir() . '/funadmin-generation-idempotency-' . bin2hex(random_bytes(5));
mkdir($root, 0755, true);

try {
    $state = new IdempotencyGenerationStateRepository();
    [$service, $tokens, $schema, $form] = idempotencyFixture($root, $state);

    $first = $service->preview(7, true, 'stable-idempotency-nonce');
    $second = $service->preview(7, true, 'stable-idempotency-nonce');
    idempotencyExpect($first['generationId'] === $second['generationId'], '相同可信 planned preview 必须复用 generation');
    $firstRow = $state->generations[$first['generationId']];
    idempotencyExpect(preg_match('/^managed:[a-f0-9]{64}$/', (string) ($firstRow['operation_key'] ?? '')) === 1, '必须持久化服务端 operation_key');

    $changed = $service->preview(7, true, 'changed-idempotency-nonce');
    idempotencyExpect($changed['generationId'] !== $first['generationId'], '可信 bundle 内容变化必须创建新 generation');
    idempotencyExpect(($state->generations[$first['generationId']]['status'] ?? '') === 'superseded'
        && ($state->generations[$first['generationId']]['superseded_by_id'] ?? null) === $changed['generationId'],
        '新 planned generation 必须 supersede 同模块旧 planned');

    $conflictRoot = $root . '/conflict';
    mkdir($conflictRoot, 0755, true);
    $definition = (new FormCrudDefinitionFactory())->createFromSchema($schema, $form);
    $modelPath = (string) $definition->get('generationTargets')['model'];
    mkdir($conflictRoot . '/' . dirname($modelPath), 0755, true);
    file_put_contents($conflictRoot . '/' . $modelPath, "local edit\n");
    $conflictState = new IdempotencyGenerationStateRepository();
    [$conflictService] = idempotencyFixture($conflictRoot, $conflictState);
    $conflictA = $conflictService->preview(7, false, 'stable-conflict-nonce');
    $conflictB = $conflictService->preview(7, false, 'stable-conflict-nonce');
    idempotencyExpect(($conflictA['plan']['blocked'] ?? false) === true
        && $conflictA['generationId'] === $conflictB['generationId'], '相同 conflict preview 必须复用 generation');

    $completedId = 900;
    $persisted = [
        'generationId' => $completedId, 'routePath' => '/generated/idempotent-sample',
        'definitionHash' => str_repeat('a', 64), 'schemaHash' => str_repeat('b', 64),
        'transactionId' => str_repeat('c', 32), 'planDigest' => str_repeat('d', 64),
    ];
    $state->generations[$completedId] = [
        'id' => $completedId, 'business_module_id' => 7, 'status' => 'completed', 'result' => $persisted,
    ];
    $replayToken = $tokens->issue(str_repeat('e', 64));
    $replay = $service->execute(7, $completedId, $replayToken);
    idempotencyExpect($replay === $persisted + ['idempotentReplay' => true], 'completed execute 必须返回持久化结果并标记重放');
    $tokens->verify($replayToken, str_repeat('e', 64));

    $supersededId = 901;
    $state->generations[$supersededId] = [
        'id' => $supersededId, 'business_module_id' => 7, 'status' => 'superseded', 'superseded_by_id' => 902,
    ];
    idempotencyReject(static fn () => $service->execute(7, $supersededId, 'unused'), 'superseded');

    $plannedId = $changed['generationId'];
    $state->allowClaim = false;
    $plannedToken = (string) $changed['sensitive']['confirmToken'];
    idempotencyReject(static fn () => $service->execute(7, $plannedId, $plannedToken), '其他执行者');
    $manifest = $state->generations[$plannedId]['manifest'];
    $tokens->verify($plannedToken, (string) $manifest['bundleDigest']);
    idempotencyExpect(count(array_filter($state->events, static fn (array $event): bool => $event[0] === 'claim')) === 1,
        'execute 必须通过 planned CAS 竞争唯一执行权');

    $state->allowClaim = true;
    $claims = $tokens->verify($plannedToken, (string) $manifest['bundleDigest']);
    $tokens->consume((string) $claims['nonce'], (int) $claims['expiresAt']);
    idempotencyReject(static fn () => $service->execute(7, $plannedId, $plannedToken), '已使用');
    idempotencyExpect(($state->generations[$plannedId]['status'] ?? '') === 'planned',
        'token 校验或消费失败不得错误终结计划');

    echo "Business generation idempotency tests: PASS\n";
} finally {
    idempotencyRemoveTree($root);
}
