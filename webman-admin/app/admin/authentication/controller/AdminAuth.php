<?php

namespace app\admin\authentication\controller;

use app\admin\authorization\service\AuthorizationService;
use app\admin\authentication\service\AdminSessionService;
use app\admin\authentication\service\BrowserAuthService;
use DomainException;
use support\Request;
use support\Response;

/**
 * Admin Web 认证 HTTP 适配层。
 */
final class AdminAuth
{
    public function csrf(Request $request): Response
    {
        $session = (new BrowserAuthService())->bootstrap($this->cookie($request));
        return $this->attachCookie($this->result(['csrfToken' => $session['csrfToken'], 'captchaEnabled' => false]), $session);
    }

    public function login(Request $request): Response
    {
        try {
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

    public function me(Request $request): Response
    {
        try {
            $admin = (new BrowserAuthService())->user($this->cookie($request));
            return $this->result((new AuthorizationService())->userInfo($admin));
        } catch (DomainException $error) {
            return $this->result(null, $error->getMessage(), $error->getCode());
        }
    }

    public function menus(Request $request): Response
    {
        try {
            $admin = (new BrowserAuthService())->user($this->cookie($request));
            return $this->result((new AuthorizationService())->menus($admin));
        } catch (DomainException $error) {
            return $this->result(null, $error->getMessage(), $error->getCode());
        }
    }

    public function logout(Request $request): Response
    {
        try {
            (new BrowserAuthService())->logout($this->cookie($request), $request->header('x-csrf-token', ''));
            return $this->result(null, '退出成功')->cookie(BrowserAuthService::COOKIE, '', time() - 3600, '/admin', '', $this->secure(), true, 'Lax');
        } catch (DomainException $error) {
            return $this->result(null, $error->getMessage(), $error->getCode());
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
        return $response->cookie(BrowserAuthService::COOKIE, $session['cookie'], 0, '/admin', '', $this->secure(), true, 'Lax')
            ->withHeader('X-CSRF-TOKEN', $session['csrfToken']);
    }

    private function result(mixed $data, string $msg = '成功', int $status = 200): Response
    {
        return json(['code' => $status, 'msg' => $msg, 'data' => $data])->withStatus($status)
            ->withHeaders(['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
