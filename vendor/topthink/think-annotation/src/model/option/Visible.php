<?php

namespace think\annotation\model\option;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Visible
{
    public function __construct(
        public array $visible
    )
    {
    }
}
