<?php

namespace think\annotation\model\option;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Append
{
    public function __construct(
        public array $append
    )
    {
    }
}
