<?php

declare(strict_types=1);

namespace app\admin\authentication\controller;

use OpenApi\Annotations as OA;

use app\admin\authentication\service\AdminSessionService;
use DomainException;
use support\Request;
use support\Response;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;

/**
 * 管理员令牌认证接口。
 */
#[RouteGroup('/api/auth')]
final class ApiAuth
{
    /**
     * 验证管理员账号和密码，返回令牌登录信息。
     *
     * 请求字段：username、password。
     *
     * @param Request $request 当前 HTTP 请求，包含会话、请求头及提交参数。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/api/auth/login",
     *     operationId="admin_authentication_controller_ApiAuth_login_post",
     *     tags={"管理员令牌认证"},
     *     summary="验证管理员账号和密码，返回令牌登录信息。",
     *     description="管理员令牌认证协议：成功 code=0，错误 code 与 HTTP 状态一致。",
     *     security={},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(allOf={@OA\Schema(ref="#/components/schemas/LoginInput"), @OA\Schema(type="object", required={"username", "password"})})
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/ApiAuthLoginResult")),
     *     @OA\Response(response=422, description="认证输入校验失败。", @OA\JsonContent(ref="#/components/schemas/TokenAuthEnvelope")),
     *     @OA\Response(response="default", ref="#/components/responses/TokenAuthError")
     * )
     */
    #[Post('/login')]
    public function login(Request $request): Response
    {
        $username = $request->post('username');
        $password = $request->post('password');
        if (!is_string($username) || !is_string($password)) {
            return $this->result(422, '账号和密码必须为字符串');
        }
        try {
            return $this->result(200, '登录成功', (new AdminSessionService())->login($username, $password, $request->getRemoteIp()));
        } catch (DomainException $error) {
            return $this->result($error->getCode(), $error->getMessage());
        }
    }

    /**
     * 验证请求令牌，返回当前管理员身份信息。
     *
     * @param Request $request 当前 HTTP 请求，包含会话、请求头及提交参数。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/api/auth/me",
     *     operationId="admin_authentication_controller_ApiAuth_me_get",
     *     tags={"管理员令牌认证"},
     *     summary="验证请求令牌，返回当前管理员身份信息。",
     *     description="管理员令牌认证协议：成功 code=0，错误 code 与 HTTP 状态一致。",
     *     security={{"AdminBearer"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/TokenAuthEnvelope")),
     *     @OA\Response(response=401, ref="#/components/responses/TokenAuthError"),
     *     @OA\Response(response=403, ref="#/components/responses/TokenAuthError"),
     *     @OA\Response(response="default", ref="#/components/responses/TokenAuthError")
     * )
     */
    #[Get('/me')]
    public function me(Request $request): Response
    {
        try {
            return $this->result(200, '成功', (new AdminSessionService())->authenticate($this->token($request)));
        } catch (DomainException $error) {
            return $this->result($error->getCode(), $error->getMessage());
        }
    }

    /**
     * 验证并注销当前请求携带的管理员令牌。
     *
     * @param Request $request 当前 HTTP 请求，包含会话、请求头及提交参数。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/api/auth/logout",
     *     operationId="admin_authentication_controller_ApiAuth_logout_post",
     *     tags={"管理员令牌认证"},
     *     summary="验证并注销当前请求携带的管理员令牌。",
     *     description="管理员令牌认证协议：成功 code=0，错误 code 与 HTTP 状态一致。",
     *     security={{"AdminBearer"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/ApiAuthLogoutResult")),
     *     @OA\Response(response=401, ref="#/components/responses/TokenAuthError"),
     *     @OA\Response(response=403, ref="#/components/responses/TokenAuthError"),
     *     @OA\Response(response="default", ref="#/components/responses/TokenAuthError")
     * )
     */
    #[Post('/logout')]
    public function logout(Request $request): Response
    {
        try {
            $service = new AdminSessionService();
            $token = $this->token($request);
            $service->authenticate($token);
            $service->logout($token);
            return $this->result(200, '已退出登录');
        } catch (DomainException $error) {
            return $this->result($error->getCode(), $error->getMessage());
        }
    }

    private function token(Request $request): string
    {
        $header = $request->header('authorization', '');
        return preg_match('/\ABearer ([a-f0-9]{64})\z/i', $header, $matches) ? $matches[1] : '';
    }

    private function result(int $status, string $message, mixed $data = null): Response
    {
        return json(['code' => $status === 200 ? 0 : $status, 'message' => $message, 'data' => $data])
            ->withStatus($status)->withHeaders(['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
