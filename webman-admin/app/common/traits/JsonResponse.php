<?php

declare(strict_types=1);

namespace app\common\traits;

use think\Response;

/**
 * 统一 JSON 响应协议。
 */
trait JsonResponse
{
    protected function ok(string $msg = '操作成功', mixed $data = null, int $code = 200): Response
    {
        return $this->jsonResponse($msg, $data, $code);
    }

    protected function fail(string $msg = '操作失败', mixed $data = null, int $code = 400): Response
    {
        return $this->jsonResponse($msg, $data, $code);
    }

    protected function responseHeaders(): array
    {
        return [];
    }

    protected function responseHttpStatus(int $code): int
    {
        return $code;
    }

    protected function jsonResponse(string $msg, mixed $data, int $code): Response
    {
        return \app\common\http\JsonEnvelope::create($msg, $data, $code, $this->responseHttpStatus($code), $this->responseHeaders());
    }
}
