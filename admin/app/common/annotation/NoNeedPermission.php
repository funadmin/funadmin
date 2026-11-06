<?php

declare(strict_types=1);

namespace app\common\annotation;

use Attribute;

/**
 * 标记后台中"登录即可、不校验权限码"的控制器或方法（如个人资料、页面目录）。
 *
 * 只影响权限判断，仍要求登录；方法注解优先于类注解。
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class NoNeedPermission
{
}
