<?php

declare(strict_types=1);

namespace app\common\model;

/** 系统升级持久任务。 */
final class UpgradeTask extends BaseModel
{
    protected $name = 'upgrade_task';

    protected $json = ['metadata'];

    protected $jsonAssoc = true;
}
