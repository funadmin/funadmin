<?php

declare(strict_types=1);

namespace app\admin\form\service;

use app\common\form\builder\Form;
use app\admin\form\model\FormSchemaVersion;
use app\admin\form\repository\FormSchemaRepository;

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
        return $this->schemas->saveVersion($formId, $builder->compile()->document(), 'php', $actor, $message);
    }
}
