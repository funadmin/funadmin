<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\form\schema\FormSchemaException;
use app\console\model\Form;
use InvalidArgumentException;
use Throwable;

/** 编排表单元数据、增量 DDL、全栈代码与菜单权限的一次发布。 */
final class FormPublishService
{
    private readonly FormCrudDefinitionFactory $definitions;
    private readonly DevCrudService $crud;
    private readonly FormSchemaRepository $schemas;

    public function __construct(
        private readonly FormDesignerService $forms,
        string $projectRoot,
        array $allowedConnections,
        ?FormCrudDefinitionFactory $definitions = null,
        ?DevCrudService $crud = null,
        ?FormSchemaRepository $schemas = null
    ) {
        $this->definitions = $definitions ?? new FormCrudDefinitionFactory();
        $this->crud = $crud ?? new DevCrudService($projectRoot, $allowedConnections);
        $this->schemas = $schemas ?? new FormSchemaRepository();
    }

    public function preview(array $payload, bool $canGenerate): array
    {
        $schemaPayload = $this->schemaPayload($payload);
        $dependencies = $this->schemas->checkDependencies($schemaPayload);
        if ($dependencies['diagnostics'] !== []) {
            return [
                'diagnostics' => $dependencies['diagnostics'],
                'formDependencyHash' => $dependencies['dependencyHash'],
                'publishStatus' => 'dependency_failed',
            ];
        }
        $compiled = $this->schemas->compile($schemaPayload);
        $compatible = $this->compatiblePayload($payload, $compiled);
        $this->forms->validateDefinition($compatible);
        $definition = $this->withDependencyHash($this->definitions->createFromSchema(
            $compiled,
            $compatible,
            (array) ($compatible['publish_config'] ?? []),
            $this->schema($compatible)
        ), $dependencies['dependencyHash']);
        $ddl = $this->forms->previewMigration($compatible);
        $crud = $this->crud->preview($definition->toArray(), $canGenerate, $canGenerate);
        $files = (array) ($crud['plan']['files'] ?? []);
        $conflicts = array_values(array_filter($files, static fn (array $file): bool => ($file['status'] ?? '') === 'conflict'));
        return [
            'definition' => $definition->toArray(),
            'definitionHash' => $definition->hash(),
            'formSchemaHash' => $compiled->hash(),
            'formDependencyHash' => $dependencies['dependencyHash'],
            'diagnostics' => [],
            'ddl' => $ddl,
            'generationId' => $crud['generationId'] ?? null,
            'plan' => $crud['plan'] ?? [],
            'sensitive' => $crud['sensitive'] ?? null,
            'conflicts' => $conflicts,
            'publishStatus' => $conflicts === [] ? 'ready' : 'conflict',
        ];
    }

    public function publish(
        array $payload,
        string $confirmToken,
        array $allowOverwrite,
        bool $canOverwrite,
        bool $canApplyResources,
        string $operator
    ): array {
        $schemaPayload = $this->schemaPayload($payload);
        $dependencies = $this->schemas->checkDependencies($schemaPayload);
        if ($dependencies['diagnostics'] !== []) {
            $diagnostic = $dependencies['diagnostics'][0];
            throw new FormSchemaException($diagnostic['message'], $diagnostic['path'], $diagnostic['code']);
        }
        $compiled = $this->schemas->compile($schemaPayload);
        $compatible = $this->compatiblePayload($payload, $compiled);
        $crudDefinition = $this->withDependencyHash($this->definitions->createFromSchema(
            $compiled,
            $compatible,
            (array) ($compatible['publish_config'] ?? []),
            $this->schema($compatible)
        ), $dependencies['dependencyHash']);
        try {
            $validatedPlan = $this->crud->preflightGeneration(
                $crudDefinition->toArray(),
                $confirmToken,
                $allowOverwrite,
                $canOverwrite,
                $canApplyResources
            );
        } catch (Throwable $exception) {
            $formId = (int) ($payload['id'] ?? 0);
            if ($formId > 0) $this->updateStatus($formId, $this->isConflict($exception) ? 'conflict' : 'failed');
            throw $exception;
        }

        $saved = $this->forms->save($compatible);
        $definitionPayload = $this->definitionPayload($saved);
        $formId = (int) ($definitionPayload['id'] ?? 0);
        $this->updateStatus($formId, 'publishing');
        $ddl = null;
        $generationFloor = (int) (\app\console\model\CrudGeneration::max('id') ?: 0);
        try {
            $ddl = $this->forms->applyMigration($definitionPayload);
            $generated = $this->crud->generate(
                $crudDefinition->toArray(),
                $confirmToken,
                $allowOverwrite,
                $canOverwrite,
                $operator,
                true,
                $canApplyResources,
                $validatedPlan
            );
            $resourceStatus = (string) ($generated['resourceApplyStatus'] ?? 'failed');
            $status = $resourceStatus === 'applied' ? 'published' : 'partial';
            $publishMetadata = [
                'crud_generation_id' => (int) ($generated['generationId'] ?? 0) ?: null,
                'published_definition_hash' => $crudDefinition->hash(),
            ];
            if ($status === 'published') {
                $publishMetadata['published_schema_hash'] = $compiled->hash();
                $publishMetadata['published_at'] = date('Y-m-d H:i:s');
            }
            $this->updateStatus($formId, $status, $publishMetadata);
            return [
                'form' => $this->forms->detail($formId)['form'],
                'ddl' => $ddl,
                'generation' => $generated,
                'publishStatus' => $status,
                'routePath' => (string) $crudDefinition->get('routePath'),
            ];
        } catch (Throwable $exception) {
            $generationId = $this->latestRetryableGenerationId($crudDefinition->hash(), $generationFloor);
            $ddlApplied = $ddl !== null || ($exception instanceof FormMigrationException && $exception->ddlApplied);
            if ($generationId !== null) {
                $this->updateStatus($formId, 'partial', [
                    'crud_generation_id' => $generationId,
                    'published_definition_hash' => $crudDefinition->hash(),
                ]);
            } else {
                $status = $this->isConflict($exception) ? 'conflict' : ($ddlApplied ? 'partial' : 'failed');
                $this->updateStatus($formId, $status);
            }
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
        return $this->crud->generation($generationId);
    }

    public function retryResources(int $formId): array
    {
        $status = $this->status($formId);
        $generationId = (int) ($status['generationId'] ?? 0);
        if ($generationId < 1) throw new InvalidArgumentException('表单没有可重试的生成记录');
        $result = $this->crud->applyResources($generationId);
        if (($result['resourceApplyStatus'] ?? '') === 'applied') {
            $form = Form::find($formId);
            $pendingHash = is_array($result['definition'] ?? null)
                ? (string) (($result['definition']['formSchemaHash'] ?? ''))
                : (string) ($form->schema_hash ?? '');
            $this->updateStatus($formId, 'published', [
                'published_at' => date('Y-m-d H:i:s'),
                'published_schema_hash' => $pendingHash,
            ]);
        }
        return $result + ['publishStatus' => ($result['resourceApplyStatus'] ?? '') === 'applied' ? 'published' : 'partial'];
    }

    private function withDependencyHash(\app\common\crud\CrudDefinition $definition, string $dependencyHash): \app\common\crud\CrudDefinition
    {
        $payload = $definition->toArray();
        $schema = (array) ($payload['formSchema'] ?? []);
        $extensions = (array) ($schema['extensions'] ?? []);
        $extensions['dependencies'] = ['hash' => $dependencyHash];
        $schema['extensions'] = $extensions;
        $payload['formSchema'] = $schema;
        return \app\common\crud\CrudDefinition::fromArray($payload);
    }

    private function schemaPayload(array $payload): array
    {
        $schema = $payload['schema_document'] ?? null;
        return is_array($schema) && (int) ($schema['schemaVersion'] ?? 0) === 2 ? $schema : $payload;
    }

    private function compatiblePayload(array $payload, \app\common\form\schema\FormSchema $compiled): array
    {
        $document = $compiled->document();
        $database = (array) ($document['database'] ?? []);
        return array_replace($payload, [
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

    private function schema(array $payload): array
    {
        if ((string) ($payload['source_type'] ?? 'created') !== 'adopted') return [];
        return $this->crud->inspect(
            (string) ($payload['connection'] ?? 'mysql'),
            (string) ($payload['table_name'] ?? '')
        );
    }

    private function definitionPayload(array $saved): array
    {
        $form = $saved['form'] ?? null;
        $data = is_object($form) && method_exists($form, 'toArray') ? $form->toArray() : (array) $form;
        $data['fields'] = array_map(
            static fn ($field): array => is_object($field) && method_exists($field, 'toArray') ? $field->toArray() : (array) $field,
            (array) ($saved['fields'] ?? [])
        );
        return $data;
    }

    private function latestRetryableGenerationId(string $definitionHash, int $afterId): ?int
    {
        $records = \app\console\model\CrudGeneration::where('operation', 'generate')
            ->where('definition_hash', $definitionHash)
            ->where('id', '>', $afterId)
            ->order('id', 'desc')
            ->select();
        foreach ($records as $record) {
            $manifest = is_array($record->manifest) ? $record->manifest : [];
            if (in_array((string) ($manifest['resourceApplyStatus'] ?? ''), ['pending', 'failed'], true)) {
                return (int) $record->id;
            }
        }
        return null;
    }

    private function isConflict(Throwable $exception): bool
    {
        return preg_match('/(?:token|冲突|hash|计划|已变化)/i', $exception->getMessage()) === 1;
    }

    private function updateStatus(int $formId, string $status, array $extra = []): void
    {
        if ($formId < 1) return;
        Form::where('id', $formId)->update(['publish_status' => $status] + $extra);
    }
}
