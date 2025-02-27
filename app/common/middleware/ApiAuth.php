<?php

namespace app\common\middleware;

use app\common\service\TokenService;
use app\common\traits\Apis;
use Closure;
use think\Request;
use think\Response;

class ApiAuth
{
    use Apis;

    public TokenService $tokenService;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
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
    
        // 获取 Authorization 头
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $this->error(__('Unauthorized'), [], 401);
        }
        $token = $matches[1];

        // 验证 JWT
        $userData = $this->tokenService->validateToken($token);

        if (!$userData) {
            $this->error(__('Invalid token'), [], 401);
        }
        // 将解码后的用户信息存储在请求中
        $request->user = $userData;

        // 继续处理请求
        return $next($request);
    }

}
