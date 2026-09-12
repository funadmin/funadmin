<?php

declare(strict_types=1);

namespace app\console\authorization\model;

use app\console\model\BackendModel;

/**
 * 可授权字段目录，同时作为服务端字段白名单。
 */
final class PermissionField extends BackendModel
{
    protected string $name = 'permission_field';
}
