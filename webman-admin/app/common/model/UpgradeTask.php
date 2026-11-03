<?php

declare(strict_types=1);

namespace app\common\model;

/** 系统升级持久任务。 */
final class UpgradeTask extends BaseModel
{
    protected $table = 'upgrade_task';

    protected $casts = ['metadata' => 'array'];
    protected $guarded = [];
}
