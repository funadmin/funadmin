<?php

namespace think\annotation\route;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Middleware
{
    public array $params;

    public function __construct(public $value, ...$params)
    {
        $this->params = $params;
    }
}
