<?php

declare(strict_types=1);

namespace app\admin\http;

use app\common\http\JsonEnvelope;
use think\response\Json;

/** 仅由明确后台业务出口调用，不检查请求路径或改写已有响应。 */
final class AdminResponse
{
    public static function httpStatus(int $code): int
    {
        return in_array($code, [400, 422], true) ? 200 : $code;
    }

    public static function create(string $msg, mixed $data, int $code, ?int $httpStatus = null, array $headers = []): Json
    {
        $httpStatus ??= self::httpStatus($code);
        if ($httpStatus >= 500) {
            $msg = '服务器内部错误';
            $data = null;
        }
        return JsonEnvelope::create($msg, $data, $code, $httpStatus, $headers);
    }
}
