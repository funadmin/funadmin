<?php

namespace think\annotation\model\option;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Hidden
{
    public function __construct(
        public array $hidden
    )
    {
    }
}
