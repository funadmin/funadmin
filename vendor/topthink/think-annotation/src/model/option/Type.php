<?php

namespace think\annotation\model\option;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Type
{
    public function __construct(
        public string $name,
        public string $type,
    )
    {
    }
}
