<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\form\schema\FormSchema;
use app\common\form\schema\FormSchemaException;
use app\console\model\BusinessModule;
use app\console\model\Form;
use app\console\model\FormSchemaVersion;
use Closure;
use InvalidArgumentException;
use think\facade\Db;
use Throwable;

/** 纯动态表单发布服务：只编译 Schema、执行 forward-only DDL 并推进业务模块元数据。 */
final class FormPublishService
{
    private readonly Closure $saveForm;
    private readonly Closure $previewDdl;
    private readonly Closure $applyDdl;
    private readonly Closure $verifyStructure;
    private readonly Closure $publishMetadata;
    private readonly Closure $statusUpdater;
    private readonly Closure $publishedBaselineReader;
    private readonly Closure $formReader;

    public function __construct(
        private readonly FormDesignerService $forms,
        private readonly FormSchemaRepository $schemas = new FormSchemaRepository(),
        ?callable $saveForm = null,
        ?callable $previewDdl = null,
        ?callable $applyDdl = null,
        ?callable $verifyStructure = null,
        ?callable $publishMetadata = null,
        ?callable $statusUpdater = null,
        ?callable $publishedBaselineReader = null,
        ?callable $formReader = null
    ) {
        $this->saveForm = Closure::fromCallable($saveForm ?? fn (array $payload): array => $this->forms->save($payload));
        $this->previewDdl = Closure::fromCallable($previewDdl ?? fn (array $payload): array => $this->previewForwardDdl($payload));
        $this->applyDdl = Closure::fromCallable($applyDdl ?? fn (array $payload): array => $this->forms->applyDynamicDdl($payload));
        $this->verifyStructure = Closure::fromCallable($verifyStructure ?? function (array $payload): void {
            $this->assertStructure($payload);
        });
        $this->publishMetadata = Closure::fromCallable($publishMetadata ?? fn (array $saved, FormSchema $compiled, string $operator, array $baseline): array => $this->persistPublishedMetadata($saved, $compiled, $operator, $baseline));
        $this->statusUpdater = Closure::fromCallable($statusUpdater ?? fn (int $formId, string $status, array $extra = [], ?array $baseline = null): int => $this->persistStatus($formId, $status, $extra, $baseline));
        $this->publishedBaselineReader = Closure::fromCallable($publishedBaselineReader ?? fn (int $formId, string $code): array => $this->publishedBaseline($formId, $code));
        $this->formReader = Closure::fromCallable($formReader ?? fn (int $formId): array => $this->forms->detail($formId));
    }

    /** 动态发布预览：只做依赖、定义校验和 DDL 预览，无任何写入。 */
    public function previewDynamic(array $payload): array
    {
        $compiled = $this->compileForPublish($payload);
        $compatible = $this->compatiblePayload($payload, $compiled);
        $this->forms->validateDefinition($compatible);
        return [
            'formSchemaHash' => $compiled->hash(),
            'formDependencyHash' => $this->dependencies($compiled)['dependencyHash'],
            'diagnostics' => [],
            'ddl' => ($this->previewDdl)($compatible),
            'publishStatus' => 'ready',
        ];
    }

    /** 兼容旧预览入口；仅委托动态发布，不接受源码生成参数。 */
    public function preview(array $payload): array
    {
        return $this->previewDynamic($payload);
    }

    /** 兼容旧发布入口；仅委托动态发布，不接受覆盖或资源生成参数。 */
    public function publish(array $payload, string $operator = 'system'): array
    {
        return $this->publishDynamic($payload, $operator);
    }

    /** 动态发布：DDL 成功后只推进不可变 Schema 快照与 BusinessModule。 */
    public function publishDynamic(array $payload, string $operator): array
    {
        $formId = (int) ($payload['id'] ?? 0);
        $baseline = null;
        $attemptCode = trim((string) (($payload['schema_document']['key'] ?? null) ?? ($payload['form_key'] ?? '')));
        if ($formId > 0 && $attemptCode !== '') {
            $baseline = ($this->publishedBaselineReader)($formId, $attemptCode);
        }
        try {
            $compiled = $this->compileForPublish($payload);
            $compatible = $this->compatiblePayload($payload, $compiled);
            $this->forms->validateDefinition($compatible);
            $baseline ??= ($this->publishedBaselineReader)($formId, $compiled->key());
            $requestedDependencyHash = trim((string) ($payload['formDependencyHash'] ?? $payload['form_dependency_hash'] ?? ''));
            $currentDependencyHash = (string) $this->dependencies($compiled)['dependencyHash'];
            if ($requestedDependencyHash !== '' && !hash_equals($currentDependencyHash, $requestedDependencyHash)) {
                throw new InvalidArgumentException('FORM_DEPENDENCY_CONFLICT');
            }
        } catch (Throwable $exception) {
            if ($exception->getMessage() !== 'FORM_SCHEMA_CONFLICT') {
                $this->setStatus($formId, 'validation_failed', [], $baseline);
            }
            throw $exception;
        }

        $this->setStatus($formId, 'validating', [], $baseline);
        $this->setStatus($formId, 'ddl_pending', [], $baseline);
        $ddlApplied = false;
        try {
            $saved = ($this->saveForm)($compatible);
            $definition = $this->definitionPayload($saved);
            $formId = (int) ($definition['id'] ?? $formId);
            $this->setStatus($formId, 'publishing', [], $baseline);
            $ddl = ($this->previewDdl)($definition);
            if ((string) ($definition['source_type'] ?? 'created') === 'created') {
                $definition['dynamic_ddl_hash'] = hash('sha256', (string) ($ddl['sql'] ?? ''));
                $ddl = ($this->applyDdl)($definition);
                $ddlApplied = (bool) ($ddl['applied'] ?? false);
            }
        } catch (Throwable $exception) {
            if ($exception instanceof FormMigrationException && $exception->ddlApplied) {
                $ddlApplied = true;
            }
            $this->setStatus($formId, $ddlApplied ? 'metadata_partial' : 'ddl_failed', [], $baseline);
            throw $exception;
        }

        try {
            ($this->verifyStructure)($definition, $compiled);
            $module = ($this->publishMetadata)($saved, $compiled, $operator, $baseline);
        } catch (Throwable $exception) {
            if ($exception->getMessage() !== 'FORM_SCHEMA_CONFLICT') {
                $this->setStatus($formId, 'metadata_partial', [], $baseline);
            }
            throw $exception;
        }
        $finalForm = ($this->formReader)($formId);
        return [
            'form' => $finalForm['form'] ?? $finalForm,
            'businessModule' => $module,
            'ddl' => $ddl,
            'publishStatus' => 'dynamic_published',
            'routePath' => '/development/business/runtime/' . $compiled->key(),
        ];
    }

    public function status(int $formId): array
    {
        $form = Form::find($formId);
        if (!$form) {
            throw new InvalidArgumentException('表单不存在');
        }
        $module = $this->findBusinessModule((int) $form->id, (string) $form->form_key);
        return [
            'formId' => (int) $form->id,
            'publishStatus' => (string) ($module->lifecycle_status ?? $form->publish_status ?? 'draft'),
            'publishedAt' => $form->published_at,
            'generationId' => null,
            'definitionHash' => null,
            'publishConfig' => $form->publish_config ?? [],
        ];
    }

    private function compileForPublish(array $payload): FormSchema
    {
        $compiled = $this->schemas->compile($this->schemaPayload($payload));
        $requestedHash = trim((string) ($payload['schemaHash'] ?? $payload['schema_hash'] ?? ''));
        if ($requestedHash === '' || !hash_equals($compiled->hash(), $requestedHash)) {
            throw new InvalidArgumentException('FORM_SCHEMA_CONFLICT');
        }
        $this->dependencies($compiled);
        return $compiled;
    }

    private function dependencies(FormSchema $compiled): array
    {
        $dependencies = $this->schemas->checkDependencies($compiled);
        if ($dependencies['diagnostics'] !== []) {
            $diagnostic = $dependencies['diagnostics'][0];
            throw new FormSchemaException($diagnostic['message'], $diagnostic['path'], $diagnostic['code']);
        }
        return $dependencies;
    }

    private function previewForwardDdl(array $payload): array
    {
        return $this->forms->previewMigration($payload);
    }

    private function persistPublishedMetadata(array $saved, FormSchema $compiled, string $operator, array $baseline): array
    {
        $this->assertBusinessModuleStorage();
        $definition = $this->definitionPayload($saved);
        $formId = (int) ($definition['id'] ?? 0);
        $code = $compiled->key();
        return Db::transaction(function () use ($definition, $formId, $code, $compiled, $operator, $baseline): array {
            $form = Form::lock(true)->find($formId);
            if (!$form) {
                throw new InvalidArgumentException('表单不存在');
            }
            $version = FormSchemaVersion::where('form_id', $formId)
                ->where('schema_hash', $compiled->hash())
                ->order('version', 'desc')
                ->find();
            if (!$version) {
                throw new InvalidArgumentException('已发布 FormSchema 版本不存在');
            }
            $module = $this->lockedBusinessModule($formId, $code);
            $this->assertPublishedBaseline($module, $baseline);
            if (!$module) {
                $module = $this->createOrReloadBusinessModule($formId, $code, $baseline);
            }
            $now = date('Y-m-d H:i:s');
            $module->save([
                'code' => $code,
                'name' => (string) ($compiled->document()['title'] ?? ''),
                'form_id' => $formId,
                'origin' => $module->origin ?: ((string) ($definition['source_type'] ?? 'created') === 'adopted' ? 'database' : 'visual'),
                'connection_name' => (string) ($definition['connection'] ?? 'mysql'),
                'table_name' => (string) ($definition['table_name'] ?? ''),
                'runtime_route' => '/development/business/runtime/' . $code,
                'module_route' => '/development/business/' . $code,
                'lifecycle_status' => 'dynamic_published',
                'published_schema_hash' => $compiled->hash(),
                'published_schema_version' => (int) $version->version,
                'generation_status' => 'idle',
                'metadata' => array_replace((array) ($module->metadata ?? []), [
                    'publishedBy' => $operator,
                    'publishConfig' => (array) ($definition['publish_config'] ?? []),
                ]),
                'updated_at' => $now,
                'created_at' => $module->id ? $module->created_at : $now,
            ]);
            $form->save([
                'business_module_id' => (int) $module->id,
                'publish_mode' => 'dynamic',
                'publish_status' => 'published',
                'published_schema_hash' => $compiled->hash(),
                'published_at' => $now,
            ]);
            return $module->toArray();
        });
    }

    private function assertBusinessModuleStorage(): void
    {
        try {
            $tables = Db::connect()->getTables();
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('无法检查 077_business_development_center 迁移：' . $exception->getMessage(), 0, $exception);
        }
        $normalized = array_map(static fn (string $table): string => preg_replace('/^.*\./', '', $table) ?? $table, $tables);
        if (!in_array('fun_business_module', $normalized, true) && !in_array('business_module', $normalized, true)) {
            throw new InvalidArgumentException('业务模块存储不可用，请先执行 077_business_development_center 迁移');
        }
    }

    private function findBusinessModule(int $formId, string $code): ?BusinessModule
    {
        try {
            return BusinessModule::where(function ($query) use ($formId, $code): void {
                $query->where('form_id', $formId)->whereOr('code', $code);
            })->find();
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('业务模块存储不可用，请先执行 077_business_development_center 迁移', 0, $exception);
        }
    }

    private function publishedBaseline(int $formId, string $code): array
    {
        $module = $this->findBusinessModule($formId, $code);
        return [
            'exists' => $module !== null,
            'hash' => (string) ($module->published_schema_hash ?? ''),
            'version' => (int) ($module->published_schema_version ?? 0),
        ];
    }

    private function lockedBusinessModule(int $formId, string $code): ?BusinessModule
    {
        return BusinessModule::lock(true)->where(function ($query) use ($formId, $code): void {
            $query->where('form_id', $formId)->whereOr('code', $code);
        })->find();
    }

    private function assertPublishedBaseline(?BusinessModule $module, array $baseline): void
    {
        $current = [
            'exists' => $module !== null,
            'hash' => (string) ($module->published_schema_hash ?? ''),
            'version' => (int) ($module->published_schema_version ?? 0),
        ];
        if ($current !== $baseline) {
            throw new InvalidArgumentException('FORM_SCHEMA_CONFLICT');
        }
    }

    private function createOrReloadBusinessModule(int $formId, string $code, array $baseline): BusinessModule
    {
        try {
            $module = new BusinessModule();
            $module->save(['code' => $code, 'form_id' => $formId]);
            return $module;
        } catch (Throwable $exception) {
            if (!$this->isDuplicateKey($exception)) {
                throw $exception;
            }
            $module = $this->lockedBusinessModule($formId, $code);
            $this->assertPublishedBaseline($module, $baseline);
            if (!$module) {
                throw new InvalidArgumentException('FORM_SCHEMA_CONFLICT', 0, $exception);
            }
            return $module;
        }
    }

    private function isDuplicateKey(Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), '1062')
            || str_contains($exception->getMessage(), 'Duplicate entry');
    }

    private function assertStructure(array $payload): void
    {
        $actual = Db::connect((string) ($payload['connection'] ?? 'mysql'))
            ->getFields((string) ($payload['table_name'] ?? ''));
        foreach ((array) ($payload['fields'] ?? []) as $field) {
            $virtual = in_array((string) ($field['type'] ?? ''), ['group', 'grid', 'divider', 'text', 'collapse', 'tabs', 'repeatable', 'subform'], true)
                || (string) ($field['relation_type'] ?? 'none') === 'has_many';
            $name = (string) ($field['field_name'] ?? '');
            if (!$virtual && !array_key_exists($name, $actual)) {
                throw new InvalidArgumentException('DDL 结构验证失败，缺少字段：' . $name);
            }
        }
    }

    private function persistStatus(int $formId, string $status, array $extra = [], ?array $baseline = null): int
    {
        if ($formId < 1 || $baseline === null) {
            return 0;
        }
        return Db::transaction(function () use ($formId, $status, $extra, $baseline): int {
            $form = Form::lock(true)->find($formId);
            if (!$form) {
                return 0;
            }
            $module = $this->lockedBusinessModule($formId, (string) $form->form_key);
            try {
                $this->assertPublishedBaseline($module, $baseline);
            } catch (InvalidArgumentException) {
                return 0;
            }
            return Form::where('id', $formId)->update([
                'publish_status' => $status === 'dynamic_published' ? 'published' : $status,
            ] + $extra);
        });
    }

    private function setStatus(int $formId, string $status, array $extra = [], ?array $baseline = null): void
    {
        if ($baseline !== null) {
            ($this->statusUpdater)($formId, $status, $extra, $baseline);
        }
    }

    private function schemaPayload(array $payload): array
    {
        $schema = $payload['schema_document'] ?? null;
        return is_array($schema) && (int) ($schema['schemaVersion'] ?? 0) === 2 ? $schema : $payload;
    }

    private function compatiblePayload(array $payload, FormSchema $compiled): array
    {
        $expectedSchemaHash = trim((string) ($payload['expected_schema_hash'] ?? $payload['schema_hash'] ?? ''));
        $document = $compiled->document();
        $database = (array) ($document['database'] ?? []);
        return array_replace($payload, [
            'expected_schema_hash' => $expectedSchemaHash,
            'form_key' => $compiled->key(),
            'name' => (string) ($document['title'] ?? ''),
            'table_name' => (string) ($database['table'] ?? $payload['table_name'] ?? ''),
            'connection' => (string) ($database['connection'] ?? $payload['connection'] ?? 'mysql'),
            'source_type' => (string) ($database['source'] ?? $payload['source_type'] ?? 'created'),
            'fields' => $compiled->fieldProjection(),
            'schema_version' => $compiled->version(),
            'schema_document' => $document,
            'schema_hash' => $compiled->hash(),
            'schema_origin' => (string) ($payload['schema_origin'] ?? 'designer'),
        ]);
    }

    private function definitionPayload(array $saved): array
    {
        $form = $saved['form'] ?? null;
        $data = is_object($form) && method_exists($form, 'toArray') ? $form->toArray() : (array) $form;
        $fields = $saved['fields'] ?? [];
        if (is_object($fields) && method_exists($fields, 'toArray')) {
            $fields = $fields->toArray();
        }
        $data['fields'] = array_map(
            static fn ($field): array => is_object($field) && method_exists($field, 'toArray') ? $field->toArray() : (array) $field,
            (array) $fields
        );
        return $data;
    }
}
