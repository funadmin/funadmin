<?php

declare(strict_types=1);

namespace app;

use DomainException;
use Throwable;
use Webman\Exception\ExceptionHandler;
use Webman\Http\Request;
use Webman\Http\Response;

class ExceptionHandle extends ExceptionHandler
{
    public function render(Request $request, Throwable $exception): Response
    {
        $status = 500;
        $message = '服务暂时不可用';
        if ($exception instanceof DomainException && in_array($exception->getCode(), [400, 401, 403, 404, 409, 422, 429], true)) {
            $status = $exception->getCode();
            $message = $exception->getMessage();
        }
        return new Response($status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], json_encode(['code' => $status, 'msg' => $message, 'data' => null], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
