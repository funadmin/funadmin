<?php

declare(strict_types=1);

namespace app\common\plugin\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

final class PluginVersionHistory extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    protected $table = 'plugin_version_history';
    protected $guarded = [];
}
