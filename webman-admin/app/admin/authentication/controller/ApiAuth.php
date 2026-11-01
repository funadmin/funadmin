<?php

declare(strict_types=1);

namespace app\admin\authentication\controller;

use app\admin\authentication\service\AdminSessionService;
use DomainException;
use support\Request;
use support\Response;

final class ApiAuth
{
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

    public function me(Request $request): Response
    {
        try {
            return $this->result(200, '成功', (new AdminSessionService())->authenticate($this->token($request)));
        } catch (DomainException $error) {
            return $this->result($error->getCode(), $error->getMessage());
        }
    }

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
