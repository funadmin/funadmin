<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

interface SessionStore
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function delete(string $key): void;
}
