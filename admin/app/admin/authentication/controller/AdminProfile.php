<?php

declare(strict_types=1);

namespace app\admin\authentication\controller;

use OpenApi\Annotations as OA;

use app\admin\authentication\model\Admin;
use app\admin\authentication\service\AdminSessionService;
use app\admin\authentication\service\BrowserAuthService;
use DomainException;
use support\Db;
use support\Request;
use support\Response;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;

/** 当前登录管理员的个人资料与密码，不接受请求指定其他管理员。 */
#[RouteGroup('/admin/profile')]
final class AdminProfile
{
    /**
     * 读取当前浏览器会话对应的管理员个人资料。
     *
     * @param Request $request 当前 HTTP 请求，包含会话、请求头及提交参数。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/profile",
     *     operationId="admin_authentication_controller_AdminProfile_index_get",
     *     tags={"管理员个人资料"},
     *     summary="读取当前浏览器会话对应的管理员个人资料。",
     *     description="浏览器认证协议：code 与 HTTP 状态一致。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminProfileIndexResult")),
     *     @OA\Response(response=401, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response=403, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response="default", ref="#/components/responses/BrowserAuthError")
     * )
     */
    #[Get('')]
    public function index(Request $request): Response
    {
        return $this->execute($request, static fn (Admin $admin): array => $admin->toProfileData());
    }

    /**
     * 更新当前管理员的昵称、头像、邮箱及手机号。
     *
     * @param Request $request 当前 HTTP 请求，包含会话、请求头及提交参数。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/profile",
     *     operationId="admin_authentication_controller_AdminProfile_update_put",
     *     tags={"管理员个人资料"},
     *     summary="更新当前管理员的昵称、头像、邮箱及手机号。",
     *     description="浏览器认证协议：code 与 HTTP 状态一致。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="nickname", type="string", maxLength=50),
     *             @OA\Property(property="avatar", type="string", maxLength=255),
     *             @OA\Property(property="email", type="string", maxLength=60),
     *             @OA\Property(property="mobile", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminProfileUpdateResult")),
     *     @OA\Response(response=401, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response=403, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response=422, description="认证输入校验失败。", @OA\JsonContent(ref="#/components/schemas/BrowserAuthEnvelope")),
     *     @OA\Response(response="default", ref="#/components/responses/BrowserAuthError")
     * )
     */
    #[Put('')]
    public function update(Request $request): Response
    {
        return $this->execute($request, static function (Admin $admin) use ($request): array {
            $data = [];
            foreach (['nickname' => 'real_name', 'avatar' => 'avatar', 'email' => 'email', 'mobile' => 'mobile'] as $input => $field) {
                if ($request->post($input) === null) continue;
                $value = $request->post($input);
                if (!is_string($value)) throw new DomainException('个人资料必须为字符串', 422);
                $data[$field] = trim(strip_tags($value));
            }
            if (isset($data['real_name']) && ($data['real_name'] === '' || mb_strlen($data['real_name']) > 50)) {
                throw new DomainException('昵称不能为空且不能超过 50 个字符', 422);
            }
            if (isset($data['email']) && (strlen($data['email']) > 60 || ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)))) {
                throw new DomainException('邮箱格式不正确或超过 60 个字符', 422);
            }
            if (isset($data['mobile']) && $data['mobile'] !== '' && !preg_match('/\A1[3-9]\d{9}\z/', $data['mobile'])) {
                throw new DomainException('手机号格式不正确', 422);
            }
            if (isset($data['avatar']) && (strlen($data['avatar']) > 255 || ($data['avatar'] !== '' && !preg_match('~\A(?:/storage/|https?://)[^\s<>"\x00-\x1f]+\z~i', $data['avatar'])))) {
                throw new DomainException('头像地址格式不正确或过长', 422);
            }
            if ($data !== []) $admin->update($data);
            return $admin->toProfileData();
        }, '资料已更新');
    }

    /**
     * 校验原密码后修改密码，撤销全部会话并清除浏览器登录 Cookie。
     *
     * 请求字段：oldPassword、newPassword。
     *
     * @param Request $request 当前 HTTP 请求，包含会话、请求头及提交参数。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/profile/password",
     *     operationId="admin_authentication_controller_AdminProfile_password_post",
     *     tags={"管理员个人资料"},
     *     summary="校验原密码后修改密码，撤销全部会话并清除浏览器登录 Cookie。",
     *     description="浏览器认证协议：code 与 HTTP 状态一致。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"oldPassword", "newPassword"},
     *             @OA\Property(property="oldPassword", type="string", format="password", writeOnly=true),
     *             @OA\Property(property="newPassword", type="string", description="12 至 72 字节，不能与旧密码相同。", format="password", writeOnly=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminProfilePasswordResult")),
     *     @OA\Response(response=401, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response=403, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response=422, description="认证输入校验失败。", @OA\JsonContent(ref="#/components/schemas/BrowserAuthEnvelope")),
     *     @OA\Response(response="default", ref="#/components/responses/BrowserAuthError")
     * )
     */
    #[Post('/password')]
    public function password(Request $request): Response
    {
        $response = $this->execute($request, static function (Admin $admin) use ($request): null {
            $old = $request->post('oldPassword');
            $new = $request->post('newPassword');
            if (!is_string($old) || !is_string($new) || $old === '' || strlen($old) > 72 || strlen($new) < 12 || strlen($new) > 72 || str_contains($new, "\0")) {
                throw new DomainException('原密码不能为空，新密码需为 12～72 字节', 422);
            }
            return Db::transaction(static function () use ($admin, $old, $new): null {
                $current = Admin::where('id', $admin->id)->lockForUpdate()->first();
                if (!password_verify($old, $current->password)) throw new DomainException('原密码错误', 422);
                if (password_verify($new, $current->password)) throw new DomainException('新密码不能与原密码相同', 422);
                $current->update(['password' => password_hash($new, PASSWORD_DEFAULT)]);
                (new AdminSessionService())->revokeAll((int) $current->id);
                return null;
            });
        }, '密码已更新，请重新登录');
        if ($response->getStatusCode() === 200) {
            $response->cookie(BrowserAuthService::COOKIE, '', 0, '/admin', '', filter_var(getenv('COOKIE_SECURE') ?: 'false', FILTER_VALIDATE_BOOL), true, 'Lax');
        }
        return $response;
    }

    private function execute(Request $request, callable $callback, string $msg = '成功'): Response
    {
        try {
            $auth = new BrowserAuthService();
            $cookie = (string) $request->cookie(BrowserAuthService::COOKIE, '');
            $identity = $auth->user($cookie);
            if ($request->method() !== 'GET') $auth->assertCsrf($cookie, (string) $request->header('x-csrf-token', ''));
            $admin = Admin::whereNull('deleted_at')->where('id', $identity['id'])->where('status', 1)->first();
            if (!$admin) throw new DomainException('登录已失效', 401);
            return $this->result($callback($admin), $msg);
        } catch (DomainException $error) {
            return $this->result(null, $error->getMessage(), $error->getCode());
        }
    }

    private function result(mixed $data, string $msg, int $code = 200): Response
    {
        return json(compact('code', 'msg', 'data'))->withStatus($code)
            ->withHeaders(['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
