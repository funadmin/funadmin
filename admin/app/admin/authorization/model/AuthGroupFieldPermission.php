<?php

declare(strict_types=1);

namespace app\admin\authorization\model;

use app\common\model\BaseModel;

/**
 * 角色直接字段授权；继承授权在查询时合并。
 */
final class AuthGroupFieldPermission extends BaseModel
{
    protected string $name = 'auth_group_field_permission';
}
