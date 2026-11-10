<?php

declare(strict_types=1);

namespace app\common\http;

use Closure;
use Workerman\Protocols\Http\Request;

/**
 * 请求级记忆：值保存在当前请求对象的 context 中，随请求对象一起销毁，不跨请求、不进静态变量。
 * 没有请求（命令行、队列）时不记忆，每次都重新计算。
 */
final class RequestMemo
{
    public static function remember(string $scope, string $key, Closure $build): mixed
    {
        $request = self::request();
        if ($request === null) {
            return $build();
        }
        if (isset($request->context[$scope]) && array_key_exists($key, $request->context[$scope])) {
            return $request->context[$scope][$key];
        }
        $value = $build();
        $request->context[$scope][$key] = $value;
        return $value;
    }

    public static function forget(string $scope): void
    {
        $request = self::request();
        if ($request !== null) {
            unset($request->context[$scope]);
        }
    }

    private static function request(): ?Request
    {
        $request = \request();
        return $request instanceof Request ? $request : null;
    }
}
