<?php

declare(strict_types=1);

namespace app\admin\common\traits;

use app\common\traits\JsonResponse;
use support\Response;

/**
 * 管理后台 JSON 响应协议。
 */
trait AdminJsonResponse
{
    use JsonResponse;

    protected function responseHttpStatus(int $code): int
    {
        return \app\admin\common\http\AdminResponse::httpStatus($code);
    }

    protected function jsonResponse(string $msg, mixed $data, int $code): Response
    {
        return \app\admin\common\http\AdminResponse::create($msg, $data, $code, $this->responseHttpStatus($code), $this->responseHeaders());
    }

    protected function responseHeaders(): array
    {
        $token = (string) ($this->request->context['admin_csrf_token'] ?? '');
        return $token === '' ? [] : ['X-CSRF-TOKEN' => $token];
    }
}
