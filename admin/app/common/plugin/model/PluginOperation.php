<?php

declare(strict_types=1);

namespace app\common\plugin\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

final class PluginOperation extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    protected $table = 'plugin_operation';

    protected $guarded = [];
}
