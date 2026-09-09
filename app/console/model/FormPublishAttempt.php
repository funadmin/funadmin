<?php

declare(strict_types=1);

namespace app\console\model;

/** 表单完整发布的幂等阶段记录。 */
final class FormPublishAttempt extends BackendModel
{
    protected string $name = 'form_publish_attempt';
    protected array $json = ['result', 'error'];
    protected bool $jsonAssoc = true;
}
