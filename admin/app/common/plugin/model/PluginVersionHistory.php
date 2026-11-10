<?php

declare(strict_types=1);

namespace app\common\plugin\model;

use app\common\model\BaseModel;

final class PluginVersionHistory extends BaseModel
{
    protected $table = 'plugin_version_history';
    protected $guarded = [];

}
