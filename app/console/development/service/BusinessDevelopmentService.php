<?php

declare(strict_types=1);

namespace app\console\development\service;

use app\common\crud\CrudDefinition;
use app\common\form\registry\FieldCapabilityRegistry;
use app\console\development\exception\BusinessOperationException;
use app\console\development\http\BusinessResponseSanitizer;
use app\console\form\repository\FormSchemaRepository;
use app\console\form\service\FormDataService;
use app\console\form\service\FormDesignerService;
use app\console\form\service\FormPublishService;
use InvalidArgumentException;

/** 统一业务开发 API 的应用编排层。 */
final class BusinessDevelopmentService
{
    public function __construct(
        private readonly BusinessModuleService $modules,
        private readonly FormDesignerService $forms,
        private readonly FormSchemaRepository $schemas,
        private readonly FormPublishService $publisher,
        private readonly ManagedGenerationService $managed,
        private readonly FormDataService $data,
        private readonly DevCrudService $crud,
        private readonly FieldCapabilityRegistry $fieldCapabilities
    ) {
    }

    public static function production(string $root, array $connections): self
    {
        $schemas = new FormSchemaRepository();
        $forms = new FormDesignerService($root, $schemas);
        return new self(
            new BusinessModuleService(),
            $forms,
            $schemas,
            new FormPublishService($forms, $schemas),
            new ManagedGenerationService($root, schemas: $schemas),
            new FormDataService(),
            new DevCrudService($root, $connections),
            new FieldCapabilityRegistry()
        );
    }

    public function modules(int $page, int $pageSize, string $keyword, string $status, string $origin): array
    {
        self::assertEnum($status, ['', 'draft', 'published', 'dynamic_published', 'disabled'], 'status');
        self::assertEnum($origin, ['', 'visual', 'database'], 'origin');
        return $this->modules->listing($page, $pageSize, $keyword, $status, $origin);
    }

    public function module(int $id): array
    {
        self::assertPositiveId($id);
        return $this->modules->detail($id);
    }

    public function createVisual(array $input, string $actor): array
    {
        $payload = $this->creationPayload($input, 'created', []);
        return $this->modules->createWithForm($payload, 'visual', $actor, $this->forms);
    }

    public function inspectDatabase(string $connection, string $table): array
    {
        self::assertIdentifier($connection, 'connection');
        self::assertIdentifier($table, 'table');
        $inspection = $this->crud->infer($connection, $table);
        $schema = (array) ($inspection['schema'] ?? []);
        $canonical = [
            'connection' => $connection,
            'table' => $table,
            'fields' => (array) ($inspection['fields'] ?? []),
            'primaryKey' => (array) ($schema['primaryKey'] ?? []),
            'indexes' => (array) ($schema['indexes'] ?? []),
        ];
        return [
            'connection' => $connection,
            'table' => $table,
            'snapshotHash' => hash('sha256', CrudDefinition::canonicalJson($canonical)),
            'observedAt' => date(DATE_ATOM),
            'fields' => $canonical['fields'],
            'primaryKey' => $canonical['primaryKey'],
            'indexes' => $canonical['indexes'],
        ];
    }

    public function createFromDatabase(array $input, string $actor): array
    {
        $connection = trim((string) ($input['connection'] ?? 'mysql'));
        $table = trim((string) ($input['table'] ?? ''));
        $expectedHash = trim((string) ($input['expectedInspectionHash'] ?? ''));
        self::assertHash($expectedHash);
        $inspection = $this->inspectDatabase($connection, $table);
        if (!hash_equals($inspection['snapshotHash'], $expectedHash)) throw new InvalidArgumentException('DATABASE_INSPECTION_STALE');
        $payload = $this->creationPayload($input, 'adopted', $inspection['fields']);
        $payload['connection'] = $connection;
        $payload['table_name'] = $table;
        $payload['schema_origin'] = 'database';
        return $this->modules->createWithForm($payload, 'database', $actor, $this->forms);
    }

    public function validateSchema(int $moduleId, array $schema): array
    {
        $module = $this->module($moduleId);
        $compiled = $this->schemas->compile($schema);
        $this->assertSchemaIdentity($module, $compiled->key());
        return $this->schemas->compilePayload($schema);
    }

    public function saveSchema(int $moduleId, array $schema, string $expectedHash, string $actor, string $summary): array
    {
        self::assertHash($expectedHash);
        $detail = $this->module($moduleId);
        $formId = (int) ($detail['module']['form_id'] ?? 0);
        $compiled = $this->schemas->compile($schema);
        $this->assertSchemaIdentity($detail, $compiled->key());
        $version = $this->schemas->saveCompiledVersionIfCurrentHash($formId, $compiled, $expectedHash, 'business_api', $actor, $summary);
        return ['version' => $version->toArray(), 'document' => $compiled->document(), 'schemaHash' => $compiled->hash()];
    }

    public function compileSchema(int $moduleId, array $schema): array
    {
        return $this->validateSchema($moduleId, $schema);
    }

    public function exportSchema(int $moduleId, array $schema): array
    {
        $detail = $this->module($moduleId);
        $compiled = $this->schemas->compile($schema);
        $this->assertSchemaIdentity($detail, $compiled->key());
        return ['document' => $this->schemas->export($schema)];
    }

    public function schemaVersions(int $moduleId): array
    {
        return ['list' => $this->schemas->versions($this->formId($moduleId))];
    }

    public function schemaVersion(int $moduleId, int $version): array
    {
        self::assertPositiveId($version);
        return $this->schemas->findVersion($this->formId($moduleId), $version)->toArray();
    }

    public function schemaDiff(int $moduleId, int $fromVersion, int $toVersion): array
    {
        self::assertPositiveId($fromVersion);
        self::assertPositiveId($toVersion);
        if ($fromVersion === $toVersion) throw new InvalidArgumentException('版本号不能相同');
        return $this->schemas->diff($this->formId($moduleId), $fromVersion, $toVersion);
    }

    public function rollbackSchema(
        int $moduleId,
        int $version,
        string $expectedSchemaHash,
        string $actor,
        string $summary
    ): array {
        self::assertPositiveId($version);
        self::assertHash($expectedSchemaHash);
        $formId = $this->formId($moduleId);
        // 旧实现 $this->schemas->rollback($formId, ...) 缺少并发基线，必须使用原子 CAS 回滚。
        return $this->schemas->rollbackIfCurrentHash($formId, $version, $expectedSchemaHash, $actor, $summary)->toArray();
    }

    public function databaseTables(string $connection): array
    {
        self::assertIdentifier($connection, 'connection');
        return $this->crud->tables($connection);
    }

    public function databaseTableSchema(string $connection, string $table): array
    {
        self::assertIdentifier($connection, 'connection');
        self::assertIdentifier($table, 'table');
        return $this->crud->inspect($connection, $table);
    }

    public function previewPublish(int $moduleId, array $payload): array
    {
        return $this->publisher->previewDynamic($this->modulePayload($moduleId, $payload));
    }

    public function publish(int $moduleId, array $payload, string $actor): array
    {
        return $this->publisher->publishDynamic($this->modulePayload($moduleId, $payload), $actor);
    }

    public function runtimeMeta(int $moduleId): array
    {
        $detail = $this->module($moduleId);
        return $this->data->meta((string) $detail['module']['code']);
    }

    public function previewFormalGeneration(int $moduleId, bool $includeSensitive, ?string $nonce, array $input = []): array
    {
        self::assertPositiveId($moduleId);
        $this->assertFormalGenerationInput($input);
        return $this->managed->preview($moduleId, $includeSensitive, $nonce);
    }

    public function previewStructuredProposal(
        int $moduleId,
        array $document,
        string $proposalType,
        bool $includeSensitive,
        ?string $nonce
    ): array {
        self::assertPositiveId($moduleId);
        return $this->managed->previewProposal($moduleId, $document, $proposalType, $includeSensitive, $nonce);
    }

    public function formalGeneration(int $moduleId, int $generationId, string $confirmToken, array $input = []): array
    {
        self::assertPositiveId($moduleId);
        self::assertPositiveId($generationId);
        $this->assertFormalGenerationInput($input);
        if ($confirmToken === '' || strlen($confirmToken) > 2048) throw new InvalidArgumentException('confirmToken 不合法');
        return $this->managed->execute($moduleId, $generationId, $confirmToken);
    }

    public function generations(int $page, int $pageSize, int $moduleId, string $status): array
    {
        if ($moduleId < 0) throw new InvalidArgumentException('moduleId 不合法');
        self::assertEnum($status, ['', 'planned', 'completed', 'failed', 'conflict'], 'status');
        return $this->modules->generations($page, $pageSize, $moduleId, $status);
    }

    public function generation(int $id): array
    {
        self::assertPositiveId($id);
        return $this->modules->generation($id);
    }

    public function recoverGeneration(int $id, string $expectedRecoveryStatus, string $actor): array
    {
        self::assertPositiveId($id);
        self::assertEnum($expectedRecoveryStatus, ['none', 'recovering', 'recovery_required'], 'expectedRecoveryStatus');
        return GenerationTransactionService::production()->recoverGeneration($id, $expectedRecoveryStatus, $actor);
    }

    public function retryResources(int $id): array
    {
        $this->generation($id);
        throw new InvalidArgumentException('managed generation 的资源与文件原子提交，不支持独立重试');
    }

    public function adoptResolvedBaseline(int $moduleId, int $generationId, string $path, string $localHash, string $remoteHash, string $actor): array
    {
        self::assertPositiveId($moduleId);
        self::assertPositiveId($generationId);
        self::assertHash($localHash);
        self::assertHash($remoteHash);
        return $this->managed->adoptResolvedBaseline($moduleId, $generationId, $path, $localHash, $remoteHash, $actor);
    }

    public function fieldCapabilities(): array
    {
        return self::fieldCapabilityPayload($this->fieldCapabilities, []);
    }

    public static function fieldCapabilityPayload(FieldCapabilityRegistry $registry, array $diagnostics): array
    {
        return ['registryVersion' => $registry->version(), 'schemaVersion' => 2, 'registryHash' => $registry->hash(), 'capabilities' => array_values($registry->definitions()), 'diagnostics' => $diagnostics];
    }

    public static function assertPositiveId(int $id): void
    {
        if ($id < 1) throw new InvalidArgumentException('ID 必须为正整数');
    }

    public static function assertCode(string $code): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,60}$/', $code) !== 1) throw new InvalidArgumentException('code 不合法');
    }

    public static function assertHash(string $hash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) throw new InvalidArgumentException('hash 不合法');
    }

    public static function pagination(int $page, int $pageSize): array
    {
        return [max(1, $page), min(100, max(1, $pageSize))];
    }

    private function creationPayload(array $input, string $source, array $fields): array
    {
        $allowed = array_intersect_key($input, array_flip(['code', 'name', 'table', 'connection', 'status', 'listConfig', 'formConfig', 'remark']));
        $code = trim((string) ($allowed['code'] ?? ''));
        self::assertCode($code);
        $name = trim((string) ($allowed['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) throw new InvalidArgumentException('name 不合法');
        $table = trim((string) ($allowed['table'] ?? ('fun_' . $code)));
        self::assertIdentifier($table, 'table');
        $connection = trim((string) ($allowed['connection'] ?? 'mysql'));
        self::assertIdentifier($connection, 'connection');
        $status = $allowed['status'] ?? 1;
        if (!in_array($status, [0, 1, '0', '1'], true)) throw new InvalidArgumentException('status 不合法');
        $listConfig = $allowed['listConfig'] ?? [];
        $formConfig = $allowed['formConfig'] ?? [];
        if (!is_array($listConfig) || !is_array($formConfig)) throw new InvalidArgumentException('配置必须为对象或数组');
        $remark = (string) ($allowed['remark'] ?? '');
        if (mb_strlen($remark) > 1000) throw new InvalidArgumentException('remark 过长');
        return ['form_key' => $code, 'name' => $name, 'table_name' => $table, 'connection' => $connection, 'source_type' => $source, 'status' => (int) $status, 'list_config' => $listConfig, 'form_config' => $formConfig, 'remark' => $remark, 'fields' => $fields];
    }

    private function modulePayload(int $moduleId, array $payload): array
    {
        $detail = $this->module($moduleId);
        $allowed = array_intersect_key($payload, array_flip(['schema_document', 'schemaHash', 'schema_hash', 'expected_schema_hash', 'formDependencyHash', 'publish_config']));
        $schema = $allowed['schema_document'] ?? null;
        if (!is_array($schema) || array_is_list($schema)) throw new InvalidArgumentException('schema_document 必须为对象');
        $compiled = $this->schemas->compile($schema);
        $this->assertSchemaIdentity($detail, $compiled->key());
        $schemaHash = trim((string) ($allowed['schemaHash'] ?? $allowed['schema_hash'] ?? ''));
        self::assertHash($schemaHash);
        $expectedHash = trim((string) ($allowed['expected_schema_hash'] ?? ''));
        self::assertHash($expectedHash);
        $allowed['expected_schema_hash'] = $expectedHash;
        $dependencyHash = trim((string) ($allowed['formDependencyHash'] ?? ''));
        if ($dependencyHash !== '') self::assertHash($dependencyHash);
        $publishConfig = $allowed['publish_config'] ?? [];
        if (!is_array($publishConfig)) throw new InvalidArgumentException('publish_config 必须为对象或数组');
        $allowed['publish_config'] = $publishConfig;
        $allowed['id'] = (int) $detail['module']['form_id'];
        return $allowed;
    }

    private function formId(int $moduleId): int
    {
        $detail = $this->module($moduleId);
        $formId = (int) ($detail['module']['form_id'] ?? 0);
        self::assertPositiveId($formId);
        return $formId;
    }

    private function assertFormalGenerationInput(array $input): void
    {
        $unsupported = array_values(array_intersect(
            array_keys($input),
            ['allowOverwrite', 'definition', 'trustedBundle', 'files', 'resources', 'routePath']
        ));
        if ($unsupported !== []) {
            throw new BusinessOperationException('UNSUPPORTED_GENERATION_INPUT', ['fields' => $unsupported]);
        }
    }

    private function assertSchemaIdentity(array $detail, string $code): void
    {
        if (!hash_equals((string) $detail['module']['code'], $code)) throw new InvalidArgumentException('Schema key 与业务模块不匹配');
    }

    private static function assertIdentifier(string $value, string $field): void
    {
        if (preg_match('/^[a-z_][a-z0-9_]{0,189}$/', $value) !== 1) throw new InvalidArgumentException($field . ' 不合法');
    }

    private static function assertEnum(string $value, array $allowed, string $field): void
    {
        if (!in_array($value, $allowed, true)) throw new InvalidArgumentException($field . ' 不合法');
    }
}

final class BusinessConflictException extends InvalidArgumentException
{
}
