<?php

declare(strict_types=1);

namespace app\admin\authorization\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

/**
 * 可授权字段目录，同时作为服务端字段白名单。
 */
final class PermissionField extends BaseModel
{
    use LaravelSoftDelete;

    protected string $name = 'permission_field';
}
