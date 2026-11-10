<?php

declare(strict_types=1);

namespace app\admin\form\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

/** 表单完整发布的幂等阶段记录。 */
final class FormPublishAttempt extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    protected string $name = 'form_publish_attempt';
    protected array $json = ['result', 'error'];
    protected bool $jsonAssoc = true;
}
