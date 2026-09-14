<?php

declare(strict_types=1);

namespace app\console\traits;

use app\common\traits\JsonResponse;
use think\facade\Session;

/**
 * 管理后台 JSON 响应协议。
 */
trait AdminJsonResponse
{
    use JsonResponse;

    protected function responseHttpStatus(int $code): int
    {
        return \app\console\http\AdminResponse::httpStatus($code);
    }

    protected function jsonResponse(string $msg, mixed $data, int $code): \think\Response
    {
        return \app\console\http\AdminResponse::create($msg, $data, $code, $this->responseHttpStatus($code), $this->responseHeaders());
    }

    protected function responseHeaders(): array
    {
        return ['X-CSRF-TOKEN' => (string) Session::get('__token__', '')];
    }
}
