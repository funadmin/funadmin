<?php

declare(strict_types=1);

namespace app\market\controller;

use app\market\service\MarketAccountService;
use think\annotation\route\Get;
use think\annotation\route\Post;
use think\captcha\facade\Captcha;
use think\Response;

final class Account extends StoreController
{
    #[Get('login', ['complete_match' => true])]
    public function loginForm(): Response
    {
        if ($this->member !== null) {
            return $this->to('/user');
        }
        return $this->render('store/login', ['title' => '登录', 'next' => $this->safeNext((string) $this->request->get('next', '')), 'account' => '']);
    }

    #[Post('login', ['complete_match' => true])]
    public function login(): Response
    {
        $next = $this->safeNext((string) $this->request->post('next', ''));
        $account = mb_substr(trim((string) $this->request->post('account', '')), 0, 100);
        $error = $this->validateForm();
        if ($error === null) {
            try {
                (new MarketAccountService())->login($account, (string) $this->request->post('password', ''), (string) $this->request->ip());
                return redirect($next);
            } catch (\RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
        $this->flash($error, true);
        return $this->render('store/login', ['title' => '登录', 'next' => $next, 'account' => $account], 422);
    }

    #[Get('register', ['complete_match' => true])]
    public function registerForm(): Response
    {
        if ($this->member !== null) {
            return $this->to('/user');
        }
        return $this->render('store/register', ['title' => '注册', 'form' => ['username' => '', 'email' => '', 'nickname' => '']]);
    }

    #[Post('register', ['complete_match' => true])]
    public function register(): Response
    {
        $form = [
            'username' => mb_substr(trim((string) $this->request->post('username', '')), 0, 40),
            'email' => mb_substr(trim((string) $this->request->post('email', '')), 0, 80),
            'nickname' => mb_substr(trim((string) $this->request->post('nickname', '')), 0, 60),
        ];
        $error = $this->validateForm();
        if ($error === null && !$this->request->post('agree')) {
            $error = '请阅读并同意用户协议';
        }
        if ($error === null) {
            try {
                (new MarketAccountService())->register($form + [
                    'password' => (string) $this->request->post('password', ''),
                    'password_confirm' => (string) $this->request->post('password_confirm', ''),
                ], (string) $this->request->ip());
                $this->flash('注册成功，欢迎加入！');
                return $this->to('/user');
            } catch (\RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
        $this->flash($error, true);
        return $this->render('store/register', ['title' => '注册', 'form' => $form], 422);
    }

    #[Post('logout', ['complete_match' => true])]
    public function logout(): Response
    {
        if ($this->checkCsrf()) {
            (new MarketAccountService())->logout();
        }
        return $this->to('');
    }

    #[Get('captcha', ['complete_match' => true])]
    public function captcha(): Response
    {
        return Captcha::create()->header(['Cache-Control' => 'no-store']);
    }

    private function validateForm(): ?string
    {
        if (!$this->checkCsrf()) {
            return '页面已过期，请刷新后重试';
        }
        if (!captcha_check((string) $this->request->post('captcha', ''))) {
            return '验证码错误或已过期';
        }
        return null;
    }
}
