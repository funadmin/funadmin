<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

/** 服务端验证后短期保存的一次性升级 manifest。 */
final class UpgradeManifest extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    protected $table = 'upgrade_manifest';
    protected $guarded = [];

    protected $casts = ['payload' => 'array'];
}
