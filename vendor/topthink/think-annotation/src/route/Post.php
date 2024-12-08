<?php

namespace think\annotation\route;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Post extends Route
{
    public function __construct(
        public string $rule,
        public array  $options = []
    )
    {
        parent::__construct('POST', $rule, $options);
    }
}
