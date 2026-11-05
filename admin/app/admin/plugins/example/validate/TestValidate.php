<?php

declare(strict_types=1);

namespace app\admin\plugins\example\validate;

final class TestValidate
{
    public function forUpdate(int|string $id, array $data = []): self
    {
        return $this;
    }

    public function check(array $data): bool { return true; }
    public function getError(): string { return ''; }
}
