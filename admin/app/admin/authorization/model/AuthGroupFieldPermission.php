<?php

declare(strict_types=1);

namespace app\admin\authorization\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

/**
 * 角色直接字段授权；继承授权在查询时合并。
 */
final class AuthGroupFieldPermission extends BaseModel
{
    use LaravelSoftDelete;

    protected string $name = 'auth_group_field_permission';
}
