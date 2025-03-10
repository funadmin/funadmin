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
        $request->user = [];
        $request->user_id = null
        $controllerClass = '\\' . app()->getNamespace() . '\\controller\\' . str_replace('.', '\\', request()->controller());
        $reflectionClass = new \ReflectionClass($controllerClass);
        $noNeedRight = $reflectionClass->hasProperty('noNeedRight') ? $reflectionClass->getProperty('noNeedRight')->getValue($reflectionClass->newInstanceWithoutConstructor()) : [];
        $action = request()->action();
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            //如果是不需要验证权限的方法，并且token不存在
            if((!empty($noNeedRight) && in_array($action, $noNeedRight) || $noNeedRight==['*'])){
                 // 继续处理请求
                return $next($request);
            }
            $this->error(__('Unauthorized'), [], 401);        // 未认证
        }
        $token = $matches[1];
        // 验证 JWT
        $userData = $this->tokenService->validateToken($token);

        if (!$userData) {
            $this->error(__('Invalid token'), [], 401);
        }
        if($userData===true){
             // 继续处理请求
            return $next($request);
        }
        // 将解码后的用户信息存储在请求中
        $request->user = $userData;
        $request->user_id = $userData['id'];

        // 继续处理请求
        return $next($request);
    }

}
