<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

/** 系统升级持久任务。 */
final class UpgradeTask extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    protected $table = 'upgrade_task';

    protected $casts = ['metadata' => 'array'];
    protected $guarded = [];
}
