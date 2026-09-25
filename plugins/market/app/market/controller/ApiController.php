<?php

declare(strict_types=1);

namespace app\market\controller;

use app\BaseController;
use app\market\service\MarketTokenService;
use think\Response;

/** /api/v3 响应约定：业务码与 HTTP 状态一致，成功时 code=200 且 data 为对象。 */
abstract class ApiController extends BaseController
{
    protected function success(array|object $data, string $msg = 'success'): Response
    {
        return json(['code' => 200, 'msg' => $msg, 'data' => $data], 200);
    }

    protected function error(int $code, string $msg): Response
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => null], $code);
    }

    /** @return array{id:int, username:string, nickname:string, avatar:string}|null */
    protected function member(): ?array
    {
        $header = trim((string) $this->request->header('authorization', ''));
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return null;
        }
        return (new MarketTokenService())->authenticate($matches[1]);
    }

    protected function context(): array
    {
        return [
            'manifest_schema' => (int) $this->request->param('manifest_schema', 0),
            'package_format' => (string) $this->request->param('package_format', ''),
            'platform_version' => (string) $this->request->param('platform_version', ''),
            'php_version' => (string) $this->request->param('php_version', ''),
        ];
    }

    protected function unauthorized(): Response
    {
        return $this->error(401, '市场账号未登录或令牌已过期');
    }
}
