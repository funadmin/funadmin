<?php

declare(strict_types=1);

namespace tests\fixtures\plugins\example\controller;

use think\Response;

final class Index
{
    public function ping(): Response
    {
        return json(['code' => 200, 'data' => ['plugin' => 'example']]);
    }
}
