<?php

namespace think\annotation\route;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        #[ExpectedValues(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD', '*'])]
        public string $method,
        public string $rule,
        public array  $options = []
    )
    {

    }

}
