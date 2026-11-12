<?php

namespace app\admin\authentication\controller;

use OpenApi\Annotations as OA;

use app\admin\authorization\service\AuthorizationService;
use app\admin\authentication\service\AdminSessionService;
use app\admin\authentication\service\BrowserAuthService;
use app\admin\authentication\service\AdminLoginPath;
use DomainException;
use support\Request;
use support\Response;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;

/**
 * Admin Web 认证 HTTP 适配层。
 */
#[RouteGroup('/admin/{entry:[a-z][a-z0-9-]{3,31}}/auth')]
final class AdminAuth
{
    /**
     * 建立或读取浏览器会话，返回 CSRF 令牌并设置会话 Cookie。
     *
     * @param Request $request 当前 HTTP 请求，包含会话、请求头及提交参数。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/{entry}/auth/csrf",
     *     operationId="admin_authentication_controller_AdminAuth_csrf_get",
     *     tags={"后台浏览器认证"},
     *     summary="建立或读取浏览器会话，返回 CSRF 令牌并设置会话 Cookie。",
     *     description="浏览器认证协议：code 与 HTTP 状态一致。",
     *     security={},
     *     @OA\Parameter(name="entry", in="path", required=true, description="后台登录入口路径段，由系统配置决定，不匹配当前入口时返回 404。", @OA\Schema(type="string", pattern="^[a-z][a-z0-9-]{3,31}$")),
     *     @OA\Response(
     *         response=200,
     *         description="业务响应；以响应体 code 判断成功与失败。",
     *         @OA\JsonContent(ref="#/components/schemas/AdminAuthCsrfResult"),
     *         @OA\Header(header="Set-Cookie", description="后台会话 Cookie，浏览器保存后供后续请求使用。", @OA\Schema(type="string")),
     *         @OA\Header(header="X-CSRF-TOKEN", description="后续写请求使用的 CSRF 令牌。", @OA\Schema(type="string"))
     *     ),
     *     @OA\Response(response="default", ref="#/components/responses/BrowserAuthError")
     * )
     */
    #[Get('/csrf')]
    public function csrf(Request $request, ?string $entry = null): Response
    {
        $this->assertEntry($entry);
        $session = (new BrowserAuthService())->bootstrap($this->cookie($request));
        return $this->attachCookie($this->result(['csrfToken' => $session['csrfToken'], 'captchaEnabled' => false]), $session);
    }

    /**
     * 验证账号、密码和 CSRF 令牌后建立后台浏览器登录会话。
     *
     * 请求字段：username、password。
     *
     * @param Request $request 当前 HTTP 请求，包含会话、请求头及提交参数。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/{entry}/auth/login",
     *     operationId="admin_authentication_controller_AdminAuth_login_post",
     *     tags={"后台浏览器认证"},
     *     summary="验证账号、密码和 CSRF 令牌后建立后台浏览器登录会话。",
     *     description="浏览器认证协议：code 与 HTTP 状态一致。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="entry", in="path", required=true, description="后台登录入口路径段，由系统配置决定，不匹配当前入口时返回 404。", @OA\Schema(type="string", pattern="^[a-z][a-z0-9-]{3,31}$")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(allOf={@OA\Schema(ref="#/components/schemas/LoginInput"), @OA\Schema(type="object", required={"username", "password"})})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="业务响应；以响应体 code 判断成功与失败。",
     *         @OA\JsonContent(ref="#/components/schemas/AdminAuthLoginResult"),
     *         @OA\Header(header="Set-Cookie", description="后台会话 Cookie，浏览器保存后供后续请求使用。", @OA\Schema(type="string")),
     *         @OA\Header(header="X-CSRF-TOKEN", description="后续写请求使用的 CSRF 令牌。", @OA\Schema(type="string"))
     *     ),
     *     @OA\Response(response=403, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response=422, description="认证输入校验失败。", @OA\JsonContent(ref="#/components/schemas/BrowserAuthEnvelope")),
     *     @OA\Response(response="default", ref="#/components/responses/BrowserAuthError")
     * )
     */
    #[Post('/login')]
    public function login(Request $request, ?string $entry = null): Response
    {
        try {
            $this->assertEntry($entry);
            $username = $request->post('username');
            $password = $request->post('password');
            if (!is_string($username) || !is_string($password)) {
                throw new DomainException('账号和密码必须为字符串', 422);
            }
            $auth = new BrowserAuthService();
            $session = $auth->login($this->cookie($request), $request->header('x-csrf-token', ''), $username, $password, $request->getRemoteIp());
            return $this->attachCookie($this->result(['authenticated' => true], '登录成功'), $session);
        } catch (DomainException $error) {
            return $this->result(null, $error->getMessage(), $error->getCode());
        }
    }

    /**
     * 根据浏览器会话获取当前管理员及其权限信息。
     *
     * @param Request $request 当前 HTTP 请求，包含会话、请求头及提交参数。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/{entry}/auth/me",
     *     operationId="admin_authentication_controller_AdminAuth_me_get",
     *     tags={"后台浏览器认证"},
     *     summary="根据浏览器会话获取当前管理员及其权限信息。",
     *     description="浏览器认证协议：code 与 HTTP 状态一致。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="entry", in="path", required=true, description="后台登录入口路径段，由系统配置决定，不匹配当前入口时返回 404。", @OA\Schema(type="string", pattern="^[a-z][a-z0-9-]{3,31}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/BrowserAuthEnvelope")),
     *     @OA\Response(response=401, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response=403, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response="default", ref="#/components/responses/BrowserAuthError")
     * )
     */
    #[Get('/me')]
    public function me(Request $request, ?string $entry = null): Response
    {
        try {
            $this->assertEntry($entry);
            $admin = (new BrowserAuthService())->user($this->cookie($request));
            return $this->result((new AuthorizationService())->userInfo($admin));
        } catch (DomainException $error) {
            return $this->result(null, $error->getMessage(), $error->getCode());
        }
    }

    /**
     * 根据浏览器会话获取当前管理员可访问的菜单。
     *
     * @param Request $request 当前 HTTP 请求，包含会话、请求头及提交参数。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/{entry}/auth/menus",
     *     operationId="admin_authentication_controller_AdminAuth_menus_get",
     *     tags={"后台浏览器认证"},
     *     summary="根据浏览器会话获取当前管理员可访问的菜单。",
     *     description="浏览器认证协议：code 与 HTTP 状态一致。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="entry", in="path", required=true, description="后台登录入口路径段，由系统配置决定，不匹配当前入口时返回 404。", @OA\Schema(type="string", pattern="^[a-z][a-z0-9-]{3,31}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/BrowserAuthEnvelope")),
     *     @OA\Response(response=401, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response=403, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response="default", ref="#/components/responses/BrowserAuthError")
     * )
     */
    #[Get('/menus')]
    public function menus(Request $request, ?string $entry = null): Response
    {
        try {
            $this->assertEntry($entry);
            $admin = (new BrowserAuthService())->user($this->cookie($request));
            return $this->result((new AuthorizationService())->menus($admin));
        } catch (DomainException $error) {
            return $this->result(null, $error->getMessage(), $error->getCode());
        }
    }

    /**
     * 验证 CSRF 令牌后注销浏览器会话，并清除登录 Cookie。
     *
     * @param Request $request 当前 HTTP 请求，包含会话、请求头及提交参数。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/{entry}/auth/logout",
     *     operationId="admin_authentication_controller_AdminAuth_logout_post",
     *     tags={"后台浏览器认证"},
     *     summary="验证 CSRF 令牌后注销浏览器会话，并清除登录 Cookie。",
     *     description="浏览器认证协议：code 与 HTTP 状态一致。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="entry", in="path", required=true, description="后台登录入口路径段，由系统配置决定，不匹配当前入口时返回 404。", @OA\Schema(type="string", pattern="^[a-z][a-z0-9-]{3,31}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminAuthLogoutResult")),
     *     @OA\Response(response=401, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response=403, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response="default", ref="#/components/responses/BrowserAuthError")
     * )
     */
    #[Post('/logout')]
    public function logout(Request $request, ?string $entry = null): Response
    {
        try {
            $this->assertEntry($entry);
            (new BrowserAuthService())->logout($this->cookie($request), $request->header('x-csrf-token', ''));
            return $this->result(null, '退出成功')->cookie(BrowserAuthService::COOKIE, '', 0, '/admin', '', $this->secure(), true, 'Lax');
        } catch (DomainException $error) {
            return $this->result(null, $error->getMessage(), $error->getCode());
        }
    }

    private function assertEntry(?string $entry): void
    {
        $entry = $entry === null || $entry === '' ? AdminLoginPath::DEFAULT : strtolower($entry);
        if (!hash_equals(AdminLoginPath::current(), $entry)) {
            throw new DomainException('接口不存在', 404);
        }
    }

    private function cookie(Request $request): string
    {
        return (string) $request->cookie(BrowserAuthService::COOKIE, '');
    }

    private function secure(): bool
    {
        return filter_var(getenv('COOKIE_SECURE') ?: 'false', FILTER_VALIDATE_BOOL);
    }

    private function attachCookie(Response $response, array $session): Response
    {
        return $response->cookie(BrowserAuthService::COOKIE, $session['cookie'], null, '/admin', '', $this->secure(), true, 'Lax')
            ->withHeader('X-CSRF-TOKEN', $session['csrfToken']);
    }

    private function result(mixed $data, string $msg = '成功', int $status = 200): Response
    {
        return json(['code' => $status, 'msg' => $msg, 'data' => $data])->withStatus($status)
            ->withHeaders(['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
