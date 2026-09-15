<?php

declare(strict_types=1);

namespace app\admin\form\model;

use app\admin\model\BackendModel;

/** 表单完整发布的幂等阶段记录。 */
final class FormPublishAttempt extends BackendModel
{
    protected string $name = 'form_publish_attempt';
    protected array $json = ['result', 'error'];
    protected bool $jsonAssoc = true;
}
