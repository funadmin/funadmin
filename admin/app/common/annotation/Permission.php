<?php

declare(strict_types=1);

namespace app\common\annotation;

use Attribute;

/**
 * 声明访问控制器或方法所需的权限码；传数组表示满足任一即可。
 *
 * 后台可选：未标注时按控制器/方法推导资源并查询权限表。插件后台控制器必须声明，未声明一律拒绝。
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Permission
{
    public function __construct(public string|array $code)
    {
    }
}
