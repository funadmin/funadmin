<?php

namespace think\annotation\route;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Model
{
    public function __construct(public string $value, public string $var = 'id', public bool $exception = true)
    {
    }
}
