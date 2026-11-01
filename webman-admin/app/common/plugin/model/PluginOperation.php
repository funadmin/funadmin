<?php

declare(strict_types=1);

namespace app\common\plugin\model;

use app\common\model\BaseModel;

final class PluginOperation extends BaseModel
{
    protected $name = 'plugin_operation';

    protected $autoWriteTimestamp = true;
}
