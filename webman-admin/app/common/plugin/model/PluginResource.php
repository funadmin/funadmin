<?php

declare(strict_types=1);

namespace app\common\plugin\model;

use app\common\model\BaseModel;

final class PluginResource extends BaseModel
{
    protected $table = 'plugin_resource';
    protected $guarded = [];
}
