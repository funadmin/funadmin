<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\BaseModel;

/** PHP 迁移文件执行记录（database/migrations/*.php）。 */
final class PhpMigration extends BaseModel
{
    public $timestamps = false;
    protected $table = 'migrations';
    protected $guarded = [];

}
