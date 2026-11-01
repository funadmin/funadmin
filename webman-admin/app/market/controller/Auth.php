<?php

declare(strict_types=1);

namespace app\market\controller;

use app\market\service\MarketTokenService;
use think\annotation\route\Group;
use think\annotation\route\Post;
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
}
