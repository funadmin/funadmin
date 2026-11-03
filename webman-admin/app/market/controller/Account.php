<?php

declare(strict_types=1);

namespace app\market\controller;

use app\market\service\MarketAccountService;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;

#[RouteGroup('/market')]
final class Account extends StoreController
{
    #[Get('/login')]
    public function loginForm(): Response
    {
        if ($this->member !== null) {
            return $this->to('/user');
        }
        return $this->render('store/login', ['title' => '登录', 'next' => $this->safeNext((string) $this->request->get('next', '')), 'account' => '']);
    }

    #[Post('/login')]
    public function login(): Response
    {
        $next = $this->safeNext((string) $this->request->post('next', ''));
        $account = mb_substr(trim((string) $this->request->post('account', '')), 0, 100);
        $error = $this->validateForm();
        if ($error === null) {
            try {
                (new MarketAccountService())->login($account, (string) $this->request->post('password', ''), (string) $this->request->getRemoteIp());
                return redirect($next);
            } catch (\RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
        $this->flash($error, true);
        return $this->render('store/login', ['title' => '登录', 'next' => $next, 'account' => $account], 422);
    }

    #[Get('/register')]
    public function registerForm(): Response
    {
        if ($this->member !== null) {
            return $this->to('/user');
        }
        return $this->render('store/register', ['title' => '注册', 'form' => ['username' => '', 'email' => '', 'nickname' => '']]);
    }

    #[Post('/register')]
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
                ], (string) $this->request->getRemoteIp());
                $this->flash('注册成功，欢迎加入！');
                return $this->to('/user');
            } catch (\RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
        $this->flash($error, true);
        return $this->render('store/register', ['title' => '注册', 'form' => $form], 422);
    }

    #[Post('/logout')]
    public function logout(): Response
    {
        if ($this->checkCsrf()) {
            (new MarketAccountService())->logout();
        }
        return $this->to('');
    }

    #[Get('/captcha')]
    public function captcha(): Response
    {
        $captcha = new \Webman\Captcha\CaptchaBuilder();
        $captcha->build();
        request()->session()->set('market_captcha', strtolower($captcha->getPhrase()));
        return response($captcha->get(), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store']);
    }

    private function validateForm(): ?string
    {
        if (!$this->checkCsrf()) {
            return '页面已过期，请刷新后重试';
        }
        $expected = (string) request()->session()->pull('market_captcha', '');
        $captcha = strtolower((string) $this->request->post('captcha', ''));
        if ($expected === '' || $captcha === '' || !hash_equals($expected, $captcha)) {
            return '验证码错误或已过期';
        }
        return null;
    }
}
