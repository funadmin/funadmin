<?php

declare(strict_types=1);

namespace app\common\http;

use think\response\Json;

/** 无 Session 的业务信封构造器，业务码与传输状态独立。 */
final class JsonEnvelope
{
    public static function create(string $msg, mixed $data, int $code, int $httpStatus, array $headers = []): Json
    {
        return json(['code' => $code, 'msg' => $msg, 'time' => time(), 'data' => $data], $httpStatus)->header($headers);
    }
}
