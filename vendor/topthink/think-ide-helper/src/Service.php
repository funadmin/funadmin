<?php

namespace think\ide;

use think\ide\console\ModelCommand;

class Service extends \think\Service
{
    public function boot()
    {
        $this->commands([ModelCommand::class]);
    }
}
