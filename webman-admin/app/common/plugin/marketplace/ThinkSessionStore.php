<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

use think\facade\Session;

final class ThinkSessionStore implements SessionStore
{
    public function get(string $key): mixed
    {
        return Session::get($key);
    }

    public function set(string $key, mixed $value): void
    {
        Session::set($key, $value);
    }

    public function delete(string $key): void
    {
        Session::delete($key);
    }
}
