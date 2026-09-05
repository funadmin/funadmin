<?php

namespace think\annotation\model\option;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class CreateTime
{
    public function __construct(
        public string $name
    )
    {
    }
}
