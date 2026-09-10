<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\crud\CrudDefinition;
use app\common\form\schema\FormSchema;
use app\common\form\schema\FormSchemaException;
use app\console\model\BusinessModule;
use app\console\model\Form;
use app\console\model\FormPublishAttempt;
use app\console\model\FormSchemaVersion;
use InvalidArgumentException;
use Throwable;

/** 从服务端已发布 FormSchema 执行 business-managed 完整发布。 */
final class FormFullPublishService
{
    private readonly FormCrudDefinitionFactory $definitions;
    private readonly FormSchemaRepository $schemas;
    private readonly ManagedGenerationService $managed;

    public function __construct(
        private readonly FormDesignerService $forms,
        string $projectRoot,
        array $allowedConnections,
        ?FormCrudDefinitionFactory $definitions = null,
        ?FormSchemaRepository $schemas = null,
        ?ManagedGenerationService $managed = null
    ) {
        $this->definitions = $definitions ?? new FormCrudDefinitionFactory();
        $this->schemas = $schemas ?? new FormSchemaRepository();
        $this->managed = $managed ?? new ManagedGenerationService(
            $projectRoot,
            definitions: $this->definitions,
            schemas: $this->schemas
        );
    }

    public function preview(array $payload, bool $canGenerate): array
    {
        [$form, $module, $schema, $definition, $dependencyHash, $diagnostics] = $this->prepare($payload, false);
        $generation = $this->managed->preview((int) $module->id, $canGenerate);
        $conflicts = array_values((array) ($generation['conflicts'] ?? []));
        return [
            'definitionHash' => $definition->hash(),
            'formSchemaHash' => $schema->hash(),
            'formDependencyHash' => $dependencyHash,
            'diagnostics' => $diagnostics,
            'ddl' => [
                'mode' => 'none',
                'sql' => '',
                'file' => '',
                'message' => '完整发布使用服务端已发布 Schema，不执行草稿 DDL',
                'applied' => false,
            ],
            'generationId' => $generation['generationId'] ?? null,
            'plan' => $generation['plan'] ?? [],
            'sensitive' => $generation['sensitive'] ?? null,
            'conflicts' => $conflicts,
            'publishStatus' => $conflicts === [] && $diagnostics === [] ? 'ready' : 'conflict',
            'formId' => (int) $form->id,
        ];
    }

    public function publish(
        array $payload,
        string $confirmToken,
        string $operationKey,
        bool $canApplyResources,
        string $operator
    ): array {
        [$form, $module, $schema, $definition, $dependencyHash] = $this->prepare($payload, true);
        $generationId = (int) ($payload['generationId'] ?? 0);
        if ($generationId < 1) throw new InvalidArgumentException('完整发布缺少 preview generationId');
        if (!$canApplyResources) throw new InvalidArgumentException('没有应用菜单与权限资源的权限');
        [$attempt, $leaseToken] = $this->beginAttempt($operationKey, $schema, $definition, $dependencyHash);
        if ((string) $attempt->status === 'completed' && is_array($attempt->result)) return $attempt->result;
        if (!in_array((string) $attempt->stage, ['prepared', 'metadata_saved', 'ddl_applied'], true)) {
            throw new InvalidArgumentException('发布事务阶段需要人工恢复：' . $attempt->stage);
        }
        $formId = (int) $form->id;
        $this->checkpointAttempt($attempt, 'ddl_applied', [
            'form_id' => $formId,
            'generation_id' => $generationId,
        ], $leaseToken);
        $this->updateStatus($formId, 'publishing');
        try {
            $generated = $this->managed->execute((int) $module->id, $generationId, $confirmToken);
            $this->checkpointAttempt($attempt, 'source_generated', ['generation_id' => $generationId], $leaseToken);
            $this->publishBusinessModule($formId, $schema, $definition, $operator, $generationId);
            $this->updateStatus($formId, 'published', [
                'crud_generation_id' => $generationId,
                'published_definition_hash' => (string) $generated['definitionHash'],
                'published_schema_hash' => $schema->hash(),
                'published_at' => date('Y-m-d H:i:s'),
            ]);
            $result = [
                'form' => $this->forms->detail($formId)['form'],
                'ddl' => [
                    'mode' => 'none', 'sql' => '', 'file' => '',
                    'message' => '已发布 Schema 的 DDL 不在完整生成事务中重复执行', 'applied' => false,
                ],
                'generation' => $generated,
                'publishStatus' => 'published',
                'routePath' => (string) $definition->get('routePath'),
            ];
            $this->finishAttempt($attempt, $leaseToken, 'completed', 'completed', $result, formId: $formId);
            return $result;
        } catch (Throwable $exception) {
            $status = $this->isConflict($exception) ? 'conflict' : 'failed';
            $this->updateStatus($formId, $status);
            $this->finishAttempt(
                $attempt,
                $leaseToken,
                (string) $attempt->stage,
                $status,
                null,
                ['message' => $exception->getMessage()],
                $formId
            );
            throw $exception;
        }
    }

    public function status(int $formId): array
    {
        $form = Form::find($formId);
        if (!$form) throw new InvalidArgumentException('表单不存在');
        return [
            'formId' => (int) $form->id,
            'publishStatus' => (string) ($form->publish_status ?: 'draft'),
            'publishedAt' => $form->published_at,
            'generationId' => $form->crud_generation_id ? (int) $form->crud_generation_id : null,
            'definitionHash' => $form->published_definition_hash,
            'publishConfig' => $form->publish_config ?? [],
        ];
    }

    public function generation(int $generationId): ?array
    {
        return $this->managed->generation($generationId);
    }

    /** managed 资源与文件在同一 WAL 事务提交，不存在独立资源重试窗口。 */
    public function retryResources(int $formId): array
    {
        $status = $this->status($formId);
        $generationId = (int) ($status['generationId'] ?? 0);
        if ($generationId < 1) throw new InvalidArgumentException('表单没有生成记录');
        $generation = $this->managed->generation($generationId);
        if (($generation['status'] ?? '') !== 'completed') {
            throw new InvalidArgumentException('managed generation 尚未完成，不能单独重试资源');
        }
        return ['resourceApplyStatus' => 'applied', 'publishStatus' => 'published'];
    }

    /** @return array{Form,BusinessModule,FormSchema,CrudDefinition,string,list<array<string,mixed>>} */
    private function prepare(array $payload, bool $strictDependencies = true): array
    {
        $formId = (int) ($payload['formId'] ?? 0);
        if ($formId < 1) throw new InvalidArgumentException('formId 必须为正整数');
        $form = Form::find($formId);
        if (!$form) throw new InvalidArgumentException('表单不存在');
        $module = BusinessModule::where('form_id', $formId)->find();
        if (!$module || (int) $module->id < 1) throw new InvalidArgumentException('表单尚未绑定业务模块');
        if ((int) ($form->business_module_id ?? 0) > 0 && (int) $form->business_module_id !== (int) $module->id) {
            throw new InvalidArgumentException('表单与业务模块绑定不一致');
        }
        $schema = $this->schemas->published($formId);
        $requestedHash = trim((string) ($payload['schemaHash'] ?? ''));
        if ($requestedHash !== '' && !hash_equals($schema->hash(), $requestedHash)) {
            throw new InvalidArgumentException('FORM_SCHEMA_CONFLICT');
        }
        $dependencies = $this->schemas->checkDependencies($schema);
        if ($strictDependencies && $dependencies['diagnostics'] !== []) {
            $diagnostic = $dependencies['diagnostics'][0];
            throw new FormSchemaException($diagnostic['message'], $diagnostic['path'], $diagnostic['code']);
        }
        $definition = $this->definitions->createFromSchema(
            $schema,
            $form->toArray(),
            (array) ($form->publish_config ?? [])
        );
        $definition = $this->withDependencyHash($definition, (string) $dependencies['dependencyHash']);
        return [
            $form,
            $module,
            $schema,
            $definition,
            (string) $dependencies['dependencyHash'],
            array_values((array) $dependencies['diagnostics']),
        ];
    }

    private function withDependencyHash(CrudDefinition $definition, string $dependencyHash): CrudDefinition
    {
        $payload = $definition->toArray();
        $schema = (array) ($payload['formSchema'] ?? []);
        $extensions = (array) ($schema['extensions'] ?? []);
        $extensions['dependencies'] = ['hash' => $dependencyHash];
        $schema['extensions'] = $extensions;
        $payload['formSchema'] = $schema;
        return CrudDefinition::fromArray($payload);
    }

    private function beginAttempt(string $operationKey, FormSchema $schema, CrudDefinition $definition, string $dependencyHash): array
    {
        if ($operationKey === '' || strlen($operationKey) > 128 || preg_match('/[\x00-\x1F\x7F]/', $operationKey)) {
            throw new InvalidArgumentException('operationKey 必须为 1-128 字符');
        }
        $leaseToken = bin2hex(random_bytes(16));
        $attempt = FormPublishAttempt::where('operation_key', $operationKey)->find();
        if ($attempt) {
            if (!hash_equals((string) $attempt->schema_hash, $schema->hash())
                || !hash_equals((string) $attempt->definition_hash, $definition->hash())
                || !hash_equals((string) $attempt->dependency_hash, $dependencyHash)) {
                throw new InvalidArgumentException('发布 operationKey 已被其他定义使用');
            }
            if ((string) $attempt->status === 'completed' && is_array($attempt->result)) return [$attempt, ''];
            if ((string) $attempt->lease_token !== '' && strtotime((string) $attempt->lease_expires_at) > time()) {
                throw new InvalidArgumentException('发布 operationKey 正在执行');
            }
            $affected = FormPublishAttempt::where('id', (int) $attempt->id)
                ->where(function ($query): void {
                    $query->whereNull('lease_expires_at')->whereOr('lease_expires_at', '<=', date('Y-m-d H:i:s'));
                })
                ->update([
                    'lease_token' => $leaseToken,
                    'lease_expires_at' => date('Y-m-d H:i:s', time() + 120),
                    'status' => 'running',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            if ($affected !== 1) throw new InvalidArgumentException('发布 operationKey 正在执行');
            $attempt->refresh();
            return [$attempt, $leaseToken];
        }
        $attempt = new FormPublishAttempt();
        try {
            $attempt->save([
                'operation_key' => $operationKey,
                'schema_hash' => $schema->hash(),
                'definition_hash' => $definition->hash(),
                'dependency_hash' => $dependencyHash,
                'stage' => 'prepared',
                'status' => 'running',
                'lease_token' => $leaseToken,
                'lease_expires_at' => date('Y-m-d H:i:s', time() + 120),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return [$attempt, $leaseToken];
        } catch (Throwable $exception) {
            if (!str_contains($exception->getMessage(), '1062')
                && !str_contains($exception->getMessage(), 'Duplicate entry')) throw $exception;
            $winner = FormPublishAttempt::where('operation_key', $operationKey)->find();
            if (!$winner) throw $exception;
            if (!hash_equals((string) $winner->schema_hash, $schema->hash())
                || !hash_equals((string) $winner->definition_hash, $definition->hash())
                || !hash_equals((string) $winner->dependency_hash, $dependencyHash)) {
                throw new InvalidArgumentException('发布 operationKey 已被其他定义使用', 0, $exception);
            }
            throw new InvalidArgumentException('发布 operationKey 正在执行', 0, $exception);
        }
    }

    private function checkpointAttempt(FormPublishAttempt $attempt, string $stage, array $extra, string $leaseToken): void
    {
        $affected = FormPublishAttempt::where('id', (int) $attempt->id)->where('lease_token', $leaseToken)->update([
            'stage' => $stage,
            'status' => 'running',
            'lease_expires_at' => date('Y-m-d H:i:s', time() + 120),
            'updated_at' => date('Y-m-d H:i:s'),
        ] + $extra);
        if ($affected !== 1) throw new InvalidArgumentException('发布事务租约已失效');
        $attempt->refresh();
    }

    private function finishAttempt(
        FormPublishAttempt $attempt,
        string $leaseToken,
        string $stage,
        string $status,
        ?array $result = null,
        ?array $error = null,
        ?int $formId = null
    ): void {
        $affected = FormPublishAttempt::where('id', (int) $attempt->id)->where('lease_token', $leaseToken)->update([
            'form_id' => $formId ?? $attempt->form_id,
            'stage' => $stage,
            'status' => $status,
            'result' => $result,
            'error' => $error,
            'lease_token' => null,
            'lease_expires_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($affected !== 1) throw new InvalidArgumentException('发布事务租约已失效');
        $attempt->refresh();
    }

    private function isConflict(Throwable $exception): bool
    {
        return preg_match('/(?:token|冲突|hash|计划|已变化|漂移)/i', $exception->getMessage()) === 1;
    }

    private function publishBusinessModule(
        int $formId,
        FormSchema $schema,
        CrudDefinition $definition,
        string $operator,
        int $generationId
    ): void {
        $version = FormSchemaVersion::where('form_id', $formId)
            ->where('schema_hash', $schema->hash())
            ->order('version', 'desc')
            ->find();
        if (!$version) throw new InvalidArgumentException('已发布 FormSchema 版本不存在');
        $form = Form::find($formId);
        if (!$form) throw new InvalidArgumentException('表单不存在');
        $module = BusinessModule::where('form_id', $formId)->find();
        if (!$module) throw new InvalidArgumentException('业务模块不存在');
        $module->save([
            'code' => $schema->key(),
            'name' => (string) ($schema->document()['title'] ?? ''),
            'connection_name' => (string) $definition->get('connection'),
            'table_name' => (string) $definition->get('table'),
            'runtime_route' => '/development/business/runtime/' . $schema->key(),
            'module_route' => (string) $definition->get('routePath'),
            'lifecycle_status' => 'published',
            'published_schema_hash' => $schema->hash(),
            'published_schema_version' => (int) $version->version,
            'current_generation_id' => $generationId,
            'last_success_generation_id' => $generationId,
            'generation_status' => 'generated',
            'metadata' => array_replace((array) ($module->metadata ?? []), [
                'publishedBy' => $operator,
                'publishConfig' => (array) ($form->publish_config ?? []),
            ]),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $form->save(['business_module_id' => (int) $module->id, 'publish_mode' => 'full']);
    }

    private function updateStatus(int $formId, string $status, array $extra = []): void
    {
        if ($formId > 0) Form::where('id', $formId)->update(['publish_status' => $status] + $extra);
    }
}
