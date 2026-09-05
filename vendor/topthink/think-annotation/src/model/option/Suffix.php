<?php

namespace think\annotation\model\option;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Suffix
{
    public function __construct(
        public string $suffix
    )
    {
    }
}
