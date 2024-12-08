<?php

namespace think\annotation\route;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Options extends Route
{
    public function __construct(
        public string $rule,
        public array  $options = []
    )
    {
        parent::__construct('OPTIONS', $rule, $options);
    }
}
