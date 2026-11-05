<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use Attribute;

/** 将插件控制器或方法绑定到 Manifest 中注册的权限码。 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class PluginPermission
{
    public function __construct(public string $code)
    {
    }
}
