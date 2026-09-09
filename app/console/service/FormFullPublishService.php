<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\crud\CrudDefinition;
use app\common\form\schema\FormSchema;
use app\common\form\schema\FormSchemaException;
use app\console\model\CrudGeneration;
use app\console\model\Form;
use InvalidArgumentException;
use Throwable;

/** 元数据、增量 DDL、静态源码、菜单权限的一次完整发布。 */
final class FormFullPublishService
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
        [$compiled, $compatible, $definition, $dependencyHash] = $this->prepare($payload);
        $ddl = $this->forms->previewMigration($compatible);
        $crud = $this->crud->preview($definition->toArray(), $canGenerate, $canGenerate);
        $files = (array) ($crud['plan']['files'] ?? []);
        $conflicts = array_values(array_filter(
            $files,
            static fn (array $file): bool => ($file['status'] ?? '') === 'conflict'
        ));
        return [
            'definition' => $definition->toArray(),
            'definitionHash' => $definition->hash(),
            'formSchemaHash' => $compiled->hash(),
            'formDependencyHash' => $dependencyHash,
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
        [$compiled, $compatible, $definition] = $this->prepare($payload);
        $validatedPlan = $this->crud->preflightGeneration(
            $definition->toArray(),
            $confirmToken,
            $allowOverwrite,
            $canOverwrite,
            $canApplyResources
        );
        $saved = $this->forms->save($compatible);
        $definitionPayload = $this->definitionPayload($saved);
        $formId = (int) ($definitionPayload['id'] ?? 0);
        $this->updateStatus($formId, 'publishing');
        $ddl = null;
        $generationFloor = (int) (CrudGeneration::max('id') ?: 0);
        try {
            $ddl = $this->forms->applyMigration($definitionPayload);
            $generated = $this->crud->generate(
                $definition->toArray(),
                $confirmToken,
                $allowOverwrite,
                $canOverwrite,
                $operator,
                true,
                $canApplyResources,
                $validatedPlan
            );
            $status = ($generated['resourceApplyStatus'] ?? '') === 'applied' ? 'published' : 'partial';
            $metadata = [
                'crud_generation_id' => (int) ($generated['generationId'] ?? 0) ?: null,
                'published_definition_hash' => $definition->hash(),
            ];
            if ($status === 'published') {
                $metadata['published_schema_hash'] = $compiled->hash();
                $metadata['published_at'] = date('Y-m-d H:i:s');
            }
            $this->updateStatus($formId, $status, $metadata);
            return [
                'form' => $this->forms->detail($formId)['form'],
                'ddl' => $ddl,
                'generation' => $generated,
                'publishStatus' => $status,
                'routePath' => (string) $definition->get('routePath'),
            ];
        } catch (Throwable $exception) {
            $generationId = $this->latestRetryableGenerationId($definition->hash(), $generationFloor);
            $ddlApplied = $ddl !== null || ($exception instanceof FormMigrationException && $exception->ddlApplied);
            $status = $this->isConflict($exception) ? 'conflict' : ($ddlApplied ? 'partial' : 'failed');
            $extra = $generationId === null ? [] : [
                'crud_generation_id' => $generationId,
                'published_definition_hash' => $definition->hash(),
            ];
            $this->updateStatus($formId, $status, $extra);
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
            $this->updateStatus($formId, 'published', [
                'published_at' => date('Y-m-d H:i:s'),
                'published_schema_hash' => (string) ($form->schema_hash ?? ''),
            ]);
        }
        return $result + [
            'publishStatus' => ($result['resourceApplyStatus'] ?? '') === 'applied' ? 'published' : 'partial',
        ];
    }

    private function prepare(array $payload): array
    {
        $schemaPayload = is_array($payload['schema_document'] ?? null) ? $payload['schema_document'] : $payload;
        $compiled = $this->schemas->compile($schemaPayload);
        $requestedHash = trim((string) ($payload['schemaHash'] ?? $payload['schema_hash'] ?? ''));
        if ($requestedHash !== '' && !hash_equals($compiled->hash(), $requestedHash)) {
            throw new InvalidArgumentException('FORM_SCHEMA_CONFLICT');
        }
        $dependencies = $this->schemas->checkDependencies($compiled);
        if ($dependencies['diagnostics'] !== []) {
            $diagnostic = $dependencies['diagnostics'][0];
            throw new FormSchemaException($diagnostic['message'], $diagnostic['path'], $diagnostic['code']);
        }
        $compatible = $this->compatiblePayload($payload, $compiled);
        $this->forms->validateDefinition($compatible);
        $databaseSchema = (string) $compatible['source_type'] === 'adopted'
            ? $this->crud->inspect((string) $compatible['connection'], (string) $compatible['table_name'])
            : [];
        $definition = $this->definitions->createFromSchema(
            $compiled,
            $compatible,
            (array) ($compatible['publish_config'] ?? []),
            $databaseSchema
        );
        $definition = $this->withDependencyHash($definition, (string) $dependencies['dependencyHash']);
        return [$compiled, $compatible, $definition, (string) $dependencies['dependencyHash']];
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

    private function compatiblePayload(array $payload, FormSchema $compiled): array
    {
        $document = $compiled->document();
        $database = (array) ($document['database'] ?? []);
        return array_replace($payload, [
            'expected_schema_hash' => trim((string) ($payload['schema_hash'] ?? '')),
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
        if (is_object($fields) && method_exists($fields, 'toArray')) $fields = $fields->toArray();
        $data['fields'] = array_map(
            static fn ($field): array => is_object($field) && method_exists($field, 'toArray') ? $field->toArray() : (array) $field,
            (array) $fields
        );
        return $data;
    }

    private function latestRetryableGenerationId(string $definitionHash, int $afterId): ?int
    {
        $record = CrudGeneration::where('operation', 'generate')
            ->where('definition_hash', $definitionHash)
            ->where('id', '>', $afterId)
            ->order('id', 'desc')
            ->find();
        return $record ? (int) $record->id : null;
    }

    private function isConflict(Throwable $exception): bool
    {
        return preg_match('/(?:token|冲突|hash|计划|已变化)/i', $exception->getMessage()) === 1;
    }

    private function updateStatus(int $formId, string $status, array $extra = []): void
    {
        if ($formId > 0) Form::where('id', $formId)->update(['publish_status' => $status] + $extra);
    }
}
