<?php

declare(strict_types=1);

namespace app\common\controller;

use app\BaseController;
use app\common\middleware\ApiMiddleware;
use app\common\traits\JsonResponse;
use support\annotation\Middleware;

/**
 * 前台会员 API 基类：统一挂载 ApiMiddleware。
 *
 * 默认所有方法都需要会员登录；无需登录的方法（或整个控制器）标注
 * #[\app\common\annotation\NoNeedLogin]。登录后可读取 $request->member。
 */
#[Middleware(ApiMiddleware::class)]
abstract class ApiController extends BaseController
{
    use JsonResponse;
}
