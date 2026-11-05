<?php

declare(strict_types=1);

namespace app\admin\form\model;

use app\common\model\BaseModel;

/** 表单完整发布的幂等阶段记录。 */
final class FormPublishAttempt extends BaseModel
{
    protected string $name = 'form_publish_attempt';
    protected array $json = ['result', 'error'];
    protected bool $jsonAssoc = true;
}
