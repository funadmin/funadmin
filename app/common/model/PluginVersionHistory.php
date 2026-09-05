<?php

declare(strict_types=1);

namespace app\common\model;

final class PluginVersionHistory extends BaseModel
{
    protected $name = 'plugin_version_history';

    protected $autoWriteTimestamp = true;
}
