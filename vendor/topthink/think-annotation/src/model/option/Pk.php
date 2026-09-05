<?php

namespace think\annotation\model\option;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Pk
{
    public function __construct(
        public string $name
    )
    {
    }
}
