<?php

declare(strict_types=1);

namespace app\console\authorization\model;

use app\console\model\BackendModel;

/**
 * 角色直接字段授权；继承授权在查询时合并。
 */
final class AuthGroupFieldPermission extends BackendModel
{
    protected string $name = 'auth_group_field_permission';
}
