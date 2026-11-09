<?php

declare(strict_types=1);

namespace app\common\middleware;

use app\common\auth\AuthRequirementResolver;
use app\common\service\authentication\BearerTokenExtractor;
use app\common\service\member\MemberApiSessionService;
use app\common\traits\JsonResponse;
use Webman\Http\Request;
use support\Response;
use Webman\MiddlewareInterface;

/**
 * 会员 API 鉴权：默认要求登录，控制器或方法标注 #[NoNeedLogin] 时放行。
 *
 * 登录校验：access token 签名有效、所属会话未吊销、会员仍启用；
 * 通过后注入 $request->member、$request->member_id、$request->member_session_id。
 */
class ApiMiddleware implements MiddlewareInterface
{
    use JsonResponse;

    public function __construct(
        private readonly MemberApiSessionService $sessions,
        private readonly BearerTokenExtractor $bearerTokenExtractor,
        private readonly AuthRequirementResolver $requirements = new AuthRequirementResolver()
    ) {
    }

    /**
     * 已完成会员鉴权的请求（路由组与控制器同时挂载时只执行一次）。
     * 用弱引用表记录请求对象本身，外部无法通过写入上下文键伪造"已鉴权"。
     */
    private static ?\WeakMap $handled = null;

    public function process(Request $request, callable $next): Response
    {
        self::$handled ??= new \WeakMap();
        if (isset(self::$handled[$request])) {
            return $next($request);
        }
        $public = !$this->requirements->for((string) $request->controller, (string) $request->action)->login;
        $token = $this->bearerTokenExtractor->extract($request);
        $member = $token === null ? null : $this->sessions->authenticate($token);

        if (!$public) {
            if ($token === null) {
                return $this->fail(trans('Unauthorized'), code: 401);
            }
            if ($member === null) {
                return $this->fail(trans('Invalid token'), code: 401);
            }
        }

        self::$handled[$request] = true;
        if ($member !== null) {
            $request->member = $member;
            $request->member_id = (int) $member['id'];
            $request->member_session_id = $member['session_id'];
        }

        return $next($request);
    }
}
