<?php

namespace think\annotation\route;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Resource
{
    public function __construct(public string $rule, public array $options = [])
    {
    }
}
