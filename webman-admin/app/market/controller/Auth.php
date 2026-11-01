<?php

declare(strict_types=1);

namespace app\market\controller;

use app\market\service\MarketAccountService;
use app\market\service\MarketTokenService;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;

#[Group('api/v3/auth', ['complete_match' => true])]
final class Auth extends ApiController
{
    #[Post('login')]
    public function login(): Response
    {
        $account = trim((string) $this->request->param('account', ''));
        $password = (string) $this->request->param('password', '');
        if ($account === '' || $password === '' || mb_strlen($account) > 100 || strlen($password) > 200) {
            return $this->error(422, '请输入市场账号和密码');
        }
        $result = (new MarketTokenService())->login($account, $password);
        if ($result === null || $result['account'] === null) {
            return $this->error(401, '账号或密码错误');
        }
        return $this->success($result, '登录成功');
    }

    #[Post('refresh')]
    public function refresh(): Response
    {
        $result = (new MarketTokenService())->refresh((string) $this->request->param('refresh_token', ''));
        return $result === null ? $this->error(401, '刷新令牌无效或已过期，请重新登录') : $this->success($result);
    }

    #[Post('register')]
    public function register(): Response
    {
        $payload = [
            'username' => trim((string) $this->request->param('username', '')),
            'email' => trim((string) $this->request->param('email', '')),
            'nickname' => trim((string) $this->request->param('nickname', '')),
            'password' => (string) $this->request->param('password', ''),
            'password_confirm' => (string) $this->request->param('password_confirm', ''),
        ];
        if ($payload['username'] === '' || $payload['email'] === '' || $payload['password'] === '') {
            return $this->error(422, '请完整填写注册信息');
        }
        try {
            (new MarketAccountService())->register($payload, (string) $this->request->ip());
            $account = $payload['username'];
            $tokens = (new MarketTokenService())->login($account, $payload['password']);
            return $tokens === null ? $this->error(500, '注册成功但无法创建登录会话') : $this->success($tokens + ['account' => (new MarketTokenService())->account((int) MarketAccountService::currentId())], '注册成功');
        } catch (\RuntimeException $exception) {
            return $this->error(422, $exception->getMessage());
        }
    }

    private function revokeCurrentToken(): void
    {
        $token = $this->token();
        if ($token !== '') {
            (new MarketTokenService())->revoke($token);
        }
    }

    #[Post('me')]
    public function me(): Response
    {
        $memberId = $this->memberId();
        $account = $memberId === null ? null : (new MarketAccountService())->profile($memberId);
        return $account === null ? $this->unauthorized() : $this->success(['account' => $account]);
    }

    #[Put('profile')]
    public function profile(): Response
    {
        $memberId = $this->memberId();
        if ($memberId === null) {
            return $this->unauthorized();
        }
        try {
            (new MarketAccountService())->updateProfile($memberId, [
                'nickname' => trim((string) $this->request->param('nickname', '')),
                'motto' => trim((string) $this->request->param('motto', '')),
                'avatar' => trim((string) $this->request->param('avatar', '')),
            ]);
            $account = (new MarketAccountService())->profile($memberId);
            return $account === null ? $this->unauthorized() : $this->success(['account' => $account], '资料已更新');
        } catch (\RuntimeException $exception) {
            return $this->error(422, $exception->getMessage());
        }
    }

    #[Post('password')]
    public function password(): Response
    {
        $memberId = $this->memberId();
        if ($memberId === null) {
            return $this->unauthorized();
        }
        try {
            (new MarketAccountService())->changePassword(
                $memberId,
                (string) $this->request->param('current', ''),
                (string) $this->request->param('password', ''),
                (string) $this->request->param('password_confirm', '')
            );
            $this->revokeCurrentToken();
            return $this->success(['logged_out' => true], '密码已修改，请重新登录');
        } catch (\RuntimeException $exception) {
            return $this->error(422, $exception->getMessage());
        }
    }

    #[Post('logout')]
    public function logout(): Response
    {
        $token = $this->token();
        if ($token !== '') {
            (new MarketTokenService())->revoke($token);
        }
        return $this->success(['logged_out' => true]);
    }
}
