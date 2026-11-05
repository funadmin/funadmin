<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

final class ThinkSessionStore implements SessionStore
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
