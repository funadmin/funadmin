<?php

namespace app\common\middleware;

use app\common\service\BearerTokenExtractor;
use app\common\service\MemberAuthService;
use app\common\service\TokenService;
use app\common\traits\JsonResponse;
use Closure;
use Webman\Http\Request;
use support\Response;
use Webman\MiddlewareInterface;

class MApi implements MiddlewareInterface
{
    use JsonResponse;

    public function __construct(
        private readonly TokenService $tokenService,
        private readonly MemberAuthService $memberAuthService,
        private readonly BearerTokenExtractor $bearerTokenExtractor
    ) {
    }

    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function process(Request $request, callable $next): Response
    {
        $token = $this->bearerTokenExtractor->extract($request);
        if ($token === null) {
            return $this->fail('Unauthorized', code: 401);
        }

        $tokenData = $this->tokenService->validateToken($token);
        $memberId = is_array($tokenData) ? (int) ($tokenData['id'] ?? 0) : 0;
        if ($memberId <= 0) {
            return $this->fail('Invalid token', code: 401);
        }

        $member = $this->memberAuthService->activeMember($memberId);
        if (!$member) {
            return $this->fail('Invalid token', code: 401);
        }

        $request->member = $member;
        $request->member_id = $memberId;

        return $next($request);
    }

}
