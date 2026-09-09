<?php

declare(strict_types=1);

namespace app\console\model;

/**
 * 可授权字段目录，同时作为服务端字段白名单。
 */
final class PermissionField extends BackendModel
{
    protected $name = 'permission_field';
}
