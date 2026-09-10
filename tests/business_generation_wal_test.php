<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

define('FUNADMIN_CRUD_HELPER_TESTING', true);

use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\GenerationPlanner;
use app\console\service\GeneratedFileBaselineRepository;
use app\console\service\GenerationTransactionService;
use app\console\service\ManagedGenerationService;
use app\console\service\FormCrudDefinitionFactory;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaMigrator;
use app\common\form\schema\FormSchemaValidator;

final class MemoryGenerationStateRepository
{
    public array $baselines = [];
    public array $generations = [];
    public array $modules = [];
    public bool $failCommit = false;
    private array $snapshot = [];

    public function transaction(callable $operation): mixed
    {
        $this->snapshot = [$this->baselines, $this->generations, $this->modules];
        try {
            $result = $operation();
            if ($this->failCommit) {
                throw new RuntimeException('db commit failed');
            }
            return $result;
        } catch (Throwable $exception) {
            [$this->baselines, $this->generations, $this->modules] = $this->snapshot;
            throw $exception;
        }
    }

    public function loadBaselines(int $moduleId): array
    {
        return array_values(array_filter($this->baselines, static fn (array $row): bool => $row['business_module_id'] === $moduleId));
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
        $this->modules[$moduleId] = ['current_generation_id' => $generationId, 'last_success_generation_id' => $generationId, 'generation_status' => 'generated'];
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

    public function adoptResolvedBaseline(int $moduleId, int $generationId, array $record): array
    {
        $saved = $record + ['business_module_id' => $moduleId, 'generation_id' => $generationId, 'status' => 'active'];
        $this->baselines[] = $saved;
        return $saved;
    }
}

final class MemoryGenerationResources
{
    public array $state = [];
    private array $before = [];
    public bool $failApply = false;

    public function begin(): void
    {
        $this->before = $this->state;
    }

    public function apply(array $resources): void
    {
        if ($this->failApply) {
            throw new RuntimeException('resource apply failed');
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

function generationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function generationReject(callable $operation, string $contains): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        generationExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $contains);
}

function generationRemoveTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function generationDefinition(): CrudDefinition
{
    return CrudDefinition::fromArray([
        'schemaVersion' => '1.0', 'entity' => 'sample', 'table' => 'fun_sample', 'title' => '示例',
        'apiPrefix' => '/sample', 'permissionPrefix' => 'sample:item',
        'fields' => [['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true]],
        'relations' => [], 'optionsSource' => [],
        'features' => ['batchDelete' => false, 'status' => false, 'detail' => false, 'import' => false, 'export' => false, 'upload' => false, 'dictionary' => false, 'referenceProtection' => false, 'formMode' => 'dialog', 'importLimit' => 100, 'exportLimit' => 100],
        'dataScope' => ['enabled' => false, 'field' => ''],
        'generationTargets' => ['model' => 'managed/A.txt', 'service' => 'managed/B.txt', 'api' => 'managed/blob.bin'],
        'templates' => ['model' => 'unused', 'service' => 'unused', 'api' => 'unused'],
        'formSchemaHash' => str_repeat('1', 64),
    ]);
}

$root = sys_get_temp_dir() . '/funadmin-generation-wal-' . bin2hex(random_bytes(5));
mkdir($root . '/managed', 0755, true);
file_put_contents($root . '/managed/A.txt', "base-a\n");
file_put_contents($root . '/managed/B.txt', "base-b\n");

try {
    $state = new MemoryGenerationStateRepository();
    $resources = new MemoryGenerationResources();
    $blobRepository = new GeneratedFileBaselineRepository($root, $state);
    $prepared = $blobRepository->prepare("remote\0blob");
    generationExpect($prepared['hash'] === hash('sha256', "remote\0blob"), 'blob 必须按内容 hash 寻址');
    generationExpect(!str_starts_with($prepared['path'], $root), 'DB/prepare 只能暴露相对 storage path');
    generationExpect((fileperms($root . '/runtime/private/business-development') & 0777) === 0700, '私有根目录必须为 0700');
    generationExpect((fileperms($root . '/runtime/private/business-development/' . $prepared['path']) & 0777) === 0600, 'blob 必须为 0600');
    generationExpect($blobRepository->load($prepared['path'], $prepared['hash']) === "remote\0blob", 'load 必须验证并读取 blob');
    $blobRepository->rollback([$prepared]);
    generationExpect(!is_file($root . '/runtime/private/business-development/' . $prepared['path']), '未提交 blob 必须可回滚');
    generationReject(static fn () => $blobRepository->load('../escape', str_repeat('0', 64)), '路径');

    $definition = generationDefinition();
    $remote = ['managed/A.txt' => "remote-a\n", 'managed/B.txt' => "remote-b\n", 'managed/blob.bin' => "remote\0blob"];
    $planner = new GenerationPlanner($root, new ConfirmationToken($root, 'generation-plan-secret'));
    $plan = $planner->planManaged($definition, $remote, [
        ['path' => 'managed/A.txt', 'baseContent' => "base-a\n"],
        ['path' => 'managed/B.txt', 'baseContent' => "base-b\n"],
        ['path' => 'managed/blob.bin', 'contentKind' => 'binary'],
    ]);
    generationExpect($plan['blocked'] === false && !isset($plan['confirmToken']), 'managed public plan 必须无冲突且不携带 token');
    $tokens = new ConfirmationToken($root, 'generation-execute-secret');
    $bundle = [
        'plan' => $plan,
        'remoteContents' => $remote,
        'mergedTextContents' => ['managed/A.txt' => "remote-a\n", 'managed/B.txt' => "remote-b\n"],
        'definitionHash' => $definition->hash(),
        'schemaHash' => str_repeat('1', 64),
        'registryHash' => str_repeat('2', 64),
        'templateVersion' => 'm5-production-v1',
        'migrationHash' => str_repeat('3', 64),
        'resourcesHash' => hash('sha256', CrudDefinition::canonicalJson([['resourceKey' => 'menu|sample|/sample']])),
        'resources' => [['resourceKey' => 'menu|sample|/sample']],
    ];
    $executionDigest = GenerationTransactionService::bundleDigest($bundle);
    $current = static fn (): array => [
        'definitionHash' => $definition->hash(), 'schemaHash' => str_repeat('1', 64),
        'registryHash' => str_repeat('2', 64), 'templateVersion' => 'm5-production-v1',
        'migrationHash' => str_repeat('3', 64), 'resourcesHash' => $bundle['resourcesHash'],
    ];
    $service = new GenerationTransactionService($root, $tokens, $blobRepository, $state, $resources, $current);
    $retryableToken = $tokens->issue($executionDigest);
    file_put_contents($root . '/managed/A.txt', "drift-a\n");
    generationReject(static fn () => $service->execute(7, 10, $bundle, $retryableToken), 'hash 已变化');
    file_put_contents($root . '/managed/A.txt', "base-a\n");
    $result = $service->execute(7, 11, $bundle, $retryableToken);
    generationExpect($result['state'] === 'completed' && file_get_contents($root . '/managed/A.txt') === "remote-a\n", '成功事务必须写文件并完成');
    generationExpect(file_get_contents($root . '/managed/blob.bin') === "remote\0blob", '二进制必须从 trusted remoteContents 写入');
    generationExpect($state->baselines[0]['base_hash'] !== '', '成功后必须提交 Remote baseline');
    foreach ($state->baselines as $baseline) {
        generationExpect($baseline['base_hash'] === $baseline['target_hash'], 'Base 必须是 Remote hash，不得使用 merged target hash');
        generationExpect($baseline['artifact_type'] !== '', 'baseline artifact_type 必须从 managed plan 保留');
        generationExpect(!str_starts_with($baseline['base_storage_path'], $root), 'baseline DB 记录不得包含绝对路径');
    }
    $journalFile = $root . '/runtime/private/business-development/wal/' . $result['transactionId'] . '.json';
    $journalJson = (string) file_get_contents($journalFile);
    $journal = json_decode($journalJson, true, 512, JSON_THROW_ON_ERROR);
    generationExpect($journal['state'] === 'completed', 'WAL 最终阶段必须为 completed');
    foreach ($journal['files'] as $journalFile) {
        generationExpect(trim((string) ($journalFile['artifact_type'] ?? '')) !== '', 'WAL 必须保留 artifact_type');
    }
    foreach (['prepared', 'staged', 'writing', 'files_written', 'resources_applying', 'resources_applied', 'baselines_committing', 'completed'] as $stage) {
        generationExpect(in_array($stage, $journal['history'], true), 'WAL 缺少阶段 checkpoint：' . $stage);
    }
    generationExpect(!str_contains($journalJson, 'generation-execute-secret') && !str_contains($journalJson, "remote\0blob") && !str_contains($journalJson, $root), 'WAL 不得包含密钥、二进制或项目绝对路径');
    generationReject(static fn () => $service->execute(7, 12, $bundle, $retryableToken), '已使用');

    $conflict = $bundle;
    $conflict['plan']['blocked'] = true;
    $before = file_get_contents($root . '/managed/A.txt');
    generationReject(static fn () => $service->execute(7, 13, $conflict, $tokens->issue(GenerationTransactionService::bundleDigest($conflict))), '冲突');
    generationExpect(file_get_contents($root . '/managed/A.txt') === $before, '冲突计划必须零文件副作用');

    $managedRoot = $root . '/managed-production';
    mkdir($managedRoot, 0755, true);
    $published = (new FormSchemaCompiler(new FormSchemaValidator()))->compile((new FormSchemaMigrator())->fromV1([
        'form_key' => 'managed_sample', 'name' => '正式生成', 'table_name' => 'fun_managed_sample',
        'connection' => 'mysql', 'source_type' => 'created',
        'fields' => [[
            'field_name' => 'title', 'label' => '标题', 'type' => 'input', 'column_type' => 'varchar(255)',
            'nullable' => 0, 'list_show' => 1, 'form_show' => 1, 'relation_type' => 'none',
        ]],
    ]));
    $publishedForm = [
        'id' => 31, 'form_key' => 'managed_sample', 'name' => '正式生成', 'table_name' => 'fun_managed_sample',
        'connection' => 'mysql', 'source_type' => 'created', 'publish_config' => [],
    ];
    $managedDefinition = (new FormCrudDefinitionFactory())->createFromSchema($published, $publishedForm);
    $modelPath = (string) $managedDefinition->get('generationTargets')['model'];
    mkdir($managedRoot . '/' . dirname($modelPath), 0755, true);
    file_put_contents($managedRoot . '/' . $modelPath, "parallel local edit\n");
    $managedState = new MemoryGenerationStateRepository();
    $managedResources = new MemoryGenerationResources();
    $managedBaselines = new GeneratedFileBaselineRepository($managedRoot, $managedState);
    $managedGenerations = [];
    $managed = new ManagedGenerationService(
        $managedRoot,
        baselines: $managedBaselines,
        stateRepository: $managedState,
        resources: $managedResources,
        tokens: new ConfirmationToken($managedRoot, 'managed-production-secret'),
        moduleReader: static fn (int $id): array => ['id' => $id, 'form_id' => 31, 'code' => 'managed_sample'],
        formReader: static fn (int $id): array => $publishedForm,
        schemaReader: static fn (int $id) => $published,
        generationWriter: static function (array $row) use (&$managedGenerations): int {
            $id = count($managedGenerations) + 100;
            $managedGenerations[$id] = $row + ['id' => $id];
            return $id;
        },
        generationReader: static function (int $id) use (&$managedGenerations): ?array {
            return $managedGenerations[$id] ?? null;
        }
    );
    $blockedPreview = $managed->preview(7, true, 'stable-preview-nonce');
    generationExpect(($blockedPreview['plan']['blocked'] ?? false) === true, 'existing no-base 必须阻断正式 managed 生成');
    generationExpect(($blockedPreview['generationId'] ?? 0) === 100 && ($managedGenerations[100]['status'] ?? '') === 'conflict', '冲突必须保存无敏感内容的审计以支持严格采纳');
    generationExpect(!isset($blockedPreview['sensitive']) && !isset($managedGenerations[100]['manifest']['plan']['files'][0]['remoteContent']), '冲突不得签发 token 或持久化敏感内容');
    generationExpect(file_get_contents($managedRoot . '/' . $modelPath) === "parallel local edit\n", '正式生成冲突必须零文件副作用');

    $resolvedPath = 'resolved/Model.php';
    mkdir($managedRoot . '/resolved', 0755, true);
    $resolvedContent = "resolved remote\n";
    file_put_contents($managedRoot . '/' . $resolvedPath, $resolvedContent);
    $resolvedHash = hash('sha256', $resolvedContent);
    $conflictRecords = [
        200 => [
            'id' => 200, 'business_module_id' => 7, 'status' => 'conflict', 'definition_hash' => str_repeat('a', 64),
            'manifest' => ['hashes' => ['templateVersion' => 'test-v1'], 'plan' => ['files' => [[
                'path' => $resolvedPath, 'status' => 'conflict-no-base', 'remoteHash' => $resolvedHash,
                'artifactType' => 'model', 'contentKind' => 'text',
            ]]]],
        ],
        201 => ['id' => 201, 'business_module_id' => 7, 'status' => 'planned', 'manifest' => []],
    ];
    $latestConflict = 200;
    $adoptionState = new MemoryGenerationStateRepository();
    $adoptionService = new ManagedGenerationService(
        $managedRoot,
        baselines: new GeneratedFileBaselineRepository($managedRoot, $adoptionState),
        stateRepository: $adoptionState,
        tokens: new ConfirmationToken($managedRoot, 'adoption-secret'),
        generationReader: static fn (int $id): ?array => $conflictRecords[$id] ?? null,
        latestConflictReader: static function (int $moduleId) use (&$latestConflict): ?int {
            return $latestConflict;
        }
    );
    generationReject(static fn () => $adoptionService->adoptResolvedBaseline(7, 201, $resolvedPath, $resolvedHash, $resolvedHash, 'tester'), '最近冲突');
    $latestConflict = 199;
    generationReject(static fn () => $adoptionService->adoptResolvedBaseline(7, 200, $resolvedPath, $resolvedHash, $resolvedHash, 'tester'), '最近冲突');
    $latestConflict = 200;
    generationReject(static fn () => $adoptionService->adoptResolvedBaseline(7, 200, $resolvedPath, str_repeat('b', 64), $resolvedHash, 'tester'), '当前 Local hash');
    generationReject(static fn () => $adoptionService->adoptResolvedBaseline(7, 200, $resolvedPath, $resolvedHash, str_repeat('c', 64), 'tester'), 'Remote hash 不匹配');
    $adopted = $adoptionService->adoptResolvedBaseline(7, 200, $resolvedPath, $resolvedHash, $resolvedHash, 'tester');
    generationExpect(($adopted['base_hash'] ?? '') === $resolvedHash && ($adopted['target_hash'] ?? '') === $resolvedHash, '采纳成功必须把计划 Remote 保存为 baseline');

    unlink($managedRoot . '/' . $modelPath);
    $managedPreview = $managed->preview(7, true, 'stable-preview-nonce');
    generationExpect(($managedPreview['plan']['managed'] ?? false) === true && ($managedPreview['generationId'] ?? 0) === 101, '正式预览必须创建绑定 module/form 的 planned generation');
    $plannedDefinition = (array) ($managedGenerations[101]['definition'] ?? []);
    $plannedDependencies = (array) (($plannedDefinition['formSchema']['extensions']['dependencies'] ?? []));
    generationExpect(
        preg_match('/^[a-f0-9]{64}$/', (string) ($plannedDependencies['hash'] ?? '')) === 1,
        'managed Definition 必须绑定实时 registry/dependency hash'
    );
    generationExpect(isset($managedPreview['sensitive']['confirmToken']), '仅正式 preview sensitive 必须返回执行 token');
    $migrationPaths = array_values(array_filter(
        array_column($managedPreview['plan']['files'], 'path'),
        static fn (string $path): bool => str_starts_with($path, 'database/generated/')
            && str_ends_with($path, '.sql')
    ));
    generationExpect(count($migrationPaths) >= 1 && str_contains($migrationPaths[0], 'stable-preview-nonce'), 'migration 必须使用 preview/execute 稳定且唯一的不可变路径');
    $managedResult = $managed->execute(7, 101, (string) $managedPreview['sensitive']['confirmToken']);
    generationExpect(($managedResult['state'] ?? '') === 'completed', '正式 managed execute 必须进入 GenerationTransactionService');
    generationExpect($managedState->baselines !== [] && glob($managedRoot . '/runtime/private/business-development/wal/*.json') !== [], '正式成功必须产生 WAL 与 baseline');

    echo "Business generation WAL tests: PASS\n";
} finally {
    generationRemoveTree($root);
}
