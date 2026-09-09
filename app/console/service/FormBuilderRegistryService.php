<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\form\schema\FormSchema;
use app\console\model\Form as FormModel;
use app\console\model\FormSchemaVersion;
use fun\form\Form;

/**
 * PHP Builder 注册入口：将 Builder 当前 FormSchema 保存为不可变版本。
 */
final class FormBuilderRegistryService
{
    public function __construct(private readonly FormSchemaRepository $schemas = new FormSchemaRepository())
    {
    }

    /**
     * 注册 Builder 当前 Schema，不反向生成或执行 PHP 代码。
     */
    public function register(
        int $formId,
        Form $builder,
        string $actor = 'php-builder',
        string $summary = ''
    ): FormSchemaVersion {
        $message = $summary !== '' ? $summary : 'PHP Builder 注册';
        $compiled = $builder->compile();
        $version = $this->schemas->saveVersion($formId, $compiled->document(), 'php', $actor, $message);
        $this->compatiblePayload($formId, $compiled);
        return $version;
    }

    /** 将 canonical 文档同步为发布服务可直接消费的 v1 兼容元数据。 */
    private function compatiblePayload(int $formId, FormSchema $compiled): void
    {
        $document = $compiled->document();
        $database = (array) ($document['database'] ?? []);
        $form = FormModel::find($formId);
        if (!$form) {
            return;
        }
        $form->save([
            'form_key' => $compiled->key(),
            'name' => (string) ($document['title'] ?? ''),
            'table_name' => (string) ($database['table'] ?? ''),
            'connection' => (string) ($database['connection'] ?? 'mysql'),
            'source_type' => (string) ($database['source'] ?? 'created'),
            'form_config' => (array) ($document['form'] ?? []),
            'list_config' => (array) ($document['list'] ?? []),
        ]);
    }
}
