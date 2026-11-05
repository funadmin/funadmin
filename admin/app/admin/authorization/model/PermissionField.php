<?php

declare(strict_types=1);

namespace app\admin\authorization\model;

use app\common\model\BaseModel;

/**
 * 可授权字段目录，同时作为服务端字段白名单。
 */
final class PermissionField extends BaseModel
{
    protected string $name = 'permission_field';
}
