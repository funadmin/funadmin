<?php

declare(strict_types=1);

namespace app\console\validate\plugin\shop;

use think\Validate;

final class ProductValidate extends Validate
{
    protected $rule = array (
  'name' => 'require|require|max:120',
  'price' => 'require|egt:0|require|number|min:0',
  'status' => 'require|integer',
);

    public function forUpdate(int|string $id): self
    {
        foreach ($this->rule as &$rule) {
            $rule = str_replace('{id}', (string) $id, $rule);
        }
        return $this;
    }
}

