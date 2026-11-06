<?php

declare(strict_types=1);

namespace app\api\controller\v3;

use app\BaseController;
use app\common\service\authentication\BearerTokenExtractor;
use app\common\service\member\MemberAccountService;
use app\common\service\member\MemberTokenService;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\Response;

#[RouteGroup('/api/v3/auth')]
final class Auth extends BaseController
{
    #[Post('/login')]
    public function login(): Response
    {
        $account = trim((string) $this->request->input('account', ''));
        $password = (string) $this->request->input('password', '');
        if ($account === '' || $password === '' || mb_strlen($account) > 100 || strlen($password) > 200) {
            return $this->error(422, '请输入会员账号和密码');
        }
        $result = (new MemberTokenService())->login($account, $password);
        if ($result === null || $result['account'] === null) {
            return $this->error(401, '账号或密码错误');
        }
        return $this->success($result, '登录成功');
    }

    #[Post('/refresh')]
    public function refresh(): Response
    {
        $result = (new MemberTokenService())->refresh((string) $this->request->input('refresh_token', ''));
        return $result === null ? $this->error(401, '刷新令牌无效或已过期，请重新登录') : $this->success($result);
    }

    #[Post('/register')]
    public function register(): Response
    {
        $payload = [
            'username' => trim((string) $this->request->input('username', '')),
            'email' => trim((string) $this->request->input('email', '')),
            'nickname' => trim((string) $this->request->input('nickname', '')),
            'password' => (string) $this->request->input('password', ''),
            'password_confirm' => (string) $this->request->input('password_confirm', ''),
        ];
        if ($payload['username'] === '' || $payload['email'] === '' || $payload['password'] === '') {
            return $this->error(422, '请完整填写注册信息');
        }
        try {
            (new MemberAccountService())->register($payload, (string) $this->request->getRemoteIp());
            $account = $payload['username'];
            $tokens = (new MemberTokenService())->login($account, $payload['password']);
            return $tokens === null ? $this->error(500, '注册成功但无法创建登录会话') : $this->success($tokens + ['account' => (new MemberTokenService())->account((int) MemberAccountService::currentId())], '注册成功');
        } catch (\RuntimeException $exception) {
            return $this->error(422, $exception->getMessage());
        }
    }

    private function revokeCurrentToken(): void
    {
        $token = $this->token();
        if ($token !== '') {
            (new MemberTokenService())->revoke($token);
        }
    }

    #[Post('/me')]
    public function me(): Response
    {
        $memberId = $this->memberId();
        $account = $memberId === null ? null : (new MemberAccountService())->profile($memberId);
        return $account === null ? $this->unauthorized() : $this->success(['account' => $account]);
    }

    #[Put('/profile')]
    public function profile(): Response
    {
        $memberId = $this->memberId();
        if ($memberId === null) {
            return $this->unauthorized();
        }
        try {
            (new MemberAccountService())->updateProfile($memberId, [
                'nickname' => trim((string) $this->request->input('nickname', '')),
                'motto' => trim((string) $this->request->input('motto', '')),
                'avatar' => trim((string) $this->request->input('avatar', '')),
            ]);
            $account = (new MemberAccountService())->profile($memberId);
            return $account === null ? $this->unauthorized() : $this->success(['account' => $account], '资料已更新');
        } catch (\RuntimeException $exception) {
            return $this->error(422, $exception->getMessage());
        }
    }

    #[Post('/password')]
    public function password(): Response
    {
        $memberId = $this->memberId();
        if ($memberId === null) {
            return $this->unauthorized();
        }
        try {
            (new MemberAccountService())->changePassword(
                $memberId,
                (string) $this->request->input('current', ''),
                (string) $this->request->input('password', ''),
                (string) $this->request->input('password_confirm', '')
            );
            $this->revokeCurrentToken();
            return $this->success(['logged_out' => true], '密码已修改，请重新登录');
        } catch (\RuntimeException $exception) {
            return $this->error(422, $exception->getMessage());
        }
    }

    #[Post('/logout')]
    public function logout(): Response
    {
        $token = $this->token();
        if ($token !== '') {
            (new MemberTokenService())->revoke($token);
        }
        return $this->success(['logged_out' => true]);
    }

    /** Compatibility envelope for the existing public-member opaque-token endpoints. */
    protected function success(array|object $data, string $msg = 'success'): Response
    {
        return json(['code' => 200, 'msg' => $msg, 'data' => $data]);
    }

    protected function error(int $code, string $msg): Response
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => null])->withStatus($code);
    }

    protected function token(): string
    {
        return (new BearerTokenExtractor())->extract($this->request) ?? '';
    }

    protected function memberId(): ?int
    {
        $token = $this->token();
        return $token === '' ? null : (new MemberTokenService())->memberId($token);
    }

    protected function unauthorized(): Response
    {
        return $this->error(401, '会员未登录或令牌已过期');
    }
}
