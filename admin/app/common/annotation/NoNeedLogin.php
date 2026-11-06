<?php

declare(strict_types=1);

namespace app\common\annotation;

use Attribute;

/**
 * 标记会员 API 中无需登录即可调用的控制器或方法。
 *
 * 未标注时默认要求登录。标注后 ApiMiddleware 直接放行；若请求仍带有效令牌，
 * 会照常注入 $request->member，便于"游客可看、登录后看到更多"的接口。
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class NoNeedLogin
{
}
