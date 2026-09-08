<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\form\component\PluginFormComponentRegistry;
use app\common\form\schema\FormSchema;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaException;
use app\common\form\schema\FormSchemaMigrator;
use app\common\form\schema\FormSchemaValidator;
use app\console\model\Form;
use app\console\model\FormField;
use app\console\model\FormSchemaVersion;
use InvalidArgumentException;
use JsonException;
use think\facade\Db;

final class FormSchemaRepository
{
    private FormSchemaCompiler $compiler;

    public function __construct(
        private readonly FormSchemaMigrator $migrator = new FormSchemaMigrator(),
        private readonly PluginFormComponentRegistry $pluginComponents = new PluginFormComponentRegistry()
    ) {
        $this->compiler = new FormSchemaCompiler(new FormSchemaValidator($this->pluginComponents));
    }

    public function compile(array $definition): FormSchema
    {
        $schemaVersion = $definition['schemaVersion'] ?? null;
        if ($schemaVersion !== null && $schemaVersion !== 2) {
            throw new FormSchemaException('不支持的 FormSchema 版本', '/schemaVersion', 'FORM_SCHEMA_VERSION_UNSUPPORTED');
        }
        $schema = $schemaVersion === 2 ? $definition : $this->migrator->fromV1($definition);
        return $this->compiler->compile($schema);
    }

    /**
     * 返回 API 可直接序列化的规范编译结果。
     */
    public function compilePayload(array $definition): array
    {
        $compiled = $this->compile($definition);
        return [
            'document' => $compiled->document(),
            'hash' => $compiled->hash(),
            'projection' => $compiled->fieldProjection(),
        ];
    }

    /**
     * 导出稳定的 canonical JSON 文档。
     */
    public function export(array $definition): string
    {
        return $this->compile($definition)->canonicalJson();
    }

    /**
     * 从 JSON 导入并验证 FormSchema v2 文档。
     */
    public function import(string $json): FormSchema
    {
        try {
            $definition = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new FormSchemaException('FormSchema JSON 无效：' . $exception->getMessage(), '/', 'FORM_SCHEMA_IMPORT_INVALID');
        }
        if (!is_array($definition) || array_is_list($definition)) {
            throw new FormSchemaException('FormSchema JSON 必须为对象', '/', 'FORM_SCHEMA_IMPORT_INVALID');
        }
        return $this->compile($definition);
    }

    public function saveVersion(int $formId, array $definition, string $origin, string $actor, string $summary = ''): FormSchemaVersion
    {
        $compiled = $this->compile($definition);
        return Db::transaction(function () use ($formId, $compiled, $origin, $actor, $summary): FormSchemaVersion {
            $form = Form::lock(true)->find($formId);
            if (!$form) throw new InvalidArgumentException('表单不存在');
            $existing = FormSchemaVersion::where('form_id', $formId)->where('schema_hash', $compiled->hash())->find();
            if ($existing) {
                $this->persistCurrent($form, $compiled, $origin);
                return $existing;
            }
            $latest = FormSchemaVersion::where('form_id', $formId)->order('version', 'desc')->find();
            $version = new FormSchemaVersion();
            $version->save([
                'form_id' => $formId,
                'version' => (int) ($latest->version ?? 0) + 1,
                'schema_version' => $compiled->version(),
                'schema_hash' => $compiled->hash(),
                'schema_document' => $compiled->document(),
                'origin' => $origin,
                'parent_version_id' => $latest?->id,
                'change_summary' => $summary,
                'created_by' => $actor,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->persistCurrent($form, $compiled, $origin);
            return $version;
        });
    }

    public function versions(int $formId): array
    {
        if (!Form::find($formId)) {
            throw new FormSchemaException('表单不存在', '/formId', 'FORM_NOT_FOUND');
        }
        return FormSchemaVersion::where('form_id', $formId)->order('version', 'desc')->select()->toArray();
    }

    public function findVersion(int $formId, int $version): FormSchemaVersion
    {
        $record = FormSchemaVersion::where('form_id', $formId)->where('version', $version)->find();
        if (!$record) {
            throw new FormSchemaException('表单版本不存在', '/version', 'FORM_SCHEMA_VERSION_NOT_FOUND');
        }
        return $record;
    }

    /**
     * 比较同一表单的两个不可变历史版本。
     */
    public function diff(int $formId, int $fromVersion, int $toVersion): array
    {
        $from = $this->findVersion($formId, $fromVersion);
        $to = $this->findVersion($formId, $toVersion);
        $result = $this->diffDocuments((array) $from->schema_document, (array) $to->schema_document);
        return array_merge($result, ['fromVersion' => $fromVersion, 'toVersion' => $toVersion]);
    }

    /**
     * 比较两个规范文档，使用 JSON Pointer 标识变化路径。
     */
    public function diffDocuments(array $from, array $to): array
    {
        $compiledFrom = $this->compile($from);
        $compiledTo = $this->compile($to);
        $changes = [];
        $this->collectChanges($compiledFrom->document(), $compiledTo->document(), '', $changes);
        return [
            'fromHash' => $compiledFrom->hash(),
            'toHash' => $compiledTo->hash(),
            'changes' => $changes,
        ];
    }

    /**
     * 以历史文档为基础创建全新的不可变版本，不修改或复用历史记录。
     */
    public function rollback(int $formId, int $version, string $actor, string $summary = ''): FormSchemaVersion
    {
        $source = $this->findVersion($formId, $version);
        $document = (array) $source->schema_document;
        $extensions = is_array($document['extensions'] ?? null) ? $document['extensions'] : [];
        $extensions['rollback'] = [
            'rolledBackFromVersion' => $version,
            'rolledBackAt' => date(DATE_ATOM),
            'rollbackId' => bin2hex(random_bytes(16)),
            'actor' => $actor,
        ];
        $document['extensions'] = $extensions;
        $message = $summary !== '' ? $summary : '回滚至版本 ' . $version;
        return $this->saveVersion($formId, $document, 'rollback', $actor, $message);
    }

    /**
     * 返回服务端支持的 FormSchema v2 组件目录。
     */
    public function componentCatalog(): array
    {
        return [
            'schemaVersion' => 2,
            'components' => array_merge(
                array_map(
                    static fn (string $type): array => [
                        'type' => $type,
                        'namespace' => 'core',
                        'component' => $type,
                        'kind' => in_array($type, ['group', 'grid', 'divider', 'text', 'collapse', 'tabs'], true) ? 'layout' : 'field',
                        'valueType' => 'mixed',
                        'defaultValue' => null,
                        'defaultProps' => [],
                        'propertySchema' => ['type' => 'object', 'properties' => []],
                        'codec' => 'core:identity',
                        'allowedAttrs' => [],
                        'allowedEvents' => ['change', 'blur', 'focus'],
                        'renderer' => 'core:registry',
                    ],
                    FormSchemaValidator::COMPONENTS
                ),
                array_map(
                    fn (array $definition): array => $this->catalogPluginComponent($definition),
                    $this->pluginComponents->catalog()
                )
            ),
        ];
    }

    /** 补齐可信 manifest 的前端渲染契约，不向客户端暴露任意模块路径。 */
    private function catalogPluginComponent(array $definition): array
    {
        $properties = (array) ($definition['propertySchema']['properties'] ?? []);
        return array_replace([
            'kind' => 'field',
            'valueType' => 'mixed',
            'defaultValue' => null,
            'defaultProps' => [],
            'propertySchema' => ['type' => 'object', 'properties' => $properties],
            'codec' => '',
            'allowedAttrs' => [],
            'allowedEvents' => [],
            'renderer' => 'plugin:module',
        ], $definition);
    }

    private function persistCurrent(Form $form, FormSchema $compiled, string $origin): void
    {
        $form->save([
            'schema_version' => $compiled->version(),
            'schema_document' => $compiled->document(),
            'schema_hash' => $compiled->hash(),
            'schema_origin' => $origin,
            'publish_status' => (string) ($form->published_schema_hash ?? '') === $compiled->hash() ? 'published' : 'draft',
        ]);
        FormField::where('form_id', (int) $form->id)->delete();
        foreach ($compiled->fieldProjection() as $sort => $field) {
            (new FormField())->save($this->projectionRow((int) $form->id, $field, $sort));
        }
    }

    private function projectionRow(int $formId, array $field, int $sort): array
    {
        return array_replace([
            'form_id' => $formId, 'field_name' => '', 'label' => '', 'type' => 'input', 'column_type' => '',
            'nullable' => 1, 'default_value' => '', 'comment' => '', 'unsigned' => 0, 'index_type' => 'none',
            'placeholder' => '', 'options_source' => null, 'control_props' => null, 'validate_rules' => null,
            'link_rules' => null, 'relation_type' => 'none', 'relation_table' => '', 'relation_label_field' => '',
            'relation_value_field' => 'id', 'relation_multiple' => 0, 'relation_on_delete' => 'restrict',
            'list_show' => 0, 'list_sort' => 0, 'list_filter' => '', 'list_formatter' => '', 'list_width' => 0,
            'form_show' => 1, 'form_required' => 0, 'form_group' => '', 'form_span' => 24,
            'form_readonly' => 0, 'sort_order' => $sort,
        ], array_intersect_key($field, array_flip([
            'field_name', 'label', 'type', 'column_type', 'nullable', 'default_value', 'comment', 'unsigned',
            'index_type', 'placeholder', 'options_source', 'control_props', 'validate_rules', 'link_rules',
            'relation_type', 'relation_table', 'relation_label_field', 'relation_value_field', 'relation_multiple',
            'relation_on_delete', 'list_show', 'list_sort', 'list_filter', 'list_formatter', 'list_width', 'form_show',
            'form_required', 'form_group', 'form_span', 'form_readonly', 'sort_order',
        ])));
    }

    private function collectChanges(mixed $from, mixed $to, string $path, array &$changes): void
    {
        if (!is_array($from) || !is_array($to)) {
            if ($from !== $to) {
                $changes[] = ['op' => 'replace', 'path' => $path ?: '/', 'from' => $from, 'value' => $to];
            }
            return;
        }
        foreach ($from as $key => $value) {
            $childPath = $path . '/' . $this->escapePointer((string) $key);
            if (!array_key_exists($key, $to)) {
                $changes[] = ['op' => 'remove', 'path' => $childPath, 'from' => $value];
                continue;
            }
            $this->collectChanges($value, $to[$key], $childPath, $changes);
        }
        foreach ($to as $key => $value) {
            if (array_key_exists($key, $from)) {
                continue;
            }
            $changes[] = ['op' => 'add', 'path' => $path . '/' . $this->escapePointer((string) $key), 'value' => $value];
        }
    }

    private function escapePointer(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
