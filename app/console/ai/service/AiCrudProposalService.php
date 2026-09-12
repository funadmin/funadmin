<?php

declare(strict_types=1);

namespace app\console\ai\service;

use app\common\crud\CrudDefinition;
use app\common\form\registry\FieldCapabilityRegistry;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use app\console\development\service\BusinessDevelopmentService;
use Closure;
use InvalidArgumentException;

/** 只接受模型提交的 FormSchema/CrudDefinition 结构化提案，并返回规范化摘要。 */
final class AiCrudProposalService
{
    private const FORBIDDEN = ['bundle', 'trustedBundle', 'files', 'outputPath', 'routePath', 'confirmToken', 'confirmationToken'];
    private const FORBIDDEN_DOCUMENT_KEYS = ['bundle', 'trustedBundle', 'files', 'outputPath', 'routePath', 'apiPrefix', 'generationTargets', 'paths', 'templates', 'confirmToken', 'confirmationToken'];
    private const DOCUMENT_KEYS = ['schemaVersion', 'key', 'title', 'model', 'layout', 'nodes', 'dataSources', 'actions', 'form', 'submit', 'list', 'database', 'extensions'];

    private readonly Closure $moduleReader;
    private readonly Closure $schemaValidator;
    private readonly Closure $generationPreview;
    private readonly Closure $generationApply;

    public function __construct(
        private readonly ?FieldCapabilityRegistry $capabilities = null,
        ?callable $moduleReader = null,
        ?callable $schemaValidator = null,
        ?callable $generationPreview = null,
        ?callable $generationApply = null
    ) {
        $this->moduleReader = Closure::fromCallable($moduleReader ?? static fn (int $id): array => ['module' => ['id' => $id]]);
        $this->schemaValidator = Closure::fromCallable($schemaValidator ?? static function (int $moduleId, array $schema): array {
            $compiled = (new FormSchemaCompiler(new FormSchemaValidator()))->compile($schema);
            return ['document' => $compiled->document(), 'schemaHash' => $compiled->hash()];
        });
        $this->generationPreview = Closure::fromCallable($generationPreview ?? static fn (int $moduleId, array $document, string $proposalType, bool $canApply, ?string $nonce): array => throw new InvalidArgumentException('CRUD generation preview 未配置'));
        $this->generationApply = Closure::fromCallable($generationApply ?? static fn (int $moduleId, int $generationId, string $token): array => throw new InvalidArgumentException('CRUD generation apply 未配置'));
    }

    public static function production(string $root, array $connections): self
    {
        $business = BusinessDevelopmentService::production($root, $connections);
        return new self(
            moduleReader: fn (int $id): array => $business->module($id),
            schemaValidator: fn (int $moduleId, array $schema): array => $business->validateSchema($moduleId, $schema),
            generationPreview: fn (int $moduleId, array $document, string $proposalType, bool $canApply, ?string $nonce): array =>
                $business->previewStructuredProposal($moduleId, $document, $proposalType, $canApply, $nonce),
            generationApply: fn (int $moduleId, int $generationId, string $token): array =>
                $business->formalGeneration($moduleId, $generationId, $token)
        );
    }

    public function validate(array $proposal): array
    {
        $unsupported = array_values(array_intersect(array_keys($proposal), self::FORBIDDEN));
        if ($unsupported !== []) throw new InvalidArgumentException('结构化 CRUD 提案禁止携带：' . implode(',', $unsupported));
        if (($proposal['schema_version'] ?? null) !== 1) throw new InvalidArgumentException('schema_version 必须为 1');
        $type = (string) ($proposal['proposal_type'] ?? '');
        if (!in_array($type, ['form_schema', 'crud_definition'], true)) throw new InvalidArgumentException('提案类型必须为 FormSchema 或 CrudDefinition');
        $documentKey = $type === 'form_schema' ? 'form_schema' : 'crud_definition';
        $document = $proposal[$documentKey] ?? null;
        if (!is_array($document) || array_is_list($document)) throw new InvalidArgumentException('结构化提案正文必须为对象');
        $this->assertNoModelControlledPaths($document);
        $this->validateIdentity($document);
        if ($type === 'form_schema') $this->validateFormSchema($document);
        else $this->validateCrudDefinition($document);
        $normalized = ['schema_version'=>1, 'proposal_type'=>$type, 'module_id'=>(int)($proposal['module_id'] ?? 0), $documentKey=>$this->canonicalize($document)];
        return $normalized + ['proposalDigest'=>hash('sha256', CrudDefinition::canonicalJson($normalized))];
    }

    public function preview(
        array $proposal,
        int $moduleId,
        int $adminId,
        int $conversationId,
        bool $canApply = false,
        ?string $nonce = null
    ): array {
        if ($moduleId <= 0 || $adminId <= 0 || $conversationId <= 0) throw new InvalidArgumentException('CRUD proposal 资源标识不合法');
        $normalized = $this->validate(array_replace($proposal, ['module_id'=>$moduleId]));
        $module = ($this->moduleReader)($moduleId);
        $documentKey = $normalized['proposal_type'] === 'form_schema' ? 'form_schema' : 'crud_definition';
        $document = (array) $normalized[$documentKey];
        $this->assertModuleIdentity($module, $document);
        if ($normalized['proposal_type'] === 'form_schema') {
            $validated = ($this->schemaValidator)($moduleId, $document);
            if (!is_array($validated) || !is_string($validated['schemaHash'] ?? null)) throw new InvalidArgumentException('FormSchema 服务端校验结果不合法');
        }
        $generation = ($this->generationPreview)($moduleId, $document, $normalized['proposal_type'], $canApply, $nonce);
        if (!is_array($generation) || (int) ($generation['generationId'] ?? 0) <= 0) throw new InvalidArgumentException('CRUD generation preview 结果不合法');
        $result = [
            'businessModuleId'=>$moduleId, 'generationId'=>(int)$generation['generationId'],
            'adminId'=>$adminId, 'conversationId'=>$conversationId,
            'proposalDigest'=>$normalized['proposalDigest'], 'plan'=>$generation['plan'] ?? [],
        ];
        $token = (string) ($generation['sensitive']['confirmToken'] ?? '');
        if ($token !== '') $result['confirmToken'] = $token;
        return $result;
    }

    public function apply(array $input, int $adminId, int $conversationId, array $approval = []): array
    {
        $taskId = (int) ($input['taskId'] ?? 0);
        $this->assertFinalApproval($approval, $adminId, $conversationId, $taskId);
        $unsupported = array_values(array_intersect(array_keys($input), array_merge(self::FORBIDDEN, ['proposal', 'form_schema', 'crud_definition'])));
        $unsupported = array_values(array_diff($unsupported, ['confirmToken']));
        if ($unsupported !== []) throw new InvalidArgumentException('CRUD proposal apply 禁止携带：' . implode(',', $unsupported));
        if ($adminId <= 0 || $conversationId <= 0) throw new InvalidArgumentException('CRUD proposal 资源标识不合法');
        $moduleId = (int) ($input['moduleId'] ?? 0);
        $generationId = (int) ($input['generationId'] ?? 0);
        $token = (string) ($input['confirmToken'] ?? '');
        if ($moduleId <= 0 || $generationId <= 0 || $token === '' || strlen($token) > 2048) throw new InvalidArgumentException('CRUD proposal apply 参数不合法');
        ($this->moduleReader)($moduleId);
        return ($this->generationApply)($moduleId, $generationId, $token);
    }

    private function assertFinalApproval(array $approval, int $adminId, int $conversationId, int $taskId): void
    {
        if (($approval['status'] ?? '') !== 'approved'
            || ($approval['operation'] ?? '') !== 'apply_workspace'
            || (int) ($approval['requested_by'] ?? 0) !== $adminId
            || (int) ($approval['decided_by'] ?? 0) !== $adminId
            || (int) ($approval['conversation_id'] ?? 0) !== $conversationId
            || $taskId <= 0
            || (int) ($approval['task_id'] ?? 0) !== $taskId) {
            throw new InvalidArgumentException('最终审批无效或不可绕过', 403);
        }
    }

    private function assertModuleIdentity(array $detail, array $document): void
    {
        $module = (array) ($detail['module'] ?? $detail);
        $expectedKey = (string) ($module['code'] ?? $module['key'] ?? '');
        $actualKey = (string) ($document['key'] ?? $document['module'] ?? $document['entity'] ?? '');
        if ($expectedKey !== '' && !hash_equals($expectedKey, $actualKey)) throw new InvalidArgumentException('CRUD proposal 模块名与业务模块不一致');
        $expectedTable = (string) ($module['table_name'] ?? $module['table'] ?? '');
        $actualTable = (string) (($document['database']['table'] ?? null) ?? ($document['table'] ?? ''));
        if ($expectedTable !== '' && !hash_equals($expectedTable, $actualTable)) throw new InvalidArgumentException('CRUD proposal 表名与业务模块不一致');
    }

    private function assertNoModelControlledPaths(array $document, string $location = 'document'): void
    {
        foreach ($document as $key => $value) {
            if (is_string($key) && in_array($key, self::FORBIDDEN_DOCUMENT_KEYS, true)) {
                throw new InvalidArgumentException('结构化 CRUD 提案禁止模型控制路径：' . $location . '.' . $key);
            }
            if (is_array($value)) $this->assertNoModelControlledPaths($value, $location . '.' . (string) $key);
        }
    }

    private function validateIdentity(array $document): void
    {
        $key = (string)($document['key'] ?? $document['module'] ?? $document['entity'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{0,60}$/', $key) !== 1) throw new InvalidArgumentException('模块名不合法');
        $database = (array)($document['database'] ?? []);
        $table = (string)($database['table'] ?? $document['table'] ?? '');
        if (preg_match('/^[a-z_][a-z0-9_]{0,189}$/', $table) !== 1) throw new InvalidArgumentException('表名不合法');
    }

    private function validateFormSchema(array $schema): void
    {
        $unknown = array_diff(array_keys($schema), self::DOCUMENT_KEYS);
        if ($unknown !== []) throw new InvalidArgumentException('未知顶层字段：' . implode(',', $unknown));
        if (($schema['schemaVersion'] ?? null) !== 2) throw new InvalidArgumentException('FormSchema schema_version 必须为 2');
        $registry = $this->capabilities ?? new FieldCapabilityRegistry();
        foreach ((array)($schema['nodes'] ?? []) as $node) {
            if (!is_array($node)) throw new InvalidArgumentException('字段能力节点不合法');
            $type = (string)($node['type'] ?? '');
            if (!$registry->has($type)) throw new InvalidArgumentException('字段能力未注册：' . $type);
        }
        (new FormSchemaCompiler(new FormSchemaValidator(fieldCapabilities: $registry)))->compile($schema);
    }

    private function validateCrudDefinition(array $definition): void
    {
        if (!isset($definition['schemaVersion'], $definition['fields']) || !is_array($definition['fields'])) throw new InvalidArgumentException('CrudDefinition 结构不完整');
    }

    private function canonicalize(array $value): array
    {
        if (!array_is_list($value)) ksort($value, SORT_STRING);
        foreach ($value as $key=>$item) if (is_array($item)) $value[$key] = $this->canonicalize($item);
        return $value;
    }
}
