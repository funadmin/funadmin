<?php

declare(strict_types=1);

namespace Tests\Support;

use support\annotation\route\Get;

/** OpenApiCoverageTest 的样例：带路由属性的 trait，检查器必须拒绝。 */
trait OpenApiCoverageRoutedTraitFixture
{
    #[Get('/from-trait')]
    public function fromTrait(): void
    {
    }
}
