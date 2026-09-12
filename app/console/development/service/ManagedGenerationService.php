<?php

declare(strict_types=1);

namespace app\console\development\service;

use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\crud\PathGuard;
use app\common\form\schema\FormSchema;
use app\console\development\exception\BusinessOperationException;
use app\console\development\model\BusinessModule;
use app\console\development\model\CrudGeneration;
use app\console\development\repository\DatabaseGenerationStateRepository;
use app\console\development\repository\GeneratedFileBaselineRepository;
use app\console\form\model\Form;
use app\console\form\repository\FormSchemaRepository;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** 为 business-managed 正式发布构造可信 bundle 并执行 WAL 事务。 */
final class ManagedGenerationService
{
    /** @var Closure(int): array<string, mixed> */
    private readonly Closure $moduleReader;

    /** @var Closure(int): array<string, mixed> */
    private readonly Closure $formReader;

    /** @var Closure(int): FormSchema */
    private readonly Closure $schemaReader;

    /** @var Closure(array<string, mixed>): int */
    private readonly Closure $generationWriter;

    /** @var Closure(int): ?array<string, mixed> */
    private readonly Closure $generationReader;

    /** @var Closure(int): ?int */
    private readonly Closure $latestConflictReader;

    private readonly GeneratedFileBaselineRepository $baselines;
    private readonly mixed $stateRepository;
    private readonly mixed $resources;
    private readonly ConfirmationToken $tokens;
    private readonly FormCrudDefinitionFactory $definitions;
    private readonly FormSchemaRepository $schemas;

    public function __construct(
        private readonly string $projectRoot,
        ?GeneratedFileBaselineRepository $baselines = null,
        mixed $stateRepository = null,
        mixed $resources = null,
        ?ConfirmationToken $tokens = null,
        ?FormCrudDefinitionFactory $definitions = null,
        ?FormSchemaRepository $schemas = null,
        ?callable $moduleReader = null,
        ?callable $formReader = null,
        ?callable $schemaReader = null,
        ?callable $generationWriter = null,
        ?callable $generationReader = null,
        ?callable $latestConflictReader = null
    ) {
        $this->stateRepository = $stateRepository ?? new DatabaseGenerationStateRepository();
        $this->baselines = $baselines ?? new GeneratedFileBaselineRepository($projectRoot, $this->stateRepository);
        $this->resources = $resources ?? new GenerationResourceTransaction();
        $this->tokens = $tokens ?? new ConfirmationToken($projectRoot);
        $this->definitions = $definitions ?? new FormCrudDefinitionFactory();
        $this->schemas = $schemas ?? new FormSchemaRepository();
        $this->moduleReader = Closure::fromCallable($moduleReader ?? static function (int $id): array {
            $module = BusinessModule::find($id);
            if (!$module) throw new InvalidArgumentException('业务模块不存在');
            return $module->toArray();
        });
        $this->formReader = Closure::fromCallable($formReader ?? static function (int $id): array {
            $form = Form::find($id);
            if (!$form) throw new InvalidArgumentException('表单不存在');
            return $form->toArray();
        });
        $this->schemaReader = Closure::fromCallable($schemaReader ?? fn (int $id): FormSchema => $this->schemas->published($id));
        $this->generationWriter = Closure::fromCallable($generationWriter ?? static function (array $row): int {
            $generation = CrudGeneration::create($row);
            return (int) $generation->id;
        });
        $this->generationReader = Closure::fromCallable($generationReader ?? static function (int $id): ?array {
            $generation = CrudGeneration::find($id);
            return $generation ? $generation->toArray() : null;
        });
        $this->latestConflictReader = Closure::fromCallable($latestConflictReader ?? static function (int $moduleId): ?int {
            $id = CrudGeneration::where('business_module_id', $moduleId)
                ->where('status', 'conflict')
                ->order('id', 'desc')
                ->value('id');
            return $id === null ? null : (int) $id;
        });
    }

    /** 只返回公开计划；确认 token 仅在有权限且计划可执行时返回。 */
    public function preview(int $moduleId, bool $includeSensitive, ?string $nonce = null): array
    {
        $nonce = $this->nonce($nonce);
        return $this->previewBundle($moduleId, $this->buildBundle($moduleId, $nonce), $includeSensitive, $nonce);
    }

    /** 由服务端校验结构化提案并保存可信 Definition，绝不接受模型生成的 bundle 或路径。 */
    public function previewProposal(
        int $moduleId,
        array $document,
        string $proposalType,
        bool $includeSensitive,
        ?string $nonce = null
    ): array {
        $nonce = $this->nonce($nonce);
        $module = ($this->moduleReader)($moduleId);
        $formId = (int) ($module['form_id'] ?? 0);
        if ($moduleId < 1 || $formId < 1) throw new InvalidArgumentException('业务模块未绑定表单');
        $form = ($this->formReader)($formId);
        if ($proposalType === 'form_schema') {
            $schema = $this->schemas->compile($document);
            $definition = $this->definitions->createFromSchema($schema, $form, (array) ($form['publish_config'] ?? []));
            $definition = $this->withDependencyHash($definition, $this->registryHash($schema));
        } elseif ($proposalType === 'crud_definition') {
            $definition = CrudDefinition::fromArray($document);
        } else {
            throw new InvalidArgumentException('提案类型必须为 FormSchema 或 CrudDefinition');
        }
        $this->assertProposalIdentity($module, $definition);
        $definition = $this->withManagedMigrationPaths($definition, $nonce);
        $schemaHash = $proposalType === 'form_schema' ? $schema->hash() : (string) $definition->get('formSchemaHash', '');
        $registryHash = $proposalType === 'form_schema' ? $this->registryHash($schema) : '';
        $bundle = $this->buildDefinitionBundle($moduleId, $definition, $schemaHash, $registryHash);
        $bundle['proposal'] = [
            'type' => $proposalType,
            'digest' => hash('sha256', CrudDefinition::canonicalJson($document)),
        ];
        return $this->previewBundle($moduleId, $bundle, $includeSensitive, $nonce);
    }

    private function previewBundle(int $moduleId, array $bundle, bool $includeSensitive, string $nonce): array
    {
        $publicPlan = $this->publicPlan($bundle['plan']);
        $module = ($this->moduleReader)($moduleId);
        $formId = (int) ($module['form_id'] ?? 0);
        $blocked = ($publicPlan['blocked'] ?? true) === true;
        $manifest = [
            'managedNonce' => $nonce,
            'planDigest' => (string) $bundle['plan']['planDigest'],
            'bundleDigest' => GenerationTransactionService::bundleDigest($this->trustedBundle($bundle)),
            'hashes' => $this->stateHashes($bundle),
        ];
        if (isset($bundle['proposal'])) $manifest['proposal'] = $bundle['proposal'];
        if ($blocked) {
            $manifest['plan'] = $publicPlan;
        }
        $row = [
            'business_module_id' => $moduleId,
            'form_id' => $formId,
            'binding_status' => 'bound',
            'generation_mode' => 'managed',
            'operation' => $blocked ? 'preview' : 'generate',
            'operation_key' => $this->operationKey($moduleId, $bundle),
            'status' => $blocked ? 'conflict' : 'planned',
            'connection_name' => (string) $bundle['definition']->get('connection', ''),
            'table_name' => (string) $bundle['definition']->get('table', ''),
            'definition_hash' => $bundle['definitionHash'],
            'definition' => $blocked ? null : $bundle['definition']->toArray(),
            'manifest' => $manifest,
            'error' => $blocked ? ['code' => 'MANAGED_PLAN_CONFLICT'] : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $generation = $this->createOrReuseGeneration($row);
        $generationId = (int) $generation['id'];
        if ($blocked) {
            return [
                'generationId' => $generationId,
                'plan' => $publicPlan,
                'conflicts' => $this->conflicts($includeSensitive ? $bundle['plan'] : $publicPlan, $includeSensitive),
            ];
        }
        $result = [
            'generationId' => $generationId,
            'definitionHash' => $bundle['definitionHash'],
            'schemaHash' => $bundle['schemaHash'],
            'routePath' => (string) $bundle['definition']->get('routePath'),
            'plan' => $publicPlan,
            'conflicts' => [],
        ];
        if ($includeSensitive) {
            $result['sensitive'] = ['confirmToken' => $this->tokens->issue(GenerationTransactionService::bundleDigest($this->trustedBundle($bundle)))];
        }
        return $result;
    }

    /** 从服务端数据重建 bundle，核对 planned 审计后才进入 WAL 事务。 */
    public function execute(int $moduleId, int $generationId, string $confirmToken): array
    {
        $record = ($this->generationReader)($generationId);
        if (!is_array($record) || (int) ($record['business_module_id'] ?? 0) !== $moduleId) {
            throw new InvalidArgumentException('managed generation 不存在、未绑定或不可执行');
        }
        $status = (string) ($record['status'] ?? '');
        if ($status === 'completed') {
            $result = $this->completedResult($generationId);
            if ($result === null) {
                throw new RuntimeException('completed managed generation 缺少持久化结果');
            }
            return $result + ['idempotentReplay' => true];
        }
        if ($status === 'superseded') {
            throw new BusinessOperationException('GENERATION_SUPERSEDED', [], 'managed generation 已 superseded，不可执行');
        }
        if ($status === 'running') {
            throw new BusinessOperationException('GENERATION_IN_PROGRESS', [], 'managed generation 已由其他执行者获取');
        }
        if (($record['recovery_status'] ?? 'none') === 'recovery_required') {
            throw new BusinessOperationException('GENERATION_RECOVERY_REQUIRED', [], 'managed generation 需要人工恢复');
        }
        if ($status !== 'planned') {
            throw new InvalidArgumentException('managed generation 不存在、未绑定或不可执行');
        }
        $manifest = (array) ($record['manifest'] ?? []);
        $nonce = (string) ($manifest['managedNonce'] ?? '');
        $bundle = $this->rebuildRecordedBundle($moduleId, $record, $manifest, $nonce);
        $trusted = $this->trustedBundle($bundle);
        if (!hash_equals((string) ($manifest['planDigest'] ?? ''), (string) $trusted['plan']['planDigest'])
            || !hash_equals((string) ($manifest['bundleDigest'] ?? ''), GenerationTransactionService::bundleDigest($trusted))) {
            throw new BusinessOperationException('GENERATION_PLAN_CONFLICT', [], 'managed generation 计划已漂移');
        }
        $transaction = new GenerationTransactionService(
            $this->projectRoot,
            $this->tokens,
            $this->baselines,
            $this->stateRepository,
            $this->resources,
            fn (): array => $this->stateHashes($this->rebuildRecordedBundle($moduleId, $record, $manifest, $nonce))
        );
        try {
            $execution = $transaction->execute(
                $moduleId,
                $generationId,
                $trusted,
                $confirmToken,
                fn (): bool => $this->claimGeneration($moduleId, $generationId)
            );
        } catch (Throwable $exception) {
            $completed = $this->completedResult($generationId);
            if ($completed !== null) {
                return $completed + ['idempotentReplay' => false];
            }
            $this->recordExecutionFailure($generationId, $transaction->executionOutcome(), $exception);
            throw $exception;
        }
        $completed = $this->completedResult($generationId);
        if ($completed !== null) {
            return $completed + ['idempotentReplay' => false];
        }
        return $execution + [
            'generationId' => $generationId,
            'resourceApplyStatus' => 'applied',
            'resourceApplyError' => null,
            'routePath' => (string) $bundle['definition']->get('routePath'),
            'definitionHash' => $bundle['definitionHash'],
            'schemaHash' => $bundle['schemaHash'],
            'idempotentReplay' => false,
        ];
    }

    /** 返回已持久化审计，不暴露确认 token 或可信内容 bundle。 */
    public function generation(int $generationId): ?array
    {
        $record = ($this->generationReader)($generationId);
        return is_array($record) ? $record : null;
    }

    /** 仅采纳最近一次 conflict-no-base 计划中已由操作者解析为 Remote 的本地文件。 */
    public function adoptResolvedBaseline(
        int $moduleId,
        int $generationId,
        string $path,
        string $localHash,
        string $remoteHash,
        string $actor
    ): array {
        if (!method_exists($this->stateRepository, 'adoptResolvedBaseline')) {
            throw new RuntimeException('生成 baseline 数据仓储不支持采纳');
        }
        $record = ($this->generationReader)($generationId);
        if (!is_array($record) || (int) ($record['business_module_id'] ?? 0) !== $moduleId
            || (string) ($record['status'] ?? '') !== 'conflict') {
            throw new InvalidArgumentException('仅可采纳该模块最近冲突计划');
        }
        $latest = ($this->latestConflictReader)($moduleId);
        if ($latest !== $generationId) throw new InvalidArgumentException('仅可采纳最近冲突计划');
        $manifest = (array) ($record['manifest'] ?? []);
        $files = (array) (($manifest['plan']['files'] ?? null) ?? ($manifest['files'] ?? []));
        $planned = null;
        foreach ($files as $file) {
            if ((string) ($file['path'] ?? '') === $path && (string) ($file['status'] ?? '') === 'conflict-no-base') {
                $planned = $file;
                break;
            }
        }
        if (!is_array($planned) || !hash_equals((string) ($planned['remoteHash'] ?? ''), $remoteHash)) {
            throw new InvalidArgumentException('路径不是该计划的 conflict-no-base 或 Remote hash 不匹配');
        }
        $absolute = PathGuard::resolve($this->projectRoot, $path, '冲突文件');
        $stat = @lstat($absolute);
        if ($stat === false || is_link($absolute) || (($stat['mode'] & 0170000) !== 0100000)) {
            throw new InvalidArgumentException('当前 Local 必须为项目内普通文件');
        }
        $actualLocalHash = hash_file('sha256', $absolute);
        if (!is_string($actualLocalHash) || !hash_equals($actualLocalHash, $localHash) || !hash_equals($localHash, $remoteHash)) {
            throw new InvalidArgumentException('当前 Local hash 必须等于该计划 Remote hash');
        }
        $content = file_get_contents($absolute);
        if (!is_string($content)) throw new RuntimeException('无法读取已解析文件');
        $blob = $this->baselines->prepare($content);
        return $this->stateRepository->adoptResolvedBaseline($moduleId, $generationId, [
            'relative_path' => $path,
            'artifact_type' => (string) ($planned['artifactType'] ?? 'source'),
            'base_hash' => $remoteHash,
            'base_storage_path' => $blob['path'],
            'target_hash' => $remoteHash,
            'template_version' => (string) ($manifest['hashes']['templateVersion'] ?? CrudGenerator::TEMPLATE_VERSION),
            'definition_hash' => (string) ($record['definition_hash'] ?? ''),
            'content_kind' => (string) ($planned['contentKind'] ?? 'text'),
        ]);
    }

    /** @return array<string, mixed> */
    private function buildBundle(int $moduleId, string $nonce): array
    {
        $module = ($this->moduleReader)($moduleId);
        $formId = (int) ($module['form_id'] ?? 0);
        if ($moduleId < 1 || $formId < 1) throw new InvalidArgumentException('业务模块未绑定表单');
        $form = ($this->formReader)($formId);
        $schema = ($this->schemaReader)($formId);
        $registryHash = $this->registryHash($schema);
        $definition = $this->definitions->createFromSchema($schema, $form, (array) ($form['publish_config'] ?? []));
        $definition = $this->withDependencyHash($definition, $registryHash);
        $definition = $this->withManagedMigrationPaths($definition, $nonce);
        return $this->buildDefinitionBundle($moduleId, $definition, $schema->hash(), $registryHash);
    }

    private function buildDefinitionBundle(
        int $moduleId,
        CrudDefinition $definition,
        string $schemaHash = '',
        string $registryHash = ''
    ): array {
        $generator = new CrudGenerator($this->projectRoot);
        $remote = $generator->renderManagedBundle($definition);
        $plan = $generator->planManaged($definition, $this->baselineInputs($moduleId));
        $merged = [];
        foreach ((array) $plan['files'] as $file) {
            if (($file['contentKind'] ?? 'text') !== 'binary' && is_string($file['content'] ?? null)) {
                $merged[(string) $file['path']] = $file['content'];
            }
        }
        $resources = $this->resourcesFromDefinition($definition);
        $migration = (string) ($definition->get('generationTargets')['migration'] ?? '');
        return [
            'definition' => $definition, 'plan' => $plan, 'remoteContents' => $remote,
            'mergedTextContents' => $merged, 'definitionHash' => $definition->hash(),
            'schemaHash' => $schemaHash !== '' ? $schemaHash : (string) $definition->get('formSchemaHash', ''),
            'registryHash' => $registryHash,
            'templateVersion' => CrudGenerator::TEMPLATE_VERSION,
            'migrationHash' => hash('sha256', (string) ($remote[$migration] ?? '')),
            'resourcesHash' => hash('sha256', CrudDefinition::canonicalJson($resources)), 'resources' => $resources,
        ];
    }

    private function assertProposalIdentity(array $module, CrudDefinition $definition): void
    {
        $expectedCode = str_replace('_', '-', (string) ($module['code'] ?? ''));
        if ($expectedCode !== '' && !hash_equals($expectedCode, (string) $definition->get('entity', ''))) {
            throw new InvalidArgumentException('CRUD proposal 模块名与业务模块不一致');
        }
        $expectedTable = (string) ($module['table_name'] ?? '');
        if ($expectedTable !== '' && !hash_equals($expectedTable, (string) $definition->get('table', ''))) {
            throw new InvalidArgumentException('CRUD proposal 表名与业务模块不一致');
        }
    }

    private function rebuildRecordedBundle(int $moduleId, array $record, array $manifest, string $nonce): array
    {
        $proposal = $manifest['proposal'] ?? null;
        if (!is_array($proposal)) return $this->buildBundle($moduleId, $nonce);
        $stored = $record['definition'] ?? null;
        if (!is_array($stored) || array_is_list($stored)) {
            throw new RuntimeException('proposal generation 缺少可信 Definition snapshot');
        }
        $definition = CrudDefinition::fromArray($stored);
        $this->assertProposalIdentity(($this->moduleReader)($moduleId), $definition);
        $hashes = (array) ($manifest['hashes'] ?? []);
        $bundle = $this->buildDefinitionBundle(
            $moduleId,
            $definition,
            (string) ($hashes['schemaHash'] ?? ''),
            (string) ($hashes['registryHash'] ?? '')
        );
        $bundle['proposal'] = [
            'type' => (string) ($proposal['type'] ?? ''),
            'digest' => (string) ($proposal['digest'] ?? ''),
        ];
        return $bundle;
    }

    /** DB baseline 只提供定位信息，Base 内容必须从私有 blob 读取并校验 hash。 */
    private function baselineInputs(int $moduleId): array
    {
        $inputs = [];
        foreach ($this->baselines->baselines($moduleId) as $row) {
            $artifact = (string) ($row['artifact_type'] ?? '');
            if (in_array($artifact, ['migration', 'permissionMigration'], true)) continue;
            $inputs[] = [
                'path' => (string) $row['relative_path'],
                'artifactType' => $artifact,
                'baseHash' => (string) $row['base_hash'],
                'baseContent' => $this->baselines->load((string) $row['base_storage_path'], (string) $row['base_hash']),
                'contentKind' => (string) ($row['content_kind'] ?? 'text'),
            ];
        }
        return $inputs;
    }

    private function withDependencyHash(CrudDefinition $definition, string $dependencyHash): CrudDefinition
    {
        $data = $definition->toArray();
        $schema = (array) ($data['formSchema'] ?? []);
        $extensions = (array) ($schema['extensions'] ?? []);
        $extensions['dependencies'] = ['hash' => $dependencyHash];
        $schema['extensions'] = $extensions;
        $data['formSchema'] = $schema;
        return CrudDefinition::fromArray($data);
    }

    private function withManagedMigrationPaths(CrudDefinition $definition, string $nonce): CrudDefinition
    {
        $data = $definition->toArray();
        $entity = (string) $data['entity'];
        $data['generationTargets']['migration'] = "database/generated/{$entity}_{$nonce}.sql";
        $data['generationTargets']['permissionMigration'] = "database/generated/{$entity}_permissions_{$nonce}.sql";
        return CrudDefinition::fromArray($data);
    }

    /** 资源由 Definition 的权限和菜单结构确定性派生，绝不解析生成 SQL。 */
    private function resourcesFromDefinition(CrudDefinition $definition): array
    {
        $data = $definition->toArray();
        $source = (string) $data['entity'];
        $resources = [];
        if (($data['permission']['enabled'] ?? false) === true) {
            foreach ((array) $data['permission']['actions'] as $action) {
                $code = (string) $data['permissionPrefix'] . ':' . (string) $action['codeSuffix'];
                $resources[] = [
                    'resourceKey' => "permission|{$source}|{$code}", 'resourceType' => 'permission',
                    'sourceName' => $source, 'code' => $code, 'name' => (string) $action['label'],
                ];
            }
        }
        $menu = (array) ($data['menu'] ?? []);
        if (($menu['enabled'] ?? false) === true) {
            $href = '/' . ltrim((string) $data['routePath'], '/');
            $resources[] = [
                'resourceKey' => "menu|{$source}|{$href}", 'resourceType' => 'menu', 'sourceName' => $source,
                'name' => (string) $menu['name'], 'href' => $href,
                'permission' => (string) ($resources[0]['code'] ?? ''), 'icon' => (string) $menu['icon'],
                'sortOrder' => (int) $menu['sortOrder'], 'visible' => ($menu['hidden'] ?? false) ? 0 : 1,
            ];
        }
        usort($resources, static fn (array $left, array $right): int => $left['resourceKey'] <=> $right['resourceKey']);
        return $resources;
    }

    private function registryHash(FormSchema $schema): string
    {
        $dependencies = $this->schemas->checkDependencies($schema);
        $hash = (string) ($dependencies['dependencyHash'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new RuntimeException('FormSchema registry hash 无效');
        }
        return $hash;
    }

    private function trustedBundle(array $bundle): array
    {
        unset($bundle['definition']);
        return $bundle;
    }

    private function stateHashes(array $bundle): array
    {
        return array_intersect_key($bundle, array_flip([
            'definitionHash', 'schemaHash', 'registryHash', 'templateVersion', 'migrationHash', 'resourcesHash',
        ]));
    }

    private function operationKey(int $moduleId, array $bundle): string
    {
        $identity = ['moduleId' => $moduleId] + $this->stateHashes($bundle) + [
            'planDigest' => (string) $bundle['plan']['planDigest'],
        ];
        return 'managed:' . hash('sha256', CrudDefinition::canonicalJson($identity));
    }

    /** @return array<string, mixed> */
    private function createOrReuseGeneration(array $row): array
    {
        if (is_object($this->stateRepository) && method_exists($this->stateRepository, 'createOrReuseGeneration')) {
            return $this->stateRepository->createOrReuseGeneration($row, 'system');
        }
        $id = ($this->generationWriter)($row);
        return $row + ['id' => $id];
    }

    private function claimGeneration(int $moduleId, int $generationId): bool
    {
        if (is_object($this->stateRepository) && method_exists($this->stateRepository, 'claimGeneration')) {
            return $this->stateRepository->claimGeneration($moduleId, $generationId, 'system');
        }
        return true;
    }

    /** @return array<string, mixed>|null */
    private function completedResult(int $generationId): ?array
    {
        if (!is_object($this->stateRepository) || !method_exists($this->stateRepository, 'completedResult')) {
            return null;
        }
        return $this->stateRepository->completedResult($generationId);
    }

    private function recordExecutionFailure(int $generationId, string $outcome, Throwable $exception): void
    {
        if (!is_object($this->stateRepository) || $outcome === 'none') {
            return;
        }
        $error = ['message' => $exception->getMessage(), 'type' => $exception::class];
        if ($outcome === 'rolled_back' && method_exists($this->stateRepository, 'markRolledBack')) {
            $this->stateRepository->markRolledBack($generationId, 'system');
            return;
        }
        if ($outcome === 'recovery_required' && method_exists($this->stateRepository, 'markRecoveryRequired')) {
            $this->stateRepository->markRecoveryRequired($generationId, 'GENERATION_RECOVERY_REQUIRED', $error, 'system');
            return;
        }
        if ($outcome === 'running' && method_exists($this->stateRepository, 'markFailed')) {
            $this->stateRepository->markFailed($generationId, 'GENERATION_EXECUTION_FAILED', $error, 'system');
        }
    }

    private function publicPlan(array $plan): array
    {
        foreach ($plan['files'] as &$file) {
            unset($file['content'], $file['baseContent'], $file['localContent'], $file['remoteContent']);
        }
        unset($file);
        return $plan;
    }

    private function conflicts(array $plan, bool $includeContents = false): array
    {
        $conflicts = array_values(array_filter((array) ($plan['files'] ?? []), static fn (array $file): bool => in_array(
            (string) ($file['status'] ?? ''), ['conflict', 'binary-conflict', 'conflict-no-base'], true
        )));
        return array_map(static function (array $file) use ($includeContents): array {
            unset($file['content']);
            if (!$includeContents || ($file['contentKind'] ?? 'text') === 'binary') {
                unset($file['baseContent'], $file['localContent'], $file['remoteContent']);
            }
            return $file;
        }, $conflicts);
    }

    private function nonce(?string $nonce): string
    {
        $value = $nonce ?? gmdate('YmdHis') . '_' . bin2hex(random_bytes(6));
        if (preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $value) !== 1) {
            throw new InvalidArgumentException('managed generation nonce 不合法');
        }
        return $value;
    }
}
