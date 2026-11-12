<?php

declare(strict_types=1);

namespace Tests\Support;

use Attribute;
use support\annotation\route\Get;

/** OpenApiCoverageTest 的样例：继承 Webman 路由属性的自定义属性（Webman 按 IS_INSTANCEOF 注册），检查器必须拒绝。 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class OpenApiCoverageCustomGetFixture extends Get
{
}
