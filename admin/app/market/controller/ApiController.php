<?php

declare(strict_types=1);

namespace app\market\controller;

use app\BaseController;
use app\market\service\MarketTokenService;
use support\Response;

/** /api/v3 响应约定：业务码与 HTTP 状态一致，成功时 code=200 且 data 为对象。 */
abstract class ApiController extends BaseController
{
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
        return (new \app\common\service\authentication\BearerTokenExtractor())->extract($this->request) ?? '';
    }

    protected function memberId(): ?int
    {
        $token = $this->token();
        return $token === '' ? null : (new MarketTokenService())->memberId($token);
    }

    /** @return array{id:int, username:string, nickname:string, avatar:string}|null */
    protected function member(): ?array {
        $memberId = $this->memberId();
        return $memberId === null ? null : (new MarketTokenService())->account($memberId);
    }

    protected function context(): array
    {
        return [
            'manifest_schema' => (int) $this->request->input('manifest_schema', 0),
            'package_format' => (string) $this->request->input('package_format', ''),
            'platform_version' => (string) $this->request->input('platform_version', ''),
            'php_version' => (string) $this->request->input('php_version', ''),
        ];
    }

    protected function unauthorized(): Response
    {
        return $this->error(401, '市场账号未登录或令牌已过期');
    }
}
