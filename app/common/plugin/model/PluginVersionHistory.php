<?php

declare(strict_types=1);

namespace app\common\plugin\model;

use app\common\model\BaseModel;

final class PluginVersionHistory extends BaseModel
{
    protected $name = 'plugin_version_history';

    protected $autoWriteTimestamp = true;
}
