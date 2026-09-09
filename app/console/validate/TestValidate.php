<?php

declare(strict_types=1);

namespace app\console\validate;

use think\Validate;

final class TestValidate extends Validate
{
    protected array $rule = array (
);

    public function forUpdate(int|string $id): self
    {
        foreach ($this->rule as &$rule) {
            $rule = str_replace('{id}', (string) $id, $rule);
        }
        return $this;
    }
}

