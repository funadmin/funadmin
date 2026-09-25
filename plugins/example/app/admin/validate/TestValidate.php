<?php

declare(strict_types=1);

namespace app\admin\validate\plugin\example;

use think\Validate;

final class TestValidate extends Validate
{
    protected $rule = array (
);

    public function forUpdate(int|string $id, array $data = []): self
    {
        foreach (array (
) as $field) {
            if (!array_key_exists($field, $data)) unset($this->rule[$field]);
        }
        foreach ($this->rule as &$rule) {
            $rule = str_replace('{id}', (string) $id, $rule);
        }
        return $this;
    }
}

