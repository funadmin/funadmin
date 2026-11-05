<?php

declare(strict_types=1);

namespace app\admin\authentication\controller;

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
    #[Get('')]
    public function index(Request $request): Response
    {
        return $this->execute($request, static fn (Admin $admin): array => $admin->toProfileData());
    }

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
                $current->update(['password' => password_hash($new, PASSWORD_DEFAULT), 'token' => null]);
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
