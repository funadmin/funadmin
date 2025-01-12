<?php

namespace app\common\middleware;

use app\common\service\JwtService;
use app\common\traits\ApiTraits;
use Closure;
use think\Request;
use think\Response;

class ApiAuth
{
    use ApiTraits;

    public $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next)
    {
        // 获取请求的 URL 或其他标识符
        $url = $request->url();

        // 检查是否需要鉴权
        // 获取 Authorization 头
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $this->error(__('Unauthorized'), [], 401);
        }
        $token = $matches[1];

        // 验证 JWT
        $userData = $this->jwtService->validateToken($token);

        if (!$userData) {
            $this->error(__('Invalid token'), [], 401);
        }

        // 将解码后的用户信息存储在请求中
        $request->user = $userData;

        // 继续处理请求
        return $next($request);
    }

}
