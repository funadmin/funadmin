<?php

declare(strict_types=1);

namespace app\admin\authorization\model;

use app\admin\model\BackendModel;

/**
 * 可授权字段目录，同时作为服务端字段白名单。
 */
final class PermissionField extends BackendModel
{
    protected string $name = 'permission_field';
}
