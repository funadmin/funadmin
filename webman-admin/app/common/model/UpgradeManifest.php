<?php

declare(strict_types=1);

namespace app\common\model;

/** 服务端验证后短期保存的一次性升级 manifest。 */
final class UpgradeManifest extends BaseModel
{
    protected $name = 'upgrade_manifest';

    protected $json = ['payload'];

    protected $jsonAssoc = true;
}
