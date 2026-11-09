<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

/** 插件市场账号会话存储：读写当前 webman 请求的 session。 */
final class RequestSessionStore implements SessionStore
{
    public function get(string $key): mixed
    {
        return request()->session()->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        request()->session()->set($key, $value);
    }

    public function delete(string $key): void
    {
        request()->session()->delete($key);
    }
}
