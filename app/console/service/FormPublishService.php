<?php

declare(strict_types=1);

namespace app\console\service;

use app\console\model\Form;
use InvalidArgumentException;
use Throwable;

/** 编排表单元数据、增量 DDL、全栈代码与菜单权限的一次发布。 */
final class FormPublishService
{
    private readonly FormCrudDefinitionFactory $definitions;
    private readonly DevCrudService $crud;

    public function __construct(
        private readonly FormDesignerService $forms,
        string $projectRoot,
        array $allowedConnections,
        ?FormCrudDefinitionFactory $definitions = null,
        ?DevCrudService $crud = null
    ) {
        $this->definitions = $definitions ?? new FormCrudDefinitionFactory();
        $this->crud = $crud ?? new DevCrudService($projectRoot, $allowedConnections);
    }

    public function preview(array $payload, bool $canGenerate): array
    {
        $this->forms->validateDefinition($payload);
        $definition = $this->definitions->create(
            $payload,
            (array) ($payload['publish_config'] ?? []),
            $this->schema($payload)
        );
        $ddl = $this->forms->previewMigration($payload);
        $crud = $this->crud->preview($definition->toArray(), $canGenerate, $canGenerate);
        $files = (array) ($crud['plan']['files'] ?? []);
        $conflicts = array_values(array_filter($files, static fn (array $file): bool => ($file['status'] ?? '') === 'conflict'));
        return [
            'definition' => $definition->toArray(),
            'definitionHash' => $definition->hash(),
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
        $crudDefinition = $this->definitions->create(
            $payload,
            (array) ($payload['publish_config'] ?? []),
            $this->schema($payload)
        );
        try {
            $this->crud->preflightGeneration(
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

        $saved = $this->forms->save($payload);
        $definitionPayload = $this->definitionPayload($saved);
        $formId = (int) ($definitionPayload['id'] ?? 0);
        $this->updateStatus($formId, 'publishing');
        $ddl = null;
        try {
            $ddl = $this->forms->applyMigration($definitionPayload);
            $generated = $this->crud->generate(
                $crudDefinition->toArray(),
                $confirmToken,
                $allowOverwrite,
                $canOverwrite,
                $operator,
                true,
                $canApplyResources
            );
            $resourceStatus = (string) ($generated['resourceApplyStatus'] ?? 'failed');
            $status = $resourceStatus === 'applied' ? 'published' : 'partial';
            $this->updateStatus($formId, $status, [
                'crud_generation_id' => (int) ($generated['generationId'] ?? 0) ?: null,
                'published_definition_hash' => $crudDefinition->hash(),
                'published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null,
            ]);
            return [
                'form' => $this->forms->detail($formId)['form'],
                'ddl' => $ddl,
                'generation' => $generated,
                'publishStatus' => $status,
                'routePath' => (string) $crudDefinition->get('routePath'),
            ];
        } catch (Throwable $exception) {
            $this->updateStatus($formId, $this->isConflict($exception) ? 'conflict' : 'failed');
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

    public function retryResources(int $formId): array
    {
        $status = $this->status($formId);
        $generationId = (int) ($status['generationId'] ?? 0);
        if ($generationId < 1) throw new InvalidArgumentException('表单没有可重试的生成记录');
        $result = $this->crud->applyResources($generationId);
        if (($result['resourceApplyStatus'] ?? '') === 'applied') {
            $this->updateStatus($formId, 'published', ['published_at' => date('Y-m-d H:i:s')]);
        }
        return $result + ['publishStatus' => ($result['resourceApplyStatus'] ?? '') === 'applied' ? 'published' : 'partial'];
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
