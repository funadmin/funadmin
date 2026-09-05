<?php

namespace app\common\middleware;

use app\common\service\MemberAuthService;
use app\common\service\TokenService;
use Closure;
use think\Request;
use think\Response;

class MApi
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly MemberAuthService $memberAuthService
    ) {
    }

    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->member = [];
        $request->member_id = $request->mid = null;
        $authHeader = (string) $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
            return $this->fail(__('Unauthorized'), 401);
        }

        $tokenData = $this->tokenService->validateToken($matches[1]);
        $memberId = is_array($tokenData) ? (int) ($tokenData['id'] ?? 0) : 0;
        if ($memberId <= 0) {
            return $this->fail(__('Invalid token'), 401);
        }

        $member = $this->memberAuthService->activeMember($memberId);
        if (!$member) {
            return $this->fail(__('Invalid token'), 401);
        }

        $request->member = $member;
        $request->member_id = $request->mid = $memberId;

        return $next($request);
    }

    private function fail(string $message, int $code): Response
    {
        return json([
            'code' => $code,
            'msg' => $message,
            'time' => time(),
            'data' => null,
        ], $code);
    }

}
