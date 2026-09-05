<?php

declare(strict_types=1);

namespace app\common\traits;

use think\Response;

/**
 * 统一 JSON 响应协议。
 */
trait JsonResponse
{
    protected function ok(mixed $data = null, string $msg = '操作成功'): Response
    {
        return $this->jsonResponse(200, $msg, $data);
    }

    protected function fail(string $msg, int $code = 400, mixed $data = null): Response
    {
        return $this->jsonResponse($code, $msg, $data, $code);
    }

    protected function responseHeaders(): array
    {
        return [];
    }

    private function jsonResponse(int $code, string $msg, mixed $data, int $httpCode = 200): Response
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ], $httpCode)->header($this->responseHeaders());
    }
}
