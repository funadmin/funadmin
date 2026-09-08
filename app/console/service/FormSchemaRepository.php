<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\form\schema\FormSchema;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaMigrator;
use app\common\form\schema\FormSchemaValidator;
use app\console\model\Form;
use app\console\model\FormSchemaVersion;
use InvalidArgumentException;
use think\facade\Db;

final class FormSchemaRepository
{
    private FormSchemaCompiler $compiler;

    public function __construct(private readonly FormSchemaMigrator $migrator = new FormSchemaMigrator())
    {
        $this->compiler = new FormSchemaCompiler(new FormSchemaValidator());
    }

    public function compile(array $definition): FormSchema
    {
        $schema = (int) ($definition['schemaVersion'] ?? 0) === 2 ? $definition : $this->migrator->fromV1($definition);
        return $this->compiler->compile($schema);
    }

    public function saveVersion(int $formId, array $definition, string $origin, string $actor, string $summary = ''): FormSchemaVersion
    {
        $compiled = $this->compile($definition);
        return Db::transaction(function () use ($formId, $compiled, $origin, $actor, $summary): FormSchemaVersion {
            $form = Form::lock(true)->find($formId);
            if (!$form) throw new InvalidArgumentException('表单不存在');
            $existing = FormSchemaVersion::where('form_id', $formId)->where('schema_hash', $compiled->hash())->find();
            if ($existing) return $existing;
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
            $form->save([
                'schema_version' => $compiled->version(),
                'schema_document' => $compiled->document(),
                'schema_hash' => $compiled->hash(),
                'schema_origin' => $origin,
            ]);
            return $version;
        });
    }

    public function versions(int $formId): array
    {
        return FormSchemaVersion::where('form_id', $formId)->order('version', 'desc')->select()->toArray();
    }

    public function findVersion(int $formId, int $version): FormSchemaVersion
    {
        $record = FormSchemaVersion::where('form_id', $formId)->where('version', $version)->find();
        if (!$record) throw new InvalidArgumentException('表单版本不存在');
        return $record;
    }
}
